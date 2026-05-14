<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\DomainsAI\Semantic\Executor;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Cache;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\Config\DomainFields;
use ClicShopping\AI\DomainsAI\CoreAI\Helper\AgentResponseHelper;
use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Memory\ConversationMemory;
use ClicShopping\AI\InterfacesAI\SemanticConfigInterface;

/**
 * SemanticQueryExecutor Class
 *
 * Responsibility: Execute semantic search queries using embedding database.
 * This class is focused solely on semantic query execution and does not handle
 * other query types or orchestration logic.
 *
 * Extracted from HybridQueryProcessor as part of refactoring (Task 2.11.2)
 */
class SemanticQueryExecutor
{
  private SecurityLogger $logger;
  private bool $debug;
  private ?ConversationMemory $conversationMemory;
  private $language;
  private string $languageCode;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   * @param ConversationMemory|null $conversationMemory Optional conversation memory for context
   */
  public function __construct(bool $debug = false, ?ConversationMemory $conversationMemory = null)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
    $this->conversationMemory = $conversationMemory;
    
    // Initialize language
    $this->language = Registry::get('Language');
    $this->languageCode = $this->language->get('code');
    
    DomainConfig::loadLanguageFile('rag_semantic_query_executor');
  }

  /**
   * Execute a semantic search query using LLPhant QuestionAnswering (RAG pattern)
   *
   * This method uses MultiDBRAGManager's answerQuestion() which implements the proper
   * RAG pattern with LLPhant QuestionAnswering. This ensures accurate answers from embeddings
   * without LLM hallucination.
   *
   * Flow:
   * 1. Search for relevant documents in embedding database
   * 2. Use LLPhant QuestionAnswering to generate answer from documents
   * 3. Fallback to direct LLM only if no documents found
   *
   * All operations are wrapped in try-catch to prevent server 500 errors.
   *
   * @param string $query Semantic query
   * @param array $context Context information (may include entity_id, interaction_id for memory)
   * @return array Result with structured embedding data and generated answer
   */
  public function execute(string $query, array $context = []): array
  {
    $startTime = microtime(true);
    
    try {
      // Extract context parameters for cache key
      $languageId = $context['language_id'] ?? null;
      $entityType = $context['entity_type'] ?? null;
      
      // Get language ID from Registry if not provided
      if ($languageId === null && Registry::exists('Language')) {
        $languageId = Registry::get('Language')->getId();
      }
      
      // Generate cache key: md5(query + entityType + languageId)
      $cacheKey = md5($query . ($entityType ?? '') . ($languageId ?? ''));
      
      // 1. Check cache (namespace: Rag/Semantic, TTL: 30 minutes)
      $cache = new Cache($cacheKey, 'Rag/Semantic');
      
      if ($cache->exists(30)) { // 30 minutes
        $cachedResults = $cache->get();
        if ($cachedResults !== null) {
          $duration = (microtime(true) - $startTime) * 1000;

          $logMessage = sprintf(
            "✅ SEMANTIC CACHE HIT - Duration: %.2f ms | Query: %s | EntityType: %s | LanguageId: %s",
            $duration,
            $query,
            $entityType ?? 'null',
            $languageId ?? 'null'
          );
          
          if ($this->debug) {
            $this->logger->logSecurityEvent($logMessage, 'info');
          }
          
          // Always log to error_log for performance tracking
          error_log($logMessage);
          
          return $cachedResults; // ✅ CACHE HIT (< 10ms)
        }
      }
      
      // 2. Cache miss - execute search
      $cacheMissMessage = sprintf(
        "❌ SEMANTIC CACHE MISS - Executing search | Query: %s | EntityType: %s | LanguageId: %s",
        $query,
        $entityType ?? 'null',
        $languageId ?? 'null'
      );
      
      if ($this->debug) {
        $this->logger->logSecurityEvent($cacheMissMessage, 'info');
      }
      
      // Always log cache miss for performance tracking
      error_log($cacheMissMessage);
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "SemanticQueryExecutor: Executing semantic query using RAG: {$query}",
          'info'
        );
      }

      // Resolve contextual references using ConversationMemory
      $resolvedQuery = $query;
      $contextUsed = null;
      
      if ($this->conversationMemory !== null) {
        try {
          $resolutionResult = $this->conversationMemory->resolveContextualReferences($query);
          
          if ($resolutionResult['has_references'] && !empty($resolutionResult['resolved_query'])) {
            $resolvedQuery = $resolutionResult['resolved_query'];
            $contextUsed = $resolutionResult['context_used'];
            
            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "Contextual references resolved: '{$query}' -> '{$resolvedQuery}'",
                'info'
              );
            }
          }
        } catch (\Exception $e) {
          $this->logger->logSecurityEvent(
            "Error resolving contextual references: " . $e->getMessage(),
            'warning'
          );
          // Continue with original query
        }
      }

      // Retrieve last entity from memory if available
      $lastEntity = null;
      if ($this->conversationMemory !== null) {
        try {
          $lastEntity = $this->conversationMemory->getLastEntity();
          
          if ($lastEntity !== null && $this->debug) {
            $this->logger->logSecurityEvent(
              "Last entity from memory: {$lastEntity['type']} (ID: {$lastEntity['id']})",
              'info'
            );
          }
        } catch (\Exception $e) {
          $this->logger->logSecurityEvent(
            "Error retrieving last entity: " . $e->getMessage(),
            'warning'
          );
        }
      }

      // Extract context parameters
      $languageId = $context['language_id'] ?? null;
      $entityType = $context['entity_type'] ?? null;
      $entityId = $context['entity_id'] ?? null;
      
      // Use global constants (loaded from TechnicalConfig in config_clicshopping.php)
      $minScore = defined('CLICSHOPPING_APP_CHATGPT_RA_MIN_SIMILARITY_SCORE') ? (float)CLICSHOPPING_APP_CHATGPT_RA_MIN_SIMILARITY_SCORE : 0.25;
      $limit = defined('CLICSHOPPING_APP_CHATGPT_RA_MAX_RESULTS_PER_STORE') ? (int)CLICSHOPPING_APP_CHATGPT_RA_MAX_RESULTS_PER_STORE : 5;

      // Apply domain-specific semantic config overrides if available
      $semanticConfig = $this->getSemanticDomainConfig();
      if ($semanticConfig['min_score'] !== null) {
        $minScore = (float)$semanticConfig['min_score'];
      }
      if ($semanticConfig['limit'] !== null) {
        $limit = (int)$semanticConfig['limit'];
      }
      
      // Override with context if provided
      $minScore = $context['min_score'] ?? $minScore;
      $limit = $context['limit'] ?? $limit;

      // Use last entity from memory if not provided in context
      if ($entityId === null && $lastEntity !== null) {
        $entityId = $lastEntity['id'];
        $entityType = $lastEntity['type'];
        
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Using entity from memory: {$entityType} (ID: {$entityId})",
            'info'
          );
        }
      }

      // Get language ID from Registry if not provided
      if ($languageId === null && Registry::exists('Language')) {
        $languageId = Registry::get('Language')->getId();
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Semantic search - Query: {$resolvedQuery}, Language: {$languageId}, MinScore: {$minScore}, Limit: {$limit}, Entity Type: " . ($entityType ?? 'null'),
          'info'
        );
      }

      // Use MultiDBRAGManager's answerQuestion() which implements LLPhant QuestionAnswering
      // This is the PRIMARY method for semantic queries (not Gpt::getGptResponse)
      $ragManager = new MultiDBRAGManager(null, $semanticConfig['tables']);
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Calling MultiDBRAGManager::answerQuestion() with resolved query: {$resolvedQuery}",
          'info'
        );
      }

      // Use SemanticAgent::search() to get documents with entity information
      $searchResults = SemanticAgent::search(
        $resolvedQuery,
        $limit,
        $minScore,
        $languageId,
        $entityType,
        $entityId
      );
      
      $documentNames = [];
      if (!empty($searchResults['results'])) {
        foreach ($searchResults['results'] as $result) {
          $metadata = $result['metadata'] ?? [];
          
          // Try to extract document name from metadata
          $docName = null;
          
          // Priority order: domain-specific names first, then generic names
          $possibleFields = array_values(array_unique(array_merge(
            DomainFields::getPossibleFields(),
            [
              // Document-specific
              'title',                // General documents
              'document_name',        // Explicit document name
              'page_title',           // CMS pages
              'pages_title',          // Legacy pages
              // Generic fallback
              'name',                 // Generic name field
            ]
          )));
          
          foreach ($possibleFields as $field) {
            if (isset($metadata[$field]) && !empty($metadata[$field])) {
              $docName = trim($metadata[$field]);
              break;
            }
          }
          
          // Fallback 1: Try to build name from type + entity_id
          if ($docName === null && isset($metadata['type']) && isset($metadata['entity_id'])) {
            $type = ucwords(str_replace('_', ' ', $metadata['type']));
            $entityId = $metadata['entity_id'];
            $docName = "{$type} #{$entityId}";
          }
          
          // Fallback 2: Use source_table if no name found
          if ($docName === null && isset($metadata['source_table'])) {
            $tableName = $metadata['source_table'];

            // Remove prefix and _embedding suffix
            $prefix = CLICSHOPPING::getConfig('db_table_prefix');

            if (str_starts_with($tableName, $prefix)) {
              $tableName = substr($tableName, strlen($prefix));
            }
            $tableName = str_replace('_embedding', '', $tableName);
            $tableName = str_replace('_', ' ', $tableName);
            $docName = ucwords($tableName);
          }
          
          if ($docName !== null) {
            $documentNames[] = $docName;
          }
        }
      }
      
      $documentNames = array_values(array_unique($documentNames));
      
      if ($this->debug && !empty($documentNames)) {
        $this->logger->logSecurityEvent(
          "TASK 5.2.1.3: Extracted document names: " . implode(', ', $documentNames),
          'info'
        );
      }
      
      // Extract entity from search results if not already set
      if (!empty($searchResults['results'])) {
        $firstResult = $searchResults['results'][0];
        
        if (isset($firstResult['metadata']['entity_id']) && $entityId === null) {
          $entityId = (int) $firstResult['metadata']['entity_id'];
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Extracted entity_id from search: {$entityId}",
              'info'
            );
          }
        }
        
        if (isset($firstResult['metadata']['type']) && $entityType === null) {
          $entityType = $firstResult['metadata']['type'];
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Extracted entity_type from search: {$entityType}",
              'info'
            );
          }
        }
      }
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "After extraction - entity_id: " . ($entityId ?? 'NULL') . ", entity_type: " . ($entityType ?? 'NULL'),
          'info'
        );
      }
      
      // Call answerQuestion which uses proper RAG pattern:
      // 1. Search documents via embeddings
      // 2. Build context from retrieved documents
      // 3. Generate answer using LLM with context (not hallucination)
      $ragResult = $ragManager->answerQuestion(
        $resolvedQuery,
        $limit,
        $minScore,
        $languageId,
        $entityType,
        ['return_metadata' => true] // Request metadata in response
      );

      // Handle array response (with metadata) or string response
      $answer = '';
      $auditMetadata = [];
      
      if (is_array($ragResult)) {
        $answer = $ragResult['response'] ?? '';
        $auditMetadata = $ragResult['audit_metadata'] ?? [];
      } else {
        $answer = $ragResult;
      }

      // Check if answer is empty or indicates no data found
      // Load no-data indicators from language file
      $noDataIndicators = [];
      for ($i = 1; $i <= 6; $i++) {
        $indicator = $this->language->getDef("text_rag_semantic_no_data_indicator_{$i}");
        if (!empty($indicator)) {
          $noDataIndicators[] = $indicator;
        }
      }
      
      $hasNoData = false;
      foreach ($noDataIndicators as $indicator) {
        if (stripos($answer, $indicator) !== false) {
          $hasNoData = true;
          break;
        }
      }

      // If no data found in embeddings, indicate LLM fallback
      if (empty($answer) || $hasNoData) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "No embedding data found, would fallback to LLM for general knowledge",
            'info'
          );
        }

        // Return response indicating LLM fallback needed
        return AgentResponseHelper::createSemanticResponse(
          $query,
          [], // Empty results - no embeddings
          true,
          array_merge($auditMetadata, [
            'min_score' => $minScore,
            'execution_time' => microtime(true) - $startTime,
            'fallback_to_llm' => true,
            'rag_used' => true,
            'answer' => $answer, // Include the "not found" message
          ])
        );
      }

      // This ensures entity_id and entity_type are captured from semantic search results
      if (!empty($searchResults)) {
        $firstDoc = $searchResults[0] ?? null;
        if ($firstDoc && is_array($firstDoc)) {
          // Extract entity from document (check multiple possible locations)
          if (isset($firstDoc['entity_id']) && $entityId === null) {
            $entityId = (int) $firstDoc['entity_id'];
            
            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "Extracted entity_id from search result: {$entityId}",
                'info'
              );
            }
          }
          
          if (isset($firstDoc['type']) && $entityType === null) {
            $entityType = $firstDoc['type'];
            
            if ($this->debug) {
              $this->logger->logSecurityEvent(
                "Extracted entity_type from search result: {$entityType}",
                'info'
              );
            }
          }
          
          // Also check in metadata if not found at root level
          if (isset($firstDoc['metadata']) && is_array($firstDoc['metadata'])) {
            if (isset($firstDoc['metadata']['entity_id']) && $entityId === null) {
              $entityId = (int) $firstDoc['metadata']['entity_id'];
              
              if ($this->debug) {
                $this->logger->logSecurityEvent(
                  "Extracted entity_id from metadata: {$entityId}",
                  'info'
                );
              }
            }
            
            if (isset($firstDoc['metadata']['type']) && $entityType === null) {
              $entityType = $firstDoc['metadata']['type'];
              
              if ($this->debug) {
                $this->logger->logSecurityEvent(
                  "Extracted entity_type from metadata: {$entityType}",
                  'info'
                );
              }
            }
          }
        }
      }
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Final entity info - entity_id: " . ($entityId ?? 'NULL') . ", entity_type: " . ($entityType ?? 'NULL'),
          'info'
        );
      }
      
      // We have a valid answer from RAG (embeddings + LLM synthesis)
      // Format as semantic result with the answer
      $results = [
        [
          'content' => $answer,
          'score' => 0.85, // High confidence since it's from RAG
          'metadata' => [
            'source' => 'rag_knowledge_base',
            'method' => 'llphant_question_answering',
            'language_id' => $languageId,
            'entity_type' => $entityType,
            'entity_id' => $entityId, 
          ]
        ]
      ];

      // Store entity in memory if available
      if ($entityId !== null && $entityType !== null && $this->conversationMemory !== null) {
        try {
          $this->conversationMemory->setLastEntity((int)$entityId, $entityType);
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Stored entity in memory: {$entityType} (ID: {$entityId})",
              'info'
            );
          }
        } catch (\Exception $e) {
          $this->logger->logSecurityEvent(
            "Error storing entity in memory: " . $e->getMessage(),
            'warning'
          );
        }
      }

      // Return standardized response with RAG answer
      // IMPORTANT: Add 'response' field at root level for ResultFormatter compatibility
      $response = AgentResponseHelper::createSemanticResponse(
        $query,
        $results,
        true,
        array_merge($auditMetadata, [
          'min_score' => $minScore,
          'execution_time' => microtime(true) - $startTime,
          'result_count' => 1,
          'context_used' => $contextUsed !== null,
          'query_resolved' => $resolvedQuery !== $query,
          'rag_used' => true,
          'method' => 'llphant_question_answering',
          'entity_id' => $entityId, 
          'entity_type' => $entityType, 
        ])
      );
      
      // Add 'response' field for ResultFormatter (expects 'response' or 'interpretation')
      $response['response'] = $answer;
      $response['interpretation'] = $answer; // Fallback field name
      
      $response['entity_id'] = $entityId;
      $response['entity_type'] = $entityType;
      
      // This provides user-visible information about where the answer came from
      $response['source_attribution'] = [
        'source_type' => $this->language->getDef('text_rag_semantic_source_type'),
        'source_icon' => '📚',
        'source_details' => $this->language->getDef('text_rag_semantic_source_details'),
        'document_count' => count($documentNames),
        'document_names' => $documentNames, 
        'entity_type' => $entityType,
        'entity_id' => $entityId,
      ];
      
      $cache->save($response);
      
      $cacheDuration = (microtime(true) - $startTime) * 1000;
      
      $cacheCompleteMessage = sprintf(
        "[SUCCESS] Search completed and cached - Duration: %.2f ms | Query: %s | EntityType: %s | LanguageId: %s | ResultCount: %d",
        $cacheDuration,
        $query,
        $entityType ?? 'null',
        $languageId ?? 'null',
        count($response['results'] ?? [])
      );
      
      if ($this->debug) {
        $this->logger->logSecurityEvent($cacheCompleteMessage, 'info');
      }
      
      // Always log to error_log for performance tracking
      error_log($cacheCompleteMessage);
      
      return $response;

    } catch (\Exception $e) {
      $errorId = uniqid('sem_', true);
      $this->logger->logSecurityEvent(
        "Error executing semantic query [ID: {$errorId}]: " . $e->getMessage() . "\nQuery: {$query}\nStack: " . $e->getTraceAsString(),
        'error'
      );

      return AgentResponseHelper::createErrorResponse(
        $query,
        $this->language->getDef('text_rag_semantic_error_execution'),
        'semantic',
        [
          'error_id' => $errorId,
          'error_type' => 'execution_error',
          'component' => 'SemanticQueryExecutor::execute',
        ]
      );
    }
  }

  /**
   * Load semantic config for active domain.
   *
   * @return array{min_score: float|null, limit: int|null, tables: array}
   */
  private function getSemanticDomainConfig(): array
  {
    $activeDomain = DomainConfig::getActivities();
    $semanticConfigClass = DomainFields::resolveAppClass($activeDomain, 'SemanticConfig');
    
    if ($semanticConfigClass === null) {
      return ['min_score' => null, 'limit' => null, 'tables' => []];
    }
    
    try {
      if (is_subclass_of($semanticConfigClass, SemanticConfigInterface::class)) {
        $tables = $semanticConfigClass::getEmbeddingTables();
        return [
          'min_score' => $semanticConfigClass::getSimilarityThreshold(),
          'limit' => $semanticConfigClass::getMaxResultsPerStore(),
          'tables' => is_array($tables) ? $tables : []
        ];
      }
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Failed to load SemanticConfig: " . $e->getMessage(),
          'warning'
        );
      }
    }
    
    return ['min_score' => null, 'limit' => null, 'tables' => []];
  }
}
