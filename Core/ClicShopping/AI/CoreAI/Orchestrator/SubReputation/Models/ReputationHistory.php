<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models;

use DateTimeImmutable;

/**
 * ReputationHistory - Data model for reputation change history
 * 
 * Represents a single reputation change event with full audit trail.
 * 
 * Requirements: 2.6, 9.1, 9.2
 */
class ReputationHistory
{
    public int $historyId;
    public string $criticId;
    public string $evaluationId;
    public float $consensusScore;
    public float $criticScore;
    public float $alignmentDelta;       // abs(consensusScore - criticScore)
    public float $reputationImpact;     // change in reputation
    public float $oldReputation;
    public float $newReputation;
    public DateTimeImmutable $recordedAt;
    
    /**
     * Create a new ReputationHistory record
     * 
     * @param string $criticId Critic identifier
     * @param string $evaluationId Evaluation identifier
     * @param float $consensusScore Final consensus score
     * @param float $criticScore Critic's evaluation score
     * @param float $oldReputation Reputation before change
     * @param float $newReputation Reputation after change
     * @return self New history record
     */
    public static function create(
        string $criticId,
        string $evaluationId,
        float $consensusScore,
        float $criticScore,
        float $oldReputation,
        float $newReputation
    ): self {
        $history = new self();
        $history->criticId = $criticId;
        $history->evaluationId = $evaluationId;
        $history->consensusScore = $consensusScore;
        $history->criticScore = $criticScore;
        $history->alignmentDelta = abs($consensusScore - $criticScore);
        $history->reputationImpact = $newReputation - $oldReputation;
        $history->oldReputation = $oldReputation;
        $history->newReputation = $newReputation;
        $history->recordedAt = new DateTimeImmutable();
        
        return $history;
    }
    
    /**
     * Check if critic was aligned with consensus
     * 
     * @param float $threshold Alignment threshold (default: 0.1)
     * @return bool True if aligned
     */
    public function wasAligned(float $threshold = 0.1): bool
    {
        return $this->alignmentDelta < $threshold;
    }
    
    /**
     * Check if reputation improved
     * 
     * @return bool True if reputation increased
     */
    public function isImprovement(): bool
    {
        return $this->reputationImpact > 0;
    }
    
    /**
     * Get history as array for serialization
     * 
     * @return array History data
     */
    public function toArray(): array
    {
        return [
            'history_id' => $this->historyId ?? null,
            'critic_id' => $this->criticId,
            'evaluation_id' => $this->evaluationId,
            'consensus_score' => $this->consensusScore,
            'critic_score' => $this->criticScore,
            'alignment_delta' => $this->alignmentDelta,
            'reputation_impact' => $this->reputationImpact,
            'old_reputation' => $this->oldReputation,
            'new_reputation' => $this->newReputation,
            'recorded_at' => $this->recordedAt->format('Y-m-d H:i:s')
        ];
    }
}
