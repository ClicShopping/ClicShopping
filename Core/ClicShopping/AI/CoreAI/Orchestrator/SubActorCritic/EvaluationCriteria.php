<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

/**
 * EvaluationCriteria class
 * 
 * Represents a critic's evaluation criteria for a specific output type.
 * Contains expertise level, domain specialization, and evaluation weights.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class EvaluationCriteria
{
    private string $outputType;
    private float $expertiseLevel; // 0.0-1.0
    private string $domain; // 'analytics', 'semantic', 'validation', 'reasoning', etc.
    private array $evaluationWeights; // weights for accuracy, completeness, efficiency, clarity
    private array $specificCriteria; // output-type specific criteria
    private array $qualityThresholds; // minimum acceptable scores
    
    public function __construct(
        string $outputType,
        float $expertiseLevel,
        string $domain,
        array $evaluationWeights = [],
        array $specificCriteria = [],
        array $qualityThresholds = []
    ) {
        $this->outputType = $outputType;
        $this->expertiseLevel = max(0.0, min(1.0, $expertiseLevel)); // Clamp to 0.0-1.0
        $this->domain = $domain;
        $this->evaluationWeights = $this->normalizeWeights($evaluationWeights);
        $this->specificCriteria = $specificCriteria;
        $this->qualityThresholds = $qualityThresholds;
    }
    
    public function getOutputType(): string { return $this->outputType; }
    public function getExpertiseLevel(): float { return $this->expertiseLevel; }
    public function getDomain(): string { return $this->domain; }
    public function getEvaluationWeights(): array { return $this->evaluationWeights; }
    public function getSpecificCriteria(): array { return $this->specificCriteria; }
    public function getQualityThresholds(): array { return $this->qualityThresholds; }
    
    public function updateExpertiseLevel(float $newLevel): void
    {
        $this->expertiseLevel = max(0.0, min(1.0, $newLevel));
    }
    
    public function getWeight(string $dimension): float
    {
        return $this->evaluationWeights[$dimension] ?? $this->getDefaultWeight($dimension);
    }
    
    public function getThreshold(string $dimension): float
    {
        return $this->qualityThresholds[$dimension] ?? 0.5; // Default threshold
    }
    
    public function hasSpecificCriterion(string $criterion): bool
    {
        return isset($this->specificCriteria[$criterion]);
    }
    
    public function getSpecificCriterion(string $criterion): mixed
    {
        return $this->specificCriteria[$criterion] ?? null;
    }
    
    private function normalizeWeights(array $weights): array
    {
        // Default weights if not provided
        $defaultWeights = [
            'accuracy' => 0.35,
            'completeness' => 0.25,
            'efficiency' => 0.25,
            'clarity' => 0.15
        ];
        
        if (empty($weights)) {
            return $defaultWeights;
        }
        
        // Normalize weights to sum to 1.0
        $total = array_sum($weights);
        if ($total > 0) {
            foreach ($weights as $key => $value) {
                $weights[$key] = $value / $total;
            }
        }
        
        return array_merge($defaultWeights, $weights);
    }
    
    private function getDefaultWeight(string $dimension): float
    {
        $defaults = [
            'accuracy' => 0.35,
            'completeness' => 0.25,
            'efficiency' => 0.25,
            'clarity' => 0.15
        ];
        
        return $defaults[$dimension] ?? 0.0;
    }
    
    public function toArray(): array
    {
        return [
            'output_type' => $this->outputType,
            'expertise_level' => $this->expertiseLevel,
            'domain' => $this->domain,
            'evaluation_weights' => $this->evaluationWeights,
            'specific_criteria' => $this->specificCriteria,
            'quality_thresholds' => $this->qualityThresholds
        ];
    }
}