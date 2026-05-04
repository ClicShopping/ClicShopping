<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine;

use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models\WeightResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models\ConsensusResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationStore;
use ClicShopping\OM\Registry;
use Exception;
use InvalidArgumentException;

/**
 * WeightedConsensusBuilder Class
 *
 * Builds consensus using adaptive weights from LLM analysis.
 * Calculates both dynamic consensus (using adaptive weights) and static consensus
 * (using reputation-only weights) for comparison and quality assessment.
 *
 * Requirements: 8.1, 8.2, 8.3, 8.4, 8.5
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine
 * @version 1.0.0
 * @since 2026-02-06
 */
class WeightedConsensusBuilder
{
    private $db;
    private ReputationStore $reputationStore;
    private bool $debug;
    
    /**
     * Constructor
     *
     * Initializes the weighted consensus builder with database connection
     * and reputation store for static weight calculation.
     */
    public function __construct()
    {
        $this->db = Registry::get('Db');
        $this->reputationStore = new ReputationStore();
        $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                       CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    }
    
    /**
     * Build dynamic consensus using adaptive weights
     *
     * Calculates consensus as the weighted sum of critic scores using
     * LLM-determined adaptive weights. This is the primary consensus
     * calculation method that considers context, expertise, and other factors.
     *
     * Formula: Consensus = Σ(critic_score × adaptive_weight)
     *
     * Requirements: 8.1, 8.2, 8.5
     *
     * @param array $evaluations Array of evaluations with criticId and score
     * @param WeightResult $weights The adaptive weights from LLM analysis
     * @return ConsensusResult The consensus result with dynamic and static scores
     * @throws InvalidArgumentException If evaluations or weights are invalid
     * @throws Exception If consensus calculation fails
     */
    public function buildDynamicConsensus(
        array $evaluations,
        WeightResult $weights
    ): ConsensusResult {
        // Validate inputs
        if (empty($evaluations)) {
            throw new InvalidArgumentException('Evaluations array cannot be empty');
        }
        
        $normalizedWeights = $weights->getNormalizedWeights();
        if (empty($normalizedWeights)) {
            throw new InvalidArgumentException('Normalized weights cannot be empty');
        }
        
        try {
            // Calculate dynamic consensus using adaptive weights
            $dynamicConsensus = 0.0;
            $weightedScores = [];
            
            foreach ($evaluations as $evaluation) {
                $criticId = $this->extractCriticId($evaluation);
                $score = $this->extractScore($evaluation);
                
                // Get adaptive weight for this critic
                $weight = $normalizedWeights[$criticId] ?? 0.0;
                
                // Calculate weighted score
                $weightedScore = $score * $weight;
                $dynamicConsensus += $weightedScore;
                
                $weightedScores[$criticId] = $weightedScore;
                
                if ($this->debug) {
                    error_log(sprintf(
                        "WeightedConsensusBuilder: Critic %s - Score: %.4f, Weight: %.4f, Weighted: %.4f",
                        $criticId,
                        $score,
                        $weight,
                        $weightedScore
                    ));
                }
            }
            
            // Calculate static consensus for comparison
            $staticConsensus = $this->calculateStaticConsensus($evaluations);
            
            // Calculate difference
            $consensusDifference = $dynamicConsensus - $staticConsensus;
            
            // Create consensus result
            $consensusResult = new ConsensusResult(
                $weights->getEvaluationId(),
                $dynamicConsensus,
                $staticConsensus,
                $consensusDifference,
                $weightedScores,
                $this->calculateConfidenceLevel($evaluations, $weights),
                $this->assessConsensusQuality($dynamicConsensus, $staticConsensus, $evaluations)
            );
            
            // Store comparison in database
            $this->storeConsensusComparison($consensusResult);
            
            if ($this->debug) {
                error_log(sprintf(
                    "WeightedConsensusBuilder: Dynamic=%.4f, Static=%.4f, Diff=%.4f (%.2f%%)",
                    $dynamicConsensus,
                    $staticConsensus,
                    $consensusDifference,
                    $consensusResult->getImprovementPercentage()
                ));
            }
            
            return $consensusResult;
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("WeightedConsensusBuilder: Failed to build consensus - " . $e->getMessage());
            }
            throw new Exception('Failed to build dynamic consensus: ' . $e->getMessage());
        }
    }
    
    /**
     * Calculate static consensus using reputation-only weights
     *
     * Calculates consensus using only reputation scores as weights,
     * without considering context, expertise, or other factors.
     * This provides a baseline for comparison with adaptive weighting.
     *
     * Formula: Consensus = Σ(critic_score × reputation_weight)
     *
     * Requirements: 8.3, 8.4
     *
     * @param array $evaluations Array of evaluations with criticId and score
     * @return float The static consensus score
     * @throws Exception If calculation fails
     */
    public function calculateStaticConsensus(array $evaluations): float
    {
        try {
            // Get reputation scores for all critics
            $reputationWeights = [];
            $totalReputation = 0.0;
            
            foreach ($evaluations as $evaluation) {
                $criticId = $this->extractCriticId($evaluation);
                
                // Get reputation score from reputation store
                $reputation = $this->reputationStore->getReputation($criticId);
                $reputationScore = $reputation ? $reputation->reputationScore : 0.75; // Default if not found
                
                $reputationWeights[$criticId] = $reputationScore;
                $totalReputation += $reputationScore;
            }
            
            // Normalize reputation weights
            if ($totalReputation > 0) {
                foreach ($reputationWeights as $criticId => $reputation) {
                    $reputationWeights[$criticId] = $reputation / $totalReputation;
                }
            } else {
                // Equal weighting if no reputation data
                $equalWeight = 1.0 / count($evaluations);
                foreach ($reputationWeights as $criticId => $reputation) {
                    $reputationWeights[$criticId] = $equalWeight;
                }
            }
            
            // Calculate weighted consensus
            $staticConsensus = 0.0;
            foreach ($evaluations as $evaluation) {
                $criticId = $this->extractCriticId($evaluation);
                $score = $this->extractScore($evaluation);
                $weight = $reputationWeights[$criticId] ?? 0.0;
                
                $staticConsensus += $score * $weight;
            }
            
            return $staticConsensus;
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("WeightedConsensusBuilder: Failed to calculate static consensus - " . $e->getMessage());
            }
            throw new Exception('Failed to calculate static consensus: ' . $e->getMessage());
        }
    }
    
    /**
     * Compare consensus approaches and calculate improvement metrics
     *
     * Analyzes the difference between dynamic and static consensus,
     * calculates improvement scores, and provides quality assessment.
     *
     * Requirements: 8.3, 8.4
     *
     * @param array $evaluations Array of evaluations
     * @param WeightResult $adaptiveWeights The adaptive weights
     * @return array Comparison data with analysis
     */
    public function compareConsensusApproaches(
        array $evaluations,
        WeightResult $adaptiveWeights
    ): array {
        try {
            // Build consensus using both approaches
            $consensusResult = $this->buildDynamicConsensus($evaluations, $adaptiveWeights);
            
            $dynamicConsensus = $consensusResult->getDynamicConsensus();
            $staticConsensus = $consensusResult->getStaticConsensus();
            $difference = $consensusResult->getConsensusDifference();
            
            // Calculate improvement score
            $improvementScore = $this->calculateImprovementScore(
                $dynamicConsensus,
                $staticConsensus,
                $evaluations
            );
            
            // Identify impact factors
            $impactFactors = $this->identifyImpactFactors(
                $evaluations,
                $adaptiveWeights,
                $difference
            );
            
            // Generate analysis
            $analysis = $this->generateComparisonAnalysis(
                $dynamicConsensus,
                $staticConsensus,
                $difference,
                $improvementScore,
                $impactFactors
            );
            
            return [
                'evaluation_id' => $adaptiveWeights->getEvaluationId(),
                'dynamic_consensus' => $dynamicConsensus,
                'static_consensus' => $staticConsensus,
                'difference' => $difference,
                'improvement_score' => $improvementScore,
                'improvement_percentage' => $consensusResult->getImprovementPercentage(),
                'is_dynamic_better' => $consensusResult->isDynamicBetter(),
                'analysis' => $analysis,
                'impact_factors' => $impactFactors,
                'quality_metrics' => [
                    'confidence_level' => $consensusResult->getConfidenceLevel(),
                    'consensus_quality' => $consensusResult->getConsensusQuality()
                ]
            ];
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("WeightedConsensusBuilder: Failed to compare approaches - " . $e->getMessage());
            }
            throw new Exception('Failed to compare consensus approaches: ' . $e->getMessage());
        }
    }
    
    /**
     * Store consensus comparison in database
     *
     * Persists the consensus comparison data for audit and analysis.
     *
     * Requirements: 8.3, 8.4
     *
     * @param ConsensusResult $consensusResult The consensus result to store
     * @throws Exception If storage fails
     */
    private function storeConsensusComparison(ConsensusResult $consensusResult): void
    {
        try {
            $sql = "INSERT INTO :table_rag_agent_weight_consensus 
                    (evaluation_id, dynamic_consensus, static_consensus, difference, created_at)
                    VALUES (:evaluation_id, :dynamic_consensus, :static_consensus, :difference, :created_at)
                    ON DUPLICATE KEY UPDATE
                    dynamic_consensus = VALUES(dynamic_consensus),
                    static_consensus = VALUES(static_consensus),
                    difference = VALUES(difference)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'evaluation_id' => $consensusResult->getEvaluationId(),
                'dynamic_consensus' => $consensusResult->getDynamicConsensus(),
                'static_consensus' => $consensusResult->getStaticConsensus(),
                'difference' => $consensusResult->getConsensusDifference(),
                'created_at' => $consensusResult->getCalculatedAt()->format('Y-m-d H:i:s')
            ]);
            
            if ($this->debug) {
                error_log(sprintf(
                    "WeightedConsensusBuilder: Stored consensus comparison for evaluation %s",
                    $consensusResult->getEvaluationId()
                ));
            }
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("WeightedConsensusBuilder: Failed to store consensus comparison - " . $e->getMessage());
            }
            throw new Exception('Failed to store consensus comparison: ' . $e->getMessage());
        }
    }
    
    /**
     * Extract critic ID from evaluation object or array
     *
     * @param mixed $evaluation The evaluation object or array
     * @return string The critic ID
     * @throws InvalidArgumentException If critic ID cannot be extracted
     */
    private function extractCriticId($evaluation): string
    {
        if (is_array($evaluation)) {
            if (isset($evaluation['criticId'])) {
                return $evaluation['criticId'];
            }
            if (isset($evaluation['critic_id'])) {
                return $evaluation['critic_id'];
            }
            if (isset($evaluation['evaluator_id'])) {
                return $evaluation['evaluator_id'];
            }
        } elseif (is_object($evaluation)) {
            if (method_exists($evaluation, 'getCriticId')) {
                return $evaluation->getCriticId();
            }
            if (method_exists($evaluation, 'getEvaluatorAgentId')) {
                return $evaluation->getEvaluatorAgentId();
            }
            if (isset($evaluation->criticId)) {
                return $evaluation->criticId;
            }
            if (isset($evaluation->critic_id)) {
                return $evaluation->critic_id;
            }
        }
        
        throw new InvalidArgumentException('Cannot extract critic ID from evaluation');
    }
    
    /**
     * Extract score from evaluation object or array
     *
     * @param mixed $evaluation The evaluation object or array
     * @return float The evaluation score
     * @throws InvalidArgumentException If score cannot be extracted
     */
    private function extractScore($evaluation): float
    {
        if (is_array($evaluation)) {
            if (isset($evaluation['score'])) {
                return (float)$evaluation['score'];
            }
            if (isset($evaluation['overall_score'])) {
                return (float)$evaluation['overall_score'];
            }
        } elseif (is_object($evaluation)) {
            if (method_exists($evaluation, 'getScore')) {
                return (float)$evaluation->getScore();
            }
            if (method_exists($evaluation, 'getOverallScore')) {
                return (float)$evaluation->getOverallScore();
            }
            if (isset($evaluation->score)) {
                return (float)$evaluation->score;
            }
            if (isset($evaluation->overall_score)) {
                return (float)$evaluation->overall_score;
            }
        }
        
        throw new InvalidArgumentException('Cannot extract score from evaluation');
    }
    
    /**
     * Calculate confidence level in consensus
     *
     * @param array $evaluations The evaluations
     * @param WeightResult $weights The adaptive weights
     * @return float Confidence level (0.0-1.0)
     */
    private function calculateConfidenceLevel(array $evaluations, WeightResult $weights): float
    {
        // Calculate based on weight distribution and score agreement
        $normalizedWeights = $weights->getNormalizedWeights();
        
        // Calculate weight entropy (lower entropy = more concentrated weights = higher confidence)
        $entropy = 0.0;
        foreach ($normalizedWeights as $weight) {
            if ($weight > 0) {
                $entropy -= $weight * log($weight);
            }
        }
        
        // Normalize entropy to 0-1 range (assuming max entropy for equal weights)
        $maxEntropy = log(count($normalizedWeights));
        $normalizedEntropy = $maxEntropy > 0 ? $entropy / $maxEntropy : 0.0;
        
        // Calculate score variance
        $scores = [];
        foreach ($evaluations as $evaluation) {
            $scores[] = $this->extractScore($evaluation);
        }
        
        $mean = array_sum($scores) / count($scores);
        $variance = 0.0;
        foreach ($scores as $score) {
            $variance += pow($score - $mean, 2);
        }
        $variance /= count($scores);
        
        // Lower variance = higher confidence
        $scoreAgreement = 1.0 - min($variance, 1.0);
        
        // Combine factors (weight concentration 60%, score agreement 40%)
        $confidence = (1.0 - $normalizedEntropy) * 0.6 + $scoreAgreement * 0.4;
        
        return max(0.0, min(1.0, $confidence));
    }
    
    /**
     * Assess consensus quality
     *
     * @param float $dynamicConsensus The dynamic consensus score
     * @param float $staticConsensus The static consensus score
     * @param array $evaluations The evaluations
     * @return string Quality assessment
     */
    private function assessConsensusQuality(
        float $dynamicConsensus,
        float $staticConsensus,
        array $evaluations
    ): string {
        $difference = abs($dynamicConsensus - $staticConsensus);
        $improvementPct = $staticConsensus > 0 
            ? (($dynamicConsensus - $staticConsensus) / $staticConsensus) * 100 
            : 0.0;
        
        if ($difference < 0.05) {
            return 'High agreement between dynamic and static consensus';
        } elseif ($improvementPct > 10) {
            return 'Significant improvement with adaptive weighting';
        } elseif ($improvementPct < -10) {
            return 'Static weighting performed better - review adaptive weights';
        } else {
            return 'Moderate difference between approaches';
        }
    }
    
    /**
     * Calculate improvement score
     *
     * @param float $dynamicConsensus The dynamic consensus
     * @param float $staticConsensus The static consensus
     * @param array $evaluations The evaluations
     * @return float Improvement score
     */
    private function calculateImprovementScore(
        float $dynamicConsensus,
        float $staticConsensus,
        array $evaluations
    ): float {
        // Simple improvement score based on difference
        // Positive = dynamic better, negative = static better
        return $dynamicConsensus - $staticConsensus;
    }
    
    /**
     * Identify factors that impacted the consensus difference
     *
     * @param array $evaluations The evaluations
     * @param WeightResult $weights The adaptive weights
     * @param float $difference The consensus difference
     * @return array Impact factors
     */
    private function identifyImpactFactors(
        array $evaluations,
        WeightResult $weights,
        float $difference
    ): array {
        $factors = [];
        
        // Analyze weight distribution
        $normalizedWeights = $weights->getNormalizedWeights();
        $maxWeight = max($normalizedWeights);
        $minWeight = min($normalizedWeights);
        
        if ($maxWeight - $minWeight > 0.3) {
            $factors[] = 'Significant weight variation among critics';
        }
        
        // Analyze if high-weighted critics had different scores
        $weightedScoreDiff = 0.0;
        foreach ($evaluations as $evaluation) {
            $criticId = $this->extractCriticId($evaluation);
            $score = $this->extractScore($evaluation);
            $weight = $normalizedWeights[$criticId] ?? 0.0;
            
            // Compare to average score
            $avgScore = array_sum(array_map(function($e) {
                return $this->extractScore($e);
            }, $evaluations)) / count($evaluations);
            
            $weightedScoreDiff += abs($score - $avgScore) * $weight;
        }
        
        if ($weightedScoreDiff > 0.1) {
            $factors[] = 'High-weighted critics had divergent scores';
        }
        
        if (abs($difference) > 0.1) {
            $factors[] = 'Substantial consensus difference indicates adaptive weighting impact';
        }
        
        return $factors;
    }
    
    /**
     * Generate comparison analysis text
     *
     * @param float $dynamicConsensus The dynamic consensus
     * @param float $staticConsensus The static consensus
     * @param float $difference The difference
     * @param float $improvementScore The improvement score
     * @param array $impactFactors The impact factors
     * @return string Analysis text
     */
    private function generateComparisonAnalysis(
        float $dynamicConsensus,
        float $staticConsensus,
        float $difference,
        float $improvementScore,
        array $impactFactors
    ): string {
        $analysis = sprintf(
            "Dynamic consensus (%.4f) vs Static consensus (%.4f) shows a difference of %.4f. ",
            $dynamicConsensus,
            $staticConsensus,
            $difference
        );
        
        if ($improvementScore > 0.05) {
            $analysis .= "Adaptive weighting improved consensus quality. ";
        } elseif ($improvementScore < -0.05) {
            $analysis .= "Static weighting performed better in this case. ";
        } else {
            $analysis .= "Both approaches yielded similar results. ";
        }
        
        if (!empty($impactFactors)) {
            $analysis .= "Key factors: " . implode('; ', $impactFactors) . ".";
        }
        
        return $analysis;
    }
}
