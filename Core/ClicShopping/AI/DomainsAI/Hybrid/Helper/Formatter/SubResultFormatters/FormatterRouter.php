<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Helper\Formatter\SubResultFormatters;

/**
 * FormatterRouter - Intelligent routing system to select the appropriate formatter
 * based on query complexity and result type
 */

class FormatterRouter
{
  private array $formatters = [];
  private bool $debug;

  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
  }

  /**
   * Register a formatter
   */
  public function registerFormatter(AbstractFormatter $formatter, int $priority = 50): void
  {
    $this->formatters[] = [
      'formatter' => $formatter,
      'priority' => $priority,
      'class' => get_class($formatter)
    ];

    // Sort by priority (higher priority first)
    usort($this->formatters, fn($a, $b) => $b['priority'] <=> $a['priority']);

    if ($this->debug) {
      error_log('[INFO FormatterRouter]: Registered formatter ' . get_class($formatter) . ' with priority ' . $priority);
    }
  }

  /**
   * Route to the appropriate formatter based on results
   */
  public function route(array $results): ?AbstractFormatter
  {
    if (empty($results)) {
      if ($this->debug) {
        error_log('[INFO FormatterRouter]: Empty results provided');
      }
      return null;
    }

    // Analyze result complexity
    $complexity = $this->analyzeComplexity($results);

    if ($this->debug) {
      error_log('[INFO : HYBRID]FormatterRouter: Analyzing results');
      error_log('   Type: ' . ($results['type'] ?? 'NONE'));
      error_log('   Has analytics_component: ' . (isset($results['analytics_component']) ? 'YES' : 'NO'));
      error_log('   Has semantic_component: ' . (isset($results['semantic_component']) ? 'YES' : 'NO'));
      error_log('   Complexity: ' . $complexity['level'] . ' (score: ' . $complexity['score'] . ')');
      error_log('   Factors: ' . implode(', ', $complexity['factors']));
    }

    // Find the best formatter
    foreach ($this->formatters as $entry) {
      $formatter = $entry['formatter'];

      if ($this->debug) {
        error_log('[INFO : Testing formatter]: ' . $entry['class']);
      }

      if ($formatter->canHandle($results)) {
        if ($this->debug) {
          error_log('[INFO] Selected formatter: ' . $entry['class'] . ' (priority: ' . $entry['priority'] . ')');
        }
        return $formatter;
      }
    }

    if ($this->debug) {
      error_log('[INFO] No formatter found for type: ' . ($results['type'] ?? 'NONE'));
      error_log('[INFO] Available formatters: ' . count($this->formatters));
    }

    return null;
  }

  /**
   * Analyze the complexity of the results
   * Returns: ['level' => 'simple|medium|complex', 'score' => int, 'factors' => array]
   */
  public function analyzeComplexity(array $results): array
  {
    $score = 0;
    $factors = [];

    // Factor 1: Result type
    $type = $results['type'] ?? '';
    if (in_array($type, ['complex_query', 'hybrid'], true)) {
      $score += 30;
      $factors[] = 'complex_type';
      if ($this->debug) {
        error_log('[INFO] Complexity factor: complex_type (+30)');
      }
    } elseif (in_array($type, ['analytics_results', 'analytics_response'], true)) {
      $score += 10;
      $factors[] = 'analytics_type';
      if ($this->debug) {
        error_log('[INFO] Complexity factor: analytics_type (+10)');
      }
    }

    // Factor 2: Multiple sub-results
    if (!empty($results['sub_results'])) {
      $subCount = count($results['sub_results']);
      $points = min($subCount * 10, 30);
      $score += $points;
      $factors[] = "sub_results_count:{$subCount}";
      if ($this->debug) {
        error_log('[INFO] Complexity factor: sub_results_count:' . $subCount . ' (+' . $points . ')');
      }
    }

    // Factor 3: Multiple data sources
    if (!empty($results['data']) && is_array($results['data'])) {
      if (isset($results['data'][0]['sub_query'])) {
        $score += 20;
        $factors[] = 'multiple_data_sources';
        if ($this->debug) {
          error_log('[INFO] Complexity factor: multiple_data_sources (+20)');
        }
      }
    }

    // Factor 4: Has SQL query (indicates analytics)
    if (!empty($results['sql_query'])) {
      $score += 5;
      $factors[] = 'has_sql';
      if ($this->debug) {
        error_log('[INFO] Complexity factor: has_sql (+5)');
      }
    }

    // Factor 5: Has web search results
    if (!empty($results['web_results']) || !empty($results['sources'])) {
      $score += 15;
      $factors[] = 'has_web_search';
      if ($this->debug) {
        error_log('[INFO] Complexity factor: has_web_search (+15)');
      }
    }

    // Factor 6: Large result set
    if (!empty($results['results']) && is_array($results['results'])) {
      $resultCount = count($results['results']);
      if ($resultCount > 10) {
        $score += 10;
        $factors[] = "large_result_set:{$resultCount}";
        if ($this->debug) {
          error_log('[INFO] Complexity factor: large_result_set:' . $resultCount . ' (+10)');
        }
      }
    }

    // Determine complexity level
    $level = 'simple';
    if ($score >= 50) {
      $level = 'complex';
    } elseif ($score >= 20) {
      $level = 'medium';
    }

    if ($this->debug) {
      error_log('[INFO] Final complexity: ' . $level . ' (score: ' . $score . ')');
    }

    return [
      'level' => $level,
      'score' => $score,
      'factors' => $factors
    ];
  }

  /**
   * Get all registered formatters
   */
  public function getFormatters(): array
  {
    return $this->formatters;
  }

  /**
   * Get complexity analysis for debugging
   */
  public function getComplexityReport(array $results): string
  {
    $complexity = $this->analyzeComplexity($results);

    $report = "Complexity Analysis:\n";
    $report .= "  Level: {$complexity['level']}\n";
    $report .= "  Score: {$complexity['score']}\n";
    $report .= "  Factors:\n";

    foreach ($complexity['factors'] as $factor) {
      $report .= "    - {$factor}\n";
    }

    return $report;
  }
}
