<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Context;

use ClicShopping\OM\Cache;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Memory\ConversationMemory;
use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\Config\DomainFields;

/**
 * ContextRetriever Class
 *
 * 🔧 PRIORITY 3 - PHASE 3.1: Lazy loading context retrieval
 *
 * Responsible for retrieving context only when needed to optimize performance.
 * Implements intelligent context loading based on query type and confidence.
 *
 * Key Features:
 * - Lazy loading: Skip context for high-confidence queries
 * - Caching: Cache context for 5 minutes to avoid repeated lookups
 * - Type-specific context: Load different context based on query type
 * - Performance optimization: Reduce latency by 30-50%
 *
 * Context Loading Strategy:
 * - High confidence (≥0.9): Skip context (classification is clear)
 * - Web search: Skip context (entities come from external sources)
 * - Simple analytics: Skip context (no entity context needed)
 * - Low confidence (<0.9): Load context to improve classification
 * - Semantic queries: Load semantic context (embeddings, similar queries)
 * - Analytics with filters: Load analytics context (entity data, aggregations)
 * - Hybrid queries: Load both semantic and analytics context
 *
 * @package ClicShopping\AI\CoreAI\Context
 */

class ContextRetriever
{
  private SecurityLogger $logger;
  private ?ConversationMemory $conversationMemory;
  private bool $debug;
  private bool $cacheEnabled = true;
  private int $cacheTTL = 5; // 5 minutes

  /**
   * Constructor
   *
   * @param ConversationMemory|null $conversationMemory For loading conversation context
   * @param bool $debug Enable debug logging
   */
  public function __construct(?ConversationMemory $conversationMemory = null, bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->conversationMemory = $conversationMemory;
    $this->debug = $debug;

    if ($this->debug) {
      $this->logger->logSecurityEvent("ContextRetriever initialized", 'info');
    }
  }

  /**
   * Retrieve context for query based on classification
   *
   * 🔧 PRIORITY 3 - PHASE 3.1: Main entry point for context retrieval
   *
   * This method determines if context is needed and loads appropriate context
   * based on query type. It implements caching to avoid repeated lookups.
   *
   * @param array $classification Classification result from IntentAnalyzer
   * @param string $query Original query
   * @param int $limit Maximum number of context items to retrieve
   * @return array Context data (empty array if context not needed)
   */
  public function retrieveContext(array $classification, string $query, int $limit = 3): array
  {
    $startTime = microtime(true);

    if ($this->debug) {
      error_log("--- CONTEXT RETRIEVAL START ---");
      error_log("Query: '{$query}'");
      error_log("Type: {$classification['type']}");
      error_log("Confidence: {$classification['confidence']}");
    }

    // 1. Check if context is needed
    if (!$this->needsContext($classification, $query)) {
      $duration = (microtime(true) - $startTime) * 1000;

      if ($this->debug) {
        error_log("[info]️ Context NOT needed - skipping retrieval");
        error_log("Duration: " . round($duration, 2) . " ms");
        error_log("--- CONTEXT RETRIEVAL END ---\n");
      }

      $this->logger->logStructured(
        'info',
        'ContextRetriever',
        'context_skipped',
        [
          'query' => $query,
          'type' => $classification['type'],
          'confidence' => $classification['confidence'],
          'reason' => $this->getSkipReason($classification, $query),
          'duration_ms' => round($duration, 2)
        ]
      );

      return [];
    }

    // 2. Check cache
    if ($this->cacheEnabled) {
      $cacheKey = $this->generateCacheKey($classification, $query, $limit);
      $cache = new Cache($cacheKey, 'Rag/Context');

      if ($cache->exists($this->cacheTTL)) {
        $cached = $cache->get();

        if ($cached !== null && is_array($cached)) {
          $duration = (microtime(true) - $startTime) * 1000;

          if ($this->debug) {
            error_log("✅ CACHE HIT - Returning cached context");
            error_log("Duration: " . round($duration, 2) . " ms");
            error_log("--- CONTEXT RETRIEVAL END ---\n");
          }

          $this->logger->logStructured(
            'info',
            'ContextRetriever',
            'context_cache_hit',
            [
              'query' => $query,
              'type' => $classification['type'],
              'cache_key' => $cacheKey,
              'duration_ms' => round($duration, 2)
            ]
          );

          return $cached;
        }
      }

      if ($this->debug) {
        error_log("[error] CACHE MISS - Loading fresh context");
      }
    }

    // 3. Load context based on type
    $context = $this->loadContextByType($classification, $query, $limit);

    $duration = (microtime(true) - $startTime) * 1000;

    if ($this->debug) {
      error_log("✅ Context loaded: " . count($context) . " items");
      error_log("Duration: " . round($duration, 2) . " ms");
      error_log("--- CONTEXT RETRIEVAL END ---\n");
    }

    $this->logger->logStructured(
      'info',
      'ContextRetriever',
      'context_loaded',
      [
        'query' => $query,
        'type' => $classification['type'],
        'context_items' => count($context),
        'duration_ms' => round($duration, 2)
      ]
    );

    // 4. Cache result
    if ($this->cacheEnabled && !empty($context)) {
      try {
        $cacheKey = $this->generateCacheKey($classification, $query, $limit);
        $cache = new Cache($cacheKey, 'Rag/Context');
        $cache->save($context);

        if ($this->debug) {
          error_log("✅ Context cached for {$this->cacheTTL} minutes");
        }
      } catch (\Exception $e) {
        $this->logger->logSecurityEvent(
          "ContextRetriever: Failed to cache context: " . $e->getMessage(),
          'warning'
        );
      }
    }

    return $context;
  }

  /**
   * Determine if context is needed for this query
   *
   * 🔧 PRIORITY 3 - PHASE 3.1: Core decision logic for lazy loading
   *
   * Context is expensive to load (embeddings, database queries), so we only
   * load it when it will actually improve the response quality.
   *
   * We SKIP context for:
   * - High confidence queries (≥0.9) - classification is very clear
   * - Web search queries - entities come from external sources
   * - Simple analytics queries - no entity context needed
   * - Generic queries - "help", "contact", etc.
   *
   * We LOAD context for:
   * - Low confidence queries (<0.9) - might need entity context
   * - Semantic queries - need similar documents/embeddings
   * - Analytics with filters - need entity data
   * - Hybrid queries - need both semantic and analytics context
   *
   * @param array $classification Classification result
   * @param string $query Original query
   * @return bool True if context is needed
   */
  private function needsContext(array $classification, string $query): bool
  {
    $type = $classification['type'] ?? 'unknown';
    $confidence = $classification['confidence'] ?? 0.5;
    $isHybrid = $classification['is_hybrid'] ?? false;

    // 1. Skip for very high confidence queries (≥0.9)
    // Classification is very clear, no need for additional context
    if ($confidence >= 0.9 && !$isHybrid) {
      return false;
    }

    // 2. Skip for web search queries
    // Entities and context come from external sources, not our database
    if ($type === 'web_search') {
      return false;
    }

    // 3. Skip for very short generic queries (1-2 words)
    $wordCount = str_word_count($query);
    if ($wordCount <= 2) {
      // Check if it's a generic query
      $genericPatterns = [
        '/\b(help|aide|contact|about|faq|support|info|information)\b/i',
        '/\b(hello|hi|bonjour|salut|hey)\b/i',
        '/\b(thanks|thank you|merci|bye|goodbye|au revoir)\b/i',
      ];

      foreach ($genericPatterns as $pattern) {
        if (preg_match($pattern, $query)) {
          return false;
        }
      }
    }

    // 4. Check for simple analytics queries without filters
    // These don't need entity context
    // 🔧 MIGRATION: Domain-agnostic implementation
    if ($type === 'analytics' && $confidence >= 0.8) {
      // Get active domain
      $domain = DomainConfig::getActivities();
      
      // Build entity list dynamically based on domain
      $entityList = '';
      $entityConfigClass = DomainFields::resolveAppClass($domain, 'EntityConfig');
      if ($entityConfigClass !== null) {
        try {
          if (method_exists($entityConfigClass, 'getEntityTypes')) {
            $entities = $entityConfigClass::getEntityTypes();
            if (!empty($entities)) {
              $entityList = implode('|', array_map('strtolower', $entities));
            }
          }
        } catch (\Exception $e) {
          // Fallback to generic pattern if EntityConfig fails
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              'Failed to load EntityConfig in ContextRetriever: ' . $e->getMessage(),
              'warning'
            );
          }
        }
      }
      
      // If no entity list available, use generic pattern
      if (empty($entityList)) {
        $entityList = 'items|records|entries|data';
      }
      
      // Simple analytics patterns that don't need context (with dynamic entity list)
      $simpleAnalyticsPatterns = [
        '/\b(count|total|sum|average|list|show)\s+(all|total)?\s*(' . $entityList . ')\b/i',
        '/\b(how many|combien)\s+(' . $entityList . ')\b/i',
      ];

      foreach ($simpleAnalyticsPatterns as $pattern) {
        if (preg_match($pattern, $query)) {
          return false;
        }
      }
    }

    // 5. Always load context for:
    // - Low confidence queries (<0.8) - might need entity context
    // - Semantic queries - need embeddings/similar documents
    // - Analytics with filters - need entity data
    // - Hybrid queries - need both types of context
    return true;
  }

  /**
   * Get reason why context was skipped (for logging)
   *
   * @param array $classification Classification result
   * @param string $query Original query
   * @return string Reason for skipping context
   */
  private function getSkipReason(array $classification, string $query): string
  {
    $type = $classification['type'] ?? 'unknown';
    $confidence = $classification['confidence'] ?? 0.5;
    $isHybrid = $classification['is_hybrid'] ?? false;

    if ($confidence >= 0.9 && !$isHybrid) {
      return "High confidence (≥0.9) - classification is clear";
    }

    if ($type === 'web_search') {
      return "Web search query - entities from external sources";
    }

    $wordCount = str_word_count($query);
    if ($wordCount <= 2) {
      return "Generic short query - no context needed";
    }

    if ($type === 'analytics' && $confidence >= 0.8) {
      return "Simple analytics query without filters";
    }

    return "Unknown reason";
  }

  /**
   * Generate cache key for context
   *
   * @param array $classification Classification result
   * @param string $query Original query
   * @param int $limit Maximum number of results
   * @return string Cache key
   */
  private function generateCacheKey(array $classification, string $query, int $limit): string
  {
    $type = $classification['type'] ?? 'unknown';
    $confidence = $classification['confidence'] ?? 0.5;

    // Generate key from query, type, confidence, and limit
    $keyData = [
      'query' => strtolower(trim($query)),
      'type' => $type,
      'confidence' => round($confidence, 2),
      'limit' => $limit,
    ];

    return md5(json_encode($keyData));
  }
  
  /**
   * Load context based on query type
   *
   * 🔧 PRIORITY 3 - PHASE 3.1: Type-specific context loading
   * 🔧 PRIORITY 3 - PHASE 4.2: Updated to support parallel execution
   *
   * Different query types need different context:
   * - Semantic: Similar documents, embeddings, conversation history
   * - Analytics: Entity data, aggregations, filters
   * - Hybrid: Both semantic and analytics context
   *
   * @param array $classification Classification result
   * @param string $query Original query
   * @param int $limit Maximum number of context items
   * @param bool $parallel Whether to use parallel execution (default: false for backward compatibility)
   * @return array Context data
   */
  private function loadContextByType(array $classification, string $query, int $limit, bool $parallel = false): array
  {
    $type = $classification['type'] ?? 'unknown';
    $translatedQuery = $classification['translated_query'] ?? $query;

    // If parallel execution is requested, use parallel loading
    if ($parallel) {
      return $this->loadContextParallel($classification, $translatedQuery, $limit);
    }

    // Otherwise, use sequential loading (original behavior)
    $context = match($type) {
      'semantic' => $this->loadSemanticContext($translatedQuery, $limit),
      'analytics' => $this->loadAnalyticsContext($translatedQuery, $classification, $limit),
      'hybrid' => $this->loadHybridContext($translatedQuery, $classification, $limit),
      default => []
    };

    return $context;
  }
  
  /**
   * Load context using parallel execution
   *
   * 🔧 PRIORITY 3 - PHASE 4.2: Parallel context loading
   *
   * Executes independent context loading operations in parallel to minimize latency.
   * Uses AsyncOperationManager to handle timeouts and graceful degradation.
   *
   * Operations executed in parallel:
   * 1. Embedding search (semantic context)
   * 2. Conversation memory load (recent interactions)
   * 3. Entity detection (analytics context)
   *
   * @param array $classification Classification result
   * @param string $query Query to analyze
   * @param int $limit Maximum number of results
   * @return array Combined context from all operations
   */
  private function loadContextParallel(array $classification, string $query, int $limit): array
  {
    try {
      if ($this->debug) {
        error_log("Loading context in parallel...");
      }

      $type = $classification['type'] ?? 'unknown';
      $asyncManager = new \ClicShopping\AI\Infrastructure\Async\AsyncOperationManager();

      // Define operations to execute in parallel
      $operations = [];

      // 1. Embedding search (for semantic and hybrid queries)
      if ($type === 'semantic' || $type === 'hybrid') {
        $operations['embeddings'] = function() use ($query, $limit) {
          return $this->loadEmbeddings($query, $limit);
        };
      }

      // 2. Conversation memory (for semantic and hybrid queries)
      if (($type === 'semantic' || $type === 'hybrid') && $this->conversationMemory) {
        $operations['memory'] = function() {
          return $this->loadConversationMemory(3);
        };
      }

      // 3. Entity context (for analytics and hybrid queries)
      if ($type === 'analytics' || $type === 'hybrid') {
        $operations['entities'] = function() use ($classification, $limit) {
          return $this->loadEntityContext($classification, $limit);
        };
      }

      // Execute all operations in parallel with 200ms timeout
      $results = $asyncManager->executeParallel($operations, 200);

      $completedOps = array_keys(array_filter($results, fn($r) => $r !== null));
      $failedOps = array_keys(array_filter($results, fn($r) => $r === null));
      $degradationOccurred = !empty($failedOps);

      // Aggregate results (graceful degradation: use whatever succeeded)
      $context = [];

      // Add embedding results
      if (!empty($results['embeddings'])) {
        $context = array_merge($context, $results['embeddings']);
      }

      // Add conversation memory results
      if (!empty($results['memory'])) {
        $context = array_merge($context, $results['memory']);
      }

      // Add entity results
      if (!empty($results['entities'])) {
        $context = array_merge($context, $results['entities']);
      }

      // Limit total context items
      $maxItems = $type === 'hybrid' ? $limit * 2 : $limit;
      $context = array_slice($context, 0, $maxItems);

      if ($degradationOccurred) {
        $this->logger->logStructured(
          'warning',
          'ContextRetriever',
          'parallel_context_degradation',
          [
            'query' => $query,
            'query_type' => $type,
            'operations_requested' => array_keys($operations),
            'operations_completed' => $completedOps,
            'operations_failed' => $failedOps,
            'context_items_loaded' => count($context),
            'degradation_impact' => $this->assessDegradationImpact($failedOps, $type),
          ]
        );
      }

      if ($this->debug) {
        error_log("Loaded " . count($context) . " context items in parallel");
        error_log("Operations completed: " . implode(', ', $completedOps));
        if ($degradationOccurred) {
          error_log("⚠️ DEGRADATION: Operations failed/timeout: " . implode(', ', $failedOps));
          error_log("Impact: " . $this->assessDegradationImpact($failedOps, $type));
        }
      }

      return $context;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error loading context in parallel: " . $e->getMessage(),
        'error'
      );

      // Graceful degradation: return empty context
      return [];
    }
  }
  
  /**
   * Load embeddings (extracted from loadSemanticContext for parallel execution)
   *
   * 🔧 PRIORITY 3 - PHASE 4.2: Extracted for parallel execution
   *
   * @param string $query Query to search for
   * @param int $limit Maximum number of results
   * @return array Embedding results
   */
  private function loadEmbeddings(string $query, int $limit): array
  {
    try {
      $context = [];
      $ragManager = new MultiDBRAGManager();
      $results = $ragManager->searchDocuments($query, $limit, 0.7);

      if (!empty($results['documents'])) {
        foreach ($results['documents'] as $doc) {
          $context[] = [
            'type' => 'embedding',
            'content' => $doc->content ?? '',
            'score' => $doc->metadata['score'] ?? 0,
            'entity_type' => $doc->metadata['entity_type'] ?? 'unknown',
            'entity_id' => $doc->metadata['entity_id'] ?? null,
          ];
        }
      }

      return $context;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error loading embeddings: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }
  
  /**
   * Load conversation memory (extracted from loadSemanticContext for parallel execution)
   *
   * 🔧 PRIORITY 3 - PHASE 4.2: Extracted for parallel execution
   *
   * @param int $limit Maximum number of interactions
   * @return array Conversation history
   */
  private function loadConversationMemory(int $limit): array
  {
    try {
      $context = [];

      if ($this->conversationMemory) {
        $recentInteractions = $this->conversationMemory->getRecentInteractions($limit);

        foreach ($recentInteractions as $interaction) {
          $context[] = [
            'type' => 'conversation',
            'query' => $interaction['query'] ?? '',
            'response' => $interaction['response'] ?? '',
            'timestamp' => $interaction['created_at'] ?? null,
          ];
        }
      }

      return $context;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error loading conversation memory: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }

  /**
   * Load entity context (extracted from loadAnalyticsContext for parallel execution)
   *
   * 🔧 PRIORITY 3 - PHASE 4.2: Extracted for parallel execution
   *
   * @param array $classification Classification result
   * @param int $limit Maximum number of entities
   * @return array Entity context
   */
  private function loadEntityContext(array $classification, int $limit): array
  {
    try {
      $context = [];

      // 1. Extract entities from metadata
      $entities = $classification['metadata']['entities'] ?? [];

      if (!empty($entities)) {
        foreach (array_slice($entities, 0, $limit) as $entity) {
          $context[] = [
            'type' => 'entity',
            'entity_type' => $entity['type'] ?? 'unknown',
            'entity_id' => $entity['id'] ?? null,
            'confidence' => $entity['confidence'] ?? 0,
            'method' => $entity['method'] ?? 'unknown',
          ];
        }
      }

      // 2. Load last entity from conversation memory (if available)
      if ($this->conversationMemory) {
        $lastEntity = $this->conversationMemory->getLastEntity();

        if ($lastEntity && $lastEntity['entity_id'] !== null) {
          $context[] = [
            'type' => 'last_entity',
            'entity_type' => $lastEntity['entity_type'] ?? 'unknown',
            'entity_id' => $lastEntity['entity_id'],
            'from_context' => true,
          ];
        }
      }

      return $context;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error loading entity context: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }

  /**
   * Assess the impact of degradation based on failed operations
   *
   *
   * Determines the severity and impact of failed operations on the final result.
   * This helps with monitoring and alerting for production issues.
   *
   * Impact Levels:
   * - "minimal": Failed operations are not critical for this query type
   * - "moderate": Failed operations reduce context quality but result is still usable
   * - "significant": Failed operations significantly impact result quality
   *
   * @param array $failedOps List of failed operation names
   * @param string $queryType Type of query (semantic, analytics, hybrid, web_search)
   * @return string Impact assessment (minimal, moderate, significant)
   */
  private function assessDegradationImpact(array $failedOps, string $queryType): string
  {
    if (empty($failedOps)) {
      return 'none';
    }

    // Assess impact based on query type and failed operations
    switch ($queryType) {
      case 'semantic':
        // For semantic queries, embeddings are critical
        if (in_array('embeddings', $failedOps, true)) {
          return 'significant'; // Embeddings are essential for semantic search
        }
        if (in_array('memory', $failedOps, true)) {
          return 'moderate'; // Memory helps but not critical
        }
        return 'minimal';

      case 'analytics':
        // For analytics queries, entities are critical
        if (in_array('entities', $failedOps, true)) {
          return 'significant'; // Entities are essential for analytics
        }
        if (in_array('memory', $failedOps, true)) {
          return 'minimal'; // Memory less important for analytics
        }
        return 'minimal';

      case 'hybrid':
        // For hybrid queries, all operations are important
        $criticalOps = ['embeddings', 'entities'];
        $failedCritical = array_intersect($criticalOps, $failedOps);

        if (count($failedCritical) >= 2) {
          return 'significant'; // Multiple critical operations failed
        }
        if (count($failedCritical) === 1) {
          return 'moderate'; // One critical operation failed
        }
        return 'minimal'; // Only non-critical operations failed

      case 'web_search':
        // For web search, context is less important (external sources)
        return 'minimal';

      default:
        // Unknown query type - assume moderate impact
        return 'moderate';
    }
  }

  /**
   * Load semantic context (embeddings, similar documents)
   *
   * @param string $query Query to search for
   * @param int $limit Maximum number of results
   * @return array Semantic context
   */
  private function loadSemanticContext(string $query, int $limit): array
  {
    try {
      if ($this->debug) {
        error_log("Loading semantic context...");
      }

      $context = [];

      // 1. Load similar documents from embeddings
      $ragManager = new MultiDBRAGManager();
      $results = $ragManager->searchDocuments($query, $limit, 0.7);

      if (!empty($results['documents'])) {
        foreach ($results['documents'] as $doc) {
          $context[] = [
            'type' => 'embedding',
            'content' => $doc->content ?? '',
            'score' => $doc->metadata['score'] ?? 0,
            'entity_type' => $doc->metadata['entity_type'] ?? 'unknown',
            'entity_id' => $doc->metadata['entity_id'] ?? null,
          ];
        }
      }

      // 2. Load recent conversation history (if available)
      if ($this->conversationMemory) {
        $recentInteractions = $this->conversationMemory->getRecentInteractions(3);

        foreach ($recentInteractions as $interaction) {
          $context[] = [
            'type' => 'conversation',
            'query' => $interaction['query'] ?? '',
            'response' => $interaction['response'] ?? '',
            'timestamp' => $interaction['created_at'] ?? null,
          ];
        }
      }

      if ($this->debug) {
        error_log("Loaded " . count($context) . " semantic context items");
      }

      return $context;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error loading semantic context: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }

  /**
   * Load analytics context (entity data, filters)
   *
   * @param string $query Query to analyze
   * @param array $classification Classification result
   * @param int $limit Maximum number of results
   * @return array Analytics context
   */
  private function loadAnalyticsContext(string $query, array $classification, int $limit): array
  {
    try {
      if ($this->debug) {
        error_log("Loading analytics context...");
      }

      $context = [];

      // 1. Extract entities from metadata
      $entities = $classification['metadata']['entities'] ?? [];

      if (!empty($entities)) {
        foreach (array_slice($entities, 0, $limit) as $entity) {
          $context[] = [
            'type' => 'entity',
            'entity_type' => $entity['type'] ?? 'unknown',
            'entity_id' => $entity['id'] ?? null,
            'confidence' => $entity['confidence'] ?? 0,
            'method' => $entity['method'] ?? 'unknown',
          ];
        }
      }

      // 2. Load last entity from conversation memory (if available)
      if ($this->conversationMemory) {
        $lastEntity = $this->conversationMemory->getLastEntity();

        if ($lastEntity && $lastEntity['entity_id'] !== null) {
          $context[] = [
            'type' => 'last_entity',
            'entity_type' => $lastEntity['entity_type'] ?? 'unknown',
            'entity_id' => $lastEntity['entity_id'],
            'from_context' => true,
          ];
        }
      }

      if ($this->debug) {
        error_log("Loaded " . count($context) . " analytics context items");
      }

      return $context;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error loading analytics context: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }

  /**
   * Load hybrid context (both semantic and analytics)
   *
   * @param string $query Query to analyze
   * @param array $classification Classification result
   * @param int $limit Maximum number of results
   * @return array Hybrid context
   */
  private function loadHybridContext(string $query, array $classification, int $limit): array
  {
    try {
      if ($this->debug) {
        error_log("Loading hybrid context...");
      }

      // Load both semantic and analytics context
      $semanticContext = $this->loadSemanticContext($query, $limit);
      $analyticsContext = $this->loadAnalyticsContext($query, $classification, $limit);

      // Merge contexts
      $context = array_merge($semanticContext, $analyticsContext);

      // Limit total context items
      $context = array_slice($context, 0, $limit * 2);

      if ($this->debug) {
        error_log("Loaded " . count($context) . " hybrid context items");
      }

      return $context;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error loading hybrid context: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }

  /**
   * Enable or disable caching
   *
   * @param bool $enabled Enable caching
   * @return void
   */
  public function setCacheEnabled(bool $enabled): void
  {
    $this->cacheEnabled = $enabled;
  }
  
  /**
   * Set cache TTL in minutes
   *
   * @param int $minutes Cache TTL in minutes
   * @return void
   */
  public function setCacheTTL(int $minutes): void
  {
    $this->cacheTTL = $minutes;
  }
  
  /**
   * Retrieve context using parallel execution
   *
   * 🔧 PRIORITY 3 - PHASE 4.2: Public method for parallel context retrieval
   *
   * This is a convenience method that enables parallel execution for context loading.
   * It's identical to retrieveContext() but forces parallel execution.
   *
   * @param array $classification Classification result from IntentAnalyzer
   * @param string $query Original query
   * @param int $limit Maximum number of context items to retrieve
   * @return array Context data (empty array if context not needed)
   */
  public function retrieveContextParallel(array $classification, string $query, int $limit = 3): array
  {
    $startTime = microtime(true);

    if ($this->debug) {
      error_log("\n--- PARALLEL CONTEXT RETRIEVAL START ---");
      error_log("Query: '{$query}'");
      error_log("Type: {$classification['type']}");
      error_log("Confidence: {$classification['confidence']}");
    }

    // 1. Check if context is needed
    if (!$this->needsContext($classification, $query)) {
      $duration = (microtime(true) - $startTime) * 1000;

      if ($this->debug) {
        error_log("[INFO] Context NOT needed - skipping retrieval");
        error_log("Duration: " . round($duration, 2) . " ms");
        error_log("--- PARALLEL CONTEXT RETRIEVAL END ---\n");
      }

      return [];
    }

    // 2. Check cache
    if ($this->cacheEnabled) {
      $cacheKey = $this->generateCacheKey($classification, $query, $limit);
      $cache = new Cache($cacheKey, 'Rag/Context');

      if ($cache->exists($this->cacheTTL)) {
        $cached = $cache->get();

        if ($cached !== null && is_array($cached)) {
          $duration = (microtime(true) - $startTime) * 1000;

          if ($this->debug) {
            error_log("✅ CACHE HIT - Returning cached context");
            error_log("Duration: " . round($duration, 2) . " ms");
            error_log("--- PARALLEL CONTEXT RETRIEVAL END ---\n");
          }

          return $cached;
        }
      }

      if ($this->debug) {
        error_log("[error] CACHE MISS - Loading fresh context in parallel");
      }
    }

    // 3. Load context in parallel
    $context = $this->loadContextByType($classification, $query, $limit, true);

    $duration = (microtime(true) - $startTime) * 1000;

    if ($this->debug) {
      error_log("✅ Context loaded in parallel: " . count($context) . " items");
      error_log("Duration: " . round($duration, 2) . " ms");
      error_log("--- PARALLEL CONTEXT RETRIEVAL END ---\n");
    }

    $this->logger->logStructured(
      'info',
      'ContextRetriever',
      'context_loaded_parallel',
      [
        'query' => $query,
        'type' => $classification['type'],
        'context_items' => count($context),
        'duration_ms' => round($duration, 2)
      ]
    );

    // 4. Cache result
    if ($this->cacheEnabled && !empty($context)) {
      try {
        $cacheKey = $this->generateCacheKey($classification, $query, $limit);
        $cache = new Cache($cacheKey, 'Rag/Context');
        $cache->save($context);

        if ($this->debug) {
          error_log("[INFO] Context cached for {$this->cacheTTL} minutes");
        }
      } catch (\Exception $e) {
        $this->logger->logSecurityEvent(
          "ContextRetriever: Failed to cache context: " . $e->getMessage(),
          'warning'
        );
      }
    }

    return $context;
  }
}
