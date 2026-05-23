<?php
/**
 * AJAX Endpoint: Get Evaluation Statistics
 * 
 * Retrieves evaluation statistics including score distributions and consensus sessions
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
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

header('Content-Type: application/json');

AdministratorAdmin::hasUserAccess();

function normalizeJsonObject($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (!is_string($value) || $value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    return [];
}

try {
    $db = Registry::get('Db');
    
    // Get score distribution
    $scoreSql = "SELECT 
                    CASE 
                        WHEN overall_score >= 0.9 THEN 'Excellent (0.9-1.0)'
                        WHEN overall_score >= 0.7 THEN 'Good (0.7-0.9)'
                        WHEN overall_score >= 0.5 THEN 'Fair (0.5-0.7)'
                        ELSE 'Poor (0.0-0.5)'
                    END as score_range,
                    COUNT(*) as count
                 FROM :table_rag_agent_critic_evaluations
                 GROUP BY score_range
                 ORDER BY MIN(overall_score) DESC";
    
    $scoreStmt = $db->prepare($scoreSql);
    $scoreStmt->execute();
    
    $scoreDistribution = [];
    while ($row = $scoreStmt->fetch()) {
        $scoreDistribution[] = [
            'range' => $row['score_range'],
            'count' => (int)$row['count']
        ];
    }
    
    // Get average scores by dimension
    $avgSql = "SELECT 
                    AVG(accuracy_score) as avg_accuracy,
                    AVG(completeness_score) as avg_completeness,
                    AVG(efficiency_score) as avg_efficiency,
                    AVG(clarity_score) as avg_clarity,
                    AVG(overall_score) as avg_overall
               FROM :table_rag_agent_critic_evaluations";
    
    $avgStmt = $db->prepare($avgSql);
    $avgStmt->execute();
    $avgRow = $avgStmt->fetch();
    
    $averageScores = [
        'accuracy' => round((float)$avgRow['avg_accuracy'], 3),
        'completeness' => round((float)$avgRow['avg_completeness'], 3),
        'efficiency' => round((float)$avgRow['avg_efficiency'], 3),
        'clarity' => round((float)$avgRow['avg_clarity'], 3),
        'overall' => round((float)$avgRow['avg_overall'], 3)
    ];
    
    // Get evaluation count by output type
    $typeSql = "SELECT output_type, COUNT(*) as count
                FROM :table_rag_agent_critic_evaluations
                GROUP BY output_type
                ORDER BY count DESC";
    
    $typeStmt = $db->prepare($typeSql);
    $typeStmt->execute();
    
    $evaluationsByType = [];
    while ($row = $typeStmt->fetch()) {
        $evaluationsByType[] = [
            'output_type' => $row['output_type'],
            'count' => (int)$row['count']
        ];
    }
    
    // Get consensus session statistics
    $consensusSql = "SELECT 
                        COUNT(*) as total_sessions,
                        AVG(dynamic_consensus) as avg_dynamic,
                        AVG(static_consensus) as avg_static,
                        AVG(difference) as avg_difference,
                        SUM(CASE WHEN ABS(difference) <= 0.05 THEN 1 ELSE 0 END) as reached_consensus
                     FROM :table_rag_agent_weight_consensus";
    
    $consensusStmt = $db->prepare($consensusSql);
    $consensusStmt->execute();
    $consensusRow = $consensusStmt->fetch();
    
    $consensusStats = [
        'total_sessions' => (int)$consensusRow['total_sessions'],
        'reached_consensus' => (int)$consensusRow['reached_consensus'],
        'failed_consensus' => (int)$consensusRow['total_sessions'] - (int)$consensusRow['reached_consensus'],
        'avg_dynamic_consensus' => round((float)$consensusRow['avg_dynamic'], 3),
        'avg_static_consensus' => round((float)$consensusRow['avg_static'], 3),
        'avg_difference' => round((float)$consensusRow['avg_difference'], 3),
        'consensus_rate' => $consensusRow['total_sessions'] > 0 
            ? round(((int)$consensusRow['reached_consensus'] / (int)$consensusRow['total_sessions']) * 100, 1)
            : 0
    ];
    
    // Get recent consensus sessions
    $recentConsensusSql = "SELECT * 
                           FROM :table_rag_agent_weight_consensus
                           ORDER BY created_at DESC
                           LIMIT 10";
    
    $recentConsensusStmt = $db->prepare($recentConsensusSql);
    $recentConsensusStmt->execute();
    
    $recentConsensusSessions = [];
    while ($row = $recentConsensusStmt->fetch()) {
        $recentConsensusSessions[] = [
            'evaluation_id' => $row['evaluation_id'],
            'dynamic_consensus' => (float)$row['dynamic_consensus'],
            'static_consensus' => (float)$row['static_consensus'],
            'difference' => (float)$row['difference'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Get total evaluation count
    $totalSql = "SELECT COUNT(*) as total FROM :table_rag_agent_critic_evaluations";
    $totalStmt = $db->prepare($totalSql);
    $totalStmt->execute();
    $totalRow = $totalStmt->fetch();
    
    // Format response
    $response = [
        'success' => true,
        'data' => [
            'total_evaluations' => (int)$totalRow['total'],
            'score_distribution' => $scoreDistribution,
            'average_scores' => $averageScores,
            'evaluations_by_type' => $evaluationsByType,
            'consensus_stats' => $consensusStats,
            'recent_consensus_sessions' => $recentConsensusSessions
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => CLICSHOPPING::getDef('module_chatgpt_debug_mode') === 'True' ? $e->getTraceAsString() : null
    ], JSON_PRETTY_PRINT);
}
exit;
