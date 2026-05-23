<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

/**
 * Evaluation class
 * 
 * Represents a critic's evaluation of an action result.
 * Contains dimension scores (accuracy, completeness, efficiency, clarity),
 * overall score, and structured feedback.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class Evaluation
{
    private string $evaluationId;
    private string $evaluatorAgentId;
    private string $outputId;
    private float $accuracyScore;
    private float $completenessScore;
    private float $efficiencyScore;
    private float $clarityScore;
    private float $overallScore;
    private string $feedback;
    private array $strengths;
    private array $improvements;
    private \DateTimeImmutable $evaluatedAt;
    
    public function __construct(
        string $evaluatorAgentId,
        string $outputId,
        array $scores,
        string $feedback,
        array $strengths,
        array $improvements
    ) {
        $this->evaluationId = $this->generateId();
        $this->evaluatorAgentId = $evaluatorAgentId;
        $this->outputId = $outputId;
        $this->accuracyScore = $scores['accuracy'];
        $this->completenessScore = $scores['completeness'];
        $this->efficiencyScore = $scores['efficiency'];
        $this->clarityScore = $scores['clarity'];
        $this->overallScore = $this->calculateOverallScore($scores);
        $this->feedback = $feedback;
        $this->strengths = $strengths;
        $this->improvements = $improvements;
        $this->evaluatedAt = new \DateTimeImmutable();
    }
    
    private function calculateOverallScore(array $scores): float
    {
        // Weighted average: accuracy (35%), completeness (25%), efficiency (25%), clarity (15%)
        return ($scores['accuracy'] * 0.35) +
               ($scores['completeness'] * 0.25) +
               ($scores['efficiency'] * 0.25) +
               ($scores['clarity'] * 0.15);
    }
    
    public function getEvaluationId(): string { return $this->evaluationId; }
    public function getEvaluatorAgentId(): string { return $this->evaluatorAgentId; }
    public function getOutputId(): string { return $this->outputId; }
    public function getAccuracyScore(): float { return $this->accuracyScore; }
    public function getCompletenessScore(): float { return $this->completenessScore; }
    public function getEfficiencyScore(): float { return $this->efficiencyScore; }
    public function getClarityScore(): float { return $this->clarityScore; }
    public function getOverallScore(): float { return $this->overallScore; }
    public function getFeedback(): string { return $this->feedback; }
    public function getStrengths(): array { return $this->strengths; }
    public function getImprovements(): array { return $this->improvements; }
    public function getEvaluatedAt(): \DateTimeImmutable { return $this->evaluatedAt; }
    
    private function generateId(): string
    {
        return 'eval_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
    
    public function toArray(): array
    {
        return [
            'evaluation_id' => $this->evaluationId,
            'evaluator_agent_id' => $this->evaluatorAgentId,
            'output_id' => $this->outputId,
            'accuracy_score' => $this->accuracyScore,
            'completeness_score' => $this->completenessScore,
            'efficiency_score' => $this->efficiencyScore,
            'clarity_score' => $this->clarityScore,
            'overall_score' => $this->overallScore,
            'feedback' => $this->feedback,
            'strengths' => $this->strengths,
            'improvements' => $this->improvements,
            'evaluated_at' => $this->evaluatedAt->format('Y-m-d H:i:s')
        ];
    }
}