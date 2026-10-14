<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation;

use ClicShopping\OM\Registry;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationStore;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ConsensusQualityTracker;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\ConsensusResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Evaluation;
use Exception;
use InvalidArgumentException;

/**
 * WeightedConsensus - Builds consensus using reputation weights
 * 
 * Calculates weighted consensus from critic evaluations by applying
 * reputation scores as weights. Falls back to unweighted consensus
 * if reputation data is unavailable. Logs consensus differences for
 * analysis and system effectiveness tracking.
 * 
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubReputation
 * @version 1.0.0
 * @since 2026-02-04
 */
class WeightedConsensus
{
    private ReputationStore $store;
    private ConsensusQualityTracker $qualityTracker;
    private bool $debug;
    
    // Default reputation for new critics or when reputation unavailable
    private const DEFAULT_REPUTATION = 0.75;
    
    // Bootstrapping weight reduction factor
    private const BOOTSTRAPPING_WEIGHT_FACTOR = 0.5;
    
    /**
     * Constructor
     * 
     * Initializes the weighted consensus builder with reputation store.
     */
    public function __construct()
    {
        $this->store = new ReputationStore();
        $this->qualityTracker = new ConsensusQualityTracker();
        $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                       CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    }
    
    /**
     * Build consensus from evaluations using reputation weights
     * 
     * Main entry point for weighted consensus building. Retrieves reputation
     * scores for all critics, calculates weighted consensus using the formula
     * Σ(score × reputation) / Σ(reputation), and compares with unweighted
     * consensus for effectiveness tracking.
     * 
     * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5
     * 
     * @param array $evaluations Array of Evaluation objects
     * @return ConsensusResult The consensus result with weighted and unweighted scores
     * @throws InvalidArgumentException If evaluations array is invalid
     * @throws Exception If consensus building fails
     */
    public function buildConsensus(array $evaluations): ConsensusResult
    {
        // Validate evaluations array (Requirement 3.1)
        if (empty($evaluations)) {
            throw new InvalidArgumentException('Evaluations array cannot be empty');
        }
        
        // Validate all elements are Evaluation instances
        foreach ($evaluations as $evaluation) {
            if (!($evaluation instanceof Evaluation)) {
                throw new InvalidArgumentException('All evaluations must be Evaluation instances');
            }
        }
        
        try {
            // Get reputation scores for all critics (Requirement 3.1)
            $reputations = $this->getReputations($evaluations);
            
            // Calculate weighted consensus (Requirement 3.2)
            $weightedConsensus = $this->calculateWeightedConsensus($evaluations, $reputations);
            
            // Calculate unweighted consensus for comparison (Requirement 3.5)
            $unweightedConsensus = $this->calculateUnweightedConsensus($evaluations);
            
            // Calculate difference
            $difference = abs($weightedConsensus - $unweightedConsensus);
            
            // Calculate consensus quality metrics (Requirement 11.1)
            $agreementLevel = $this->calculateAgreementLevel($evaluations, $weightedConsensus);
            $confidence = $this->calculateConfidence($evaluations, $reputations);
            $stability = $this->calculateStability($evaluations);
            
            // Log consensus difference (Requirement 3.5)
            $this->logConsensusDifference($difference, $evaluations, $reputations);
            
            // Create and return consensus result
            $result = new ConsensusResult(
                weightedScore: $weightedConsensus,
                unweightedScore: $unweightedConsensus,
                difference: $difference,
                evaluations: $evaluations,
                reputations: $reputations,
                agreementLevel: $agreementLevel,
                confidence: $confidence,
                stability: $stability
            );
            
            // Track consensus for quality analysis (Requirement 11.1)
            $this->qualityTracker->trackConsensus($result);
            
            if ($this->debug) {
                error_log(sprintf(
                    "WeightedConsensus: Built consensus - Weighted: %.4f, Unweighted: %.4f, Difference: %.4f, Agreement: %.4f, Confidence: %.4f, Stability: %.4f",
                    $weightedConsensus,
                    $unweightedConsensus,
                    $difference,
                    $agreementLevel,
                    $confidence,
                    $stability
                ));
            }
            
            return $result;
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("WeightedConsensus: Failed to build consensus - " . $e->getMessage());
            }
            
            // Fall back to unweighted consensus (Requirement 3.4)
            return $this->fallbackToUnweighted($evaluations, $e);
        }
    }
    
    /**
     * Get reputation scores for all critics in evaluations
     * 
     * Retrieves reputation scores from the store for each critic.
     * Uses default reputation for new critics or when data unavailable.
     * Applies bootstrapping weight reduction for critics with status 'bootstrapping'.
     * 
     * Requirement 3.1: Get reputation scores for all critics
     * 
     * @param array $evaluations Array of Evaluation objects
     * @return array Associative array mapping critic_id to reputation score
     */
    private function getReputations(array $evaluations): array
    {
        $reputations = [];
        
        foreach ($evaluations as $evaluation) {
            $criticId = $evaluation->getEvaluatorAgentId();
            
            // Skip if already retrieved
            if (isset($reputations[$criticId])) {
                continue;
            }
            
            try {
                // Try to get reputation from store
                $reputationScore = $this->store->getReputation($criticId);
                
                if ($reputationScore !== null) {
                    $reputation = $reputationScore->reputationScore;
                    
                    // Apply bootstrapping weight reduction (Requirement 5.5)
                    if ($reputationScore->status === 'bootstrapping') {
                        $reputation *= self::BOOTSTRAPPING_WEIGHT_FACTOR;
                        
                        if ($this->debug) {
                            error_log(sprintf(
                                "WeightedConsensus: Applied bootstrapping weight reduction for critic %s: %.4f -> %.4f",
                                $criticId,
                                $reputationScore->reputationScore,
                                $reputation
                            ));
                        }
                    }
                    
                    $reputations[$criticId] = $reputation;
                } else {
                    // New critic - use default reputation
                    $reputations[$criticId] = self::DEFAULT_REPUTATION;
                    
                    if ($this->debug) {
                        error_log(sprintf(
                            "WeightedConsensus: Using default reputation for new critic %s: %.4f",
                            $criticId,
                            self::DEFAULT_REPUTATION
                        ));
                    }
                }
                
            } catch (Exception $e) {
                // Error retrieving reputation - use default
                $reputations[$criticId] = self::DEFAULT_REPUTATION;
                
                if ($this->debug) {
                    error_log(sprintf(
                        "WeightedConsensus: Error getting reputation for critic %s, using default: %s",
                        $criticId,
                        $e->getMessage()
                    ));
                }
            }
        }
        
        return $reputations;
    }
    
    /**
     * Calculate weighted consensus score
     * 
     * Applies reputation weights to evaluation scores using the formula:
     * Weighted Consensus = Σ(score × reputation) / Σ(reputation)
     * 
     * Requirement 3.2: Calculate weighted consensus as Σ(score × reputation) / Σ(reputation)
     * 
     * @param array $evaluations Array of Evaluation objects
     * @param array $reputations Associative array mapping critic_id to reputation score
     * @return float Weighted consensus score (0.0-1.0)
     */
    private function calculateWeightedConsensus(array $evaluations, array $reputations): float
    {
        $weightedSum = 0.0;
        $totalWeight = 0.0;
        
        foreach ($evaluations as $evaluation) {
            $criticId = $evaluation->getEvaluatorAgentId();
            $score = $evaluation->getOverallScore();
            $reputation = $reputations[$criticId] ?? self::DEFAULT_REPUTATION;
            
            $weightedSum += $score * $reputation;
            $totalWeight += $reputation;
        }
        
        // Avoid division by zero
        if ($totalWeight == 0) {
            if ($this->debug) {
                error_log("WeightedConsensus: Total weight is zero, falling back to unweighted consensus");
            }
            return $this->calculateUnweightedConsensus($evaluations);
        }
        
        return $weightedSum / $totalWeight;
    }
    
    /**
     * Calculate unweighted consensus score
     * 
     * Calculates simple average of evaluation scores for comparison
     * with weighted consensus.
     * 
     * Requirement 3.5: Calculate unweighted consensus for comparison
     * 
     * @param array $evaluations Array of Evaluation objects
     * @return float Unweighted consensus score (0.0-1.0)
     */
    private function calculateUnweightedConsensus(array $evaluations): float
    {
        if (empty($evaluations)) {
            return 0.0;
        }
        
        $sum = 0.0;
        foreach ($evaluations as $evaluation) {
            $sum += $evaluation->getOverallScore();
        }
        
        return $sum / count($evaluations);
    }
    
    /**
     * Log consensus difference for analysis
     * 
     * Logs the difference between weighted and unweighted consensus
     * for system effectiveness tracking and analysis.
     * 
     * Requirement 3.5: Log difference between weighted and unweighted consensus
     * 
     * @param float $difference Absolute difference between weighted and unweighted
     * @param array $evaluations Array of Evaluation objects
     * @param array $reputations Associative array mapping critic_id to reputation score
     */
    private function logConsensusDifference(float $difference, array $evaluations, array $reputations): void
    {
        // Always log to error_log for debugging
        if ($this->debug) {
            $criticIds = array_map(fn($e) => $e->getEvaluatorAgentId(), $evaluations);
            $reputationValues = array_map(fn($id) => $reputations[$id] ?? self::DEFAULT_REPUTATION, $criticIds);
            
            error_log(sprintf(
                "WeightedConsensus: Consensus difference: %.4f | Critics: %s | Reputations: %s",
                $difference,
                implode(', ', $criticIds),
                implode(', ', array_map(fn($r) => sprintf('%.4f', $r), $reputationValues))
            ));
        }
        
        // TODO: In future, store this in database for dashboard analytics
        // This would support Requirements 11.1, 11.2, 11.3, 11.4, 11.5
        // For now, we just log to error_log
    }
    
    /**
     * Fallback to unweighted consensus when reputation unavailable
     * 
     * When reputation data is unavailable or an error occurs,
     * falls back to simple unweighted consensus calculation.
     * 
     * Requirement 3.4: Fall back to unweighted consensus if reputation unavailable
     * 
     * @param array $evaluations Array of Evaluation objects
     * @param Exception $originalException The exception that triggered fallback
     * @return ConsensusResult Consensus result with unweighted scores
     */
    private function fallbackToUnweighted(array $evaluations, Exception $originalException): ConsensusResult
    {
        if ($this->debug) {
            error_log(sprintf(
                "WeightedConsensus: Falling back to unweighted consensus due to error: %s",
                $originalException->getMessage()
            ));
        }
        
        // Calculate unweighted consensus
        $unweightedConsensus = $this->calculateUnweightedConsensus($evaluations);
        
        // Create default reputations array
        $reputations = [];
        foreach ($evaluations as $evaluation) {
            $criticId = $evaluation->getEvaluatorAgentId();
            $reputations[$criticId] = self::DEFAULT_REPUTATION;
        }
        
        // Calculate quality metrics with default reputations
        $agreementLevel = $this->calculateAgreementLevel($evaluations, $unweightedConsensus);
        $confidence = $this->calculateConfidence($evaluations, $reputations);
        $stability = $this->calculateStability($evaluations);
        
        // Return result with weighted = unweighted (no weighting applied)
        return new ConsensusResult(
            weightedScore: $unweightedConsensus,
            unweightedScore: $unweightedConsensus,
            difference: 0.0,
            evaluations: $evaluations,
            reputations: $reputations,
            agreementLevel: $agreementLevel,
            confidence: $confidence,
            stability: $stability
        );
    }
    
    /**
     * Calculate agreement level among critics
     * 
     * Measures how closely critics agree with each other by calculating
     * the percentage of evaluations within a threshold of the consensus.
     * Higher agreement indicates more consistent evaluations.
     * 
     * Requirement 11.1: Track consensus quality metrics
     * 
     * @param array $evaluations Array of Evaluation objects
     * @param float $consensusScore The consensus score to compare against
     * @param float $threshold Agreement threshold (default: 0.1)
     * @return float Agreement level (0.0-1.0)
     */
    private function calculateAgreementLevel(array $evaluations, float $consensusScore, float $threshold = 0.1): float
    {
        if (empty($evaluations)) {
            return 0.0;
        }
        
        $withinThreshold = 0;
        
        foreach ($evaluations as $evaluation) {
            $score = $evaluation->getOverallScore();
            if (abs($score - $consensusScore) <= $threshold) {
                $withinThreshold++;
            }
        }
        
        $agreementLevel = $withinThreshold / count($evaluations);
        
        if ($this->debug) {
            error_log(sprintf(
                "WeightedConsensus: Agreement level: %.4f (%d/%d within threshold %.2f)",
                $agreementLevel,
                $withinThreshold,
                count($evaluations),
                $threshold
            ));
        }
        
        return $agreementLevel;
    }
    
    /**
     * Calculate confidence in the consensus
     * 
     * Measures confidence based on the reputation-weighted variance.
     * Higher reputation critics contribute more to confidence.
     * Lower variance and higher average reputation = higher confidence.
     * 
     * Requirement 11.1: Track consensus quality metrics
     * 
     * @param array $evaluations Array of Evaluation objects
     * @param array $reputations Associative array mapping critic_id to reputation score
     * @return float Confidence level (0.0-1.0)
     */
    private function calculateConfidence(array $evaluations, array $reputations): float
    {
        if (empty($evaluations)) {
            return 0.0;
        }
        
        // Calculate average reputation
        $totalReputation = 0.0;
        $count = 0;
        
        foreach ($evaluations as $evaluation) {
            $criticId = $evaluation->getEvaluatorAgentId();
            $reputation = $reputations[$criticId] ?? self::DEFAULT_REPUTATION;
            $totalReputation += $reputation;
            $count++;
        }
        
        $avgReputation = $count > 0 ? $totalReputation / $count : 0.0;
        
        // Calculate weighted variance
        $weightedConsensus = $this->calculateWeightedConsensus($evaluations, $reputations);
        $weightedVariance = 0.0;
        $totalWeight = 0.0;
        
        foreach ($evaluations as $evaluation) {
            $criticId = $evaluation->getEvaluatorAgentId();
            $score = $evaluation->getOverallScore();
            $reputation = $reputations[$criticId] ?? self::DEFAULT_REPUTATION;
            
            $weightedVariance += $reputation * pow($score - $weightedConsensus, 2);
            $totalWeight += $reputation;
        }
        
        $weightedVariance = $totalWeight > 0 ? $weightedVariance / $totalWeight : 0.0;
        
        // Confidence = average reputation × (1 - normalized variance)
        // Normalized variance is capped at 1.0 for scores in range [0, 1]
        $normalizedVariance = min($weightedVariance / 0.25, 1.0); // 0.25 is max variance for [0,1] range
        $confidence = $avgReputation * (1.0 - $normalizedVariance);
        
        if ($this->debug) {
            error_log(sprintf(
                "WeightedConsensus: Confidence: %.4f (avg_reputation: %.4f, weighted_variance: %.4f)",
                $confidence,
                $avgReputation,
                $weightedVariance
            ));
        }
        
        return $confidence;
    }
    
    /**
     * Calculate stability of the consensus
     * 
     * Measures how stable the consensus is by calculating the inverse
     * of the coefficient of variation (standard deviation / mean).
     * Higher stability indicates more consistent scores.
     * 
     * Requirement 11.1: Track consensus quality metrics
     * 
     * @param array $evaluations Array of Evaluation objects
     * @return float Stability level (0.0-1.0)
     */
    private function calculateStability(array $evaluations): float
    {
        if (empty($evaluations)) {
            return 0.0;
        }
        
        // Get all scores
        $scores = [];
        foreach ($evaluations as $evaluation) {
            $scores[] = $evaluation->getOverallScore();
        }
        
        // Calculate mean
        $mean = array_sum($scores) / count($scores);
        
        // Handle edge case: mean is zero
        if ($mean == 0) {
            if ($this->debug) {
                error_log("WeightedConsensus: Mean is zero, returning neutral stability");
            }
            return 0.5;
        }
        
        // Calculate standard deviation
        $variance = 0.0;
        foreach ($scores as $score) {
            $variance += pow($score - $mean, 2);
        }
        $variance /= count($scores);
        $stdDev = sqrt($variance);
        
        // Calculate coefficient of variation
        $cv = $stdDev / $mean;
        
        // Stability = 1 - normalized CV (capped at 1.0)
        $stability = max(0.0, 1.0 - min($cv, 1.0));
        
        if ($this->debug) {
            error_log(sprintf(
                "WeightedConsensus: Stability: %.4f (mean: %.4f, std_dev: %.4f, cv: %.4f)",
                $stability,
                $mean,
                $stdDev,
                $cv
            ));
        }
        
        return $stability;
    }
    
    /**
     * Get default reputation value
     * 
     * @return float Default reputation for new critics
     */
    public function getDefaultReputation(): float
    {
        return self::DEFAULT_REPUTATION;
    }
    
    /**
     * Get bootstrapping weight factor
     * 
     * @return float Weight reduction factor for bootstrapping critics
     */
    public function getBootstrappingWeightFactor(): float
    {
        return self::BOOTSTRAPPING_WEIGHT_FACTOR;
    }
    
    /**
     * Get consensus quality tracker
     * 
     * Provides access to the quality tracker for correlation analysis
     * and effectiveness reporting.
     * 
     * Requirements: 11.4, 11.5
     * 
     * @return ConsensusQualityTracker The quality tracker instance
     */
    public function getQualityTracker(): ConsensusQualityTracker
    {
        return $this->qualityTracker;
    }
    
    /**
     * Calculate correlation between reputation weighting and consensus quality
     * 
     * Convenience method to access correlation calculation from the quality tracker.
     * 
     * Requirement 11.4: Calculate correlation between reputation weighting and consensus quality
     * 
     * @param int $days Number of days to analyze (default: 30)
     * @return array Correlation analysis results
     */
    public function calculateCorrelation(int $days = 30): array
    {
        return $this->qualityTracker->calculateCorrelation($days);
    }
    
    /**
     * Generate monthly effectiveness report
     * 
     * Convenience method to access monthly report generation from the quality tracker.
     * 
     * Requirement 11.5: Generate monthly effectiveness reports
     * 
     * @return array Monthly effectiveness report
     */
    public function generateMonthlyReport(): array
    {
        return $this->qualityTracker->generateMonthlyReport();
    }
}
