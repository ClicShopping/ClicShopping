<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\AgentEvaluation;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\ReputationHistory;
use DateTimeImmutable;

/**
 * ReputationTracker - Monitors evaluation outcomes and tracks reputation metrics
 * 
 * This class is responsible for:
 * - Listening to evaluation completion events
 * - Recording evaluation outcomes
 * - Calculating consensus alignment
 * - Tracking feedback quality
 * - Measuring consistency
 * 
 * The tracker integrates with the Actor-Critic evaluation system to capture
 * evaluation data and feed it into the reputation calculation pipeline.
 * 
 * Requirements: 1.2, 1.3
 */
class ReputationTracker
{
    private $db;
    private string $prefix;
    private ReputationStore $store;
    private ReputationCalculator $calculator;
    
    /**
     * Constructor
     * 
     * Initializes database connection and dependencies.
     */
    public function __construct()
    {
        $this->db = Registry::get('Db');
        $this->prefix = CLICSHOPPING::getConfig('db_table_prefix', 'Database');
        $this->store = new ReputationStore();
        $this->calculator = new ReputationCalculator();
    }
    
    /**
     * Track an evaluation outcome
     * 
     * This method is called when an evaluation is completed. It records the evaluation
     * outcome and triggers reputation updates.
     * 
     * Requirements: 1.2
     * 
     * @param AgentEvaluation $evaluation The completed evaluation
     * @param float $consensusScore The final consensus score (if available)
     * @param bool $feedbackAccepted Whether the actor accepted the feedback
     * @return void
     */
    public function trackEvaluation(
        AgentEvaluation $evaluation,
        float $consensusScore = null,
        bool $feedbackAccepted = false
    ): void {
        $criticId = $evaluation->getEvaluatorAgentId();
        $evaluationId = $evaluation->getEvaluationId();
        $criticScore = $evaluation->getOverallScore();
        
        // If no consensus score provided, use the critic's score as consensus
        // (This handles single-critic evaluations)
        if ($consensusScore === null) {
            $consensusScore = $criticScore;
        }
        
        // Calculate alignment delta
        $alignmentDelta = abs($criticScore - $consensusScore);
        $withinThreshold = $alignmentDelta < 0.1;
        
        // Record the evaluation outcome
        $this->recordOutcome(
            evaluationId: $evaluationId,
            criticId: $criticId,
            criticScore: $criticScore,
            consensusScore: $consensusScore,
            withinThreshold: $withinThreshold,
            alignmentDelta: $alignmentDelta,
            feedbackAccepted: $feedbackAccepted
        );
        
        // Trigger reputation update (this will be async in production)
        $this->updateReputationForCritic($criticId);
    }
    
    /**
     * Record an evaluation outcome to the database
     * 
     * Stores the evaluation outcome for later reputation calculation.
     * This creates an audit trail of all evaluations.
     * 
     * Requirements: 1.3
     * 
     * @param string $evaluationId Evaluation identifier
     * @param string $criticId Critic identifier
     * @param float $criticScore Critic's evaluation score
     * @param float $consensusScore Final consensus score
     * @param bool $withinThreshold Whether score was within 0.1 of consensus
     * @param float $alignmentDelta Absolute difference between scores
     * @param bool $feedbackAccepted Whether feedback was accepted
     * @return bool True if successful
     */
    public function recordOutcome(
        string $evaluationId,
        string $criticId,
        float $criticScore,
        float $consensusScore,
        bool $withinThreshold,
        float $alignmentDelta,
        bool $feedbackAccepted
    ): bool {
        $sql = "
            INSERT INTO :table_rag_agent_reputation_evaluation_outcomes (
                evaluation_id,
                critic_id,
                critic_score,
                consensus_score,
                within_threshold,
                alignment_delta,
                feedback_accepted,
                evaluated_at
            ) VALUES (
                :evaluation_id,
                :critic_id,
                :critic_score,
                :consensus_score,
                :within_threshold,
                :alignment_delta,
                :feedback_accepted,
                NOW()
            )
        ";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'evaluation_id' => $evaluationId,
                'critic_id' => $criticId,
                'critic_score' => $criticScore,
                'consensus_score' => $consensusScore,
                'within_threshold' => $withinThreshold ? 1 : 0,
                'alignment_delta' => $alignmentDelta,
                'feedback_accepted' => $feedbackAccepted ? 1 : 0
            ]);
            
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            // Log error but don't fail the evaluation
            error_log("ReputationTracker: Failed to record outcome - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update reputation for a critic based on their evaluation history
     * 
     * This method:
     * 1. Retrieves recent evaluation outcomes
     * 2. Calculates reputation metrics
     * 3. Computes new reputation score
     * 4. Saves updated reputation
     * 5. Records history entry
     * 
     * @param string $criticId Critic identifier
     * @return void
     */
    private function updateReputationForCritic(string $criticId): void
    {
        try {
            // Get current reputation or initialize new one
            $currentReputation = $this->store->getReputation($criticId);
            $oldReputationScore = $currentReputation ? $currentReputation->reputationScore : 0.75;
            
            // Get recent evaluation outcomes (last 30 days)
            $outcomes = $this->getRecentOutcomes($criticId, 30);
            
            if (empty($outcomes)) {
                // No outcomes yet, initialize with default reputation
                if (!$currentReputation) {
                    $this->initializeReputation($criticId);
                }
                return;
            }
            
            // Calculate reputation metrics
            $consensusAlignment = $this->calculator->calculateConsensusAlignment($outcomes);
            $feedbackQuality = $this->calculator->calculateFeedbackQuality($outcomes);
            $consistency = $this->calculator->calculateConsistency(
                array_column($outcomes, 'critic_score')
            );
            
            // For now, use neutral expertise accuracy (will be enhanced later)
            $expertiseAccuracy = 0.5;
            
            // Calculate new reputation score
            $newReputationScore = $this->calculator->calculate(
                $consensusAlignment,
                $feedbackQuality,
                $consistency,
                $expertiseAccuracy
            );
            
            // Determine status based on total evaluations
            $totalEvaluations = count($outcomes);
            $status = $this->calculator->determineStatus($totalEvaluations);
            
            // Create updated reputation score object
            $reputation = new \ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\ReputationScore();
            $reputation->criticId = $criticId;
            $reputation->reputationScore = $newReputationScore;
            $reputation->consensusAlignment = $consensusAlignment;
            $reputation->feedbackQuality = $feedbackQuality;
            $reputation->consistencyScore = $consistency;
            $reputation->expertiseAccuracy = $expertiseAccuracy;
            $reputation->totalEvaluations = $totalEvaluations;
            $reputation->status = $status;
            $reputation->calculatedAt = new DateTimeImmutable();
            $reputation->lastDecayAt = $currentReputation ? $currentReputation->lastDecayAt : new DateTimeImmutable();
            
            // Save updated reputation
            $this->store->saveReputation($reputation);
            
            // Record history entry for the most recent evaluation
            $latestOutcome = $outcomes[0]; // Outcomes are ordered by date DESC
            $this->recordHistoryEntry(
                $criticId,
                $latestOutcome['evaluation_id'],
                $latestOutcome['consensus_score'],
                $latestOutcome['critic_score'],
                $latestOutcome['alignment_delta'],
                $oldReputationScore,
                $newReputationScore
            );
            
        } catch (\Exception $e) {
            // Log error but don't fail the evaluation
            error_log("ReputationTracker: Failed to update reputation for {$criticId} - " . $e->getMessage());
        }
    }
    
    /**
     * Get recent evaluation outcomes for a critic
     * 
     * @param string $criticId Critic identifier
     * @param int $days Number of days to look back
     * @return array Array of evaluation outcomes
     */
    private function getRecentOutcomes(string $criticId, int $days): array
    {
        $sql = "
            SELECT 
                evaluation_id,
                critic_score,
                consensus_score,
                within_threshold,
                alignment_delta,
                feedback_accepted,
                evaluated_at
            FROM :table_rag_agent_reputation_evaluation_outcomes
            WHERE critic_id = :critic_id
              AND evaluated_at > DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY evaluated_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'critic_id' => $criticId,
            'days' => $days
        ]);
        
        $outcomes = [];
        while ($row = $stmt->fetch()) {
            $outcomes[] = [
                'evaluation_id' => $row['evaluation_id'],
                'critic_score' => (float)$row['critic_score'],
                'consensus_score' => (float)$row['consensus_score'],
                'within_threshold' => (bool)$row['within_threshold'],
                'alignment_delta' => (float)$row['alignment_delta'],
                'accepted' => (bool)$row['feedback_accepted'],
                'evaluated_at' => $row['evaluated_at']
            ];
        }
        
        return $outcomes;
    }
    
    /**
     * Initialize reputation for a new critic
     * 
     * Creates initial reputation record with default values.
     * 
     * @param string $criticId Critic identifier
     * @return void
     */
    private function initializeReputation(string $criticId): void
    {
        $reputation = new \ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\ReputationScore();
        $reputation->criticId = $criticId;
        $reputation->reputationScore = 0.75; // Default initial reputation
        $reputation->consensusAlignment = 0.5;
        $reputation->feedbackQuality = 0.5;
        $reputation->consistencyScore = 0.5;
        $reputation->expertiseAccuracy = 0.5;
        $reputation->totalEvaluations = 0;
        $reputation->status = 'bootstrapping';
        $reputation->calculatedAt = new DateTimeImmutable();
        $reputation->lastDecayAt = new DateTimeImmutable();
        
        $this->store->saveReputation($reputation);
    }
    
    /**
     * Record a reputation history entry
     * 
     * Creates an audit trail entry showing how reputation changed.
     * 
     * @param string $criticId Critic identifier
     * @param string $evaluationId Evaluation that triggered the change
     * @param float $consensusScore Consensus score
     * @param float $criticScore Critic's score
     * @param float $alignmentDelta Alignment delta
     * @param float $oldReputation Previous reputation score
     * @param float $newReputation New reputation score
     * @return void
     */
    private function recordHistoryEntry(
        string $criticId,
        string $evaluationId,
        float $consensusScore,
        float $criticScore,
        float $alignmentDelta,
        float $oldReputation,
        float $newReputation
    ): void {
        $history = new ReputationHistory();
        $history->criticId = $criticId;
        $history->evaluationId = $evaluationId;
        $history->consensusScore = $consensusScore;
        $history->criticScore = $criticScore;
        $history->alignmentDelta = $alignmentDelta;
        $history->reputationImpact = $newReputation - $oldReputation;
        $history->oldReputation = $oldReputation;
        $history->newReputation = $newReputation;
        $history->recordedAt = new DateTimeImmutable();
        
        $this->store->saveHistory($history);
    }
    
    /**
     * Get evaluation statistics for a critic
     * 
     * Returns summary statistics about a critic's evaluation history.
     * 
     * @param string $criticId Critic identifier
     * @param int $days Number of days to analyze
     * @return array Statistics array
     */
    public function getEvaluationStatistics(string $criticId, int $days = 30): array
    {
        $outcomes = $this->getRecentOutcomes($criticId, $days);
        
        if (empty($outcomes)) {
            return [
                'total_evaluations' => 0,
                'consensus_alignment' => 0.5,
                'feedback_quality' => 0.5,
                'consistency' => 0.5,
                'average_score' => 0.5
            ];
        }
        
        $scores = array_column($outcomes, 'critic_score');
        
        return [
            'total_evaluations' => count($outcomes),
            'consensus_alignment' => $this->calculator->calculateConsensusAlignment($outcomes),
            'feedback_quality' => $this->calculator->calculateFeedbackQuality($outcomes),
            'consistency' => $this->calculator->calculateConsistency($scores),
            'average_score' => array_sum($scores) / count($scores)
        ];
    }
}
