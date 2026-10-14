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
 */
class EvaluationContext
{
    /** @var string The unique identifier for this evaluation instance. */
    private string $evaluationId;

    /** @var string The type of output being evaluated (e.g., text, code, JSON). */
    private string $outputType;

    /** @var array<string> Array of required expertise domains needed for evaluation. */
    private array $requiredExpertise;

    /** @var string The priority level of the evaluation (low, medium, high, critical). */
    private string $priorityLevel;

    /** @var array<string> Special execution or processing requirements (e.g., security-sensitive). */
    private array $specialRequirements;

    /** @var string|null The specific business domain (e.g., Ecommerce, Analytics). */
    private ?string $domain;

    /** @var array<string, mixed> Key-value pairs containing additional contextual metadata. */
    private array $metadata;
    
    /**
     * EvaluationContext constructor.
     *
     * @param string $evaluationId Unique identifier for the evaluation.
     * @param string $outputType Target output type format.
     * @param array<string> $requiredExpertise List of necessary domain knowledge areas.
     * @param string $priorityLevel urgency scale: 'low', 'medium', 'high', 'critical'.
     * @param array<string> $specialRequirements Constraints or flags for parsing environment.
     * @param string|null $domain Associated vertical domain.
     * @param array<string, mixed> $metadata Arbitrary key-value store for extensible context.
     */
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
    
    /**
     * Gets the evaluation ID.
     *
     * @return string
     */
    public function getEvaluationId(): string 
    { 
        return $this->evaluationId; 
    }
    
    /**
     * Gets the target output type.
     *
     * @return string
     */
    public function getOutputType(): string 
    { 
        return $this->outputType; 
    }
    
    /**
     * Gets the collection of required expertise areas.
     *
     * @return array<string>
     */
    public function getRequiredExpertise(): array 
    { 
        return $this->requiredExpertise; 
    }
    
    /**
     * Gets the configured priority level.
     *
     * @return string
     */
    public function getPriorityLevel(): string 
    { 
        return $this->priorityLevel; 
    }
    
    /**
     * Gets the specific operational constraints.
     *
     * @return array<string>
     */
    public function getSpecialRequirements(): array 
    { 
        return $this->specialRequirements; 
    }
    
    /**
     * Gets the operational business domain.
     *
     * @return string|null
     */
    public function getDomain(): ?string 
    { 
        return $this->domain; 
    }
    
    /**
     * Gets the structural metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array 
    { 
        return $this->metadata; 
    }
    
    /**
     * Checks if a specific domain expertise is demanded by the context.
     *
     * @param string $expertise The expertise string to check.
     * @return bool True if required, false otherwise.
     */
    public function hasRequiredExpertise(string $expertise): bool
    {
        return in_array($expertise, $this->requiredExpertise, true);
    }
    
    /**
     * Checks if a distinct compliance or environmental constraint is required.
     *
     * @param string $requirement The target requirement string.
     * @return bool True if present, false otherwise.
     */
    public function hasSpecialRequirement(string $requirement): bool
    {
        return in_array($requirement, $this->specialRequirements, true);
    }
    
    /**
     * Confirms if the priority level is designated as critical.
     *
     * @return bool
     */
    public function isCritical(): bool
    {
        return $this->priorityLevel === 'critical';
    }
    
    /**
     * Checks if the evaluation demands prioritized immediate handling ('high' or 'critical').
     *
     * @return bool
     */
    public function isHighPriority(): bool
    {
        return in_array($this->priorityLevel, ['high', 'critical'], true);
    }
    
    /**
     * Safely retrieves a specific key value from the inner metadata payload.
     *
     * @param string $key The internal metadata property key.
     * @return mixed The value mapped to the key, or null if unassigned.
     */
    public function getMetadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }
    
    /**
     * Mutates or registers a metadata contextual asset.
     *
     * @param string $key Property target pointer.
     * @param mixed $value Payload element.
     * @return void
     */
    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }
    
    /**
     * Extracts the instance state map into a plain structural array format.
     *
     * @return array{
     * evaluation_id: string,
     * output_type: string,
     * required_expertise: array<string>,
     * priority_level: string,
     * special_requirements: array<string>,
     * domain: string|null,
     * metadata: array<string, mixed>
     * }
     */
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
