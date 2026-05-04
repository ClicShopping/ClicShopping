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
 * Prediction class
 * 
 * Represents a critic's prediction of an action's outcome before execution.
 * Contains predicted results, confidence estimates, and identified risks.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class Prediction
{
    private string $predictionId;
    private string $actionId;
    private string $predictorAgentId;
    private mixed $predictedOutcome;
    private float $confidenceEstimate; // 0.0-1.0
    private array $identifiedRisks;
    private array $successProbabilities; // probabilities for different outcome scenarios
    private array $recommendedMitigations;
    private \DateTimeImmutable $predictedAt;
    
    public function __construct(
        string $actionId,
        string $predictorAgentId,
        mixed $predictedOutcome,
        float $confidenceEstimate,
        array $identifiedRisks = [],
        array $successProbabilities = [],
        array $recommendedMitigations = []
    ) {
        $this->predictionId = $this->generateId();
        $this->actionId = $actionId;
        $this->predictorAgentId = $predictorAgentId;
        $this->predictedOutcome = $predictedOutcome;
        $this->confidenceEstimate = max(0.0, min(1.0, $confidenceEstimate));
        $this->identifiedRisks = $identifiedRisks;
        $this->successProbabilities = $successProbabilities;
        $this->recommendedMitigations = $recommendedMitigations;
        $this->predictedAt = new \DateTimeImmutable();
    }
    
    public function getPredictionId(): string { return $this->predictionId; }
    public function getActionId(): string { return $this->actionId; }
    public function getPredictorAgentId(): string { return $this->predictorAgentId; }
    public function getPredictedOutcome(): mixed { return $this->predictedOutcome; }
    public function getConfidenceEstimate(): float { return $this->confidenceEstimate; }
    public function getIdentifiedRisks(): array { return $this->identifiedRisks; }
    public function getSuccessProbabilities(): array { return $this->successProbabilities; }
    public function getRecommendedMitigations(): array { return $this->recommendedMitigations; }
    public function getPredictedAt(): \DateTimeImmutable { return $this->predictedAt; }
    
    public function addRisk(string $riskType, string $description, float $probability): void
    {
        $this->identifiedRisks[] = [
            'type' => $riskType,
            'description' => $description,
            'probability' => max(0.0, min(1.0, $probability))
        ];
    }
    
    public function addMitigation(string $riskType, string $mitigation): void
    {
        if (!isset($this->recommendedMitigations[$riskType])) {
            $this->recommendedMitigations[$riskType] = [];
        }
        $this->recommendedMitigations[$riskType][] = $mitigation;
    }
    
    public function getHighRisks(float $threshold = 0.7): array
    {
        return array_filter($this->identifiedRisks, fn($risk) => $risk['probability'] >= $threshold);
    }
    
    public function getOverallRiskScore(): float
    {
        if (empty($this->identifiedRisks)) {
            return 0.0;
        }
        
        $totalRisk = 0.0;
        foreach ($this->identifiedRisks as $risk) {
            $totalRisk += $risk['probability'];
        }
        
        return min(1.0, $totalRisk / count($this->identifiedRisks));
    }
    
    private function generateId(): string
    {
        return 'prediction_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
    
    public function toArray(): array
    {
        return [
            'prediction_id' => $this->predictionId,
            'action_id' => $this->actionId,
            'predictor_agent_id' => $this->predictorAgentId,
            'predicted_outcome' => $this->predictedOutcome,
            'confidence_estimate' => $this->confidenceEstimate,
            'identified_risks' => $this->identifiedRisks,
            'success_probabilities' => $this->successProbabilities,
            'recommended_mitigations' => $this->recommendedMitigations,
            'overall_risk_score' => $this->getOverallRiskScore(),
            'predicted_at' => $this->predictedAt->format('Y-m-d H:i:s')
        ];
    }
}