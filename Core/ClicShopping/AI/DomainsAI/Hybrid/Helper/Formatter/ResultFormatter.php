<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter;


use ClicShopping\OM\Registry;
use ClicShopping\OM\Language;
use ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\SubResultFormatters\FormatterRouter;
use ClicShopping\AI\DomainsAI\Analytics\Helper\Formatter\AnalyticsFormatter;
use ClicShopping\AI\DomainsAI\Semantic\Helper\Formatter\SemanticFormatter;
use ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\SubResultFormatters\ComplexQueryFormatter;
use ClicShopping\AI\DomainsAI\WebSearch\Helper\Formatter\WebSearchFormatter;
use ClicShopping\AI\DomainsAI\Analytics\Helper\Formatter\AmbiguousResultFormatter;
use ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\SubResultFormatters\HybridFormatter;

/**
 * ResultFormatter Class (Refactored - Clean Version)
 *
 * Main orchestrator that uses FormatterRouter to select the appropriate
 * SubResultFormatter based on result type and complexity.
 *
 * REFACTORING NOTES:
 * - Removed ~810 lines of duplicate/dead code (68% reduction)
 * - All utility functions now in AbstractFormatter (used by SubFormatters)
 * - formatAnalyticsResults() and formatSemanticResults() removed (SubFormatters handle this)
 * - formatMemoryContext() removed (never called)
 * - formatGuardrailsMetrics() removed (SubFormatters have their own versions)
 *
 * @version 2.0 - Refactored 2025-12-30
 */

class ResultFormatter
{
  private FormatterRouter $router;
  private bool $debug;
  private bool $displaySql;
  private $language;

  /**
   * Constructor
   * Initializes the formatter router and registers all SubFormatters
   */
  public function __construct()
  {
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                   CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->displaySql = defined('CLICSHOPPING_APP_CHATGPT_RA_DISPLAY_SQL') && 
                        CLICSHOPPING_APP_CHATGPT_RA_DISPLAY_SQL === 'True';

    // Initialize language support
    If(!Registry::exists('Language')) {
      Registry::set('Language', new Language());
    }
    
    $this->language = Registry::get('Language');

    // Load language definitions (null = use current user language)
    Registry::get('Language')->loadDefinitions('ClicShoppingAdmin/ai_response_labels');

    // Initialize router
    $this->router = new FormatterRouter($this->debug);

    // Register formatters with priorities (higher = checked first)
    // HybridFormatter must have higher priority than ComplexQueryFormatter
    $this->router->registerFormatter(new HybridFormatter($this->debug, $this->displaySql), 105);
    $this->router->registerFormatter(new ComplexQueryFormatter($this->debug, $this->displaySql), 100);
    $this->router->registerFormatter(new AmbiguousResultFormatter($this->debug, $this->displaySql), 90);
    $this->router->registerFormatter(new AnalyticsFormatter($this->debug, $this->displaySql), 80);
    $this->router->registerFormatter(new WebSearchFormatter($this->debug, $this->displaySql), 70);
    $this->router->registerFormatter(new SemanticFormatter($this->debug, $this->displaySql), 60);
  }

  /**
   * Formats the results based on their type using intelligent routing
   *
   * @param array $results The results to format
   * @return array The formatted results ['type' => 'formatted_results', 'content' => 'HTML']
   */
  public function format(array $results): array
  {
    // Ensure we have a valid results array
    if (empty($results) || !is_array($results)) {
      return [
        'type' => 'formatted_results',
        'content' => '<div class="alert alert-warning">' . htmlspecialchars($this->language->getDef('no_results_to_display')) . '</div>'
      ];
    }

    // Check if user input is required (price comparison mode selection)
    // If so, return it directly without formatting - the chat interface will handle the prompt display
    if (isset($results['type']) && $results['type'] === 'user_input_required') {
      if ($this->debug) {
        error_log('[DEBUG] ResultFormatter: User input required, returning without formatting');
      }
      
      // Return the response as-is for the chat interface to handle
      return $results;
    }

    // If it's an error, return it as is
    if (isset($results['type']) && $results['type'] === 'error') {
      return $results;
    }

    // If it's a clarification request, format it properly
    if (isset($results['type']) && $results['type'] === 'clarification_needed') {
      if (!empty($results['clarification_options']) && is_array($results['clarification_options'])) {
        $message = $this->language->getDef('clarification_needed_message');
        $optionsHtml = '<div class="row">';
        foreach ($results['clarification_options'] as $option) {
          $optionsHtml .= '- ' . htmlspecialchars((string)$option) . "\n";
        }
        $optionsHtml .= '</div>';
      } else {
        $message = $results['text_response'] ?? $results['response'] ?? $results['message'] ?? $this->language->getDef('clarification_needed_message');
        // Collapse runs of blank lines / multiple newlines into a single one so nl2br
        // renders a single <br> between lines instead of stacks of <br><br>.
        $message = preg_replace('/\s*[\r\n]+\s*/', "\n", trim((string)$message));
        $optionsHtml = '';
      }

      return [
        'type' => 'formatted_results',
        'content' => '<div class="alert alert-warning"><i class="bi bi-question-circle"></i> <strong>' . htmlspecialchars($this->language->getDef('clarification_needed_label')) . '</strong><br>' . nl2br(htmlspecialchars($message)) . $optionsHtml . '</div>'
      ];
    }

    // Use router to find appropriate formatter
    $formatter = $this->router->route($results);

    if ($formatter) {
      $formatterClass = get_class($formatter);
      $resultType = $results['type'] ?? 'unknown';
      
      // ✅ ALWAYS LOG SYNTHESIS OPERATIONS (Requirement 8.4)
      $startTime = microtime(true);
      $formattedResult = $formatter->format($results);
      $executionTime = round((microtime(true) - $startTime) * 1000, 2);
      
      $contentLength = isset($formattedResult['content']) ? strlen($formattedResult['content']) : 0;
      
      error_log(sprintf(
        '[INFO][RAG] Synthesis: type=%s, formatter=%s, length=%d, time=%dms',
        $resultType,
        basename(str_replace('\\', '/', $formatterClass)),
        $contentLength,
        $executionTime
      ));
      
      if ($this->debug) {
        error_log('[DEBUG] ResultFormatter: Using ' . $formatterClass);
        error_log('[DEBUG] ' . $this->router->getComplexityReport($results));
      }

      return $formattedResult;
    }

    // Fallback: return raw data
    if ($this->debug) {
      error_log('[WARNING] ResultFormatter: No formatter found, using fallback');
    }

    return [
      'type' => 'formatted_results',
      'content' => '<div class="alert alert-info"><strong>' . htmlspecialchars($this->language->getDef('raw_result_label')) . '</strong><pre>'
        . htmlspecialchars(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
        . '</pre></div>'
    ];
  }

  /**
   * Formats the results with memory context integration
   * 
   * Memory is used internally to improve responses, but should not be shown to users
   * 
   * @param array $results The results to format
   * @param array $memoryContext Memory context from ConversationMemory
   * @return array The formatted results with memory metadata (no visual display)
   */
  public function formatWithMemory(array $results, array $memoryContext): array
  {
    // First, format the results normally
    $formattedResults = $this->format($results);
    
    // If memory context is empty or not relevant, return normal formatting
    if (empty($memoryContext) || !$this->hasRelevantMemoryContext($memoryContext)) {
      return $formattedResults;
    }
    
    // ✅ FIX: DO NOT add visual memory display to content
    // Memory context is used internally but should not be shown in chat
    // Only add metadata for tracking purposes
    
    // Add memory metadata (for logging/debugging only)
    $formattedResults['has_memory_context'] = true;
    $formattedResults['memory_metadata'] = [
      'short_term_count' => count($memoryContext['short_term_context'] ?? []),
      'long_term_count' => count($memoryContext['long_term_context'] ?? []),
      'feedback_count' => count($memoryContext['feedback_context'] ?? []),
    ];
    
    if ($this->debug) {
      error_log('[DEBUG] ResultFormatter: Memory context used (not displayed) - ' . 'Short-term: ' . $formattedResults['memory_metadata']['short_term_count']
        . ', Long-term: ' . $formattedResults['memory_metadata']['long_term_count']
        . ', Feedback: ' . $formattedResults['memory_metadata']['feedback_count'] . "\n");
    }
    
    return $formattedResults;
  }

  /**
   * Checks if memory context contains relevant information
   * 
   * @param array $memoryContext Memory context data
   * @return bool True if context is relevant
   */
  private function hasRelevantMemoryContext(array $memoryContext): bool
  {
    // Check if has_context flag is set
    if (isset($memoryContext['has_context']) && !$memoryContext['has_context']) {
      return false;
    }
    
    // Check if any context arrays have content
    $hasShortTerm = !empty($memoryContext['short_term_context']);
    $hasLongTerm = !empty($memoryContext['long_term_context']);
    $hasFeedback = !empty($memoryContext['feedback_context']);
    
    return $hasShortTerm || $hasLongTerm || $hasFeedback;
  }

  // ========================================================================
  // STATIC UTILITY METHODS (Used by HybridQueryProcessor)
  // ========================================================================

  /**
   * Format analytics results as plain text table
   * Moved from HybridQueryProcessor (Task 2.11.3)
   *
   * @param array $data Analytics data with rows
   * @param object|null $language Optional language object for translations
   * @return string Formatted text table
   */
  public static function formatAnalyticsAsText(array $data, $language = null): string
  {
    // Initialize language if not provided
    if ($language === null) {
      $language = Registry::get('Language');
      
      // Load language definitions (null = use current user language)
      Registry::get('Language')->loadDefinitions('ClicShoppingAdmin/ai_response_labels');
    }
    
    $formatted = $language->getDef('analytics_results_title') . "\n\n";
    
    foreach ($data as $index => $result) {
      if (isset($result['rows']) && is_array($result['rows'])) {
        $formatted .= $language->getDef('analytics_result_number') . " " . ($index + 1) . ":\n";
        $formatted .= $language->getDef('analytics_rows_count') . " " . count($result['rows']) . "\n";
        
        // Format as simple table
        if (!empty($result['rows'])) {
          $firstRow = $result['rows'][0];
          if (is_array($firstRow)) {
            $formatted .= implode(" | ", array_keys($firstRow)) . "\n";
            $formatted .= str_repeat("-", 50) . "\n";
            
            foreach ($result['rows'] as $row) {
              $formatted .= implode(" | ", array_values($row)) . "\n";
            }
          }
        }
        $formatted .= "\n";
      }
    }
    
    return $formatted;
  }

  /**
   * Format web search results with citations as plain text
   * Moved from HybridQueryProcessor (Task 2.11.3)
   *
   * @param array $texts Text results
   * @param array $sources Source URLs with titles and snippets
   * @param object|null $language Optional language object for translations
   * @return string Formatted text with citations
   */
  public static function formatWebSearchAsText(array $texts, array $sources, $language = null): string
  {
    // Initialize language if not provided
    if ($language === null) {
      $language = Registry::get('Language');
      
      // Load language definitions (null = use current user language)
      Registry::get('Language')->loadDefinitions('ClicShoppingAdmin/ai_response_labels');
    }
    
    $formatted = $language->getDef('web_search_results_title') . "\n\n";
    
    // If we have structured sources with snippets, format them nicely
    if (!empty($sources)) {
      foreach ($sources as $index => $source) {
        if (is_array($source)) {
          $formatted .= ($index + 1) . ". " . ($source['title'] ?? 'Source') . "\n";
          
          if (!empty($source['snippet'])) {
            $formatted .= "   " . $source['snippet'] . "\n";
          }
          
          if (!empty($source['url'])) {
            $formatted .= "   " . $language->getDef('web_search_source_label') . " " . $source['url'] . "\n";
          }
          
          $formatted .= "\n";
        } elseif (is_string($source)) {
          $formatted .= ($index + 1) . ". " . $source . "\n\n";
        }
      }
    }
    
    // Add any additional text results
    if (!empty($texts)) {
      $formatted .= "\n" . $language->getDef('web_search_additional_info') . "\n";
      $formatted .= implode("\n\n", $texts) . "\n";
    }
    
    return trim($formatted);
  }

  /**
   * Format price comparison report as plain text
   * Moved from HybridQueryProcessor (Task 2.11.3)
   *
   * @param array $comparison Comparison data from comparePrice()
   * @param object|null $language Optional language object for translations
   * @return string Formatted price comparison report
   */
  public static function formatPriceComparisonAsText(array $comparison, $language = null): string
  {
    // Initialize language if not provided
    if ($language === null) {
      $language = Registry::get('Language');
      
      // Load language definitions (null = use current user language)
      Registry::get('Language')->loadDefinitions('ClicShoppingAdmin/ai_response_labels');
    }
    
    $output = "📊 " . $language->getDef('price_comparison_title') . "\n";
    $output .= str_repeat("=", 60) . "\n\n";
    
    $output .= $language->getDef('price_comparison_product') . " {$comparison['product_name']}\n";
    $output .= $language->getDef('price_comparison_your_price') . " \${$comparison['internal_price']}\n\n";
    
    if ($comparison['total_competitors_found'] > 0) {
      $output .= $language->getDef('price_comparison_competitors_analyzed') . " {$comparison['total_competitors_found']}\n";
      $output .= $language->getDef('price_comparison_average_price') . " \$" . $comparison['comparison']['average_competitor_price'] . "\n\n";
      
      // Display competitor prices
      $output .= $language->getDef('price_comparison_competitor_prices') . "\n";
      foreach ($comparison['competitor_prices'] as $i => $competitor) {
        $output .= "  " . ($i + 1) . ". {$competitor['source']}: \${$competitor['price']}\n";
      }
      $output .= "\n";
      
      // Display cheapest and most expensive
      if ($comparison['comparison']['cheapest']) {
        $cheapest = $comparison['comparison']['cheapest'];
        $output .= "💰 " . $language->getDef('price_comparison_cheapest') . " {$cheapest['source']} at \${$cheapest['competitor_price']}\n";
        $output .= "   " . $language->getDef('price_comparison_difference') . " \${$cheapest['difference']} ({$cheapest['percentage_difference']}%)\n\n";
      }
      
      if ($comparison['comparison']['most_expensive']) {
        $expensive = $comparison['comparison']['most_expensive'];
        $output .= "💎 " . $language->getDef('price_comparison_most_expensive') . " {$expensive['source']} at \${$expensive['competitor_price']}\n";
        $output .= "   " . $language->getDef('price_comparison_difference') . " \${$expensive['difference']} ({$expensive['percentage_difference']}%)\n\n";
      }
      
      // Display competitive status
      $statusEmoji = [
        'very_competitive' => '🟢',
        'competitive' => '🟡',
        'not_competitive' => '🔴',
        'unknown' => '⚪',
      ];
      
      $emoji = $statusEmoji[$comparison['competitive_status']] ?? '⚪';
      $output .= "{$emoji} " . $language->getDef('price_comparison_competitive_status') . " " . strtoupper($comparison['competitive_status']) . "\n\n";
      
      // Display recommendation
      $output .= "💡 " . $language->getDef('price_comparison_recommendation') . "\n";
      $output .= str_repeat("-", 60) . "\n";
      $output .= $comparison['recommendation'] . "\n";
      $output .= str_repeat("-", 60) . "\n";
      
    } else {
      $output .= "⚠️  " . $language->getDef('price_comparison_no_competitors') . "\n";
      $output .= $language->getDef('price_comparison_recommendation_label') . " {$comparison['recommendation']}\n";
    }
    
    return $output;
  }
}
