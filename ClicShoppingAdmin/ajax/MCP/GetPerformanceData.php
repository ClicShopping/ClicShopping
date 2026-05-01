<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM)  at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\SimpleLogger;

use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\MCPConnector;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\McpMonitor;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . '/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');
AdministratorAdmin::hasUserAccess();

$mcpConnector = MCPConnector::getInstance();

$token = $_GET['token'] ?? $_GET['sessiontoken'] ?? null;
//var_dump($token);
//var_dump($mcpConnector->getSessionToken());

if ($token !== $mcpConnector->getSessionToken()) {
  http_response_code(403);
  echo json_encode(['error' => 'Invalid MCP token']);
  exit();
}

// Configuration des headers pour SSE
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

// Récupérer la plage de temps demandée
$range = isset($_GET['range']) ? HTML::sanitize($_GET['range']) : '24h';

// Récupérer l'ID du serveur MCP (all = tous les serveurs, ou un ID spécifique)
$mcpId = isset($_GET['mcp_id']) ? HTML::sanitize($_GET['mcp_id']) : 'all';

// Simulation controls (for testing alerts/UI)
$sim_latency_ms = isset($_GET['sim_latency']) ? (int)$_GET['sim_latency'] : 0;               // e.g. 1200
$sim_error_pct = isset($_GET['sim_error']) ? (float)$_GET['sim_error'] : null;               // e.g. 35.5
$sim_fail_rate = isset($_GET['sim_fail']) ? max(0.0, min(1.0, (float)$_GET['sim_fail'])) : 0; // 0..1 probability to emit error event
$sim_drop = isset($_GET['sim_drop']) ? filter_var($_GET['sim_drop'], FILTER_VALIDATE_BOOLEAN) : false; // randomly close connection
$sim_sleep_override = isset($_GET['sim_sleep']) ? (int)$_GET['sim_sleep'] : null;             // override loop sleep seconds

// Runtime alert thresholds (optional)
$threshold_error = isset($_GET['threshold_error']) ? (int)$_GET['threshold_error'] : null;      // percentage
$threshold_latency = isset($_GET['threshold_latency']) ? (int)$_GET['threshold_latency'] : null; // ms
$threshold_downtime = isset($_GET['threshold_downtime']) ? (int)$_GET['threshold_downtime'] : null; // seconds

if (!Registry::exists('SimpleLogger')) {
  $logger = new SimpleLogger('MCP_ClicShopping');
  Registry::set('Logger', $logger);
}

// Use real MCP monitor with real connection
// If specific MCP ID is requested, get that config, otherwise get first active
try {
  if ($mcpId !== 'all' && is_numeric($mcpId)) {
    $config = MCPConnector::getConfigDb((int)$mcpId);
    $monitor = McpMonitor::getInstance($config);
  } else {
    // For "all" servers, we'll aggregate data from all active servers
    $config = MCPConnector::getConfigDb();
    $monitor = McpMonitor::getInstance($config);
  }

  // Apply runtime thresholds if provided
  $thresholds = [];
  if ($threshold_error !== null) { $thresholds['error_rate'] = $threshold_error; }
  if ($threshold_latency !== null) { $thresholds['latency'] = $threshold_latency; }
  if ($threshold_downtime !== null) { $thresholds['downtime'] = $threshold_downtime; }
  if (!empty($thresholds)) {
    $monitor->setAlertThresholds($thresholds);
  }
} catch (\Exception $e) {
  // If MCP server is not running or connection fails, send ONE error message and stop
  // No error_log - just send message to client
  
  // Send a single, clear error message
  echo "event: server_not_running\n";
  echo "data: " . json_encode([
    'error' => 'MCP Server Not Running',
    'message' => 'The MCP server is not running. If you want to see statistics, please start an MCP server instance or create a connection.',
    'instructions' => [
      'Start the MCP server: cd mcp && node src/server.js',
      'Or check your MCP configuration in the database',
      'Verify server_host and server_port settings',
      'Works only if MCP server.js exist, not with LmStudio for example'
    ],
    'config' => [
      'host' => $config['server_host'] ?? 'unknown',
      'port' => $config['server_port'] ?? 'unknown'
    ]
  ]) . "\n\n";
  
  ob_flush();
  flush();
  
  // Exit immediately - do not loop
  exit();
}

// Send an initial event to confirm the stream is open
echo "event: status\n";
echo "data: " . json_encode([
  'status' => 'open',
  'message' => 'Performance stream opened. Waiting for MCP response...'
]) . "\n\n";
ob_flush();
flush();

// Boucle infinie pour l'envoi des événements
while (true) {
  try {
    // Simulate random failure before computing data
    $threshold = (int) ($sim_fail_rate * 1000000);

    if ($sim_fail_rate > 0 && random_int(1, 1000000) <= $threshold) {
      throw new \Exception('Simulated failure');
    }

    // Récupérer les données de performance
    $data = $monitor->getPerformanceData($range);

    // Apply simulation overrides to metrics
    if ($sim_latency_ms > 0) {
      // Optional processing delay to simulate server-side slowness
      usleep(min($sim_latency_ms, 30000) * 1000); // cap delay to 30s to avoid runaway
      if (isset($data['metrics'])) {
        $data['metrics']['average_latency'] = (float)$sim_latency_ms;
      }
    }

    if ($sim_error_pct !== null) {
      if (isset($data['metrics'])) {
        $data['metrics']['error_frequency'] = max(0.0, (float)$sim_error_pct);
      }
    }

    // Optionally force a random connection drop to test auto-reconnect
    if (!empty($sim_drop) && random_int(1, 100) <= 5) {
      echo "event: error\n";
      echo "data: {\"error\":\"Simulated connection drop\"}\n\n";

      if (ob_get_level() > 0) {
        ob_flush();
      }

      flush();
      exit();
    }

    // Envoyer les données au format SSE
    echo "data: " . json_encode($data) . "\n\n";

    // Vider le buffer de sortie
    ob_flush();
    flush();

    // Attendre avant la prochaine mise à jour
    $sleepSeconds = $sim_sleep_override !== null ? max(0, $sim_sleep_override) : 5;
    sleep($sleepSeconds);
  } catch (\Exception $e) {
    // Send error to client only (no log)
    echo "event: error\n";
    echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";

    // Vider le buffer
    ob_flush();
    flush();

    // Attendre avant de réessayer
    sleep(5);
  }
}
