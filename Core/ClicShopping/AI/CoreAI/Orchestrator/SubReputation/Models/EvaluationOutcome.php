<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models;

/**
 * Evaluation Outcome Model
 * 
 * Represents the outcome of a critic evaluation, including the critic's score,
 * consensus score, and alignment metrics.
 */
class EvaluationOutcome
{
    public string $evaluationId;
    public string $criticId;
    public float $criticScore;
    public float $consensusScore;
    public bool $withinThreshold;
    public float $alignmentDelta;
    public bool $feedbackAccepted;
    public \DateTimeImmutable $evaluatedAt;
    public array $metadata;
    
    /**
     * Constructor
     * 
     * @param string $evaluationId Evaluation ID
     * @param string $criticId Critic ID
     * @param float $criticScore Critic's score (0.0-1.0)
     * @param float $consensusScore Consensus score (0.0-1.0)
     * @param bool $withinThreshold Whether within 0.1 threshold
     * @param float $alignmentDelta Absolute difference between scores
     * @param bool $feedbackAccepted Whether actor accepted feedback
     * @param \DateTimeImmutable $evaluatedAt Evaluation timestamp
     * @param array $metadata Additional metadata
     */
    public function __construct(
        string $evaluationId,
        string $criticId,
        float $criticScore,
        float $consensusScore,
        bool $withinThreshold,
        float $alignmentDelta,
        bool $feedbackAccepted,
        \DateTimeImmutable $evaluatedAt,
        array $metadata = []
    ) {
        $this->evaluationId = $evaluationId;
        $this->criticId = $criticId;
        $this->criticScore = $criticScore;
        $this->consensusScore = $consensusScore;
        $this->withinThreshold = $withinThreshold;
        $this->alignmentDelta = $alignmentDelta;
        $this->feedbackAccepted = $feedbackAccepted;
        $this->evaluatedAt = $evaluatedAt;
        $this->metadata = $metadata;
    }
    
    /**
     * Convert to array
     * 
     * @return array Array representation
     */
    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'critic_id' => $this->criticId,
            'critic_score' => $this->criticScore,
            'consensus_score' => $this->consensusScore,
            'within_threshold' => $this->withinThreshold,
            'alignment_delta' => $this->alignmentDelta,
            'feedback_accepted' => $this->feedbackAccepted,
            'evaluated_at' => $this->evaluatedAt->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata
        ];
    }
    
    /**
     * Create from array
     * 
     * @param array $data Array data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            evaluationId: $data['evaluation_id'],
            criticId: $data['critic_id'],
            criticScore: (float)$data['critic_score'],
            consensusScore: (float)$data['consensus_score'],
            withinThreshold: (bool)$data['within_threshold'],
            alignmentDelta: (float)$data['alignment_delta'],
            feedbackAccepted: (bool)$data['feedback_accepted'],
            evaluatedAt: new \DateTimeImmutable($data['evaluated_at']),
            metadata: $data['metadata'] ?? []
        );
    }
}
