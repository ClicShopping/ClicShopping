<?php
/**
 * Run Weight Anomaly Scan AJAX Endpoint
 *
 * Triggers an on-demand LLM analysis of recent critic-weight history to detect
 * gaming/collusion patterns, persists the findings into rag_agent_weight_anomalies,
 * and returns a summary. The adaptive_weighting_dashboard panel reads the stored
 * anomalies on reload.
 *
 * State-mutating + LLM-backed: POST only, admin-authenticated, fail-closed.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightAnomalyDetector;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightAuditLogger;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\LLMPromptBuilder;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine\WeightingLlmClient;
use ClicShopping\AI\Infrastructure\Monitoring\AlertManager;

define('CLICSHOPPING_BASE_DIR', dirname(__DIR__, 3) . '/Core/ClicShopping/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');

AdministratorAdmin::hasUserAccess();

header('Content-Type: application/json');

// Fail-closed: POST only (this endpoint mutates and triggers a paid LLM call).
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Look-back window (days). Accept JSON body or form post; clamp to a sane range.
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $days = (int)($input['days'] ?? $_POST['days'] ?? 30);
    if ($days < 1) {
        $days = 1;
    } elseif ($days > 365) {
        $days = 365;
    }

    // Assemble the detector exactly like ActorCriticCoordinator builds the engine deps.
    $debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
        && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $errorLogPath = CLICSHOPPING_BASE_DIR . 'Work/Log/adaptive_weighting_errors.log';
    $maxRetries = 2;

    $detector = new WeightAnomalyDetector(
        new WeightAuditLogger(),
        new LLMPromptBuilder(),
        new WeightingLlmClient($maxRetries, $debug, $errorLogPath),
        new AlertManager(),
        $debug,
        $errorLogPath
    );

    $result = $detector->detectAnomalies($days);

    echo json_encode([
        'success' => true,
        'data' => [
            'analysis_id' => $result['analysis_id'] ?? null,
            'period_days' => $result['period_days'] ?? $days,
            'critics_analyzed' => $result['critics_analyzed'] ?? 0,
            'evaluations_analyzed' => $result['evaluations_analyzed'] ?? 0,
            'anomaly_count' => count($result['anomalies'] ?? []),
            'high_severity_count' => $result['high_severity_count'] ?? 0,
            'medium_severity_count' => $result['medium_severity_count'] ?? 0,
            'low_severity_count' => $result['low_severity_count'] ?? 0,
            'overall_assessment' => $result['overall_assessment'] ?? ''
        ]
    ], JSON_PRETTY_PRINT);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
exit;
