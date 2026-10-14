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
 */
class ConsensusResult
{
    /** @var string The unique identifier for the evaluation instance. */
    private string $evaluationId;

    /** @var float Score generated using dynamic, adaptive algorithmic weighting. */
    private float $dynamicConsensus;

    /** @var float Score generated using static, reputation-only weights. */
    private float $staticConsensus;

    /** @var float Calculated variance value between the dynamic and static approaches. */
    private float $consensusDifference;

    /** @var array<string, float> Map of individual weights per critic id ($criticId => $weightedScore). */
    private array $weightedScores;

    /** @var float Trust ratio or probability score regarding the calculated consensus accuracy. */
    private float $confidenceLevel;

    /** @var string LLM-generated qualitative assessment of the output consensus. */
    private string $consensusQuality;

    /** @var \DateTimeImmutable Precise point in time when the consensus math was resolved. */
    private \DateTimeImmutable $calculatedAt;
    
    /**
     * ConsensusResult constructor.
     *
     * @param string $evaluationId Target identification pointer.
     * @param float $dynamicConsensus Score derived through contextual adaptivity.
     * @param float $staticConsensus Score derived through rigid/historical metrics.
     * @param float $consensusDifference Precalculated comparative shift metric.
     * @param array<string, float> $weightedScores Map of isolated metric actor outputs.
     * @param float $confidenceLevel Aggregated evaluation confidence value.
     * @param string $consensusQuality Textual summary profiling matrix output quality.
     */
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
    
    /**
     * Gets the associated evaluation ID.
     *
     * @return string
     */
    public function getEvaluationId(): string 
    { 
        return $this->evaluationId; 
    }
    
    /**
     * Gets the calculated dynamic consensus score.
     *
     * @return float
     */
    public function getDynamicConsensus(): float 
    { 
        return $this->dynamicConsensus; 
    }
    
    /**
     * Gets the baseline static consensus score.
     *
     * @return float
     */
    public function getStaticConsensus(): float 
    { 
        return $this->staticConsensus; 
    }
    
    /**
     * Gets the numerical variance between both approaches.
     *
     * @return float
     */
    public function getConsensusDifference(): float 
    { 
        return $this->consensusDifference; 
    }
    
    /**
     * Gets the full map of individual weighted critic scores.
     *
     * @return array<string, float>
     */
    public function getWeightedScores(): array 
    { 
        return $this->weightedScores; 
    }
    
    /**
     * Gets the overall engine confidence score.
     *
     * @return float
     */
    public function getConfidenceLevel(): float 
    { 
        return $this->confidenceLevel; 
    }
    
    /**
     * Gets the semantic quality analysis overview.
     *
     * @return string
     */
    public function getConsensusQuality(): string 
    { 
        return $this->consensusQuality; 
    }
    
    /**
     * Gets the immutable timestamp of the calculation step.
     *
     * @return \DateTimeImmutable
     */
    public function getCalculatedAt(): \DateTimeImmutable 
    { 
        return $this->calculatedAt; 
    }
    
    /**
     * Retrieves the isolated weighted score context for an individual critic.
     *
     * @param string $criticId Target identifier string for the sub-actor.
     * @return float|null The calculated score, or null if unmapped.
     */
    public function getWeightedScore(string $criticId): ?float
    {
        return $this->weightedScores[$criticId] ?? null;
    }
    
    /**
     * Calculates the percentage performance gap of the dynamic approach vs the static approach.
     *
     * @return float Percentage relative change (could be negative if regression occurs).
     */
    public function getImprovementPercentage(): float
    {
        if ($this->staticConsensus == 0) {
            return 0.0;
        }
        
        return (($this->dynamicConsensus - $this->staticConsensus) / $this->staticConsensus) * 100;
    }
    
    /**
     * Validates if the adaptive execution provided an increased optimization yield over the static profile.
     *
     * @return bool True if dynamic model out-performed baseline.
     */
    public function isDynamicBetter(): bool
    {
        return $this->dynamicConsensus > $this->staticConsensus;
    }
    
    /**
     * Flattens the complete domain model metrics into a serialized serializable native state array.
     *
     * @return array{
     * evaluation_id: string,
     * dynamic_consensus: float,
     * static_consensus: float,
     * consensus_difference: float,
     * weighted_scores: array<string, float>,
     * confidence_level: float,
     * consensus_quality: string,
     * calculated_at: string,
     * improvement_percentage: float,
     * is_dynamic_better: bool
     * }
     */
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
