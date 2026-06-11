<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Helper\Formatter;


use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\LlmGuardrails;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\SubResultFormatters\AbstractFormatter;

/**
 * AnalyticsFormatter - Formats analytics query results
 */

class AnalyticsFormatter extends AbstractFormatter
{
  /**
   * @var \ClicShopping\OM\Language Language instance for translations
   */
  private $language;
  
  /**
   * @var string Current language code
   */
  private string $languageCode;
  
  /**
   * Constructor
   * 
   * @param bool $debug Enable debug mode
   * @param bool $displaySql Display SQL queries
   */
  public function __construct(bool $debug = false, bool $displaySql = false)
  {
    parent::__construct($debug, $displaySql);
    
    // Initialize language
    $this->language = Registry::get('Language');
    $this->languageCode = $this->language->get('code');
    
    // Load language definitions (null = use current user language)
    DomainConfig::loadAgnosticLanguageFile('rag_formatters', null);
  }
  
  public function canHandle(array $results): bool
  {
    $type = $results['type'] ?? '';
    return in_array($type, ['analytics_results', 'analytics_response'], true);
  }

  public function format(array $results): array
  {
    $question = $results['question'] ?? $results['query'] ?? 'Unknown request';

    // DEBUG: Log what we receive
    if ($this->debug) {
      error_log("=== ANALYTICS FORMATTER DEBUG ===");
      error_log("Results keys: " . implode(', ', array_keys($results)));
      error_log("Has 'results': " . (isset($results['results']) ? 'YES' : 'NO'));
      error_log("Has 'data_results': " . (isset($results['data_results']) ? 'YES' : 'NO'));
      
      $dataRows = $results['results'] ?? $results['data_results'] ?? [];
      if (!empty($dataRows)) {
        error_log("Data rows is array: " . (is_array($dataRows) ? 'YES' : 'NO'));
        error_log("Data rows count: " . count($dataRows));
        if (is_array($dataRows) && !empty($dataRows)) {
          error_log("First row keys: " . implode(', ', array_keys($dataRows[0])));
        }
      } else {
        error_log("No data rows found");
      }
    }

    if (isset($results['multiple_results']) && is_array($results['multiple_results'])) {
      return $this->formatMultipleResults($results);
    }

    $output = "<div class='analytics-results'>";
    $output .= "<h4>" . $this->language->getDef('text_rag_analytics_results_for') . " " . htmlspecialchars($question) . "</h4>";

    // Display source attribution
    if (isset($results['source_attribution'])) {
      $sourceAttribution = $this->normalizeSourceAttribution($results['source_attribution']);
      $output .= $this->formatSourceAttribution($sourceAttribution);
    }

    // SQL query display
    if ($this->displaySql && isset($results['sql_query'])) {
      $output .= $this->formatSqlQuery($results['sql_query']);
    }

    // Interpretation
    $interpretationText = '';
    $isHtmlContent = false;
    
    if (isset($results['text_response']) && !empty($results['text_response'])) {
      $interpretationText = $results['text_response'];
      // Check if text_response contains HTML
      $isHtmlContent = (str_contains($interpretationText, '<div') || str_contains($interpretationText, '<p>'));
    } elseif (isset($results['interpretation']) && $results['interpretation'] !== 'Array') {
      $interpretationText = $results['interpretation'];
    }

    if (!empty($interpretationText)) {
      if ($isHtmlContent) {
        $output .= "<div class='interpretation'>" . $interpretationText . "</div>";
      } else {
        // Plain text - apply HTML encoding
        $output .= "<div class='interpretation'><strong>" . $this->language->getDef('text_rag_analytics_interpretation') . "</strong> " 
                . Hash::displayDecryptedDataText($interpretationText) . "</div>";
      }
    }

    // Guardrails — reuse the evaluation already computed by the validation gate (Volet B) when
    // present, to avoid a second LLM call; otherwise compute it here (default path, gate OFF).
    $output .= "<div class='mt-2'></div>";
    $lmGuardrails = $results['validation_evaluation']
      ?? LlmGuardrails::checkGuardrails($question, Hash::displayDecryptedDataText($interpretationText));

    if (is_array($lmGuardrails)) {
      $output .= $this->formatGuardrailsMetrics($lmGuardrails);
    } else {
      $output .= "<div class='alert alert-warning'>" . htmlspecialchars($lmGuardrails) . "</div>";
    }

    $output .= "<div class='mt-2'></div>";

    // Data table - Support both 'results' and 'data_results' keys
    $dataRows = $results['results'] ?? $results['data_results'] ?? [];
    
    if (is_array($dataRows) && !empty($dataRows)) {
      $output .= $this->formatDataTable($dataRows);
    } else {
      $output .= "<div class='alert alert-info'>";
      $output .= "<strong>" . $this->language->getDef('text_rag_analytics_note') . "</strong> " . $this->language->getDef('text_rag_analytics_detailed_data_available');
      $output .= "</div>";
    }

    $output .= "</div>";

    // Save audit data
    $auditExtra = [
      'embeddings_context' => $results['embeddings_context'] ?? [],
      'similarity_scores'  => $results['similarity_scores'] ?? [],
      'processing_chain'   => $results['processing_chain'] ?? []
    ];
    Gpt::saveData($question, $output, $auditExtra);

    return [
      'type' => 'formatted_results',
      'content' => $output
    ];
  }

  /**
   * Format multiple query results with clear separation
   * 
   * Each sub-query gets its own section, even if one fails
   *
   * @param array $results Results containing multiple_results array
   * @return array Formatted output
   */
  private function formatMultipleResults(array $results): array
  {
    $originalQuery = $results['question'] ?? $results['query'] ?? 'Unknown request';
    $multipleResults = $results['multiple_results'] ?? [];
    $queryCount = count($multipleResults);

    $output = "<div class='analytics-results multiple-queries'>";
    $output .= "<h4>" . $this->language->getDef('text_rag_analytics_results_for') . " " . htmlspecialchars($originalQuery) . "</h4>";
    $output .= "<div class='alert alert-info'>";
    $output .= "<strong>" . $this->language->getDef('text_rag_analytics_note') . "</strong> " . str_replace('{count}', $queryCount, $this->language->getDef('text_rag_analytics_note_multiple_queries'));
    $output .= "</div>";

    // Display source attribution
    if (isset($results['source_attribution'])) {
      $sourceAttribution = $this->normalizeSourceAttribution($results['source_attribution']);
      $output .= $this->formatSourceAttribution($sourceAttribution);
    }

    // Process each sub-query result
    foreach ($multipleResults as $index => $subResult) {
      $subQueryNum = $index + 1;
      $subQuery = $subResult['query'] ?? $this->language->getDef('text_rag_analytics_sub_query') . " {$subQueryNum}";
      
      $output .= "<div class='sub-query-result' style='margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px;'>";
      $output .= "<h5 style='color: #0066cc;'>📊 " . $this->language->getDef('text_rag_analytics_query') . " {$subQueryNum} : " . htmlspecialchars($subQuery) . "</h5>";

      // Check if this sub-query failed
      if (isset($subResult['error']) || (isset($subResult['success']) && $subResult['success'] === false)) {
        $errorMsg = $subResult['error'] ?? $this->language->getDef('text_rag_analytics_unknown_error');
        $output .= "<div class='alert alert-warning'>";
        $output .= "<strong>⚠️ " . $this->language->getDef('text_rag_analytics_error') . "</strong> " . htmlspecialchars($errorMsg);
        $output .= "</div>";
        $output .= "</div>"; // Close sub-query-result
        continue;
      }

      // SQL query display
      if ($this->displaySql && isset($subResult['sql'])) {
        $output .= $this->formatSqlQuery($subResult['sql']);
      }

      // Interpretation
      if (isset($subResult['interpretation']) && !empty($subResult['interpretation'])) {
        $output .= "<div class='interpretation'><strong>" . $this->language->getDef('text_rag_analytics_interpretation') . "</strong> " 
                . Hash::displayDecryptedDataText($subResult['interpretation']) . "</div>";
      }

      // Data table
      if (isset($subResult['rows']) && is_array($subResult['rows']) && !empty($subResult['rows'])) {
        $output .= "<div class='results-table'>";
        $output .= "<h6>" . $this->language->getDef('text_rag_analytics_data') . "</h6>";
        $output .= $this->generateTable($subResult['rows'], 'table table-bordered table-striped');
        $output .= "<div class='result-count'><em>" . $this->language->getDef('text_rag_analytics_result_count') . " " . $subResult['row_count'] . "</em></div>";
        $output .= "</div>";
      } else {
        $output .= "<div class='alert alert-info'>";
        $output .= "<strong>" . $this->language->getDef('text_rag_analytics_note') . "</strong> " . $this->language->getDef('text_rag_analytics_no_data_found');
        $output .= "</div>";
      }

      $output .= "</div>"; // Close sub-query-result
    }

    // Display all SQL queries if requested
    if ($this->displaySql && isset($results['sql_queries']) && is_array($results['sql_queries'])) {
      $output .= "<div class='all-sql-queries' style='margin-top: 20px;'>";
      $output .= "<h5>" . $this->language->getDef('text_rag_analytics_all_sql_queries') . "</h5>";
      foreach ($results['sql_queries'] as $index => $sql) {
        $output .= "<div class='sql-query-item'>";
        $output .= "<strong>" . $this->language->getDef('text_rag_analytics_query') . " " . ($index + 1) . " :</strong>";
        $output .= $this->formatSqlQuery($sql);
        $output .= "</div>";
      }
      $output .= "</div>";
    }

    $output .= "</div>"; // Close analytics-results

    // Save audit data
    $auditExtra = [
      'multiple_queries' => true,
      'query_count' => $queryCount,
      'embeddings_context' => $results['embeddings_context'] ?? [],
      'similarity_scores'  => $results['similarity_scores'] ?? [],
      'processing_chain'   => $results['processing_chain'] ?? []
    ];
    Gpt::saveData($originalQuery, $output, $auditExtra);

    return [
      'type' => 'formatted_results',
      'content' => $output
    ];
  }

  /**
   * Normalize source_attribution into the expected array structure.
   */
  private function normalizeSourceAttribution(mixed $sourceAttribution): array
  {
    if (is_array($sourceAttribution)) {
      return $sourceAttribution;
    }

    if (is_string($sourceAttribution) && $sourceAttribution !== '') {
      return [
        'source_type' => $sourceAttribution,
        'source_icon' => '📊'
      ];
    }

    return [];
  }

  private function formatSqlQuery(string $sql): string
  {
    $formatted = $this->prettySql($sql);
    $escaped = htmlspecialchars($formatted, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return "<div class='col-md-12 row sql-query'>
            <strong>" . $this->language->getDef('text_rag_analytics_sql_query') . "</strong>
            <pre>{$escaped}</pre>
          </div>";
  }

  private function formatDataTable(array $data): string
  {
    if (empty($data)) {
      return '';
    }

    $output = "<div class='results-table'>";
    $output .= "<h5>" . $this->language->getDef('text_rag_analytics_data') . "</h5>";
    
    // Use inherited method from AbstractFormatter
    $output .= $this->generateTable($data, 'table table-bordered table-striped');
    
    $output .= "</div>";

    return $output;
  }

  private function formatGuardrailsMetrics(array $guardrails): string
  {
    // Surface the semantic quality signal already computed by LlmGuardrails::checkGuardrails()
    $overall = isset($guardrails['overall_score']) ? (float) $guardrails['overall_score'] : null;
    $issues = $guardrails['llm_evaluation']['detected_issues'] ?? [];
    $recommendations = $guardrails['recommendations'] ?? ($guardrails['llm_evaluation']['recommendations'] ?? []);

    // No concern → render nothing (keep the UI clean).
    if ($overall === null || $overall >= 0.7) {
      return '';
    }

    $score = (int) round($overall * 100);
    $cls = $score >= 60 ? 'text-warning' : 'text-danger';

    $output = "<div class='guardrails-metrics mt-2'>";
    $output .= '<h6>🔍 ' . htmlspecialchars($this->language->getDef('text_rag_semantic_quality_metrics')) . '</h6>';
    $output .= '<p class="' . $cls . ' mb-1"><strong>🎯 ' . htmlspecialchars($this->language->getDef('text_rag_semantic_reliability_score')) . ' ' . $score . '%</strong></p>';

    if (!empty($issues) && \is_array($issues)) {
      $output .= '<ul class="mb-1">';
      foreach (\array_slice($issues, 0, 5) as $issue) {
        $output .= '<li>⚠️ ' . htmlspecialchars((string) $issue) . '</li>';
      }
      $output .= '</ul>';
    }

    if (!empty($recommendations) && \is_array($recommendations)) {
      $output .= '<p class="mb-0"><strong>' . htmlspecialchars($this->language->getDef('text_rag_ambiguous_recommendation')) . '</strong> ' . htmlspecialchars((string) reset($recommendations)) . '</p>';
    }

    $output .= "</div>";
    return $output;
  }
}
