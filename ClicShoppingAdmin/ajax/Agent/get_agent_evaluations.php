<?php
/**
 * AJAX Endpoint: Get Agent Evaluations
 * 
 * Retrieves agent evaluations with filtering and pagination support
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

function normalizeJsonArray($value): array
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

    return [$value];
}

try {
    $CLICSHOPPING_Db = Registry::get('Db');
    
    // Get filter parameters
    $criticId = $_GET['critic_id'] ?? null;
    $producerId = $_GET['producer_id'] ?? null;
    $outputType = $_GET['output_type'] ?? null;
    $evaluationId = $_GET['evaluation_id'] ?? null;
    $minScore = isset($_GET['min_score']) ? (float)$_GET['min_score'] : null;
    $maxScore = isset($_GET['max_score']) ? (float)$_GET['max_score'] : null;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Build query
    $conditions = [];
    $params = [];
    
    if ($evaluationId) {
        $conditions[] = 'evaluation_id = :evaluation_id';
        $params[':evaluation_id'] = $evaluationId;
    }
    if ($criticId) {
        $conditions[] = 'critic_id = :critic_id';
        $params[':critic_id'] = $criticId;
    }
    if ($producerId) {
        $conditions[] = 'producer_agent_id = :producer_id';
        $params[':producer_id'] = $producerId;
    }
    if ($outputType) {
        $conditions[] = 'output_type = :output_type';
        $params[':output_type'] = $outputType;
    }
    if ($minScore !== null) {
        $conditions[] = 'overall_score >= :min_score';
        $params[':min_score'] = $minScore;
    }
    if ($maxScore !== null) {
        $conditions[] = 'overall_score <= :max_score';
        $params[':max_score'] = $maxScore;
    }
    
    $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    
    // Get evaluations
    $sql = "SELECT * FROM :table_rag_agent_critic_evaluations 
            {$whereClause}
            ORDER BY evaluated_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $CLICSHOPPING_Db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
    $stmt->execute();
    
    $evaluations = [];
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $evaluations[] = [
            'evaluation_id' => $row['evaluation_id'],
            'critic_id' => $row['critic_id'],
            'producer_agent_id' => $row['producer_agent_id'] ?? null,
            'output_id' => $row['output_id'],
            'output_type' => $row['output_type'],
            'accuracy_score' => (float)$row['accuracy_score'],
            'completeness_score' => (float)$row['completeness_score'],
            'efficiency_score' => (float)$row['efficiency_score'],
            'clarity_score' => (float)$row['clarity_score'],
            'overall_score' => (float)$row['overall_score'],
            'feedback' => $row['feedback'],
            'strengths' => normalizeJsonArray($row['strengths'] ?? '[]'),
            'improvements' => normalizeJsonArray($row['improvements'] ?? '[]'),
            'evaluated_at' => $row['evaluated_at']
        ];
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total
                FROM :table_rag_agent_critic_evaluations
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
        'data' => [
            'evaluations' => $evaluations,
            'total_count' => $totalCount,
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
        'trace' => CLICSHOPPING::getDef('module_chatgpt_debug_mode') === 'True' ? $e->getTraceAsString() : null
    ], JSON_PRETTY_PRINT);
}
exit;
