<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation;

use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\ReputationScore;

/**
 * ReputationCalculator - Calculates reputation scores from tracked metrics
 * 
 * Implements the reputation formula and handles reputation decay, bootstrapping logic,
 * and bounds enforcement. This is the core calculation engine for the reputation system.
 * 
 * Reputation Formula:
 * Reputation = (0.4 × Consensus_Alignment + 0.3 × Feedback_Quality + 0.2 × Consistency + 0.1 × Expertise_Accuracy)
 * 
 * Requirements: 1.6, 2.1, 2.2, 2.3, 2.4, 5.2, 5.3, 5.4
 */
class ReputationCalculator
{
    // Reputation formula weights
    private const WEIGHT_CONSENSUS_ALIGNMENT = 0.4;
    private const WEIGHT_FEEDBACK_QUALITY = 0.3;
    private const WEIGHT_CONSISTENCY = 0.2;
    private const WEIGHT_EXPERTISE_ACCURACY = 0.1;
    
    // Reputation bounds
    private const MIN_REPUTATION = 0.5;
    private const MAX_REPUTATION = 1.0;
    
    // Status thresholds
    private const BOOTSTRAPPING_THRESHOLD = 10;
    private const ESTABLISHING_THRESHOLD = 50;
    
    /**
     * Calculate reputation score from component metrics
     * 
     * Requirements: 1.6
     * 
     * @param float $consensusAlignment Consensus alignment metric (0.0-1.0)
     * @param float $feedbackQuality Feedback quality metric (0.0-1.0)
     * @param float $consistency Consistency metric (0.0-1.0)
     * @param float $expertiseAccuracy Expertise accuracy metric (0.0-1.0)
     * @return float Reputation score (0.5-1.0)
     */
    public function calculate(
        float $consensusAlignment,
        float $feedbackQuality,
        float $consistency,
        float $expertiseAccuracy
    ): float {
        // Apply reputation formula
        $reputation = (
            self::WEIGHT_CONSENSUS_ALIGNMENT * $consensusAlignment +
            self::WEIGHT_FEEDBACK_QUALITY * $feedbackQuality +
            self::WEIGHT_CONSISTENCY * $consistency +
            self::WEIGHT_EXPERTISE_ACCURACY * $expertiseAccuracy
        );
        
        // Enforce bounds
        return $this->enforceBounds($reputation);
    }
    
    /**
     * Calculate consensus alignment from evaluation history
     * 
     * Consensus alignment is the percentage of evaluations where the critic's score
     * was within 0.1 of the final consensus score.
     * 
     * Requirements: 2.1
     * 
     * @param array $evaluations Array of evaluations with 'critic_score' and 'consensus_score'
     * @return float Consensus alignment (0.0-1.0)
     */
    public function calculateConsensusAlignment(array $evaluations): float
    {
        if (empty($evaluations)) {
            return 0.5; // Neutral if no evaluations
        }
        
        $withinThreshold = 0;
        $total = count($evaluations);
        
        foreach ($evaluations as $eval) {
            $delta = abs($eval['critic_score'] - $eval['consensus_score']);
            if ($delta < 0.1) {
                $withinThreshold++;
            }
        }
        
        return $withinThreshold / $total;
    }
    
    /**
     * Calculate feedback quality from feedback history
     * 
     * Feedback quality is the percentage of feedback that was accepted by actors.
     * 
     * Requirements: 2.2
     * 
     * @param array $feedbacks Array of feedbacks with 'accepted' boolean
     * @return float Feedback quality (0.0-1.0)
     */
    public function calculateFeedbackQuality(array $feedbacks): float
    {
        if (empty($feedbacks)) {
            return 0.5; // Neutral if no feedback
        }
        
        $accepted = 0;
        $total = count($feedbacks);
        
        foreach ($feedbacks as $feedback) {
            if ($feedback['accepted']) {
                $accepted++;
            }
        }
        
        return $accepted / $total;
    }
    
    /**
     * Calculate consistency from evaluation scores
     * 
     * Consistency is calculated as (1 - coefficient_of_variation) where
     * coefficient_of_variation = standard_deviation / mean.
     * 
     * Requirements: 2.3
     * 
     * @param array $scores Array of evaluation scores
     * @return float Consistency score (0.0-1.0)
     */
    public function calculateConsistency(array $scores): float
    {
        if (empty($scores)) {
            return 0.5; // Neutral if no scores
        }
        
        if (count($scores) === 1) {
            return 1.0; // Perfect consistency with single score
        }
        
        // Calculate mean
        $mean = array_sum($scores) / count($scores);
        
        if ($mean == 0) {
            return 0.5; // Neutral if mean is zero
        }
        
        // Calculate variance
        $variance = 0;
        foreach ($scores as $score) {
            $variance += pow($score - $mean, 2);
        }
        $variance /= count($scores);
        
        // Calculate standard deviation
        $stdDev = sqrt($variance);
        
        // Calculate coefficient of variation
        $cv = $stdDev / $mean;
        
        // Consistency = 1 - CV (bounded to [0, 1])
        $consistency = 1 - $cv;
        
        return max(0.0, min(1.0, $consistency));
    }
    
    /**
     * Calculate expertise accuracy from evaluations within expertise domains
     * 
     * Expertise accuracy is the percentage of accurate evaluations within
     * the critic's claimed expertise domains.
     * 
     * Requirements: 2.4
     * 
     * @param array $expertiseEvaluations Array of evaluations within expertise domains
     * @return float Expertise accuracy (0.0-1.0)
     */
    public function calculateExpertiseAccuracy(array $expertiseEvaluations): float
    {
        if (empty($expertiseEvaluations)) {
            return 0.5; // Neutral if no expertise evaluations
        }
        
        $accurate = 0;
        $total = count($expertiseEvaluations);
        
        foreach ($expertiseEvaluations as $eval) {
            $delta = abs($eval['critic_score'] - $eval['consensus_score']);
            if ($delta < 0.1) {
                $accurate++;
            }
        }
        
        return $accurate / $total;
    }
    
    /**
     * Determine critic status based on total evaluations
     * 
     * Status classification:
     * - bootstrapping: < 10 evaluations
     * - establishing: 10-49 evaluations
     * - established: >= 50 evaluations
     * 
     * Requirements: 5.2, 5.3, 5.4
     * 
     * @param int $totalEvaluations Total number of evaluations
     * @return string Status ('bootstrapping', 'establishing', 'established')
     */
    public function determineStatus(int $totalEvaluations): string
    {
        if ($totalEvaluations < self::BOOTSTRAPPING_THRESHOLD) {
            return 'bootstrapping';
        } elseif ($totalEvaluations < self::ESTABLISHING_THRESHOLD) {
            return 'establishing';
        } else {
            return 'established';
        }
    }
    
    /**
     * Apply reputation decay formula
     * 
     * Decay formula: Reputation(t) = Reputation(t-1) × 0.95 + Recent_Performance × 0.05
     * 
     * Requirements: 4.1, 4.3, 4.4, 4.5
     * 
     * @param float $oldReputation Previous reputation score
     * @param float $recentPerformance Recent performance score (0.0-1.0)
     * @param int $periods Number of decay periods elapsed
     * @param float $decayFactor Decay factor (default: 0.95)
     * @return float New reputation score after decay (0.5-1.0)
     */
    public function applyDecay(
        float $oldReputation,
        float $recentPerformance,
        int $periods = 1,
        float $decayFactor = 0.95
    ): float {
        // Validate inputs
        if ($periods < 0) {
            error_log("ReputationCalculator: Invalid periods ({$periods}), using 0");
            $periods = 0;
        }
        
        if ($periods === 0) {
            return $oldReputation; // No decay
        }
        
        // Apply decay formula: Reputation(t) = Reputation(t-1) × decay^periods + Recent_Performance × (1 - decay^periods)
        $decayMultiplier = pow($decayFactor, $periods);
        $recentMultiplier = 1 - $decayMultiplier;
        
        $newReputation = ($oldReputation * $decayMultiplier) + ($recentPerformance * $recentMultiplier);
        
        // Enforce bounds [0.5, 1.0]
        $boundedReputation = $this->enforceBounds($newReputation);
        
        // Log decay operation (Requirement 4.5)
        $this->logDecay($oldReputation, $boundedReputation, $periods, $recentPerformance);
        
        return $boundedReputation;
    }
    
    /**
     * Log reputation decay operation
     * 
     * Requirements: 4.5
     * 
     * @param float $oldScore Old reputation score
     * @param float $newScore New reputation score after decay
     * @param int $periods Number of decay periods
     * @param float $recentPerformance Recent performance score
     * @return void
     */
    private function logDecay(
        float $oldScore,
        float $newScore,
        int $periods,
        float $recentPerformance
    ): void {
        $change = $newScore - $oldScore;
        $changePercent = $oldScore > 0 ? ($change / $oldScore) * 100 : 0;
        
        error_log(sprintf(
            "[ReputationDecay] Applied decay: old=%.3f, new=%.3f, change=%.3f (%.1f%%), periods=%d, recent_perf=%.3f",
            $oldScore,
            $newScore,
            $change,
            $changePercent,
            $periods,
            $recentPerformance
        ));
    }
    
    /**
     * Enforce reputation bounds [0.5, 1.0]
     * 
     * @param float $reputation Raw reputation score
     * @return float Bounded reputation score
     */
    private function enforceBounds(float $reputation): float
    {
        return max(self::MIN_REPUTATION, min(self::MAX_REPUTATION, $reputation));
    }
}
