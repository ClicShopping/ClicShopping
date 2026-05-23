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
use Psr\Log\LoggerInterface;
use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\McpHealth;


/**
 * Class McpPerformanceAnalyzer
 *
 * This class is responsible for analyzing the performance of the MCP (Management & Control Panel) system.
 * It calculates various metrics, analyzes trends, and provides actionable recommendations based on the data.
 * The analyzer maintains a history of performance data for trend analysis.
 */
class McpPerformanceAnalyzer
{
  /**
   * @var McpHealth The health checker instance to get current status.
   */
  private McpHealth $health;

  /**
   * @var LoggerInterface The logger instance for logging analysis results and errors.
   */
  private LoggerInterface $logger;

  /**
   * @var array An array to store the performance history.
   */
  private array $performanceHistory = [];

  /**
   * @var int The maximum number of historical data points to store.
   */
  private int $historyLimit = 100;

  /**
   * McpPerformanceAnalyzer constructor.
   *
   * Initializes the analyzer, sets up the health checker and logger, and loads the performance history.
   *
   * @param LoggerInterface|null $logger An optional PSR-3 logger instance.
   */
  public function __construct(?LoggerInterface $logger = null)
  {
    $this->health = McpHealth::getInstance();

    if (!Registry::exists('SimpleLogger')) {
      $this->logger = Registry::get('SimpleLogger');
    }
    // $this->logger = $logger ?? Registry::get('SimpleLogger');
    $this->loadPerformanceHistory();
  }

  /**
   * Analyzes current performance and provides recommendations.
   *
   * This method orchestrates the entire analysis process: calculating metrics, generating recommendations,
   * and analyzing trends. It also stores the latest metrics in the history.
   *
   * @return array An array containing the current status, metrics, recommendations, and trends.
   */
  public function analyze(): array
  {
    $status = $this->health->check();
    $metrics = $this->calculateMetrics();
    $recommendations = $this->generateRecommendations($metrics);

    $this->storeMetrics($metrics);

    return [
      'current_status' => $status,
      'metrics' => $metrics,
      'recommendations' => $recommendations,
      'trends' => $this->analyzeTrends()
    ];
  }

  /**
   * Calculates various performance metrics.
   *
   * This private helper method aggregates data from the health checker and the performance history
   * to compute key metrics.
   *
   * @return array An associative array of calculated metrics.
   */
  private function calculateMetrics(): array
  {
    $stats = $this->health->getStats();

    return [
      'request_rate' => $this->calculateRequestRate(),
      'average_latency' => $this->calculateAverageLatency(),
      'error_frequency' => $this->calculateErrorFrequency(),
      'uptime_percentage' => $this->calculateUptimePercentage(),
      'memory_usage' => $this->getMemoryUsage(),
      'timestamp' => time()
    ];
  }

  /**
   * Calculates the requests per minute based on recent history.
   *
   * @return float The number of requests per minute.
   */
  public function calculateRequestRate(): float
  {
    $history = array_slice($this->performanceHistory, -10);
    if (empty($history)) {
      return 0.0;
    }

    $totalRequests = array_sum(array_column($history, 'requests'));
    $timeSpan = end($history)['timestamp'] - reset($history)['timestamp'];

    return $timeSpan > 0 ? ($totalRequests / $timeSpan) * 60 : 0;
  }

  /**
   * Calculates the average latency over recent requests.
   *
   * @return float The average latency in milliseconds.
   */
  private function calculateAverageLatency(): float
  {
    $history = array_slice($this->performanceHistory, -10);
    if (empty($history)) {
      return 0.0;
    }

    $latencies = array_column($history, 'latency');
    return array_sum($latencies) / count($latencies);
  }

  /**
   * Calculates the error frequency as a percentage of total requests.
   *
   * @return float The error frequency percentage.
   */
  private function calculateErrorFrequency(): float
  {
    $history = array_slice($this->performanceHistory, -20);
    if (empty($history)) {
      return 0.0;
    }

    $totalErrors = array_sum(array_column($history, 'errors'));
    $totalRequests = array_sum(array_column($history, 'requests'));

    return $totalRequests > 0 ? ($totalErrors / $totalRequests) * 100 : 0;
  }

  /**
   * Calculates the uptime percentage based on historical status.
   *
   * @return float The uptime percentage.
   */
  private function calculateUptimePercentage(): float
  {
    $history = array_slice($this->performanceHistory, -100);
    if (empty($history)) {
      return 100.0;
    }

    $downtime = array_sum(array_map(function ($record) {
      return $record['status'] === 'error' ? 1 : 0;
    }, $history));

    return ((count($history) - $downtime) / count($history)) * 100;
  }

  /**
   * Gets the current memory usage of the PHP script.
   *
   * @return array An associative array with current and peak memory usage in bytes.
   */
  private function getMemoryUsage(): array
  {
    return [
      'current' => memory_get_usage(true),
      'peak' => memory_get_peak_usage(true)
    ];
  }

  /**
   * Generates a list of recommendations based on the calculated metrics.
   *
   * @param array $metrics The calculated performance metrics.
   * @return array An array of recommendation objects.
   */
  private function generateRecommendations(array $metrics): array
  {
    $recommendations = [];

    // Check request rate
    if ($metrics['request_rate'] > 100) {
      $recommendations[] = [
        'type' => 'warning',
        'message' => 'High request rate detected. Consider implementing rate limiting.',
        'priority' => 'high'
      ];
    }

    // Check latency
    if ($metrics['average_latency'] > 1000) {
      $recommendations[] = [
        'type' => 'warning',
        'message' => 'High average latency detected. Consider optimizing network configuration.',
        'priority' => 'medium'
      ];
    }

    // Check error frequency
    if ($metrics['error_frequency'] > 5) {
      $recommendations[] = [
        'type' => 'error',
        'message' => 'High error rate detected. Review error logs for common patterns.',
        'priority' => 'high'
      ];
    }

    // Check memory usage
    if ($metrics['memory_usage']['current'] > 67108864) { // 64MB
      $recommendations[] = [
        'type' => 'warning',
        'message' => 'High memory usage detected. Consider optimizing memory allocation.',
        'priority' => 'medium'
      ];
    }

    return $recommendations;
  }

  /**
   * Analyzes performance trends by comparing recent and older data.
   *
   * @return array An associative array of trend analysis results for latency, errors, and requests.
   */
  private function analyzeTrends(): array
  {
    if (count($this->performanceHistory) < 2) {
      return ['insufficient_data' => true];
    }

    $recent = array_slice($this->performanceHistory, -10);
    $older = array_slice($this->performanceHistory, -20, 10);

    return [
      'latency_trend' => $this->calculateTrend(
        array_column($older, 'latency'),
        array_column($recent, 'latency')
      ),
      'error_trend' => $this->calculateTrend(
        array_column($older, 'errors'),
        array_column($recent, 'errors')
      ),
      'request_trend' => $this->calculateTrend(
        array_column($older, 'requests'),
        array_column($recent, 'requests')
      )
    ];
  }

  /**
   * Calculates the trend (direction and percentage change) between two data sets.
   *
   * @param array $previous The data set from the previous period.
   * @param array $current The data set from the current period.
   * @return array An associative array with the trend direction, percentage change, and average values.
   */
  private function calculateTrend(array $previous, array $current): array
  {
    $prevCount = count($previous);
    $currCount = count($current);

    $prevAvg = $prevCount > 0 ? array_sum($previous) / $prevCount : 0;
    $currAvg = $currCount > 0 ? array_sum($current) / $currCount : 0;

    $change = $prevAvg > 0 ? (($currAvg - $prevAvg) / $prevAvg) * 100 : 0;

    return [
      'direction' => $change > 0 ? 'increasing' : ($change < 0 ? 'decreasing' : 'stable'),
      'percentage' => abs($change),
      'previous_avg' => $prevAvg,
      'current_avg' => $currAvg
    ];
  }

  /**
   * Stores the current metrics in the performance history.
   *
   * This method adds the latest metrics to the history and ensures the history size does not exceed the limit.
   * It also schedules a delayed database write to persist the data.
   *
   * @param array $metrics The metrics to store.
   */
  private function storeMetrics(array $metrics): void
  {
    $this->performanceHistory[] = $metrics;

    if (count($this->performanceHistory) > $this->historyLimit) {
      array_shift($this->performanceHistory);
    }

    // Write to database at the end of the script's execution
    register_shutdown_function(function () use ($metrics) {
      $this->writeMetrics($metrics);
    });
  }

  /**
   * Writes performance metrics to the database.
   *
   * @param array $metrics The metrics to write.
   */
  private function writeMetrics(array $metrics): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $sql_array = [
      'metrics' => json_encode($metrics),
      'timestamp' => date('Y-m-d H:i:s', $metrics['timestamp'])
    ];

    $CLICSHOPPING_Db->save('mcp_performance_history', $sql_array);
  }

  /**
   * Gets performance history for a specific time range.
   *
   * @param string $range The time range (e.g., 'hour', 'day').
   * @return array An array of performance history data points within the specified range.
   */
  public function getHistory(string $range = '24h'): array
  {
    $endTime = time();
    $startTime = $this->getStartTimeFromRange($range);

    return array_filter($this->performanceHistory, function ($entry) use ($startTime, $endTime) {
      return $entry['timestamp'] >= $startTime && $entry['timestamp'] <= $endTime;
    });
  }

  /**
   * Calculates the overall uptime percentage of the system based on history.
   *
   * @return float The uptime percentage.
   */
  public function calculateUptime(): float
  {
    $history = array_slice($this->performanceHistory, -100);
    if (empty($history)) {
      return 100.0;
    }

    $downTimeEvents = array_filter($history, function ($entry) {
      return isset($entry['status']) && $entry['status'] === 'down';
    });

    return (1 - (count($downTimeEvents) / count($history))) * 100;
  }

  /**
   * Analyzes the trend for a specific metric.
   *
   * This method compares the average of the most recent data points with a previous set
   * to determine the trend direction and percentage change.
   *
   * @param string $metric The name of the metric to analyze (e.g., 'latency', 'error_rate').
   * @return array An associative array with the trend direction and percentage change.
   */
  public function analyzeTrend(string $metric): array
  {
    $history = array_slice($this->performanceHistory, -20);
    if (count($history) < 2) {
      return [
        'direction' => 'stable',
        'percentage' => 0.0
      ];
    }

    $values = array_column($history, $metric);
    $oldAvg = array_sum(array_slice($values, 0, 10)) / 10;
    $newAvg = array_sum(array_slice($values, -10)) / 10;

    if ($oldAvg === 0) {
      return [
        'direction' => 'stable',
        'percentage' => 0.0
      ];
    }

    $changePercentage = (($newAvg - $oldAvg) / $oldAvg) * 100;

    return [
      'direction' => $this->getTrendDirection($changePercentage),
      'percentage' => abs($changePercentage)
    ];
  }

  /**
   * Gets the trend direction based on a change percentage.
   *
   * @param float $changePercentage The percentage change.
   * @return string The trend direction ('increasing', 'decreasing', or 'stable').
   */
  private function getTrendDirection(float $changePercentage): string
  {
    if (abs($changePercentage) < 5) {
      return 'stable';
    }
    return $changePercentage > 0 ? 'increasing' : 'decreasing';
  }

  /**
   * Gets the start timestamp for a given time range.
   *
   * @param string $range The time range (e.g., 'hour', 'week').
   * @return int The start timestamp.
   */
  private function getStartTimeFromRange(string $range): int
  {
    $now = time();
    switch ($range) {
      case 'hour':
        return $now - 3600;
      case 'week':
        return $now - 604800;
      case 'month':
        return $now - 2592000;
      case '24h':
      case 'day':
      default:
        return $now - 86400;
    }
  }

  /**
   * Loads performance history from persistent storage.
   *
   * This method is a placeholder for a database or file-based history loader.
   * Currently, it initializes dummy data for development purposes.
   */
  private function loadPerformanceHistory(): void
  {
    // TODO: Implement persistent storage
    // For now, we use temporary data for development
    if (empty($this->performanceHistory)) {
      $this->initializeDummyData();
    }
  }

  /**
   * Initializes dummy data for development purposes.
   *
   * This private helper method creates a mock performance history to allow the analyzer
   * to function without a live data source.
   */
  private function initializeDummyData(): void
  {
    $now = time();
    $requests = random_int(100, 1000);

    $latency = 50 + (int)($requests / 20) + random_int(0, 30);
    $error_rate = (int)($requests / 300);

    $status = ($error_rate > 2 || $latency > 200) ? (random_int(1, 100) > 70 ? 'down' : 'degraded') : 'up';

    for ($i = 100; $i > 0; $i--) {
      $this->performanceHistory[] = [
        'timestamp' => $now - ($i * 900),
        'requests' => $requests,
        'latency_ms' => $latency,
        'error_rate' => $error_rate,
        'status' => $status
      ];
    }
  }
}