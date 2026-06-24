<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models;

/**
 * WeightExplanation class
 * 
 * Detailed explanation for a critic's weight assignment.
 * Contains natural language explanation, factor influence analysis,
 * and identified strengths/concerns.
 */
class WeightExplanation
{
    /** @var string Unique identifier for the evaluated critic. */
    private string $criticId;

    /** @var float The final calculated weight allocated to the critic. */
    private float $weight;

    /** @var string Natural language explanation describing why this weight was assigned. */
    private string $explanation;

    /** @var array<string, float> Impact breakdown matrix mapping a specific factor to its structural influence. */
    private array $factorInfluence;

    /** @var string The primary key factor driving the consensus/weighting variance. */
    private string $dominantFactor;

    /** @var array<string> Array of qualitative risk issues or architectural concerns flagged by the LLM. */
    private array $concerns;

    /** @var array<string> Array of positive performance indices or capabilities flagged by the LLM. */
    private array $strengths;
    
    /**
     * WeightExplanation constructor.
     *
     * @param string $criticId The target critic identifier string.
     * @param float $weight Allocated strategic influence factor.
     * @param string $explanation Plain text summary explaining the assignment context.
     * @param array<string, float> $factorInfluence Set of relative contextual parameter pressures.
     * @param string $dominantFactor Primary key defining the main vector of weight assignment.
     * @param array<string> $concerns Dynamic compilation of operational warning conditions.
     * @param array<string> $strengths Dynamic compilation of positive capabilities.
     */
    public function __construct(
        string $criticId,
        float $weight,
        string $explanation,
        array $factorInfluence = [],
        string $dominantFactor = '',
        array $concerns = [],
        array $strengths = []
    ) {
        $this->criticId = $criticId;
        $this->weight = $weight;
        $this->explanation = $explanation;
        $this->factorInfluence = $factorInfluence;
        $this->dominantFactor = $dominantFactor;
        $this->concerns = $concerns;
        $this->strengths = $strengths;
    }
    
    /**
     * Gets the associated critic identifier.
     *
     * @return string
     */
    public function getCriticId(): string 
    { 
        return $this->criticId; 
    }
    
    /**
     * Gets the designated numerical score weight.
     *
     * @return float
     */
    public function getWeight(): float 
    { 
        return $this->weight; 
    }
    
    /**
     * Gets the textual contextual breakdown rationale string.
     *
     * @return string
     */
    public function getExplanation(): string 
    { 
        return $this->explanation; 
    }
    
    /**
     * Gets the dynamic map capturing how specific indicators scaled the weight distribution.
     *
     * @return array<string, float>
     */
    public function getFactorInfluence(): array 
    { 
        return $this->factorInfluence; 
    }
    
    /**
     * Gets the highest contributing driver factor.
     *
     * @return string
     */
    public function getDominantFactor(): string 
    { 
        return $this->dominantFactor; 
    }
    
    /**
     * Gets the tracked collection of structural vulnerabilities or behavioral concerns.
     *
     * @return array<string>
     */
    public function getConcerns(): array 
    { 
        return $this->concerns; 
    }
    
    /**
     * Gets the monitored capabilities or optimization vectors.
     *
     * @return array<string>
     */
    public function getStrengths(): array 
    { 
        return $this->strengths; 
    }
    
    /**
     * Safely retrieves the numeric impact metric score for a particular analytical factor.
     *
     * @param string $factor The factor key label to search for.
     * @return float|null The scalar impact value, or null if key is unassigned.
     */
    public function getFactorValue(string $factor): ?float
    {
        return $this->factorInfluence[$factor] ?? null;
    }
    
    /**
     * Checks if the explanation payload registers any behavioral warnings or operational concerns.
     *
     * @return bool True if concerns map contains one or more entries.
     */
    public function hasConcerns(): bool
    {
        return !empty($this->concerns);
    }
    
    /**
     * Checks if the analytical pass recognized distinct performance or semantic strengths.
     *
     * @return bool True if strengths map contains one or more entries.
     */
    public function hasStrengths(): bool
    {
        return !empty($this->strengths);
    }
    
    /**
     * Flattens the domain object state into an explicit native PHP format map.
     *
     * @return array{
     * critic_id: string,
     * weight: float,
     * explanation: string,
     * factor_influence: array<string, float>,
     * dominant_factor: string,
     * concerns: array<string>,
     * strengths: array<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'critic_id' => $this->criticId,
            'weight' => $this->weight,
            'explanation' => $this->explanation,
            'factor_influence' => $this->factorInfluence,
            'dominant_factor' => $this->dominantFactor,
            'concerns' => $this->concerns,
            'strengths' => $this->strengths
        ];
    }
}
