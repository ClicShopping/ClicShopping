<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models;

/**
 * WeightResult class
 * 
 * Result of LLM weight analysis containing weights, explanations, and rationale.
 * Represents the complete output from the adaptive weighting engine.
 */
class WeightResult
{
    /** @var array<string, float> Map of dynamic weights assigned to each critic ($criticId => $weight). */
    private array $weights;

    /** @var array<string, float> Map of normalized weights assigned to each critic ($criticId => $normalizedWeight). */
    public array $normalizedWeights;

    /** @var string Unique identifier for the evaluation instance. */
    private string $evaluationId;

    /** @var array<string, string> Map of explanations for each critic ID ($criticId => $explanation). */
    private array $explanations;

    /** @var string The comprehensive structural reasoning behind the weight decisions. */
    private string $overallRationale;

    /** @var array<string, mixed> Breakdown matrix analyzing which factors were most critical. */
    private array $factorAnalysis;

    /** @var array<string, float>|null Defined minimum and maximum limits applied to the process. */
    private ?array $bounds;

    /** @var \DateTimeImmutable Precise timestamp recording the calculation execution time. */
    private \DateTimeImmutable $calculatedAt;

    /** @var bool Flag determining whether fallback algorithm defaults were activated. */
    private bool $isFallback;

    /** @var string|null Rationale or error message explaining why fallback defaults were triggered. */
    private ?string $fallbackReason;
    
    /**
     * WeightResult constructor.
     *
     * @param string $evaluationId Target identification pointer.
     * @param array<string, float> $weights Raw contextual scores before alignment.
     * @param array<string, float> $normalizedWeights Transformed relative weights.
     * @param array<string, string> $explanations Set of qualitative insights mapped per critic.
     * @param string $overallRationale High-level text summarizing execution dynamics.
     * @param array<string, mixed> $factorAnalysis Metrics highlighting key parameters.
     * @param array<string, float>|null $bounds Threshold boundaries configured for constraints.
     * @param bool $isFallback State marker denoting non-standard processing path.
     * @param string|null $fallbackReason Detailed contextual fault description if a fallback occurred.
     */
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
     * Gets the collection of raw, unnormalized weights.
     *
     * @return array<string, float>
     */
    public function getWeights(): array 
    { 
        return $this->weights; 
    }
    
    /**
     * Gets the collection of balanced, normalized weights.
     *
     * @return array<string, float>
     */
    public function getNormalizedWeights(): array 
    { 
        return $this->normalizedWeights; 
    }
    
    /**
     * Gets the mapped explanation structures.
     *
     * @return array<string, string>
     */
    public function getExplanations(): array 
    { 
        return $this->explanations; 
    }
    
    /**
     * Gets the structural rationale summary.
     *
     * @return string
     */
    public function getOverallRationale(): string 
    { 
        return $this->overallRationale; 
    }
    
    /**
     * Gets the analytical parameters matrix.
     *
     * @return array<string, mixed>
     */
    public function getFactorAnalysis(): array 
    { 
        return $this->factorAnalysis; 
    }
    
    /**
     * Gets the min/max optimization bounds constraints if configured.
     *
     * @return array<string, float>|null
     */
    public function getBounds(): ?array 
    { 
        return $this->bounds; 
    }
    
    /**
     * Gets the calculation execution timestamp block.
     *
     * @return \DateTimeImmutable
     */
    public function getCalculatedAt(): \DateTimeImmutable 
    { 
        return $this->calculatedAt; 
    }
    
    /**
     * Assesses whether execution relied on secondary static configuration patterns.
     *
     * @return bool True if a fallback strategy was used.
     */
    public function isFallback(): bool 
    { 
        return $this->isFallback; 
    }
    
    /**
     * Gets the failure description detailing why the model shifted into fallback mode.
     *
     * @return string|null
     */
    public function getFallbackReason(): ?string 
    { 
        return $this->fallbackReason; 
    }
    
    /**
     * Safely retrieves an isolated normalized weight property map for an individual critic.
     *
     * @param string $criticId Target identification string.
     * @return float|null Target factor calculation or null if unassigned.
     */
    public function getWeight(string $criticId): ?float
    {
        return $this->normalizedWeights[$criticId] ?? null;
    }
    
    /**
     * Safely retrieves an isolated explanation commentary string for an individual critic.
     *
     * @param string $criticId Target identification string.
     * @return string|null Rationale string or null if unassigned.
     */
    public function getExplanation(string $criticId): ?string
    {
        return $this->explanations[$criticId] ?? null;
    }
    
    /**
     * Serializes the structural payload data array to a clean data transfer map.
     *
     * @return array{
     * evaluation_id: string,
     * weights: array<string, float>,
     * normalized_weights: array<string, float>,
     * explanations: array<string, string>,
     * overall_rationale: string,
     * factor_analysis: array<string, mixed>,
     * bounds: array<string, float>|null,
     * calculated_at: string,
     * is_fallback: bool,
     * fallback_reason: string|null
     * }
     */
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