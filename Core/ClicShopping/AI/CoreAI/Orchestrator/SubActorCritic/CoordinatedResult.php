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
 * CoordinatedResult class
 * 
 * Represents the complete result of actor-critic coordination.
 * Contains the action result, evaluations, consensus, feedback,
 * and coordination metadata.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class CoordinatedResult
{
    private string $coordinationId;
    private ActionResult $actionResult;
    private array $evaluations;
    private Consensus $consensus;
    private Feedback $feedback;
    private array $metadata;
    private ?array $adaptiveWeights;
    private ?array $weightExplanations;
    private ?array $domainAnalysis;
    private ?array $consensusComparison;
    
    public function __construct(
        ActionResult $actionResult,
        array $evaluations,
        Consensus $consensus,
        Feedback $feedback,
        array $metadata,
        ?array $adaptiveWeights = null,
        ?array $weightExplanations = null,
        ?array $domainAnalysis = null,
        ?array $consensusComparison = null
    ) {
        $this->coordinationId = $this->generateId();
        $this->actionResult = $actionResult;
        $this->evaluations = $evaluations;
        $this->consensus = $consensus;
        $this->feedback = $feedback;
        $this->metadata = $metadata;
        $this->adaptiveWeights = $adaptiveWeights;
        $this->weightExplanations = $weightExplanations;
        $this->domainAnalysis = $domainAnalysis;
        $this->consensusComparison = $consensusComparison;
    }
    
    public function getCoordinationId(): string { return $this->coordinationId; }
    public function getActionResult(): ActionResult { return $this->actionResult; }
    public function getEvaluations(): array { return $this->evaluations; }
    public function getConsensus(): Consensus { return $this->consensus; }
    public function getFeedback(): Feedback { return $this->feedback; }
    public function getMetadata(): array { return $this->metadata; }
    public function getAdaptiveWeights(): ?array { return $this->adaptiveWeights; }
    public function getWeightExplanations(): ?array { return $this->weightExplanations; }
    public function getDomainAnalysis(): ?array { return $this->domainAnalysis; }
    public function getConsensusComparison(): ?array { return $this->consensusComparison; }
    
    public function getFinalOutput(): mixed
    {
        return $this->actionResult->getOutput();
    }
    
    public function getConsensusScore(): float
    {
        return $this->consensus->getScore();
    }
    
    public function getAggregatedFeedback(): array
    {
        return $this->consensus->getAggregatedFeedback();
    }
    
    private function generateId(): string
    {
        return 'coord_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
    
    public function toArray(): array
    {
        return [
            'coordination_id' => $this->coordinationId,
            'action_result' => $this->actionResult->toArray(),
            'evaluations' => array_map(fn($e) => $e->toArray(), $this->evaluations),
            'consensus' => $this->consensus->toArray(),
            'feedback' => $this->feedback->toArray(),
            'metadata' => $this->metadata,
            'adaptive_weights' => $this->adaptiveWeights,
            'weight_explanations' => $this->weightExplanations,
            'domain_analysis' => $this->domainAnalysis,
            'consensus_comparison' => $this->consensusComparison
        ];
    }
}