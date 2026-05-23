<?php
/**
 * AJAX Endpoint: Get Agent Alerts
 * 
 * Retrieves system alerts, overdue objectives, and systematic issues
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 * 
 * @date 2026-01-28
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\HTTP;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

define('PAGE_PARSE_START_TIME', microtime());
define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . '/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');

AdministratorAdmin::hasUserAccess();

header('Content-Type: application/json');

try {
    $db = Registry::get('Db');
    
    // Get overdue objectives
    $overdueQuery = $db->prepare("SELECT * 
                                  FROM :table_rag_agent_objectives
                                  WHERE status IN ('active', 'approved')
                                  AND estimated_completion_time > 0
                                  AND TIMESTAMPADD(SECOND, estimated_completion_time, created_at) < NOW()
                                  ORDER BY created_at ASC
                                  LIMIT 50
                                 ");
    $overdueQuery->execute();
    $overdueObjectives = $overdueQuery->fetchAll(\PDO::FETCH_ASSOC);
    
    // Get systematic issues (agents with consistently low scores)
    $systematicIssuesSql = "SELECT 
                                producer_agent_id,
                                COUNT(*) as evaluation_count,
                                AVG(overall_score) as avg_score,
                                MIN(overall_score) as min_score,
                                MAX(overall_score) as max_score
                            FROM :table_rag_agent_critic_evaluations
                            WHERE evaluated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            AND producer_agent_id IS NOT NULL
                            GROUP BY producer_agent_id
                            HAVING AVG(overall_score) < 0.6 AND COUNT(*) >= 5
                            ORDER BY avg_score ASC";
    
    $systematicStmt = $db->prepare($systematicIssuesSql);
    $systematicStmt->execute();
    
    $systematicIssues = [];
    while ($row = $systematicStmt->fetch(\PDO::FETCH_ASSOC)) {
        $systematicIssues[] = [
            'agent_id' => $row['producer_agent_id'],
            'evaluation_count' => (int)$row['evaluation_count'],
            'avg_score' => round((float)$row['avg_score'], 3),
            'min_score' => round((float)$row['min_score'], 3),
            'max_score' => round((float)$row['max_score'], 3),
            'severity' => (float)$row['avg_score'] < 0.4 ? 'critical' : 'warning'
        ];
    }
    
    // Get failed consensus sessions
    $failedConsensusSql = "SELECT * 
                           FROM :table_rag_agent_weight_consensus
                           WHERE ABS(difference) > 0.05
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                           ORDER BY created_at DESC
                           LIMIT 20";
    
    $failedConsensusStmt = $db->prepare($failedConsensusSql);
    $failedConsensusStmt->execute();
    
    $failedConsensus = [];
    while ($row = $failedConsensusStmt->fetch(\PDO::FETCH_ASSOC)) {
        $failedConsensus[] = [
            'session_id' => $row['id'],
            'output_id' => $row['evaluation_id'],
            'participating_agents' => [],
            'initial_scores' => [],
            'created_at' => $row['created_at']
        ];
    }
    
    // Get recent objective failures
    $failedObjectivesSql = "SELECT * 
                            FROM :table_rag_agent_objectives
                            WHERE status = 'failed'
                            AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                            ORDER BY completed_at DESC
                            LIMIT 20";
    
    $failedObjectivesStmt = $db->prepare($failedObjectivesSql);
    $failedObjectivesStmt->execute();
    
    $failedObjectives = [];
    while ($row = $failedObjectivesStmt->fetch(\PDO::FETCH_ASSOC)) {
        $failedObjectives[] = [
            'objective_id' => $row['objective_id'],
            'agent_id' => $row['agent_id'],
            'goal_statement' => $row['goal_statement'],
            'priority' => $row['priority'],
            'failure_reason' => $row['failure_reason'],
            'completed_at' => $row['completed_at']
        ];
    }
    
    // Calculate alert summary
    $alertSummary = [
        'overdue_objectives' => count($overdueObjectives),
        'systematic_issues' => count($systematicIssues),
        'failed_consensus' => count($failedConsensus),
        'failed_objectives' => count($failedObjectives),
        'total_alerts' => count($overdueObjectives) + count($systematicIssues) + count($failedConsensus) + count($failedObjectives)
    ];
    
    // Format response
    $response = [
        'success' => true,
        'data' => [
            'summary' => $alertSummary,
            'overdue_objectives' => $overdueObjectives,
            'systematic_issues' => $systematicIssues,
            'failed_consensus' => $failedConsensus,
            'failed_objectives' => $failedObjectives
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => CLICSHOPPING::getDef('module_chatgpt_debug_mode') === 'True' ? $e->getTraceAsString() : null
    ], JSON_PRETTY_PRINT);
}
exit;
