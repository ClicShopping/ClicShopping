<?php
/**
 * ComplexQueryHandler - Complex hybrid query handler
 * 
 * This class handles detection, decomposition and orchestration
 * of complex queries that combine multiple types of analysis:
 * - Multiple queries (AND, THEN, ALSO)
 * - Hybrid queries (analytics + semantic + web)
 * - Queries with competitive analysis
 * 
 * IMPORTANT: This class expects queries to be PRE-TRANSLATED to English.
 * All patterns and keywords are in English only, as translation is handled
 * upstream by SemanticAgent::translateToEnglish() before reaching this class.
 * 
 * @package AI\Tools\Query
 * @author Kiro AI Assistant
 * @version 1.0.0
 */

namespace ClicShopping\AI\Handler\Query;


use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Config\DomainConfig;


class ComplexQueryHandler
{
    private $securityLogger;
    private $debug;
    
    /**
     * Required weight keys for complexity calculation
     * Used for validation to prevent undefined array key errors
     */
    private const REQUIRED_WEIGHT_KEYS = [
        'multiple_connectors',
        'strong_connector',
        'hybrid_pattern',
        'web_search',
        'contextual_dependency',
        'minimum_threshold'
    ];
    
    public function __construct(bool $debug = false)
    {
        try {
            $this->securityLogger = new SecurityLogger();
        } catch (\Exception $e) {
            // SecurityLogger not available, continue without logging
            $this->securityLogger = null;
        }
        $this->debug = $debug;
    }
    
    /**
     * Detects if a query is complex and determines its type
     * 
     * This method analyzes a user query to determine if it requires complex processing
     * (decomposition into sub-queries) or can be handled as a simple query. The detection
     * is based on a weighted scoring system that evaluates multiple complexity factors.
     * 
     * Complexity Scoring System:
     * - Each detected pattern contributes to a complexity score (0-100)
     * - Scores are calculated by summing weighted factors
     * - A query is classified as complex only if score >= minimum_threshold
     * - This prevents false positives from single weak indicators
     * 
     * @param string $query User query (pre-translated to English)
     * @return array [
     *   'is_complex' => bool,              // Whether query requires complex processing
     *   'query_type' => string,            // 'simple', 'multiple', 'hybrid', or 'multiple_hybrid'
     *   'complexity_score' => int,         // Calculated score (0-100)
     *   'detected_patterns' => array,      // List of detected complexity patterns
     *   'requires_web_search' => bool,     // Whether query needs external web search
     *   'estimated_sub_queries' => int     // Estimated number of sub-queries if decomposed
     * ]
     */
    public function detectComplexQuery(string $query): array
    {
        $query_lower = mb_strtolower($query);
        
        // Initialize result
        $result = [
            'is_complex' => false,
            'query_type' => 'simple',
            'complexity_score' => 0,
            'detected_patterns' => [],
            'requires_web_search' => false,
            'estimated_sub_queries' => 1
        ];
        
        /**
         * Complexity Weights Configuration
         * 
         * These weights determine how much each complexity factor contributes to the
         * overall complexity score. The scoring system is designed to identify queries
         * that genuinely require decomposition while avoiding false positives.
         * 
         * Weight Definitions:
         * - multiple_connectors (0.3): Applied per connector when 2+ connectors detected
         *   Examples: "AND", "THEN", "ALSO" - indicates multiple distinct sub-queries
         * 
         * - strong_connector (0.5): Applied when exactly 1 strong connector detected
         *   A single connector strongly suggests the query should be split into 2 parts
         * 
         * - hybrid_pattern (0.4): Applied per hybrid pattern detected
         *   Examples: combining analytics + semantic, or requiring multiple data sources
         * 
         * - web_search (0.2): Applied when query requires external web search
         *   Adds complexity but not as significant as query decomposition needs
         * 
         * - contextual_dependency (0.3): Applied when query depends on previous context
         *   Indicates the query cannot be processed in isolation
         * 
         * - minimum_threshold (0.4): Minimum score required to classify as complex
         *   Purpose: Prevents false positives from single weak indicators
         *   Rationale: 
         *     - Single strong connector (0.5) → complex ✓
         *     - Multiple weak factors (0.3 + 0.2 = 0.5) → complex ✓
         *     - Single weak factor (0.3) → simple ✓
         *   This threshold ensures only queries with substantial complexity are decomposed,
         *   avoiding unnecessary overhead for simple queries.
         */
        $weights = [
          'multiple_connectors' => 0.3,    // Weight for multiple query connectors (2+ connectors)
          'strong_connector' => 0.5,        // Weight for single strong connector (exactly 1 connector)
          'hybrid_pattern' => 0.4,          // Weight for hybrid query patterns (multi-source queries)
          'web_search' => 0.2,              // Weight for web search requirement (external data needed)
          'contextual_dependency' => 0.3,   // Weight for contextual dependencies (requires previous context)
          'minimum_threshold' => 0.4        // Minimum score to classify as complex (prevents false positives)
        ];
        
        // DEFENSIVE: Validate weights array has all required keys
        $this->validateWeights($weights);
        
        // 1. Detect connectors (multiple queries)
        $connector_count = $this->detectConnectors($query_lower);

        if ($this->debug && $this->securityLogger) {
            $this->securityLogger->logSecurityEvent(
                "PRIORITY 2: Connector detection - count: {$connector_count}",
                'info'
            );
        }

        // PRIORITY 2 FIX: Only mark as complex if we have strong evidence
        if ($connector_count >= 2) {
            // Multiple connectors - definitely complex
            $result['is_complex'] = true;
            $result['query_type'] = 'multiple';
            $result['detected_patterns'][] = 'multiple_queries';
            $result['estimated_sub_queries'] = $connector_count + 1;
            $result['complexity_score'] += $weights['multiple_connectors'] * $connector_count;
        } elseif ($connector_count == 1) {
            // Single strong connector - likely complex
            $result['is_complex'] = true;
            $result['query_type'] = 'multiple';
            $result['detected_patterns'][] = 'multiple_queries';
            $result['estimated_sub_queries'] = 2;
            $result['complexity_score'] += $weights['strong_connector'];
        }
        
        // 2. Detect hybrid patterns
        $hybrid_patterns = $this->detectHybridPatterns($query_lower);

        if (!empty($hybrid_patterns)) {
            $result['is_complex'] = true;
            $result['query_type'] = $result['query_type'] === 'multiple' ? 'multiple_hybrid' : 'hybrid';
            $result['detected_patterns'] = array_merge($result['detected_patterns'], $hybrid_patterns);
            $result['complexity_score'] += $weights['hybrid_pattern'] * count($hybrid_patterns);
        }

        // 3. Detect web search requirement
        if ($this->requiresWebSearch($query_lower)) {
            $result['requires_web_search'] = true;
            $result['detected_patterns'][] = 'web_search_required';
            $result['complexity_score'] += $weights['web_search'];
        }

        // 4. Detect contextual dependencies
        if ($this->hasContextualDependencies($query_lower)) {
            $result['detected_patterns'][] = 'contextual_dependencies';
            $result['complexity_score'] += $weights['contextual_dependency'];
        }
        
        // Cap score at 100
        $result['complexity_score'] = min(100, $result['complexity_score']);
        
        // PRIORITY 2 FIX: Apply minimum complexity threshold
        // Only mark as complex if score >= threshold (prevents false positives)
        // DEFENSIVE: Use null coalescing operator as extra safety
        if ($result['complexity_score'] < ($weights['minimum_threshold'] ?? 0.4)) {
            $result['is_complex'] = false;
            $result['query_type'] = 'simple';
            
            if ($this->debug && $this->securityLogger) {
                $this->securityLogger->logSecurityEvent(
                    "PRIORITY 2: Query below complexity threshold (score: {$result['complexity_score']}), treating as simple",
                    'info'
                );
            }
        }
        
        // Log detection
        if ($this->debug && $this->securityLogger) {
            $this->securityLogger->logSecurityEvent(
                "Complex query detection complete",
                'info',
                [
                    'query' => $query,
                    'is_complex' => $result['is_complex'],
                    'score' => $result['complexity_score'],
                    'type' => $result['query_type']
                ]
            );
        }
        
        return $result;
    }
    
    /**
     * Validates that the weights array contains all required keys
     * 
     * DEFENSIVE ENHANCEMENT: Prevents undefined array key errors by validating
     * the weights array structure before use. Logs warnings for missing keys
     * but does not throw exceptions to maintain backward compatibility.
     * 
     * @param array $weights Weights array to validate
     * @return void
     */
    private function validateWeights(array $weights): void
    {
        $missing_keys = [];
        
        foreach (self::REQUIRED_WEIGHT_KEYS as $key) {
            if (!isset($weights[$key])) {
                $missing_keys[] = $key;
            }
        }
        
        // Log warning if keys are missing (defensive logging)
        if (!empty($missing_keys) && $this->securityLogger) {
            $this->securityLogger->logSecurityEvent(
                "DEFENSIVE WARNING: Missing weight keys in ComplexQueryHandler",
                'warning',
                [
                    'missing_keys' => $missing_keys,
                    'note' => 'Using default values via null coalescing operator'
                ]
            );
        }
    }
    
    /**
     * Decomposes a complex query into sub-queries
     * 
     * @param string $query Complex query
     * @param array $detection_result Result from detectComplexQuery()
     * @return array List of sub-queries with their metadata
     */
    public function decomposeComplexQuery(string $query, array $detection_result): array
    {
        // If query is not complex, return as-is
        if (!$detection_result['is_complex']) {
            return [[
                'type' => 'simple',
                'query' => $query,
                'context' => [],
                'depends_on' => [],
                'priority' => 1
            ]];
        }
        
        // Use GPT to decompose intelligently
        $decomposition_prompt = $this->buildDecompositionPrompt($query, $detection_result);
        
        try {
            $gpt_response = Gpt::getGptResponse($decomposition_prompt, 2000, 0.3);
;
            // Parse GPT response
            $sub_queries = $this->parseGptDecomposition($gpt_response, $query);
            
            // Validate and enrich sub-queries
            $sub_queries = $this->validateAndEnrichSubQueries($sub_queries, $detection_result);
            
            // Log decomposition
            if ($this->debug && $this->securityLogger) {
                $this->securityLogger->logSecurityEvent(
                    "Query decomposed",
                    'info',
                    [
                        'original_query' => $query,
                        'sub_queries_count' => count($sub_queries),
                        'sub_queries' => $sub_queries
                    ]
                );
            }
            
            return $sub_queries;
            
        } catch (\Exception $e) {
            // Fallback: basic decomposition by connectors
            if ($this->securityLogger) {
                $this->securityLogger->logSecurityEvent(
                    "GPT decomposition failed, using fallback",
                    'warning',
                    ['error' => $e->getMessage()]
                );
            }
            
            return $this->fallbackDecomposition($query, $detection_result);
        }
    }
    
    /**
     * Detects connectors in the query
     * 
     * Pattern detection disabled in Pure LLM mode.
     * Returns 0 (no connectors detected).
     * 
     * @param string $query_lower Query in lowercase
     * @return int Number of connectors found
     */
    private function detectConnectors(string $query_lower): int
    {
        // Pattern detection disabled - return 0
        return 0;
    }
    
    /**
     * Detects hybrid patterns in the query
     * 
     * Pattern detection disabled in Pure LLM mode.
     * Returns empty array (no patterns detected).
     * 
     * @param string $query_lower Query in lowercase
     * @return array List of detected patterns
     */
    private function detectHybridPatterns(string $query_lower): array
    {
        // Pattern detection disabled - return empty array
        return [];
    }
    
    /**
     * Determines if the query requires web search
     * 
     * Pattern detection disabled in Pure LLM mode.
     * Returns false (no web search required).
     * 
     * @param string $query_lower Query in lowercase
     * @return bool
     */
    private function requiresWebSearch(string $query_lower): bool
    {
        // Pattern detection disabled - return false
        return false;
    }
    
    /**
     * Detects contextual dependencies
     * 
     * Pattern detection disabled in Pure LLM mode.
     * Returns false (no dependencies detected).
     * 
     * @param string $query_lower Query in lowercase
     * @return bool
     */
    private function hasContextualDependencies(string $query_lower): bool
    {
        // Pattern detection disabled - return false
        return false;
    }
  /**
     * Builds the decomposition prompt for GPT
     * 
     * @param string $query Original query
     * @param array $detection_result Detection result
     * @return string Prompt for GPT
     */
    private function buildDecompositionPrompt(string $query, array $detection_result): string
    {
        // Load language file in English for internal processing
        $CLICSHOPPING_Language = Registry::get('Language');
        DomainConfig::loadAgnosticLanguageFile('rag_decomposition_prompt');
        
        // Get prompt template with placeholders
        $prompt = $CLICSHOPPING_Language->getDef('text_rag_decomposition_prompt', [
            'query' => $query,
            'query_type' => $detection_result['query_type'],
            'detected_patterns' => implode(', ', $detection_result['detected_patterns'])
        ]);
        
        return $prompt;
    }
    
    /**
     * Parses GPT decomposition response
     * 
     * @param string $gpt_response GPT response
     * @param string $original_query Original query (for fallback)
     * @return array List of sub-queries
     */
    private function parseGptDecomposition(string $gpt_response, string $original_query): array
    {
        // Clean response (remove markdown code blocks if present)
        $gpt_response = preg_replace('/```json\s*|\s*```/', '', $gpt_response);
        $gpt_response = trim($gpt_response);
        
        // Parse JSON
        $sub_queries = json_decode($gpt_response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to parse GPT response as JSON: " . json_last_error_msg());
        }
        
        if (!is_array($sub_queries) || empty($sub_queries)) {
            throw new \Exception("GPT response is not a valid array of sub-queries");
        }
        
        return $sub_queries;
    }
    
    /**
     * Validates and enriches sub-queries
     * 
     * @param array $sub_queries Parsed sub-queries
     * @param array $detection_result Detection result
     * @return array Validated and enriched sub-queries
     */
    private function validateAndEnrichSubQueries(array $sub_queries, array $detection_result): array
    {
        $validated = [];
        
        foreach ($sub_queries as $index => $sub_query) {
            // Validate required fields
            if (!isset($sub_query['type']) || !isset($sub_query['query'])) {
                continue; // Skip invalid sub-queries
            }
            
            // Enrich with default values
            $validated[] = [
                'type' => $sub_query['type'],
                'query' => $sub_query['query'],
                'context' => $sub_query['context'] ?? [],
                'depends_on' => $sub_query['depends_on'] ?? [],
                'priority' => $sub_query['priority'] ?? ($index + 1),
                'requires_web_search' => $sub_query['type'] === 'web_search' || $detection_result['requires_web_search']
            ];
        }
        
        return $validated;
    }
    
    /**
     * Basic fallback decomposition (without GPT)
     * 
     * @param string $query Original query
     * @param array $detection_result Detection result
     * @return array List of sub-queries
     */
    private function fallbackDecomposition(string $query, array $detection_result): array
    {
        // Simple decomposition by connectors
        $parts = preg_split('/\s+(' . implode('|', self::CONNECTORS) . ')\s+/i', $query);
        
        $sub_queries = [];
        foreach ($parts as $index => $part) {
            $part = trim($part);
            if (empty($part)) continue;
            
            $sub_queries[] = [
                'type' => $this->guessQueryType($part, $detection_result),
                'query' => $part,
                'context' => [],
                'depends_on' => $index > 0 ? [$index - 1] : [],
                'priority' => $index + 1,
                'requires_web_search' => $detection_result['requires_web_search']
            ];
        }
        
        return $sub_queries;
    }
    
    /**
     * Guesses query type (fallback)
     * 
     * @param string $query_part Query part
     * @param array $detection_result Detection result
     * @return string Query type
     */
  private function guessQueryType(string $query_part, array $detection_result): string
  {
    $query_lower = mb_strtolower($query_part);

    // Web search for competitor queries
    if (!empty($detection_result['requires_web_search'])) {
      foreach (self::HYBRID_PATTERNS['competitor'] as $keyword) {
        if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $query_lower)) {
          return 'web_search';
        }
      }
    }

    // Analytics patterns
    $analytics_pattern = '/\b(how many|number|total|average|sum|statistics|sales|price|count|revenue|profit|cost|quantity|amount)\b/i';
    if (preg_match($analytics_pattern, $query_lower)) {
      return 'analytics';
    }

    // Default fallback
    return 'semantic';
  }


  /***********************
   * Not used
   ************************/

  /**
     * Estimates execution time for a complex query
     * 
     * @param array $sub_queries List of sub-queries
     * @return float Estimated time in seconds
     */
    public function estimateExecutionTime(array $sub_queries): float
    {
        $total_time = 0;
        
        foreach ($sub_queries as $sub_query) {
            switch ($sub_query['type']) {
                case 'analytics':
                    $total_time += 2.0; // 2 seconds for analytics
                    break;
                case 'semantic':
                    $total_time += 1.5; // 1.5 seconds for semantic
                    break;
                case 'web_search':
                    $total_time += 5.0; // 5 seconds for web search
                    break;
            }
        }
        
        // Add overhead for orchestration
        $total_time += 1.0;
        
        return $total_time;
    }
}
