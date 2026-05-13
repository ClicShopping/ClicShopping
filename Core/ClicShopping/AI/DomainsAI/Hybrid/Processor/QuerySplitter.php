<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\DomainsAI\Hybrid\Processor;


use ClicShopping\OM\Registry;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\DomainsAI\Hybrid\Patterns\QuerySplitterPatterns;
use ClicShopping\AI\Config\DomainConfig;

/**
 * QuerySplitter - Splits complex queries into sub-queries
 *
 * Responsibilities:
 * - Detect report/analysis queries and split into analytics + semantic + web_search
 * - Detect comma-separated intents and split accordingly
 * - Detect "and then" patterns and split sequentially
 * - Detect multiple questions (multiple "?") and split accordingly
 * - Handle analytics + analytics combinations (e.g., "stock AND sales")
 * - Use LLM for intelligent splitting with fallback to simple splitting
 *
 * Requirements:
 * - REQ-3.1: Detect report/analysis queries and split into analytics + semantic + web_search
 * - REQ-3.2: Detect comma-separated intents and split accordingly
 * - REQ-3.3: Detect "and then" patterns and split sequentially
 * - REQ-3.4: Detect multiple questions (multiple "?") and split accordingly
 * - REQ-3.5: Handle analytics + analytics combinations
 * - REQ-3.6: Use LLM for intelligent splitting with fallback
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubHybridQueryProcessor
 * @since 2025-12-14
 * @version 2.0 - Internationalized 2025-12-30
 */

class QuerySplitter extends BaseQueryProcessor
{
  /**
   * @var QueryClassifier Query classifier for sub-query classification
   */
  private QueryClassifier $classifier;

  /**
   * @var array Translation cache for performance optimization
   */
  private static array $translationCache = [];

  /**
   * @var int Translation cache TTL in seconds (1 hour)
   */
  private const TRANSLATION_CACHE_TTL = 3600;

  /**
   * @var object Language object for translations
   */
  private $language;

  /**
   * @var string Current language code
   */
  private string $languageCode;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   * @param QueryClassifier|null $classifier Query classifier instance (auto-created if null)
   */
  public function __construct(bool $debug = false, ?QueryClassifier $classifier = null)
  {
    parent::__construct($debug, 'QuerySplitter');
    $this->classifier = $classifier ?? new QueryClassifier($debug);
    
    // Initialize language support
    $this->language = Registry::get('Language');
    $this->languageCode = $this->language->get('code');
    DomainConfig::loadLanguageFile('rag_query_splitter');
    
    if ($this->debug) {
      $this->logInfo("QuerySplitter initialized - Pure LLM mode only");
    }
  }

  /**
   * Process query splitting
   *
   * @param mixed $input Query string to split or array with 'query' and 'intent'
   * @param array $context Additional context (may include 'intent' for hybrid detection)
   * @return array Array of sub-queries with types and priorities
   * @throws \Exception If input is invalid
   */
  public function process($input, array $context = []): array
  {
    if (!$this->validate($input)) {
      throw new \Exception("Invalid input for QuerySplitter: input must be a non-empty string or array with 'query' key");
    }

    // Extract query and intent from input
    if (is_array($input)) {
      $query = $input['query'] ?? '';
      $intent = $input['intent'] ?? [];
    } else {
      $query = $input;
      $intent = $context['intent'] ?? [];
    }

    // Check if query is price comparison - handle first (Requirements 1, 5)
    if ($this->isPriceComparisonQuery($query, $intent)) {
      if ($this->debug) {
        $this->logInfo("Detected price comparison query, using price comparison splitting");
      }
      return $this->decomposePriceComparisonQuery($query, $intent);
    }

    // If the intent contains temporal metadata, use temporal splitting
    if ($this->isMultiTemporalQuery($intent)) {
      if ($this->debug) {
        $this->logInfo("Detected multi-temporal query, using temporal splitting", [
          'temporal_periods' => $intent['temporal_periods'] ?? [],
          'temporal_connectors' => $intent['temporal_connectors'] ?? [],
          'base_metric' => $intent['base_metric'] ?? null,
          'time_range' => $intent['time_range'] ?? null,
        ]);
      }
      return $this->splitByTemporalPeriods($query, $intent);
    }

    // Check if query is hybrid
    if ($this->detectMultipleIntents($query, $intent)) {
      return $this->splitHybridQuery($query, $intent);
    }

    // Check if query is complex
    return $this->splitComplexQuery($query);
  }

  /**
   * Validate input is a non-empty string or array with 'query' key
   *
   * @param mixed $input Input to validate
   * @return bool True if valid, false otherwise
   */
  public function validate($input): bool
  {
    if (is_string($input)) {
      return !empty(trim($input));
    }
    
    if (is_array($input)) {
      return isset($input['query']) && !empty(trim($input['query']));
    }
    
    return false;
  }

  /**
   * Check if intent indicates a multi-temporal query
   *
   * A multi-temporal query has:
   * - is_multi_temporal flag set to true, OR
   * - Multiple temporal periods (>= 2) AND temporal connectors
   *
   * @param array $intent Intent analysis from UnifiedQueryAnalyzer
   * @return bool True if multi-temporal query detected
   */
  private function isMultiTemporalQuery(array $intent): bool
  {
    // Check explicit flag
    if (isset($intent['is_multi_temporal']) && $intent['is_multi_temporal'] === true) {
      return true;
    }
    
    // Check temporal metadata
    $temporalPeriods = $intent['temporal_periods'] ?? [];
    $temporalConnectors = $intent['temporal_connectors'] ?? [];
    $periodCount = $intent['temporal_period_count'] ?? count($temporalPeriods);
    
    // Multi-temporal if >= 2 periods AND has connectors
    return $periodCount >= 2 && !empty($temporalConnectors);
  }

  /**
   * Split query by temporal periods
   *
   * Creates one sub-query per temporal period, preserving:
   * - Base metric (revenue, sales, etc.)
   * - Time range (year 2025, this year, etc.)
   * - Assigns correct temporal period to each sub-query
   *
   * **Validates: Requirements 4.1, 4.2, 4.3, 4.4**
   *
   * @param string $query Original query
   * @param array $intent Intent analysis with temporal metadata
   * @return array Array of sub-queries with temporal periods
   */
  public function splitByTemporalPeriods(string $query, array $intent): array
  {
    try {
      $temporalPeriods = $intent['temporal_periods'] ?? [];
      $baseMetric = $intent['base_metric'] ?? $this->extractBaseMetricFromQuery($query);
      $timeRange = $intent['time_range'] ?? $this->extractTimeRangeFromQuery($query);
      
      if (empty($temporalPeriods)) {
        if ($this->debug) {
          $this->logWarning("No temporal periods found, falling back to hybrid split");
        }
        return $this->splitHybridQuery($query, $intent);
      }
      
      if ($this->debug) {
        $this->logInfo("Splitting by temporal periods", [
          'period_count' => count($temporalPeriods),
          'periods' => $temporalPeriods,
          'base_metric' => $baseMetric,
          'time_range' => $timeRange,
        ]);
      }
      
      $subQueries = [];
      
      foreach ($temporalPeriods as $index => $period) {
        // Build sub-query for this temporal period
        $subQueryText = $this->buildTemporalSubQuery($baseMetric, $timeRange, $period);
        
        $subQuery = [
          'query' => $subQueryText,
          'type' => 'analytics', // Temporal aggregations are always analytics
          'confidence' => 0.95,
          'priority' => $index + 1,
          'temporal_period' => $period,
          'base_metric' => $baseMetric,
          'time_range' => $timeRange,
          'original_query' => $query,
          'is_temporal_split' => true,
        ];
        
        $subQueries[] = $subQuery;
        
        if ($this->debug) {
          $this->logInfo("Created temporal sub-query", [
            'index' => $index + 1,
            'period' => $period,
            'sub_query' => $subQueryText,
          ]);
        }
      }
      
      // Log temporal splitting event
      if ($this->debug) {
        $this->logInfo("Temporal splitting complete", [
          'original_query' => $query,
          'sub_query_count' => count($subQueries),
          'temporal_periods' => $temporalPeriods,
          'base_metric' => $baseMetric,
          'time_range' => $timeRange,
        ]);
      }
      
      return $subQueries;
      
    } catch (\Exception $e) {
      $this->logError("Error in splitByTemporalPeriods", $e, ['query' => $query]);
      // Fallback to hybrid split
      return $this->splitHybridQuery($query, $intent);
    }
  }

  /**
   * Build a sub-query for a specific temporal period
   *
   * @param string|null $baseMetric Base metric (revenue, sales, etc.)
   * @param string|null $timeRange Time range (year 2025, this year, etc.)
   * @param string $temporalPeriod Temporal period (month, quarter, semester, etc.)
   * @return string Sub-query text
   */
  private function buildTemporalSubQuery(?string $baseMetric, ?string $timeRange, string $temporalPeriod): string
  {
    // Default metric if not detected
    $metric = $baseMetric ?? 'revenue';
    
    // Build query parts
    $parts = [];
    
    // Add metric
    $parts[] = $metric;
    
    // Add time range if present
    if (!empty($timeRange)) {
      $parts[] = "for {$timeRange}";
    }
    
    // Add temporal aggregation
    $parts[] = "by {$temporalPeriod}";
    
    return implode(' ', $parts);
  }

  /**
   * Extract base metric from query text
   * 
   * NOTE: Pure LLM mode - Query is already translated to English at this point.
   * Only English patterns are needed. Non-English patterns removed per tech.md guidelines.
   * Pattern logic moved to QuerySplitterPatterns class.
   *
   * @param string $query Query text (already translated to English)
   * @return string|null Base metric or null if not found
   */
  private function extractBaseMetricFromQuery(string $query): ?string
  {
    return QuerySplitterPatterns::extractBaseMetric($query);
  }

  /**
   * Extract time range from query text
   * 
   * NOTE: Pure LLM mode - Query is already translated to English at this point.
   * Only English patterns are needed. Non-English patterns removed per tech.md guidelines.
   * Pattern logic moved to QuerySplitterPatterns class.
   *
   * @param string $query Query text (already translated to English)
   * @return string|null Time range or null if not found
   */
  private function extractTimeRangeFromQuery(string $query): ?string
  {
    return QuerySplitterPatterns::extractTimeRange($query);
  }

  /**
   * Detect if query contains multiple intents (hybrid query)
   *
   * NOTE: Pure LLM mode - pattern-based hybrid detection is disabled
   * Relies on QueryClassifier's LLM classification for hybrid detection
   *
   * @param string $query Query to analyze
   * @param array $intent Intent analysis (optional)
   * @return bool True if hybrid query detected
   */
  public function detectMultipleIntents(string $query, array $intent = []): bool
  {
    try {
      // Check both is_hybrid flag AND type === 'hybrid'
      if ($intent['is_hybrid'] ?? false) return true;
      if (($intent['type'] ?? '') === 'hybrid') return true;

      // In Pure LLM mode, rely on QueryClassifier's LLM classification
      // which already handles hybrid detection
      if ($this->debug) {
        $this->logInfo("Pattern-based hybrid detection disabled in Pure LLM mode");
      }
      return false;
    } catch (\Exception $e) {
      $this->logError("Error in detectMultipleIntents", $e);
      return false;
    }
  }

  /**
   * Split hybrid query into sub-queries using LLM
   *
   * @param string $query Original query
   * @param array $intent Intent analysis
   * @return array Array of sub-queries with their types
   */
  public function splitHybridQuery(string $query, array $intent): array
  {
    try {
      if ($this->debug) $this->logInfo("Splitting hybrid query", ['query' => $query]);

      // Get prompt from language file and replace {query} placeholder
      $prompt = $this->language->getDef('prompt_split_hybrid', array('query' => $query));

      $validatedPrompt = $this->validatePrompt($prompt);
      if (empty($validatedPrompt)) {
        $this->logWarning("Prompt validation failed, using simple split");
        return $this->simpleSplit($query);
      }

      try {
        $response = Gpt::getGptResponse($validatedPrompt, 300);
        
        // Log the raw LLM response for debugging
        if ($this->debug) {
          $this->logInfo("LLM response for query splitting", ['response' => substr($response, 0, 500)]);
        }

        if (preg_match('/\[.*\]/s', $response, $matches)) {
          $subQueries = json_decode($matches[0], true);
          
          // Log JSON parsing result
          if ($this->debug) {
            if (json_last_error() !== JSON_ERROR_NONE) {
              $this->logWarning("JSON parsing failed", [
                'error' => json_last_error_msg(),
                'json' => substr($matches[0], 0, 200)
              ]);
            } else {
              $this->logInfo("JSON parsed successfully", ['sub_query_count' => count($subQueries ?? [])]);
            }
          }

          if (is_array($subQueries) && !empty($subQueries)) {
            // Filter out short parts (<=5 chars)
            $subQueries = array_values(array_filter($subQueries, fn($sq) => strlen(trim($sq['query'])) > 5));
            
            // Add classification, confidence, priority, and dependency detection
            foreach ($subQueries as $index => &$subQuery) {
              // ✅ CRITICAL FIX (2026-01-02): Always use QueryClassifier for sub-query classification
              // The LLM splitting may return incorrect types (e.g., "semantic" instead of "web_search")
              // QueryClassifier applies WebSearchPostFilter which provides deterministic detection
              $classification = $this->classifier->classifyQueryType($subQuery['query']);
              
              // ✅ ALWAYS override type with QueryClassifier result (don't trust LLM type)
              $subQuery['type'] = $classification['type'];
              
              // Always set confidence score
              $subQuery['confidence'] = $classification['confidence'];
              
              // Always set priority
              if (!isset($subQuery['priority'])) {
                $subQuery['priority'] = $index + 1;
              }
              
              // Detect dependencies: if this is not the first query, check for dependency indicators (CENTRALIZED)
              if ($index > 0 && !isset($subQuery['depends_on'])) {
                // Dependencies are now handled by LLM during query splitting
                // Pattern-based dependency detection is disabled in Pure LLM mode
                if ($this->debug) {
                  $this->logInfo("Dependency detection handled by LLM during splitting");
                }
              }
            }

            if ($this->debug) $this->logInfo("Query split via LLM", ['count' => count($subQueries)]);
            return $subQueries;
          }
        } else {
          // Log when regex doesn't find JSON array
          if ($this->debug) {
            $this->logWarning("No JSON array found in LLM response", [
              'response_preview' => substr($response, 0, 200)
            ]);
          }
        }

        $this->logWarning("LLM response invalid JSON, using fallback");
        return $this->simpleSplit($query);

      } catch (\Exception $e) {
        $this->logWarning("LLM error, using fallback", ['error' => $e->getMessage()]);
        return $this->simpleSplit($query);
      }

    } catch (\Exception $e) {
      $this->logError("Critical error splitting query", $e, ['query' => $query]);
      return [['query' => $query, 'type' => 'semantic', 'confidence' => 0.3, 'error' => 'Query splitting failed']];
    }
  }

  /**
   * Split complex query into sub-queries
   *
   * @param string $query Original query
   * @return array Array of sub-queries with types and priorities
   */
  public function splitComplexQuery(string $query): array
  {
    try {
      if ($this->debug) $this->logInfo("Splitting complex query", ['query' => $query]);

      // Report/Analysis queries - using pattern from QuerySplitterPatterns
      if (preg_match(QuerySplitterPatterns::REPORT_QUERY_PATTERN, $query, $matches)) {
        return $this->splitReportQuery(trim($matches[3]), $query);
      }

      // Try splitting by various delimiters - using patterns from QuerySplitterPatterns
      $delimiters = QuerySplitterPatterns::DELIMITER_PATTERNS;
      
      foreach ($delimiters as $type => $delimiter) {
        $result = $this->splitByDelimiter($query, $delimiter, $type);
        if (!empty($result)) return $result;
      }

      // Default: single query
      if ($this->debug) $this->logInfo("Query not split, treating as single");
      $classification = $this->classifier->classifyQueryType($query);
      return [['query' => $query, 'type' => $classification['type'], 'confidence' => $classification['confidence'], 'priority' => 1, 'original_part' => $query]];

    } catch (\Exception $e) {
      $this->logError("Error in splitComplexQuery", $e);
      return [['query' => $query, 'type' => 'semantic', 'confidence' => 0.3, 'priority' => 1, 'original_part' => $query, 'error' => 'Query splitting failed']];
    }
  }

  /**
   * Split report query into analytics + semantic + optional web_search
   *
   * NOTE: Pure LLM mode - web search detection handled by LLM during classification
   */
  private function splitReportQuery(string $subject, string $originalQuery): array
  {
    $subQueries = [
      [
        'query' => str_replace('{subject}', $subject, $this->language->getDef('report_query_analytics_template')),
        'type' => 'analytics',
        'priority' => 1,
        'original_part' => $originalQuery
      ],
      [
        'query' => str_replace('{subject}', $subject, $this->language->getDef('report_query_semantic_template')),
        'type' => 'semantic',
        'priority' => 2,
        'original_part' => $originalQuery
      ]
    ];

    // LLM will determine if web search is needed during classification

    if ($this->debug) $this->logInfo("Split report query", ['subject' => $subject, 'count' => count($subQueries)]);
    return $subQueries;
  }

  /**
   * Split query by delimiter
   *
   * NOTE: Pure LLM mode - dependency detection handled by LLM during query analysis
   */
  private function splitByDelimiter(string $query, $delimiter, string $type): array
  {
    $parts = is_string($delimiter) && !preg_match('/^\//', $delimiter)
      ? explode($delimiter, $query)
      : preg_split($delimiter, $query, -1, PREG_SPLIT_NO_EMPTY);

    $parts = array_filter(array_map('trim', $parts), fn($p) => strlen($p) >= 3);

    if (count($parts) < 2) return [];

    // Special handling for "and" - only split if analytics + analytics
    if ($type === 'and') {
      $analyticsCount = count(array_filter($parts, fn($p) => $this->classifier->classifyQueryType($p)['type'] === 'analytics'));
      if ($analyticsCount < 2) return [];
    }

    // LLM handles dependencies during query analysis
    $hasDependency = false;

    $subQueries = [];
    foreach ($parts as $index => $part) {
      $classification = $this->classifier->classifyQueryType($part);
      $subQuery = [
        'query' => $type === 'question' ? $part . '?' : $part,
        'type' => $classification['type'],
        'confidence' => $classification['confidence'],
        'priority' => $index + 1,
        'original_part' => $part
      ];
      
      // Add dependency information if detected
      if ($hasDependency && $index > 0) {
        $subQuery['depends_on'] = $index; // Depends on previous sub-query (1-indexed)
        $subQuery['dependency_type'] = 'sequential'; // Sequential dependency
      }
      
      $subQueries[] = $subQuery;
    }

    if ($this->debug && !empty($subQueries)) {
      $depInfo = $hasDependency ? ' (with dependencies)' : '';
      $this->logInfo("Split by {$type}{$depInfo}", ['count' => count($subQueries)]);
    }
    return $subQueries;
  }

  /**
   * Detect if query contains multiple SQL queries (AND connector)
   *
   * NOTE: Pure LLM mode - multi-query detection is disabled
   * This method always returns false in pure LLM mode
   * 
   * @param string $query Query to analyze
   * @return array|false Always returns false (feature disabled)
   */
  public function detectMultipleSqlQueries(string $query): array|false
  {
    if ($this->debug) {
      $this->logInfo("Multi-query pattern detection disabled in Pure LLM mode, returning false");
    }
    
    return false;
  }

  /**
   * Simple fallback split on connectors
   *
   * @param string $query Query to split
   * @return array Array of sub-queries with types
   */
  public function simpleSplit(string $query): array
  {
    try {
      // Use pattern from QuerySplitterPatterns
      $parts = array_filter(
        array_map('trim', preg_split(QuerySplitterPatterns::SIMPLE_SPLIT_PATTERN, $query)),
        fn($p) => strlen($p) >= 3
      );
      
      $subQueries = [];
      foreach ($parts as $part) {
        $classification = $this->classifier->classifyQueryType($part);
        $subQueries[] = ['query' => $part, 'type' => $classification['type'], 'confidence' => $classification['confidence']];
      }

      if ($this->debug) $this->logInfo("Simple split", ['count' => count($subQueries)]);
      return $subQueries;

    } catch (\Exception $e) {
      $this->logError("Error in simple split", $e);
      return [['query' => $query, 'type' => 'semantic', 'confidence' => 0.3]];
    }
  }

  /**
   * Translate query to English with caching for performance
   *
   * This method caches translations to avoid repeated API calls for the same query.
   * Cache entries expire after 1 hour (TRANSLATION_CACHE_TTL).
   *
   * @param string $query Query to translate
   * @return string Translated query in English
   */
  private function translateToEnglishCached(string $query): string
  {
    // Generate cache key
    $cacheKey = md5($query);
    
    // Check if translation is cached and not expired
    if (isset(self::$translationCache[$cacheKey])) {
      $cached = self::$translationCache[$cacheKey];
      if (time() - $cached['timestamp'] < self::TRANSLATION_CACHE_TTL) {
        if ($this->debug) {
          $this->logInfo("Translation cache hit", ['query' => $query]);
        }
        return $cached['translation'];
      } else {
        // Cache expired, remove it
        unset(self::$translationCache[$cacheKey]);
      }
    }
    
    // Cache miss or expired, translate
    if ($this->debug) {
      $this->logInfo("Translation cache miss", ['query' => $query]);
    }
    
    $translation = SemanticAgent::translateToEnglish($query);
    
    // Store in cache
    self::$translationCache[$cacheKey] = [
      'translation' => $translation,
      'timestamp' => time(),
    ];
    
    // Clean old cache entries (keep max 100 entries)
    if (count(self::$translationCache) > 100) {
      // Remove oldest entries
      uasort(self::$translationCache, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
      self::$translationCache = array_slice(self::$translationCache, -100, 100, true);
    }
    
    return $translation;
  }

  /**
   * Validate prompt before LLM call
   */
  private function validatePrompt(string $prompt): string
  {
    try {
      if (empty(trim($prompt))) {
        return '';
      }

      // Modern LLMs support up to 128K tokens (~400K+ characters)
      // Use TechnicalConfig for max prompt length
      $maxLength = CLICSHOPPING_APP_CHATGPT_RA_MAX_PROMPT_LENGTH;
      
      if (strlen($prompt) > $maxLength) {
        if ($this->debug) {
          $this->logWarning("Prompt exceeds maximum length ({$maxLength} chars), truncating", [
            'original_length' => strlen($prompt),
            'truncated_length' => $maxLength
          ]);
        }
        $prompt = substr($prompt, 0, $maxLength);
      }
      
      // Security: Remove potentially dangerous HTML tags
      if (preg_match('/<script|<iframe/i', $prompt)) {
        $prompt = preg_replace('/<script.*?<\/script>|<iframe.*?<\/iframe>/is', '', $prompt);
      }

      return $prompt;
    } catch (\Exception $e) {
      $this->logError("Error validating prompt", $e);
      return $prompt;
    }
  }
  

  /**
   * Check if query is a price comparison query (Requirements 1, 5)
   *
   * Detects price comparison patterns in query and checks intent_type for 'price_comparison'.
   * Uses both pattern matching and intent analysis for robust detection.
   *
   * @param string $query Query to analyze
   * @param array $intent Intent analysis (optional)
   * @return bool True if price comparison query detected
   */
  private function isPriceComparisonQuery(string $query, array $intent = []): bool
  {
    try {
      // Check intent type first (most reliable)
      if (isset($intent['intent_type']) && $intent['intent_type'] === 'price_comparison') {
        if ($this->debug) {
          $this->logInfo("Price comparison detected via intent_type");
        }
        return true;
      }

      // Delegate pattern matching to QuerySplitterPatterns
      $isMatch = QuerySplitterPatterns::isPriceComparisonQuery($query);
      
      if ($isMatch && $this->debug) {
        $this->logInfo("Price comparison detected via pattern");
      }

      return $isMatch;
    } catch (\Exception $e) {
      $this->logError("Error in isPriceComparisonQuery", $e, ['query' => $query]);
      return false;
    }
  }

  /**
   * Decompose price comparison query into Analytics + WebSearch sub-queries (Requirements 1, 5)
   *
   * Creates two sub-queries:
   * 1. Analytics: "Get price for {product}" - retrieves internal product price from database
   * 2. WebSearch: "{product} price competitors" - retrieves competitor prices from web
   *
   * @param string $query Original query
   * @param array $intent Intent analysis
   * @return array Array of sub-queries with type and priority
   */
  private function decomposePriceComparisonQuery(string $query, array $intent): array
  {
    try {
      // Extract product name from query
      $productName = $this->extractProductName($query);

      if (empty($productName)) {
        if ($this->debug) {
          $this->logWarning("Could not extract product name, falling back to hybrid split");
        }
        return $this->splitHybridQuery($query, $intent);
      }

      if ($this->debug) {
        $this->logInfo("Decomposing price comparison query", [
          'original_query' => $query,
          'product_name' => $productName,
        ]);
      }

      // Create sub-queries
      $subQueries = [
        [
          'query' => "Get price for {$productName}",
          'type' => 'analytics',
          'confidence' => 0.95,
          'priority' => 1,
          'intent' => 'product_price_lookup',
          'original_query' => $query,
          'product_name' => $productName,
        ],
        [
          'query' => "{$productName} price competitors",
          'type' => 'web_search',
          'confidence' => 0.95,
          'priority' => 1, // Same priority for parallel execution
          'intent' => 'competitor_price_search',
          'original_query' => $query,
          'product_name' => $productName,
        ],
      ];

      if ($this->debug) {
        $this->logInfo("Price comparison query decomposed", [
          'sub_query_count' => count($subQueries),
          'analytics_query' => $subQueries[0]['query'],
          'websearch_query' => $subQueries[1]['query'],
        ]);
      }

      return $subQueries;

    } catch (\Exception $e) {
      $this->logError("Error in decomposePriceComparisonQuery", $e, ['query' => $query]);
      // Fallback to hybrid split
      return $this->splitHybridQuery($query, $intent);
    }
  }

  /**
   * Extract product name from price comparison query (Requirement 5)
   *
   * Removes common phrases like "give", "show", "price", "compare", etc.
   * and returns the cleaned product name.
   * 
   * Delegates to QuerySplitterPatterns for pattern-based extraction.
   *
   * @param string $query Query to extract product name from
   * @return string Cleaned product name
   */
  private function extractProductName(string $query): string
  {
    try {
      // Delegate to QuerySplitterPatterns for pattern-based extraction
      $productName = QuerySplitterPatterns::extractProductName($query);

      if ($this->debug) {
        $this->logInfo("Extracted product name", [
          'original_query' => $query,
          'product_name' => $productName,
        ]);
      }

      return $productName;
    } catch (\Exception $e) {
      $this->logError("Error in extractProductName", $e, ['query' => $query]);
      return $query; // Fallback to original query
    }
  }
  
}
