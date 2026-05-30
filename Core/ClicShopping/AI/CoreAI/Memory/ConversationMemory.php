<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory;


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Storage\MariaDBVectorStore;
use ClicShopping\AI\DomainsAI\CoreAI\Embedding\NewVector;
use LLPhant\Embeddings\Document;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;
use LLPhant\Chat\Message; // Use the specific LLPhant class for type hinting

use ClicShopping\AI\CoreAI\Memory\SubConversationMemory\ShortTermMemoryManager;
use ClicShopping\AI\CoreAI\Memory\SubConversationMemory\LongTermMemoryManager;
use ClicShopping\AI\CoreAI\Memory\SubConversationMemory\ContextResolver;
use ClicShopping\AI\CoreAI\Memory\SubConversationMemory\EntityTracker;
use ClicShopping\AI\Infrastructure\Metrics\MemoryStatistics;
use ClicShopping\AI\CoreAI\Memory\SubConversationMemory\FeedbackManager;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;

/**
 * ConversationMemory Class
 *
 * Manages the multi-agent system's conversational memory using LLPhant components.
 * This class has been refactored to use SubConversationMemory components for better
 * separation of concerns and maintainability.
 *
 * Responsibilities include:
 * - Storing user-system interactions in both short-term (History) and long-term (Vector Store) memory.
 * - Maintaining a coherent conversational context.
 * - Retrieving relevant historical interactions via vector embeddings.
 * - Resolving contextual references ("it," "the last one," etc.) within user queries.
 * - Learning from successful interaction patterns.
 * - Tracking entities mentioned in conversations for contextual follow-up queries.
 *
 * Architecture:
 * This class delegates specialized responsibilities to SubConversationMemory components:
 * - ShortTermMemoryManager: Manages conversation history and message retention
 * - LongTermMemoryManager: Manages vector store and semantic search
 * - ContextResolver: Resolves contextual references in queries
 * - EntityTracker: Tracks last mentioned entities for implicit context
 * - MemoryStatistics: Records operation statistics and performance metrics
 * - FeedbackManager: Manages user feedback and learning from corrections
 *
 * Configuration:
 * Configuration should be done through the SubConversationMemory components directly,
 * not through setter methods on this class. For example:
 * 
 * ```php
 * // Configure through constructor
 * $memory = new ConversationMemory(
 *   userId: 'user123',
 *   languageId: 1,
 *   tableName: 'rag_conversation_memory_embedding',
 *   entityId: 0
 * );
 * 
 * // Or configure SubComponents directly if needed
 * $memory->shortTermManager->setMaxHistorySize(20);
 * $memory->longTermManager->setSimilarityThreshold(0.8);
 * ```
 *
 * Migration Notes:
 * The following methods have been removed as part of the SubConversationMemory refactoring:
 * - setMaxHistorySize() - Use $memory->shortTermManager->setMaxHistorySize() instead
 * - setSimilarityThreshold() - Use $memory->longTermManager->setSimilarityThreshold() instead
 * - cleanOrphanedChunks() - Use $memory->longTermManager->cleanDuplicates() instead
 *
 * These methods are no longer needed because configuration is now handled through
 * the SubConversationMemory components, which provide better encapsulation and
 * more flexible configuration options.
 *
 * @see ShortTermMemoryManager For short-term memory configuration
 * @see LongTermMemoryManager For long-term memory and vector store configuration
 * @see ContextResolver For contextual reference resolution
 * @see EntityTracker For entity tracking and implicit context
 */

class ConversationMemory
{
  private SecurityLogger $securityLogger;
  private MariaDBVectorStore $vectorStore;
  private EmbeddingGeneratorInterface $embeddingGenerator;
  private ConversationHistory $conversationHistory;
  private bool $debug;
  private string $userId;
  private int $languageId;

  private int $entityId;
  private ?string $lastQueryType = null;
  
  // Entity tracking properties (for backward compatibility)
  private ?int $lastEntityId = null;
  private ?string $lastEntityType = null;
  
  // Database prefix
  private string $prefix;

  // Configuration
  private int $maxHistorySize = 10; // Max number of messages in short-term memory
  private int $maxContextWindow = 5; // Context window size for reference resolution
  private float $similarityThreshold = 0.7; // Threshold for semantic search

  // Statistics
  private array $stats = [];

  private mixed $db;

  // 🆕 Refactored components
  private ShortTermMemoryManager $shortTermManager;
  private LongTermMemoryManager $longTermManager;
  private ContextResolver $contextResolver;
  private EntityTracker $entityTracker;
  private MemoryStatistics $memoryStats;
  private FeedbackManager $feedbackManager;

  /**
   * Constructor
   *
   * @param string $userId User identifier (default: 'system')
   * @param int|null $languageId Language ID
   * @param string $tableName Table for long-term memory (default: 'rag_conversation_memory_embedding')
   * @param int $entityId Entity ID (default: 0)
   * @throws \RuntimeException If the database or embedding generator cannot be initialized.
   */
  public function __construct( string $userId = 'system', ?int $languageId = null, string $tableName = 'rag_conversation_memory_embedding', int $entityId = 0) {
    $this->db = Registry::get('Db');
    $this->userId = $userId;
    $this->securityLogger = new SecurityLogger();

    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    // Use null coalescing for language ID registry check
    $this->languageId = $languageId ?? Registry::get('Language')->getId();
    $this->entityId = $entityId;
    
    // 🔧 PHASE 5: Initialize entity tracking properties
    $this->lastEntityId = null;
    $this->lastEntityType = null;

    // Initialize the LLPhant-compatible embedding generator
    $this->embeddingGenerator = $this->createEmbeddingGenerator();

    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');

    // Initialize the vector store for long-term memory
    $this->vectorStore = new MariaDBVectorStore($this->embeddingGenerator,  $tableName);

    // 🆕 Initialize refactored components FIRST
    $this->shortTermManager = new ShortTermMemoryManager($this->maxHistorySize, $this->debug);
    $this->longTermManager = new LongTermMemoryManager($this->vectorStore, $this->embeddingGenerator, $this->similarityThreshold, $this->debug, $this->languageId);
    
    $this->entityTracker = new EntityTracker($this->debug);
    
    $this->contextResolver = new ContextResolver($this->languageId, $this->debug, $this->entityTracker);
    
    $this->memoryStats = new MemoryStatistics($this->debug);
    $this->feedbackManager = new FeedbackManager($this->debug);

    // Get ConversationHistory from ShortTermMemoryManager for compatibility
    $this->conversationHistory = $this->shortTermManager->getConversationHistory();

    // Load recent history (optional feature, kept for completeness)
    $this->loadRecentHistory();

    // 🗑️ REMOVED: initializeStats() - Now handled by MemoryStatistics component

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent( "ConversationMemory initialized with SubConversationMemory components for user: {$this->userId}", 'info');
    }
  }


  /**
   * Adds an interaction (user question + assistant answer) to the memory.
   * 🆕 REFACTORED: Délègue à ShortTermMemoryManager et LongTermMemoryManager
   *
   * @param string $userMessage User's message
   * @param string $systemResponse System's response
   * @param array $metadata Additional metadata (should include query_type, confidence, entities)
   * @return bool Success of the operation
   */
  public function addInteraction( string $userMessage, string $systemResponse, array $metadata = []): bool
  {
    $startTime = microtime(true);
    
    try {
      
      if (!isset($metadata['query_type'])) {
        $metadata['query_type'] = 'unknown';
        
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "[WARNING] query_type not provided in metadata, defaulting to 'unknown'",
            'warning'
          );
        }
      }
      
      if (!isset($metadata['classification_confidence'])) {
        $metadata['classification_confidence'] = 0.0;
      }
      
      if (!isset($metadata['detected_entities'])) {
        $metadata['detected_entities'] = [];
      }
      
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "[INFO] Storing interaction with metadata - query_type: {$metadata['query_type']}, " .
          "confidence: {$metadata['classification_confidence']}, " .
          "entities: " . json_encode($metadata['detected_entities']),
          'info'
        );
      }
      
      if (isset($metadata['query_type'])) {
        $contextSwitch = $this->detectContextSwitch($metadata['query_type']);
        
        if ($contextSwitch) {
          $this->clearLastEntity();
          
          if ($this->debug) {
            $this->securityLogger->logSecurityEvent(
              "TASK 6.3: Context switch detected - clearing last entity",
              'info'
            );
          }
        }
        
        $this->lastQueryType = $metadata['query_type'];
      }
      
      // 1. Add to short-term memory via ShortTermMemoryManager
      $shortTermStart = microtime(true);
      $this->shortTermManager->addMessage(new Message('user', $userMessage));
      $this->shortTermManager->addMessage(new Message('assistant', $systemResponse));

      // Update local reference for compatibility
      $this->conversationHistory = $this->shortTermManager->getConversationHistory();
      if ($this->debug) {
        error_log(sprintf("[PERF] ConversationMemory: short-term store took %.3fs",
          microtime(true) - $shortTermStart));
      }

      // 2. Store in long-term memory via LongTermMemoryManager (includes embedding)
      $longTermStart = microtime(true);
      $fullContent = $this->formatInteractionForStorage($userMessage, $systemResponse);
      if ($this->debug) {
        error_log(sprintf("[PERF] ConversationMemory: long-term store START — content: %d chars",
          strlen($fullContent)));
      }
      $success = $this->longTermManager->storeInteraction($fullContent, $metadata);
      if ($this->debug) {
        error_log(sprintf("[PERF] ConversationMemory: long-term store END — took %.3fs (success: %s)",
          microtime(true) - $longTermStart,
          $success ? 'YES' : 'NO'));
      }

      // Periodically clean duplicates (every 20 interactions)
      static $cleanupCounter = 0;
      if (++$cleanupCounter % 20 === 0) {
        $cleanupStart = microtime(true);
        try {
          $cleanupStats = $this->longTermManager->cleanDuplicates();
          if ($this->debug) {
            error_log(sprintf("[PERF] ConversationMemory: cleanDuplicates took %.3fs (cleaned: %d)",
              microtime(true) - $cleanupStart,
              $cleanupStats['total_cleaned'] ?? 0));
          }
          if ($this->debug && $cleanupStats['total_cleaned'] > 0) {
            $this->securityLogger->logSecurityEvent(
              "Cleaned {$cleanupStats['total_cleaned']} duplicate entries (by interaction_id: {$cleanupStats['by_interaction_id']}, by content_hash: {$cleanupStats['by_content_hash']})",
              'info'
            );
          }
        } catch (\Exception $e) {
          // Don't fail on cleanup errors
          if ($this->debug) {
            $this->securityLogger->logSecurityEvent(
              "Error during duplicate cleanup: " . $e->getMessage(),
              'warning'
            );
          }
        }
      }

      // 3. Record statistics
      $this->memoryStats->recordOperation('interactions_stored', $success, microtime(true) - $startTime);

      // 4. Learn from successful interactions
      if ($success && ($metadata['success'] ?? true)) {
        $learnStart = microtime(true);
        $this->learnFromSuccessfulInteraction($userMessage, $systemResponse, $metadata);
        if ($this->debug) {
          error_log(sprintf("[PERF] ConversationMemory: learnFromSuccessfulInteraction took %.3fs",
            microtime(true) - $learnStart));
        }
      }

      if ($this->debug) {
        error_log(sprintf("[PERF] ConversationMemory::addInteraction TOTAL %.3fs",
          microtime(true) - $startTime));
      }

      return $success;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error in addInteraction: " . $e->getMessage(),
        'error'
      );
      
      $this->memoryStats->recordOperation('interactions_stored', false, microtime(true) - $startTime);
      
      return false;
    }
  }

  /**
   * Retrieves the relevant conversational context for the current query.
   * 🆕 REFACTORED: Délègue à ShortTermMemoryManager et LongTermMemoryManager
   *
   * Note: Type-aware filtering is not currently implemented because LLPhant Message objects
   * don't natively support metadata. Query type tracking is done separately for context
   * switch detection. Future enhancement could store metadata alongside messages.
   *
   * @param string $currentQuery Current user query
   * @param int $limit Max number of long-term memory results
   * @param string|null $currentQueryType Optional query type (for future use and context switch detection)
   * @return array Context containing short-term and long-term memories
   */
  public function getRelevantContext(string $currentQuery, int $limit = 3, ?string $currentQueryType = null): array
  {
    $startTime = microtime(true);
    
    try {
      // 1. Get short-term history from ShortTermMemoryManager
      $recentMessages = $this->shortTermManager->getRecentMessages($this->maxContextWindow);
      
      // Note: Type-aware filtering would require storing metadata with messages
      // Currently, we track query types separately for context switch detection only
      
      // Format for compatibility
      $shortTerm = [];
      foreach ($recentMessages as $message) {
        // Check if role is initialized (LLPhant Message may not have role set)
        $role = 'user'; // Default role
        if (isset($message->role)) {
          $role = $message->role;
        } elseif (property_exists($message, 'role')) {
          try {
            $role = $message->role;
          } catch (\Error $e) {
            // Role not initialized, use default
            $role = 'user';
          }
        }
        
        $shortTerm[] = [
          'role' => $role,
          'content' => $message->content ?? '',
        ];
      }

      // 2. Search long-term memory via LongTermMemoryManager
      // 🔧 FIX: Pass user_id and language_id to filter results properly
      $longTerm = $this->longTermManager->searchSimilar($currentQuery, $limit, $this->userId, $this->languageId);

      // 3. Get feedback context for learning
      $feedbackContext = $this->getFeedbackContext($currentQuery, 3);

      
      $contextSwitch = false;
      if ($currentQueryType !== null) {
        $contextSwitch = $this->detectContextSwitch($currentQueryType);
        
        if ($contextSwitch && $this->debug) {
          $this->securityLogger->logSecurityEvent(
            "TASK 6.5: Context switch detected in getRelevantContext() for query type '{$currentQueryType}'",
            'info'
          );
        }
      }

      // 4. Combine and structure the context
      $context = [
        'short_term_context' => $shortTerm,
        'long_term_context' => $longTerm,
        'feedback_context' => $feedbackContext,
        'has_context' => !empty($shortTerm) || !empty($longTerm),
        'context_switch' => $contextSwitch, 
      ];

      $this->memoryStats->recordOperation('context_retrieved', true, microtime(true) - $startTime);

      return $context;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error retrieving context: " . $e->getMessage(),
        'error'
      );

      $this->memoryStats->recordOperation('context_retrieved', false, microtime(true) - $startTime);

      return [
        'short_term_context' => [],
        'long_term_context' => [],
        'feedback_context' => [],
        'has_context' => false,
        'context_switch' => false, 
      ];
    }
  }

  /**
   * Resolves contextual references ("it", "the previous one", etc.) in a query.
   *
   * @param string $query Query with potential references
   * @return array Resolved query and context used
   */
  public function resolveContextualReferences(string $query): array
  {
    $startTime = microtime(true);
    
    if ($this->debug) {
      error_log("[ConversationMemory] resolveContextualReferences called for query: " . substr($query, 0, 50));
    }
    
    try {
      $lastEntity = $this->entityTracker->getLastEntity();
      
      // CRITICAL: Always log last entity retrieval
      if ($this->debug) {
        error_log("[ConversationMemory] Last entity retrieved: " . json_encode($lastEntity));
      }  
      // If we have a last entity, provide it as context to the LLM
      if ($lastEntity !== null) {
        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Providing last entity context to LLM: {$lastEntity['type']} (ID: {$lastEntity['id']})",
            'info'
          );
        }

        if ($this->debug) {  
          error_log("[ConversationMemory] Returning context with last entity: ID={$lastEntity['id']}, Type={$lastEntity['type']}");
        }   
	     
        $this->memoryStats->recordOperation('references_resolved', true, microtime(true) - $startTime);
        
        return [
          'resolved_query' => $query,
          'original_query' => $query,
          'has_references' => true, // Mark as having context available
          'is_implicit_context' => true,
          'context_used' => null,
          'last_entity' => $lastEntity, // Provide to LLM for intelligent context resolution
        ];
      }
      
      // No last entity available - query has no context
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "No last entity available for context resolution",
          'info'
        );
      }
      
      if ($this->debug) {  
        error_log("[ConversationMemory] No last entity available - returning without context");
      }
      
      $this->memoryStats->recordOperation('references_resolved', true, microtime(true) - $startTime);
      
      return [
        'resolved_query' => $query,
        'has_references' => false,
        'context_used' => null,
        'last_entity' => null,
      ];

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error resolving references: " . $e->getMessage(),
        'error'
      );

      if ($this->debug) {        
        error_log("[ConversationMemory] ERROR in resolveContextualReferences: " . $e->getMessage());
      }
      
      $this->memoryStats->recordOperation('references_resolved', false, microtime(true) - $startTime);

      return [
        'resolved_query' => $query,
        'has_references' => false,
        'error' => $e->getMessage(),
        'last_entity' => null,
      ];
    }
  }

  /**
   * Learns from successful interactions by storing metadata for future analysis.
   *
   * @param string $userMessage User message
   * @param string $systemResponse System response
   * @param array $metadata Interaction metadata
   */
  private function learnFromSuccessfulInteraction(string $userMessage, string $systemResponse, array $metadata): void
  {
    // Identify successful patterns
    $pattern = [
      'query_type' => $metadata['agent_type'] ?? 'unknown',
      'intent_confidence' => $metadata['intent_confidence'] ?? 0,
      'execution_time' => $metadata['execution_time'] ?? 0,
      'user_query_length' => str_word_count($userMessage),
      'response_quality' => $this->assessResponseQuality($systemResponse),
    ];

    // Store in stats for future analysis
    $this->stats['successful_patterns'][] = $pattern;

    // Limit the size of the patterns array
    if (count($this->stats['successful_patterns']) > 100) {
      array_shift($this->stats['successful_patterns']);
    }
  }

  /**
   * Assesses the quality of a response based on word count.
   *
   * @param string $response The response to evaluate
   * @return string Quality (high, medium, low)
   */
  private function assessResponseQuality(string $response): string
  {
    $wordCount = str_word_count($response);

    if ($wordCount < 10) return 'low';
    if ($wordCount < 50) return 'medium';
    return 'high';
  }

  /**
   * 
   * Compares the current query type with the last stored query type.
   * Since LLPhant Message objects don't natively support metadata,
   * we track query types separately in the ConversationMemory class.
   * Hybrid queries are excluded from context switch detection as they can contain both types.
   *
   * @param string $currentQueryType Current query type (analytics, semantic, hybrid, web_search)
   * @return bool True if context switch detected, false otherwise
   */
  private function detectContextSwitch(string $currentQueryType): bool
  {
    try {
      // Check if we have a last query type stored
      if ($this->lastQueryType === null) {
        // No previous query type, so no switch
        
        if ($this->debug) {
          $this->securityLogger->logStructured(
            'info',
            'ConversationMemory',
            'first_query_in_conversation',
            [
              'current_query_type' => $currentQueryType,
              'user_id' => $this->userId,
              'language_id' => $this->languageId,
              'note' => 'No previous query type - this is the first query in conversation'
            ]
          );
        }
        return false;
      }
      
      $lastQueryType = $this->lastQueryType;
      
      // Context switch if types differ (excluding hybrid)
      // Hybrid queries don't trigger context switches because they can contain both types
      $isSwitch = $lastQueryType !== $currentQueryType && 
                  $lastQueryType !== 'hybrid' && 
                  $currentQueryType !== 'hybrid';
      
      
      if ($isSwitch) {
        if ($this->debug) {
          $this->securityLogger->logStructured(
            'info',
            'ConversationMemory',
            'context_switch_detected',
            [
              'previous_query_type' => $lastQueryType,
              'current_query_type' => $currentQueryType,
              'switch_direction' => "{$lastQueryType} → {$currentQueryType}",
              'user_id' => $this->userId,
              'language_id' => $this->languageId,
              'entity_will_be_cleared' => true,
              'timestamp' => date('Y-m-d H:i:s'),
              'note' => 'Context switch detected - entity tracking will be cleared'
            ]
          );
        }
      } else {
        
        if ($this->debug) {
          $this->securityLogger->logStructured(
            'info',
            'ConversationMemory',
            'context_continuation',
            [
              'query_type' => $currentQueryType,
              'previous_query_type' => $lastQueryType,
              'user_id' => $this->userId,
              'language_id' => $this->languageId,
              'note' => 'No context switch - continuing with same query type'
            ]
          );
        }
      }
      
      return $isSwitch;
      
    } catch (\Exception $e) {
      
      if ($this->debug) {
        $this->securityLogger->logStructured(
          'error',
          'ConversationMemory',
          'context_switch_detection_error',
          [
            'current_query_type' => $currentQueryType,
            'last_query_type' => $this->lastQueryType ?? 'null',
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'user_id' => $this->userId,
            'note' => 'Error detecting context switch - defaulting to no switch'
          ]
        );
      }
      return false;
    }
  }

   /**
   * Formats an interaction for vector store storage.
   *
   * @param string $userMessage User message
   * @param string $systemResponse System response
   * @return string Formatted content
   */
  private function formatInteractionForStorage(
    string $userMessage,    string $systemResponse): string {
    $cleanedResponse = self::cleanResponseForEmbedding($systemResponse);

    return "User: {$userMessage}\n\nAssistant: {$cleanedResponse}";
  }

  /**
   * Reduces an assistant response to the plain semantic text the embedding
   * model needs. Strips HTML, decodes entities, collapses whitespace and
   * caps the result so a runaway answer never poisons the embedding cost.
   *
   * The default cap (32 000 chars) is well above the typical text content of
   * a Hybrid response (≈ 5 KB) yet keeps even large pages inside one chunk.
   * Override via CLICSHOPPING_APP_CHATGPT_RA_EMBED_RESPONSE_MAX_CHARS.
   */
  private static function cleanResponseForEmbedding(string $response): string
  {
    if ($response === '') {
      return $response;
    }

    // If there are no HTML tags at all, skip the work
    $hasTags = str_contains($response, '<');

    if ($hasTags) {
      $response = \strip_tags($response);
      $response = \html_entity_decode($response, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $response = \preg_replace('/\s+/u', ' ', $response);
    $response = \trim($response);

    $maxChars = 32000;
    if (\defined('CLICSHOPPING_APP_CHATGPT_RA_EMBED_RESPONSE_MAX_CHARS')) {
      $override = (int) \constant('CLICSHOPPING_APP_CHATGPT_RA_EMBED_RESPONSE_MAX_CHARS');
      if ($override > 0) {
        $maxChars = $override;
      }
    }

    if (\mb_strlen($response) > $maxChars) {
      $response = \mb_substr($response, 0, $maxChars);
    }

    return $response;
  }


  /**
   * Loads recent history from the long-term memory to seed the short-term history.
   * This is a speculative feature and relies on high-quality timestamps/metadata.
   */
  private function loadRecentHistory(): void
  {
    try {
      // Filter for interactions in the last hour from the current user
      $oneHourAgo = time() - 3600;

      $filter = function($metadata) use ($oneHourAgo) {
        $isRecent = ($metadata['timestamp'] ?? 0) > $oneHourAgo;
        $isCurrentUser = ($metadata['user_id'] ?? '') === $this->userId;
        $isCurrentEntity = ($metadata['entity_id'] ?? 0) === $this->entityId;
        return $isRecent && $isCurrentUser && $isCurrentEntity;
      };

      // Search for documents (chunks) that match the filter
      $results = $this->vectorStore->similaritySearch("recent interactions for user {$this->userId}", $this->maxHistorySize * 5, 0.5, $filter );

      // Reconstruct and sort the interactions to rebuild the conversation history
      $groupedResults = $this->groupChunksByInteraction($results);
      $recentInteractions = $this->reconstructInteractions($groupedResults, $this->maxHistorySize);

      // Sort by timestamp
      usort($recentInteractions, function($a, $b) {
        return ($a['timestamp'] ?? 0) <=> ($b['timestamp'] ?? 0);
      });

      // Rebuild the LLPhant ConversationHistory
      foreach ($recentInteractions as $interaction) {
        $this->conversationHistory->addUserMessage($interaction['user_message']);
        $this->conversationHistory->addAssistantMessage($interaction['system_response']);
      }

      if ($this->debug && !empty($recentInteractions)) {
        $this->securityLogger->logSecurityEvent(
          "Loaded " . count($recentInteractions) . " recent interactions for user {$this->userId}",
          'info'
        );
      }

    } catch (\Exception $e) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Error loading recent history: " . $e->getMessage(),
          'error'
        );
      }
    }
  }

  /**
   * Returns the stored statistics.
   * 🆕 REFACTORED: Délègue à MemoryStatistics
   *
   * @return array
   */
  public function getStats(): array
  {
    return $this->memoryStats->getStats();
  }

  /**
   * Set the last entity mentioned in conversation
   * 🆕 NEW METHOD: Délègue à EntityTracker
   *
   * @param int $entityId Entity ID
   * @param string $entityType Entity type (product, category, order, etc.)
   * @param string|null $entityName Entity name (optional, for context enrichment)
   * @return void
   */
  public function setLastEntity(int $entityId, string $entityType, ?string $entityName = null): void
  {
    $this->entityTracker->setLastEntity($entityId, $entityType, $entityName);
    
    // Update local properties for backward compatibility
    $this->lastEntityId = $entityId;
    $this->lastEntityType = $entityType;
  }

  /**
   * Get the last entity mentioned in conversation
   * 🆕 NEW METHOD: Délègue à EntityTracker
   *
   * @return array|null Array with 'id' and 'type', or null if no entity
   */
  public function getLastEntity(): ?array
  {
    return $this->entityTracker->getLastEntity();
  }

  /**
   * Clear the last entity from memory
   *
   * @return void
   */
  public function clearLastEntity(): void
  {
    // Get entity info before clearing for logging
    $previousEntity = $this->entityTracker->getLastEntity();
    
    $this->entityTracker->clearLastEntity();
    
    // Also clear local properties for backward compatibility
    $this->lastEntityId = null;
    $this->lastEntityType = null;
    
    if ($this->debug) {
      $this->securityLogger->logStructured(
        'info',
        'ConversationMemory',
        'entity_cleared',
        [
          'previous_entity_id' => $previousEntity['id'] ?? null,
          'previous_entity_type' => $previousEntity['type'] ?? null,
          'user_id' => $this->userId,
          'language_id' => $this->languageId,
          'timestamp' => date('Y-m-d H:i:s'),
          'reason' => 'context_switch',
          'note' => 'Last entity cleared from memory due to context switch'
        ]
      );
    }
  }


  /**
   * **DEPRECATED**: Use addInteraction() instead.
   * This method is kept for backward compatibility but now delegates to addInteraction().
   * 🆕 REFACTORED: Délègue à addInteraction()
   *
   * @param string $userMessage User's message
   * @param string $systemResponse System's response
   * @param array $metadata Additional metadata
   * @return bool Success of the operation
   */
  public function addInteractionWithSplitting(string $userMessage, string $systemResponse, array $metadata = [] ): bool
  {
    // Delegate to the refactored addInteraction() method
    return $this->addInteraction($userMessage, $systemResponse, $metadata);
  }



  /**
   * Groups search result documents (chunks) by their 'interaction_id'.
   * Also handles single (non-chunked) interactions.
   *
   * @param \LLPhant\Embeddings\Document[] $documents Search results from the vector store.
   * @return array Grouped results keyed by interaction ID, sorted by best similarity score.
   */
  private function groupChunksByInteraction(array $documents): array
  {
    $grouped = [];

    foreach ($documents as $doc) {
      if (!$doc instanceof Document) {
        continue; // Skip invalid entries
      }

      $metadata = $doc->metadata ?? [];
      $isChunked = $metadata['is_chunked'] ?? false;
      $score = $metadata['score'] ?? 0; // Assuming the score is stored in metadata upon retrieval

      if ($isChunked) {
        $interactionId = $metadata['interaction_id'] ?? 'unknown_chunked_' . uniqid();

        // Initialize group entry if it doesn't exist
        if (!isset($grouped[$interactionId])) {
          $grouped[$interactionId] = [
            'is_chunked' => true,
            'chunks' => [],
            'metadata' => $metadata, // Store metadata from the first chunk found
            'best_score' => 0.0,
          ];
        }

        // Add the current chunk detail
        $grouped[$interactionId]['chunks'][] = [
          'content' => $doc->content,
          'chunk_index' => $metadata['chunk_index'] ?? 0,
          'score' => $score,
        ];

        // Keep track of the best score found for this interaction
        if ($score > $grouped[$interactionId]['best_score']) {
          $grouped[$interactionId]['best_score'] = $score;
        }

      } else {
        // Handle single (non-chunked) interaction
        $interactionId = $metadata['interaction_id'] ?? 'single_' . uniqid();

        // Store non-chunked interaction directly
        $grouped[$interactionId] = [
          'is_chunked' => false,
          'content' => $doc->content,
          'metadata' => $metadata,
          'best_score' => $score,
        ];
      }
    }

    // Sort by best score in descending order
    uasort($grouped, function(array $a, array $b) {
      // Use floating point comparison
      return $b['best_score'] <=> $a['best_score'];
    });

    return $grouped;
  }

  /**
   * Reconstructs complete interactions from grouped results (chunks or singles).
   *
   * @param array $groupedResults Results grouped by interaction_id and sorted by score.
   * @param int $limit Max number of complete interactions to return.
   * @return array Formatted and reconstructed interactions.
   */
  private function reconstructInteractions(array $groupedResults, int $limit): array
  {
    $formatted = [];
    $count = 0;

    foreach ($groupedResults as $interactionId => $interaction) {
      if ($count >= $limit) {
        break;
      }

      $metadata = $interaction['metadata'];
      $bestScore = $interaction['best_score'];
      $isReconstructed = $interaction['is_chunked'];

      if ($interaction['is_chunked']) {
        // Sort chunks by index before concatenation
        usort($interaction['chunks'], function(array $a, array $b) {
          return $a['chunk_index'] <=> $b['chunk_index'];
        });

        // Reconstruct the full content by concatenating chunks
        $fullContent = '';
        foreach ($interaction['chunks'] as $chunk) {
          $fullContent .= $chunk['content'] . "\n";
        }

        $formatted[] = [
          'user_message' => $metadata['user_message'] ?? 'N/A',
          'system_response' => $metadata['system_response'] ?? 'N/A',
          'timestamp' => $metadata['timestamp'] ?? 0,
          'agent_type' => $metadata['agent_type'] ?? 'unknown',
          'similarity_score' => $bestScore,
          'is_reconstructed' => $isReconstructed,
          'chunk_count' => count($interaction['chunks']),
          'full_content' => trim($fullContent), // Optionally include for debugging
        ];

      } else {
        // Handle single interaction
        $formatted[] = [
          'user_message' => $metadata['user_message'] ?? 'N/A',
          'system_response' => $metadata['system_response'] ?? 'N/A',
          'timestamp' => $metadata['timestamp'] ?? 0,
          'agent_type' => $metadata['agent_type'] ?? 'unknown',
          'similarity_score' => $bestScore,
          'is_reconstructed' => $isReconstructed,
          'full_content' => $interaction['content'] ?? 'N/A', // The full content is just the single document content
        ];
      }

      $count++;
    }

    return $formatted;
  }




  /**
   * Clears the short-term conversational memory.
   * 🆕 REFACTORED: Délègue à ShortTermMemoryManager
   */
  public function clearShortTermMemory(): void
  {
    $this->shortTermManager->clearHistory();

    // Update local reference for compatibility
    $this->conversationHistory = $this->shortTermManager->getConversationHistory();
  }

  /**
   * Clears the entire conversation context (short-term memory and entity tracking).
   * This creates a fresh context for a new conversation.
   * 
   * Note: Long-term memory (vector store) is NOT cleared as it contains historical data
   * that may be useful for future conversations.
   * 
   * @return void
   */
  public function clearContext(): void
  {
    // Clear short-term memory (conversation history)
    $this->clearShortTermMemory();
    
    // Clear entity tracking
    $this->clearLastEntity();
    
    // Reset statistics
    $this->stats = [];
    
    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "ConversationMemory: Context cleared for user {$this->userId}",
        'info'
      );
    }
  }

  /**
   * Gets statistics related to interaction chunking.
   *
   * NOTE: This method is conceptual. To get accurate stats, it requires specific database queries
   * against the vector store table, which must be implemented based on the actual table structure.
   *
   * @return array Chunking statistics.
   */
  public function getChunkingStats(): array
  {
    $stats = [
      'total_interactions' => $this->stats['interactions_stored'] ?? 0,
      'chunked_interactions_count' => 0, // Number of unique interaction_ids that were chunked
      'single_interactions_count' => 0, // Number of unique interaction_ids that were not chunked
      'total_chunks_stored' => 0, // Total number of documents with 'is_chunked' = true
      'avg_chunks_per_interaction' => 0.0,
      // 'db_queries_required' => 'Yes, requires database querying based on metadata fields',
    ];

    // Using the simplified statistics available internally
    $stats['total_interactions'] = $this->stats['interactions_stored'] ?? 0;

    return $stats;
  }

  /**
   * Records user feedback for a specific interaction
   * 
   * @param string $interactionId Unique identifier of the interaction
   * @param string $feedbackType Type of feedback: 'positive', 'negative', or 'correction'
   * @param array $feedbackData Additional feedback data (e.g., corrected text, rating, comment)
   * @return bool Success of the operation
   */
  public function recordFeedback(string $interactionId, string $feedbackType, array $feedbackData = []): bool
  {
    try {
      error_log("ConversationMemory::recordFeedback - START");
      error_log("ConversationMemory::recordFeedback - Interaction ID: {$interactionId}");
      error_log("ConversationMemory::recordFeedback - Feedback Type: {$feedbackType}");
      
      // Validate feedback type
      $validTypes = ['positive', 'negative', 'correction'];
      if (!in_array($feedbackType, $validTypes, true)) {
        error_log("ConversationMemory::recordFeedback - INVALID TYPE: {$feedbackType}");
        $this->securityLogger->logSecurityEvent(
          "Invalid feedback type: {$feedbackType}. Must be one of: " . implode(', ', $validTypes),
          'warning'
        );
        return false;
      }

      // Prepare feedback metadata
      // some are anor used : to check
      $feedbackMetadata = [
        'interaction_id' => $interactionId,
        'feedback_type' => $feedbackType,
        'feedback_data' => $feedbackData,
        'user_id' => $this->userId,
        'timestamp' => time(),
        'language_id' => $this->languageId,
      ];

      error_log("ConversationMemory::recordFeedback - Metadata prepared");

      // Store feedback in database using ClicShopping's save method
      $feedbackRecord = [
        'interaction_id' => $interactionId,
        'feedback_type' => $feedbackType,
        'feedback_data' => json_encode($feedbackData),
        'user_id' => $this->userId,
        'timestamp' => $feedbackMetadata['timestamp'],
        'language_id' => $this->languageId,
        'date_added' => date('Y-m-d H:i:s')
      ];

      error_log("ConversationMemory::recordFeedback - Record prepared: " . json_encode($feedbackRecord));
      error_log("ConversationMemory::recordFeedback - Calling db->save()");

      $success = DoctrineOrm::insert('rag_feedback', $feedbackRecord);
      
      error_log("ConversationMemory::recordFeedback - Save result: " . ($success ? 'TRUE' : 'FALSE'));

      if ($success) {
        // Update quality metrics based on feedback
        $this->updateQualityMetrics($feedbackType);

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Feedback recorded: {$feedbackType} for interaction {$interactionId}",
            'info'
          );
        }
      }

      return $success;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error recording feedback: " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }

  /**
   * Get feedback context for a query
   * Retrieves relevant corrections and positive feedback to improve responses
   *
   * @param string $query Query text
   * @param int $maxResults Maximum number of feedback items to retrieve
   * @return array Feedback context data
   */
  public function getFeedbackContext(string $query, int $maxResults = 3): array
  {
    try {
      // Use FeedbackManager to get relevant feedback
      // Convert userId to int (it's stored as string but FeedbackManager expects int)
      $userIdInt = is_numeric($this->userId) ? (int)$this->userId : 0;
      
      return $this->feedbackManager->getRelevantFeedbackForLearning(
        $userIdInt,
        $this->languageId,
        $maxResults
      );

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error retrieving feedback context: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }

  /**
   * Creates an embedding generator compatible with LLPhant
   * 
   * @return EmbeddingGeneratorInterface
   */
  private function createEmbeddingGenerator(): EmbeddingGeneratorInterface
  {
    return new class implements EmbeddingGeneratorInterface
    {
      public function embedText(string $text): array
      {
        $generator = NewVector::gptEmbeddingsModel();
        if (!$generator) {
          throw new \RuntimeException('Embedding generator not initialized.');
        }
        return $generator->embedText($text);
      }

      public function embedDocument(Document $document): Document
      {
        $document->embedding = $this->embedText($document->content);
        return $document;
      }

      public function embedDocuments(array $documents): array
      {
        return array_map($this->embedDocument(...), $documents);
      }

      public function getEmbeddingLength(): int
      {
        return NewVector::getEmbeddingLength();
      }
    };
  }

  /**
   * Updates quality metrics based on feedback
   * 
   * @param string $feedbackType Type of feedback received
   * @return void
   */
  private function updateQualityMetrics(string $feedbackType): void
  {
    // Initialize metrics if not exists
    if (!isset($this->stats['feedback_metrics'])) {
      $this->stats['feedback_metrics'] = [
        'positive_count' => 0,
        'negative_count' => 0,
        'correction_count' => 0,
        'total_feedback' => 0,
        'positive_rate' => 0.0,
      ];
    }

    // Update counts
    $this->stats['feedback_metrics']['total_feedback']++;
    
    switch ($feedbackType) {
      case 'positive':
        $this->stats['feedback_metrics']['positive_count']++;
        break;
      case 'negative':
        $this->stats['feedback_metrics']['negative_count']++;
        break;
      case 'correction':
        $this->stats['feedback_metrics']['correction_count']++;
        break;
    }

    // Calculate positive rate
    $total = $this->stats['feedback_metrics']['total_feedback'];
    if ($total > 0) {
      $positive = $this->stats['feedback_metrics']['positive_count'];
      $this->stats['feedback_metrics']['positive_rate'] = round(($positive / $total) * 100, 2);
    }
  }


  //**********
  // Not used - to check
  //**********

  /**
   * Gets recent interactions from conversation history
   * 
   * @param int $limit Maximum number of interactions to return
   * @return array Array of recent interactions with user_message and system_response
   */
  public function getRecentInteractions(int $limit = 5): array
  {
    try {
      // Get recent messages from ShortTermMemoryManager
      $recentMessages = $this->shortTermManager->getRecentMessages($limit * 2); // Get double to account for user+assistant pairs
      
      $interactions = [];
      $userMessage = null;
      
      // Pair user messages with assistant responses
      foreach ($recentMessages as $message) {
        $role = 'user'; // Default role
        if (isset($message->role)) {
          $role = $message->role;
        } elseif (property_exists($message, 'role')) {
          try {
            $role = $message->role;
          } catch (\Error $e) {
            // Role not initialized, use default
            $role = 'user';
          }
        }
        
        if ($role === 'user') {
          $userMessage = $message->content ?? '';
        } elseif ($role === 'assistant' && $userMessage !== null) {
          $interactions[] = [
            'user_message' => $userMessage,
            'system_response' => $message->content ?? '',
            'timestamp' => time(), // Approximate timestamp
          ];
          $userMessage = null;
          
          // Stop if we have enough interactions
          if (count($interactions) >= $limit) {
            break;
          }
        }
      }
      
      return array_reverse($interactions); // Return in chronological order
      
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error getting recent interactions: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }








  /**
   * Gets the complete LLPhant ConversationHistory object.
   *
   * This method provides backward compatibility by exposing the underlying
   * ConversationHistory object managed by ShortTermMemoryManager. It allows
   * code that expects direct access to the ConversationHistory to continue
   * working after the SubConversationMemory refactoring.
   *
   * Purpose:
   * - Provides access to the LLPhant ConversationHistory for backward compatibility
   * - Used internally to maintain consistency after delegating to ShortTermMemoryManager
   * - Allows external code to access conversation history without breaking changes
   *
   * Note: This method is kept for backward compatibility. New code should prefer
   * using the higher-level methods like getRelevantContext() or getRecentInteractions()
   * which provide more structured access to conversation data.
   *
   * @return ConversationHistory The LLPhant ConversationHistory object
   */

  public function getConversationHistory(): ConversationHistory
  {
    return $this->conversationHistory;
  }

  /**
   * Retrieves the last successfully executed SQL query from conversation history.
   *
   * This method is critical for the "modify last query" functionality in AnalyticsAgent,
   * allowing users to request modifications to their previous SQL queries without
   * having to repeat the entire query.
   *
   * Purpose:
   * - Enables SQL query modification requests (e.g., "add column X to the last query")
   * - Used by AnalyticsAgent to detect and handle modification requests
   * - Searches conversation history for the most recent SQL query response
   * - Extracts SQL from markdown-formatted responses (```sql ... ```)
   *
   * Usage Example:
   * User: "Show me all products"
   * System: [Returns SQL query in response]
   * User: "Add the price column to that query"
   * System: [Uses getLastSQLQuery() to retrieve and modify the previous query]
   *
   * Implementation Details:
   * - Searches rag_interactions table for recent SQL responses
   * - Filters by user_id and language_id for context isolation
   * - Extracts SQL from markdown code blocks (```sql ... ```)
   * - Returns null if no SQL query found in recent history
   *
   * @return string|null The last SQL query or null if none found
   */
  public function getLastSQLQuery(): ?string
  {
    try {
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      $sql = "
        SELECT system_response
        FROM {$prefix}rag_interactions
        WHERE user_id = :user_id
        AND language_id = :language_id
        AND system_response LIKE '%SELECT%'
        AND system_response LIKE '%```sql%'
        ORDER BY date_added DESC
        LIMIT 1
      ";
      
      $result = DoctrineOrm::selectOne($sql, [
        'user_id' => (int)$this->userId,
        'language_id' => (int)$this->languageId
      ]);
      
      if ($result) {
        $response = $result['system_response'];
        
        // Extraire la requête SQL de la réponse (format markdown)
        if (preg_match('/```sql\s*(.*?)\s*```/s', $response, $matches)) {
          $sqlQuery = trim($matches[1]);
          
          if ($this->debug) {
            $this->securityLogger->logSecurityEvent(
              "Retrieved last SQL query: " . substr($sqlQuery, 0, 100) . "...",
              'info'
            );
          }
          
          return $sqlQuery;
        }
      }
      
      return null;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error getting last SQL query: " . $e->getMessage(),
        'error'
      );
      return null;
    }
  }
}
