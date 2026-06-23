<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\RequestValidator;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ContextManager;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\EntityExtractor;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\MemoryManager;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ResponseFormatter;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\StatisticsManager;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\ResponseValidator;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt\QueryProcessor;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\AI\Infrastructure\Metrics\StatisticsTracker;
use ClicShopping\AI\Security\LlmGuardrails;
use ClicShopping\AI\Security\LlmResponseEvaluator;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\ChatCritic\ChatCriticSeam;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;

// ============================================
// INITIALIZATION
// ============================================
define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();

CLICSHOPPING::loadSite('ClicShoppingAdmin');

header('Content-Type: application/json; charset=UTF-8');
AdministratorAdmin::hasUserAccess();

try {
  Gpt::getEnvironment();
} catch (Exception $e) {
  error_log("Warning: Could not load ChatGPT app/environment: " . $e->getMessage());
}

if (defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True') {
  try {
    // ============================================
    // 1. CONFIGURE TIMEOUT
    // ============================================
    $enableTimeout = true;
    $maxExecutionTime = defined('CLICSHOPPING_APP_CHATGPT_RA_MAX_EXECUTION_TIME') ? CLICSHOPPING_APP_CHATGPT_RA_MAX_EXECUTION_TIME : 120; // Default to 120 seconds if constant not defined

    RequestValidator::configureTimeout($maxExecutionTime, $enableTimeout);
    $queryStartTime = microtime(true);

    // ============================================
    // 2. VALIDATE REQUEST
    // ============================================
    $validation = RequestValidator::validateRequest($_POST);

    if (!$validation['valid']) {
      if (ob_get_length()) ob_clean();
      echo json_encode($validation['error'], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $userQuery = $validation['query'];
    $userQueryDisplay = $validation['query_display'];

    // ============================================
    // 3. GET USER CONTEXT
    // ============================================
    $language = Registry::get('Language');
    $languageId = $language->getId();
    $userId = AdministratorAdmin::getUserAdminId();
    $sessionId = session_id();

    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      error_log('=== CHATGPT.PHP START  ===');
      error_log('User Query: ' . substr($userQuery, 0, 100));
      error_log('User ID: ' . $userId);
      error_log('Language ID: ' . $languageId);
    }

    // ============================================
    // 4. INITIALIZE SERVICES
    // ============================================
    $statsTracker = new StatisticsTracker($userId, $sessionId, $languageId);
    $statsTracker->startTracking();

    $memoryService = ContextManager::initializeMemoryService($userId, $languageId);

    // ============================================
    // 5. RETRIEVE CONTEXT
    // ============================================
    $context = ContextManager::retrieveContext($memoryService, $userQuery, 5);

    // ============================================
    // 6. PROCESS QUERY
    // ============================================
    $aiResponse = QueryProcessor::process($userQuery, $userId, $languageId, $statsTracker);

    // Check timeout
    if ($enableTimeout && RequestValidator::checkTimeout($queryStartTime, $maxExecutionTime)) {
      if (ob_get_length()) ob_clean();
      echo json_encode([
        'success' => false,
        'error' => 'La requête prend trop de temps, veuillez réessayer',
        'error_code' => 'QUERY_TIMEOUT',
        'timeout_seconds' => $maxExecutionTime,
        'interaction_id' => null,
        'timeout_error' => true
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    // ============================================
    // 7. EXTRACT ENTITY METADATA
    // ============================================
    $metadata = EntityExtractor::extractMetadata($aiResponse, $languageId);

    // ============================================
    // 8. RECORD IN MEMORY
    // ============================================
    MemoryManager::recordInteraction($memoryService, $userQuery, $aiResponse, $metadata);

    // Trigger migration every 10 calls
    static $callCounter = 0;
    MemoryManager::triggerMigration($memoryService, ++$callCounter, 10);

    // ============================================
    // 9. FORMAT RESPONSE
    // ============================================
    $memoryContext = ContextManager::isMemoryDisplayEnabled() ? $context : null;
    $formattedResponse = ResponseFormatter::format($aiResponse, $userQuery, $metadata, $memoryContext);
    $formatted = $formattedResponse['content'];

    // ============================================
    // 10. TRACK STATISTICS
    // ============================================
    $responseTime = $statsTracker->stopTracking();

    StatisticsManager::recordTokenUsage($statsTracker);

    $qualityVerdict = [];
    $guardrailsEval = LlmGuardrails::getLastEvaluation();
    if (is_array($guardrailsEval) && !isset($guardrailsEval['error'])) {
      $securityScore = $guardrailsEval['security_analysis']['overall_security_score'] ?? null;
      $aiResponse['metrics']['relevance_score'] = (float)($guardrailsEval['relevance'] ?? 0);
      $aiResponse['metrics']['security_score'] = $securityScore !== null ? (float)$securityScore : 0.8;
      $aiResponse['metrics']['hallucination_score'] = (float)($guardrailsEval['hallucination_risk'] ?? 0.1);
      $aiResponse['metrics']['response_quality'] = (float)($guardrailsEval['overall_score'] ?? 0);
      $aiResponse['metrics']['confidence_score'] = (float)($aiResponse['intent']['confidence'] ?? 0);

      // dark-launch: when the chat critic seam is enabled (OFF by code default), route the
      // verdict through the agnostic ActorCriticCoordinator (observe-only, verdict parity). Otherwise
      // keep the legacy direct path byte-identical.
      if (ChatCriticSeam::isEnabled()) {
        $seamAnswer = MemoryManager::extractResponseText($aiResponse) ?? $formatted;
        $qualityVerdict = ChatCriticSeam::evaluate((string)$seamAnswer, $guardrailsEval, new Context((string)$userId, $languageId, ['source' => 'chat']));
      } else {
        $qualityVerdict = LlmResponseEvaluator::deriveQualitySignal($guardrailsEval);
      }
      $statsTracker->setQualityVerdict($qualityVerdict);
    }

    $metrics = StatisticsManager::calculateFallbackMetrics(
      $aiResponse,
      $formattedResponse['data_to_format'],
      $formatted
    );

    // Observe-first flag surfaced to the chat panel: a discrete "to verify" badge shown only
    // when the LLM verdict is weak (overall<0.6 OR hallucination>=0.5 OR reliability<=2), so a
    // correct answer is not undermined by an alarming raw hallucination %. The user stays judge.
    $metrics['to_verify'] = (bool)($qualityVerdict['to_verify'] ?? false);

    $statsTracker->setQualityScores(
      $metrics['response_quality'] * 100,
      $metrics['security_score'] * 100
    );

    // ============================================
    // 11. PERSIST TO DATABASE
    // ============================================
    $responseText = MemoryManager::extractResponseText($aiResponse) ?? $formatted;

    // Mint the client-facing token BEFORE persistence so it is stored on rag_interactions
    // (client_interaction_id) and can later be joined to the user's feedback row
    // (rag_feedback.interaction_id = this token). The same value is returned to the browser below.
    $clientInteractionId = 'interaction_' . $userId . '_' . time() . '_' . substr(md5(uniqid()), 0, 8);

    $interactionData = StatisticsManager::buildInteractionData(
      $userQuery,
      $responseText,
      $aiResponse,
      $metadata,
      $userId,
      $sessionId,
      $languageId,
      $responseTime,
      $metrics,
      $statsTracker,
      $clientInteractionId
    );

    $dbInteractionId = StatisticsManager::persistInteraction($interactionData, $statsTracker);
    $statsSaved = StatisticsManager::saveStatistics($statsTracker, $dbInteractionId);

    // Log critical metrics
    error_log(sprintf(
      '[RAG] Query processed: type=%s, confidence=%.2f, agent=%s, time=%dms, stats_saved=%s',
      $aiResponse['intent']['type'] ?? 'unknown',
      $aiResponse['intent']['confidence'] ?? 0,
      $aiResponse['agent_used'] ?? 'unknown',
      $responseTime,
      $statsSaved ? 'YES' : 'NO'
    ));

    // ============================================
    // 12. BUILD JSON RESPONSE
    // ============================================
    // $clientInteractionId was minted above (step 11) and persisted on rag_interactions.

    $jsonResponse = [
      'success' => true,
      'interaction_id' => $clientInteractionId,
      'text_response' => nl2br($formatted),
      'user_query_display' => $userQueryDisplay,
      'type' => $aiResponse['intent']['type'] ?? 'semantic',
      'confidence' => $aiResponse['intent']['confidence'] ?? 0,
      'agent_used' => $aiResponse['agent_used'] ?? 'unknown',
      'execution_time' => $aiResponse['execution_time'] ?? 0,
      'entity_id' => $metadata['entity_id'],
      'entity_type' => $metadata['entity_type'],
      'language_id' => $languageId,
      'metrics' => $metrics,
      'metadata' => [
        'query' => $userQuery,
        'query_display' => $userQueryDisplay,
        'timestamp' => time(),
        'user_id' => $userId
      ]
    ];

    // ============================================
    // 13. VALIDATE & RETURN
    // ============================================
    $validation = ResponseValidator::validate($jsonResponse);

    if (!$validation['valid']) {
      error_log('[INFO : ALERT] RESPONSE VALIDATION FAILED:');
      foreach ($validation['errors'] as $error) {
        error_log('   - ' . $error);
      }
    }

    if (!empty($validation['warnings'])) {
      error_log('[INFO : ALERT] RESPONSE VALIDATION WARNINGS:');
      foreach ($validation['warnings'] as $warning) {
        error_log('   - ' . $warning);
      }
    }

    // Clean output buffer and return JSON
    if (ob_get_length()) ob_clean();
    echo json_encode($jsonResponse, JSON_UNESCAPED_UNICODE);

    } catch (\Exception $e) {
      error_log('[INFO : ERROR] AJAX HANDLER ERROR: ' . $e->getMessage());
      error_log('Stack trace: ' . $e->getTraceAsString());

      if (ob_get_length()) ob_clean();

      echo json_encode([
        'success' => false,
        'error' => 'Une erreur est survenue lors du traitement de votre requête',
        'error_code' => 'INTERNAL_ERROR',
        'error_details' => $e->getMessage(),
        'interaction_id' => null
      ], JSON_UNESCAPED_UNICODE);
    }
  } else {
    $rawInput = file_get_contents('php://input');
    $jsonInput = json_decode($rawInput, true);
    $jsonInput = HTML::sanitize($jsonInput);

    if ($jsonInput && isset($jsonInput['message'])) {
      $userQuery = trim($jsonInput['message']);
    } else {
      $userQuery = trim($_POST['message'] ?? $_GET['message'] ?? '');
    }

    RequestValidator::configureTimeout(30, false);
    $queryStartTime = microtime(true);

    if (Registry::exists('Language')) {
      $language = Registry::get('Language');
      $language->loadDefinitions('ecommerce');
      $language->getDef('text_rag_security_analysis');
    }

    $model = CLICSHOPPING_APP_CHATGPT_CH_MODEL;

    $response = GPT::callWithModel($userQuery, $model);

    if (empty($userQuery)) {
      if (ob_get_length()) ob_clean();
      echo json_encode([
        'success' => false,
        'error' => 'Please add a question',
        'error_code' => 'EMPTY_QUERY',
        'interaction_id' => null,
        'validation_error' => true
      ], JSON_UNESCAPED_UNICODE);
      exit;
    }

    $textResponse = $response['text_response']
      ?? $response['response']
      ?? $response['interpretation']
      ?? $response['raw_response']
      ?? '';

    $jsonResponse = [
      'success' => true,
      'interaction_id' => 'interaction_' . time() . '_' . substr(md5(uniqid()), 0, 8),
      'text_response' => is_string($textResponse) ? $textResponse : '',
      'type' => $response['response_type'] ?? ($response['type'] ?? 'semantic'),
      'confidence' => $response['confidence'] ?? 0,
      'parse_error' => $response['parse_error'] ?? null
    ];

    if (ob_get_length()) ob_clean();
    echo json_encode($jsonResponse, JSON_UNESCAPED_UNICODE);
    exit;
  }
