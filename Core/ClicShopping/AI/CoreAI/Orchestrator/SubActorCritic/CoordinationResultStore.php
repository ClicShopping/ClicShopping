<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

use ClicShopping\OM\CLICSHOPPING;
use Exception;

/**
 * CoordinationResultStore
 *
 * Database-persistence concern extracted verbatim from {@see ActorCriticCoordinator}.
 * Owns every DB access of the coordinator: storing coordinated results, reading the
 * 24h coordination statistics, and tracking evaluation-retry attempts. Behaviour is
 * unchanged — same SQL, same fail-soft handling (storage/log failures never bubble up
 * and never fail coordination).
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 */
class CoordinationResultStore
{
    private $db;
    private bool $debug;

    /**
     * @param mixed $db Registry 'Db' connection
     * @param bool $debug Debug logging toggle (inherited from the coordinator)
     */
    public function __construct($db, bool $debug)
    {
        $this->db = $db;
        $this->debug = $debug;
    }

    /**
     * Store coordinated result in database
     *
     * @param CoordinatedResult $result Coordinated result to store
     * @return void
     */
    public function store(CoordinatedResult $result): void
    {
        try {
            // Check if table exists
            if (!$this->tableExists('rag_agent_coordinated_results')) {
                if ($this->debug) {
                    error_log("ActorCriticCoordinator: Table rag_agent_coordinated_results does not exist, skipping storage");
                }
                return;
            }

            $metadata = $result->getMetadata();
            $actionResult = $result->getActionResult();
            $consensus = $result->getConsensus();

            $sql = "INSERT INTO :table_rag_agent_coordinated_results
                    (coordination_id, action_id, result_id, actor_id, consensus_id,
                     consensus_score, num_evaluations, num_critics, execution_time_ms,
                     evaluation_time_ms, total_time_ms, created_at)
                    VALUES (:coordination_id, :action_id, :result_id, :actor_id, :consensus_id,
                            :consensus_score, :num_evaluations, :num_critics, :execution_time_ms,
                            :evaluation_time_ms, :total_time_ms, :created_at)";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':coordination_id', $result->getCoordinationId());
            $stmt->bindValue(':action_id', $actionResult->getActionId());
            $stmt->bindValue(':result_id', $actionResult->getResultId());
            $stmt->bindValue(':actor_id', $metadata['actor_id']);
            $stmt->bindValue(':consensus_id', $consensus->getConsensusId());
            $stmt->bindValue(':consensus_score', $consensus->getScore());
            $stmt->bindValue(':num_evaluations', count($result->getEvaluations()));
            $stmt->bindValue(':num_critics', $metadata['critics_count']);
            $stmt->bindValue(':execution_time_ms', (int)($metadata['execution_time'] * 1000));
            $stmt->bindValue(':evaluation_time_ms', (int)($metadata['evaluation_time'] * 1000));
            $stmt->bindValue(':total_time_ms', (int)($metadata['total_time'] * 1000));
            $stmt->bindValue(':created_at', date('Y-m-d H:i:s'));
            $stmt->execute();

        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ActorCriticCoordinator: Failed to store coordinated result - " . $e->getMessage());
            }
            // Don't throw - storage failure shouldn't fail coordination
        }
    }

    /**
     * Check if a database table exists
     *
     * @param string $tableName The table name (without prefix)
     * @return bool True if table exists
     */
    private function tableExists(string $tableName): bool
    {
        try {
            $prefix = CLICSHOPPING::getConfig('db_table_prefix');
            $fullTableName = $prefix . $tableName;
            $sql = "SHOW TABLES LIKE ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$fullTableName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get coordination statistics
     *
     * @return array Statistics about coordinations
     */
    public function getStatistics(): array
    {
        try {
            if (!$this->tableExists('rag_agent_coordinated_results')) {
                return [
                    'total_coordinations' => 0,
                    'avg_consensus_score' => 0.0,
                    'avg_execution_time_ms' => 0.0,
                    'avg_evaluation_time_ms' => 0.0,
                    'avg_total_time_ms' => 0.0,
                    'avg_critics_per_coordination' => 0.0
                ];
            }

            $sql = "
                SELECT
                    COUNT(*) as total_coordinations,
                    AVG(consensus_score) as avg_consensus_score,
                    AVG(execution_time_ms) as avg_execution_time_ms,
                    AVG(evaluation_time_ms) as avg_evaluation_time_ms,
                    AVG(total_time_ms) as avg_total_time_ms,
                    AVG(num_critics) as avg_critics_per_coordination
                FROM :table_rag_agent_coordinated_results
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'total_coordinations' => (int)$stats['total_coordinations'],
                'avg_consensus_score' => (float)$stats['avg_consensus_score'],
                'avg_execution_time_ms' => (float)$stats['avg_execution_time_ms'],
                'avg_evaluation_time_ms' => (float)$stats['avg_evaluation_time_ms'],
                'avg_total_time_ms' => (float)$stats['avg_total_time_ms'],
                'avg_critics_per_coordination' => (float)$stats['avg_critics_per_coordination']
            ];

        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ActorCriticCoordinator: Failed to get statistics - " . $e->getMessage());
            }
            return [
                'total_coordinations' => 0,
                'avg_consensus_score' => 0.0,
                'avg_execution_time_ms' => 0.0,
                'avg_evaluation_time_ms' => 0.0,
                'avg_total_time_ms' => 0.0,
                'avg_critics_per_coordination' => 0.0
            ];
        }
    }

    /**
     * Log evaluation retry attempt to the tracking table
     *
     * @param string $resultId The action result ID
     * @param string $outputType The output type being evaluated
     * @param string $failedCriticId The critic that failed evaluation
     */
    public function logRetryAttempt(
        string $resultId,
        string $outputType,
        string $failedCriticId
    ): void {
        try {
            $sql = "INSERT INTO :table_rag_evaluation_retries
                    (output_id, output_type, failed_evaluator_id, attempt_number, status, created_at)
                    VALUES (:output_id, :output_type, :failed_evaluator_id, :attempt_number, :status, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':output_id', $resultId);
            $stmt->bindValue(':output_type', $outputType);
            $stmt->bindValue(':failed_evaluator_id', $failedCriticId);
            $stmt->bindValue(':attempt_number', 1);
            $stmt->bindValue(':status', 'attempting');
            $stmt->execute();
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ActorCriticCoordinator: Failed to log evaluation retry - " . $e->getMessage());
            }
        }
    }

    /**
     * Update evaluation retry status
     *
     * @param string $outputId The output ID
     * @param string $retryEvaluatorId The retry evaluator that resolved the retry
     * @param string $status The new status (success or failed)
     */
    public function updateRetryStatus(
        string $outputId,
        string $retryEvaluatorId,
        string $status
    ): void {
        try {
            $sql = "UPDATE :table_rag_evaluation_retries
                    SET status = :status,
                        retry_evaluator_id = :retry_evaluator_id,
                        resolved_at = NOW()
                    WHERE output_id = :output_id
                      AND status = 'attempting'
                    ORDER BY created_at DESC
                    LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':retry_evaluator_id', $retryEvaluatorId);
            $stmt->bindValue(':output_id', $outputId);
            $stmt->execute();
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ActorCriticCoordinator: Failed to update evaluation retry status - " . $e->getMessage());
            }
        }
    }
}
