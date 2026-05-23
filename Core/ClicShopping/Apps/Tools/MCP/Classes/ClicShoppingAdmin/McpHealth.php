<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Exceptions\McpConnectionException;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\MCPConnector;

/**
 * Class McpHealth
 *
 * This class provides a comprehensive health check for the MCP (Management & Control Panel) system.
 * It checks various aspects of the system, including configuration, connectivity, and performance,
 * to provide a real-time status of the service.
 */
class McpHealth
{
  /**
   * @var McpService The service instance used for communication with the MCP server.
   */
  private McpService $service;

  /**
   * @var array Stores the results of the last health check.
   */
  private array $lastCheck = [];

  /**
   * @var McpHealth|null The singleton instance of the class.
   */
  private static ?McpHealth $instance = null;

  /**
   * McpHealth constructor.
   * Initializes the service instance.
   */
  public function __construct()
  {
    $this->service = McpService::getInstance();
  }

  /**
   * Get the singleton instance of McpHealth.
   *
   * This method ensures that only one instance of the class is created.
   *
   * @return McpHealth The single instance of the class.
   */
  public static function getInstance(): self
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Performs a comprehensive health check for the MCP system.
   *
   * This method combines checks for configuration, connectivity, and performance
   * into a single overall status. The result is stored internally as the last check.
   *
   * @return array An array with a 'status', 'message', 'timestamp', and detailed 'details' of each check.
   */
  public function check(): array
  {
    // Call the real-time check methods
    $configStatus = $this->checkConfiguration();
    $connectivityStatus = $this->checkConnectivity();
    $performanceStatus = $this->checkPerformance();

    // Check if any sub-status is an error or warning
    $overallStatus = 'ok';
    $overallMessage = 'MCP system is operational.';

    if ($configStatus['status'] === 'error' || $connectivityStatus['status'] === 'error' || $performanceStatus['status'] === 'error') {
      $overallStatus = 'error';
      $overallMessage = 'MCP system has errors.';
    } elseif ($configStatus['status'] === 'warning' || $connectivityStatus['status'] === 'warning' || $performanceStatus['status'] === 'warning') {
      $overallStatus = 'warning';
      $overallMessage = 'MCP system has warnings.';
    }

    // Return the combined, real-time status
    return [
      'status' => $overallStatus,
      'message' => $overallMessage,
      'timestamp' => date('Y-m-d H:i:s'),
      'details' => [
        'configuration' => $configStatus,
        'connectivity' => $connectivityStatus,
        'performance' => $performanceStatus,
      ]
    ];
  }
  /**
   * Checks the configuration status.
   *
   * This method validates the MCP configuration settings to ensure they are correct.
   *
   * @return array An array with a 'valid' boolean, an 'issues' array, and a 'status' string.
   */
  private function checkConfiguration(): array
  {
    $validation = $this->service->validateConfiguration();
    return [
      'valid' => $validation['valid'],
      'issues' => $validation['issues'],
      'status' => $validation['valid'] ? 'healthy' : 'error'
    ];
  }

  /**
   * Checks the connectivity status with the MCP server.
   *
   * This method sends a 'ping' message to the server to test the connection and measure latency.
   *
   * @return array An array with connection status, latency, and error message if the connection fails.
   */
  private function checkConnectivity(): array
  {
    try {
      $startTime = microtime(true);
      $this->service->sendMessage('ping');
      $latency = (microtime(true) - $startTime) * 1000;

      $result = [
        'connected' => true,
        'latency' => round($latency, 2),
        'status' => $latency < 1000 ? 'healthy' : 'warning'
      ];

      return $result;
    } catch (McpConnectionException $e) {
      // Fallback: raw TCP connectivity check to avoid endpoint-specific failures
      $config = MCPConnector::getInstance()->getConfig();
      $host = $config['server_host'] ?? 'localhost';
      $port = (int)($config['server_port'] ?? 0);
      $timeout = 2;

      $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
      if (is_resource($socket)) {
        fclose($socket);
        return [
          'connected' => true,
          'latency' => null,
          'status' => 'warning',
          'error' => 'Ping failed, but TCP connection to MCP server succeeded.'
        ];
      }

      return [
        'connected' => false,
        'error' => $e->getMessage(),
        'status' => 'error'
      ];
    }
  }

  /**
   * Checks the performance metrics of the MCP service.
   *
   * This method retrieves real-time performance statistics from the service,
   * such as uptime, total requests, and error rate, to determine its health.
   *
   * @return array Performance metrics including status, uptime, total requests, and error rate.
   */
  private function checkPerformance(): array
  {
    // This calls your getHealthStatus() function
    $stats = $this->service->getHealthStatus();

    // If getHealthStatus returned an error, pass that on
    if ($stats['status'] === 'error') {
      return ['status' => 'error', 'message' => $stats['message']];
    }

    // Build the performance array from the stats
    $performance = [
      'status' => $stats['status'], // Use the status from getHealthStatus
      'uptime' => $stats['uptime'] ?? 0,
      'total_requests' => $stats['total_requests'] ?? 0,
      'error_rate' => 0,
    ];

    if (($stats['total_requests'] ?? 0) > 0) {
      $performance['error_rate'] = round(($stats['errors'] ?? 0) / $stats['total_requests'] * 100, 2);
    }

    // Override status if error rate is too high
    if ($performance['error_rate'] > 5) {
      $performance['status'] = 'warning';
    }
    if ($performance['error_rate'] > 20) {
      $performance['status'] = 'error';
    }

    return $performance;
  }

  /**
   * Determines the overall health status from a set of individual checks.
   *
   * This is a helper method to aggregate the statuses. An error in any check results in an overall 'error'.
   *
   * @param array $checks An array of check results, each with a 'status' key.
   * @return string The overall status: 'error', 'warning', or 'healthy'.
   */
  private function determineOverallStatus(array $checks): string
  {
    if (in_array('error', array_column($checks, 'status'))) {
      return 'error';
    }
    if (in_array('warning', array_column($checks, 'status'))) {
      return 'warning';
    }
    return 'healthy';
  }

  /**
   * Retrieves the results of the last health check.
   *
   * @return array The array of results from the most recent `check()` call.
   */
  public function getLastCheck(): array
  {
    return $this->lastCheck;
  }

  /**
   * Checks if the service is considered healthy.
   *
   * This method performs a new health check and returns a boolean value based on the overall status.
   *
   * @return bool True if the overall status is 'healthy', false otherwise.
   */
  public function isHealthy(): bool
  {
    return $this->check()['status'] === 'healthy';
  }

  /**
   * Retrieves the current health statistics.
   *
   * This method gets detailed statistics, including performance metrics, connectivity info, and system status.
   * It performs a new health check if no previous check has been run.
   *
   * @return array Health statistics including performance metrics.
   */
  public function getStats(): array
  {
    if (empty($this->lastCheck)) {
      $this->check();
    }

    $stats = $this->service->getHealthStatus();

    return [
      'performance' => [
        'uptime' => $stats['uptime'] ?? 0,
        'total_requests' => $stats['total_requests'] ?? 0,
        'error_rate' => $stats['error_rate'] ?? 0,
        'memory_usage' => $stats['memory_usage'] ?? 0,
        'cpu_usage' => $stats['cpu_usage'] ?? 0,
        'requests_per_minute' => $stats['requests_per_minute'] ?? 0
      ],
      'connectivity' => [
        'latency' => $this->lastCheck['connectivity']['latency'] ?? 0,
        'status' => $this->lastCheck['connectivity']['status'] ?? 'unknown'
      ],
      'system' => [
        'last_check' => $this->lastCheck['timestamp'] ?? time(),
        'status' => $this->lastCheck['status'] ?? 'unknown'
      ]
    ];
  }
}
