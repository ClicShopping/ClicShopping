<?php
/**
 * AJAX Endpoint: Get Agent Consensus Sessions
 * 
 * Retrieves consensus sessions with filtering support
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 * 
 * @date 2026-02-06
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

AdministratorAdmin::hasUserAccess();

header('Content-Type: application/json');

try {
    $CLICSHOPPING_Db = Registry::get('Db');
    
    // Get filter parameters
    $status = $_GET['status'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Build query
    $conditions = [];
    $params = [];
    
    if ($status) {
        $conditions[] = 'status = :status';
        $params[':status'] = $status;
    }
    
    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    // Get consensus sessions
    $sql = "SELECT 
                id,
                evaluation_id,
                dynamic_consensus,
                static_consensus,
                difference,
                created_at
            FROM :table_rag_agent_weight_consensus 
            {$whereClause}
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $CLICSHOPPING_Db->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
    $stmt->execute();
    
    $sessions = [];
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $sessions[] = [
            'id' => (int)$row['id'],
            'evaluation_id' => $row['evaluation_id'],
            'dynamic_consensus' => (float)$row['dynamic_consensus'],
            'static_consensus' => (float)$row['static_consensus'],
            'difference' => (float)$row['difference'],
            'difference_percent' => (float)$row['difference'] * 100,
            'created_at' => $row['created_at']
        ];
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total
                FROM :table_rag_agent_weight_consensus 
                {$whereClause}";
    $countStmt = $CLICSHOPPING_Db->prepare($countSql);
    
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    
    $countStmt->execute();
    $countRow = $countStmt->fetch(\PDO::FETCH_ASSOC);
    $totalCount = (int)$countRow['total'];
    
    // Format response
    $response = [
        'success' => true,
        'data' => $sessions,
        'total_count' => $totalCount,
        'limit' => $limit,
        'offset' => $offset
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
