<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\Infrastructure\Monitoring;

use ClicShopping\AI\InterfacesAI\PerformanceTrackerInterface;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * PerformanceTracker Class
 *
 * Provides query-level performance tracking with detailed execution markers.
 * This class tracks individual query execution lifecycle with precise timing
 * for performance analysis, debugging, and optimization.
 *
 * IMPORTANT DISTINCTION:
 * - PerformanceTracker (this class): Query-level tracking
 *   Purpose: Track individual query execution with markers, latency, parallel execution
 *   Scope: Single query lifecycle from start to completion
 *   State: Transient (reset after each query)
 *
 * - PerformanceMonitor (separate class): Operation-level monitoring
 *   Purpose: Aggregate statistics across operations, cache hit rates, dashboard data
 *   Scope: System-wide performance monitoring and reporting
 *   State: Persistent (saved to cache)
 *
 * Features:
 * - Query lifecycle tracking (start/stop)
 * - Execution markers for detailed phase breakdown
 * - Parallel execution metrics (context + translation)
 * - Latency metrics collection
 * - Custom event recording
 * - Performance breakdown logging
 *
 * Usage:
 * ```php
 * $tracker = new PerformanceTracker($collector, $debug);
 * $tracker->startTracking();
 * $tracker->addMarker('intent_analysis_start');
 * // ... intent analysis ...
 * $tracker->addMarker('intent_analysis_complete');
 * $tracker->addMarker('domain_routing_start');
 * // ... domain routing ...
 * $tracker->addMarker('domain_routing_complete');
 * $tracker->stopTracking('success');
 * $tracker->logPerformanceBreakdown();
 * $metrics = $tracker->getLatencyMetrics();
 * ```
 */
class PerformanceTracker implements PerformanceTrackerInterface
{
  private SecurityLogger $logger;
  private ?MetricsCollector $collector;
  private bool $debug;

  // Tracking state
  private float $startTime = 0.0;
  private float $endTime = 0.0;
  private string $status = '';
  private array $markers = [];
  private array $events = [];
  private ?array $parallelExecution = null;

  /**
   * Constructor
   *
   * @param MetricsCollector|null $collector Metrics collector for histogram stats (optional)
   * @param bool $debug Enable debug logging
   */
  public function __construct(?MetricsCollector $collector = null, bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->collector = $collector;
    $this->debug = $debug;

    if ($this->debug) {
      $this->logger->logStructured(
        'debug',
        'PerformanceTracker',
        'initialized',
        ['has_collector' => $collector !== null]
      );
    }
  }

  /**
   * Start tracking query execution
   *
   * Initializes performance tracking for a query by recording the start time
   * and resetting any previous tracking state.
   *
   * @return float Start time in seconds with microseconds (microtime(true))
   */
  public function startTracking(): float
  {
    $this->startTime = microtime(true);
    $this->endTime = 0.0;
    $this->status = '';
    $this->markers = [];
    $this->events = [];
    $this->parallelExecution = null;

    // Add initial marker
    $this->addMarker('start');

    if ($this->debug) {
      $this->logger->logStructured(
        'debug',
        'PerformanceTracker',
        'tracking_started',
        ['start_time' => $this->startTime]
      );
    }

    return $this->startTime;
  }

  /**
   * Add execution marker
   *
   * Records a named marker at the current point in execution.
   * Markers are used to create a detailed performance breakdown.
   *
   * @param string $markerName Descriptive name for the marker
   * @return void
   */
  public function addMarker(string $markerName): void
  {
    if ($this->startTime === 0.0) {
      if ($this->debug) {
        $this->logger->logStructured(
          'warning',
          'PerformanceTracker',
          'marker_without_start',
          ['marker' => $markerName]
        );
      }
      return;
    }

    $currentTime = microtime(true);
    $elapsedMs = ($currentTime - $this->startTime) * 1000;

    // Calculate delta from previous marker
    $deltaMs = 0.0;
    if (!empty($this->markers)) {
      $lastMarker = end($this->markers);
      $deltaMs = ($currentTime - $lastMarker['timestamp']) * 1000;
    }

    $this->markers[] = [
      'name' => $markerName,
      'timestamp' => $currentTime,
      'elapsed_ms' => round($elapsedMs, 2),
      'delta_ms' => round($deltaMs, 2)
    ];

    if ($this->debug) {
      $this->logger->logStructured(
        'debug',
        'PerformanceTracker',
        'marker_added',
        [
          'marker' => $markerName,
          'elapsed_ms' => round($elapsedMs, 2),
          'delta_ms' => round($deltaMs, 2)
        ]
      );
    }
  }

  /**
   * Stop tracking and record final status
   *
   * Completes performance tracking by recording the end time and final status.
   * Returns the total execution time in seconds.
   *
   * @param string $status Final execution status (success, error, timeout, cancelled, partial)
   * @return float Total execution time in seconds
   */
  public function stopTracking(string $status): float
  {
    if ($this->startTime === 0.0) {
      if ($this->debug) {
        $this->logger->logStructured(
          'warning',
          'PerformanceTracker',
          'stop_without_start',
          ['status' => $status]
        );
      }
      return 0.0;
    }

    $this->endTime = microtime(true);
    $this->status = $status;

    // Add final marker
    $this->addMarker('end');

    $totalTime = $this->endTime - $this->startTime;

    // Record to MetricsCollector if available
    if ($this->collector !== null) {
      $this->collector->recordMetric(
        'orchestrator_query_latency_ms',
        $totalTime * 1000,
        ['status' => $status]
      );
    }

    if ($this->debug) {
      $this->logger->logStructured(
        'info',
        'PerformanceTracker',
        'tracking_stopped',
        [
          'status' => $status,
          'total_time_ms' => round($totalTime * 1000, 2),
          'marker_count' => count($this->markers)
        ]
      );
    }

    return $totalTime;
  }

  /**
   * Log performance breakdown
   *
   * Logs detailed performance breakdown showing time spent in each execution phase.
   *
   * @return void
   */
  public function logPerformanceBreakdown(): void
  {
    if ($this->startTime === 0.0) {
      if ($this->debug) {
        $this->logger->logStructured(
          'warning',
          'PerformanceTracker',
          'breakdown_without_tracking',
          []
        );
      }
      return;
    }

    $totalTime = $this->endTime > 0 ? $this->endTime - $this->startTime : microtime(true) - $this->startTime;
    $totalMs = $totalTime * 1000;

    // Build phase breakdown from markers
    $breakdown = [
      'total_ms' => round($totalMs, 2),
      'status' => $this->status ?: 'in_progress',
      'marker_count' => count($this->markers),
      'phases' => []
    ];

    // Calculate time between markers
    for ($i = 1, $iMax = count($this->markers); $i < $iMax; $i++) {
      $prevMarker = $this->markers[$i - 1];
      $currMarker = $this->markers[$i];

      $phaseName = $prevMarker['name'] . '_to_' . $currMarker['name'];
      $phaseDuration = $currMarker['delta_ms'];
      $phasePercentage = $totalMs > 0 ? round(($phaseDuration / $totalMs) * 100, 1) : 0;

      $breakdown['phases'][] = [
        'name' => $phaseName,
        'duration_ms' => $phaseDuration,
        'percentage' => $phasePercentage
      ];
    }

    // Add parallel execution metrics if available
    if ($this->parallelExecution !== null) {
      $breakdown['parallel_execution'] = $this->parallelExecution;
    }

    // Add event count
    $breakdown['event_count'] = count($this->events);

    $this->logger->logStructured(
      'info',
      'PerformanceTracker',
      'performance_breakdown',
      $breakdown
    );
  }

  /**
   * Track parallel execution performance
   *
   * Records performance metrics for parallel execution of context retrieval
   * and translation. Calculates efficiency gain from parallelization.
   *
   * @param float $contextDuration Context retrieval duration in milliseconds
   * @param float $translationDuration Translation duration in milliseconds
   * @param float $parallelDuration Actual parallel execution duration in milliseconds
   * @return array Parallel execution metrics
   */
  public function trackParallelExecution(
    float $contextDuration,
    float $translationDuration,
    float $parallelDuration
  ): array {
    // Calculate sequential duration (if run sequentially)
    $sequentialDuration = $contextDuration + $translationDuration;

    // Calculate time saved
    $timeSaved = $sequentialDuration - $parallelDuration;

    // Calculate efficiency gain percentage
    $efficiencyGain = $sequentialDuration > 0
      ? round(($timeSaved / $sequentialDuration) * 100, 1)
      : 0.0;

    $this->parallelExecution = [
      'context_duration_ms' => round($contextDuration, 2),
      'translation_duration_ms' => round($translationDuration, 2),
      'parallel_duration_ms' => round($parallelDuration, 2),
      'sequential_duration_ms' => round($sequentialDuration, 2),
      'time_saved_ms' => round($timeSaved, 2),
      'efficiency_gain_percent' => $efficiencyGain
    ];

    if ($this->debug) {
      $this->logger->logStructured(
        'info',
        'PerformanceTracker',
        'parallel_execution_tracked',
        $this->parallelExecution
      );
    }

    return $this->parallelExecution;
  }

  /**
   * Get latency metrics for dashboard
   *
   * Returns comprehensive latency metrics for the current query.
   *
   * @return array Comprehensive latency metrics
   */
  public function getLatencyMetrics(): array
  {
    $totalDuration = 0.0;
    if ($this->endTime > 0) {
      $totalDuration = ($this->endTime - $this->startTime) * 1000;
    } elseif ($this->startTime > 0) {
      $totalDuration = (microtime(true) - $this->startTime) * 1000;
    }

    // Build phase durations from markers
    $phaseDurations = [];
    for ($i = 1, $iMax = count($this->markers); $i < $iMax; $i++) {
      $prevMarker = $this->markers[$i - 1];
      $currMarker = $this->markers[$i];
      $phaseName = $prevMarker['name'] . '_to_' . $currMarker['name'];
      $phaseDurations[$phaseName] = $currMarker['delta_ms'];
    }

    $metrics = [
      'total_duration_ms' => round($totalDuration, 2),
      'start_time' => $this->startTime,
      'end_time' => $this->endTime,
      'status' => $this->status ?: 'in_progress',
      'markers' => $this->markers,
      'phase_durations' => $phaseDurations,
      'parallel_execution' => $this->parallelExecution,
      'events' => $this->events
    ];

    // Add histogram stats from MetricsCollector if available
    if ($this->collector !== null) {
      $histogramStats = $this->collector->getHistogramStats('orchestrator_query_latency_ms');
      if ($histogramStats !== null) {
        $metrics['overall'] = $histogramStats;
      }
    }

    return $metrics;
  }

  /**
   * Record custom event
   *
   * Records a custom event with metadata for detailed tracking.
   *
   * @param string $eventName Event name identifier
   * @param array $metadata Event metadata
   * @return void
   */
  public function recordEvent(string $eventName, array $metadata = []): void
  {
    $currentTime = microtime(true);
    $elapsedMs = $this->startTime > 0 ? ($currentTime - $this->startTime) * 1000 : 0.0;

    $this->events[] = [
      'name' => $eventName,
      'timestamp' => $currentTime,
      'elapsed_ms' => round($elapsedMs, 2),
      'metadata' => $metadata
    ];

    if ($this->debug) {
      $this->logger->logStructured(
        'debug',
        'PerformanceTracker',
        'event_recorded',
        [
          'event' => $eventName,
          'elapsed_ms' => round($elapsedMs, 2),
          'metadata' => $metadata
        ]
      );
    }
  }

  /**
   * Get all execution markers
   *
   * Returns all markers recorded during query execution.
   *
   * @return array Array of marker objects
   */
  public function getMarkers(): array
  {
    return $this->markers;
  }

  /**
   * Get tracking start time
   *
   * Returns the timestamp when tracking started.
   *
   * @return float Start time in seconds with microseconds (microtime(true))
   */
  public function getStartTime(): float
  {
    return $this->startTime;
  }

  /**
   * Get elapsed time since tracking started
   *
   * Returns the time elapsed since startTracking() was called.
   * If tracking has stopped, returns the total execution time.
   * If tracking is still in progress, returns the current elapsed time.
   *
   * @return float Elapsed time in seconds
   */
  public function getElapsedTime(): float
  {
    if ($this->startTime === 0.0) {
      return 0.0;
    }

    if ($this->endTime > 0) {
      // Tracking stopped, return total time
      return $this->endTime - $this->startTime;
    }

    // Tracking in progress, return current elapsed time
    return microtime(true) - $this->startTime;
  }

  /**
   * Reset tracking state
   *
   * Resets all tracking state to initial values.
   *
   * @return void
   */
  public function reset(): void
  {
    $this->startTime = 0.0;
    $this->endTime = 0.0;
    $this->status = '';
    $this->markers = [];
    $this->events = [];
    $this->parallelExecution = null;

    if ($this->debug) {
      $this->logger->logStructured(
        'debug',
        'PerformanceTracker',
        'reset',
        []
      );
    }
  }
}
