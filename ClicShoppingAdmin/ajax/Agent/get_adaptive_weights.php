<?php
/**
 * AJAX Endpoint: Get Adaptive Weights
 * 
 * Retrieves adaptive weighting data
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

define('PAGE_PARSE_START_TIME', microtime());
define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . '/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');

header('Content-Type: application/json');

AdministratorAdmin::hasUserAccess();

try {
    $CLICSHOPPING_Db = Registry::get('Db');
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Get adaptive weights
    $sql = "SELECT * FROM :table_rag_agent_adaptive_weights 
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $CLICSHOPPING_Db->prepare($sql);
    $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
    $stmt->execute();
    
    $weights = [];
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $weights[] = [
            'id' => (int)$row['id'],
            'evaluation_id' => $row['evaluation_id'],
            'critic_id' => $row['critic_id'],
            'raw_weight' => (float)$row['raw_weight'],
            'normalized_weight' => (float)$row['normalized_weight'],
            'llm_explanation' => $row['llm_explanation'],
            'factor_analysis' => $row['factor_analysis'],
            'created_at' => $row['created_at']
        ];
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM :table_rag_agent_adaptive_weights";
    $countStmt = $CLICSHOPPING_Db->prepare($countSql);
    $countStmt->execute();
    $countRow = $countStmt->fetch(\PDO::FETCH_ASSOC);
    $totalCount = (int)$countRow['total'];
    
    // Calculate statistics
    $statsSql = "SELECT 
                    COUNT(*) as total,
                    AVG(normalized_weight) as avg_weight,
                    MIN(normalized_weight) as min_weight,
                    MAX(normalized_weight) as max_weight
                 FROM :table_rag_agent_adaptive_weights";
    $statsStmt = $CLICSHOPPING_Db->prepare($statsSql);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(\PDO::FETCH_ASSOC);
    
    // Format response
    $response = [
        'success' => true,
        'data' => [
            'weights' => $weights,
            'total_count' => $totalCount,
            'statistics' => [
                'total' => (int)$stats['total'],
                'avg_weight' => (float)$stats['avg_weight'],
                'min_weight' => (float)$stats['min_weight'],
                'max_weight' => (float)$stats['max_weight']
            ],
            'limit' => $limit,
            'offset' => $offset
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
exit;
