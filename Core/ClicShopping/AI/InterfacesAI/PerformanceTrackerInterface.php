<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * PerformanceTrackerInterface
 *
 * Interface for query-level performance tracking in the AI orchestration system.
 * This interface defines the contract for tracking execution markers, latency metrics,
 * and parallel execution performance during query processing.
 *
 * IMPORTANT DISTINCTION:
 * - PerformanceTracker (this interface): Query-level tracking
 *   Purpose: Track individual query execution with markers, latency, parallel execution
 *   Scope: Single query lifecycle from start to completion
 *   Location: Core/ClicShopping/AI/Infrastructure/Monitoring/PerformanceTracker.php
 *
 * - PerformanceMonitor (existing): Operation-level monitoring
 *   Purpose: Aggregate statistics across operations, cache hit rates, dashboard data
 *   Scope: System-wide performance monitoring and reporting
 *   Location: Core/ClicShopping/AI/Infrastructure/Monitoring/PerformanceMonitor.php
 *
 * Purpose:
 * - Track query execution lifecycle with precise timing
 * - Record execution markers for detailed performance breakdown
 * - Monitor parallel execution efficiency (context + translation)
 * - Collect latency metrics for performance analysis
 * - Enable performance debugging and optimization
 * - Support performance-based routing decisions
 *
 * Architecture Flow:
 * Query Start → startTracking() → addMarker() → ... → addMarker() → stopTracking() → getLatencyMetrics()
 *
 * Tracking Strategy:
 * 1. Start tracking at query entry point
 * 2. Add markers at key execution points (intent analysis, domain routing, agent execution)
 * 3. Track parallel execution when applicable (context retrieval + translation)
 * 4. Stop tracking at query completion
 * 5. Log performance breakdown for analysis
 * 6. Collect latency metrics for dashboard
 *
 * Use Cases:
 * - Performance debugging: Identify slow execution points
 * - Optimization: Measure impact of performance improvements
 * - Monitoring: Track query latency trends over time
 * - Capacity planning: Understand resource usage patterns
 * - SLA compliance: Verify query execution within acceptable time

 */
 
interface PerformanceTrackerInterface
{
  /**
   * Start tracking query execution
   *
   * Initializes performance tracking for a query by recording the start time
   * and resetting any previous tracking state. This method should be called
   * at the beginning of query processing.
   *
   * The start time is recorded with microsecond precision using microtime(true)
   * to enable accurate latency measurements.
   *
   * Tracking state includes:
   * - Start timestamp (float, microseconds)
   * - Markers array (empty at start)
   * - Events array (empty at start)
   * - Status (tracking in progress)
   *
   * Use cases:
   * - Query entry point in OrchestratorAgent::execute()
   * - Beginning of agent execution
   * - Start of domain processing
   * - Beginning of tool execution
   *
   * Examples:
   * - $tracker->startTracking() → 1714492800.123456 (start time)
   * - Called at beginning of OrchestratorAgent::execute()
   * - Called before intent analysis
   * - Called before domain routing
   *
   * @return float Start time in seconds with microseconds (microtime(true))
   */
  public function startTracking(): float;

  /**
   * Add execution marker
   *
   * Records a named marker at the current point in execution.
   * Markers are used to create a detailed performance breakdown
   * showing time spent in each execution phase.
   *
   * Marker information includes:
   * - Marker name (string identifier)
   * - Timestamp (float, microseconds since start)
   * - Elapsed time since start (float, milliseconds)
   * - Elapsed time since previous marker (float, milliseconds)
   *
   * Common marker names:
   * - 'intent_analysis_start': Beginning of intent classification
   * - 'intent_analysis_complete': End of intent classification
   * - 'domain_routing_start': Beginning of domain routing
   * - 'domain_routing_complete': End of domain routing
   * - 'agent_execution_start': Beginning of agent execution
   * - 'agent_execution_complete': End of agent execution
   * - 'context_retrieval_start': Beginning of context retrieval
   * - 'context_retrieval_complete': End of context retrieval
   * - 'translation_start': Beginning of translation
   * - 'translation_complete': End of translation
   * - 'validation_start': Beginning of validation
   * - 'validation_complete': End of validation
   *
   * Use cases:
   * - Performance breakdown: Identify time spent in each phase
   * - Bottleneck detection: Find slow execution points
   * - Optimization validation: Measure improvement impact
   * - Debugging: Trace execution flow with timing
   *
   * Examples:
   * - $tracker->addMarker('intent_analysis_start')
   * - $tracker->addMarker('domain_routing_complete')
   * - $tracker->addMarker('agent_execution_start')
   * - $tracker->addMarker('validation_complete')
   *
   * @param string $markerName Descriptive name for the marker
   * @return void
   */
  public function addMarker(string $markerName): void;

  /**
   * Stop tracking and record final status
   *
   * Completes performance tracking by recording the end time and final status.
   * Returns the total execution time in seconds.
   *
   * Status values:
   * - 'success': Query completed successfully
   * - 'error': Query failed with error
   * - 'timeout': Query exceeded time limit
   * - 'cancelled': Query was cancelled
   * - 'partial': Query completed with partial results
   *
   * The method calculates:
   * - Total execution time (end time - start time)
   * - Final marker (if not already added)
   * - Tracking completion timestamp
   *
   * After calling stopTracking():
   * - getElapsedTime() returns total execution time
   * - getMarkers() returns all recorded markers
   * - getLatencyMetrics() returns complete metrics
   * - logPerformanceBreakdown() can be called for logging
   *
   * Use cases:
   * - Query completion: Record final execution time
   * - Error handling: Record failure with timing
   * - Timeout handling: Record timeout with partial timing
   * - Performance logging: Trigger performance breakdown logging
   *
   * Examples:
   * - $tracker->stopTracking('success') → 0.523 (execution time in seconds)
   * - $tracker->stopTracking('error') → 0.123 (time until error)
   * - $tracker->stopTracking('timeout') → 5.000 (timeout threshold)
   * - Called at end of OrchestratorAgent::execute()
   *
   * @param string $status Final execution status
   * @return float Total execution time in seconds
   */
  public function stopTracking(string $status): float;

  /**
   * Log performance breakdown
   *
   * Logs detailed performance breakdown showing time spent in each execution phase.
   * This method should be called after stopTracking() to log the complete
   * performance profile of the query.
   *
   * Logged information includes:
   * - Total execution time
   * - Time spent in each phase (between markers)
   * - Percentage of total time per phase
   * - Marker timestamps and deltas
   * - Parallel execution efficiency (if applicable)
   * - Latency metrics summary
   *
   * Log format example:
   * ```
   * Performance Breakdown:
   *   Total: 523ms
   *   Intent Analysis: 45ms (8.6%)
   *   Domain Routing: 12ms (2.3%)
   *   Agent Execution: 420ms (80.3%)
   *   Validation: 46ms (8.8%)
   * ```
   *
   * Use cases:
   * - Performance debugging: Identify slow phases
   * - Optimization: Measure improvement impact
   * - Monitoring: Track performance trends
   * - Alerting: Detect performance degradation
   *
   * Examples:
   * - $tracker->logPerformanceBreakdown() (logs to SecurityLogger)
   * - Called after stopTracking() in debug mode
   * - Called when execution time exceeds threshold
   * - Called for performance analysis
   *
   * @return void
   */
  public function logPerformanceBreakdown(): void;

  /**
   * Track parallel execution performance
   *
   * Records performance metrics for parallel execution of context retrieval
   * and translation. Calculates efficiency gain from parallelization.
   *
   * Parallel execution occurs when:
   * - Context retrieval and translation can run simultaneously
   * - Both operations are independent
   * - Parallel execution is enabled in configuration
   *
   * Metrics calculated:
   * - Context duration: Time for context retrieval alone
   * - Translation duration: Time for translation alone
   * - Parallel duration: Actual time for both operations in parallel
   * - Sequential duration: Estimated time if run sequentially (context + translation)
   * - Time saved: Sequential duration - parallel duration
   * - Efficiency gain: (Time saved / sequential duration) * 100%
   *
   * Return structure:
   * [
   *     'context_duration_ms' => float,      // Context retrieval time
   *     'translation_duration_ms' => float,  // Translation time
   *     'parallel_duration_ms' => float,     // Actual parallel execution time
   *     'sequential_duration_ms' => float,   // Estimated sequential time
   *     'time_saved_ms' => float,            // Time saved by parallelization
   *     'efficiency_gain_percent' => float   // Efficiency gain percentage
   * ]
   *
   * Use cases:
   * - Parallel execution validation: Verify parallelization benefit
   * - Performance optimization: Measure parallel execution efficiency
   * - Capacity planning: Understand parallel execution patterns
   * - Configuration tuning: Decide when to enable parallel execution
   *
   * Examples:
   * - $tracker->trackParallelExecution(120.5, 95.3, 125.8)
   *   → ['time_saved_ms' => 90.0, 'efficiency_gain_percent' => 41.7]
   * - Called when parallel execution is used
   * - Called for performance analysis
   * - Called for optimization validation
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
  ): array;

  /**
   * Get latency metrics for dashboard
   *
   * Returns comprehensive latency metrics for the current query.
   * Used for performance monitoring, dashboard display, and analysis.
   *
   * Metrics structure:
   * [
   *     'total_duration_ms' => float,        // Total execution time
   *     'start_time' => float,               // Start timestamp
   *     'end_time' => float,                 // End timestamp
   *     'status' => string,                  // Final status
   *     'markers' => array,                  // All execution markers
   *     'phase_durations' => array,          // Time per phase
   *     'parallel_execution' => array|null,  // Parallel execution metrics (if applicable)
   *     'events' => array                    // Recorded events
   * ]
   *
   * Phase durations structure:
   * [
   *     'intent_analysis' => float,          // Intent analysis time
   *     'domain_routing' => float,           // Domain routing time
   *     'agent_execution' => float,          // Agent execution time
   *     'validation' => float,               // Validation time
   *     'other' => float                     // Unaccounted time
   * ]
   *
   * Use cases:
   * - Dashboard display: Show query performance metrics
   * - Performance monitoring: Track latency trends
   * - Alerting: Detect slow queries
   * - Analysis: Identify performance bottlenecks
   * - Reporting: Generate performance reports
   *
   * Examples:
   * - $tracker->getLatencyMetrics() → ['total_duration_ms' => 523.45, ...]
   * - Called after stopTracking() for metrics collection
   * - Called by OrchestratorAgent for dashboard data
   * - Called by monitoring systems for alerting
   *
   * @return array Comprehensive latency metrics
   */
  public function getLatencyMetrics(): array;

  /**
   * Record custom event
   *
   * Records a custom event with metadata for detailed tracking.
   * Events are used to capture important execution points beyond standard markers.
   *
   * Event metadata may include:
   * - 'duration_ms': Event duration
   * - 'status': Event status (success, error, warning)
   * - 'details': Event-specific details
   * - 'error': Error message (if applicable)
   * - 'count': Event count (for repeated events)
   * - 'resource': Resource identifier
   * - 'operation': Operation type
   *
   * Common event names:
   * - 'cache_hit': Cache hit occurred
   * - 'cache_miss': Cache miss occurred
   * - 'fallback_triggered': Fallback mechanism activated
   * - 'retry_attempted': Retry attempted
   * - 'validation_failed': Validation failed
   * - 'hallucination_detected': Hallucination detected
   * - 'confidence_low': Low confidence score
   * - 'timeout_warning': Approaching timeout
   *
   * Use cases:
   * - Event tracking: Record important execution events
   * - Debugging: Capture detailed execution context
   * - Monitoring: Track event frequency and patterns
   * - Analysis: Correlate events with performance
   *
   * Examples:
   * - $tracker->recordEvent('cache_hit', ['key' => 'query_123'])
   * - $tracker->recordEvent('fallback_triggered', ['reason' => 'low_confidence'])
   * - $tracker->recordEvent('validation_failed', ['error' => 'Invalid SQL'])
   * - $tracker->recordEvent('hallucination_detected', ['type' => 'revenue_bias'])
   *
   * @param string $eventName Event name identifier
   * @param array $metadata Event metadata
   * @return void
   */
  public function recordEvent(string $eventName, array $metadata = []): void;

  /**
   * Get all execution markers
   *
   * Returns all markers recorded during query execution.
   * Used for performance analysis and debugging.
   *
   * Marker structure:
   * [
   *     [
   *         'name' => string,                // Marker name
   *         'timestamp' => float,            // Absolute timestamp
   *         'elapsed_ms' => float,           // Time since start
   *         'delta_ms' => float              // Time since previous marker
   *     ],
   *     ...
   * ]
   *
   * Use cases:
   * - Performance analysis: Analyze execution flow
   * - Debugging: Trace execution with timing
   * - Visualization: Create performance timeline
   * - Reporting: Generate execution reports
   *
   * Examples:
   * - $tracker->getMarkers() → [['name' => 'intent_analysis_start', ...], ...]
   * - Called after stopTracking() for analysis
   * - Called for performance visualization
   * - Called for debugging
   *
   * @return array Array of marker objects
   */
  public function getMarkers(): array;

  /**
   * Get tracking start time
   *
   * Returns the timestamp when tracking started.
   * Used for calculating elapsed time and marker timestamps.
   *
   * Use cases:
   * - Time calculation: Calculate elapsed time
   * - Marker timestamps: Calculate marker timing
   * - Debugging: Verify tracking state
   * - Logging: Include start time in logs
   *
   * Examples:
   * - $tracker->getStartTime() → 1714492800.123456
   * - Called for elapsed time calculation
   * - Called for marker timestamp calculation
   * - Called for debugging
   *
   * @return float Start time in seconds with microseconds (microtime(true))
   */
  public function getStartTime(): float;

  /**
   * Get elapsed time since tracking started
   *
   * Returns the time elapsed since startTracking() was called.
   * If tracking has stopped, returns the total execution time.
   * If tracking is still in progress, returns the current elapsed time.
   *
   * Use cases:
   * - Progress monitoring: Check current execution time
   * - Timeout detection: Verify execution within time limit
   * - Performance logging: Log intermediate timing
   * - Debugging: Monitor execution progress
   *
   * Examples:
   * - $tracker->getElapsedTime() → 0.523 (during execution)
   * - $tracker->getElapsedTime() → 0.523 (after stopTracking)
   * - Called for timeout checking
   * - Called for progress monitoring
   *
   * @return float Elapsed time in seconds
   */
  public function getElapsedTime(): float;

  /**
   * Reset tracking state
   *
   * Resets all tracking state to initial values.
   * Used to reuse the tracker for a new query without creating a new instance.
   *
   * Reset operations:
   * - Clear start time
   * - Clear end time
   * - Clear all markers
   * - Clear all events
   * - Clear status
   * - Clear parallel execution metrics
   *
   * Use cases:
   * - Tracker reuse: Reuse tracker for new query
   * - Testing: Reset state between tests
   * - Error recovery: Reset after error
   * - Memory management: Clear accumulated data
   *
   * Examples:
   * - $tracker->reset() (clears all state)
   * - Called before starting new query
   * - Called in error recovery
   * - Called in testing teardown
   *
   * @return void
   */
  public function reset(): void;
}
