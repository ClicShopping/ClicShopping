<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


/**
 * MCP Health Check Stream
 *
 * This script provides a Server-Sent Events (SSE) stream for real-time health checks
 * of the MCP (Modular Content Platform) system. It continuously sends health status updates
 * every 3 seconds.
 *
 * Usage:
 * - Access this script via a web browser or an SSE-capable client.
 * - The client will receive JSON-encoded health data as events.
 *
 * Note:
 * - Ensure that the server supports SSE and that output buffering is disabled.
 * - Adjust the sleep duration as needed for your application's requirements.
 */
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\McpHealth;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\MCPConnector;
use ClicShopping\OM\CLICSHOPPING;
use \ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

// Include the necessary core files
define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../../Core/ClicShopping/') . '/');


require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

// Initialize the application environment
CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');

AdministratorAdmin::hasUserAccess();

// Set the appropriate headers for Server-Sent Events
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Content-Encoding: none');

// Keep the connection open for SSE
set_time_limit(0);
ignore_user_abort(true);

// Disable output buffering/compression for real-time streaming
if (function_exists('apache_setenv')) {
  @apache_setenv('no-gzip', '1');
  @apache_setenv('dont-vary', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
  ob_end_flush();
}
@ob_implicit_flush(true);

// Avoid session locking during SSE
if (session_status() === PHP_SESSION_ACTIVE) {
  session_write_close();
}

// Disable output buffering to allow real-time streaming
if (ob_get_level() > 0) {
  ob_end_clean();
}

/**
 * Sends a single Server-Sent Event.
 *
 * @param string $event The event name.
 * @param array $data The data to be sent, will be JSON-encoded.
 * @return void
 */
function sendEvent(string $event, array $data): void
{
  echo "event: " . $event . "\n";
  echo "data: " . json_encode($data, JSON_UNESCAPED_SLASHES) . "\n\n";
  flush();
}

try {
  // If a specific MCP server is requested, update connector config for this stream
  $requestedMcpId = isset($_GET['mcp_id']) ? (int)$_GET['mcp_id'] : null;
  if (!empty($requestedMcpId)) {
    $config = MCPConnector::getConfigDb($requestedMcpId);
    MCPConnector::getInstance()->updateConfig($config);
  }

  // Send an initial event to confirm the stream is open
  sendEvent('healthcheck', [
    'status' => 'unknown',
    'message' => 'Health stream opened. Waiting for MCP response...',
    'timestamp' => date('Y-m-d H:i:s'),
    'details' => []
  ]);

  // Get the MCP Health service
  $mcpHealth = McpHealth::getInstance();
  $counter = 0;

  // Loop to continuously send health data
  while (true) {
    try {
      $healthData = $mcpHealth->check();
      // Ensure the data is an array before sending
      if (!is_array($healthData)) {
        $healthData = ['status' => 'error', 'message' => 'Invalid data from health check.'];
      }
    } catch (Exception $e) {
      // If an error occurs, send a structured error message
      $healthData = ['status' => 'error', 'message' => 'Health check failed: ' . $e->getMessage()];
    }

    sendEvent('healthcheck', $healthData);
    sleep(3);
  }
} catch (Exception $e) {
  // Log and send any errors that occur
  sendEvent('error', ['message' => $e->getMessage()]);
  exit;
}
