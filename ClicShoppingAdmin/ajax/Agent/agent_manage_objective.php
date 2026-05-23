<?php
/**
 * AJAX Endpoint: Manage Agent Objective
 * 
 * Handles objective approval, cancellation, and status updates
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

try {
    $db = Registry::get('Db');
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new \Exception('Invalid request data');
    }
    
    $objectiveId = $input['objective_id'] ?? null;
    $action = $input['action'] ?? null;
    $reason = $input['reason'] ?? '';
    
    if (!$objectiveId || !$action) {
        throw new \Exception('Missing required parameters: objective_id and action');
    }
    
    // Get objective
    $query = $db->prepare("SELECT * FROM :table_rag_agent_objectives WHERE objective_id = :objective_id");
    $query->bindValue('objective_id', $objectiveId);
    $query->execute();
    $objective = $query->fetch(\PDO::FETCH_ASSOC);
    
    if (!$objective) {
        throw new \Exception('Objective not found: ' . $objectiveId);
    }
    
    // Handle different actions
    $message = '';
    $newStatus = null;
    $updateFields = [];
    $logTransition = true;
    
    switch ($action) {
        case 'approve':
            $newStatus = 'approved';
            $updateFields['approved_at'] = date('Y-m-d H:i:s');
            $message = 'Objective approved successfully';
            break;
            
        case 'cancel':
            $newStatus = 'cancelled';
            $updateFields['failure_reason'] = $reason ?: 'Cancelled by administrator';
            $updateFields['completed_at'] = date('Y-m-d H:i:s');
            $message = 'Objective cancelled successfully';
            break;
            
        case 'activate':
            $newStatus = 'active';
            $updateFields['started_at'] = date('Y-m-d H:i:s');
            $message = 'Objective activated successfully';
            break;
            
        case 'complete':
            $newStatus = 'completed';
            $updateFields['completed_at'] = date('Y-m-d H:i:s');
            if (isset($input['metrics'])) {
                $updateFields['metrics'] = json_encode($input['metrics']);
            }
            $message = 'Objective marked as completed';
            break;
            
        case 'fail':
            $newStatus = 'failed';
            $updateFields['failure_reason'] = $reason ?: 'Failed by administrator';
            $updateFields['completed_at'] = date('Y-m-d H:i:s');
            $message = 'Objective marked as failed';
            break;
            
        case 'retry':
            $newStatus = 'approved';
            $updateFields['approved_at'] = date('Y-m-d H:i:s');
            $updateFields['started_at'] = null;
            $updateFields['completed_at'] = null;
            $updateFields['failure_reason'] = null;
            $updateFields['metrics'] = null;
            $message = 'Objective reset for retry';
            break;
            
        case 'escalate':
            $newStatus = $objective['status'];
            $updateFields['priority'] = 'critical';
            $message = 'Objective escalated to critical priority';
            $logTransition = false;
            break;
            
        default:
            throw new \Exception('Invalid action: ' . $action);
    }
    
    // Update objective
    $updateSql = "UPDATE :table_rag_agent_objectives  SET status = :status";
    $params = ['status' => $newStatus, 'objective_id' => $objectiveId];
    
    foreach ($updateFields as $field => $value) {
        $updateSql .= ", $field = :$field";
        $params[$field] = $value;
    }
    
    $updateSql .= " WHERE objective_id = :objective_id";
    
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute($params);
    
    if ($logTransition && $objective['status'] !== $newStatus) {
        $transitionSql = "INSERT INTO :table_rag_agent_objective_state_transitions 
                          (objective_id, old_status, new_status, transition_reason, transitioned_at)
                          VALUES (:objective_id, :old_status, :new_status, :transition_reason, NOW())";
        
        $transitionStmt = $db->prepare($transitionSql);
        $transitionStmt->execute([
            'objective_id' => $objectiveId,
            'old_status' => $objective['status'],
            'new_status' => $newStatus,
            'transition_reason' => $reason ?: "Action: $action"
        ]);
    }
    
    // Get updated objective
    $updatedQuery = $db->prepare("SELECT * FROM :table_rag_agent_objectives
                                  WHERE objective_id = :objective_id");
    $updatedQuery->bindValue('objective_id', $objectiveId);
    $updatedQuery->execute();
    $updatedObjective = $updatedQuery->fetch(\PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'objective' => $updatedObjective
        ]
    ], JSON_PRETTY_PRINT);
    
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
