<?php
/**
 * AJAX Endpoint: Get Agent Objectives
 * 
 * Retrieves agent objectives with filtering and pagination support
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

AdministratorAdmin::hasUserAccess();

header('Content-Type: application/json');

function normalizeObjectiveRow(array $row): array
{
    foreach (['success_criteria', 'metrics'] as $field) {
        if (isset($row[$field]) && is_string($row[$field])) {
            $decoded = json_decode($row[$field], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row[$field] = $decoded;
            }
        }
    }

    return $row;
}

try {
    // Get database connection
    $db = Registry::get('Db');
    
    // Get filter parameters
    $agentId = $_GET['agent_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $priority = $_GET['priority'] ?? null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $objectiveId = $_GET['objective_id'] ?? null;
    
    // If specific objective requested
    if ($objectiveId) {
        $query = $db->prepare("SELECT * FROM :table_rag_agent_objectives 
	                             WHERE objective_id = :objective_id
			                         ");
        $query->bindValue('objective_id', $objectiveId);
        $query->execute();
        $objectives = $query->fetchAll(\PDO::FETCH_ASSOC);
        $objectives = array_map('normalizeObjectiveRow', $objectives);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'objectives' => $objectives,
                'total_count' => count($objectives),
                'limit' => $limit,
                'offset' => $offset
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Build WHERE clause
    $where = [];
    $params = [];
    
    if ($agentId) {
        $where[] = "agent_id = :agent_id";
        $params['agent_id'] = $agentId;
    }
    if ($status) {
        $where[] = "status = :status";
        $params['status'] = $status;
    }
    if ($priority) {
        $where[] = "priority = :priority";
        $params['priority'] = $priority;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total count
    $countQuery = $db->prepare("SELECT COUNT(*) as total FROM 
                                :table_rag_agent_objectives 
				$whereClause
				");
    $countQuery->execute($params);
    $totalCount = $countQuery->fetch(\PDO::FETCH_ASSOC)['total'];
    
    // Get objectives
    $query = $db->prepare("
        SELECT * FROM :table_rag_agent_objectives 
        $whereClause 
        ORDER BY created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $key => $value) {
        $query->bindValue(":$key", $value);
    }
    $query->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $query->bindValue(':offset', $offset, \PDO::PARAM_INT);
    
    $query->execute();
    $objectives = $query->fetchAll(\PDO::FETCH_ASSOC);
    $objectives = array_map('normalizeObjectiveRow', $objectives);
    
    // Format response
    $response = [
        'success' => true,
        'data' => [
            'objectives' => $objectives,
            'total_count' => (int)$totalCount,
            'limit' => $limit,
            'offset' => $offset
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
