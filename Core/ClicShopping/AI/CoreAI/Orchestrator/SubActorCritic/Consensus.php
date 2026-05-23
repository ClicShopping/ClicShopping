<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

/**
 * Consensus class
 * 
 * Represents aggregated consensus from multiple critic evaluations.
 * Contains the consensus score, whether consensus was reached,
 * aggregated feedback, and outlier information.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class Consensus
{
    private string $consensusId;
    private string $outputId;
    private array $evaluations;
    private float $consensusScore;
    private bool $consensusReached;
    private array $aggregatedFeedback;
    private array $outliers;
    private \DateTimeImmutable $createdAt;
    
    public function __construct(
        string $outputId,
        array $evaluations,
        float $consensusScore,
        bool $consensusReached,
        array $aggregatedFeedback,
        array $outliers = []
    ) {
        $this->consensusId = $this->generateId();
        $this->outputId = $outputId;
        $this->evaluations = $evaluations;
        $this->consensusScore = $consensusScore;
        $this->consensusReached = $consensusReached;
        $this->aggregatedFeedback = $aggregatedFeedback;
        $this->outliers = $outliers;
        $this->createdAt = new \DateTimeImmutable();
    }
    
    public function getConsensusId(): string { return $this->consensusId; }
    public function getOutputId(): string { return $this->outputId; }
    public function getEvaluations(): array { return $this->evaluations; }
    public function getScore(): float { return $this->consensusScore; }
    public function isReached(): bool { return $this->consensusReached; }
    public function getAggregatedFeedback(): array { return $this->aggregatedFeedback; }
    public function getOutliers(): array { return $this->outliers; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    
    private function generateId(): string
    {
        return 'consensus_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
    
    public function toArray(): array
    {
        return [
            'consensus_id' => $this->consensusId,
            'output_id' => $this->outputId,
            'evaluations' => array_map(fn($e) => $e->toArray(), $this->evaluations),
            'consensus_score' => $this->consensusScore,
            'consensus_reached' => $this->consensusReached,
            'aggregated_feedback' => $this->aggregatedFeedback,
            'outliers' => $this->outliers,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s')
        ];
    }
}