<?php
/**
 * AmbiguousResultFormatter.php
 * 
 * Formats ambiguous query results with multiple interpretations
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 * 
 * @package ClicShopping\AI\Helper\Formatter\SubResultFormatters
 * @author ClicShopping Team
 * @date 2025-12-06
 * @reorganization 2025-12-10 - Moved from Tools/Formatter to Helper/Formatter
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Helper\Formatter;

use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\OM\Registry;
use ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\SubResultFormatters\AbstractFormatter;

/**
 * Class AmbiguousResultFormatter
 * 
 * Formats results from ambiguous queries that have multiple interpretations
 * Displays each interpretation with its results clearly separated
 */

class AmbiguousResultFormatter extends AbstractFormatter
{
  /**
   * @var \ClicShopping\OM\Language Language instance for translations
   */
  private $language;

  /**
   * Constructor
   * 
   * @param bool $debug Enable debug mode
   * @param bool $displaySql Display SQL queries
   */
  public function __construct(bool $debug = false, bool $displaySql = false)
  {
    parent::__construct($debug, $displaySql);

    $this->language = Registry::get('Language');
    DomainConfig::loadAgnosticLanguageFile('rag_formatters', null);
  }
  
  /**
   * Check if this formatter can handle the given results
   * 
   * @param array $results The results to check
   * @return bool True if results are from ambiguous query
   */
  public function canHandle(array $results): bool
  {
    return isset($results['type']) && 
           $results['type'] === 'analytics_results_ambiguous' &&
           isset($results['interpretation_results']) &&
           is_array($results['interpretation_results']);
  }
  
  /**
   * Format ambiguous query results for display
   * 
   * @param array $results The results to format
   * @return array Formatted results with HTML content
   */
  public function format(array $results): array
  {
    $interpretations = $results['interpretation_results'] ?? [];
    $query = $results['query'] ?? '';
    $ambiguityType = $results['ambiguity_type'] ?? 'unknown';
    
    $html = '<div class="ambiguous-query-results">';
    
    // Ambiguity notice
    $html .= '<div class="ambiguity-notice alert alert-info">';
    $html .= '<strong>⚠️ ' . $this->language->getDef('text_rag_ambiguous_multiple_interpretations') . '</strong><br>';
    $html .= $this->language->getDef('text_rag_ambiguous_explanation');
    $html .= '</div>';
    
    // Each interpretation
    foreach ($interpretations as $index => $interpretation) {
      $html .= $this->formatInterpretation($interpretation, $index + 1);
    }
    
    // Recommendation
    if (isset($results['recommendation'])) {
      $html .= '<div class="ambiguity-recommendation alert alert-success">';
      $html .= '<strong>💡 ' . $this->language->getDef('text_rag_ambiguous_recommendation') . '</strong> ' . htmlspecialchars($results['recommendation']);
      $html .= '</div>';
    }
    
    $html .= '</div>';
    
    // Build source attribution
    $sourceAttribution = $this->buildSourceAttribution($results);
    
    return [
      'content' => $html,
      'source_attribution' => $sourceAttribution,
      'metadata' => [
        'ambiguous' => true,
        'ambiguity_type' => $ambiguityType,
        'interpretation_count' => count($interpretations)
      ],
      'type' => 'analytics_ambiguous'
    ];
  }
  
  /**
   * Format a single interpretation
   * 
   * @param array $interpretation Interpretation data
   * @param int $number Interpretation number
   * @return string HTML for interpretation
   */
  private function formatInterpretation(array $interpretation, int $number): string
  {
    $html = '<div class="interpretation-section card mb-3">';
    $html .= '<div class="card-header">';
    $html .= '<h4>' . $number . '. ' . htmlspecialchars($interpretation['label']) . '</h4>';
    $html .= '</div>';
    $html .= '<div class="card-body">';
    
    // Description
    $html .= '<p class="interpretation-description text-muted">';
    $html .= htmlspecialchars($interpretation['description']);
    $html .= '</p>';
    
    // SQL Query (collapsible)
    if (isset($interpretation['sql_query'])) {
      $html .= '<details class="sql-details mb-3">';
      $html .= '<summary class="text-primary" style="cursor: pointer;">' . $this->language->getDef('text_rag_ambiguous_view_sql') . '</summary>';
      $html .= '<pre class="sql-query bg-light p-2 mt-2"><code>';
      $html .= htmlspecialchars($interpretation['sql_query']);
      $html .= '</code></pre>';
      $html .= '</details>';
    }
    
    // Results
    $html .= '<div class="interpretation-results">';
    $html .= $this->formatInterpretationResults($interpretation);
    $html .= '</div>';
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
  }
  
  /**
   * Format results for a single interpretation
   * 
   * @param array $interpretation Interpretation with results
   * @return string HTML for results
   */
  private function formatInterpretationResults(array $interpretation): string
  {
    $results = $interpretation['results'] ?? [];
    $count = $interpretation['count'] ?? 0;
    
    if (empty($results)) {
      return '<p class="text-muted">' . $this->language->getDef('text_rag_ambiguous_no_results') . '</p>';
    }
    
    // Single value result (like COUNT or SUM)
    if ($count === 1 && count($results[0]) === 1) {
      $value = reset($results[0]);
      $key = key($results[0]);
      
      return '<div class="single-value-result">' .
             '<span class="result-value display-4">' . htmlspecialchars($value) . '</span>' .
             '<span class="result-label text-muted d-block">' . htmlspecialchars($key) . '</span>' .
             '</div>';
    }
    
    // Table result
    return $this->formatTableResults($results);
  }
  
  /**
   * Format results as a table
   * 
   * @param array $results Result rows
   * @return string HTML table
   */
  private function formatTableResults(array $results): string
  {
    if (empty($results)) {
      return '';
    }
    
    $html = '<div class="table-responsive">';
    $html .= '<table class="table table-striped table-sm">';
    
    // Header
    $html .= '<thead class="thead-light"><tr>';
    foreach (array_keys($results[0]) as $column) {
      $html .= '<th>' . htmlspecialchars($column) . '</th>';
    }
    $html .= '</tr></thead>';
    
    // Body
    $html .= '<tbody>';
    foreach ($results as $row) {
      $html .= '<tr>';
      foreach ($row as $value) {
        $html .= '<td>' . htmlspecialchars($value ?? '') . '</td>';
      }
      $html .= '</tr>';
    }
    $html .= '</tbody>';
    
    $html .= '</table>';
    $html .= '</div>';
    
    return $html;
  }
  
  /**
   * Build source attribution display
   * 
   * @param array $results The results
   * @return string HTML for source attribution
   */
  private function buildSourceAttribution(array $results): string
  {
    $interpretationCount = count($results['interpretation_results'] ?? []);
    
    return sprintf(
      '<div class="source-attribution">📊 Analytics Database (%d interpretations)</div>',
      $interpretationCount
    );
  }
}
