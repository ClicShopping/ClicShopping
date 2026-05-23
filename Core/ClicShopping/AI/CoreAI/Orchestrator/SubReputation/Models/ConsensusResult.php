<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models;

/**
 * ConsensusResult - Data model for weighted consensus results
 * 
 * Represents the result of a weighted consensus calculation,
 * including both weighted and unweighted scores for comparison,
 * plus quality metrics for tracking consensus effectiveness.
 * 
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 11.1
 */
class ConsensusResult
{
    public float $weightedScore;
    public float $unweightedScore;
    public float $difference;
    public array $evaluations;
    public array $reputations;
    public \DateTimeImmutable $calculatedAt;
    
    // Consensus quality metrics (Requirement 11.1)
    public float $agreementLevel;
    public float $confidence;
    public float $stability;
    
    /**
     * Constructor
     * 
     * @param float $weightedScore Weighted consensus score
     * @param float $unweightedScore Unweighted consensus score
     * @param float $difference Absolute difference between weighted and unweighted
     * @param array $evaluations Array of evaluations used
     * @param array $reputations Array of reputation scores used
     * @param float $agreementLevel Agreement level among critics (0.0-1.0)
     * @param float $confidence Confidence in the consensus (0.0-1.0)
     * @param float $stability Stability of the consensus (0.0-1.0)
     */
    public function __construct(
        float $weightedScore,
        float $unweightedScore,
        float $difference,
        array $evaluations,
        array $reputations,
        float $agreementLevel = 0.0,
        float $confidence = 0.0,
        float $stability = 0.0
    ) {
        $this->weightedScore = $weightedScore;
        $this->unweightedScore = $unweightedScore;
        $this->difference = $difference;
        $this->evaluations = $evaluations;
        $this->reputations = $reputations;
        $this->agreementLevel = $agreementLevel;
        $this->confidence = $confidence;
        $this->stability = $stability;
        $this->calculatedAt = new \DateTimeImmutable();
    }
    
    /**
     * Get consensus result as array for serialization
     * 
     * @return array Consensus result data
     */
    public function toArray(): array
    {
        return [
            'weighted_score' => $this->weightedScore,
            'unweighted_score' => $this->unweightedScore,
            'difference' => $this->difference,
            'evaluation_count' => count($this->evaluations),
            'reputations' => $this->reputations,
            'agreement_level' => $this->agreementLevel,
            'confidence' => $this->confidence,
            'stability' => $this->stability,
            'calculated_at' => $this->calculatedAt->format('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Check if reputation weighting made a significant difference
     * 
     * @param float $threshold Threshold for significance (default: 0.05)
     * @return bool True if difference is significant
     */
    public function hasSignificantDifference(float $threshold = 0.05): bool
    {
        return $this->difference >= $threshold;
    }
}
