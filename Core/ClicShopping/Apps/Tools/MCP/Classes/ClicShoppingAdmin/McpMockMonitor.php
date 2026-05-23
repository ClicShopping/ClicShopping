<?php
/*
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin;

/**
 * Class McpMockMonitor
 *
 * This class serves as a mock performance monitor for the MCP (Management & Control Panel) system.
 * It generates simulated performance data, analyzes trends, and provides recommendations based on
 * predefined thresholds. This class is useful for testing and demonstration purposes when
 * a real-time connection to a live MCP service is not available.
 */
class McpMockMonitor
{
  /**
   * @var array The thresholds for generating alerts.
   */
  private array $alertThresholds;

  /**
   * @var array The configuration settings for the monitor.
   */
  private array $config;

  /**
   * @var MpcPerformanceHistoryStorage The storage component for historical performance data.
   */
  private MpcPerformanceHistoryStorage $historyStorage;

  /**
   * McpMockMonitor constructor.
   *
   * Initializes the mock monitor with default configuration and alert thresholds.
   *
   * @param array $config Optional configuration settings.
   */
  public function __construct(array $config = [])
  {
    $this->config = $config;
    $this->historyStorage = new MpcPerformanceHistoryStorage();

    $this->alertThresholds = [
      'error_rate' => 20,
      'latency' => 1000,
      'downtime' => 300
    ];
  }

  /**
   * Sets alert thresholds at runtime.
   *
   * This method allows for dynamic adjustment of the alert thresholds.
   *
   * @param array $thresholds An associative array of new thresholds to merge with the existing ones.
   */
  public function setAlertThresholds(array $thresholds): void
  {
    $this->alertThresholds = array_merge($this->alertThresholds, $thresholds);
  }

  /**
   * Retrieves mock performance data, including current metrics, historical trends, and recommendations.
   *
   * This method simulates the process of gathering performance data from a live system.
   * It generates current metrics, stores them, and analyzes historical data to produce actionable insights.
   *
   * @param string $range The time range for historical data (e.g., '24h', 'week').
   * @return array An array containing 'metrics', 'trends', 'recommendations', 'history', and 'statistics'.
   */
  public function getPerformanceData(string $range = '24h'): array
  {
    // Generate current metrics
    $metrics = [
      'request_rate' => random_int(20, 80),
      'average_latency_ms' => (int) (100 + sqrt(random_int(0, 40000))),
      'error_frequency' => random_int(0, 10),
      'uptime_percentage' => random_int(9900, 10000) / 100,
      'total_requests' => random_int(1000, 5000)
    ];

    // Store current metrics for persistence
    $this->historyStorage->storeMetrics($metrics);

    // Get historical data from storage
    $history = $this->historyStorage->getHistory($range);

    // If no historical data, generate some mock data
    if (empty($history)) {
      $history = $this->generateMockHistory($range);
    }

    // Analyze trends using stored data
    $trends = [
      'latency_trend' => $this->historyStorage->analyzeTrends('latency', $range),
      'error_trend' => $this->historyStorage->analyzeTrends('error_rate', $range),
      'request_trend' => $this->historyStorage->analyzeTrends('requests', $range)
    ];

    // Generate recommendations based on thresholds and trends
    $recommendations = $this->generateRecommendations($metrics, $trends);

    return [
      'metrics' => $metrics,
      'trends' => $trends,
      'recommendations' => $recommendations,
      'history' => $history,
      'statistics' => $this->historyStorage->getStatistics($range)
    ];
  }

  /**
   * Generates mock historical data for a specified time range.
   *
   * This private helper method creates a series of simulated data points to represent
   * past performance.
   *
   * @param string $range The time range for which to generate data.
   * @return array An array of mock data points.
   */
  private function generateMockHistory(string $range): array
  {
    $baseTime = time();
    $history = [];
    $interval = $this->getIntervalForRange($range);

    $points = $this->getPointsForRange($range);

    for ($i = 0; $i < $points; $i++) {
      $timestamp = $baseTime - ($i * $interval);
      $history[] = [
        'timestamp' => $timestamp,
        'latency_ms' => random_int(50, 200),
        'error_rate' => random_int(0, 5) / 100,
        'requests' => random_int(10, 50),
        'uptime_percentage' => random_int(9500, 10000) / 100
      ];
    }

    return array_reverse($history);
  }

  /**
   * Gets the time interval (in seconds) for each data point based on the specified range.
   *
   * @param string $range The time range.
   * @return int The interval in seconds.
   */
  private function getIntervalForRange(string $range): int
  {
    switch ($range) {
      case 'hour':
        return 300; // 5 minutes
      case 'day':
      case '24h':
        return 1800; // 30 minutes
      case 'week':
        return 3600; // 1 hour
      case 'month':
        return 86400; // 1 day
      default:
        return 1800; // Default 30 minutes
    }
  }

  /**
   * Gets the number of data points to generate for a given range.
   *
   * @param string $range The time range.
   * @return int The number of data points.
   */
  private function getPointsForRange(string $range): int
  {
    switch ($range) {
      case 'hour':
        return 12; // 12 points for 1 hour
      case 'day':
      case '24h':
        return 48; // 48 points for 24 hours
      case 'week':
        return 168; // 168 points for 1 week
      case 'month':
        return 30; // 30 points for 1 month
      default:
        return 48;
    }
  }

  /**
   * Generates a list of recommendations based on current metrics and historical trends.
   *
   * This method checks metrics against predefined thresholds and analyzes trends to identify
   * potential issues, categorizing them by priority and type.
   *
   * @param array $metrics Current performance metrics.
   * @param array $trends Historical performance trends.
   * @return array An array of recommendation objects.
   */
  private function generateRecommendations(array $metrics, array $trends): array
  {
    $recommendations = [];

    // Check error rate threshold
    if ($metrics['error_frequency'] > $this->alertThresholds['error_rate']) {
      $recommendations[] = [
        'type' => 'danger',
        'priority' => 'high',
        'message' => 'High error rate detected. Check error logs and system stability.',
        'metric' => 'error_rate',
        'current_value' => $metrics['error_frequency'],
        'threshold' => $this->alertThresholds['error_rate']
      ];
    }

    // Check latency threshold
    if ($metrics['average_latency'] > $this->alertThresholds['latency']) {
      $recommendations[] = [
        'type' => 'warning',
        'priority' => 'medium',
        'message' => 'High latency detected. Consider optimizing network configuration.',
        'metric' => 'latency',
        'current_value' => $metrics['average_latency'],
        'threshold' => $this->alertThresholds['latency']
      ];
    }

    // Check uptime threshold
    if ($metrics['uptime_percentage'] < 99) {
      $recommendations[] = [
        'type' => 'warning',
        'priority' => 'high',
        'message' => 'System uptime below target. Review system stability.',
        'metric' => 'uptime',
        'current_value' => $metrics['uptime_percentage'],
        'threshold' => 99
      ];
    }

    // Add trend-based recommendations
    if ($trends['latency_trend']['direction'] === 'increasing' &&
      $trends['latency_trend']['percentage'] > 20) {
      $recommendations[] = [
        'type' => 'info',
        'priority' => 'medium',
        'message' => 'Latency trend shows significant increase. Monitor system resources.',
        'metric' => 'latency_trend',
        'trend_percentage' => $trends['latency_trend']['percentage']
      ];
    }

    if ($trends['error_trend']['direction'] === 'increasing' &&
      $trends['error_trend']['percentage'] > 15) {
      $recommendations[] = [
        'type' => 'warning',
        'priority' => 'high',
        'message' => 'Error rate trend shows concerning increase. Investigate recent changes.',
        'metric' => 'error_trend',
        'trend_percentage' => $trends['error_trend']['percentage']
      ];
    }

    return $recommendations;
  }

  /**
   * Retrieves historical performance statistics for a given time range.
   *
   * @param string $range The time range (e.g., '24h').
   * @return array An array of performance statistics.
   */
  public function getStatistics(string $range = '24h'): array
  {
    return $this->historyStorage->getStatistics($range);
  }

  /**
   * Exports historical performance data in a specified format.
   *
   * @param string $range The time range for data export.
   * @param string $format The format for the exported data (e.g., 'json').
   * @return string The exported data as a string.
   */
  public function exportData(string $range = '24h', string $format = 'json'): string
  {
    return $this->historyStorage->exportData($range, $format);
  }
}