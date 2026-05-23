<?php
/**
 * SemanticSearchOrchestrator
 * 
 * Orchestrates the semantic search fallback chain:
 * 1. Cache (optional, if enabled)
 * 2. Document Stores / RAG Knowledge Base (12+ vector stores) - PRIMARY SOURCE
 * 3. ConversationMemory (fallback for repeated queries)
 * 4. LLM Fallback (for general knowledge queries)
 * 5. Web Search Fallback (if enabled)
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Planning\SubPlanExecutor\SubSemanticExecutor;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\Infrastructure\Cache\Cache;
use ClicShopping\AI\Infrastructure\Cache\RagWarmupManager;
use ClicShopping\AI\Helper\InsufficientInformationDetector;
use ClicShopping\AI\Handler\Fallback\LLMFallbackHandler;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Config\DomainConfig;

/**
 * SemanticSearchOrchestrator Class
 *
 * Orchestrates semantic search with fallback chain to ensure users always get answers.
 * Implements the strategy pattern for different search sources.
 */

class SemanticSearchOrchestrator
{
  private SecurityLogger $logger;
  private bool $debug;
  private ?MultiDBRAGManager $ragManager = null;
  private ?LLMFallbackHandler $llmHandler = null;
  private ?Cache $cache = null;
  private ?RagWarmupManager $warmupManager = null;
  private string $userId;
  private int $languageId;
  private $language;
  private InsufficientInformationDetector $infoDetector;

  private string $prefix;
  
  /**
   * Constructor
   *
   * @param string $userId User ID
   * @param int $languageId Language ID
   * @param bool $debug Enable debug logging
   */
  public function __construct(string $userId = 'system', int $languageId = 1, bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->userId = $userId;
    $this->languageId = $languageId;
    $this->debug = $debug;

    // Load language definitions
    $this->language = Registry::get('Language');
    DomainConfig::loadLanguageFile('rag_semantic_search_orchestrator');
    
    // Initialize insufficient information detector
    $this->infoDetector = new InsufficientInformationDetector();

    // Initialize cache if enabled (Task 4.3.2)
    if ($this->shouldInitializeCache()) {
      $this->cache = new Cache(true);
      if ($this->debug) {
        $this->logger->logSecurityEvent("Cache initialized for semantic queries", 'info');
      }
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent("SemanticSearchOrchestrator initialized for user: {$userId}", 'info');
    }
  }

  /**
   * Execute semantic search with full fallback chain
   *
   * @param string $query User query
   * @param array $context Context information
   * @param array $options Search options (limit, minScore, etc.)
   * @return array Search result with metadata
   */
  public function search(string $query, array $context = [], array $options = []): array
  {
    $startTime = microtime(true);
    $fallbackChain = [];

    if ($this->debug) {
      $this->logger->logSecurityEvent("SemanticSearchOrchestrator: Starting search for query: \"{$query}\"", 'info');
    }

    try {
     // cold warm up
      if ($this->warmupManager === null) {
        $this->warmupManager = new RagWarmupManager($this->debug);
      }

      $this->warmupManager->warmupIfNeeded($query);

      $cacheStatus = 'disabled';
      if ($this->shouldUseCache($query, $context)) {
        $cacheStatus = 'checking';

        if ($this->debug) {
          $this->logger->logSecurityEvent("Step 1: Checking cache for query", 'info');
        }

        // Try to get cached response
        $cachedResponse = $this->cache->getCachedResponse($query);

        if ($cachedResponse !== null) {
          $executionTime = microtime(true) - $startTime;
          $cacheStatus = 'hit';

          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "✓ Cache HIT - Returning cached response (time: {$executionTime}s)",
              'info'
            );
          }

          // Decode cached response (it's stored as JSON string)
          $cachedData = json_decode($cachedResponse, true);
          if ($cachedData !== null && is_array($cachedData)) {
            // Add cache metadata
            $cachedData['metadata']['cache_status'] = 'hit';
            $cachedData['metadata']['execution_time'] = $executionTime;
            $cachedData['metadata']['from_cache'] = true;
            return $cachedData;
          }
        }

        $cacheStatus = 'miss';

        if ($this->debug) {
          $this->logger->logSecurityEvent("Cache MISS - Proceeding with search", 'info');
        }
      }

      // Step 2: Search Document Stores FIRST (RAG Knowledge Base)
      $fallbackChain[] = 'documents';
      if ($this->debug) {
        $this->logger->logSecurityEvent("Step 2: Searching Document Stores (RAG Knowledge Base)", 'info');
      }

      $documentResult = $this->searchDocumentStores($query, $options);
      if ($documentResult !== null && !empty($documentResult['documents'])) {
        $executionTime = microtime(true) - $startTime;
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Search completed - Source: documents, Results: " . count($documentResult['documents']) . ", Time: {$executionTime}s",
            'info'
          );
        }

        return $this->formatResult($documentResult, 'documents', $fallbackChain, $cacheStatus, $executionTime, $query);
      }

      // Step 3: Search ConversationMemory (FALLBACK if RAG has no results)
      $fallbackChain[] = 'conversation_memory';
      if ($this->debug) {
        $this->logger->logSecurityEvent("Step 3: Searching ConversationMemory (fallback)", 'info');
      }

      $conversationResult = $this->searchConversationMemory($query, $options);

      // Check if the documents have meaningful content (not just empty "Response: \n")
      $hasContent = false;
      if ($conversationResult !== null && !empty($conversationResult['documents'])) {
        // First, check if any document has actual content in the "Response:" section
        foreach ($conversationResult['documents'] as $doc) {
          $content = '';
          if (is_object($doc) && isset($doc->content)) {
            $content = $doc->content;
          } elseif (is_array($doc) && isset($doc['content'])) {
            $content = $doc['content'];
          }

          // Extract the response part after "Response: "
          if (preg_match('/Response:\s*(.+)/s', $content, $matches)) {
            $responseContent = trim($matches[1]);


            // 🔧 FIX 2025-12-28: Use InsufficientInformationDetector helper
            $isGenericResponse = $this->infoDetector->isInsufficientInformation($responseContent);

            // Check if response has actual content (not empty, not generic)
            if (!$isGenericResponse) {
              $hasContent = true;

              if ($this->debug) {
                $logMessage = $this->language->getDef('text_log_conversation_memory_useful');
                $logMessage = str_replace('{{length}}', strlen($responseContent), $logMessage);
                $this->logger->logSecurityEvent($logMessage, 'info');
              }
              break;
            } elseif ($isGenericResponse) {
              if ($this->debug) {
                $this->logger->logSecurityEvent(
                  "Conversation memory document has generic LLM response - skipping",
                  'info'
                );
              }
            }
          }
        }

        // If we found useful content, extract and use it directly
        if ($hasContent) {
          // Extract the response from the first document with useful content
          $extractedResponse = '';
          foreach ($conversationResult['documents'] as $doc) {
            $content = '';
            if (is_object($doc) && isset($doc->content)) {
              $content = $doc->content;
            } elseif (is_array($doc) && isset($doc['content'])) {
              $content = $doc['content'];
            }

            // Extract the response part after "Response: "
            if (preg_match('/Response:\s*(.+)/s', $content, $matches)) {
              $responseContent = trim($matches[1]);

              // Check if this is the useful response (not generic)
              // 🔧 FIX 2025-12-28: Use InsufficientInformationDetector helper
              $isGenericResponse = $this->infoDetector->isInsufficientInformation($responseContent);

              if (!$isGenericResponse) {
                $extractedResponse = $responseContent;

                if ($this->debug) {
                  $logMessage = $this->language->getDef('text_log_extracted_response');
                  $logMessage = str_replace('{{length}}', strlen($extractedResponse), $logMessage);
                  $this->logger->logSecurityEvent($logMessage, 'info');
                }
                break;
              }
            }
          }

          // Use the extracted response directly (don't regenerate)
          if (!empty($extractedResponse)) {
            $conversationResult['answer'] = $extractedResponse;
            $conversationResult['response'] = $extractedResponse;
            $conversationResult['text_response'] = $extractedResponse;

            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "Using extracted response from conversation memory directly",
                'info'
              );
            }
          } else {
            // No useful response found, don't use conversation memory
            $hasContent = false;

            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "Could not extract useful response from conversation memory",
                'warning'
              );
            }
          }
        } else {
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Conversation memory documents have no actual content (empty or generic responses)",
              'info'
            );
          }
        }
      }

      if ($hasContent) {
        $executionTime = microtime(true) - $startTime;
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Search completed - Source: conversation_memory, Results: " . count($conversationResult['documents']) . ", Time: {$executionTime}s",
            'info'
          );
        }

        return $this->formatResult($conversationResult, 'conversation_memory', $fallbackChain, $cacheStatus, $executionTime, $query);
      } else {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Conversation memory returned empty or no-content results - continuing to next fallback",
            'info'
          );
        }
      }

      // Step 4: LLM Fallback
      if ($this->isLLMFallbackEnabled()) {
        $fallbackChain[] = 'llm';
        if ($this->debug) {
          $this->logger->logSecurityEvent("Step 4: Falling back to LLM", 'info');
        }

        $llmResult = $this->fallbackToLLM($query, $context);
        if ($llmResult !== null) {
          $executionTime = microtime(true) - $startTime;
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Search completed - Source: llm, Time: {$executionTime}s",
              'info'
            );
          }

          return $this->formatResult($llmResult, 'llm', $fallbackChain, $cacheStatus, $executionTime, $query);
        }
      }

      // All fallbacks failed
      $executionTime = microtime(true) - $startTime;
      $this->logger->logSecurityEvent(
        "All fallback sources failed for query: \"{$query}\"",
        'error'
      );

      return $this->formatErrorResult($query, $fallbackChain, $executionTime);

    } catch (\Exception $e) {
      $executionTime = microtime(true) - $startTime;
      $this->logger->logSecurityEvent(
        "SemanticSearchOrchestrator error: " . $e->getMessage(),
        'error'
      );

      return $this->formatErrorResult($query, $fallbackChain, $executionTime, $e->getMessage());
    }
  }

  /**
   * Search conversation memory
   *
   * @param string $query Search query
   * @param array $options Search options
   * @return array|null Results or null if no matches
   */
  private function searchConversationMemory(string $query, array $options): ?array
  {
    try {
      // Initialize RAG manager if needed
      if ($this->ragManager === null) {
        $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
        $this->ragManager = new MultiDBRAGManager(null, [$this->prefix . 'rag_conversation_memory_embedding']);
      }

      $limit = $options['limit'] ?? 5;

      // Conversation memory should only match VERY similar queries (0.85+), not loosely related ones
      // This prevents "où est Paris" from matching "refund policy" (similarity 0.63)
      // Conversation memory is a FALLBACK, not primary source - it should only match near-exact repeats
      $configuredMinScore = CLICSHOPPING_APP_CHATGPT_RA_MEMORY_MIN_SCORE;
      $minScore = isset($options['minScore']) ? (float)$options['minScore'] : $configuredMinScore;

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Searching ConversationMemory - limit: {$limit}, minScore: {$minScore}",
          'info'
        );
      }

      // Search only conversation memory
      $results = $this->ragManager->searchDocuments(
        $query,
        $limit,
        $minScore,
        $this->languageId,
        null
      );

      if (!empty($results['documents'])) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "ConversationMemory search found " . count($results['documents']) . " results",
            'info'
          );
        }

        return $results;
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent("ConversationMemory search: no results", 'info');
      }

      return null;
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "ConversationMemory search error: " . $e->getMessage(),
        'error'
      );

      return null;
    }
  }

  /**
   * Search all document vector stores
   *
   * @param string $query Search query
   * @param array $options Search options
   * @return array|null Results or null if no matches
   */
  private function searchDocumentStores(string $query, array $options): ?array
  {
    try {
      // Initialize RAG manager with all document stores (excluding conversation_memory)
      if ($this->ragManager === null) {
        // Let MultiDBRAGManager auto-detect all embedding tables
        $this->ragManager = new MultiDBRAGManager();
      }

      $limit = $options['limit'] ?? 5;

      $configuredMinScore = CLICSHOPPING_APP_CHATGPT_RA_MIN_SIMILARITY_SCORE;
      $minScore = $options['minScore'] ?? $configuredMinScore;

      // Get document store names (all except conversation_memory)
      $documentStores = $this->ragManager->knownEmbeddingTable();

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Searching DocumentStores - stores: " . count($documentStores) . ", limit: {$limit}, minScore: {$minScore}",
          'info'
        );
      }

      // Translate query to English before embedding/search to ensure consistent processing
      try {
        $normalizedQuery = SemanticAgent::translateToEnglish($query, 120);
      } catch (\Throwable $e) {
        $normalizedQuery = $query;
      }

      // Search all document stores with normalized query
      $results = $this->ragManager->searchDocuments($normalizedQuery, $limit, $minScore, $this->languageId);

      if (!empty($results['documents'])) {
        if ($this->debug) {
          $storesSearched = $results['audit_metadata']['tables_searched'] ?? 0;
          $resultsCount = count($results['documents']);
          $this->logger->logSecurityEvent(
            "DocumentStores search - stores searched: {$storesSearched}, results found: {$resultsCount}",
            'info'
          );
        }

        // Generate an answer using the found documents (RAG synthesis)
        try {
          $answer = $this->ragManager->answerQuestion($normalizedQuery, $limit, $minScore, $this->languageId, null, ['structured' => true]);

          // Ensure consistent array structure
          if (is_string($answer)) {
            $answer = ['response' => $answer, 'audit_metadata' => $results['audit_metadata'] ?? []];
          } elseif (is_array($answer)) {
            // merge audit metadata from search phase if missing
            if (!isset($answer['audit_metadata']) && isset($results['audit_metadata'])) {
              $answer['audit_metadata'] = $results['audit_metadata'];
            }

            if (!isset($answer['answer']) && isset($answer['response'])) {
              $answer['answer'] = $answer['response'];
            } elseif (!isset($answer['response']) && isset($answer['answer'])) {
              $answer['response'] = $answer['answer'];
            }
          }

          // Add documents to answer for source attribution
          if (!isset($answer['documents'])) {
            $answer['documents'] = $results['documents'];
          }

          // If RAG found documents but they're not relevant to the query, return null to trigger LLM fallback
          $responseText = $answer['response'] ?? $answer['answer'] ?? '';
          $isGenericNoInfo = $this->infoDetector->isInsufficientInformation($responseText);

          if ($isGenericNoInfo) {
            if ($this->debug) {
              $logMessage = $this->language->getDef('text_log_rag_generic_response');
              $this->logger->logSecurityEvent($logMessage, 'info');
            }
            // Return null to trigger LLM fallback
            return null;
          }

          return $answer;
        } catch (\Throwable $e) {
          if ($this->debug) {
            $logMessage = $this->language->getDef('text_log_rag_synthesis_failed');
            $logMessage = str_replace('{{error}}', $e->getMessage(), $logMessage);
            $this->logger->logSecurityEvent($logMessage, 'warning');
          }
          // If synthesis fails, return raw results to be formatted upstream
          return $results;
        }
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent("DocumentStores search: no results", 'info');
      }

      return null;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "DocumentStores search error: " . $e->getMessage(),
        'error'
      );
      return null;
    }
  }


  /**
   * Fallback to LLM
   *
   * @param string $query User query
   * @param array $context Context information
   * @return array|null LLM response or null if failed
   */
  private function fallbackToLLM(string $query, array $context): ?array
  {
    try {
      // Initialize LLM handler if needed
      if ($this->llmHandler === null) {
        $this->llmHandler = new LLMFallbackHandler($this->userId, $this->languageId, $this->debug);
      }

      // Query LLM
      $result = $this->llmHandler->queryLLM($query, $context);

      if ($result['success'] ?? false) {
        return $result;
      }

      return null;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "LLM fallback error: " . $e->getMessage(),
        'error'
      );
      return null;
    }
  }


  /**
   * Determine if cache should be initialized
   *
   * @return bool True if cache should be initialized
   */
  private function shouldInitializeCache(): bool
  {
    // Check if cache is globally enabled
    if (!defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER') || CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER !== 'True') {
      return false;
    }

    // Check if semantic caching is enabled
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_SEMANTIC_QUERIES') && CLICSHOPPING_APP_CHATGPT_RA_CACHE_SEMANTIC_QUERIES === 'True') {
      return true;
    }

    return false;
  }

  /**
   * Determine if cache should be used
   *
   * @param string $query User query
   * @param array $context Context information
   * @return bool True if cache should be used
   */
  private function shouldUseCache(string $query, array $context): bool
  {
    // Check if cache is globally enabled
    if (!defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER')  || CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER !== 'True') {
      return false;
    }

    // Check if semantic caching is enabled (default: False)
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_SEMANTIC_QUERIES') && CLICSHOPPING_APP_CHATGPT_RA_CACHE_SEMANTIC_QUERIES === 'True') {
      if ($this->debug) {
        $this->logger->logSecurityEvent("Cache enabled for semantic queries", 'info');
      }
      return true;
    }

    // By default, don't cache semantic queries (they need fresh information)
    if ($this->debug) {
      $this->logger->logSecurityEvent("Cache bypassed for semantic query (default behavior)", 'info');
    }

    return false;
  }

  /**
   * Check if LLM fallback is enabled
   *
   * @return bool True if enabled
   */
  private function isLLMFallbackEnabled(): bool
  {
    return CLICSHOPPING_APP_CHATGPT_RA_ENABLE_LLM_FALLBACK;
  }


  /**
   * Format successful result
   *
   * @param array $result Raw result
   * @param string $source Result source
   * @param array $fallbackChain Fallback chain attempted
   * @param string $cacheStatus Cache status
   * @param float $executionTime Execution time
   * @param string $query Original query (for caching)
   * @return array Formatted result
   */
  private function formatResult(array $result, string $source, array $fallbackChain, string $cacheStatus, float $executionTime, string $query = ''): array
  {
    // Preserve audit_metadata and add priority_table if present
    $auditMetadata = $result['audit_metadata'] ?? [];

    // Ensure priority_table is set (use from result or default to source-based value)
    if (!isset($auditMetadata['priority_table'])) {
      if ($source === 'conversation_memory') {
        $auditMetadata['priority_table'] = CLICSHOPPING::getConfig('db_prefix') . '_rag_conversation_memory_embedding';
      } elseif ($source === 'documents' && isset($result['audit_metadata']['priority_table'])) {
        $auditMetadata['priority_table'] = $result['audit_metadata']['priority_table'];
      }
    }

    $answer = $result['answer'] ?? $result['response'] ?? $result['text_response'] ?? '';

    $formattedResult = [
      'success' => true,
      'type' => 'semantic',
      'source' => $source, //Keep 'answer' for backward compatibility
      'response' => $answer,  // Add 'response' for consistency
      'text_response' => $answer,  // Keep 'text_response' for backward compatibility
      'documents' => $result['documents'] ?? [],  //Keep 'documents' for hallucination detection
      'results' => $result['documents'] ?? [],
      'sources' => $result['documents'] ?? [],  // Add 'sources' alias
      'metadata' => [
        'fallback_chain' => $fallbackChain,
        'cache_status' => $cacheStatus,
        'execution_time' => $executionTime,
        'vector_stores_searched' => $auditMetadata['tables_searched'] ?? 0,
        'vector_stores_with_results' => $auditMetadata['vector_stores_with_results'] ?? [],
      ],
      'audit_metadata' => $auditMetadata
    ];

    if ($this->cache !== null && $cacheStatus !== 'hit' && $cacheStatus !== 'disabled' && !empty($query)) {
      try {
        // Cache the formatted result as JSON
        // Use global constant (loaded from TechnicalConfig in config_clicshopping.php)
        $cacheTTL = defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_TTL') ? (int)CLICSHOPPING_APP_CHATGPT_RA_CACHE_TTL: 2592000;

        $this->cache->cacheResponse($query, json_encode($formattedResult), $cacheTTL);

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "✓ Cached semantic query result (query: \"{$query}\", TTL: {$cacheTTL}s)",
            'info'
          );
        }
      } catch (\Exception $e) {
        // Don't fail if caching fails, just log it
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Failed to cache result: " . $e->getMessage(),
            'warning'
          );
        }
      }
    }

    return $formattedResult;
  }

  /**
   * Format error result
   *
   * @param string $query Original query
   * @param array $fallbackChain Fallback chain attempted
   * @param float $executionTime Execution time
   * @param string|null $errorMessage Error message
   * @return array Formatted error result
   */
  private function formatErrorResult(string $query, array $fallbackChain, float $executionTime, ?string $errorMessage = null): array
  {
    return [
      'success' => false,
      'type' => 'semantic',
      'error' => $errorMessage ?? 'All search sources failed',
      'text_response' => 'I apologize, but I could not find an answer to your question.',
      'metadata' => [
        'query' => $query,
        'fallback_chain_attempted' => $fallbackChain,
        'execution_time' => $executionTime,
        'error' => $errorMessage
      ]
    ];
  }
}
