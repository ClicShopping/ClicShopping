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
    $issues = [];
    $db = Registry::get('Db');

    $Qmcp = $db->prepare('SELECT COUNT(*) AS total,
                                 SUM(status) AS active,
                                 SUM(select_data + update_data + create_data + delete_data + create_db) AS granted
                          FROM :table_mcp
                         ');
    $Qmcp->execute();
    $Qmcp->fetch();

    $total = $Qmcp->valueInt('total');
    $active = $Qmcp->valueInt('active');
    $granted = $Qmcp->valueInt('granted');

    if ($total === 0) {
      $issues[] = 'no_configuration';
    } elseif ($active === 0) {
      $issues[] = 'no_active_configuration';
    } elseif ($granted === 0) {
      $issues[] = 'no_permission_granted';
    }

    return [
      'valid' => $issues === [],
      'issues' => $issues,
      'configurations' => $total,
      'active' => $active,
      'permissions_granted' => $granted,
      'status' => $issues === [] ? 'healthy' : 'error'
    ];
  }

  /**
   * Reports the INBOUND traffic: MCP calls ClicShopping, never the reverse.
   *
   * It used to ping an outbound MCP server on server_host:server_port. No such server exists in
   * this governance, so the probe was permanently red and said nothing about the traffic that
   * does happen. What matters is whether callers reach us, and whether they are being refused.
   *
   * @return array Inbound session counters, with no prose (the page owns the wording).
   */
  private function checkConnectivity(): array
  {
    $db = Registry::get('Db');

    $Qsession = $db->prepare('SELECT COUNT(*) AS total,
                                     MAX(date_modified) AS last_seen,
                                     SUM(date_modified >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS last_day
                              FROM :table_mcp_session
                             ');
    $Qsession->execute();
    $Qsession->fetch();

    $total = $Qsession->valueInt('total');
    $lastSeen = $Qsession->value('last_seen');
    $lastDay = $Qsession->valueInt('last_day');

    $Qrefused = $db->prepare('SELECT COALESCE(SUM(attempts), 0) AS refused
                              FROM :table_mcp_failed_attempts
                             ');
    $Qrefused->execute();
    $Qrefused->fetch();

    $refused = $Qrefused->valueInt('refused');

    // Never contacted is not a failure: it is an integration nobody has called yet.
    $status = 'healthy';

    if ($refused > 0) {
      $status = 'warning';
    }

    return [
      'connected' => $total > 0,
      'latency' => null,
      'direction' => 'inbound',
      'sessions' => $total,
      'sessions_last_day' => $lastDay,
      'last_seen' => $lastSeen,
      'refused' => $refused,
      'status' => $status
    ];
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
    // It used to read the outbound transport counters, which are per-instance and therefore always
    // zero on a fresh request: uptime 0h, 0 requests, 0% errors, whatever had actually happened.
    $db = Registry::get('Db');

    $Qload = $db->prepare('SELECT COUNT(*) AS calls_last_hour,
                                  COUNT(DISTINCT identifier) AS callers
                           FROM :table_mcp_rate_limit
                           WHERE timestamp >= :timestamp
                          ');
    $Qload->bindInt(':timestamp', time() - 3600);
    $Qload->execute();
    $Qload->fetch();

    $Qalerts = $db->prepare('SELECT COUNT(*) AS total,
                                    COALESCE(MAX(severity_level), 0) AS worst
                             FROM :table_mcp_alerts
                             WHERE alert_timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                            ');
    $Qalerts->execute();
    $Qalerts->fetch();

    $alerts = $Qalerts->valueInt('total');
    $worst = $Qalerts->valueInt('worst');

    $performance = [
      'status' => 'healthy',
      'calls_last_hour' => $Qload->valueInt('calls_last_hour'),
      'callers_last_hour' => $Qload->valueInt('callers'),
      'alerts_last_day' => $alerts,
      'worst_severity' => $worst,
      'error_rate' => 0,
    ];

    if ($alerts > 0) {
      $performance['status'] = $worst >= 3 ? 'error' : 'warning';
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
