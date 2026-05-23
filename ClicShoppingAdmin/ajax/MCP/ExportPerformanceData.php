<?php
/**
 * Export Performance Data
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
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

if ($token !== $mcpConnector->getSessionToken()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid MCP token']);
    exit();
}

$range = $_GET['range'] ?? '24h';
$format = $_GET['format'] ?? 'json';

// Create real monitor instance
$config = MCPConnector::getConfigDb();
$monitor = McpMonitor::getInstance($config);

// Get performance data
$performanceData = $monitor->getPerformanceData($range);

// Export data based on format
if ($format === 'csv') {
    $exportData = convertToCSV($performanceData);
} else {
    $exportData = json_encode($performanceData, JSON_PRETTY_PRINT);
}

/**
 * Convert performance data to CSV format
 */
function convertToCSV($data) {
    $output = fopen('php://temp', 'r+');
    
    // Write headers
    fputcsv($output, ['Timestamp', 'Latency (ms)', 'Error Rate (%)', 'Requests/min', 'Uptime (%)']);
    
    // Write history data
    if (isset($data['history']) && is_array($data['history'])) {
        foreach ($data['history'] as $entry) {
            fputcsv($output, [
                date('Y-m-d H:i:s', $entry['timestamp'] ?? time()),
                $entry['latency'] ?? 0,
                $entry['error_rate'] ?? 0,
                $entry['requests'] ?? 0,
                $entry['uptime'] ?? 100
            ]);
        }
    }
    
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    return $csv;
}

// Set appropriate headers based on format
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="mcp_performance_' . $range . '_' . date('Y-m-d') . '.csv"');
} else {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="mcp_performance_' . $range . '_' . date('Y-m-d') . '.json"');
}

echo $exportData;

