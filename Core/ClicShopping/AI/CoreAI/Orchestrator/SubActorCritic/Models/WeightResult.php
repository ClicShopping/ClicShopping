<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models;

/**
 * WeightResult class
 * 
 * Result of LLM weight analysis containing weights, explanations, and rationale.
 * Represents the complete output from the adaptive weighting engine.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models
 * @version 1.0.0
 * @since 2026-02-06
 */
class WeightResult
{
    public array $normalizedWeights;
    private string $evaluationId;               // [criticId => weight]
    private array $weights;     // [criticId => normalized_weight]
    private array $explanations;          // [criticId => explanation]
    private string $overallRationale;     // LLM's overall reasoning
    private array $factorAnalysis;        // Which factors were most important
    private ?array $bounds;               // Min/max bounds if applied
    private \DateTimeImmutable $calculatedAt;
    private bool $isFallback;             // Whether this used fallback weighting
    private ?string $fallbackReason;      // Reason for fallback if applicable
    
    public function __construct(
        string $evaluationId,
        array $weights,
        array $normalizedWeights,
        array $explanations,
        string $overallRationale,
        array $factorAnalysis = [],
        ?array $bounds = null,
        bool $isFallback = false,
        ?string $fallbackReason = null
    ) {
        $this->evaluationId = $evaluationId;
        $this->weights = $weights;
        $this->normalizedWeights = $normalizedWeights;
        $this->explanations = $explanations;
        $this->overallRationale = $overallRationale;
        $this->factorAnalysis = $factorAnalysis;
        $this->bounds = $bounds;
        $this->calculatedAt = new \DateTimeImmutable();
        $this->isFallback = $isFallback;
        $this->fallbackReason = $fallbackReason;
    }
    
    public function getEvaluationId(): string 
    { 
        return $this->evaluationId; 
    }
    
    public function getWeights(): array 
    { 
        return $this->weights; 
    }
    
    public function getNormalizedWeights(): array 
    { 
        return $this->normalizedWeights; 
    }
    
    public function getExplanations(): array 
    { 
        return $this->explanations; 
    }
    
    public function getOverallRationale(): string 
    { 
        return $this->overallRationale; 
    }
    
    public function getFactorAnalysis(): array 
    { 
        return $this->factorAnalysis; 
    }
    
    public function getBounds(): ?array 
    { 
        return $this->bounds; 
    }
    
    public function getCalculatedAt(): \DateTimeImmutable 
    { 
        return $this->calculatedAt; 
    }
    
    public function isFallback(): bool 
    { 
        return $this->isFallback; 
    }
    
    public function getFallbackReason(): ?string 
    { 
        return $this->fallbackReason; 
    }
    
    public function getWeight(string $criticId): ?float
    {
        return $this->normalizedWeights[$criticId] ?? null;
    }
    
    public function getExplanation(string $criticId): ?string
    {
        return $this->explanations[$criticId] ?? null;
    }
    
    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'weights' => $this->weights,
            'normalized_weights' => $this->normalizedWeights,
            'explanations' => $this->explanations,
            'overall_rationale' => $this->overallRationale,
            'factor_analysis' => $this->factorAnalysis,
            'bounds' => $this->bounds,
            'calculated_at' => $this->calculatedAt->format('Y-m-d H:i:s'),
            'is_fallback' => $this->isFallback,
            'fallback_reason' => $this->fallbackReason
        ];
    }
}
