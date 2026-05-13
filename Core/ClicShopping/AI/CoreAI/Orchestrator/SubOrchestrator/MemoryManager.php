<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Memory\ConversationMemory;
use ClicShopping\AI\CoreAI\Memory\WorkingMemory;

/**
 * MemoryManager Class
 *
 * Responsible for memory operations wrapper and coordination.
 * Separated from OrchestratorAgent to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Store interactions in conversation memory
 * - Retrieve relevant context for queries
 * - Track last entity (entity_id, entity_type)
 * - Resolve contextual references in queries
 * - Provide clean interface for memory operations
 */

class MemoryManager
{
  private ?ConversationMemory $conversationMemory;
  private WorkingMemory $workingMemory;
  private SecurityLogger $securityLogger;
  private bool $debug;

  /**
   * Constructor
   *
   * @param ConversationMemory|null $conversationMemory Conversation memory instance (nullable)
   * @param WorkingMemory $workingMemory Working memory instance
   * @param bool $debug Enable debug logging
   */
  public function __construct(
    ?ConversationMemory $conversationMemory,
    WorkingMemory $workingMemory,
    bool $debug = false
  ) {
    $this->conversationMemory = $conversationMemory;
    $this->workingMemory = $workingMemory;
    $this->debug = $debug;
    $this->securityLogger = new SecurityLogger();

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent("MemoryManager initialized", 'info');
    }
  }

  /**
   * Store interaction in conversation memory
   *
   * Stores a user query and system response in conversation memory
   * for future context retrieval.
   *
   * @param string $query User query
   * @param string $response System response (formatted)
   * @param array $metadata Interaction metadata (intent, entities, etc.)
   */
  public function storeInteraction(string $query, string $response, array $metadata): void
  {
    if ($this->conversationMemory) {
      $this->conversationMemory->addInteraction($query, $response, $metadata);

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Stored interaction in conversation memory",
          'info'
        );
      }
    }
  }

  /**
   * Get relevant context for query
   *
   * Retrieves relevant conversation context (short-term and long-term)
   * for the given query using semantic similarity.
   *
   * @param string $query User query
   * @return array Relevant context (short_term_context, long_term_context, feedback_context)
   */
  public function getRelevantContext(string $query): array
  {
    if ($this->conversationMemory) {
      return $this->conversationMemory->getRelevantContext($query);
    }
    return [];
  }

  /**
   * Set last entity in conversation memory
   *
   * Tracks the last entity (product, category, customer, etc.) mentioned
   * in the conversation for contextual reference resolution.
   *
   * @param int $entityId Entity ID
   * @param string $entityType Entity type (product, category, customer, etc.)
   * @param string|null $entityName Entity name (optional, for context enrichment)
   */
  public function setLastEntity(int $entityId, string $entityType, ?string $entityName = null): void
  {
    // CRITICAL: Always log (even if debug is off) to diagnose context resolution issues
    if ($this->debug) {
      $nameInfo = $entityName ? ", Name={$entityName}" : "";
      error_log("[MemoryManager] setLastEntity called: ID={$entityId}, Type={$entityType}{$nameInfo}");
    }
    
    if ($this->conversationMemory && $entityId !== null && $entityId !== 0) {
      $this->conversationMemory->setLastEntity($entityId, $entityType, $entityName);

      if ($this->debug) {
        $nameInfo = $entityName ? ", name={$entityName}" : "";
	
        $this->securityLogger->logSecurityEvent(
          "Stored last entity: entity_id={$entityId}, type={$entityType}{$nameInfo}",
          'info'
        );
      }
    } else {
      if ($this->debug) {
        error_log("[MemoryManager] setLastEntity SKIPPED: conversationMemory=" . ($this->conversationMemory ? 'exists' : 'null') . ", entityId={$entityId}");
      }
    }
  }

  /**
   * Clear last entity from conversation memory
   *
   * Clears the last entity tracking, typically when context switches
   * to a new domain or topic.
   */
  public function clearLastEntity(): void
  {
    if ($this->conversationMemory) {
      $this->conversationMemory->clearLastEntity();

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Cleared last entity from conversation memory",
          'info'
        );
      }
    }
  }

  /**
   * Resolve contextual references in query
   *
   * Resolves pronouns and contextual references (it, this, that, etc.)
   * to actual entities based on conversation history.
   *
   * @param string $query User query
   * @return array Resolved query info (has_references, resolved_query, entity_id, entity_type)
   */
  public function resolveContextualReferences(string $query): array
  {
    if ($this->conversationMemory) {
      return $this->conversationMemory->resolveContextualReferences($query);
    }
    return ['has_references' => false, 'resolved_query' => $query];
  }

  /**
   * Get working memory instance
   *
   * Provides access to working memory for temporary data storage
   * during query execution.
   *
   * @return WorkingMemory Working memory instance
   */
  public function getWorkingMemory(): WorkingMemory
  {
    return $this->workingMemory;
  }

  /**
   * Check if conversation memory is available
   *
   * @return bool True if conversation memory is available
   */
  public function hasConversationMemory(): bool
  {
    return $this->conversationMemory !== null;
  }

  /**
   * Store orchestration result in conversation memory with metadata
   * 
   * Builds comprehensive metadata and stores the interaction in conversation memory.
   * Also handles entity tracking.
   * 
   * @param string $query Original user query
   * @param string $queryToProcess Processed query
   * @param array $response Complete response structure
   * @param array $intent Intent analysis result
   * @param array $contextAnalysis Context analysis result
   * @param object $plan Execution plan
   * @param array $validationResults Validation results
   * @param int $entityId Extracted entity ID
   * @param string $entityType Extracted entity type
   * @param string $userId User ID
   * @param int $languageId Language ID
   * @param object $queryAnalyzer QueryAnalyzer instance for keyword extraction
   * @param object $responseProcessor ResponseProcessor instance for formatting
   */
  public function storeOrchestrationResult(
    string $query,
    string $queryToProcess,
    array $response,
    array $intent,
    array $contextAnalysis,
    $plan,
    array $validationResults,
    int $entityId,
    string $entityType,
    string $userId,
    int $languageId,
    $queryAnalyzer,
    $responseProcessor
  ): void {
    // Build metadata
    $metadata = [
      'success' => true,
      'agent_type' => 'orchestrator_full',
      'intent_confidence' => $intent['confidence'],
      'intent_type' => $intent['type'] ?? 'unknown',
      'execution_time' => $response['execution_time'],
      'plan_steps' => $plan !== null ? count($plan->getSteps()) : 0, 
      'validations_performed' => count($validationResults),
      'entity_id' => $entityId,
      'entity_type' => $entityType,
      'user_id' => $userId,
      'language_id' => $languageId,
      'timestamp' => time(),
      'response_type' => $response['type'] ?? 'unknown',
      'keywords' => $queryAnalyzer !== null ? $queryAnalyzer->extractKeywords($query) : [], // Handle null queryAnalyzer
      'original_query' => $query,
      'processed_query' => $queryToProcess,
      'is_related_to_context' => $contextAnalysis['is_related_to_context'] ?? false,
      'relation_type' => $contextAnalysis['relation_type'] ?? 'none',
      'context_confidence' => $contextAnalysis['confidence'] ?? 0.0,
      'related_entities' => $contextAnalysis['related_entities'] ?? [],
    ];

    // 🔧 FIX: Skip memory storage for web_search to avoid embedding timeout
    // WebSearch results don't need to be in long-term memory
    $intentType = $intent['type'] ?? 'unknown';
    
    if ($intentType === 'web_search') {
      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'MemoryManager', 'skipped_websearch_storage', [
          'reason' => 'web_search results not stored in memory to avoid embedding timeout',
          'query' => $query
        ]);
      }
      // Skip memory storage for web_search
      return;
    }
    
    // Store interaction
    if ($this->conversationMemory) {
      $formattedResponse = $responseProcessor->formatResponseForMemory($response);
      $this->conversationMemory->addInteraction($query, $formattedResponse, $metadata);
      
      if ($this->debug) {
        $this->securityLogger->logStructured('info', 'MemoryManager', 'stored_interaction', [
          'entity_id' => $entityId,
          'entity_type' => $entityType,
          'metadata_keys' => array_keys($metadata)
        ]);
      }
    }

    // Store last entity
    if ($entityId !== null && $entityId !== 0) {
      $this->setLastEntity($entityId, $entityType);
    }
  }
}
