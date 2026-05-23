<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models;

/**
 * ConsensusResult class
 * 
 * Result of consensus calculation with both dynamic (adaptive) and static approaches.
 * Contains consensus scores, weighted scores per critic, and quality metrics.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models
 * @version 1.0.0
 * @since 2026-02-06
 */
class ConsensusResult
{
    private string $evaluationId;
    private float $dynamicConsensus;      // Using adaptive weights
    private float $staticConsensus;       // Using reputation-only weights
    private float $consensusDifference;   // Difference between approaches
    private array $weightedScores;        // [criticId => weighted_score]
    private float $confidenceLevel;       // Confidence in consensus
    private string $consensusQuality;     // LLM assessment of quality
    private \DateTimeImmutable $calculatedAt;
    
    public function __construct(
        string $evaluationId,
        float $dynamicConsensus,
        float $staticConsensus,
        float $consensusDifference,
        array $weightedScores,
        float $confidenceLevel = 0.0,
        string $consensusQuality = ''
    ) {
        $this->evaluationId = $evaluationId;
        $this->dynamicConsensus = $dynamicConsensus;
        $this->staticConsensus = $staticConsensus;
        $this->consensusDifference = $consensusDifference;
        $this->weightedScores = $weightedScores;
        $this->confidenceLevel = $confidenceLevel;
        $this->consensusQuality = $consensusQuality;
        $this->calculatedAt = new \DateTimeImmutable();
    }
    
    public function getEvaluationId(): string 
    { 
        return $this->evaluationId; 
    }
    
    public function getDynamicConsensus(): float 
    { 
        return $this->dynamicConsensus; 
    }
    
    public function getStaticConsensus(): float 
    { 
        return $this->staticConsensus; 
    }
    
    public function getConsensusDifference(): float 
    { 
        return $this->consensusDifference; 
    }
    
    public function getWeightedScores(): array 
    { 
        return $this->weightedScores; 
    }
    
    public function getConfidenceLevel(): float 
    { 
        return $this->confidenceLevel; 
    }
    
    public function getConsensusQuality(): string 
    { 
        return $this->consensusQuality; 
    }
    
    public function getCalculatedAt(): \DateTimeImmutable 
    { 
        return $this->calculatedAt; 
    }
    
    public function getWeightedScore(string $criticId): ?float
    {
        return $this->weightedScores[$criticId] ?? null;
    }
    
    public function getImprovementPercentage(): float
    {
        if ($this->staticConsensus == 0) {
            return 0.0;
        }
        
        return (($this->dynamicConsensus - $this->staticConsensus) / $this->staticConsensus) * 100;
    }
    
    public function isDynamicBetter(): bool
    {
        return $this->dynamicConsensus > $this->staticConsensus;
    }
    
    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'dynamic_consensus' => $this->dynamicConsensus,
            'static_consensus' => $this->staticConsensus,
            'consensus_difference' => $this->consensusDifference,
            'weighted_scores' => $this->weightedScores,
            'confidence_level' => $this->confidenceLevel,
            'consensus_quality' => $this->consensusQuality,
            'calculated_at' => $this->calculatedAt->format('Y-m-d H:i:s'),
            'improvement_percentage' => $this->getImprovementPercentage(),
            'is_dynamic_better' => $this->isDynamicBetter()
        ];
    }
}
