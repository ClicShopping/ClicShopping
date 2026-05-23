<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use \ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
// The McpMonitor class is no longer needed in this script.
// use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\McpMonitor;

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . '/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');

AdministratorAdmin::hasUserAccess();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Add your user access check here.
  $CLICSHOPPING_Db = Registry::get('Db');

  // Clear alerts from the database.
  // The 'truncate table' command is very fast.
  $Qdelete = $CLICSHOPPING_Db->prepare('truncate table :table_mcp_alerts');
  $success = $Qdelete->execute();

  // The call to clear in-memory alerts has been removed because it is unnecessary
  // and causing the fatal error.

  header('Content-Type: application/json');
  echo json_encode([
    'success' => $success,
    'message' => $success ? 'Alerts cleared successfully' : 'Failed to clear alerts'
  ]);
  exit;
} else {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid request method']);
  exit;
}