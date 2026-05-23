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
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models
 * @version 1.0.0
 * @since 2026-02-06
 */
class WeightExplanation
{
    private string $criticId;
    private float $weight;
    private string $explanation;          // Natural language explanation
    private array $factorInfluence;       // How each factor influenced the weight
    private string $dominantFactor;       // Which factor had most influence
    private array $concerns;              // Any concerns identified by LLM
    private array $strengths;             // Strengths identified by LLM
    
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
    
    public function getCriticId(): string 
    { 
        return $this->criticId; 
    }
    
    public function getWeight(): float 
    { 
        return $this->weight; 
    }
    
    public function getExplanation(): string 
    { 
        return $this->explanation; 
    }
    
    public function getFactorInfluence(): array 
    { 
        return $this->factorInfluence; 
    }
    
    public function getDominantFactor(): string 
    { 
        return $this->dominantFactor; 
    }
    
    public function getConcerns(): array 
    { 
        return $this->concerns; 
    }
    
    public function getStrengths(): array 
    { 
        return $this->strengths; 
    }
    
    public function getFactorValue(string $factor): ?float
    {
        return $this->factorInfluence[$factor] ?? null;
    }
    
    public function hasConcerns(): bool
    {
        return !empty($this->concerns);
    }
    
    public function hasStrengths(): bool
    {
        return !empty($this->strengths);
    }
    
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
