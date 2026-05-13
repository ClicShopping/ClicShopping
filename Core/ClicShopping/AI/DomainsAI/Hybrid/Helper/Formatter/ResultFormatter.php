<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
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
use ClicShopping\AI\Config\DomainConfig;

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
  private string $languageCode;

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
    $this->languageCode = $this->language->get('code');

    // Load language definitions (null = use current user language)
    DomainConfig::loadLanguageFile('rag_result_formatter', null);

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
      $message = $results['message'] ?? $this->language->getDef('clarification_needed_message');
      return [
        'type' => 'formatted_results',
        'content' => '<div class="alert alert-warning"><i class="bi bi-question-circle"></i> <strong>' . htmlspecialchars($this->language->getDef('clarification_needed_label')) . '</strong><br>' . htmlspecialchars($message) . '</div>'
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
      $languageCode = $language->get('code');
      
      // Load language definitions (null = use current user language)
      DomainConfig::loadLanguageFile('rag_result_formatter', null);
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
      $languageCode = $language->get('code');
      
      // Load language definitions (null = use current user language)
      DomainConfig::loadLanguageFile('rag_result_formatter', null);
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

  // ================================================================================
  // TEMPORAL FORMATTING METHODS (Task 7 - Multi-Temporal Query Detection)
  // Requirements: 7.2, 7.3, 7.4, 7.5
  // ================================================================================

  /**
   * Format temporal results with section headers and labels
   * 
   * Formats multi-temporal query results with clear section headers,
   * temporal labels, and responsive HTML structure.
   * 
   * Requirements: 7.2, 7.3, 7.4, 7.5
   * 
   * @param array $results Array of temporal results with temporal_period metadata
   * @param string $languageCode The user's language code (en, fr, es, de)
   * @return array Formatted results ['type' => 'formatted_results', 'content' => 'HTML']
   */
  public function formatTemporalResults(array $results, string $languageCode = 'en'): array
  {
    $startTime = microtime(true);
    
    if (empty($results)) {
      error_log('[WARNING][RAG] TemporalFormatter: No results to format');
      return [
        'type' => 'formatted_results',
        'content' => '<div class="alert alert-warning">' . htmlspecialchars($this->language->getDef('no_results_to_display')) . '</div>'
      ];
    }

    if ($this->debug) {
      error_log('[DEBUG][RAG] TemporalFormatter: Starting temporal formatting');
      error_log('[DEBUG][RAG] TemporalFormatter: Results count=' . count($results) . ', language=' . $languageCode);
    }

    $output = '<div class="temporal-results-container" style="width: 100%;">';
    
    // Process each temporal result section
    foreach ($results as $index => $result) {
      $temporalPeriod = $result['temporal_period'] ?? null;
      
      if ($temporalPeriod === null) {
        // No temporal period, format as generic result
        if (isset($result['content']) || isset($result['text_response'])) {
          $content = $result['content'] ?? $result['text_response'] ?? '';
          $output .= '<div class="temporal-section generic-section" style="margin-bottom: 20px;">';
          $output .= '<div class="section-content">' . $content . '</div>';
          $output .= '</div>';
        }
        continue;
      }

      // Get section header
      $sectionHeader = $this->getTemporalSectionHeader($temporalPeriod, $languageCode);
      $sectionIcon = $this->getTemporalSectionIcon($temporalPeriod);
      
      // Build section HTML
      $output .= $this->buildTemporalSection($result, $temporalPeriod, $sectionHeader, $sectionIcon, $languageCode);
    }

    $output .= '</div>';

    // Add responsive CSS
    $output .= $this->getTemporalResultsCSS();

    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    
    // Log formatting event
    error_log(sprintf(
      '[INFO][RAG] TemporalFormatter: Completed formatting - sections=%d, language=%s, time=%dms',
      count($results),
      $languageCode,
      $executionTime
    ));

    return [
      'type' => 'formatted_results',
      'content' => $output
    ];
  }

  /**
   * Build HTML for a single temporal section
   * 
   * @param array $result The result data for this section
   * @param string $temporalPeriod The temporal period type
   * @param string $sectionHeader The localized section header
   * @param string $sectionIcon The icon for this section
   * @param string $languageCode The user's language code
   * @return string HTML for the section
   */
  private function buildTemporalSection(array $result, string $temporalPeriod, string $sectionHeader, string $sectionIcon, string $languageCode): string
  {
    $output = '<div class="temporal-section" style="margin-bottom: 25px; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">';
    
    // Section header
    $output .= '<div class="temporal-section-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 15px;">';
    $output .= '<h5 style="margin: 0; font-size: 1.1em;">' . $sectionIcon . ' ' . htmlspecialchars($sectionHeader) . '</h5>';
    $output .= '</div>';
    
    // Section content
    $output .= '<div class="temporal-section-content" style="padding: 15px;">';
    
    // Check for different content types
    if (isset($result['rows']) && is_array($result['rows']) && !empty($result['rows'])) {
      // Format as table with temporal labels
      $output .= $this->formatTemporalDataTable($result['rows'], $temporalPeriod, $languageCode);
    } elseif (isset($result['result']) && is_array($result['result'])) {
      if (isset($result['result']['rows'])) {
        $output .= $this->formatTemporalDataTable($result['result']['rows'], $temporalPeriod, $languageCode);
      } else {
        $output .= $this->formatTemporalDataTable($result['result'], $temporalPeriod, $languageCode);
      }
    } elseif (isset($result['text_response']) && !empty($result['text_response'])) {
      // Text response
      $output .= '<div class="temporal-text-response">' . nl2br(htmlspecialchars($result['text_response'])) . '</div>';
    } elseif (isset($result['content']) && !empty($result['content'])) {
      // HTML content
      $output .= '<div class="temporal-html-content">' . $result['content'] . '</div>';
    } else {
      // No data available
      $output .= '<div class="alert alert-info" style="margin: 0;">';
      $output .= '<i class="bi bi-info-circle"></i> No data available for this period.';
      $output .= '</div>';
    }
    
    $output .= '</div>'; // Close section-content
    $output .= '</div>'; // Close temporal-section
    
    return $output;
  }

  /**
   * Format data table with temporal labels
   * 
   * @param array $rows Data rows
   * @param string $temporalPeriod The temporal period type
   * @param string $languageCode The user's language code
   * @return string HTML table
   */
  private function formatTemporalDataTable(array $rows, string $temporalPeriod, string $languageCode): string
  {
    if (empty($rows)) {
      return '<div class="alert alert-info">No data available.</div>';
    }

    $output = '<div class="table-responsive">';
    $output .= '<table class="table table-bordered table-striped table-hover" style="margin-bottom: 0;">';
    
    // Generate headers
    $firstRow = reset($rows);
    if (is_array($firstRow)) {
      $output .= '<thead class="thead-light">';
      $output .= '<tr>';
      foreach (array_keys($firstRow) as $column) {
        $displayColumn = $this->mapTemporalColumnName($column, $temporalPeriod, $languageCode);
        $output .= '<th style="white-space: nowrap;">' . htmlspecialchars($displayColumn) . '</th>';
      }
      $output .= '</tr>';
      $output .= '</thead>';
      
      // Generate rows with temporal labels
      $output .= '<tbody>';
      foreach ($rows as $row) {
        $output .= '<tr>';
        foreach ($row as $column => $value) {
          $formattedValue = $this->formatTemporalCellValue($column, $value, $temporalPeriod, $languageCode);
          $output .= '<td>' . $formattedValue . '</td>';
        }
        $output .= '</tr>';
      }
      $output .= '</tbody>';
    }
    
    $output .= '</table>';
    $output .= '</div>';
    
    // Add row count
    $output .= '<div class="text-muted small mt-2" style="text-align: right;">';
    $output .= count($rows) . ' ' . (count($rows) === 1 ? 'row' : 'rows\n');
    $output .= '</div>';
    
    return $output;
  }

  /**
   * Map column name to display name with temporal context
   * 
   * @param string $column Column name
   * @param string $temporalPeriod Temporal period type
   * @param string $languageCode Language code
   * @return string Display name
   */
  private function mapTemporalColumnName(string $column, string $temporalPeriod, string $languageCode): string
  {
    // Check for period-related columns
    $periodColumns = ['period', 'month', 'quarter', 'semester', 'year', 'week', 'day', 'MONTH', 'QUARTER', 'YEAR', 'WEEK'];
    
    if (in_array($column, $periodColumns, true) || stripos($column, 'period') !== false) {
      return $this->getTemporalColumnLabel($temporalPeriod, $languageCode);
    }
    
    // Standard column name mapping
    return ucwords(str_replace('_', ' ', $column));
  }

  /**
   * Get temporal column label
   * 
   * @param string $temporalPeriod Temporal period type
   * @param string $languageCode Language code
   * @return string Column label
   */
  private function getTemporalColumnLabel(string $temporalPeriod, string $languageCode): string
  {
    $labels = [
      'month' => ['en' => 'Month', 'fr' => 'Mois', 'es' => 'Mes', 'de' => 'Monat'],
      'quarter' => ['en' => 'Quarter', 'fr' => 'Trimestre', 'es' => 'Trimestre', 'de' => 'Quartal'],
      'semester' => ['en' => 'Semester', 'fr' => 'Semestre', 'es' => 'Semestre', 'de' => 'Semester'],
      'year' => ['en' => 'Year', 'fr' => 'Année', 'es' => 'Año', 'de' => 'Jahr'],
      'week' => ['en' => 'Week', 'fr' => 'Semaine', 'es' => 'Semana', 'de' => 'Woche'],
      'day' => ['en' => 'Day', 'fr' => 'Jour', 'es' => 'Día', 'de' => 'Tag'],
    ];
    
    $periodLabels = $labels[strtolower($temporalPeriod)] ?? null;
    if ($periodLabels === null) {
      return 'Period';
    }
    
    return $periodLabels[strtolower($languageCode)] ?? $periodLabels['en'];
  }

  /**
   * Format cell value with temporal context
   * 
   * @param string $column Column name
   * @param mixed $value Cell value
   * @param string $temporalPeriod Temporal period type
   * @param string $languageCode Language code
   * @return string Formatted value
   */
  private function formatTemporalCellValue(string $column, $value, string $temporalPeriod, string $languageCode): string
  {
    if ($value === null || $value === '') {
      return '-';
    }

    // Check if this is a period column that needs temporal formatting
    $periodColumns = ['period', 'month', 'quarter', 'semester', 'year', 'week', 'day', 'MONTH', 'QUARTER', 'YEAR', 'WEEK'];
    
    if (in_array($column, $periodColumns, true) || stripos($column, 'period') !== false) {
      return $this->formatTemporalLabel($temporalPeriod, $value, $languageCode);
    }
    
    // Format numeric values
    if (is_numeric($value)) {
      // Check for price/amount columns
      if (preg_match('/(price|amount|revenue|total|cost|value)/i', $column)) {
        $currency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : '€';
        return number_format((float)$value, 2, ',', ' ') . ' ' . $currency;
      }
      // Check for quantity columns
      if (preg_match('/(quantity|count|number|stock)/i', $column)) {
        return number_format((int)$value, 0, ',', ' ');
      }
      // Default numeric formatting
      return number_format((float)$value, 2, ',', ' ');
    }
    
    return htmlspecialchars((string)$value);
  }

  /**
   * Format temporal label based on period type and value
   * 
   * Converts raw temporal values (1, 2, 3...) into human-readable labels
   * in the user's language (January, Q1, Semester 1, etc.)
   * 
   * Requirements: 7.2, 7.3, 7.4, 7.5
   * 
   * @param string $temporalPeriod The temporal period type
   * @param mixed $value The raw value
   * @param string $languageCode The user's language code
   * @return string The formatted temporal label
   */
  public function formatTemporalLabel(string $temporalPeriod, $value, string $languageCode = 'en'): string
  {
    return match (strtolower($temporalPeriod)) {
      'month' => $this->formatMonthLabel($value, $languageCode),
      'quarter' => $this->formatQuarterLabel($value, $languageCode),
      'semester' => $this->formatSemesterLabel($value, $languageCode),
      'year' => (string)$value,
      'week' => $this->formatWeekLabel($value, $languageCode),
      'day' => $this->formatDayLabel($value, $languageCode),
      default => (string)$value
    };
  }

  /**
   * Format month label
   * 
   * @param mixed $value Month number (1-12) or month name
   * @param string $languageCode Language code
   * @return string Formatted month name
   */
  private function formatMonthLabel($value, string $languageCode): string
  {
    if (is_string($value) && !is_numeric($value)) {
      return $value; // Already a month name
    }

    $monthNumber = (int)$value;
    if ($monthNumber < 1 || $monthNumber > 12) {
      return (string)$value;
    }

    $months = [
      'en' => ['January', 'February', 'March', 'April', 'May', 'June', 
               'July', 'August', 'September', 'October', 'November', 'December'],
      'fr' => ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
               'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
      'es' => ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
      'de' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
               'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
    ];

    $monthNames = $months[strtolower($languageCode)] ?? $months['en'];
    return $monthNames[$monthNumber - 1] ?? (string)$value;
  }

  /**
   * Format quarter label
   * 
   * @param mixed $value Quarter number (1-4)
   * @param string $languageCode Language code
   * @return string Formatted quarter label
   */
  private function formatQuarterLabel($value, string $languageCode): string
  {
    $quarterNumber = (int)$value;
    if ($quarterNumber < 1 || $quarterNumber > 4) {
      return (string)$value;
    }

    $prefixes = ['en' => 'Q', 'fr' => 'T', 'es' => 'T', 'de' => 'Q'];
    $prefix = $prefixes[strtolower($languageCode)] ?? 'Q';
    return $prefix . $quarterNumber;
  }

  /**
   * Format semester label
   * 
   * @param mixed $value Semester number (1-2)
   * @param string $languageCode Language code
   * @return string Formatted semester label
   */
  private function formatSemesterLabel($value, string $languageCode): string
  {
    $semesterNumber = (int)$value;
    if ($semesterNumber < 1 || $semesterNumber > 2) {
      return (string)$value;
    }

    $labels = [
      'en' => ['Semester 1', 'Semester 2'],
      'fr' => ['Semestre 1', 'Semestre 2'],
      'es' => ['Semestre 1', 'Semestre 2'],
      'de' => ['Semester 1', 'Semester 2'],
    ];

    $semesterLabels = $labels[strtolower($languageCode)] ?? $labels['en'];
    return $semesterLabels[$semesterNumber - 1] ?? (string)$value;
  }

  /**
   * Format week label
   * 
   * @param mixed $value Week number
   * @param string $languageCode Language code
   * @return string Formatted week label
   */
  private function formatWeekLabel($value, string $languageCode): string
  {
    $weekNumber = (int)$value;
    $labels = ['en' => 'Week', 'fr' => 'Semaine', 'es' => 'Semana', 'de' => 'Woche'];
    $label = $labels[strtolower($languageCode)] ?? 'Week';
    return "{$label} {$weekNumber}";
  }

  /**
   * Format day label
   * 
   * @param mixed $value Date value
   * @param string $languageCode Language code
   * @return string Formatted day label
   */
  private function formatDayLabel($value, string $languageCode): string
  {
    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
      $formats = ['en' => 'Y-m-d', 'fr' => 'd/m/Y', 'es' => 'd/m/Y', 'de' => 'd.m.Y'];
      $format = $formats[strtolower($languageCode)] ?? 'Y-m-d';
      return date($format, strtotime($value));
    }
    return (string)$value;
  }

  /**
   * Get section header for temporal period
   * 
   * @param string $temporalPeriod The temporal period type
   * @param string $languageCode The user's language code
   * @return string The section header
   */
  public function getTemporalSectionHeader(string $temporalPeriod, string $languageCode = 'en'): string
  {
    $headers = [
      'month' => ['en' => 'Monthly Results', 'fr' => 'Résultats mensuels', 'es' => 'Resultados mensuales', 'de' => 'Monatliche Ergebnisse'],
      'quarter' => ['en' => 'Quarterly Results', 'fr' => 'Résultats trimestriels', 'es' => 'Resultados trimestrales', 'de' => 'Quartalsergebnisse'],
      'semester' => ['en' => 'Semester Results', 'fr' => 'Résultats semestriels', 'es' => 'Resultados semestrales', 'de' => 'Semesterergebnisse'],
      'year' => ['en' => 'Yearly Results', 'fr' => 'Résultats annuels', 'es' => 'Resultados anuales', 'de' => 'Jährliche Ergebnisse'],
      'week' => ['en' => 'Weekly Results', 'fr' => 'Résultats hebdomadaires', 'es' => 'Resultados semanales', 'de' => 'Wöchentliche Ergebnisse'],
      'day' => ['en' => 'Daily Results', 'fr' => 'Résultats quotidiens', 'es' => 'Resultados diarios', 'de' => 'Tägliche Ergebnisse'],
    ];

    $periodHeaders = $headers[strtolower($temporalPeriod)] ?? null;
    if ($periodHeaders === null) {
      $customHeaders = ['en' => 'Custom Period Results', 'fr' => 'Résultats par période personnalisée', 'es' => 'Resultados por período personalizado', 'de' => 'Ergebnisse nach benutzerdefiniertem Zeitraum'];
      return $customHeaders[strtolower($languageCode)] ?? $customHeaders['en'];
    }

    return $periodHeaders[strtolower($languageCode)] ?? $periodHeaders['en'];
  }

  /**
   * Get icon for temporal section
   * 
   * @param string $temporalPeriod The temporal period type
   * @return string Icon emoji
   */
  private function getTemporalSectionIcon(string $temporalPeriod): string
  {
    $icons = [
      'month' => '📅',
      'quarter' => '📊',
      'semester' => '📈',
      'year' => '📆',
      'week' => '🗓️',
      'day' => '📋',
    ];

    return $icons[strtolower($temporalPeriod)] ?? '📊';
  }

  /**
   * Get CSS for temporal results (responsive design)
   * 
   * Requirements: 7.4, 7.5 - Responsive design for mobile
   * 
   * @return string CSS styles
   */
  private function getTemporalResultsCSS(): string
  {
    return '
    <style>
    .temporal-results-container {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    
    .temporal-section {
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      transition: box-shadow 0.3s ease;
    }
    
    .temporal-section:hover {
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .temporal-section-header h5 {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .temporal-section-content .table {
      font-size: 0.95em;
    }
    
    .temporal-section-content .table th {
      background-color: #f8f9fa;
      font-weight: 600;
    }
    
    .temporal-section-content .table td {
      vertical-align: middle;
    }
    
    /* Responsive design for mobile */
    @media (max-width: 768px) {
      .temporal-section {
        margin-bottom: 15px;
      }
      
      .temporal-section-header {
        padding: 10px 12px;
      }
      
      .temporal-section-header h5 {
        font-size: 1em;
      }
      
      .temporal-section-content {
        padding: 10px;
      }
      
      .temporal-section-content .table {
        font-size: 0.85em;
      }
      
      .temporal-section-content .table th,
      .temporal-section-content .table td {
        padding: 8px 6px;
      }
      
      .table-responsive {
        margin: 0 -10px;
        padding: 0 10px;
      }
    }
    
    /* Extra small devices */
    @media (max-width: 480px) {
      .temporal-section-content .table {
        font-size: 0.8em;
      }
      
      .temporal-section-content .table th,
      .temporal-section-content .table td {
        padding: 6px 4px;
      }
    }
    
    /* Print styles */
    @media print {
      .temporal-section {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ccc;
      }
      
      .temporal-section-header {
        background: #f0f0f0 !important;
        color: #333 !important;
        -webkit-print-color-adjust: exact;
      }
    }
    </style>';
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
      $languageCode = $language->get('code');
      
      // Load language definitions (null = use current user language)
      DomainConfig::loadLanguageFile('rag_result_formatter', null);
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
