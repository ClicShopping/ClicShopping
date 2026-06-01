<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\SubResultFormatters;


use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\OM\Hash;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\LlmGuardrails;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * ComplexQueryFormatter - Formats complex query results (multi-step queries)
 * 
 * Handles formatting of complex queries that combine multiple data sources:
 * - Analytics + Semantic (e.g., "Show sales and explain return policy")
 * - Analytics + Web Search (e.g., "Compare product price with competitors")
 * - Semantic + Web Search (e.g., "Our policy vs competitor policy")
 * - Three-way hybrid (Analytics + Semantic + Web)
 * 
 * Displays results in structured sections with proper source attribution
 */

class ComplexQueryFormatter extends AbstractFormatter
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
    DomainConfig::loadLanguageFile('rag_complex_query_formatter', null);
  }
  
  /**
   * Check if this formatter can handle the given results
   * 
   * @param array $results Results to check
   * @return bool True if results are complex_query type (NOT hybrid - that's handled by HybridFormatter)
   */
  public function canHandle(array $results): bool
  {
    $type = $results['type'] ?? '';
    // Note: 'hybrid' is now handled by HybridFormatter (priority 105)
    // ComplexQueryFormatter handles 'complex_query' and legacy 'hybrid_results'
    return in_array($type, ['complex_query', 'hybrid_results'], true);
  }

  /**
   * Format complex query results for display
   * 
   * @param array $results Complex query results to format
   * @return array Formatted results with HTML content
   */
  public function format(array $results): array
  {
    $question = $results['question'] ?? $results['query'] ?? 'Unknown request';

    $output = "<div class='complex-query-results'>";
    $output .= '<h4>' . $this->language->getDef('text_rag_complex_query_results_for', ['query' => $question]) .'</h4>';

    // Display mixed source attribution (🔀 Mixed)
    if (isset($results['source_attribution'])) {
      $output .= $this->formatMixedSourceAttribution($results['source_attribution']);
    }

    // Display overall interpretation/summary if available
    $interpretationText = '';
    $isHtmlContent = false;
    
    if (isset($results['text_response']) && !empty($results['text_response'])) {
      $interpretationText = $results['text_response'];
      // Check if text_response contains HTML
      $isHtmlContent = (str_contains($interpretationText, '<div') || str_contains($interpretationText, '<p>'));
    } elseif (isset($results['response']) && !empty($results['response'])) {
      $interpretationText = $results['response'];
    } elseif (isset($results['interpretation']) && $results['interpretation'] !== 'Array') {
      $interpretationText = $results['interpretation'];
    }

    if (!empty($interpretationText)) {
      if ($isHtmlContent) {
        // text_response already contains formatted HTML - use as-is
        $output .= "<div class='overall-summary alert alert-primary' style='margin-top: 15px;'>";
        $output .= $interpretationText;
        $output .= "</div>";
      } else {
        // Plain text - apply HTML encoding
        $output .= "<div class='overall-summary alert alert-primary' style='margin-top: 15px;'>";
        $output .= "<strong>📋 " . $this->language->getDef('text_rag_complex_query_global_summary') . "</strong><br>";
        $output .= Hash::displayDecryptedDataText($interpretationText);
        $output .= "</div>";
      }
    }

    // Guardrails for overall response
    $output .= "<div class='mt-2'></div>";
    $lmGuardrails = LlmGuardrails::checkGuardrails($question, Hash::displayDecryptedDataText($interpretationText));

    if (is_array($lmGuardrails)) {
      $output .= $this->formatGuardrailsMetrics($lmGuardrails);
    } else {
      $output .= "<div class='alert alert-warning'>" . htmlspecialchars($lmGuardrails) . "</div>";
    }

    $output .= "<div class='mt-2'></div>";

    // Display multi-section results
    if (!empty($results['sub_results']) && is_array($results['sub_results'])) {
      $output .= $this->formatMultiSectionResults($results['sub_results']);
    }

    // Display aggregated data if available (legacy support)
    if (!empty($results['data']) && is_array($results['data'])) {
      $output .= "<div class='mt-3'>";
      $output .= "<h5>" . $this->language->getDef('text_rag_complex_query_aggregated_data') . "</h5>";
      $output .= $this->formatAggregatedData($results['data']);
      $output .= "</div>";
    }

    // Display sub-results summary
    if (!empty($results['sub_results'])) {
      $successCount = $results['successful_count'] ?? count($results['sub_results']);
      $failedCount = $results['failed_count'] ?? 0;

      $output .= "<div class='mt-3 sub-results-summary alert alert-secondary'>";
      $output .= "<p style='margin: 0;'><small>";
      $output .= "✓ {$successCount} " . $this->language->getDef('text_rag_complex_query_sub_queries_success');
      if ($failedCount > 0) {
        $output .= " | ✗ {$failedCount} " . $this->language->getDef('text_rag_complex_query_failures');
      }
      $output .= "</small></p>";
      $output .= "</div>";
    }

    $output .= "</div>";

    // Save audit data
    $auditExtra = [
      'sub_results' => $results['sub_results'] ?? [],
      'source_attribution' => $results['source_attribution'] ?? [],
      'processing_chain' => $results['processing_chain'] ?? []
    ];
    Gpt::saveData($question, $output, $auditExtra);

    return [
      'type' => 'formatted_results',
      'content' => $output
    ];
  }

  /**
   * Format multi-section results from different data sources
   * 
   * @param array $subResults Array of sub-results from different sources
   * @return string Formatted HTML with sections
   */
  private function formatMultiSectionResults(array $subResults): string
  {
    $output = "<div class='multi-section-results' style='margin-top: 20px;'>";
    $output .= "<h5>📊 " . $this->language->getDef('text_rag_complex_query_detailed_results') . "</h5>";

    foreach ($subResults as $index => $subResult) {
      $sectionNumber = $index + 1;
      $subQuery = $subResult['sub_query'] ?? $subResult['query'] ?? $this->language->getDef('text_rag_complex_query_section_result') . " {$sectionNumber}";
      $subType = $subResult['type'] ?? 'unknown';
      
      // Determine section icon and title based on type
      $sectionIcon = $this->getSectionIcon($subType);
      $sectionTitle = $this->getSectionTitle($subType);
      
      $output .= "<div class='result-section' style='margin-bottom: 25px; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px; background-color: #f8f9fa;'>";
      
      // Section header
      $output .= "<div class='section-header' style='margin-bottom: 10px; padding-bottom: 10px; border-bottom: 2px solid #007bff;'>";
      $output .= "<h6 style='margin: 0;'>{$sectionIcon} <strong>{$sectionTitle} {$sectionNumber}</strong></h6>";
      $output .= "<div style='font-size: 0.9em; color: #666; margin-top: 5px;'>";
      $output .= htmlspecialchars($subQuery);
      $output .= "</div>";
      $output .= "</div>";

      // Section source attribution
      if (isset($subResult['source_attribution'])) {
        $output .= $this->formatSourceAttribution($subResult['source_attribution']);
      }

      // Section content based on type
      $output .= $this->formatSectionContent($subResult, $subType);
      
      $output .= "</div>";
    }

    $output .= "</div>";

    return $output;
  }

  /**
   * Get icon for section based on type
   * 
   * @param string $type Section type
   * @return string Icon emoji
   */
  private function getSectionIcon(string $type): string
  {
    return match($type) {
      'analytics', 'analytics_results', 'analytics_response' => '📊',
      'semantic', 'semantic_results' => '📚',
      'web_search', 'web_search_results' => '🌐',
      default => '📄'
    };
  }

  /**
   * Get title for section based on type
   * 
   * @param string $type Section type
   * @return string Section title
   */
  private function getSectionTitle(string $type): string
  {
    return match($type) {
      'analytics', 'analytics_results', 'analytics_response' => $this->language->getDef('text_rag_complex_query_section_data_analysis'),
      'semantic', 'semantic_results' => $this->language->getDef('text_rag_complex_query_section_semantic_search'),
      'web_search', 'web_search_results' => $this->language->getDef('text_rag_complex_query_section_web_search'),
      default => $this->language->getDef('text_rag_complex_query_section_result')
    };
  }

  /**
   * Format section content based on type
   * 
   * @param array $subResult Sub-result data
   * @param string $type Section type
   * @return string Formatted HTML
   */
  private function formatSectionContent(array $subResult, string $type): string
  {
    $output = '';

    // Display interpretation/response
    $interpretationText = '';
    if (isset($subResult['text_response']) && !empty($subResult['text_response'])) {
      $interpretationText = $subResult['text_response'];
    } elseif (isset($subResult['response']) && !empty($subResult['response'])) {
      $interpretationText = $subResult['response'];
    } elseif (isset($subResult['interpretation']) && $subResult['interpretation'] !== 'Array') {
      $interpretationText = $subResult['interpretation'];
    }

    if (!empty($interpretationText)) {
      $output .= "<div class='section-interpretation' style='margin: 10px 0; padding: 10px; background-color: white; border-radius: 4px;'>";
      $output .= "<strong>" . $this->language->getDef('text_rag_complex_query_response') . "</strong><br>";
      $output .= Hash::displayDecryptedDataText($interpretationText);
      $output .= "</div>";
    }

    // Type-specific content
    switch ($type) {
      case 'analytics':
      case 'analytics_results':
      case 'analytics_response':
        $output .= $this->formatAnalyticsSection($subResult);
        break;
        
      case 'semantic':
      case 'semantic_results':
        $output .= $this->formatSemanticSection($subResult);
        break;
        
      case 'web_search':
      case 'web_search_results':
        $output .= $this->formatWebSearchSection($subResult);
        break;
        
      default:
        // Generic data display
        if (!empty($subResult['data']) && is_array($subResult['data'])) {
          $output .= $this->formatDataTable($subResult['data']);
        }
        break;
    }

    return $output;
  }

  /**
   * Format analytics section content
   * 
   * @param array $subResult Analytics sub-result
   * @return string Formatted HTML
   */
  private function formatAnalyticsSection(array $subResult): string
  {
    $output = '';

    // Display SQL query if available and displaySql is enabled
    if ($this->displaySql && isset($subResult['sql_query'])) {
      $formatted = $this->prettySql($subResult['sql_query']);
      $escaped = htmlspecialchars($formatted, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
      
      $output .= "<div class='sql-query' style='margin: 10px 0;'>";
      $output .= "<strong>" . $this->language->getDef('text_rag_complex_query_sql_query') . "</strong>";
      $output .= "<pre style='background-color: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto;'>{$escaped}</pre>";
      $output .= "</div>";
    }

    // Display data table
    if (isset($subResult['results']) && is_array($subResult['results']) && !empty($subResult['results'])) {
      $output .= "<div class='analytics-data' style='margin: 10px 0;'>";
      $output .= $this->generateTable($subResult['results'], 'table table-sm table-bordered table-striped');
      $output .= "</div>";
    } elseif (isset($subResult['data']) && is_array($subResult['data']) && !empty($subResult['data'])) {
      $output .= "<div class='analytics-data' style='margin: 10px 0;'>";
      $output .= $this->formatDataTable($subResult['data']);
      $output .= "</div>";
    }

    return $output;
  }

  /**
   * Format semantic section content
   * 
   * @param array $subResult Semantic sub-result
   * @return string Formatted HTML
   */
  private function formatSemanticSection(array $subResult): string
  {
    $output = '';

    // Display document count if available
    if (isset($subResult['document_count']) && $subResult['document_count'] > 0) {
      $output .= "<div class='document-info' style='margin: 10px 0; font-size: 0.9em; color: #666;'>";
      $output .= "📚 {$subResult['document_count']} " . $this->language->getDef('text_rag_complex_query_documents_found');
      $output .= "</div>";
    }

    // Display embeddings context if available
    if (isset($subResult['embeddings_context']) && is_array($subResult['embeddings_context']) && !empty($subResult['embeddings_context'])) {
      $output .= "<div class='embeddings-context' style='margin: 10px 0;'>";
      $output .= "<details>";
      $output .= "<summary style='cursor: pointer; color: #007bff;'>" . $this->language->getDef('text_rag_complex_query_view_sources') . "</summary>";
      $output .= "<div style='margin-top: 10px; padding: 10px; background-color: white; border-radius: 4px;'>";
      
      foreach ($subResult['embeddings_context'] as $idx => $context) {
        $content = $context['content'] ?? $context['text'] ?? '';
        $score = $context['score'] ?? $context['similarity'] ?? 0;
        
        if (!empty($content)) {
          $output .= "<div style='margin-bottom: 10px; padding: 8px; border-left: 3px solid #28a745;'>";
          $output .= "<div style='font-size: 0.85em; color: #666; margin-bottom: 5px;'>";
          $output .= $this->language->getDef('text_rag_complex_query_similarity_score') . " " . number_format($score, 3);
          $output .= "</div>";
          $output .= "<div>" . htmlspecialchars(substr($content, 0, 200)) . "...</div>";
          $output .= "</div>";
        }
      }
      
      $output .= "</div>";
      $output .= "</details>";
      $output .= "</div>";
    }

    return $output;
  }

  /**
   * Format web search section content
   * 
   * @param array $subResult Web search sub-result
   * @return string Formatted HTML
   */
  private function formatWebSearchSection(array $subResult): string
  {
    $output = '';

    // Display price comparison if available
    if (isset($subResult['price_comparison']) && is_array($subResult['price_comparison'])) {
      $output .= $this->formatPriceComparison($subResult['price_comparison']);
    }

    // Display web results
    if (isset($subResult['web_results']) && is_array($subResult['web_results']) && !empty($subResult['web_results'])) {
      $output .= "<div class='web-results' style='margin: 10px 0;'>";
      
      foreach ($subResult['web_results'] as $idx => $result) {
        $title = $result['title'] ?? $this->language->getDef('text_rag_complex_query_result_number') . " " . ($idx + 1);
        $snippet = $result['snippet'] ?? $result['description'] ?? '';
        $url = $result['url'] ?? $result['link'] ?? '';
        
        $output .= "<div class='web-result-item' style='margin-bottom: 10px; padding: 10px; border-left: 3px solid #17a2b8; background-color: white; border-radius: 4px;'>";
        
        if (!empty($url)) {
          $output .= "<div class='result-title'>";
          $output .= "<a href='" . htmlspecialchars($url) . "' target='_blank' rel='noopener noreferrer' style='font-weight: bold; color: #007bff;'>";
          $output .= htmlspecialchars($title) . " 🔗</a>";
          $output .= "</div>";
        } else {
          $output .= "<div class='result-title' style='font-weight: bold;'>" . htmlspecialchars($title) . "</div>";
        }
        
        if (!empty($snippet)) {
          $output .= "<div class='result-snippet' style='margin-top: 5px; color: #666; font-size: 0.9em;'>";
          $output .= htmlspecialchars($snippet);
          $output .= "</div>";
        }
        
        $output .= "</div>";
      }
      
      $output .= "</div>";
    }

    // Display external URLs
    if (isset($subResult['urls']) && is_array($subResult['urls']) && !empty($subResult['urls'])) {
      $output .= "<div class='external-urls' style='margin: 10px 0;'>";
      $output .= "<strong>🔗 " . $this->language->getDef('text_rag_complex_query_external_links') . "</strong>";
      $output .= "<ul style='margin-top: 5px;'>";
      
      foreach ($subResult['urls'] as $url) {
        if (is_string($url)) {
          $output .= "<li><a href='" . htmlspecialchars($url) . "' target='_blank' rel='noopener noreferrer'>";
          $output .= htmlspecialchars($url) . " 🔗</a></li>";
        }
      }
      
      $output .= "</ul>";
      $output .= "</div>";
    }

    return $output;
  }

  /**
   * Format price comparison data
   * 
   * @param array $priceComparison Price comparison data
   * @return string Formatted HTML
   */
  private function formatPriceComparison(array $priceComparison): string
  {
    $output = "<div class='price-comparison' style='margin: 10px 0; padding: 10px; background-color: white; border-radius: 4px; border: 1px solid #28a745;'>";
    $output .= "<strong>💰 " . $this->language->getDef('text_rag_complex_query_price_comparison') . "</strong>";

    if (isset($priceComparison['internal_price'])) {
      $internalPrice = $priceComparison['internal_price'];
      $currency = $priceComparison['currency'] ?? '€';
      $output .= "<div style='margin-top: 8px;'>";
      $output .= "<strong>" . $this->language->getDef('text_rag_complex_query_our_price') . "</strong> " . number_format((float)$internalPrice, 2, ',', ' ') . " {$currency}";
      $output .= "</div>";
    }

    if (isset($priceComparison['external_prices']) && is_array($priceComparison['external_prices'])) {
      $output .= "<div style='margin-top: 8px;'>";
      $output .= "<strong>" . $this->language->getDef('text_rag_complex_query_competitor_prices') . "</strong>";
      $output .= "<ul style='margin: 5px 0;'>";
      
      foreach ($priceComparison['external_prices'] as $competitor) {
        $name = $competitor['name'] ?? 'Unknown';
        $price = $competitor['price'] ?? 0;
        $currency = $competitor['currency'] ?? '€';
        
        $output .= "<li>";
        $output .= htmlspecialchars($name) . " : " . number_format((float)$price, 2, ',', ' ') . " {$currency}";
        $output .= "</li>";
      }
      
      $output .= "</ul>";
      $output .= "</div>";
    }

    if (isset($priceComparison['recommendation'])) {
      $output .= "<div style='margin-top: 8px; padding: 8px; background-color: #d4edda; border-radius: 4px;'>";
      $output .= "<strong>💡 " . $this->language->getDef('text_rag_complex_query_recommendation') . "</strong> " . htmlspecialchars($priceComparison['recommendation']);
      $output .= "</div>";
    }

    $output .= "</div>";

    return $output;
  }

  /**
   * Format mixed source attribution (for hybrid queries)
   * 
   * @param array $sourceAttribution Source attribution data
   * @return string Formatted HTML
   */
  private function formatMixedSourceAttribution(array $sourceAttribution): string
  {
    if (empty($sourceAttribution)) {
      return '';
    }

    // Check if it's a mixed/hybrid source
    $sourceType = $sourceAttribution['source_type'] ?? '';
    $isMixed = (stripos($sourceType, 'mixed') !== false) || (stripos($sourceType, 'hybrid') !== false);

    if ($isMixed) {
      $output = '<div class="source-attribution alert alert-info" style="margin-top: 10px; padding: 10px; border-left: 4px solid #6c757d;">';
      $output .= '<h6 style="margin-top: 0;"><strong>🔀 ' . $this->language->getDef('text_rag_complex_query_multiple_sources') . '</strong></h6>';
      
      if (!empty($sourceAttribution['source_details'])) {
        $output .= '<div style="font-size: 0.9em; color: #666; margin-bottom: 8px;">';
        $output .= htmlspecialchars($sourceAttribution['source_details'] . "\n");
        $output .= '</div>';
      }
      
      // List all sources
      if (isset($sourceAttribution['sources']) && is_array($sourceAttribution['sources'])) {
        $output .= '<div style="font-size: 0.85em; color: #555;">';
        $output .= '<strong>' . $this->language->getDef('text_rag_complex_query_sources_used') . '</strong>';
        $output .= '<ul style="margin: 5px 0; padding-left: 20px;">';
        foreach ($sourceAttribution['sources'] as $source) {
          $output .= '<li>' . htmlspecialchars($source) . '</li>';
        }
        $output .= '</ul>';
        $output .= '</div>';
      }
      
      $output .= '</div>';
      
      return $output;
    }

    // Use standard source attribution for non-mixed sources
    return $this->formatSourceAttribution($sourceAttribution);
  }

  /**
   * Format aggregated data from multiple sub-queries (legacy support)
   * 
   * @param array $data Aggregated data
   * @return string Formatted HTML
   */
  private function formatAggregatedData(array $data): string
  {
    if (empty($data)) {
      return '';
    }

    $output = "<div class='aggregated-data'>";

    // Check if it's an array of sub-query results
    if (isset($data[0]['sub_query'])) {
      foreach ($data as $index => $subData) {
        $output .= "<div class='sub-data' style='margin-bottom: 15px;'>";
        $output .= "<strong>" . $this->language->getDef('text_rag_complex_query_sub_query') . " " . ($index + 1) . ":</strong> ";
        $output .= htmlspecialchars($subData['sub_query'] ?? 'N/A');

        if (!empty($subData['data'])) {
          $output .= $this->formatDataTable($subData['data']);
        }

        $output .= "</div>";
      }
    } else {
      // Simple aggregated data
      $output .= $this->formatDataTable($data);
    }

    $output .= "</div>";

    return $output;
  }

  /**
   * Format data as a table
   * 
   * @param array $data Data to format
   * @return string Formatted HTML table
   */
  private function formatDataTable(array $data): string
  {
    if (empty($data) || !is_array($data)) {
      return '';
    }

    // If it's a single row, wrap it in an array
    if (!isset($data[0])) {
      $data = [$data];
    }

    // Use inherited method from AbstractFormatter
    return $this->generateTable($data, 'table table-sm table-bordered mt-2');
  }

  /**
   * Format guardrails metrics
   * 
   * @param array $guardrails Guardrails data
   * @return string Formatted HTML
   */
  private function formatGuardrailsMetrics(array $guardrails): string
  {
    $output = "<div class='guardrails-metrics'>";
    // Add guardrails display logic here
    // This can be expanded based on the actual guardrails structure
    $output .= "</div>";
    return $output;
  }
}
