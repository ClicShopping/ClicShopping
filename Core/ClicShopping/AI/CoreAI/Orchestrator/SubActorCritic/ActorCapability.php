<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

/**
 * ActorCapability class
 * 
 * Represents an actor's capability to execute a specific action type.
 * Contains confidence level, domain specialization, and performance metrics.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class ActorCapability
{
    private string $actionType;
    private float $confidence; // 0.0-1.0
    private string $domain; // 'analytics', 'semantic', 'validation', 'reasoning', etc.
    private string $expertiseLevel; // 'novice', 'competent', 'expert'
    private array $supportedParameters;
    private array $performanceMetrics;
    
    public function __construct(
        string $actionType,
        float $confidence,
        string $domain,
        string $expertiseLevel = 'competent',
        array $supportedParameters = [],
        array $performanceMetrics = []
    ) {
        $this->actionType = $actionType;
        $this->confidence = max(0.0, min(1.0, $confidence)); // Clamp to 0.0-1.0
        $this->domain = $domain;
        $this->expertiseLevel = $expertiseLevel;
        $this->supportedParameters = $supportedParameters;
        $this->performanceMetrics = $performanceMetrics;
    }
    
    public function getActionType(): string { return $this->actionType; }
    public function getConfidence(): float { return $this->confidence; }
    public function getDomain(): string { return $this->domain; }
    public function getExpertiseLevel(): string { return $this->expertiseLevel; }
    public function getSupportedParameters(): array { return $this->supportedParameters; }
    public function getPerformanceMetrics(): array { return $this->performanceMetrics; }
    
    public function updateConfidence(float $newConfidence): void
    {
        $this->confidence = max(0.0, min(1.0, $newConfidence));
    }
    
    public function updatePerformanceMetrics(array $metrics): void
    {
        $this->performanceMetrics = array_merge($this->performanceMetrics, $metrics);
    }
    
    public function supportsParameter(string $parameter): bool
    {
        return in_array($parameter, $this->supportedParameters, true);
    }
    
    public function getPerformanceMetric(string $metric): mixed
    {
        return $this->performanceMetrics[$metric] ?? null;
    }
    
    public function toArray(): array
    {
        return [
            'action_type' => $this->actionType,
            'confidence' => $this->confidence,
            'domain' => $this->domain,
            'expertise_level' => $this->expertiseLevel,
            'supported_parameters' => $this->supportedParameters,
            'performance_metrics' => $this->performanceMetrics
        ];
    }
}