<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Monitoring;


use ClicShopping\AI\Security\SecurityLogger;

/**
 * MetricsCollector Class
 *
 * Real-time metrics collector that:
 * - Intercepts system events
 * - Collects performance metrics
 * - Aggregates data in real-time
 * - Notifies the MonitoringAgent
 * - Supports custom metrics
 */

class MetricsCollector
{
  private SecurityLogger $logger;
  private ?MonitoringAgent $monitoringAgent = null;
  private bool $debug;

  // Buffer de métriques avant envoi
  private array $metricsBuffer = [];
  private int $bufferSize = 10;

  // Timers pour mesurer la durée
  private array $timers = [];

  // Compteurs
  private array $counters = [];

  // Gauges (valeurs instantanées)
  private array $gauges = [];

  // Histogrammes (distributions)
  private array $histograms = [];

  /**
   * Constructor
   *
   * @param MonitoringAgent|null $monitoringAgent Instance du MonitoringAgent
   */
  public function __construct(?MonitoringAgent $monitoringAgent = null)
  {
    $this->logger = new SecurityLogger();
    $this->monitoringAgent = $monitoringAgent;
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    if ($this->debug) {
      $this->logger->logSecurityEvent("MetricsCollector initialized", 'info');
    }
  }

  /**
   * Starts a timer to measure duration
   *
   * @param string $name Timer name
   * @param array $tags Optional tags
   */
  public function startTimer(string $name, array $tags = []): void
  {
    $this->timers[$name] = [
      'start' => microtime(true),
      'tags' => $tags,
    ];
  }

  /**
   * Stops a timer and records the metric
   *
   * @param string $name Timer name
   * @return float|null Elapsed time or null if timer not found
   */
  public function stopTimer(string $name): ?float
  {
    if (!isset($this->timers[$name])) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Timer not found: {$name}",
          'warning'
        );
      }
      return null;
    }

    $elapsed = microtime(true) - $this->timers[$name]['start'];
    $tags = $this->timers[$name]['tags'];

    // Record in histogram
    $this->recordHistogram($name, $elapsed, $tags);

    // Clean up timer
    unset($this->timers[$name]);

    return $elapsed;
  }

  /**
   * Increments a counter
   *
   * @param string $name Counter name
   * @param int $value Value to add (default: 1)
   * @param array $tags Optional tags
   */
  public function increment(string $name, int $value = 1, array $tags = []): void
  {
    $key = $this->buildKey($name, $tags);

    if (!isset($this->counters[$key])) {
      $this->counters[$key] = [
        'name' => $name,
        'value' => 0,
        'tags' => $tags,
      ];
    }

    $this->counters[$key]['value'] += $value;

    $this->checkBuffer();
  }

  /**
   * Decrements a counter
   *
   * @param string $name Counter name
   * @param int $value Value to subtract (default: 1)
   * @param array $tags Optional tags
   */
  public function decrement(string $name, int $value = 1, array $tags = []): void
  {
    $this->increment($name, -$value, $tags);
  }

  /**
   * Records a gauge (instantaneous value)
   *
   * @param string $name Gauge name
   * @param float $value Value
   * @param array $tags Optional tags
   */
  public function gauge(string $name, float $value, array $tags = []): void
  {
    $key = $this->buildKey($name, $tags);

    $this->gauges[$key] = [
      'name' => $name,
      'value' => $value,
      'tags' => $tags,
      'timestamp' => time(),
    ];

    $this->checkBuffer();
  }

  /**
   * Records a value in a histogram
   *
   * @param string $name Histogram name
   * @param float $value Value
   * @param array $tags Optional tags
   */
  public function recordHistogram(string $name, float $value, array $tags = []): void
  {
    $key = $this->buildKey($name, $tags);

    if (!isset($this->histograms[$key])) {
      $this->histograms[$key] = [
        'name' => $name,
        'values' => [],
        'tags' => $tags,
      ];
    }

    $this->histograms[$key]['values'][] = $value;

    // Limit histogram size
    if (count($this->histograms[$key]['values']) > 1000) {
      array_shift($this->histograms[$key]['values']);
    }

    $this->checkBuffer();
  }

  /**
   * Records an event with metrics
   *
   * @param string $eventType Event type
   * @param array $metrics Associated metrics
   */
  public function recordEvent(string $eventType, array $metrics = []): void
  {
    $event = [
      'type' => $eventType,
      'timestamp' => microtime(true),
      'metrics' => $metrics,
    ];

    $this->metricsBuffer[] = $event;

    // Notify MonitoringAgent
    if ($this->monitoringAgent) {
      $this->monitoringAgent->recordEvent($eventType, $metrics);
    }

    $this->checkBuffer();
  }

  /**
   * Measures function execution
   *
   * @param string $name Metric name
   * @param callable $callback Function to measure
   * @param array $tags Optional tags
   * @return mixed Function result
   */
  public function measure(string $name, callable $callback, array $tags = [])
  {
    $this->startTimer($name, $tags);

    try {
      $result = $callback();
      $this->increment("{$name}.success", 1, $tags);
      return $result;
    } catch (\Exception $e) {
      $this->increment("{$name}.error", 1, $tags);
      throw $e;
    } finally {
      $elapsed = $this->stopTimer($name);

      if ($this->debug && $elapsed !== null) {
        $this->logger->logSecurityEvent(
          "Measured {$name}: " . round($elapsed * 1000, 2) . "ms",
          'info'
        );
      }
    }
  }

  /**
   * Gets histogram statistics
   *
   * @param string $name Histogram name
   * @param array $tags Optional tags
   * @return array|null Statistics or null
   */
  public function getHistogramStats(string $name, array $tags = []): ?array
  {
    $key = $this->buildKey($name, $tags);

    if (!isset($this->histograms[$key]) || empty($this->histograms[$key]['values'])) {
      return null;
    }

    $values = $this->histograms[$key]['values'];
    sort($values);

    $count = count($values);
    $sum = array_sum($values);
    $mean = $sum / $count;

    // Calculate median
    $middle = floor($count / 2);
    if ($count % 2 === 0) {
      $median = ($values[$middle - 1] + $values[$middle]) / 2;
    } else {
      $median = $values[$middle];
    }

    // Percentiles
    $p50 = $this->percentile($values, 50);
    $p75 = $this->percentile($values, 75);
    $p90 = $this->percentile($values, 90);
    $p95 = $this->percentile($values, 95);
    $p99 = $this->percentile($values, 99);

    // Standard deviation
    $variance = 0;
    foreach ($values as $value) {
      $variance += pow($value - $mean, 2);
    }
    $stddev = sqrt($variance / $count);

    return [
      'count' => $count,
      'sum' => round($sum, 4),
      'min' => round(min($values), 4),
      'max' => round(max($values), 4),
      'mean' => round($mean, 4),
      'median' => round($median, 4),
      'stddev' => round($stddev, 4),
      'percentiles' => [
        'p50' => round($p50, 4),
        'p75' => round($p75, 4),
        'p90' => round($p90, 4),
        'p95' => round($p95, 4),
        'p99' => round($p99, 4),
      ],
    ];
  }

  /**
   * Calculates a percentile
   * 
   * @param array $sortedValues Sorted values
   * @param int $percentile Percentile to calculate
   * @return float Percentile value
   */
  private function percentile(array $sortedValues, int $percentile): float
  {
    $count = count($sortedValues);
    $index = ($percentile / 100) * ($count - 1);
    $lower = floor($index);
    $upper = ceil($index);

    if ($lower === $upper) {
      return $sortedValues[$lower];
    }

    $weight = $index - $lower;
    return $sortedValues[$lower] * (1 - $weight) + $sortedValues[$upper] * $weight;
  }

  /**
   * Gets all collected metrics
   *
   * @return array All metrics
   */
  public function getAllMetrics(): array
  {
    return [
      'counters' => $this->counters,
      'gauges' => $this->gauges,
      'histograms' => $this->getHistogramsWithStats(),
      'active_timers' => count($this->timers),
    ];
  }

  /**
   * Gets histograms with their statistics
   * 
   * @return array Histograms with stats
   */
  private function getHistogramsWithStats(): array
  {
    $result = [];

    foreach ($this->histograms as $key => $histogram) {
      $stats = $this->getHistogramStats($histogram['name'], $histogram['tags']);

      $result[$key] = [
        'name' => $histogram['name'],
        'tags' => $histogram['tags'],
        'stats' => $stats,
      ];
    }

    return $result;
  }

  /**
   * Builds a unique key for a metric with tags
   * 
   * @param string $name Metric name
   * @param array $tags Tags
   * @return string Unique key
   */
  private function buildKey(string $name, array $tags): string
  {
    if (empty($tags)) {
      return $name;
    }

    ksort($tags);
    $tagString = http_build_query($tags, '', '&');

    return $name . '#' . md5($tagString);
  }

  /**
   * Checks if buffer should be flushed
   */
  private function checkBuffer(): void
  {
    if (count($this->metricsBuffer) >= $this->bufferSize) {
      $this->flush();
    }
  }

  /**
   * Flushes buffer and sends metrics
   */
  public function flush(): void
  {
    if (empty($this->metricsBuffer)) {
      return;
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Flushing " . count($this->metricsBuffer) . " metrics",
        'info'
      );
    }

    // Process buffer
    foreach ($this->metricsBuffer as $metric) {
      // Here we could send to external system (StatsD, Prometheus, etc.)
      // For now, just log
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Metric: " . json_encode($metric),
          'debug'
        );
      }
    }

    // Clear buffer
    $this->metricsBuffer = [];
  }

  /**
   * Gets metrics summary
   * 
   * @return array Metrics summary
   */
  public function getSummary(): array
  {
    return [
      'total_counters' => count($this->counters),
      'total_gauges' => count($this->gauges),
      'total_histograms' => count($this->histograms),
      'active_timers' => count($this->timers),
      'buffer_size' => count($this->metricsBuffer),
      'top_counters' => $this->getTopCounters(5),
      'recent_gauges' => $this->getRecentGauges(5),
    ];
  }

  /**
   * Gets top counters
   * 
   * @param int $limit Maximum number of counters
   * @return array Top counters
   */
  private function getTopCounters(int $limit): array
  {
    $counters = $this->counters;

    usort($counters, fn($a, $b) => $b['value'] <=> $a['value']);

    return array_slice($counters, 0, $limit);
  }

  /**
   * Gets recent gauges
   * 
   * @param int $limit Maximum number of gauges
   * @return array Recent gauges
   */
  private function getRecentGauges(int $limit): array
  {
    $gauges = $this->gauges;

    usort($gauges, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

    return array_slice($gauges, 0, $limit);
  }

  /**
   * Resets all metrics
   */
  public function reset(): void
  {
    $this->counters = [];
    $this->gauges = [];
    $this->histograms = [];
    $this->timers = [];
    $this->metricsBuffer = [];

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "All metrics reset",
        'info'
      );
    }
  }

  /**
   * Records a custom metric
   *
   * @param string $type Metric type (counter, gauge, histogram)
   * @param string $name Metric name
   * @param mixed $value Value
   * @param array $tags Optional tags
   */
  public function recordCustomMetric(string $type, string $name, $value, array $tags = []): void
  {
    switch ($type) {
      case 'counter':
        $this->increment($name, (int)$value, $tags);
        break;

      case 'gauge':
        $this->gauge($name, (float)$value, $tags);
        break;

      case 'histogram':
        $this->recordHistogram($name, (float)$value, $tags);
        break;

      default:
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Unknown metric type: {$type}",
            'warning'
          );
        }
    }
  }

  /**
   * 
   * Simplified method to record latency and other metrics.
   * Automatically detects metric type based on name.
   *
   * @param string $name Metric name (e.g. 'orchestrator_query_latency_ms')
   * @param float $value Metric value
   * @param array $tags Optional tags for filtering (e.g. ['status' => 'success', 'fast_lane' => 'true'])
   */
  public function recordMetric(string $name, float $value, array $tags = []): void
  {
    // Determine metric type based on name
    if (str_contains($name, '_latency') || str_contains($name, '_time') || str_contains($name, '_duration')) {
      // Time metrics → histogram for statistics
      $this->recordHistogram($name, $value, $tags);
    } elseif (str_contains($name, '_count') || str_contains($name, '_total')) {
      // Count metrics → counter
      $this->increment($name, (int)$value, $tags);
    } else {
      // Default → gauge (instantaneous value)
      $this->gauge($name, $value, $tags);
    }

    if ($this->debug) {
      $tagsStr = empty($tags) ? '' : ' [' . json_encode($tags) . ']';
      $this->logger->logSecurityEvent(
        "Metric recorded: {$name} = {$value}{$tagsStr}",
        'debug'
      );
    }
  }

  /**
   * Exports metrics in Prometheus format
   * 
   * @return string Prometheus formatted metrics
   */
  public function exportPrometheus(): string
  {
    $output = [];

    // Counters
    foreach ($this->counters as $counter) {
      $name = str_replace('.', '_', $counter['name']);
      $tags = $this->formatPrometheusTags($counter['tags']);
      $output[] = "{$name}{$tags} {$counter['value']}";
    }

    // Gauges
    foreach ($this->gauges as $gauge) {
      $name = str_replace('.', '_', $gauge['name']);
      $tags = $this->formatPrometheusTags($gauge['tags']);
      $output[] = "{$name}{$tags} {$gauge['value']}";
    }

    // Histograms
    foreach ($this->histograms as $histogram) {
      $name = str_replace('.', '_', $histogram['name']);
      $stats = $this->getHistogramStats($histogram['name'], $histogram['tags']);

      if ($stats) {
        $tags = $this->formatPrometheusTags($histogram['tags']);
        $output[] = "{$name}_count{$tags} {$stats['count']}";
        $output[] = "{$name}_sum{$tags} {$stats['sum']}";
        $output[] = "{$name}_min{$tags} {$stats['min']}";
        $output[] = "{$name}_max{$tags} {$stats['max']}";
        $output[] = "{$name}_avg{$tags} {$stats['mean']}";
      }
    }

    return implode("\n", $output);
  }

  /**
   * Formats tags in Prometheus format
   * 
   * @param array $tags Tags to format
   * @return string Formatted tags
   */
  private function formatPrometheusTags(array $tags): string
  {
    if (empty($tags)) {
      return '';
    }

    $formatted = [];
    foreach ($tags as $key => $value) {
      $formatted[] = "{$key}=\"{$value}\"";
    }

    return '{' . implode(',', $formatted) . '}';
  }

  /**
   * Exports metrics in StatsD format
   * 
   * @return array StatsD formatted metrics
   */
  public function exportStatsD(): array
  {
    $output = [];

    // Counters
    foreach ($this->counters as $counter) {
      $output[] = "{$counter['name']}:{$counter['value']}|c";
    }

    // Gauges
    foreach ($this->gauges as $gauge) {
      $output[] = "{$gauge['name']}:{$gauge['value']}|g";
    }

    // Histograms (as timing)
    foreach ($this->histograms as $histogram) {
      $stats = $this->getHistogramStats($histogram['name'], $histogram['tags']);

      if ($stats) {
        $output[] = "{$histogram['name']}:{$stats['mean']}|ms";
      }
    }

    return $output;
  }

  /**
   * Destructor - Flush buffer
   */
  public function __destruct()
  {
    $this->flush();
  }

  /**
   * Updates the MonitoringAgent
   * 
   * @param MonitoringAgent $monitoringAgent MonitoringAgent instance
   */
  public function setMonitoringAgent(MonitoringAgent $monitoringAgent): void
  {
    $this->monitoringAgent = $monitoringAgent;
  }

  /**
   * Gets counter value
   * 
   * @param string $name Counter name
   * @param array $tags Optional tags
   * @return int|null Counter value or null
   */
  public function getCounterValue(string $name, array $tags = []): ?int
  {
    $key = $this->buildKey($name, $tags);
    return $this->counters[$key]['value'] ?? null;
  }

  /**
   * Gets gauge value
   * 
   * @param string $name Gauge name
   * @param array $tags Optional tags
   * @return float|null Gauge value or null
   */
  public function getGaugeValue(string $name, array $tags = []): ?float
  {
    $key = $this->buildKey($name, $tags);
    return $this->gauges[$key]['value'] ?? null;
  }

  /**
   * Checks if a timer is active
   * 
   * @param string $name Timer name
   * @return bool True if timer is active
   */
  public function hasActiveTimer(string $name): bool
  {
    return isset($this->timers[$name]);
  }

  /**
   * Cleans old metrics
   *
   * @param int $maxAge Maximum age in seconds
   */
  public function cleanOldMetrics(int $maxAge = 3600): void
  {
    $cutoff = time() - $maxAge;

    // Clean gauges
    foreach ($this->gauges as $key => $gauge) {
      if ($gauge['timestamp'] < $cutoff) {
        unset($this->gauges[$key]);
      }
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Cleaned old metrics (max age: {$maxAge}s)",
        'info'
      );
    }
  }

}