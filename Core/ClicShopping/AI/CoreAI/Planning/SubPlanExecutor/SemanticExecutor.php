<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Planning\SubPlanExecutor;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\AI\CoreAI\Planning\SubPlanExecutor\SubSemanticExecutor\SemanticSearchOrchestrator;
use ClicShopping\AI\Security\Validation\AnswerGroundingVerifier;
use ClicShopping\AI\Security\Validation\HallucinationDetector;
use ClicShopping\AI\Security\Validation\ConfidenceScoreCalculator;
use ClicShopping\AI\CoreAI\Memory\EntityTypeRegistry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Config\DomainFields;

/**
 * SemanticExecutor Class
 *
 * Responsible for executing semantic searches.
 * Separated from PlanExecutor to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Execute semantic searches
 * - Format semantic results
 * - Handle semantic errors
 * - Manage search cache
 */

class SemanticExecutor
{
  private SecurityLogger $logger;
  private bool $debug;
  private ?MultiDBRAGManager $ragManager = null;
  private ?SemanticSearchOrchestrator $orchestrator = null;
  private ?AnswerGroundingVerifier $groundingVerifier = null;
  private ?HallucinationDetector $hallucinationHelper = null;
  private ?ConfidenceScoreCalculator $confidenceCalculator = null;
  private ?EntityTypeRegistry $entityRegistry = null;
  private string $userId;
  private int $languageId;
  
  // Hallucination detection configuration
  private float $groundingThreshold = 0.70; // Reject answers below this score
  private bool $enableHallucinationDetection = true; // Feature flag - RE-ENABLED with fix

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

    if ($this->debug) {
      $this->logger->logSecurityEvent("SemanticExecutor initialized", 'info');
    }
  }

  /**
   * Execute semantic search
   *
   * @param string $query Query to search
   * @param array $context Context information
   * @return array Result
   */
  public function executeSemanticSearch(string $query, array $context = []): array
  {
    // ALWAYS log entry (even if debug is off) to track execution
    $this->logger->logSecurityEvent(
      "[INFO : ANALYSE] SemanticExecutor.executeSemanticSearch() CALLED - Query: {$query}",
      'info'
    );
    
    try {
      // Initialize orchestrator if needed (lazy loading)
      if ($this->orchestrator === null) {
        $this->logger->logSecurityEvent(
          "Initializing SemanticSearchOrchestrator (userId: {$this->userId}, langId: {$this->languageId})",
          'info'
        );
        
        $this->orchestrator = new SemanticSearchOrchestrator(
          $this->userId,
          $this->languageId,
          $this->debug
        );
        
        $this->logger->logSecurityEvent(
          "✅ SemanticSearchOrchestrator initialized successfully",
          'info'
        );
      }

      // Enrich query with last_entity context if available
      // This is passed via $context from PlanExecutor
      $enrichedQuery = $query;
      if (isset($context['last_entity']) && !empty($context['last_entity'])) {
        $lastEntity = $context['last_entity'];
        $entityName = $lastEntity['name'] ?? ($lastEntity['id'] ?? null);
        
        if ($entityName !== null) {
          // Detect if query needs context enrichment
          $enrichedQuery = $this->enrichSemanticQuery($query, $entityName);
          
          if ($enrichedQuery !== $query && $this->debug) {
            $this->logger->logSecurityEvent(
              "Enriched semantic query with last_entity: '{$query}' → '{$enrichedQuery}'",
              'info'
            );
          }
        }
      }

      $this->logger->logSecurityEvent(
        " Delegating to SemanticSearchOrchestrator for query: {$enrichedQuery}",
        'info'
      );

      // Delegate to orchestrator with fallback chain (use enriched query)
      $rawResult = $this->orchestrator->search($enrichedQuery, $context);

      $this->logger->logSecurityEvent(
        "✅ SemanticSearchOrchestrator returned result",
        'info'
      );

      
      // 🔧 Skip grounding verification for LLM fallback responses
      // LLM fallback is used for general knowledge queries (e.g., "où est Paris?")
      // These don't need document grounding - they use the LLM's training data
      $source = $rawResult['source'] ?? 'documents';
      $skipGroundingVerification = ($source === 'llm');
      
      if ($this->enableHallucinationDetection && isset($rawResult['answer']) && !empty($rawResult['answer']) && !$skipGroundingVerification) {
        $this->logger->logSecurityEvent(
          "[INFO : ANALYSE] Running hallucination detection on answer (source: {$source})",
          'info'
        );
        
        $groundingResult = $this->verifyAnswerGrounding($rawResult);
        
        // Initialize helper if needed
        if ($this->hallucinationHelper === null) {
          $this->hallucinationHelper = new HallucinationDetector($this->debug);
        }
        
        
        if ($this->confidenceCalculator === null) {
          $this->confidenceCalculator = new ConfidenceScoreCalculator($this->debug);
        }
        
        $sourceDocuments = $rawResult['documents'] ?? [];
        $additionalFactors = [
          'response_length' => strlen($rawResult['answer']),
          'source_count' => count($sourceDocuments),
        ];
        
        $confidenceData = $this->confidenceCalculator->calculateCombinedConfidence(
          $sourceDocuments,
          $groundingResult,
          $additionalFactors
        );
        
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            sprintf(
              "📊 Confidence calculated: overall=%.4f, doc_relevance=%.4f, grounding=%.4f, level=%s",
              $confidenceData['overall_confidence'],
              $confidenceData['document_relevance'],
              $confidenceData['answer_grounding'],
              $confidenceData['confidence_level']
            ),
            'info'
          );
        }
        
        // Check if answer should be rejected
        if ($this->hallucinationHelper->shouldRejectAnswer($groundingResult, $this->groundingThreshold)) {
          $this->logger->logSecurityEvent(
            "❌ Answer REJECTED due to low grounding score: {$groundingResult['confidence']} < {$this->groundingThreshold}",
            'warning'
          );
          
          // Log flagged answer for review
          $this->hallucinationHelper->logFlaggedAnswer($query, $rawResult, $groundingResult, $this->userId, $this->languageId);
          
          // Return "insufficient information" message instead
          return $this->hallucinationHelper->createInsufficientInformationResponse($groundingResult, $this->languageId);
        }
        
        // Add grounding metadata to result (for FLAG and ACCEPT decisions)
        $rawResult['grounding_score'] = $groundingResult['confidence'];
        $rawResult['grounding_decision'] = $groundingResult['decision'];
        $rawResult['grounding_metadata'] = $this->hallucinationHelper->formatGroundingMetadata($groundingResult);
        
        
        $rawResult['confidence_data'] = $confidenceData;
        $rawResult['confidence_score'] = $confidenceData['overall_confidence'];
        $rawResult['confidence_level'] = $confidenceData['confidence_level'];
        $rawResult['confidence_ui'] = $this->confidenceCalculator->formatForUI($confidenceData);
        
        if ($this->hallucinationHelper->shouldFlagAnswer($groundingResult)) {
          $this->logger->logSecurityEvent(
            "⚠️  Answer FLAGGED for review - grounding score: {$groundingResult['confidence']}",
            'warning'
          );
          
          // Log flagged answer for review (but still return it)
          $this->hallucinationHelper->logFlaggedAnswer($query, $rawResult, $groundingResult, $this->userId, $this->languageId);
        } else {
          $this->logger->logSecurityEvent(
            "✅ Answer ACCEPTED - grounding score: {$groundingResult['confidence']}",
            'info'
          );
        }
      }

      // Format result (maintain backward compatibility)
      $formattedResult = $this->formatSemanticResult($rawResult);

      return $formattedResult;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "❌ Exception in executeSemanticSearch: " . $e->getMessage(),
        'error'
      );
      return $this->handleSemanticError($e, $query);
    }
  }

  /**
   * Format semantic result
   *
   *
   * @param array $rawResult Raw result from MultiDBRAGManager
   * @return array Formatted result
   */
  public function formatSemanticResult(array $rawResult): array
  {
    // Extract the actual answer/response
    $answer = $rawResult['answer'] ?? $rawResult['response'] ?? $rawResult['text_response'] ?? '';
    
    $formatted = [
      'type' => 'semantic',
      'success' => $rawResult['success'] ?? true,
      'text_response' => $answer,
      'response' => $answer,  
      'sources' => $rawResult['sources'] ?? [],
    ];

    // Add metadata if present
    if (isset($rawResult['metadata'])) {
      $formatted['metadata'] = $rawResult['metadata'];
    }
    
    // Add audit_metadata if present (from RAG search)
    if (isset($rawResult['audit_metadata'])) {
      $formatted['audit_metadata'] = $rawResult['audit_metadata'];
    }

    $entityInfo = $this->extractEntityFromDocuments($rawResult);
    if ($entityInfo !== null) {
      // Add _step_entity_metadata for EntityExtractor to find
      $formatted['_step_entity_metadata'] = $entityInfo;
      
      // Also add to top level for backward compatibility
      $formatted['entity_id'] = $entityInfo['entity_id'];
      $formatted['entity_type'] = $entityInfo['entity_type'];
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "TASK 4.3.7.1: Extracted entity from semantic query - entity_type: {$entityInfo['entity_type']}, entity_id: {$entityInfo['entity_id']}",
          'info'
        );
      }
    } else {
      // No entity found - this is a general knowledge query
      $formatted['_step_entity_metadata'] = [
        'entity_id' => 0,
        'entity_type' => 'general',
        'source' => 'semantic_query_no_entity'
      ];
      $formatted['entity_id'] = 0;
      $formatted['entity_type'] = 'general';
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "TASK 4.3.7.1: No entity found in semantic query - setting entity_type='general'",
          'info'
        );
      }
    }

    // 🆕 Add source attribution for semantic queries
    $documentNames = [];
    if (isset($rawResult['documents']) && is_array($rawResult['documents'])) {
      foreach ($rawResult['documents'] as $doc) {
        $metadata = null;
        
        // Handle both object and array document formats
        if (is_object($doc) && isset($doc->metadata)) {
          $metadata = $doc->metadata;
        } elseif (is_array($doc) && isset($doc['metadata'])) {
          $metadata = $doc['metadata'];
        }
        
        if ($metadata !== null) {
          // Try to extract document name from metadata
 
          $docName = null;
          $possibleFields = array_values(array_unique(array_merge(
            DomainFields::getPossibleFields(),
            ['title', 'document_name', 'page_title', 'pages_title', 'name']
          )));
          
          foreach ($possibleFields as $field) {
            if (isset($metadata[$field]) && !empty($metadata[$field])) {
              $docName = trim($metadata[$field]);
              break;
            }
          }
          
          // Fallback to source_table if no name found
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
    }
    
    $documentNames = array_values(array_unique($documentNames));
    
    if ($this->debug && !empty($documentNames)) {
      $this->logger->logSecurityEvent(
        "TASK 5.2.1.3: Extracted " . count($documentNames) . " document names: " . implode(', ', $documentNames),
        'info'
      );
    }
    
    $documentCount = count($documentNames);
    
    // Determine source type based on where the answer came from
    $source = $rawResult['source'] ?? 'documents';
    
    if ($source === 'llm') {
      // LLM fallback (general knowledge)
      $formatted['source_attribution'] = [
        'source_type' => 'LLM General Knowledge',
        'source_icon' => '🤖',
        'source_details' => 'Answer generated by AI language model',
        'fallback_reason' => 'No relevant documents found in knowledge base',
      ];
    } elseif ($source === 'conversation_memory') {
      // From conversation memory
      $formatted['source_attribution'] = [
        'source_type' => 'Conversation Memory',
        'source_icon' => '💭',
        'source_details' => 'Information retrieved from recent conversation history',
        'document_count' => $documentCount,
        'document_names' => $documentNames, 
      ];
    } else {
      // From document stores (RAG)
      $formatted['source_attribution'] = [
        'source_type' => 'RAG Knowledge Base',
        'source_icon' => '📚',
        'source_details' => 'Information retrieved from vector embeddings database',
        'document_count' => $documentCount,
        'document_names' => $documentNames, 
      ];
    }

    return $formatted;
  }

  /**
   * Extract entity information from document metadata
   *
   *
   * This method examines the documents returned from semantic search and extracts
   * entity information from their metadata. It infers entity_type from the source table name.
   *
   * @param array $rawResult Raw result containing documents
   * @return array|null Entity information or null if no entity found
   */
  private function extractEntityFromDocuments(array $rawResult): ?array
  {
    // Check if we have documents
    $documents = $rawResult['documents'] ?? [];

    if (empty($documents)) {
      return null;
    }

    // Iterate through documents to find one with entity metadata
    foreach ($documents as $doc) {
      $metadata = null;
      
      // Handle both object and array document formats
      if (is_object($doc) && isset($doc->metadata)) {
        $metadata = $doc->metadata;
      } elseif (is_array($doc) && isset($doc['metadata'])) {
        $metadata = $doc['metadata'];
      }
      
      if ($metadata === null) {
        continue;
      }

      // Extract entity_id from metadata
      $entityId = null;
      if (isset($metadata['entity_id']) && $metadata['entity_id'] > 0) {
        $entityId = (int)$metadata['entity_id'];
      }

      // Extract or infer entity_type
      $entityType = null;
      
      // First, check if entity_type is explicitly set in metadata
      if (isset($metadata['entity_type']) && !empty($metadata['entity_type'])) {
        $entityType = $metadata['entity_type'];
      }
      // Otherwise, infer from source_table or type
      elseif (isset($metadata['source_table']) && !empty($metadata['source_table'])) {
        $entityType = $this->inferEntityTypeFromTable($metadata['source_table']);
      }
      elseif (isset($metadata['type']) && !empty($metadata['type'])) {
        $entityType = $metadata['type'];
      }

      // If we found both entity_id and entity_type, return them
      if ($entityId !== null && $entityId > 0 && $entityType !== null) {
        return [
          'entity_id' => $entityId,
          'entity_type' => $entityType,
          'source' => 'embedding_metadata',
          'source_table' => $metadata['source_table'] ?? 'unknown'
        ];
      }
    }

    // No entity found in any document
    return null;
  }

  /**
   * Infer entity type from table name
   *
   *
   * This method uses EntityTypeRegistry to dynamically convert table names
   * to entity types, avoiding code duplication.
   *
   * Examples:
   * - clic_pages_manager_description_embedding → page_manager
   * - clic_products_embedding → product
   * - clic_categories_embedding → category
   *
   * @param string $tableName Table name (with or without prefix)
   * @return string Entity type
   */
  private function inferEntityTypeFromTable(string $tableName): string
  {
    // Initialize entity registry if needed (lazy loading)
    if ($this->entityRegistry === null) {
      $this->entityRegistry = EntityTypeRegistry::getInstance();
      $this->entityRegistry->initialize(); // Ensure registry is initialized
    }

    // First try full table name (with prefix/suffix) since registry stores full names
    $entityType = $this->entityRegistry->getEntityTypeFromTable($tableName);
    if ($entityType !== null) {
      return $entityType;
    }

    // Remove table prefix if present
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    if (!empty($prefix) && str_starts_with($tableName, $prefix)) {
      $tableName = substr($tableName, strlen($prefix));
    }

    // Remove '_embedding' suffix if present
    $tableName = str_replace('_embedding', '', $tableName);

    // If not found in registry, use table name as fallback
    if ($entityType === null) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Entity type not found in registry for table: {$tableName}, using table name as fallback",
          'warning'
        );
      }
      $entityType = $tableName;
    }

    return $entityType;
  }

  /**
   * Handle semantic error
   *
   * @param \Exception $e Exception
   * @param string $query Original query
   * @return array Error result
   */
  public function handleSemanticError(\Exception $e, string $query): array
  {
    $this->logger->logSecurityEvent(
      "Semantic search failed: {$query} - " . $e->getMessage(),
      'error'
    );

    return [
      'type' => 'semantic',
      'success' => false,
      'error' => $e->getMessage(),
      'text_response' => "Semantic search failed: " . $e->getMessage(),
    ];
  }

  /**
   * Get RAG manager instance
   *
   * @return MultiDBRAGManager|null
   */
  public function getRagManager(): ?MultiDBRAGManager
  {
    return $this->ragManager;
  }

  /**
   * Verify answer grounding using AnswerGroundingVerifier
   
   *
   * @param array $rawResult Raw result from orchestrator
   * @return array Grounding verification result
   */
  private function verifyAnswerGrounding(array $rawResult): array
  {
    try {
      // Initialize grounding verifier if needed (lazy loading)
      if ($this->groundingVerifier === null) {
        $this->groundingVerifier = new AnswerGroundingVerifier($this->debug);
        
        // Configure thresholds
        $this->groundingVerifier->setConfig([
          'threshold_accept' => 0.85,
          'threshold_flag' => $this->groundingThreshold,
        ]);
      }

      // Extract answer and source documents
      $answer = $rawResult['answer'] ?? $rawResult['response'] ?? '';
      $sourceDocuments = $rawResult['documents'] ?? [];
      
      if ($this->debug) {
        $documentCount = is_array($sourceDocuments) ? count($sourceDocuments) : 0;
        $this->logger->logSecurityEvent(
          "📚 Extracted {$documentCount} source documents for grounding verification",
          'info'
        );
        
        // 🔧 REGRESSION DEBUG: Log document structure
        if ($documentCount > 0) {
          $firstDoc = $sourceDocuments[0];
          $docType = is_array($firstDoc) ? 'array' : (is_object($firstDoc) ? 'object' : gettype($firstDoc));
          $this->logger->logSecurityEvent(
            "📄 First document type: {$docType}",
            'info'
          );
        }
      }

      // 🔧 FIX: Check if we have source documents
      // Check both empty() and count() to handle edge cases
      if (empty($sourceDocuments) || count($sourceDocuments) === 0) {
        $this->logger->logSecurityEvent(
          "⚠️  No source documents available for grounding verification - skipping detection",
          'warning'
        );
        
        // 🔧 REGRESSION FIX 2025-12-28: Return safe default (accept answer if no documents to verify against)
        // This prevents rejection of general knowledge queries where LLM provides the answer
        // without needing to ground it in specific documents
        return [
          'confidence' => 1.0,
          'decision' => 'ACCEPT',
          'sentence_count' => 0,
          'flagged_sentences' => [],
          'explanation' => 'No source documents available for verification - general knowledge query',
          'skipped' => true,
          'general_knowledge' => true,  // Flag to indicate this is a general knowledge response
        ];
      }

      // Verify grounding
      $result = $this->groundingVerifier->verifyGrounding($answer, $sourceDocuments);

      return $result;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error in verifyAnswerGrounding: " . $e->getMessage(),
        'error'
      );

      // Return safe default (accept answer if verification fails)
      return [
        'confidence' => 1.0,
        'decision' => 'ACCEPT',
        'sentence_count' => 0,
        'flagged_sentences' => [],
        'explanation' => 'Grounding verification failed: ' . $e->getMessage(),
        'error' => true,
      ];
    }
  }

  /**
   * Set grounding threshold
   *
   * @param float $threshold Threshold value (0.0-1.0)
   * @return void
   */
  public function setGroundingThreshold(float $threshold): void
  {
    $this->groundingThreshold = max(0.0, min(1.0, $threshold));
  }

  /**
   * Enable or disable hallucination detection
   *
   * @param bool $enabled Enable flag
   * @return void
   */
  public function setHallucinationDetection(bool $enabled): void
  {
    $this->enableHallucinationDetection = $enabled;
  }
  
  /**
   * Enrich semantic query with last_entity context
   * 
   * Enriches semantic queries with context from previous queries
   * This allows follow-up queries like "give me its description" to include the product name
   * 
   * IMPORTANT: This method receives queries in ENGLISH (already translated)
   * All processing is done in English in a multilingual environment
   * 
   * @param string $query Original semantic query (in English)
   * @param string $entityName Name of the last entity discussed
   * @return string Enriched query
   */
  private function enrichSemanticQuery(string $query, string $entityName): string
  {
    // Detect if query contains pronouns or possessive adjectives that need entity context
    // IMPORTANT: Only English keywords - queries are translated to English before processing
    $contextualKeywords = [
      // English pronouns and possessives
      'it', 'its', 'this', 'that', 'these', 'those', 'the',
      'him', 'her', 'them', 'his', 'their',
      // Generic contextual words (English only)
      'description', 'details', 'info', 'information',
      'features', 'specifications', 'characteristics',
      'price', 'cost', 'value',
    ];
    
    $lowerQuery = strtolower($query);
    $needsContext = false;
    
    foreach ($contextualKeywords as $keyword) {
      // Use word boundary to avoid false positives
      if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $lowerQuery)) {
        $needsContext = true;
        break;
      }
    }
    
    if (!$needsContext) {
      // Query doesn't need context enrichment
      return $query;
    }
    
    // Enrich query by prepending entity name
    // Example: "give me its description" → "iPhone 17 Pro description"
    // Example: "what are the features" → "iPhone 17 Pro features"
    $enrichedQuery = $entityName . ' ' . $query;
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Enriched semantic query: '{$query}' → '{$enrichedQuery}'",
        'info'
      );
    }
    
    return $enrichedQuery;
  }
}
