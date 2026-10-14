<?php
/**
 * DecompositionPerformanceMonitor
 *
 * Responsibility:
 * - Measure execution time of hybrid query decomposition
 * - Detect slow decompositions based on a configurable threshold
 * - Persist performance metrics into RAG statistics storage
 * - Emit security / warning events for anomalous behaviors
 *
 * Scope:
 * - Runtime monitoring only (no orchestration, no optimization logic)
 * - Stateless across requests except for in-flight operations
 *
 * Thread-safety:
 * - Not thread-safe by design; intended for per-request lifecycle usage
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Monitoring;

use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;

class DecompositionPerformanceMonitor
{
    /**
     * Enable verbose internal logging.
     * Used exclusively for debugging environments.
     */
    private bool $debug;

    /**
     * Optional security logger for slow operations and persistence failures.
     */
    private ?SecurityLogger $securityLogger;

    /**
     * Registry of currently running decomposition operations.
     * Keyed by generated operation ID.
     */
    private array $activeOperations = [];

    /**
     * Threshold (in milliseconds) above which a decomposition
     * is considered slow and logged as an anomaly.
     */
    private int $slowOperationThreshold = 500;

    /**
     * Constructor.
     *
     * @param bool $debug Enables debug-level logging
     * @param SecurityLogger|null $securityLogger External security/audit logger
     * @param int $slowOperationThreshold Slow operation threshold in ms
     */
    public function __construct(
        bool $debug = false,
        ?SecurityLogger $securityLogger = null,
        int $slowOperationThreshold = 500
    ) {
        $this->debug = $debug;
        $this->securityLogger = $securityLogger;
        $this->slowOperationThreshold = $slowOperationThreshold;

        if ($this->debug) {
            $this->logDebug(
                "DecompositionPerformanceMonitor initialized (threshold: {$slowOperationThreshold}ms)"
            );
        }
    }

    /**
     * Start tracking a decomposition execution.
     *
     * @param string $query Original user query
     * @param array $intent Parsed intent structure
     *
     * @return string Generated operation identifier
     */
    public function startDecomposition(string $query, array $intent): string
    {
        $operationId = $this->generateOperationId();

        $this->activeOperations[$operationId] = [
            'query' => $query,
            'intent' => $intent,
            'start_time' => microtime(true),
            'sub_types' => $intent['sub_types'] ?? [],
        ];

        if ($this->debug) {
            $this->logDebug("Started decomposition tracking: {$operationId}");
        }

        return $operationId;
    }

    /**
     * Finalize tracking of a decomposition execution.
     * Computes execution time, records metrics, and triggers alerts if needed.
     *
     * @param string $operationId Operation identifier returned by startDecomposition()
     * @param array $result Decomposition result payload
     * @param bool $cacheHit Indicates whether cache was used
     * @param bool $success Indicates successful decomposition
     * @param string|null $errorMessage Optional error description
     */
    public function endDecomposition(
        string $operationId,
        array $result,
        bool $cacheHit = false,
        bool $success = true,
        ?string $errorMessage = null
    ): void {
        // Ignore unknown or already-finalized operations
        if (!isset($this->activeOperations[$operationId])) {
            if ($this->debug) {
                $this->logDebug("Warning: Unknown operation ID: {$operationId}");
            }
            return;
        }

        $operation = $this->activeOperations[$operationId];
        $endTime = microtime(true);

        // Execution duration in milliseconds
        $durationMs = ($endTime - $operation['start_time']) * 1000;

        // Detect and log slow decompositions
        if ($durationMs > $this->slowOperationThreshold) {
            $this->logSlowOperation($operation, $durationMs, $cacheHit);
        }

        // Persist aggregated metrics
        $this->recordMetrics(
            $operation,
            $durationMs,
            $result,
            $cacheHit,
            $success,
            $errorMessage
        );

        // Cleanup operation state
        unset($this->activeOperations[$operationId]);

        if ($this->debug) {
            $this->logDebug(sprintf(
                "Decomposition completed: %s (%.2fms, cache: %s, success: %s)",
                $operationId,
                $durationMs,
                $cacheHit ? 'HIT' : 'MISS',
                $success ? 'YES' : 'NO'
            ));
        }
    }

    /**
     * Log a slow decomposition event.
     * Emits both debug output and security log entries when available.
     *
     * @param array $operation Operation metadata
     * @param float $durationMs Execution time in ms
     * @param bool $cacheHit Cache usage flag
     */
    private function logSlowOperation(array $operation, float $durationMs, bool $cacheHit): void
    {
        $message = sprintf(
            "SLOW DECOMPOSITION: %.2fms (threshold: %dms) - Query: %s - Cache: %s - Sub-types: %s",
            $durationMs,
            $this->slowOperationThreshold,
            substr($operation['query'], 0, 100),
            $cacheHit ? 'HIT' : 'MISS',
            json_encode($operation['sub_types'])
        );

        // Forward anomaly to security logger if configured
        if ($this->securityLogger) {
            $this->securityLogger->logSecurityEvent($message, 'warning', [
                'duration_ms' => $durationMs,
                'threshold_ms' => $this->slowOperationThreshold,
                'cache_hit' => $cacheHit,
                'sub_types' => $operation['sub_types'],
                'query_length' => \strlen($operation['query'])
            ]);
        }

        if ($this->debug) {
            $this->logDebug($message);
        }
    }

    /**
     * Persist decomposition performance metrics into storage.
     *
     * @param array $operation Operation metadata
     * @param float $durationMs Execution time
     * @param array $result Decomposition result (currently unused, reserved)
     * @param bool $cacheHit Cache hit indicator
     * @param bool $success Success flag
     * @param string|null $errorMessage Error message if failure occurred
     */
    private function recordMetrics(
        array $operation,
        float $durationMs,
        array $result,
        bool $cacheHit,
        bool $success,
        ?string $errorMessage
    ): void {
        try {
            $db = Registry::get('Db');

            // Metrics insertion query
            $sql = "INSERT INTO :table_rag_statistics (
                agent_type,
                classification_type,
                response_time_ms,
                cache_hit,
                error_occurred,
                error_type,
                error_message,
                date_added
            ) VALUES (
                :agent_type,
                :classification_type,
                :response_time_ms,
                :cache_hit,
                :error_occurred,
                :error_type,
                :error_message,
                NOW()
            )";

            $sql = $db->prepare($sql);
            $sql->bindValue(':agent_type', 'hybrid_decomposition');
            $sql->bindValue(':classification_type', 'hybrid');
            $sql->bindInt(':response_time_ms', (int) round($durationMs));
            $sql->bindInt(':cache_hit', $cacheHit ? 1 : 0);
            $sql->bindInt(':error_occurred', $success ? 0 : 1);
            $sql->bindValue(':error_type', $success ? null : 'decomposition_failure');
            $sql->bindValue(':error_message', $errorMessage);

            $sql->execute();

            if ($this->debug) {
                $this->logDebug("Metrics recorded to rag_statistics table");
            }
        } catch (\Exception $e) {
            // Swallow persistence failures but emit diagnostic signals
            if ($this->debug) {
                $this->logDebug("Failed to record metrics: " . $e->getMessage());
            }

            if ($this->securityLogger) {
                $this->securityLogger->logSecurityEvent(
                    "Failed to record decomposition metrics",
                    'error',
                    ['error' => $e->getMessage()]
                );
            }
        }
    }

    /**
     * Aggregate performance statistics over a rolling time window.
     *
     * @param int $days Lookback period in days
     *
     * @return array Aggregated metrics snapshot
     */
    public function getPerformanceStats(int $days = 7): array
    {
        try {
            $db = Registry::get('Db');

            $sql = "SELECT 
                COUNT(*) as total_decompositions,
                AVG(response_time_ms) as avg_time_ms,
                MIN(response_time_ms) as min_time_ms,
                MAX(response_time_ms) as max_time_ms,
                SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits,
                SUM(CASE WHEN error_occurred = 1 THEN 1 ELSE 0 END) as errors,
                SUM(CASE WHEN response_time_ms > :threshold THEN 1 ELSE 0 END) as slow_operations
            FROM :table_rag_statistics
            WHERE agent_type = 'hybrid_decomposition'
            AND date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)";

            $sql = $db->prepare($sql);
            $sql->bindInt(':threshold', $this->slowOperationThreshold);
            $sql->bindInt(':days', $days);
            $sql->execute();

            $stats = $sql->fetch();

            if ($stats && $stats['total_decompositions'] > 0) {
                return [
                    'total_decompositions' => (int) $stats['total_decompositions'],
                    'avg_time_ms' => round((float) $stats['avg_time_ms'], 2),
                    'min_time_ms' => (int) $stats['min_time_ms'],
                    'max_time_ms' => (int) $stats['max_time_ms'],
                    'cache_hit_rate' => round(($stats['cache_hits'] / $stats['total_decompositions']) * 100, 2),
                    'error_rate' => round(($stats['errors'] / $stats['total_decompositions']) * 100, 2),
                    'slow_operation_rate' => round(($stats['slow_operations'] / $stats['total_decompositions']) * 100, 2),
                    'period_days' => $days
                ];
            }

            // No data case
            return [
                'total_decompositions' => 0,
                'avg_time_ms' => 0,
                'min_time_ms' => 0,
                'max_time_ms' => 0,
                'cache_hit_rate' => 0,
                'error_rate' => 0,
                'slow_operation_rate' => 0,
                'period_days' => $days
            ];
        } catch (\Exception $e) {
            if ($this->debug) {
                $this->logDebug("Failed to get performance stats: " . $e->getMessage());
            }

            return [
                'error' => $e->getMessage(),
                'total_decompositions' => 0
            ];
        }
    }

    /**
     * Generate a unique identifier for a decomposition operation.
     */
    private function generateOperationId(): string
    {
        return 'decomp_' . uniqid('', true);
    }

    /**
     * Emit debug-level log entry.
     * No-op when debug mode is disabled.
     */
    private function logDebug(string $message): void
    {
        if ($this->debug) {
            error_log("[DecompositionPerformanceMonitor] {$message}");
        }
    }
}

