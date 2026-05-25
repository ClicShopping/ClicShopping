<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\AI\Infrastructure\Metrics\StatisticsTracker;

/**
 * StatisticsManager
 *
 * Manages statistics tracking and persistence for chat interactions.
 * Extracted from chatGpt.php AJAX handler as part of code refactoring (Task 12).
 *
 * Responsibilities:
 * - Track statistics
 * - Record metrics (tokens, costs, quality scores)
 * - Calculate fallback metrics
 * - Persist to database
 */
class StatisticsManager
{
  /**
   * Record token usage from GPT response
   *
   * @param StatisticsTracker $statsTracker Statistics tracker instance
   * @return void
   */
  public static function recordTokenUsage(StatisticsTracker $statsTracker): void
  {
    $tokenUsage = Gpt::getLastTokenUsage();
    
    if ($tokenUsage !== null) {
      $statsTracker->setTokens($tokenUsage['prompt_tokens'], $tokenUsage['completion_tokens']);
      
      error_log(sprintf(
        '📊 Tokens recorded: prompt=%d, completion=%d, total=%d',
        $tokenUsage['prompt_tokens'],
        $tokenUsage['completion_tokens'],
        $tokenUsage['total_tokens']
      ));
    } else {
      error_log('⚠️ No token usage data available from Gpt::getLastTokenUsage()');
    }
  }
  
  /**
   * Calculate fallback metrics from AI response
   *
   * @param array $aiResponse AI response from orchestrator
   * @param array $dataToFormat Formatted data
   * @param string $formatted Formatted HTML output
   * @return array Metrics with confidence_score, security_score, hallucination_score, response_quality, relevance_score keys
   */
  public static function calculateFallbackMetrics(array $aiResponse, array $dataToFormat, string $formatted): array
  {
    $confidenceScore = (float)($aiResponse['metrics']['confidence_score'] ?? ($aiResponse['intent']['confidence'] ?? 0));
    $intentType = $aiResponse['intent']['type'] ?? 'semantic';
    $hasUsefulData = isset($dataToFormat) && is_array($dataToFormat) 
                  && (!empty($dataToFormat['results']) || !empty($dataToFormat['response']) || !empty($dataToFormat['interpretation']));
    $responseLen = strlen($formatted);
    
    $securityScore = isset($aiResponse['metrics']['security_score']) 
      ? (float)$aiResponse['metrics']['security_score'] 
      : ($intentType === 'web_search' ? 0.5 : 0.8);
    
    $hallucinationScore = isset($aiResponse['metrics']['hallucination_score']) 
      ? (float)$aiResponse['metrics']['hallucination_score'] 
      : ($hasUsefulData ? 0.1 : ($intentType === 'semantic' ? 0.2 : 0.3));
    
    $responseQuality = isset($aiResponse['metrics']['response_quality']) 
      ? (float)$aiResponse['metrics']['response_quality'] 
      : ($responseLen > 800 ? 0.85 : ($responseLen > 300 ? 0.7 : 0.55));
    
    $relevanceScore = isset($aiResponse['metrics']['relevance_score']) 
      ? (float)$aiResponse['metrics']['relevance_score'] 
      : $confidenceScore;
    
    return [
      'confidence_score' => $confidenceScore,
      'security_score' => $securityScore,
      'hallucination_score' => $hallucinationScore,
      'response_quality' => $responseQuality,
      'relevance_score' => $relevanceScore
    ];
  }
  
  /**
   * Persist interaction to database
   *
   * @param array $interactionData Interaction data to persist
   * @param StatisticsTracker $statsTracker Statistics tracker instance
   * @return int|null Database interaction ID (null if failed)
   */
  public static function persistInteraction(array $interactionData, StatisticsTracker $statsTracker): ?int
  {
    $db = Registry::get('Db');
    $dbInteractionId = null;

    // Defensive truncation for rag_interactions.response / .question.
    //
    // The historical table was created with TEXT columns (65 535 bytes max);
    // a Hybrid WebSearch answer renders as ~140 KB of HTML, which made MariaDB
    // emit "SQLSTATE[22001]: 1406 Data too long for column 'response'" and
    // silently store a truncated answer (data loss in the audit trail).
    //
    // We cap the payload before the INSERT so the row succeeds even if the
    // schema migration to MEDIUMTEXT has not yet been applied on this
    // database. Override the cap with
    // CLICSHOPPING_APP_CHATGPT_RA_INTERACTION_RESPONSE_MAX_CHARS after running
    // the ALTER to MEDIUMTEXT (suggested value: 16_000_000).
    $interactionData = self::truncateOversizedColumns($interactionData);

    try {
      $db->save('rag_interactions', $interactionData);
      $dbInteractionId = (int)$db->lastInsertId();
      $statsTracker->setInteractionId($dbInteractionId);

      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log('💾 INTERACTION INSERTED:');
        error_log('   DB ID: ' . $dbInteractionId);
        error_log('   Question: ' . substr($interactionData['question'], 0, 50));
        error_log('   Answer length: ' . strlen($interactionData['response']));
        error_log('   Entity: ' . $interactionData['entity_type'] . ' (ID: ' . $interactionData['entity_id'] . ')');
        error_log('   Agent: ' . $interactionData['agent_used']);
        error_log('   Intent: ' . $interactionData['intent_type']);
        error_log('   Confidence: ' . $interactionData['confidence']);
      }
    } catch (\Exception $e) {
      error_log('[INFO : ERROR]Failed to insert interaction record: ' . $e->getMessage());
    }

    return $dbInteractionId;
  }

  /**
   * Cap the text columns that are at risk of exceeding the rag_interactions
   * schema. Adds a "...[truncated, original: N bytes]" marker when truncation
   * happens so an audit reader knows the row was clipped.
   */
  private static function truncateOversizedColumns(array $interactionData): array
  {
    $maxResponse = defined('CLICSHOPPING_APP_CHATGPT_RA_INTERACTION_RESPONSE_MAX_CHARS')
      ? (int) constant('CLICSHOPPING_APP_CHATGPT_RA_INTERACTION_RESPONSE_MAX_CHARS')
      : 65000; // TEXT-safe default (65 535 minus marker overhead)

    if ($maxResponse > 0) {
      foreach (['response', 'question'] as $col) {
        if (empty($interactionData[$col]) || !is_string($interactionData[$col])) {
          continue;
        }
        $value = $interactionData[$col];
        $length = strlen($value);
        if ($length > $maxResponse) {
          $marker = "\n\n[truncated for storage — original: {$length} bytes]";
          $interactionData[$col] = substr($value, 0, $maxResponse - strlen($marker)) . $marker;
          error_log(sprintf(
            "[WARN] StatisticsManager: truncated rag_interactions.%s from %d to %d bytes "
            . "(see CLICSHOPPING_APP_CHATGPT_RA_INTERACTION_RESPONSE_MAX_CHARS)",
            $col,
            $length,
            strlen($interactionData[$col])
          ));
        }
      }
    }

    return $interactionData;
  }
  
  /**
   * Save statistics to database
   *
   * @param StatisticsTracker $statsTracker Statistics tracker instance
   * @param int|null $dbInteractionId Database interaction ID
   * @return bool True if saved successfully
   */
  public static function saveStatistics(StatisticsTracker $statsTracker, ?int $dbInteractionId): bool
  {
    if ($dbInteractionId === null) {
      error_log('⚠️ STATISTICS NOT SAVED: missing interaction ID');
      return false;
    }
    
    try {
      $statsSaved = $statsTracker->save();
      
      if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
        error_log('[INFO : DATA] STATISTICS SAVED: ' . ($statsSaved ? 'YES' : 'NO'));
      }
      
      return $statsSaved;
    } catch (\Exception $e) {
      error_log('[INFO : ERROR]Failed to save statistics for interaction ' . $dbInteractionId . ': ' . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Build interaction data array for database persistence
   *
   * @param string $userQuery User query
   * @param string $responseText Response text
   * @param array $aiResponse AI response from orchestrator
   * @param array $metadata Entity metadata
   * @param int $userId User ID
   * @param string $sessionId Session ID
   * @param int $languageId Language ID
   * @param float $responseTime Response time in milliseconds
   * @param array $metrics Quality metrics
   * @param StatisticsTracker $statsTracker Statistics tracker instance
   * @return array Interaction data ready for database insertion
   */
  public static function buildInteractionData(
    string $userQuery,
    string $responseText,
    array $aiResponse,
    array $metadata,
    int $userId,
    string $sessionId,
    int $languageId,
    float $responseTime,
    array $metrics,
    StatisticsTracker $statsTracker
  ): array {
    $statsSnapshot = $statsTracker->getAllMetrics();
    
    $tokensUsed = $aiResponse['usage']['total_tokens']
      ?? $aiResponse['metrics']['tokens_used']
      ?? $statsSnapshot['tokens_total']
      ?? null;
    $tokensUsed = $tokensUsed !== null ? (int)$tokensUsed : null;
    
    $apiCostValue = $aiResponse['metrics']['api_cost']
      ?? $statsSnapshot['api_cost_usd']
      ?? null;
    $apiCostValue = $apiCostValue !== null ? round((float)$apiCostValue, 6) : null;
    
    $responseQualityScore = $metrics['response_quality'];
    $responseQualityValue = $responseQualityScore <= 1
      ? (int)round($responseQualityScore * 100)
      : (int)round($responseQualityScore);
    
    $resolvedAgentUsed = $aiResponse['agent_used'] ?? 'unknown';
    $resolvedIntentType = $aiResponse['intent']['type'] ?? 'unknown';
    
    return [
      'user_id' => $userId,
      'session_id' => $sessionId,
      'question' => $userQuery,
      'response' => $responseText,
      'request_type' => $resolvedIntentType,
      'confidence' => $aiResponse['intent']['confidence'] ?? 0,
      'response_quality' => $responseQualityValue,
      'response_time' => $responseTime ?? 0,
      'execution_time' => $aiResponse['execution_time'] ?? 0,
      'tokens_used' => $tokensUsed,
      'api_cost' => $apiCostValue,
      'language_id' => $languageId,
      'entity_id' => $metadata['entity_id'],
      'entity_type' => $metadata['entity_type'],
      'agent_used' => $resolvedAgentUsed,
      'intent_type' => $resolvedIntentType,
      'date_added' => 'now()',
    ];
  }
}
