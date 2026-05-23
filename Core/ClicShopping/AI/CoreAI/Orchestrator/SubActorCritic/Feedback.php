<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

/**
 * Feedback class
 * 
 * Represents structured feedback delivered to actors.
 * Contains categorized feedback, strengths, improvements,
 * and acknowledgment tracking.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class Feedback
{
    private string $feedbackId;
    private string $targetActorId;
    private string $outputId;
    private float $consensusScore;
    private array $categorizedFeedback; // ['correctness' => [], 'efficiency' => [], ...]
    private array $strengths;
    private array $improvements;
    private bool $acknowledged;
    private ?\DateTimeImmutable $acknowledgedAt;
    
    public function __construct(
        string $targetActorId,
        string $outputId,
        float $consensusScore,
        array $categorizedFeedback,
        array $strengths,
        array $improvements
    ) {
        $this->feedbackId = $this->generateId();
        $this->targetActorId = $targetActorId;
        $this->outputId = $outputId;
        $this->consensusScore = $consensusScore;
        $this->categorizedFeedback = $categorizedFeedback;
        $this->strengths = $strengths;
        $this->improvements = $improvements;
        $this->acknowledged = false;
        $this->acknowledgedAt = null;
    }
    
    public function acknowledge(): void
    {
        $this->acknowledged = true;
        $this->acknowledgedAt = new \DateTimeImmutable();
    }
    
    public function getFeedbackId(): string { return $this->feedbackId; }
    public function getTargetActorId(): string { return $this->targetActorId; }
    public function getOutputId(): string { return $this->outputId; }
    public function getConsensusScore(): float { return $this->consensusScore; }
    public function getCategorizedFeedback(): array { return $this->categorizedFeedback; }
    public function getStrengths(): array { return $this->strengths; }
    public function getImprovements(): array { return $this->improvements; }
    public function isAcknowledged(): bool { return $this->acknowledged; }
    public function getAcknowledgedAt(): ?\DateTimeImmutable { return $this->acknowledgedAt; }
    
    private function generateId(): string
    {
        return 'feedback_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
    
    public function toArray(): array
    {
        return [
            'feedback_id' => $this->feedbackId,
            'target_actor_id' => $this->targetActorId,
            'output_id' => $this->outputId,
            'consensus_score' => $this->consensusScore,
            'categorized_feedback' => $this->categorizedFeedback,
            'strengths' => $this->strengths,
            'improvements' => $this->improvements,
            'acknowledged' => $this->acknowledged,
            'acknowledged_at' => $this->acknowledgedAt?->format('Y-m-d H:i:s')
        ];
    }
}