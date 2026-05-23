<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;

/**
 * Class MpcPerformanceHistoryStorage
 *
 * This class handles the storage and retrieval of MCP (Model Context Protocol) performance
 * metrics in the database. It provides methods for storing data, querying historical
 * records, analyzing trends, and managing data retention.
 */
class MpcPerformanceHistoryStorage
{
  /**
   * @var \ClicShopping\OM\Db The database connection instance.
   */
  private $db;

  /**
   * @var string The name of the database table for performance history.
   */
  private string $tableName = 'mcp_performance_history';

  /**
   * @var int The number of days to retain historical data.
   */
  private int $retentionDays = 90; // Keep data for 90 days by default

  /**
   * MpcPerformanceHistoryStorage constructor.
   *
   * Initializes the database connection and ensures the performance history table exists.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    //$this->createTableIfNotExists();
  }

  /**
   * Creates the performance history table if it does not exist.
   *
   * This private method is responsible for setting up the necessary database table
   * with appropriate columns and indexes for storing performance metrics.
   */
  private function createTableIfNotExists(): void
  {
    $sql = "CREATE TABLE IF NOT EXISTS clic_mcp_performance_history (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `timestamp` int(11) NOT NULL,
            `request_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
            `average_latency` decimal(10,2) NOT NULL DEFAULT 0.00,
            `error_frequency` decimal(5,2) NOT NULL DEFAULT 0.00,
            `uptime_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
            `total_requests` int(11) NOT NULL DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_timestamp` (`timestamp`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $this->db->execute($sql);
  }

  /**
   * Stores performance metrics in the database.
   *
   * @param array $metrics An associative array of performance metrics to store.
   * @return bool True on success, false on failure.
   */
  public function storeMetrics(array $metrics): bool
  {
    $timestamp = time();

    $sql_data = [
      'timestamp'         => $timestamp,
      'request_rate'      => $metrics['request_rate'] ?? 0,
      'average_latency'   => $metrics['average_latency'] ?? 0,
      'error_frequency'   => $metrics['error_frequency'] ?? 0,
      'uptime_percentage' => $metrics['uptime_percentage'] ?? 0,
      'total_requests'    => $metrics['total_requests'] ?? 0
    ];

    try {
      $this->db->save('mcp_performance_history', $sql_data);
      $this->cleanupOldData();
      return true;
    } catch (\Exception $e) {
      error_log("Failed to store performance metrics: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Retrieves historical performance data for a specified time range.
   *
   * @param string $range The time range (e.g., '24h', 'week', 'month').
   * @return array An array of historical data points.
   */
  public function getHistory(string $range = '24h'): array
  {
    $timeRange = $this->parseTimeRange($range);
    $startTime = time() - $timeRange;

    $Qsql = $this->db->prepare('SELECT timestamp,
                                          request_rate,
                                          average_latency,
                                          error_frequency,
                                          uptime_percentage,
                                          total_requests
                                  FROM :table_mcp_performance_history
                                  WHERE timestamp >= :start_time
                                  ORDER BY timestamp ASC
                                 ');
    $Qsql->bindValue(':start_time', $startTime);
    $Qsql->execute();

    try {
      $result_array = $Qsql->fetchAll();
      $history = [];

      foreach ($result_array as $row) {
        $history[] = [
          'timestamp' => (int)$row['timestamp'],
          'latency' => (float)$row['average_latency'],
          'error_rate' => (float)$row['error_frequency'],
          'requests' => (int)$row['total_requests'],
          'uptime' => (float)$row['uptime_percentage']
        ];
      }

      return $history;
    } catch (\Exception $e) {
      error_log("Failed to get performance history: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Analyzes trends for a specific metric over a time range.
   *
   * @param string $metric The metric to analyze (e.g., 'latency', 'error_rate', 'requests').
   * @param string $range The time range (e.g., '24h', 'week').
   * @return array An associative array with trend direction, percentage change, and average values.
   */
  public function analyzeTrends(string $metric = 'latency', string $range = '24h'): array
  {
    $history = $this->getHistory($range);

    if (count($history) < 2) {
      return ['direction' => 'stable', 'percentage' => 0];
    }

    $values = array_column($history, $metric === 'latency' ? 'latency' :
      ($metric === 'error_rate' ? 'error_rate' : 'requests'));

    $firstHalf = array_slice($values, 0, floor(count($values) / 2));
    $secondHalf = array_slice($values, floor(count($values) / 2));

    $firstAvg = array_sum($firstHalf) / count($firstHalf);
    $secondAvg = array_sum($secondHalf) / count($secondHalf);

    if ($firstAvg == 0) {
      return ['direction' => 'stable', 'percentage' => 0];
    }

    $percentage = (($secondAvg - $firstAvg) / $firstAvg) * 100;

    if (abs($percentage) < 5) {
      $direction = 'stable';
    } elseif ($percentage > 0) {
      $direction = 'increasing';
    } else {
      $direction = 'decreasing';
    }

    return [
      'direction' => $direction,
      'percentage' => round($percentage, 2),
      'first_period_avg' => round($firstAvg, 2),
      'second_period_avg' => round($secondAvg, 2),
      'data_points' => count($values)
    ];
  }

  /**
   * Gets performance statistics for a given time range.
   *
   * This method calculates key statistics like averages, maximums, and totals from the
   * historical data.
   *
   * @param string $range The time range.
   * @return array An associative array of performance statistics.
   */
  public function getStatistics(string $range = '24h'): array
  {
    $history = $this->getHistory($range);

    if (empty($history)) {
      return [
        'avg_latency' => 0,
        'max_latency' => 0,
        'min_latency' => 0,
        'avg_error_rate' => 0,
        'max_error_rate' => 0,
        'total_requests' => 0,
        'uptime_avg' => 0,
        'data_points' => 0
      ];
    }

    $latencies = array_column($history, 'latency');
    $errorRates = array_column($history, 'error_rate');
    $requests = array_column($history, 'requests');
    $uptimes = array_column($history, 'uptime');

    return [
      'avg_latency' => round(array_sum($latencies) / count($latencies), 2),
      'max_latency' => max($latencies),
      'min_latency' => min($latencies),
      'avg_error_rate' => round(array_sum($errorRates) / count($errorRates), 2),
      'max_error_rate' => max($errorRates),
      'total_requests' => array_sum($requests),
      'uptime_avg' => round(array_sum($uptimes) / count($uptimes), 2),
      'data_points' => count($history)
    ];
  }

  /**
   * Cleans up old data from the database based on the retention policy.
   *
   * This private method is executed after each successful data storage to maintain
   * a clean and manageable database size.
   */
  private function cleanupOldData(): void
  {
    $cutoffTime = time() - ($this->retentionDays * 24 * 3600);

    try {
      $Qquery = $this->db->prepare('DELETE FROM :table_mcp_performance_history
                                     WHERE timestamp < :cutoffTime
                                    ');
      $Qquery->bindInt(':cutoffTime', $cutoffTime);
      $Qquery->execute();
    } catch (\Exception $e) {
      error_log("Failed to cleanup old performance data: " . $e->getMessage());
    }
  }

  /**
   * Parses a human-readable time range string into seconds.
   *
   * @param string $range The time range string (e.g., 'hour', 'day').
   * @return int The time range in seconds.
   */
  private function parseTimeRange(string $range): int
  {
    switch ($range) {
      case 'hour':
        return 3600;
      case 'day':
      case '24h':
        return 86400;
      case 'week':
        return 604800;
      case 'month':
        return 2592000; // 30 days
      default:
        return 86400; // Default to 24 hours
    }
  }

  /**
   * Sets the data retention period in days.
   *
   * @param int $days The number of days to retain data.
   */
  public function setRetentionDays(int $days): void
  {
    $this->retentionDays = max(1, $days);
  }

  /**
   * Gets the current data retention period in days.
   *
   * @return int The number of days.
   */
  public function getRetentionDays(): int
  {
    return $this->retentionDays;
  }

  /**
   * Exports historical performance data in a specified format.
   *
   * @param string $range The time range for the export.
   * @param string $format The export format ('json' or 'csv').
   * @return string The exported data as a string.
   */
  public function exportData(string $range = '24h', string $format = 'json'): string
  {
    $history = $this->getHistory($range);
    $statistics = $this->getStatistics($range);

    $data = [
      'range' => $range,
      'exported_at' => date('Y-m-d H:i:s'),
      'statistics' => $statistics,
      'history' => $history
    ];

    switch ($format) {
      case 'csv':
        return $this->exportToCsv($data);
      case 'json':
      default:
        return json_encode($data, JSON_PRETTY_PRINT);
    }
  }

  /**
   * Converts the historical data into CSV format.
   *
   * @param array $data The data to export.
   * @return string The data in CSV format.
   */
  private function exportToCsv(array $data): string
  {
    $output = "timestamp,latency,error_rate,requests,uptime\n";

    foreach ($data['history'] as $point) {
      $output .= sprintf("%d,%.2f,%.2f,%d,%.2f\n",
        $point['timestamp'],
        $point['latency'],
        $point['error_rate'],
        $point['requests'],
        $point['uptime']
      );
    }

    return $output;
  }
}
