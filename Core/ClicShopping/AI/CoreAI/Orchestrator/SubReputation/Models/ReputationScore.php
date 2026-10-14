<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models;

use DateTimeImmutable;

/**
 * ReputationScore - Data model for critic reputation scores
 * 
 * Represents a critic's reputation score and associated metrics.
 * 
 * Requirements: 1.1, 1.6, 2.1, 2.2, 2.3, 2.4
 */
class ReputationScore
{
    public string $criticId;
    public float $reputationScore;      // 0.0-1.0
    public float $consensusAlignment;   // 0.0-1.0
    public float $feedbackQuality;      // 0.0-1.0
    public float $consistencyScore;     // 0.0-1.0
    public float $expertiseAccuracy;    // 0.0-1.0
    public int $totalEvaluations;
    public string $status;              // 'bootstrapping', 'establishing', 'established'
    public DateTimeImmutable $calculatedAt;
    public DateTimeImmutable $lastDecayAt;
    
    /**
     * Create a new ReputationScore with default values
     * 
     * @param string $criticId Critic identifier
     * @return self New reputation score with defaults
     */
    public static function createDefault(string $criticId): self
    {
        $reputation = new self();
        $reputation->criticId = $criticId;
        $reputation->reputationScore = 0.75;  // Default neutral reputation
        $reputation->consensusAlignment = 0.75;
        $reputation->feedbackQuality = 0.75;
        $reputation->consistencyScore = 0.75;
        $reputation->expertiseAccuracy = 0.75;
        $reputation->totalEvaluations = 0;
        $reputation->status = 'bootstrapping';
        $reputation->calculatedAt = new DateTimeImmutable();
        $reputation->lastDecayAt = new DateTimeImmutable();
        
        return $reputation;
    }
    
    /**
     * Check if reputation is within valid bounds
     * 
     * @return bool True if valid
     */
    public function isValid(): bool
    {
        return $this->reputationScore >= 0.5 
            && $this->reputationScore <= 1.0
            && $this->consensusAlignment >= 0.0
            && $this->consensusAlignment <= 1.0
            && $this->feedbackQuality >= 0.0
            && $this->feedbackQuality <= 1.0
            && $this->consistencyScore >= 0.0
            && $this->consistencyScore <= 1.0
            && $this->expertiseAccuracy >= 0.0
            && $this->expertiseAccuracy <= 1.0
            && $this->totalEvaluations >= 0
            && in_array($this->status, ['bootstrapping', 'establishing', 'established'], true);
    }
    
    /**
     * Get reputation as array for serialization
     * 
     * @return array Reputation data
     */
    public function toArray(): array
    {
        return [
            'critic_id' => $this->criticId,
            'reputation_score' => $this->reputationScore,
            'consensus_alignment' => $this->consensusAlignment,
            'feedback_quality' => $this->feedbackQuality,
            'consistency_score' => $this->consistencyScore,
            'expertise_accuracy' => $this->expertiseAccuracy,
            'total_evaluations' => $this->totalEvaluations,
            'status' => $this->status,
            'calculated_at' => $this->calculatedAt->format('Y-m-d H:i:s'),
            'last_decay_at' => $this->lastDecayAt->format('Y-m-d H:i:s')
        ];
    }
}
