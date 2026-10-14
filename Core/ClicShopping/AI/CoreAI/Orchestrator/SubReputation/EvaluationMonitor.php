<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation;

use ClicShopping\OM\Registry;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\EvaluationOutcome;

/**
 * Evaluation Monitor
 * 
 * Monitors evaluation completion events and queues reputation update jobs.
 * Integrates with the Actor-Critic architecture to track evaluation outcomes
 * and trigger reputation calculations asynchronously.
 * 
 * Requirements: 1.2, 2.5
 */
class EvaluationMonitor
{
    private $db;
    private ReputationTracker $reputationTracker;
    private array $eventLog = [];
    private bool $asyncEnabled = true;
    
    /**
     * Constructor
     * 
     * @param ReputationTracker $reputationTracker Tracker for reputation updates
     * @param bool $asyncEnabled Enable async job queueing (default: true)
     */
    public function __construct(
        ReputationTracker $reputationTracker,
        bool $asyncEnabled = true
    ) {
        $this->db = Registry::get('Db');
        $this->reputationTracker = $reputationTracker;
        $this->asyncEnabled = $asyncEnabled;
    }
    
    /**
     * Handle evaluation completion event
     * 
     * Called when a critic completes an evaluation. Records the evaluation outcome
     * and queues a reputation update job for asynchronous processing.
     * 
     * Requirements: 1.2, 2.5
     * 
     * @param string $evaluationId Evaluation ID
     * @param string $criticId Critic who performed evaluation
     * @param float $criticScore Score given by critic (0.0-1.0)
     * @param float $consensusScore Final consensus score (0.0-1.0)
     * @param bool $feedbackAccepted Whether actor accepted feedback
     * @param array $metadata Additional evaluation metadata
     * @return void
     */
    public function onEvaluationComplete(
        string $evaluationId,
        string $criticId,
        float $criticScore,
        float $consensusScore,
        bool $feedbackAccepted = false,
        array $metadata = []
    ): void {
        $startTime = microtime(true);
        
        try {
            // Create evaluation outcome
            $outcome = new EvaluationOutcome(
                evaluationId: $evaluationId,
                criticId: $criticId,
                criticScore: $criticScore,
                consensusScore: $consensusScore,
                withinThreshold: abs($criticScore - $consensusScore) < 0.1,
                alignmentDelta: abs($criticScore - $consensusScore),
                feedbackAccepted: $feedbackAccepted,
                evaluatedAt: new \DateTimeImmutable(),
                metadata: $metadata
            );
            
            // Log event
            $this->logEvent($outcome);
            
            // Queue reputation update job
            if ($this->asyncEnabled) {
                $this->queueReputationUpdate($outcome);
            } else {
                // Synchronous update for testing
                $this->reputationTracker->trackEvaluation($outcome);
            }
            
            $processingTime = (microtime(true) - $startTime) * 1000; // Convert to ms
            
            error_log(sprintf(
                "EvaluationMonitor: Evaluation %s completed - Critic: %s, Score: %.3f, Consensus: %.3f, Delta: %.3f, Time: %.2fms",
                $evaluationId,
                $criticId,
                $criticScore,
                $consensusScore,
                $outcome->alignmentDelta,
                $processingTime
            ));
            
        } catch (\Exception $e) {
            error_log("EvaluationMonitor: Failed to handle evaluation completion: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            // Don't throw - monitoring failure shouldn't break evaluation workflow
        }
    }
    
    /**
     * Queue reputation update job for asynchronous processing
     * 
     * Creates a job entry in the reputation update queue. Jobs are processed
     * by a background worker to avoid blocking the evaluation workflow.
     * 
     * Requirement 2.5: Update reputation after each evaluation
     * 
     * @param EvaluationOutcome $outcome Evaluation outcome to process
     * @return void
     */
    private function queueReputationUpdate(EvaluationOutcome $outcome): void
    {
        try {
            $jobId = uniqid('job_', true);
            
            $sql = "
                INSERT INTO :table_rag_agent_reputation_update_queue (
                    job_id, critic_id, evaluation_id,
                    critic_score, consensus_score, alignment_delta,
                    feedback_accepted, metadata, status,
                    created_at, attempts
                ) VALUES (
                    :job_id, :critic_id, :evaluation_id,
                    :critic_score, :consensus_score, :alignment_delta,
                    :feedback_accepted, :metadata, 'pending',
                    NOW(), 0
                )
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'job_id' => $jobId,
                'critic_id' => $outcome->criticId,
                'evaluation_id' => $outcome->evaluationId,
                'critic_score' => $outcome->criticScore,
                'consensus_score' => $outcome->consensusScore,
                'alignment_delta' => $outcome->alignmentDelta,
                'feedback_accepted' => $outcome->feedbackAccepted ? 1 : 0,
                'metadata' => json_encode($outcome->metadata)
            ]);
            
            error_log("EvaluationMonitor: Queued reputation update job {$jobId} for critic {$outcome->criticId}");
            
        } catch (\Exception $e) {
            error_log("EvaluationMonitor: Failed to queue reputation update: " . $e->getMessage());
            // Fall back to synchronous update
            try {
                $this->reputationTracker->trackEvaluation($outcome);
                error_log("EvaluationMonitor: Fell back to synchronous reputation update");
            } catch (\Exception $e2) {
                error_log("EvaluationMonitor: Synchronous fallback also failed: " . $e2->getMessage());
            }
        }
    }
    
    /**
     * Log evaluation event for monitoring and analysis
     * 
     * @param EvaluationOutcome $outcome Evaluation outcome
     * @return void
     */
    private function logEvent(EvaluationOutcome $outcome): void
    {
        $event = [
            'evaluation_id' => $outcome->evaluationId,
            'critic_id' => $outcome->criticId,
            'critic_score' => $outcome->criticScore,
            'consensus_score' => $outcome->consensusScore,
            'alignment_delta' => $outcome->alignmentDelta,
            'within_threshold' => $outcome->withinThreshold,
            'feedback_accepted' => $outcome->feedbackAccepted,
            'timestamp' => $outcome->evaluatedAt->format('Y-m-d H:i:s')
        ];
        
        $this->eventLog[] = $event;
        
        // Keep only last 1000 events in memory
        if (count($this->eventLog) > 1000) {
            array_shift($this->eventLog);
        }
    }
    
    /**
     * Process pending reputation update jobs
     * 
     * Processes jobs from the queue in batches. Should be called by a background
     * worker or cron job. Implements retry logic with exponential backoff.
     * 
     * @param int $batchSize Number of jobs to process (default: 10)
     * @param int $maxAttempts Maximum retry attempts (default: 3)
     * @return array Processing results
     */
    public function processPendingJobs(int $batchSize = 10, int $maxAttempts = 3): array
    {
        $startTime = microtime(true);
        $processed = 0;
        $failed = 0;
        $skipped = 0;
        
        try {
            // Get pending jobs
            $sql = "
                SELECT job_id, critic_id, evaluation_id,
                       critic_score, consensus_score, alignment_delta,
                       feedback_accepted, metadata, attempts
                FROM :table_rag_agent_reputation_update_queue
                WHERE status = 'pending'
                  AND attempts < :max_attempts
                ORDER BY created_at ASC
                LIMIT :batch_size
                FOR UPDATE
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'max_attempts' => $maxAttempts,
                'batch_size' => $batchSize
            ]);
            
            $jobs = $stmt->fetchAll();
            
            foreach ($jobs as $job) {
                try {
                    // Create outcome from job data
                    $outcome = new EvaluationOutcome(
                        evaluationId: $job['evaluation_id'],
                        criticId: $job['critic_id'],
                        criticScore: (float)$job['critic_score'],
                        consensusScore: (float)$job['consensus_score'],
                        withinThreshold: abs($job['critic_score'] - $job['consensus_score']) < 0.1,
                        alignmentDelta: (float)$job['alignment_delta'],
                        feedbackAccepted: (bool)$job['feedback_accepted'],
                        evaluatedAt: new \DateTimeImmutable(),
                        metadata: json_decode($job['metadata'], true) ?? []
                    );
                    
                    // Process reputation update
                    $this->reputationTracker->trackEvaluation($outcome);
                    
                    // Mark job as completed
                    $this->markJobCompleted($job['job_id']);
                    $processed++;
                    
                } catch (\Exception $e) {
                    error_log("EvaluationMonitor: Job {$job['job_id']} failed: " . $e->getMessage());
                    
                    // Increment attempts
                    $attempts = (int)$job['attempts'] + 1;
                    
                    if ($attempts >= $maxAttempts) {
                        // Move to dead letter queue
                        $this->markJobFailed($job['job_id'], $e->getMessage());
                        $failed++;
                    } else {
                        // Retry with exponential backoff
                        $this->markJobForRetry($job['job_id'], $attempts);
                        $skipped++;
                    }
                }
            }
            
            $processingTime = (microtime(true) - $startTime) * 1000;
            
            error_log(sprintf(
                "EvaluationMonitor: Processed %d jobs, %d failed, %d skipped in %.2fms",
                $processed,
                $failed,
                $skipped,
                $processingTime
            ));
            
            return [
                'processed' => $processed,
                'failed' => $failed,
                'skipped' => $skipped,
                'processing_time_ms' => $processingTime
            ];
            
        } catch (\Exception $e) {
            error_log("EvaluationMonitor: Failed to process pending jobs: " . $e->getMessage());
            return [
                'processed' => $processed,
                'failed' => $failed,
                'skipped' => $skipped,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Mark job as completed
     * 
     * @param string $jobId Job ID
     * @return void
     */
    private function markJobCompleted(string $jobId): void
    {
        $sql = "
            UPDATE :table_rag_agent_reputation_update_queue
            SET status = 'completed',
                completed_at = NOW()
            WHERE job_id = :job_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['job_id' => $jobId]);
    }
    
    /**
     * Mark job for retry
     * 
     * @param string $jobId Job ID
     * @param int $attempts Current attempt count
     * @return void
     */
    private function markJobForRetry(string $jobId, int $attempts): void
    {
        // Calculate next retry time with exponential backoff
        $backoffSeconds = pow(2, $attempts) * 60; // 2min, 4min, 8min
        
        $sql = "
            UPDATE :table_rag_agent_reputation_update_queue
            SET attempts = :attempts,
                next_retry_at = DATE_ADD(NOW(), INTERVAL :backoff SECOND)
            WHERE job_id = :job_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'job_id' => $jobId,
            'attempts' => $attempts,
            'backoff' => $backoffSeconds
        ]);
    }
    
    /**
     * Mark job as failed and move to dead letter queue
     * 
     * @param string $jobId Job ID
     * @param string $errorMessage Error message
     * @return void
     */
    private function markJobFailed(string $jobId, string $errorMessage): void
    {
        $sql = "
            UPDATE :table_rag_agent_reputation_update_queue
            SET status = 'failed',
                error_message = :error_message,
                failed_at = NOW()
            WHERE job_id = :job_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'job_id' => $jobId,
            'error_message' => $errorMessage
        ]);
        
        error_log("EvaluationMonitor: Job {$jobId} moved to dead letter queue: {$errorMessage}");
    }
    
    /**
     * Get event log for monitoring
     * 
     * @param int $limit Number of recent events to return
     * @return array Event log entries
     */
    public function getEventLog(int $limit = 100): array
    {
        return array_slice($this->eventLog, -$limit);
    }
    
    /**
     * Get queue statistics
     * 
     * @return array Queue statistics
     */
    public function getQueueStatistics(): array
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_jobs,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    AVG(CASE WHEN status = 'completed' 
                        THEN TIMESTAMPDIFF(SECOND, created_at, completed_at) 
                        ELSE NULL END) as avg_processing_time_seconds
                FROM :table_rag_agent_reputation_update_queue
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch();
            
            return [
                'total_jobs_24h' => (int)$stats['total_jobs'],
                'pending' => (int)$stats['pending'],
                'completed' => (int)$stats['completed'],
                'failed' => (int)$stats['failed'],
                'avg_processing_time_seconds' => (float)$stats['avg_processing_time_seconds'],
                'success_rate' => $stats['total_jobs'] > 0 
                    ? ($stats['completed'] / $stats['total_jobs']) * 100 
                    : 0.0
            ];
        } catch (\Exception $e) {
            error_log("EvaluationMonitor: Failed to get queue statistics: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean up old completed jobs
     * 
     * @param int $daysToKeep Number of days to keep completed jobs (default: 7)
     * @return int Number of jobs deleted
     */
    public function cleanupOldJobs(int $daysToKeep = 7): int
    {
        try {
            $sql = "
                DELETE FROM :table_rag_agent_reputation_update_queue
                WHERE status = 'completed'
                  AND completed_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['days' => $daysToKeep]);
            
            $deleted = $stmt->rowCount();
            error_log("EvaluationMonitor: Cleaned up {$deleted} old completed jobs");
            
            return $deleted;
        } catch (\Exception $e) {
            error_log("EvaluationMonitor: Failed to cleanup old jobs: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Enable or disable async job queueing
     * 
     * @param bool $enabled Enable async mode
     * @return void
     */
    public function setAsyncEnabled(bool $enabled): void
    {
        $this->asyncEnabled = $enabled;
        error_log("EvaluationMonitor: Async mode " . ($enabled ? "enabled" : "disabled"));
    }
}
