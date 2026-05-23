<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Memory\EntityTypeRegistry;
use ClicShopping\AI\DomainsAI\CoreAI\Entity\EntityHelper;
use LLPhant\Chat\Message;

/**
 * ContextResolver Class
 *
 * Responsible for detecting and resolving contextual references in queries.
 * Separated from ConversationMemory to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Detect contextual references ("it", "this", "le", "la", etc.)
 * - Replace references with actual entities
 * - Extract entities from conversational context
 * - Support multilingual patterns (FR/EN)
 * - Dynamic entity type discovery via EntityTypeRegistry
 */

class ContextResolver
{
  private SecurityLogger $logger;
  private bool $debug;
  private int $languageId;
  private int $maxContextWindow = 5; // Max messages to analyze for context
  private EntityTypeRegistry $entityRegistry;
  private ?EntityTracker $entityTracker = null; 
  
  /**
   * Cached pattern bypass check result
   * 
   * Cache the result once in constructor instead of checking repeatedly
   * 
   * @var bool True if Pure LLM mode (patterns disabled), False if Pattern mode
   */
  private bool $usePureLlmMode;

  /**
   * Constructor
   *
   * @param int $languageId Language ID for pattern selection
   * @param bool $debug Enable debug logging
   * @param EntityTracker|null $entityTracker Optional EntityTracker for contextual references
   */
  public function __construct(int $languageId = 1, bool $debug = false, ?EntityTracker $entityTracker = null)
  {
    $this->languageId = $languageId;
    $this->debug = $debug;
    $this->logger = new SecurityLogger();
    $this->entityRegistry = EntityTypeRegistry::getInstance();
    $this->entityTracker = $entityTracker; 
    
    // This eliminates 8 repeated checks throughout the class
    $this->usePureLlmMode = !defined('USE_PATTERN_BASED_DETECTION') || USE_PATTERN_BASED_DETECTION === 'False';

    if ($this->debug) {
      $trackerStatus = $entityTracker !== null ? 'with EntityTracker' : 'without EntityTracker';
      $this->logger->logSecurityEvent(
        "ContextResolver initialized for languageId={$languageId} with dynamic entity types {$trackerStatus}",
        'info'
      );
    }
  }

  /**
   * Detect contextual references in a query
   * 
   * 
   * Examples of contextual references:
   * - "it", "this", "that" (demonstrative pronouns)
   * - "previous", "last" (temporal references)
   * - "same", "similar" (comparative references)
   * 
   * CRITICAL: Pattern-based detection is DISABLED when USE_PATTERN_BASED_DETECTION = 'False'
   * 
   * Issue: Pattern '/\b(and)\s+(\w+)\b/i' was incorrectly matching queries like 
   * "Show model and price" which should NOT be contextual.
   * 
   * Solution: In Pure LLM mode, let the LLM decide if a query needs context based on
   * actual pronouns and conversation history, not broad patterns like "and".
   *
   * @deprecated Pattern-based detection will be removed in Q2 2026. See LLM-based alternative
   *             in kiro_documentation/2025_12_22/implicit_context_pattern_removal.md
   * @see kiro_documentation/2025_12_22/implicit_context_pattern_removal.md
   * @see kiro_documentation/2026_01_02/pattern_removal_roadmap.md (Phase 4.5)
   *
   * @param string $query Query to analyze
   * @return bool True if references detected
   */
  public function detectContextualReferences(string $query): bool
  {
    // In Pure LLM mode, patterns should NOT be used for contextual reference detection
    if ($this->usePureLlmMode) {
      // Pure LLM mode: Pattern-based contextual reference detection is DISABLED
      // Let the LLM decide if context is needed based on actual conversation analysis
      // Patterns like '/\b(and)\s+(\w+)\b/i' cause false positives
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Contextual reference pattern detection DISABLED (Pure LLM mode): {$query}",
          'info'
        );
      }
      
      return false;
    }
    
    // Legacy pattern-based detection (only when patterns explicitly enabled)
    // @deprecated This approach causes false positives and will be removed in future
    // Pattern detection disabled in Pure LLM mode - return empty array
    $referencePatterns = [];

    foreach ($referencePatterns as $pattern) {
      if (preg_match($pattern, $query)) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Contextual reference detected (PATTERN mode): {$query}",
            'info'
          );
        }
        return true;
      }
    }

    return false;
  }

  /**
   * Detect implicit contextual queries (queries that need context but don't have pronouns)
   * 
   * 
   * Examples of implicit contextual queries:
   * - "compare avec les concurrents" (needs product from previous query)
   * - "show more details" (needs entity from previous query)
   * - "what's the price" (needs product from previous query)
   * 
   * CRITICAL: Pattern-based detection is DISABLED when USE_PATTERN_BASED_DETECTION = 'False'
   * 
   * Issue: Pattern '/^(?:what|how much|tell me|show|give).*\b(price|cost|pricing)\b(?!.*\b(?:of|for)\s+\w+)/i'
   * was incorrectly matching queries like "Show model and price" which should return ALL products,
   * not just the last product from context.
   * 
   * Solution: In Pure LLM mode, let the LLM decide if context is needed based on conversation
   * history and query semantics, not rigid patterns.
   * 
   * ENGLISH ONLY: All patterns are in English as per design.
   * Query must be translated to English before calling this method.
   *
   * @deprecated Pattern-based detection will be removed in Q2 2026. See LLM-based alternative
   *             in doc/2025_12_22/implicit_context_pattern_removal.md
   * @see doc/2025_12_22/IMPLICIT_CONTEXT_PATTERN_ISSUE.md
   * @see doc/2025_12_22/implicit_context_pattern_removal.md
   * @see doc/2026_01_02/pattern_removal_roadmap.md (Phase 4.5)
   *
   * @param string $query Query to analyze (MUST BE IN ENGLISH)
   * @return bool True if implicit context needed
   */
  public function detectImplicitContextualQuery(string $query): bool
  {
    // In Pure LLM mode, patterns should NOT be used for implicit context detection
    if ($this->usePureLlmMode) {
      // Pure LLM mode: Pattern-based implicit context detection is DISABLED
      // Let the LLM decide if context is needed based on conversation history
      // and query semantics, not rigid patterns that cause false positives
      
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Implicit context pattern detection DISABLED (Pure LLM mode): {$query}",
          'info'
        );
      }
      
      return false;
    }
    
    // Legacy pattern-based detection (only when patterns explicitly enabled)
    // @deprecated This approach causes false positives and will be removed in future
    // See: kiro_documentation/2025_12_22/IMPLICIT_CONTEXT_PATTERN_ISSUE.md
    // Pattern detection disabled in Pure LLM mode - return empty array
    $implicitContextPatterns = [];

    foreach ($implicitContextPatterns as $pattern) {
      if (preg_match($pattern, $query)) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Implicit contextual query detected (PATTERN mode): {$query}",
            'info'
          );
        }
        return true;
      }
    }

    return false;
  }

  /**
   * Replace references in query with actual entities
   *
   * ENGLISH ONLY: As per HybridQueryProcessor design:
   * "All detection and processing logic should operate in English for consistency 
   * in a multilingual context."
   * 
   * Translation from user's language to English must happen BEFORE this method.
   * 
   *
   * @param string $query Query with references (MUST BE IN ENGLISH)
   * @param array $entities Extracted entities from context
   * @return string Query with resolved references
   */
  public function replaceReferences(string $query, array $entities): string
  {
    $resolvedQuery = $query;

    $lastEntityRef = $entities['last_entity_tracked'] ?? null;

    // Replace demonstrative pronouns (ENGLISH ONLY)
    if (!empty($lastEntityRef)) {
      $resolvedQuery = preg_replace(
        '/\b(it|this|that)\b/i',
        $lastEntityRef,
        $resolvedQuery,
        1 // Replace only first occurrence
      );
    }

    // Replace "the last one" (ENGLISH ONLY)
    if (!empty($lastEntityRef)) {
      $resolvedQuery = preg_replace(
        '/\b(the last one)\b/i',
        $lastEntityRef,
        $resolvedQuery
      );
    }

    // Replace "the previous" (ENGLISH ONLY)
    if (!empty($lastEntityRef)) {
      $resolvedQuery = preg_replace(
        '/\b(the previous)\b/i',
        $lastEntityRef,
        $resolvedQuery
      );
    }

    if ($this->debug && $resolvedQuery !== $query) {
      $this->logger->logSecurityEvent(
        "References resolved: '{$query}' → '{$resolvedQuery}'",
        'info'
      );
    }

    return $resolvedQuery;
  }

  /**
   * Extract entities from conversational context
   *
   * DYNAMIC APPROACH: Uses EntityTypeRegistry to discover entity types automatically
   * instead of hardcoded list.
   * 
   * ENGLISH ONLY: All extraction patterns are in English as per HybridQueryProcessor design.
   * Message content must be in English before calling this method.
   *
   * @param array $messages Array of Message objects (content MUST BE IN ENGLISH)
   * @return array Extracted entities
   */
  public function extractEntitiesFromContext(array $messages): array
  {
    // DYNAMIC: Get entity types from registry instead of hardcoded list
    $entities = $this->entityRegistry->getEntityTypesStructure();

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Extracting entities with dynamic types: " . implode(', ', array_keys($entities)),
        'info'
      );
    }

    // Limit to recent messages
    $recentMessages = array_slice($messages, -$this->maxContextWindow);

    foreach ($recentMessages as $message) {
      // check if the content is initialized
      if (!isset($message->content) || empty($message->content)) {
        continue;
      }
      
      $content = $message->content;

      // DYNAMIC EXTRACTION: Extract all entity types discovered by EntityTypeRegistry
      $allEntityTypes = $this->entityRegistry->getAllEntityTypes();
      
      foreach ($allEntityTypes as $entityType) {
        // Skip system tables that shouldn't be included in context resolution
        // IMPORTANT: Use correct table names with '_embedding' suffix for embedding tables
        // See docs/RAG_TABLE_NAMING_CONVENTION.md for complete documentation
        if (in_array($entityType, [
          'rag_conversation_memory_embedding',  // Embedding table: conversation history
          'rag_correction_patterns_embedding',  // Embedding table: correction patterns
          'rag_web_cache_embedding',            // Embedding table: web cache
          'rag_memory_retention_log'            // System table: retention logs (no embedding)
        ], true)) {
          continue;
        }
        
        // Convert entity type to singular for pattern matching using EntityHelper
        // e.g., "products" -> "product", "categories" -> "category"
        $singularType = EntityHelper::getSingularForm($entityType);
        
        // Build dynamic pattern for this entity type
        // Matches: "product 123", "product #456", "product ABC-123"
        $pattern = '/\b(?:' . preg_quote($singularType, '/') . ')[\s#:]*(\d+|[A-Z0-9-]+)\b/i';
        
        if (preg_match_all($pattern, $content, $matches)) {
          if (!isset($entities[$entityType]) || !is_array($entities[$entityType])) {
            $entities[$entityType] = [];
          }
          // Merge and reassign
          $entities[$entityType] = array_merge($entities[$entityType], $matches[1]);
          
          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Extracted {$entityType}: " . implode(', ', $matches[1]),
              'info'
            );
          }
        }
        
        // Also try with entity type name directly
        // Matches: "supplier ABC Corp", "manufacturer XYZ Inc"
        if (in_array($entityType, ['suppliers', 'manufacturers', 'categories'], true)) {
          $namePattern = '/\b(?:' . preg_quote($singularType, '/') . ')\s+(["\']?)([^"\']+)\1/i';
          
          if (preg_match_all($namePattern, $content, $matches)) {
            if (!isset($entities[$entityType])) {
              $entities[$entityType] = [];
            }
            $entities[$entityType] = array_merge($entities[$entityType], $matches[2]);
          }
        }
      }

      // Extract numbers (prices, quantities, etc.) (ENGLISH ONLY)
      if (preg_match_all('/\b(\d+(?:[.,]\d+)?)\s*(?:€|EUR|USD|CAD|\$|units?)\b/i', $content, $matches)) {
        $entities['numbers'] = array_merge($entities['numbers'], $matches[1]);
      }

      // Extract time ranges (ENGLISH ONLY)
      if (preg_match_all('/\b(last|past|previous)\s+(\d+)\s+(day|week|month|year)s?\b/i', $content, $matches)) {
        $matchCount = count($matches[0]);
        for ($i = 0; $i < $matchCount; $i++) {
          $entities['time_ranges'][] = [
            'quantity' => (int)($matches[2][$i] ?? 1),
            'unit' => $matches[3][$i] ?? 'day',
          ];
        }
      }
    }

    if ($this->entityTracker !== null) {
      $lastTracked = $this->entityTracker->getLastTrackedEntity();
      $entities['last_entity_tracked'] = $lastTracked['reference'];
      
      if ($this->debug && $lastTracked['reference'] !== null) {
        $this->logger->logSecurityEvent(
          "Last entity from EntityTracker: {$lastTracked['reference']}",
          'info'
        );
      }
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Extracted entities from context: " . json_encode(array_keys(array_filter($entities))),
        'info'
      );
    }

    return $entities;
  }
}
