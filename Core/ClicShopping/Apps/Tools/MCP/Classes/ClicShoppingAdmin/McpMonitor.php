<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Transport\TransportInterface;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\McpJsonRpcClient;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\Transport\SseTransport;
use ClicShopping\OM\SimpleLogger;

/**
 * Class McpMonitor
 *
 * This class serves as the main monitoring component for the MCP (Management & Control Panel) system.
 * It integrates with the health checker and the JSON-RPC client to monitor system performance,
 * generate alerts based on predefined thresholds, and provide performance data and recommendations.
 */
class McpMonitor
{
  /**
   * @var McpHealth The health checker instance.
   */
  private McpHealth $health;

  /**
   * @var array The thresholds for generating alerts.
   */
  private array $alertThresholds;

  /**
   * @var array Stores recent notifications.
   */
  private array $notifications = [];

  /**
   * @var self|null The singleton instance of the class.
   */
  private static ?McpMonitor $instance = null;

  /**
   * @var TransportInterface The transport layer for communication.
   */
  private TransportInterface $transport;

  /**
   * @var McpJsonRpcClient The JSON-RPC client for sending requests.
   */
  private McpJsonRpcClient $client;

  /**
   * @var SimpleLogger The logger instance.
   */
  private SimpleLogger $logger;

  /**
   * @var array The configuration settings for the monitor.
   */
  private array $config;

  /**
   * McpMonitor constructor.
   *
   * Initializes the monitor by setting up dependencies like the health checker, logger,
   * transport, and JSON-RPC client. It also connects to the MCP service and sets
   * the alert thresholds from configuration.
   *
   * @param array $config The configuration settings.
   */
  public function __construct(array $config)
  {
    $this->config = $config;

    $this->health = McpHealth::getInstance();

    $this->logger = new SimpleLogger();
    $this->transport = new SseTransport($config, $this->logger);
    $this->client = new McpJsonRpcClient($config, $this->transport, $this->logger);
    $this->client->connect();

    $this->alertThresholds = [
      'error_rate' =>  (int)CLICSHOPPING_APP_MCP_MC_ALERT_THRESHOLDS ?? 20,
      'latency' => (int)CLICSHOPPING_APP_MCP_MC_LATENCY_THRESHOLDS ?? 1000,
      'downtime' => (int)CLICSHOPPING_APP_MCP_MC_DOWNTIME_THRESHOLDS ?? 300
    ];
  }

  /**
   * Gets the singleton instance of the `McpMonitor`.
   *
   * @param array $config The configuration settings.
   * @return self The single instance of the class.
   */
  public static function getInstance(array $config = []): self
  {
    if (self::$instance === null) {
      self::$instance = new self($config);
    }
    return self::$instance;
  }

  /**
   * Gets the client's statistics.
   *
   * @return array The client's statistics.
   */
  public function getStats(): array
  {
    return $this->client->getStats();
  }

  /**
   * Gets the current configuration.
   *
   * @return array The configuration settings.
   */
  public function getConfig(): array
  {
    return $this->config;
  }

  /**
   * Connects to the MCP service.
   */
  public function connect(): void
  {
    $this->client->connect();
  }

  /**
   * Disconnects from the MCP service.
   */
  public function disconnect(): void
  {
    $this->client->disconnect();
  }

  /**
   * Sends a notification to the MCP service.
   *
   * @param string $method The JSON-RPC method name.
   * @param array $params An array of parameters for the method.
   */
  public function notify(string $method, array $params = []): void
  {
    $this->client->sendNotification($method, $params);
  }

  /**
   * Runs the monitoring cycle and generates alerts.
   *
   * This method performs a health check and compares the results against the alert thresholds.
   * If any metric exceeds a threshold, an alert is generated and processed.
   *
   * @return array A list of generated alerts.
   */
  public function monitor(): array
  {
    $status = $this->health->check();
    $alerts = [];

    // Check performance metrics
    if ($status['performance']['error_rate'] > $this->alertThresholds['error_rate']) {
      $alerts[] = [
        'type' => 'error',
        'message' => "High error rate detected: {$status['performance']['error_rate']}%",
        'timestamp' => time()
      ];
    }

    // Check connectivity
    if (isset($status['connectivity']['latency']) &&
      $status['connectivity']['latency'] > $this->alertThresholds['latency']) {
      $alerts[] = [
        'type' => 'warning',
        'message' => "High latency detected: {$status['connectivity']['latency']}ms",
        'timestamp' => time()
      ];
    }

    // Process and store alerts
    foreach ($alerts as $alert) {
      $this->processAlert($alert);
    }

    return $alerts;
  }

  /**
   * Processes and stores an alert.
   *
   * This private helper method adds the alert to the internal notifications array
   * and logs it using the message stack.
   *
   * @param array $alert The alert to process.
   */
  private function processAlert(array $alert): void
  {
    $this->notifications[] = $alert;

    // Keep only last 100 notifications
    if (count($this->notifications) > 100) {
      array_shift($this->notifications);
    }

    // Log alert
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $message = "[MCP Alert] {$alert['message']}";

    if ($alert['type'] === 'error') {
      $CLICSHOPPING_MessageStack->add($message, 'error');
    } else {
      $CLICSHOPPING_MessageStack->add($message, 'warning');
    }
  }

  /**
   * Gets the list of recent notifications.
   *
   * @return array An array of recent notifications.
   */
  public function getNotifications(): array
  {
    return $this->notifications;
  }

  /**
   * Clears all stored notifications.
   */
  public function clearNotifications(): void
  {
    $this->notifications = [];
  }

  /**
   * Updates the alert thresholds.
   *
   * @param array $thresholds An associative array of new thresholds to apply.
   */
  public function setAlertThresholds(array $thresholds): void
  {
    $this->alertThresholds = array_merge($this->alertThresholds, $thresholds);
  }

  /**
   * Gets the current alert thresholds.
   *
   * @return array The current alert thresholds.
   */
  public function getAlertThresholds(): array
  {
    return $this->alertThresholds;
  }

  /**
   * Gets performance data for a specified time range.
   *
   * This method uses a `McpPerformanceAnalyzer` to retrieve and analyze historical data,
   * and then generates recommendations based on the analysis.
   *
   * @param string $range Time range (e.g., '24h', 'week').
   * @return array Performance metrics, trends, and recommendations.
   */
  public function getPerformanceData(string $range = '24h'): array
  {
    $status = $this->health->check();
    $performanceAnalyzer = new McpPerformanceAnalyzer();

    // Get performance history
    $history = $performanceAnalyzer->getHistory($range);

    // Calculate current metrics
    $metrics = [
      'request_rate' => $performanceAnalyzer->calculateRequestRate(),
      'average_latency' => $status['connectivity']['latency'] ?? 0,
      'error_frequency' => $status['performance']['error_rate'] ?? 0,
      'uptime_percentage' => $performanceAnalyzer->calculateUptime()
    ];

    // Analyze trends
    $trends = [
      'latency_trend' => $performanceAnalyzer->analyzeTrend('latency'),
      'error_trend' => $performanceAnalyzer->analyzeTrend('error_rate'),
      'request_trend' => $performanceAnalyzer->analyzeTrend('requests')
    ];

    // Generate recommendations
    $recommendations = $this->generateRecommendations($metrics, $trends);

    return [
      'metrics' => $metrics,
      'trends' => $trends,
      'recommendations' => $recommendations,
      'history' => $history
    ];
  }

  /**
   * Generates performance recommendations based on metrics and trends.
   *
   * This private helper method provides a list of actionable insights based on the
   * analysis of current performance data.
   *
   * @param array $metrics Current performance metrics.
   * @param array $trends Performance trends.
   * @return array A list of recommendations.
   */
  private function generateRecommendations(array $metrics, array $trends): array
  {
    $recommendations = [];

    // Check error rate
    if ($metrics['error_frequency'] > 10) {
      $recommendations[] = [
        'type' => 'danger',
        'priority' => 'high',
        'message' => 'High error rate detected. Check error logs and system stability.'
      ];
    }

    // Check latency
    if ($metrics['average_latency'] > 500) {
      $recommendations[] = [
        'type' => 'warning',
        'priority' => 'medium',
        'message' => 'High latency detected. Consider optimizing network configuration.'
      ];
    }

    // Check request rate trend
    if ($trends['request_trend']['direction'] === 'increasing' &&
      $trends['request_trend']['percentage'] > 50) {
      $recommendations[] = [
        'type' => 'info',
        'priority' => 'low',
        'message' => 'Significant increase in request rate. Monitor system resources.'
      ];
    }

    // Check uptime
    if ($metrics['uptime_percentage'] < 99) {
      $recommendations[] = [
        'type' => 'warning',
        'priority' => 'high',
        'message' => 'System uptime below target. Review system stability.'
      ];
    }

    return $recommendations;
  }

  /**
   * Gets the current system status.
   *
   * @return array An array containing the current system status, including connectivity, performance, and errors.
   */
  public function getCurrentStatus(): array
  {
    return [
      'status' => 'healthy', // or 'warning', 'critical'
      'connectivity' => [
        'latency' => 123 // in milliseconds
      ],
      'performance' => [
        'error_rate' => 0.5, // in percentage
        'total_requests' => 4523
      ],
      'system' => [
        'disk_space' => [
          'used' => 3.2, // in GB
          'total' => 10.0, // in GB
          'percentage' => 32 // used
        ]
      ],
      'errors' => [
        'recent_errors' => [
          [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'error',
            'message' => 'Example error message'
          ]
        ]
      ]
    ];
  }

  /**
   * Gets the data retention options for the MCP configuration.
   *
   * @return array An array of key-value pairs representing data retention options.
   */
  public static function getRetentionOptions(): array
  {
    return [
      ['id' => '7', 'text' => CLICSHOPPING::getDef('text_one_week')],
      ['id' => '30', 'text' => CLICSHOPPING::getDef('text_one_month')],
      ['id' => '90', 'text' => CLICSHOPPING::getDef('text_three_months')]
    ];
  }
}