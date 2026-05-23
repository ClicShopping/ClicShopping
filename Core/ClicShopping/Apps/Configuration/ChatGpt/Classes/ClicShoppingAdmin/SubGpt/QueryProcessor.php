<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\AI\CoreAI\Orchestrator\OrchestratorAgent;
use ClicShopping\AI\Infrastructure\Metrics\StatisticsTracker;

/**
 * QueryProcessor
 *
 * Processes queries using the orchestrator agent.
 * Extracted from chatGpt.php AJAX handler as part of code refactoring (Task 12).
 *
 * Responsibilities:
 * - Process query with orchestrator
 * - Handle timeout checks
 * - Extract response data
 * - Error handling
 */
class QueryProcessor
{
  /**
   * Process query with orchestrator agent
   *
   * @param string $query User query
   * @param int $userId User ID
   * @param int $languageId Language ID
   * @param StatisticsTracker $statsTracker Statistics tracker instance
   * @return array AI response with success, data, intent, agent_used keys
   * @throws \Exception If orchestrator fails
   */
  public static function process(string $query, int $userId, int $languageId, StatisticsTracker $statsTracker): array
  {
    $orchestrator = new OrchestratorAgent($userId, $languageId);
    $aiResponse = $orchestrator->processWithValidation($query);
    
    // Ensure aiResponse has required structure
    if (!is_array($aiResponse)) {
      $statsTracker->setError('orchestrator_error', 'Invalid response format');
      throw new \Exception('Erreur orchestrateur: Invalid response format');
    }
    
    if (!isset($aiResponse['success']) || !$aiResponse['success']) {
      $statsTracker->setError('orchestrator_error', $aiResponse['error'] ?? 'Unknown error');
      throw new \Exception($aiResponse['error'] ?? 'Erreur orchestrateur');
    }
    
    // Record agent and classification info
    $statsTracker
      ->setAgentType($aiResponse['agent_used'] ?? 'unknown')
      ->setClassificationType($aiResponse['intent']['type'] ?? 'unknown')
      ->setConfidence($aiResponse['intent']['confidence'] ?? 0)
      ->setApiInfo('openai', CLICSHOPPING_APP_CHATGPT_CH_MODEL ?? 'gpt-5-mini');
    
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log('[INFO : SUCCESS] ORCHESTRATOR PROCESSED SUCCESSFULLY');
      error_log('   Agent Used: ' . ($aiResponse['agent_used'] ?? 'unknown'));
      error_log('   Intent Type: ' . ($aiResponse['intent']['type'] ?? 'unknown'));
      error_log('   Confidence: ' . ($aiResponse['intent']['confidence'] ?? 0));
    }
    
    return $aiResponse;
  }
  
  /**
   * Check if query has exceeded timeout
   *
   * @param float $startTime Query start time (from microtime(true))
   * @param int $maxTime Maximum execution time in seconds
   * @return bool True if timeout exceeded
   */
  public static function checkTimeout(float $startTime, int $maxTime): bool
  {
    $elapsedTime = microtime(true) - $startTime;
    
    if ($elapsedTime >= $maxTime) {
      error_log('[INFO : TIME] TIMEOUT WARNING: Query took ' . round($elapsedTime, 2) . ' seconds (max: ' . $maxTime . ')');
      return true;
    }
    
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log('[INFO : TIME] TIMEOUT CHECK: Query took ' . round($elapsedTime, 2) . ' seconds (max: ' . $maxTime . ')');
    }
    
    return false;
  }
  
  /**
   * Handle orchestrator error
   *
   * @param \Exception $e Exception thrown by orchestrator
   * @return array Error response
   */
  public static function handleError(\Exception $e): array
  {
    error_log('[INFO : ERROR] ORCHESTRATOR ERROR: ' . $e->getMessage());
    
    return [
      'success' => false,
      'error' => $e->getMessage(),
      'error_code' => 'ORCHESTRATOR_ERROR',
      'interaction_id' => null
    ];
  }
}
