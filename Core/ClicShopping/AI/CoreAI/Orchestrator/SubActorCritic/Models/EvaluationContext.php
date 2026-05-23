<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models;

/**
 * EvaluationContext class
 * 
 * Describes the evaluation scenario and requirements for adaptive weighting.
 * Contains output type, required expertise, priority level, and special requirements.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Models
 * @version 1.0.0
 * @since 2026-02-06
 */
class EvaluationContext
{
    private string $evaluationId;
    private string $outputType;           // Type of output being evaluated
    private array $requiredExpertise;     // Required expertise domains
    private string $priorityLevel;        // low, medium, high, critical
    private array $specialRequirements;   // security-sensitive, performance-critical, etc.
    private ?string $domain;              // Business domain (Ecommerce, Analytics, etc.)
    private array $metadata;              // Additional context
    
    public function __construct(
        string $evaluationId,
        string $outputType,
        array $requiredExpertise = [],
        string $priorityLevel = 'medium',
        array $specialRequirements = [],
        ?string $domain = null,
        array $metadata = []
    ) {
        $this->evaluationId = $evaluationId;
        $this->outputType = $outputType;
        $this->requiredExpertise = $requiredExpertise;
        $this->priorityLevel = $priorityLevel;
        $this->specialRequirements = $specialRequirements;
        $this->domain = $domain;
        $this->metadata = $metadata;
    }
    
    public function getEvaluationId(): string 
    { 
        return $this->evaluationId; 
    }
    
    public function getOutputType(): string 
    { 
        return $this->outputType; 
    }
    
    public function getRequiredExpertise(): array 
    { 
        return $this->requiredExpertise; 
    }
    
    public function getPriorityLevel(): string 
    { 
        return $this->priorityLevel; 
    }
    
    public function getSpecialRequirements(): array 
    { 
        return $this->specialRequirements; 
    }
    
    public function getDomain(): ?string 
    { 
        return $this->domain; 
    }
    
    public function getMetadata(): array 
    { 
        return $this->metadata; 
    }
    
    public function hasRequiredExpertise(string $expertise): bool
    {
        return in_array($expertise, $this->requiredExpertise, true);
    }
    
    public function hasSpecialRequirement(string $requirement): bool
    {
        return in_array($requirement, $this->specialRequirements, true);
    }
    
    public function isCritical(): bool
    {
        return $this->priorityLevel === 'critical';
    }
    
    public function isHighPriority(): bool
    {
        return in_array($this->priorityLevel, ['high', 'critical'], true);
    }
    
    public function getMetadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }
    
    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }
    
    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'output_type' => $this->outputType,
            'required_expertise' => $this->requiredExpertise,
            'priority_level' => $this->priorityLevel,
            'special_requirements' => $this->specialRequirements,
            'domain' => $this->domain,
            'metadata' => $this->metadata
        ];
    }
}
