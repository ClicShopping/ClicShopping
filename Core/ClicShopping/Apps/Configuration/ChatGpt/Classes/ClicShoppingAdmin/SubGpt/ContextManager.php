<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\AI\CoreAI\Memory\MemoryRetentionService;

/**
 * ContextManager
 *
 * Manages context retrieval and memory service for chat interactions.
 * Extracted from chatGpt.php AJAX handler as part of code refactoring (Task 12).
 *
 * Responsibilities:
 * - Initialize memory service
 * - Retrieve multi-level context (working, short-term, long-term)
 * - Manage context lifecycle
 * - Transform context for display
 */
class ContextManager
{
  /**
   * Initialize memory retention service
   *
   * @param int $userId User ID
   * @param int $languageId Language ID
   * @return MemoryRetentionService
   */
  public static function initializeMemoryService(int $userId, int $languageId): MemoryRetentionService
  {
    $memoryService = new MemoryRetentionService($userId, $languageId);
    
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log('💾 MEMORY SERVICE INITIALIZED');
      error_log('   User ID: ' . $userId);
      error_log('   Language ID: ' . $languageId);
    }
    
    return $memoryService;
  }
  
  /**
   * Retrieve multi-level context for query
   *
   * @param MemoryRetentionService $memoryService Memory service instance
   * @param string $query User query
   * @param int $limit Maximum number of context items to retrieve
   * @return array Context data with working_memory, short_term, long_term keys
   */
  public static function retrieveContext(MemoryRetentionService $memoryService, string $query, int $limit = 5): array
  {
    $context = $memoryService->retrieveContext($query, $limit);
    
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log('CONTEXT RETRIEVED:');
      error_log('   Working Memory: ' . (empty($context['working_memory']) ? 'No OK' : 'OK'));
      error_log('   Short-term: ' . count($context['short_term']) . ' items');
      error_log('   Long-term: ' . count($context['long_term']) . ' items');
    }
    
    return $context;
  }
  
  /**
   * Transform context for display in ResultFormatter
   *
   * @param array $context Raw context from retrieveContext()
   * @return array Transformed context with has_context flag
   */
  public static function transformContextForDisplay(array $context): array
  {
    $memoryContext = [
      'short_term_context' => $context['short_term'] ?? [],
      'long_term_context' => $context['long_term'] ?? [],
      'feedback_context' => [], // Feedback context not yet in retrieveContext
      'has_context' => !empty($context['short_term']) || !empty($context['long_term']),
    ];
    
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log('🔄 MEMORY CONTEXT TRANSFORMED FOR DISPLAY:');
      error_log('   Short-term: ' . count($memoryContext['short_term_context']));
      error_log('   Long-term: ' . count($memoryContext['long_term_context']));
      error_log('   Feedback: ' . count($memoryContext['feedback_context']));
      error_log('   Has context: ' . ($memoryContext['has_context'] ? 'YES' : 'NO'));
    }
    
    return $memoryContext;
  }
  
  /**
   * Get working memory from context
   *
   * @param array $context Context data
   * @return array Working memory items
   */
  public static function getWorkingMemory(array $context): array
  {
    return $context['working_memory'] ?? [];
  }
  
  /**
   * Check if memory context display is enabled
   *
   * @return bool True if memory context should be displayed
   */
  public static function isMemoryDisplayEnabled(): bool
  {
    return defined('CLICSHOPPING_APP_CHATGPT_RA_DISPLAY_MEMORY_CONTEXT') 
        && CLICSHOPPING_APP_CHATGPT_RA_DISPLAY_MEMORY_CONTEXT === 'True';
  }
}
