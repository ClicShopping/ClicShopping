<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Hybrid\Patterns\AmbiguityPreFilter;
use ClicShopping\AI\DomainsAI\Analytics\Patterns\TemporalConflictPattern;
use ClicShopping\OM\Cache as OMCache;


/**
 * AmbiguityOptimizer Class
 *
 * Optimizes ambiguity detection and SQL generation performance by:
 * 1. Using English pattern matching before expensive LLM calls
 * 2. Reducing number of interpretations from 3 to 2 (or 1 if clear)
 * 3. Using OM\Cache for caching (supports file, memory, and session-based caching)
 * 4. Implementing confidence threshold to skip unnecessary interpretations
 *
 * Performance Impact:
 * - Pattern matching: ~0.001s (vs ~1-2s for LLM detection)
 * - 2 interpretations: ~6s (vs ~9s for 3 interpretations)
 * - Cache hit: ~0.5s (vs ~9-14s for full generation)
 * - Total potential gain: ~8-13s per query
 */

class AmbiguityOptimizer
{
  private SecurityLogger $logger;
  private bool $debug;
  private bool $useCache;

  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    
    // Use OM\Cache system (configured via CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER)
    $this->useCache = \defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True';
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "AmbiguityOptimizer initialized with cache: " . ($this->useCache ? 'enabled' : 'disabled'),
        'info'
      );
    }
  }

  /**
   * Check if query is clearly non-ambiguous using pattern-based pre-filter
   *
   * ⚠️ EXCEPTION TO PURE LLM APPROACH:
   * Uses AmbiguityPreFilter for temporal expressions and quantitative language
   * because LLM is inconsistent (85% success rate). This ensures 100% consistency.
   * 
   * This pre-filter is ALWAYS enabled (not configurable) because it's critical
   * for production consistency.
   *
   * @param string $translatedQuery Query in ENGLISH (already translated)
   * @return array ['is_clear' => bool, 'pattern_type' => string|null, 'prefilter_result' => array|null]
   */
  public function isClearlyNonAmbiguous(string $translatedQuery): array
  {
    // ⚠️ CRITICAL: Always use AmbiguityPreFilter (EXCEPTION to Pure LLM)
    // This ensures 100% consistency for temporal expressions + quantitative language
    // LLM is only 85% consistent, production requires 95%+
    
    $preFilterResult = AmbiguityPreFilter::preFilter($translatedQuery);
    
    if ($preFilterResult !== null) {
      // Pre-filter determined result
      // Check if the result is ambiguous or not
      $isAmbiguous = $preFilterResult['is_ambiguous'] ?? false;
      
      if ($isAmbiguous) {
        // Pre-filter determined query IS ambiguous (e.g., temporal_period_scope)
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "AmbiguityOptimizer: Pre-filter determined query IS ambiguous: {$preFilterResult['reasoning']}",
            'info',
            ['query' => $translatedQuery, 'ambiguity_type' => $preFilterResult['ambiguity_type']]
          );
        }
        
        return [
          'is_clear' => false,
          'pattern_type' => 'prefilter_ambiguous',
          'matched_text' => $translatedQuery,
          'reason' => $preFilterResult['reasoning'],
          'prefilter_result' => $preFilterResult
        ];
      }
      
      // Pre-filter determined query is NOT ambiguous
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AmbiguityOptimizer: Pre-filter determined query is NOT ambiguous: {$preFilterResult['reasoning']}",
          'info',
          ['query' => $translatedQuery]
        );
      }
      
      return [
        'is_clear' => true,
        'pattern_type' => 'prefilter',
        'matched_text' => $translatedQuery,
        'reason' => $preFilterResult['reasoning']
      ];
    }
    
    // Pre-filter could not determine - use LLM
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "AmbiguityOptimizer: Pre-filter passed, using LLM for ambiguity detection",
        'info',
        ['query' => $translatedQuery]
      );
    }
    
    return [
      'is_clear' => false,
      'pattern_type' => null,
      'matched_text' => null,
      'reason' => 'prefilter_passed_use_llm'
    ];
  }

  /**
   * Determine optimal number of interpretations based on query complexity
   *
   * Reduces from 3 to 2 interpretations for most queries to improve performance.
   * Only generates 1 interpretation for clearly non-ambiguous queries.
   *
   * @param string $translatedQuery Query in ENGLISH
   * @param array $ambiguityAnalysis Ambiguity analysis from detector
   * @return int Number of interpretations to generate (1 or 2)
   */
  public function getOptimalInterpretationCount(string $translatedQuery, array $ambiguityAnalysis): int
  {
    // Check if clearly non-ambiguous using patterns
    $clearCheck = $this->isClearlyNonAmbiguous($translatedQuery);
    if ($clearCheck['is_clear']) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AmbiguityOptimizer: Clear pattern detected, using 1 interpretation",
          'info'
        );
      }
      return 1;
    }

    // Check ambiguity type
    $ambiguityType = $ambiguityAnalysis['ambiguity_type'] ?? '';
    $ambiguityTypes = explode('|', $ambiguityType);

    // Default: 2 interpretations (good balance between coverage and performance)
    // We removed the 3-interpretation case to always optimize performance
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "AmbiguityOptimizer: Ambiguous query ({$ambiguityType}), generating 2 interpretations",
        'info'
      );
    }
    return 2;
  }

  /**
   * Select which interpretations to generate based on query analysis
   *
   * Prioritizes the most likely interpretations to avoid generating
   * unnecessary SQL queries. "recent" is deprioritized as it's rarely useful.
   *
   * @param array $ambiguityAnalysis Ambiguity analysis from detector
   * @param int $count Number of interpretations to generate
   * @return array Array of interpretation types to generate
   */
  public function selectInterpretations(array $ambiguityAnalysis, int $count): array
  {
    $availableInterpretations = $ambiguityAnalysis['interpretations'] ?? ['sum', 'count'];
    
    // 🔧 FIX: If interpretations array is empty, use defaults based on ambiguity type
    if (empty($availableInterpretations)) {
      $ambiguityType = $ambiguityAnalysis['ambiguity_type'] ?? '';
      
      // For quantification queries, default to count and sum
      if (str_contains($ambiguityType, 'quantification')) {
        $availableInterpretations = ['count', 'sum'];
        
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "AmbiguityOptimizer: Empty interpretations array, using defaults for quantification: count, sum",
            'info'
          );
        }
      } else {
        // For other ambiguity types, use general defaults
        $availableInterpretations = ['sum', 'count', 'list'];
        
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "AmbiguityOptimizer: Empty interpretations array, using general defaults: sum, count, list",
            'info'
          );
        }
      }
    }
    
    // Priority order: sum > count > list > recent
    // "recent" is rarely what users want, so it's last
    $priority = ['sum', 'count', 'list', 'recent'];
    
    $selected = [];
    foreach ($priority as $type) {
      if (in_array($type, $availableInterpretations, true)) {
        $selected[] = $type;
        if (count($selected) >= $count) {
          break;
        }
      }
    }
    
    // Fill remaining with any available interpretations
    foreach ($availableInterpretations as $type) {
      if (!in_array($type, $selected, true)) {
        $selected[] = $type;
        if (count($selected) >= $count) {
          break;
        }
      }
    }
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "AmbiguityOptimizer: Selected {$count} interpretations: " . implode(', ', $selected),
        'info'
      );
    }
    
    return $selected;
  }

 /**
   * Get cached ambiguity analysis
   *
   * Uses OM\Cache system (file-based with in-memory caching).
   *
   * @param string $query Query to check
   * @return array|null Cached analysis or null if not found
   */
  public function getCachedAmbiguityAnalysis(string $query): ?array
  {
    if (!$this->useCache) {
      return null;
    }

    $cacheKey = 'ambiguity_' . md5($query);

    try {
      $cache = new OMCache($cacheKey, 'Rag/Ambiguity');
      
      if ($cache->exists()) {
        return $cache->get();
      }
      
      return null;
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AmbiguityOptimizer: Cache read error: " . $e->getMessage(),
          'warning'
        );
      }
      return null;
    }
  }

  /**
   * Cache ambiguity analysis
   *
   * Uses OM\Cache system (file-based with in-memory caching).
   *
   * @param string $query Query
   * @param array $analysis Analysis to cache
   * @param int $ttl Time to live in seconds (default: 1 hour) - Note: OM\Cache uses file timestamps
   */
  public function cacheAmbiguityAnalysis(string $query, array $analysis, int $ttl = 3600): void
  {
    if (!$this->useCache) {
      return;
    }

    $cacheKey = 'ambiguity_' . md5($query);

    try {
      $cache = new OMCache($cacheKey, 'Rag/Ambiguity');
      $cache->save($analysis, ['ttl' => $ttl]);
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AmbiguityOptimizer: Cache write error: " . $e->getMessage(),
          'warning'
        );
      }
    }
  }

  /**
   * Get statistics about optimizer performance
   *
   * @return array Statistics
   */
  public function getStatistics(): array
  {
    return [
      'cache_enabled' => $this->useCache,
      'cache_system' => 'OM\\Cache', // Uses ClicShopping's unified caching system
      'default_interpretation_count' => 2,
      'confidence_threshold' => 0.85,
      'prefilter_enabled' => true, // AmbiguityPreFilter is always enabled (EXCEPTION to Pure LLM)
    ];
  }

  /**
   * Detect conflicting temporal periods in a query
   *
   * **Requirement 8.1**: Detect conflicts in AmbiguityOptimizer
   *
   * Conflicting temporal periods occur when:
   * 1. A query specifies both a specific period AND a different aggregation level
   *    (e.g., "monthly revenue for Q1" - Q1 is a quarter, but asking for monthly)
   * 2. A query specifies incompatible time ranges
   *    (e.g., "weekly data for January 15" - a single day can't have weekly data)
   * 3. A query has overlapping temporal specifications
   *    (e.g., "by month and by week for the same period")
   *
   * @param string $translatedQuery Query in ENGLISH (already translated)
   * @param array $temporalPeriods Array of detected temporal periods
   * @param string|null $timeRange Detected time range
   * @return array Conflict detection result with structure:
   *   - has_conflict: bool (true if conflict detected)
   *   - conflict_type: string|null (type of conflict)
   *   - conflict_details: string|null (human-readable explanation)
   *   - suggested_clarification: string|null (question to ask user)
   *   - conflicting_elements: array (elements that conflict)
   */
  public function detectTemporalConflicts(
    string $translatedQuery,
    array $temporalPeriods,
    ?string $timeRange
  ): array {
    $defaultResult = [
      'has_conflict' => false,
      'conflict_type' => null,
      'conflict_details' => null,
      'suggested_clarification' => null,
      'conflicting_elements' => [],
    ];

    // No conflict possible with 0 or 1 temporal periods
    if (count($temporalPeriods) < 1) {
      return $defaultResult;
    }

    $query = strtolower($translatedQuery);
    $conflicts = [];

    // 1. Check for specific period + different aggregation conflict
    // e.g., "monthly revenue for Q1" or "weekly sales for January"
    $specificPeriodConflicts = $this->detectSpecificPeriodConflict($query, $temporalPeriods, $timeRange);
    if ($specificPeriodConflicts['has_conflict']) {
      $conflicts[] = $specificPeriodConflicts;
    }

    // 2. Check for incompatible granularity conflict
    // e.g., "weekly data for a single day"
    $granularityConflicts = $this->detectGranularityConflict($query, $temporalPeriods, $timeRange);
    if ($granularityConflicts['has_conflict']) {
      $conflicts[] = $granularityConflicts;
    }

    // 3. Check for overlapping temporal specifications
    // e.g., "by month and by week for the same period" without clear separation
    $overlapConflicts = $this->detectOverlapConflict($query, $temporalPeriods);
    if ($overlapConflicts['has_conflict']) {
      $conflicts[] = $overlapConflicts;
    }

    // If no conflicts found, return default
    if (empty($conflicts)) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AmbiguityOptimizer: No temporal conflicts detected",
          'info',
          ['query' => $translatedQuery, 'temporal_periods' => $temporalPeriods]
        );
      }
      return $defaultResult;
    }

    // Return the most significant conflict (first one found)
    $primaryConflict = $conflicts[0];

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "AmbiguityOptimizer: Temporal conflict detected - " . $primaryConflict['conflict_type'],
        'warning',
        [
          'query' => $translatedQuery,
          'temporal_periods' => $temporalPeriods,
          'time_range' => $timeRange,
          'conflict_details' => $primaryConflict['conflict_details']
        ]
      );
    }

    return $primaryConflict;
  }

  /**
   * Detect conflict between specific period and aggregation level
   *
   * @param string $query Lowercase query
   * @param array $temporalPeriods Detected temporal periods
   * @param string|null $timeRange Detected time range
   * @return array Conflict result
   */
  private function detectSpecificPeriodConflict(string $query, array $temporalPeriods, ?string $timeRange): array
  {
    $defaultResult = [
      'has_conflict' => false,
      'conflict_type' => null,
      'conflict_details' => null,
      'suggested_clarification' => null,
      'conflicting_elements' => [],
    ];

    if ($timeRange === null) {
      return $defaultResult;
    }

    // Get period hierarchy from pattern class
    $periodHierarchy = TemporalConflictPattern::getPeriodHierarchy();

    // Detect time range granularity
    $timeRangeGranularity = TemporalConflictPattern::detectTimeRangeGranularity($timeRange);

    // Check if any requested aggregation is finer than the time range allows
    foreach ($temporalPeriods as $period) {
      $periodLevel = $periodHierarchy[strtolower($period)] ?? 3;
      $rangeLevel = $periodHierarchy[$timeRangeGranularity] ?? 6;

      // Conflict: asking for aggregation finer than the time range
      // e.g., "monthly data for a specific day" or "weekly data for Q1" (Q1 is quarter level)
      if ($periodLevel > $rangeLevel) {
        return [
          'has_conflict' => true,
          'conflict_type' => 'specific_period_aggregation_mismatch',
          'conflict_details' => "Cannot aggregate by '{$period}' for a time range of '{$timeRange}' (granularity: {$timeRangeGranularity}). The aggregation period is larger than the time range.",
          'suggested_clarification' => "Did you mean to get {$period}ly data for a longer period, or did you want a different aggregation for '{$timeRange}'?",
          'conflicting_elements' => [
            'requested_aggregation' => $period,
            'time_range' => $timeRange,
            'time_range_granularity' => $timeRangeGranularity,
          ],
        ];
      }
    }

    return $defaultResult;
  }

  /**
   * Detect granularity conflict (aggregation too fine for time range)
   *
   * @param string $query Lowercase query
   * @param array $temporalPeriods Detected temporal periods
   * @param string|null $timeRange Detected time range
   * @return array Conflict result
   */
  private function detectGranularityConflict(string $query, array $temporalPeriods, ?string $timeRange): array
  {
    $defaultResult = [
      'has_conflict' => false,
      'conflict_type' => null,
      'conflict_details' => null,
      'suggested_clarification' => null,
      'conflicting_elements' => [],
    ];

    if ($timeRange === null) {
      return $defaultResult;
    }

    // Check for single day + weekly/monthly aggregation
    if (TemporalConflictPattern::isSingleDayRange($timeRange)) {
      $coarseAggregationPeriods = TemporalConflictPattern::getCoarseAggregationPeriods();
      foreach ($temporalPeriods as $period) {
        if (in_array(strtolower($period), $coarseAggregationPeriods, true)) {
          return [
            'has_conflict' => true,
            'conflict_type' => 'granularity_too_coarse',
            'conflict_details' => "Cannot aggregate by '{$period}' for a single day ('{$timeRange}'). A single day cannot be broken down into {$period}s.",
            'suggested_clarification' => "Did you want daily data for '{$timeRange}', or did you mean to specify a longer time range for {$period}ly aggregation?",
            'conflicting_elements' => [
              'requested_aggregation' => $period,
              'time_range' => $timeRange,
              'issue' => 'single_day_with_coarse_aggregation',
            ],
          ];
        }
      }
    }

    return $defaultResult;
  }

  /**
   * Detect overlapping temporal specifications without clear separation
   *
   * @param string $query Lowercase query
   * @param array $temporalPeriods Detected temporal periods
   * @return array Conflict result
   */
  private function detectOverlapConflict(string $query, array $temporalPeriods): array
  {
    $defaultResult = [
      'has_conflict' => false,
      'conflict_type' => null,
      'conflict_details' => null,
      'suggested_clarification' => null,
      'conflicting_elements' => [],
    ];

    // Check for potentially confusing combinations without clear connectors
    // e.g., "by month week" without "and" or "then"
    $hasConnector = TemporalConflictPattern::hasTemporalConnector($query);

    if (count($temporalPeriods) >= 2 && !$hasConnector) {
      // Check if periods are adjacent in the query without clear separation
      $periodsPattern = implode('|', array_map('preg_quote', $temporalPeriods));
      if (preg_match('/\b(' . $periodsPattern . ')\s+(' . $periodsPattern . ')\b/i', $query)) {
        return [
          'has_conflict' => true,
          'conflict_type' => 'ambiguous_temporal_combination',
          'conflict_details' => "Multiple temporal periods detected without clear separation: " . implode(', ', $temporalPeriods) . ". It's unclear if you want separate aggregations or a combined view.",
          'suggested_clarification' => "Do you want to see data aggregated by " . implode(' AND THEN by ', $temporalPeriods) . " (separate views), or did you mean something else?",
          'conflicting_elements' => [
            'temporal_periods' => $temporalPeriods,
            'issue' => 'missing_connector',
          ],
        ];
      }
    }

    return $defaultResult;
  }

  /**
   * Request clarification from user for temporal conflicts
   *
   * **Requirement 8.1**: Request clarification from user
   *
   * @param array $conflictResult Result from detectTemporalConflicts()
   * @return array Clarification request with structure:
   *   - needs_clarification: bool
   *   - clarification_type: string
   *   - message: string (user-facing message)
   *   - options: array (possible interpretations)
   */
  public function requestTemporalClarification(array $conflictResult): array
  {
    if (!$conflictResult['has_conflict']) {
      return [
        'needs_clarification' => false,
        'clarification_type' => null,
        'message' => null,
        'options' => [],
      ];
    }

    $options = [];
    $conflictType = $conflictResult['conflict_type'];
    $elements = $conflictResult['conflicting_elements'];

    switch ($conflictType) {
      case 'specific_period_aggregation_mismatch':
        $options = [
          [
            'id' => 'extend_range',
            'label' => "Extend time range to allow {$elements['requested_aggregation']} aggregation",
            'action' => 'modify_time_range',
          ],
          [
            'id' => 'change_aggregation',
            'label' => "Use {$elements['time_range_granularity']} aggregation instead",
            'action' => 'modify_aggregation',
          ],
        ];
        break;

      case 'granularity_too_coarse':
        $options = [
          [
            'id' => 'use_daily',
            'label' => "Show daily data for {$elements['time_range']}",
            'action' => 'use_daily_aggregation',
          ],
          [
            'id' => 'extend_range',
            'label' => "Extend time range to allow {$elements['requested_aggregation']} aggregation",
            'action' => 'modify_time_range',
          ],
        ];
        break;

      case 'ambiguous_temporal_combination':
        $periods = $elements['temporal_periods'] ?? [];
        $options = [
          [
            'id' => 'separate_views',
            'label' => "Show separate views: first by " . ($periods[0] ?? 'period1') . ", then by " . ($periods[1] ?? 'period2'),
            'action' => 'split_queries',
          ],
          [
            'id' => 'primary_only',
            'label' => "Show only " . ($periods[0] ?? 'primary') . " aggregation",
            'action' => 'use_primary_period',
          ],
        ];
        break;

      default:
        $options = [
          [
            'id' => 'proceed_anyway',
            'label' => "Proceed with best interpretation",
            'action' => 'auto_resolve',
          ],
          [
            'id' => 'rephrase',
            'label' => "Let me rephrase my question",
            'action' => 'user_rephrase',
          ],
        ];
    }

    // Log clarification request
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "AmbiguityOptimizer: Requesting temporal clarification",
        'info',
        [
          'conflict_type' => $conflictType,
          'options_count' => count($options),
        ]
      );
    }

    return [
      'needs_clarification' => true,
      'clarification_type' => $conflictType,
      'message' => $conflictResult['suggested_clarification'],
      'options' => $options,
    ];
  }

  /**
   * Check if query should use confidence threshold optimization
   *
   * If the first interpretation has high confidence, we can skip
   * generating additional interpretations.
   *
   * @param float $firstInterpretationConfidence Confidence of first interpretation (0-1)
   * @return bool True if should skip additional interpretations
   */
  public function shouldUseConfidenceThreshold(float $firstInterpretationConfidence): bool
  {
    $threshold = 0.85; // High confidence threshold

    if ($firstInterpretationConfidence >= $threshold) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "AmbiguityOptimizer: First interpretation has high confidence ({$firstInterpretationConfidence}), skipping others",
          'info'
        );
      }
      return true;
    }

    return false;
  }
}
