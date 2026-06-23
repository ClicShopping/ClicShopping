<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\SubResultFormatters;

use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\LlmGuardrails;

/**
 * HybridFormatter - Formats hybrid query results
 * 
 * Hybrid queries combine multiple query types (analytics + semantic, web_search + analytics, etc.)
 * This formatter presents the synthesized response with source attribution from all sub-queries.
 * 
 * @package ClicShopping\AI\Helper\Formatter\SubResultFormatters
 * @since 2025-12-30
 * @version 2.0 - Internationalized 2025-12-30
 */

class HybridFormatter extends AbstractFormatter
{
  private $language;
  private string $languageCode;

  public function __construct(bool $debug = false, bool $displaySql = false)
  {
    parent::__construct($debug, $displaySql);
    
    // Initialize language support
    $this->language = Registry::get('Language');
    $this->languageCode = $this->language->get('code');
    
    // Load language definitions (null = use current user language)
    Registry::get('Language')->loadDefinitions('ClicShoppingAdmin/ai_response_labels');
  }

  public function canHandle(array $results): bool
  {
    $type = $results['type'] ?? '';
    return $type === 'hybrid';
  }

  public function format(array $results): array
  {
    $question = $results['query'] ?? $results['question'] ?? 'Unknown request';

    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      if (isset($results['analytics_component'])) {
        $analyticsComp = $results['analytics_component'];
      }
    }

    $output = "<div class='hybrid-results'>";
    $output .= "<h4>" . htmlspecialchars($this->language->getDef('results_for')) . " " . htmlspecialchars($question) . "</h4>";

    // Display source attribution (shows combined sources)
    if (isset($results['source_attribution'])) {
      $output .= $this->formatSourceAttribution($results['source_attribution']);
    }

    // Guardrails
    $output .= "<div class='mt-2'></div>";
    $responseContent = $results['response'] ?? $results['text_response'] ?? '';
    
    $lmGuardrails = LlmGuardrails::checkGuardrails(
      $question, 
      Hash::displayDecryptedDataText($responseContent)
    );

    if (is_array($lmGuardrails)) {
      $output .= $this->formatGuardrailsMetrics($lmGuardrails);
    } else {
      $output .= "<div class='alert alert-warning'>" . htmlspecialchars($lmGuardrails) . "</div>";
    }

    $output .= "<div class='mt-2'></div>";

    // Instead of just showing text_response, render actual tables and structured data
    
    // Display analytics component with table
    // Maillon C: render ALL analytics sub-queries (e.g. price + last 3 orders), not just the first.
    // Falls back to the singular analytics_component for backward compatibility.
    $analyticsComponents = $results['analytics_components']
      ?? ((isset($results['analytics_component']) && is_array($results['analytics_component']))
          ? [$results['analytics_component']]
          : []);
    $hasAnalyticsComponent = !empty($analyticsComponents);
    $hasSemanticComponent = isset($results['semantic_component']) && is_array($results['semantic_component']);

    $this->renderAnalyticsComponents($analyticsComponents, $output);
    
    // Display semantic component
    if ($hasSemanticComponent) {
      $semanticComp = $results['semantic_component'];
      
      $output .= "<div class='mt-4'>";
      $output .= "<h5>📚 " . htmlspecialchars($this->language->getDef('semantic_results_title')) . "</h5>";
      
      // Display semantic response
      $semanticText = $semanticComp['response'] ?? $semanticComp['text_response'] ?? '';
      if (!empty($semanticText)) {
        $output .= "<div class='semantic-response'>" . nl2br(htmlspecialchars($semanticText)) . "</div>";
      }
      
      // Display sources
      if (isset($semanticComp['sources']) && is_array($semanticComp['sources']) && !empty($semanticComp['sources'])) {
        $output .= "<div class='mt-3'>";
        $output .= "<details>";
        $output .= "<summary style='cursor: pointer; font-size: 0.9em; color: #666;'><strong>📖 " . htmlspecialchars($this->language->getDef('sources_label')) . " (" . count($semanticComp['sources']) . ")</strong></summary>";
        $output .= "<ul class='mt-2'>";
        foreach ($semanticComp['sources'] as $source) {
          if (is_array($source)) {
            $sourceText = $source['content'] ?? $source['text'] ?? '';
            $sourceType = $source['type'] ?? '';
            if (!empty($sourceText)) {
              $output .= "<li style='margin-bottom: 10px;'>";
              if (!empty($sourceType)) {
                $output .= "<span class='badge bg-secondary'>" . htmlspecialchars($sourceType) . "</span> ";
              }
              $output .= htmlspecialchars(substr($sourceText, 0, 200)) . (strlen($sourceText) > 200 ? '...' : '');
              $output .= "</li>";
            }
          }
        }
        $output .= "</ul>";
        $output .= "</details>";
        $output .= "</div>";
      }
      
      $output .= "</div>";
    }

    // Display the synthesized response (optional, as summary)
    if (!empty($responseContent) && (empty($results['analytics_component']) && empty($results['semantic_component']))) {
      // Only show text_response if we don't have structured components
      $formattedResponse = nl2br(Hash::displayDecryptedDataText($responseContent));
      $output .= "<div class='response'><strong>" . htmlspecialchars($this->language->getDef('response_label')) . "</strong><br>" . $formattedResponse . "</div>";
    }

    // Render actual results from sub-queries, not just metadata
    $this->renderSubQueryResults($results, $hasAnalyticsComponent, $hasSemanticComponent, $output);

    // Display sub-query information (optional, for transparency)
    if (isset($results['sub_queries']) && is_array($results['sub_queries']) && count($results['sub_queries']) > 1) {
      $output .= "<div class='mt-3'>";
      $output .= "<details style='font-size: 0.9em; color: #666;'>";
      $output .= "<summary style='cursor: pointer;'><strong>" . htmlspecialchars($this->language->getDef('source_details_label')) . count($results['sub_queries']) . " " . htmlspecialchars($this->language->getDef('combined_queries_suffix')) . "</strong></summary>";
      $output .= "<ul style='margin-top: 10px;'>";
      
      foreach ($results['sub_queries'] as $idx => $subQuery) {
        $subType = $subQuery['type'] ?? 'unknown';
        $subIcon = $this->getIconForType($subType);
        $output .= "<li>{$subIcon} <strong>" . ucfirst($subType) . "</strong>";
        
        // Show brief info about each sub-query
        if (isset($subQuery['source_attribution']['primary_source'])) {
          $output .= " - " . htmlspecialchars($subQuery['source_attribution']['primary_source']);
        }
        
        $output .= "</li>";
      }
      
      $output .= "</ul>";
      $output .= "</details>";
      $output .= "</div>";
    }

    $output .= "</div>";

    return [
      'type' => 'formatted_results',
      'content' => $output
    ];
  }

  /**
   * Render the analytics component tables (Maillon C) into the output buffer.
   *
   * Extracted verbatim from format() to cut NPath. Renders each analytics sub-query
   * (interpretation, SQL query, result table).
   *
   * @param array $analyticsComponents Analytics components to render
   * @param string $output Output HTML buffer, mutated by reference
   * @return void
   */
  private function renderAnalyticsComponents(array $analyticsComponents, string &$output): void
  {
    foreach ($analyticsComponents as $analyticsComp) {
      if (!is_array($analyticsComp)) {
        continue;
      }
      
      $output .= "<div class='mt-4'>";
      $output .= "<h5>📊 " . htmlspecialchars($this->language->getDef('hybrid_analytics_results_title')) . "</h5>";

      $analyticsInterpretation = trim((string)($analyticsComp['interpretation'] ?? ''));
      if ($analyticsInterpretation !== '') {
        $output .= "<div class='analytics-interpretation mb-3'>" . nl2br(htmlspecialchars($analyticsInterpretation)) . "</div>";
      }

      // SQL Query (always visible)
      if (!empty($analyticsComp['sql_query'])) {
        $output .= "<div class='mb-3' style='background:#f8f9fa; border-left:3px solid #0d6efd; padding:15px; border-radius:4px;'>";
        $output .= "<div style='font-weight:bold; margin-bottom:8px; color:#0d6efd;'>🔍 Requête SQL :</div>";
        $output .= "<pre style='margin:0; font-size:0.85em; white-space:pre-wrap; word-wrap:break-word; background:#fff; padding:10px; border-radius:3px; font-family:monospace;'>" . htmlspecialchars($this->formatSqlQuery($analyticsComp['sql_query'])) . "</pre>";
        $output .= "</div>";
      }

      
      // Display results as table
      if (isset($analyticsComp['results']) && is_array($analyticsComp['results']) && !empty($analyticsComp['results'])) {
        $tableParts = $this->buildTableOpenTag('table table-sm table-bordered table-striped');
        $output .= $tableParts['toolbar'] . $tableParts['table'];
        
        // Table header
        $firstRow = $analyticsComp['results'][0];
        if (is_array($firstRow)) {
          $output .= "<thead class='table-light'><tr>";
          foreach (array_keys($firstRow) as $column) {
            $output .= "<th>" . htmlspecialchars(ucfirst(str_replace('_', ' ', $column))) . "</th>";
          }
          $output .= "</tr></thead>";
          
          // Table body
          $output .= "<tbody>";
          foreach ($analyticsComp['results'] as $row) {
            $output .= "<tr>";
          foreach ($row as $column => $value) {
            $output .= "<td>" . $this->formatCellValue((string)$column, $value) . "</td>";
          }
            $output .= "</tr>";
          }
          $output .= "</tbody>";
        }
        
        $output .= "</table>";
      }
      
      $output .= "</div>";
    }
  }

  /**
   * Render the per-sub-query result sections (web_search / analytics / semantic).
   *
   * Extracted verbatim from format() to cut NPath. Appends HTML to the shared
   * output buffer; skips analytics/semantic sub-queries already shown as components.
   *
   * @param array $results Hybrid result payload
   * @param bool $hasAnalyticsComponent Whether an analytics component was already rendered
   * @param bool $hasSemanticComponent Whether a semantic component was already rendered
   * @param string $output Output HTML buffer, mutated by reference
   * @return void
   */
  private function renderSubQueryResults(array $results, bool $hasAnalyticsComponent, bool $hasSemanticComponent, string &$output): void
  {
    if (isset($results['sub_queries']) && is_array($results['sub_queries'])) {
      foreach ($results['sub_queries'] as $idx => $subQuery) {
        $subType = $subQuery['type'] ?? 'unknown';

        // Avoid duplicate rendering when hybrid components are already shown
        if ($subType === 'analytics' && $hasAnalyticsComponent) {
          continue;
        }
        if ($subType === 'semantic' && $hasSemanticComponent) {
          continue;
        }
        
        // Format web_search results with full HTML rendering
        if ($subType === 'web_search' && isset($subQuery['results']) && is_array($subQuery['results'])) {
          $output .= "<div class='mt-4'>";
          $output .= "<h5>" . htmlspecialchars($this->language->getDef('hybrid_web_search_results_title')) . "</h5>";
          $output .= "<div class='web-search-results'>";
          
          foreach ($subQuery['results'] as $resultIdx => $result) {
            if (is_array($result)) {
              $title = $result['title'] ?? 'Untitled';
              $url = $result['url'] ?? '#';
              $snippet = $result['snippet'] ?? '';
              
              $output .= "<div class='search-result mb-3' style='border-left: 3px solid #17a2b8; padding-left: 15px;'>";
              $output .= "<h6><a href='" . htmlspecialchars($url) . "' target='_blank' rel='noopener noreferrer'>" . htmlspecialchars($title) . "</a></h6>";
              
              if (!empty($snippet)) {
                $output .= "<p style='color: #666; font-size: 0.9em;'>" . htmlspecialchars($snippet) . "</p>";
              }
              
              $output .= "<small style='color: #999;'>" . htmlspecialchars($url) . "</small>";
              $output .= "</div>";
            }
          }
          
          $output .= "</div>";
          $output .= "</div>";
        }
        
        // Format analytics results (if present)
        if ($subType === 'analytics' && isset($subQuery['results'])) {
          $output .= "<div class='mt-4'>";

        $analyticsSub = $subQuery;
        $collapseId = 'sqlQueryCollapse_' . uniqid();

        if (!empty($analyticsSub['sql_query'])) {
            $output .= "<div class='mb-3'>";
            $output .= "<a href='#' class='btn btn-primary btn-sm' data-sql-toggle='{$collapseId}'>";
            $output .= htmlspecialchars($this->language->getDef('toggle_sql_query'));
            $output .= "</a>";

            $output .= "<div id='{$collapseId}' style='display:none;' class='mt-2'>";
            $output .= "<div class='card card-body'>";
            $output .= "<div class='fw-bold mb-2 text-primary'>" . htmlspecialchars($this->language->getDef('sql_query_label')) . "</div>";
            $output .= "<pre class='mb-0' style='font-size:0.85em; white-space:pre-wrap; word-wrap:break-word; font-family:monospace;'>";
            $output .= htmlspecialchars($this->formatSqlQuery($analyticsSub['sql_query']));
            $output .= "</pre>";
            $output .= "</div>";
            $output .= "</div>";
            $output .= "</div>";
        }

          $output .= "<h5>" . htmlspecialchars($this->language->getDef('hybrid_analytics_results_title')) . "</h5>";
          // Use existing analytics formatting logic
          $output .= $this->formatAnalyticsSubQuery($subQuery);
          $output .= "</div>";
        }
        
        // Format semantic results (if present)
        if ($subType === 'semantic' && isset($subQuery['results'])) {
          $output .= "<div class='mt-4'>";
          $output .= "<h5>" . htmlspecialchars($this->language->getDef('semantic_results_title')) . "</h5>";
          // Use existing semantic formatting logic
          $output .= $this->formatSemanticSubQuery($subQuery);
          $output .= "</div>";
        }
      }
    }
  }

  /**
   * Format guardrails metrics for hybrid responses
   *
   * @param array $guardrails Guardrails evaluation results
   * @return string Formatted HTML output (empty if no warnings)
   */
  private function formatGuardrailsMetrics(array $guardrails): string
  {
    // Only display if there are warnings
    // For now, we'll keep it simple and not display metrics for hybrid queries
    // unless there's a specific issue
    return '';
  }

  /**
   * Format SQL query for better readability
   * Adds line breaks and indentation to SQL keywords
   *
   * @param string $sql Raw SQL query
   * @return string Formatted SQL query
   */
  private function formatSqlQuery(string $sql): string
  {
    // Keywords to put on new lines
    $keywords = [
      'SELECT', 'FROM', 'WHERE', 'JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN',
      'ORDER BY', 'GROUP BY', 'HAVING', 'LIMIT', 'OFFSET', 'UNION', 'AND', 'OR'
    ];

    // Replace keywords with newline + keyword
    $formatted = $sql;
    foreach ($keywords as $keyword) {
      // Add newline before keyword (case insensitive)
      $formatted = preg_replace(
        '/\s+(' . preg_quote($keyword, '/') . ')\s+/i',
        "\n" . $keyword . ' ',
        $formatted
      );
    }

    // Indent JOIN clauses
    $formatted = preg_replace('/\n((?:LEFT |RIGHT |INNER )?JOIN)/i', "\n  $1", $formatted);

    // Indent WHERE conditions after first one
    $formatted = preg_replace('/\n(AND|OR)\s+/i', "\n  $1 ", $formatted);

    // Clean up extra spaces
    $formatted = preg_replace('/\s+/', ' ', $formatted);

    // Clean up spaces around newlines
    $formatted = preg_replace('/\s*\n\s*/', "\n", $formatted);

    return trim($formatted);
  }

  /**
   * Format analytics sub-query results
   *
   * @param array $subQuery Analytics sub-query data
   * @return string Formatted HTML
   */
  private function formatAnalyticsSubQuery(array $subQuery): string
  {
    $output = '';
    
    if (isset($subQuery['results']) && is_array($subQuery['results'])) {
      $output .= "<div class='analytics-results'>";
      
      // If results are already rows (associative arrays), render directly
      if (!empty($subQuery['results']) && is_array($subQuery['results'][0])) {
        $firstRow = $subQuery['results'][0];
        $tableParts = $this->buildTableOpenTag('table table-sm table-bordered');
        $output .= $tableParts['toolbar'] . $tableParts['table'];
        $output .= "<thead><tr>";
        foreach (array_keys($firstRow) as $column) {
          $output .= "<th>" . htmlspecialchars($column) . "</th>";
        }
        $output .= "</tr></thead>";
        $output .= "<tbody>";
        foreach ($subQuery['results'] as $row) {
          $output .= "<tr>";
          foreach ($row as $column => $value) {
            $output .= "<td>" . $this->formatCellValue((string)$column, $value) . "</td>";
          }
          $output .= "</tr>";
        }
        $output .= "</tbody></table>";
        $output .= "</div>";
        return $output;
      }

      foreach ($subQuery['results'] as $result) {
        if (isset($result['rows']) && is_array($result['rows'])) {
          $tableParts = $this->buildTableOpenTag('table table-sm table-bordered');
          $output .= $tableParts['toolbar'] . $tableParts['table'];
          
          // Table header
          if (!empty($result['rows'])) {
            $firstRow = $result['rows'][0];
            if (is_array($firstRow)) {
              $output .= "<thead><tr>";
              foreach (array_keys($firstRow) as $column) {
                $output .= "<th>" . htmlspecialchars($column) . "</th>";
              }
              $output .= "</tr></thead>";
              
              // Table body
              $output .= "<tbody>";
              foreach ($result['rows'] as $row) {
                $output .= "<tr>";
            foreach ($row as $column => $value) {
              $output .= "<td>" . $this->formatCellValue((string)$column, $value) . "</td>";
            }
            $output .= "</tr>";
          }
              $output .= "</tbody>";
            }
          }
          
          $output .= "</table>";
        }
      }
      
      $output .= "</div>";
    }
    
    return $output;
  }

  /**
   * Format semantic sub-query results
   *
   * @param array $subQuery Semantic sub-query data
   * @return string Formatted HTML
   */
  private function formatSemanticSubQuery(array $subQuery): string
  {
    $output = '';
    
    if (isset($subQuery['results']) && is_array($subQuery['results'])) {
      $output .= "<div class='semantic-results'>";
      
      foreach ($subQuery['results'] as $idx => $result) {
        if (is_array($result)) {
          $content = $result['content'] ?? $result['text'] ?? '';
          $source = $result['source'] ?? $result['document_name'] ?? 'Unknown source';
          
          $output .= "<div class='semantic-result mb-3' style='border-left: 3px solid #28a745; padding-left: 15px;'>";
          $output .= "<h6>" . htmlspecialchars($source) . "</h6>";
          
          if (!empty($content)) {
            $output .= "<p>" . htmlspecialchars(substr($content, 0, 300)) . "...</p>";
          }
          
          $output .= "</div>";
        }
      }
      
      $output .= "</div>";
    }
    
    return $output;
  }

  /**
   * Get icon for query type
   *
   * @param string $type Query type
   * @return string Icon emoji
   */
  private function getIconForType(string $type): string
  {
    $icons = [
      'analytics' => '📊',
      'semantic' => '📚',
      'web_search' => '🌐',
      'hybrid' => '🔀',
    ];

    return $icons[$type] ?? '🤖';
  }
}
