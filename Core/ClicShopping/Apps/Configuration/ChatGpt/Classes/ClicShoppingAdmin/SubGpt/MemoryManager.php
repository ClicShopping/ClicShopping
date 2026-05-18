<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\AI\CoreAI\Memory\MemoryRetentionService;

/**
 * MemoryManager
 *
 * Manages memory recording and migration for chat interactions.
 * Extracted from chatGpt.php AJAX handler as part of code refactoring (Task 12).
 *
 * Responsibilities:
 * - Record interactions in memory
 * - Trigger automatic migration
 * - Manage memory lifecycle
 * - Extract response text for recording
 */
class MemoryManager
{
  /**
   * Record interaction in memory
   *
   * @param MemoryRetentionService $memoryService Memory service instance
   * @param string $query User query
   * @param array $aiResponse AI response from orchestrator
   * @param array $metadata Additional metadata (entity_id, entity_type, etc.)
   * @return void
   */
  public static function recordInteraction(
    MemoryRetentionService $memoryService,
    string $query,
    array $aiResponse,
    array $metadata
  ): void {
    $responseText = self::extractResponseText($aiResponse);
    
    $fullMetadata = array_merge([
      'source' => 'chat_ajax',
      'success' => true,
      'agent_used' => $aiResponse['agent_used'] ?? 'unknown',
      'query_type' => $aiResponse['intent']['type'] ?? $aiResponse['type'] ?? 'unknown',
      'intent_confidence' => $aiResponse['intent']['confidence'] ?? 0,
      'execution_time' => $aiResponse['execution_time'] ?? 0,
    ], $metadata);
    
    $memoryService->recordInteraction($query, $responseText, $fullMetadata);
    
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log('[INFO] INTERACTION RECORDED IN MEMORY');
      error_log('   Query Length: ' . strlen($query));
      error_log('   Response Length: ' . strlen($responseText));
      error_log('   Agent: ' . ($aiResponse['agent_used'] ?? 'unknown'));
    }
  }
  
  /**
   * Extract response text from AI response
   *
   * @param array $aiResponse AI response from orchestrator
   * @return string Response text
   */
  public static function extractResponseText(array $aiResponse): string
  {
    // Priority 1: Use text_response if available
    if (isset($aiResponse['text_response']) && !empty($aiResponse['text_response'])) {
      return $aiResponse['text_response'];
    }
    
    // Priority 2: Check data array
    if (isset($aiResponse['data']) && is_array($aiResponse['data'])) {
      return $aiResponse['data']['response'] 
          ?? $aiResponse['data']['interpretation']
          ?? $aiResponse['data']['message']
          ?? $aiResponse['data']['text_response']
          ?? json_encode($aiResponse['data']);
    }
    
    // Priority 3: Convert data to string
    if (isset($aiResponse['data'])) {
      return (string)$aiResponse['data'];
    }
    
    return 'Aucune réponse disponible';
  }
  
  /**
   * Trigger automatic migration from short-term to long-term memory
   *
   * @param MemoryRetentionService $memoryService Memory service instance
   * @param int $callCounter Current call counter
   * @param int $migrationInterval Trigger migration every N calls (default: 10)
   * @return int Number of interactions migrated (0 if not triggered)
   */
  public static function triggerMigration(
    MemoryRetentionService $memoryService,
    int $callCounter,
    int $migrationInterval = 10
  ): int {
    if ($callCounter % $migrationInterval === 0) {
      $migrated = $memoryService->migrateShortTermToLongTerm();
      
      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log('🔄 MIGRATION TRIGGERED: ' . $migrated . ' interactions migrated');
      }
      
      return $migrated;
    }
    
    return 0;
  }
  
  /**
   * Get memory statistics
   *
   * @param MemoryRetentionService $memoryService Memory service instance
   * @return array Memory statistics
   */
  public static function getMemoryStats(MemoryRetentionService $memoryService): array
  {
    // This would require additional methods in MemoryRetentionService
    // For now, return placeholder
    return [
      'short_term_count' => 0,
      'long_term_count' => 0,
      'working_memory_active' => false
    ];
  }
}
