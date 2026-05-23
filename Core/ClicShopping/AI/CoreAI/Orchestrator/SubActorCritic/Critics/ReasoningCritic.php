<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Critics;

use ClicShopping\OM\Registry;

use ClicShopping\AI\InterfacesAI\CriticAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Evaluation;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Prediction;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\EvaluationCriteria;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * ReasoningCritic - Critic agent specialized in evaluating logical reasoning
 * 
 * This critic evaluates reasoning outputs by:
 * - Analyzing logical validity and soundness
 * - Assessing argument strength and coherence
 * - Checking for logical fallacies
 * - Evaluating evidence quality and relevance
 * 
 * Evaluation Dimensions:
 * - Validity: Logical correctness of reasoning
 * - Soundness: Truth of premises and validity of conclusions
 * - Coherence: Internal consistency and clarity
 * - Evidence: Quality and relevance of supporting evidence
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Critics
 * @version 1.0.0
 * @since 2026-01-30
 */
class ReasoningCritic implements CriticAgentInterface
{
    private string $criticId;
    private SecurityLogger $securityLogger;
    private bool $debug;
    private array $evaluationHistory = [];
    
    /**
     * Constructor
     * 
     * @param bool $debug Enable debug mode
     */
    public function __construct(bool $debug = false)
    {
        $this->criticId = 'reasoning_critic_' . uniqid();
        $this->debug = $debug;
        
        // Initialize security logger
        $this->securityLogger = new SecurityLogger();
        
        // Register this critic in the CriticRegistry
        $this->registerInRegistry();
        
        $this->securityLogger->logSecurityEvent(
            "ReasoningCritic initialized: {$this->criticId}",
            'info'
        );
    }
    
    /**
     * Evaluate an action result
     * 
     * @param ActionResult $result Result to evaluate
     * @return Evaluation Complete evaluation with scores and feedback
     * @throws \Exception If evaluation fails
     */
    public function evaluateAction(ActionResult $result): Evaluation
    {
        $startTime = microtime(true);
        
        try {
            $outputType = $result->getOutputType();
            $output = $result->getOutput();
            
            $this->securityLogger->logSecurityEvent(
                "ReasoningCritic evaluating action result: {$outputType}",
                'info',
                ['critic_id' => $this->criticId, 'result_id' => $result->getResultId()]
            );
            
            // Route to appropriate evaluation method based on output type
            $scores = match($outputType) {
                'deductive_conclusion' => $this->evaluateDeductiveReasoning($output, $result),
                'inductive_generalization' => $this->evaluateInductiveReasoning($output, $result),
                'abductive_explanation' => $this->evaluateAbductiveReasoning($output, $result),
                'consistency_result' => $this->evaluateConsistencyCheck($output, $result),
                default => $this->evaluateGenericReasoning($output, $result)
            };
            
            // Generate structured feedback
            $feedback = $this->generateFeedback($scores, $output, $outputType);
            $strengths = $this->identifyStrengths($scores, $output, $outputType);
            $improvements = $this->identifyImprovements($scores, $output, $outputType);
            
            $evaluation = new Evaluation(
                $this->criticId,
                $result->getResultId(),
                $scores,
                $feedback,
                $strengths,
                $improvements
            );
            
            // Store evaluation history
            $this->evaluationHistory[] = [
                'evaluation_id' => $evaluation->getEvaluationId(),
                'output_type' => $outputType,
                'scores' => $scores,
                'overall_score' => $evaluation->getOverallScore(),
                'evaluated_at' => date('Y-m-d H:i:s')
            ];
            
            $evaluationTime = microtime(true) - $startTime;
            
            $this->securityLogger->logSecurityEvent(
                "ReasoningCritic completed evaluation",
                'info',
                [
                    'critic_id' => $this->criticId,
                    'overall_score' => $evaluation->getOverallScore(),
                    'evaluation_time' => $evaluationTime
                ]
            );
            
            return $evaluation;
            
        } catch (\Exception $e) {
            $this->securityLogger->logSecurityEvent(
                "ReasoningCritic evaluation failed: " . $e->getMessage(),
                'error',
                ['critic_id' => $this->criticId, 'result_id' => $result->getResultId()]
            );
            
            throw new \Exception("Evaluation failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Evaluate deductive reasoning output
     * 
     * @param array $output Reasoning output
     * @param ActionResult $result Full action result
     * @return array Dimension scores
     */
    private function evaluateDeductiveReasoning(array $output, ActionResult $result): array
    {
        $conclusion = $output['conclusion'] ?? '';
        $validity = $output['validity'] ?? false;
        $soundness = $output['soundness'] ?? false;
        $reasoningChain = $output['reasoning_chain'] ?? [];
        
        // Validity: Logical correctness
        $validityScore = $this->evaluateValidity($validity, $reasoningChain);
        
        // Soundness: Truth of premises and validity
        $soundnessScore = $this->evaluateSoundness($soundness, $validity);
        
        // Coherence: Internal consistency
        $coherenceScore = $this->evaluateCoherence($reasoningChain, $conclusion);
        
        // Evidence: Quality of reasoning chain
        $evidenceScore = $this->evaluateEvidence($reasoningChain);
        
        return [
            'validity' => $validityScore,
            'soundness' => $soundnessScore,
            'coherence' => $coherenceScore,
            'evidence' => $evidenceScore
        ];
    }
    
    /**
     * Evaluate inductive reasoning output
     * 
     * @param array $output Reasoning output
     * @param ActionResult $result Full action result
     * @return array Dimension scores
     */
    private function evaluateInductiveReasoning(array $output, ActionResult $result): array
    {
        $generalization = $output['generalization'] ?? '';
        $strength = $output['strength'] ?? 0.0;
        $counterexamples = $output['counterexamples'] ?? [];
        $supportingEvidence = $output['supporting_evidence'] ?? [];
        
        // Validity: Strength of inductive argument
        $validityScore = $strength;
        
        // Soundness: Quality of generalization
        $soundnessScore = $this->evaluateGeneralizationQuality($generalization, $supportingEvidence);
        
        // Coherence: Consistency with observations
        $coherenceScore = $this->evaluateInductiveCoherence($generalization, $counterexamples);
        
        // Evidence: Quality and quantity of supporting evidence
        $evidenceScore = $this->evaluateInductiveEvidence($supportingEvidence, $counterexamples);
        
        return [
            'validity' => $validityScore,
            'soundness' => $soundnessScore,
            'coherence' => $coherenceScore,
            'evidence' => $evidenceScore
        ];
    }
    
    /**
     * Evaluate abductive reasoning output
     * 
     * @param array $output Reasoning output
     * @param ActionResult $result Full action result
     * @return array Dimension scores
     */
    private function evaluateAbductiveReasoning(array $output, ActionResult $result): array
    {
        $bestExplanation = $output['best_explanation'] ?? '';
        $alternativeHypotheses = $output['alternative_hypotheses'] ?? [];
        $plausibility = $output['plausibility'] ?? 0.0;
        $supportingObservations = $output['supporting_observations'] ?? [];
        
        // Validity: Plausibility of explanation
        $validityScore = $plausibility;
        
        // Soundness: Quality of explanation
        $soundnessScore = $this->evaluateExplanationQuality($bestExplanation, $supportingObservations);
        
        // Coherence: Consistency with observations
        $coherenceScore = $this->evaluateAbductiveCoherence($bestExplanation, $supportingObservations);
        
        // Evidence: Quality of supporting observations
        $evidenceScore = $this->evaluateAbductiveEvidence($supportingObservations, $alternativeHypotheses);
        
        return [
            'validity' => $validityScore,
            'soundness' => $soundnessScore,
            'coherence' => $coherenceScore,
            'evidence' => $evidenceScore
        ];
    }
    
    /**
     * Evaluate consistency check output
     * 
     * @param array $output Consistency check output
     * @param ActionResult $result Full action result
     * @return array Dimension scores
     */
    private function evaluateConsistencyCheck(array $output, ActionResult $result): array
    {
        $isConsistent = $output['is_consistent'] ?? false;
        $inconsistencies = $output['inconsistencies'] ?? [];
        $conflicts = $output['conflicts'] ?? [];
        $resolutionSuggestions = $output['resolution_suggestions'] ?? [];
        
        // Validity: Correctness of consistency check
        $validityScore = $isConsistent ? 0.95 : 0.85;
        
        // Soundness: Accuracy of identified inconsistencies
        $soundnessScore = $this->evaluateInconsistencyDetection($inconsistencies, $conflicts);
        
        // Coherence: Quality of analysis
        $coherenceScore = $this->evaluateConsistencyAnalysis($inconsistencies, $resolutionSuggestions);
        
        // Evidence: Quality of resolution suggestions
        $evidenceScore = $this->evaluateResolutionQuality($resolutionSuggestions, $inconsistencies);
        
        return [
            'validity' => $validityScore,
            'soundness' => $soundnessScore,
            'coherence' => $coherenceScore,
            'evidence' => $evidenceScore
        ];
    }
    
    /**
     * Evaluate generic reasoning output
     * 
     * @param mixed $output Generic output
     * @param ActionResult $result Full action result
     * @return array Dimension scores
     */
    private function evaluateGenericReasoning($output, ActionResult $result): array
    {
        $hasOutput = !empty($output);
        $isStructured = is_array($output);
        
        return [
            'validity' => $hasOutput ? 0.6 : 0.3,
            'soundness' => $hasOutput ? 0.6 : 0.3,
            'coherence' => $isStructured ? 0.7 : 0.5,
            'evidence' => $hasOutput ? 0.6 : 0.3
        ];
    }
    
    /**
     * Predict outcome of an action before execution
     * 
     * @param Action $action Action to predict
     * @return Prediction Predicted outcome with confidence and risks
     */
    public function predictOutcome(Action $action): Prediction
    {
        $actionType = $action->getType();
        $parameters = $action->getParameters();
        
        // Predict based on action type
        $prediction = match($actionType) {
            'deductive_reasoning' => $this->predictDeductiveReasoning($parameters),
            'inductive_reasoning' => $this->predictInductiveReasoning($parameters),
            'abductive_reasoning' => $this->predictAbductiveReasoning($parameters),
            'consistency_check' => $this->predictConsistencyCheck($parameters),
            default => $this->predictGenericReasoning($parameters)
        };
        
        return new Prediction(
            $action->getActionId(),
            $this->criticId,
            $prediction['outcome'],
            $prediction['confidence'],
            $prediction['risks'],
            $prediction['success_probabilities'],
            $prediction['mitigations']
        );
    }
    
    /**
     * Get evaluation criteria and capabilities
     * 
     * @return array<string, EvaluationCriteria> Map of output types to criteria
     */
    public function getEvaluationCriteria(): array
    {
        return [
            'deductive_conclusion' => new EvaluationCriteria(
                'deductive_conclusion',
                0.9,
                'reasoning',
                ['validity' => 0.4, 'soundness' => 0.3, 'coherence' => 0.2, 'evidence' => 0.1],
                ['logical_validity_check' => true, 'fallacy_detection' => true],
                ['validity' => 0.8, 'soundness' => 0.7, 'coherence' => 0.6, 'evidence' => 0.5]
            ),
            'inductive_generalization' => new EvaluationCriteria(
                'inductive_generalization',
                0.85,
                'reasoning',
                ['validity' => 0.3, 'soundness' => 0.3, 'coherence' => 0.2, 'evidence' => 0.2],
                ['pattern_analysis' => true, 'counterexample_detection' => true],
                ['validity' => 0.7, 'soundness' => 0.7, 'coherence' => 0.6, 'evidence' => 0.6]
            ),
            'abductive_explanation' => new EvaluationCriteria(
                'abductive_explanation',
                0.8,
                'reasoning',
                ['validity' => 0.3, 'soundness' => 0.3, 'coherence' => 0.2, 'evidence' => 0.2],
                ['plausibility_assessment' => true, 'alternative_evaluation' => true],
                ['validity' => 0.7, 'soundness' => 0.6, 'coherence' => 0.6, 'evidence' => 0.6]
            ),
            'consistency_result' => new EvaluationCriteria(
                'consistency_result',
                0.95,
                'reasoning',
                ['validity' => 0.4, 'soundness' => 0.3, 'coherence' => 0.2, 'evidence' => 0.1],
                ['contradiction_detection' => true, 'resolution_analysis' => true],
                ['validity' => 0.9, 'soundness' => 0.8, 'coherence' => 0.7, 'evidence' => 0.6]
            )
        ];
    }
    
    /**
     * Provide detailed feedback for an action result
     * 
     * @param ActionResult $result Result to provide feedback on
     * @return Feedback Structured feedback with strengths and improvements
     */
    public function provideFeedback(ActionResult $result): Feedback
    {
        // Evaluate the result first
        $evaluation = $this->evaluateAction($result);
        
        // Create feedback from evaluation
        return new Feedback(
            $result->getProducerAgentId(),
            $result->getResultId(),
            $evaluation->getOverallScore(),
            [
                'correctness' => [$evaluation->getFeedback()],
                'efficiency' => $evaluation->getOverallScore() >= 0.7 ? ['Good reasoning efficiency'] : ['Needs optimization'],
                'completeness' => $evaluation->getOverallScore() >= 0.7 ? ['Complete reasoning'] : ['Missing elements'],
                'best_practice' => $evaluation->getOverallScore() >= 0.7 ? ['Follows logical principles'] : ['Improve logical rigor']
            ],
            $evaluation->getStrengths(),
            $evaluation->getImprovements()
        );
    }
    
    /**
     * Get unique critic identifier
     * 
     * @return string Critic ID
     */
    public function getCriticId(): string
    {
        return $this->criticId;
    }
    
    /**
     * Register this critic in the CriticRegistry
     * 
     * @return void
     */
    private function registerInRegistry(): void
    {
        try {
            if (!Registry::exists('CriticRegistry')) {
                Registry::set('CriticRegistry', new CriticRegistry());
            }
            
            $registry = Registry::get('CriticRegistry');
            $registry->registerCritic($this);
            
            $this->securityLogger->logSecurityEvent(
                "ReasoningCritic registered in CriticRegistry",
                'info',
                ['critic_id' => $this->criticId]
            );
        } catch (\Exception $e) {
            $this->securityLogger->logSecurityEvent(
                "Failed to register ReasoningCritic: " . $e->getMessage(),
                'error',
                ['critic_id' => $this->criticId]
            );
        }
    }
    
    /**
     * Get evaluation history
     * 
     * @return array Evaluation history
     */
    public function getEvaluationHistory(): array
    {
        return $this->evaluationHistory;
    }
    
    // Helper methods for evaluation
    
    private function evaluateValidity(bool $validity, array $reasoningChain): float
    {
        $score = $validity ? 0.8 : 0.4;
        
        // Bonus for detailed reasoning chain
        if (count($reasoningChain) > 2) {
            $score += 0.1;
        }
        
        return min(1.0, $score);
    }
    
    private function evaluateSoundness(bool $soundness, bool $validity): float
    {
        if ($soundness && $validity) return 0.9;
        if ($validity) return 0.6;
        return 0.4;
    }
    
    private function evaluateCoherence(array $reasoningChain, string $conclusion): float
    {
        $score = 0.6;
        
        // Check if reasoning chain is well-structured
        if (count($reasoningChain) >= 2 && !empty($conclusion)) {
            $score += 0.3;
        }
        
        return min(1.0, $score);
    }
    
    private function evaluateEvidence(array $reasoningChain): float
    {
        // More steps indicate more detailed reasoning
        $stepCount = count($reasoningChain);
        return min(1.0, 0.5 + ($stepCount * 0.1));
    }
    
    private function evaluateGeneralizationQuality(string $generalization, array $supportingEvidence): float
    {
        $score = 0.5;
        
        if (!empty($generalization)) {
            $score += 0.2;
        }
        
        if (count($supportingEvidence) >= 3) {
            $score += 0.2;
        }
        
        return min(1.0, $score);
    }
    
    private function evaluateInductiveCoherence(string $generalization, array $counterexamples): float
    {
        $score = 0.7;
        
        // Penalize if many counterexamples exist
        if (count($counterexamples) > 2) {
            $score -= 0.3;
        }
        
        return max(0.0, min(1.0, $score));
    }
    
    private function evaluateInductiveEvidence(array $supportingEvidence, array $counterexamples): float
    {
        $supportCount = count($supportingEvidence);
        $counterCount = count($counterexamples);
        
        if ($supportCount === 0) return 0.3;
        
        $ratio = $supportCount / max(1, $supportCount + $counterCount);
        return $ratio;
    }
    
    private function evaluateExplanationQuality(string $explanation, array $supportingObservations): float
    {
        $score = 0.5;
        
        if (!empty($explanation)) {
            $score += 0.2;
        }
        
        if (count($supportingObservations) >= 2) {
            $score += 0.2;
        }
        
        return min(1.0, $score);
    }
    
    private function evaluateAbductiveCoherence(string $explanation, array $supportingObservations): float
    {
        $score = 0.6;
        
        if (!empty($explanation) && count($supportingObservations) > 0) {
            $score += 0.3;
        }
        
        return min(1.0, $score);
    }
    
    private function evaluateAbductiveEvidence(array $supportingObservations, array $alternativeHypotheses): float
    {
        $score = 0.5;
        
        // More supporting observations increase score
        $score += min(0.3, count($supportingObservations) * 0.1);
        
        // Having alternatives shows thorough analysis
        if (count($alternativeHypotheses) > 1) {
            $score += 0.1;
        }
        
        return min(1.0, $score);
    }
    
    private function evaluateInconsistencyDetection(array $inconsistencies, array $conflicts): float
    {
        // Accurate detection is key
        return 0.8;
    }
    
    private function evaluateConsistencyAnalysis(array $inconsistencies, array $resolutionSuggestions): float
    {
        $score = 0.7;
        
        // Having resolution suggestions shows thorough analysis
        if (count($resolutionSuggestions) > 0) {
            $score += 0.2;
        }
        
        return min(1.0, $score);
    }
    
    private function evaluateResolutionQuality(array $resolutionSuggestions, array $inconsistencies): float
    {
        if (empty($inconsistencies)) return 0.9;
        
        $suggestionCount = count($resolutionSuggestions);
        $inconsistencyCount = count($inconsistencies);
        
        // Ideally, we have suggestions for each inconsistency
        $ratio = min(1.0, $suggestionCount / max(1, $inconsistencyCount));
        return 0.5 + ($ratio * 0.4);
    }
    
    private function generateFeedback(array $scores, $output, string $outputType): string
    {
        $feedback = [];
        
        $overallScore = ($scores['validity'] * 0.35) + ($scores['soundness'] * 0.3) + 
                       ($scores['coherence'] * 0.2) + ($scores['evidence'] * 0.15);
        
        if ($overallScore >= 0.8) {
            $feedback[] = "Excellent reasoning with strong logical foundation.";
        } elseif ($overallScore >= 0.6) {
            $feedback[] = "Good reasoning with some areas for improvement.";
        } else {
            $feedback[] = "Reasoning needs significant improvement.";
        }
        
        if ($scores['validity'] < 0.6) {
            $feedback[] = "Validity concerns: Check logical correctness.";
        }
        
        if ($scores['soundness'] < 0.6) {
            $feedback[] = "Soundness issues: Verify premises and conclusions.";
        }
        
        if ($scores['coherence'] < 0.6) {
            $feedback[] = "Coherence problems: Improve internal consistency.";
        }
        
        if ($scores['evidence'] < 0.6) {
            $feedback[] = "Evidence issues: Strengthen supporting evidence.";
        }
        
        return implode(' ', $feedback);
    }
    
    private function identifyStrengths(array $scores, $output, string $outputType): array
    {
        $strengths = [];
        
        if ($scores['validity'] >= 0.8) {
            $strengths[] = "Strong logical validity";
        }
        
        if ($scores['soundness'] >= 0.8) {
            $strengths[] = "Sound reasoning with true premises";
        }
        
        if ($scores['coherence'] >= 0.8) {
            $strengths[] = "Excellent internal coherence";
        }
        
        if ($scores['evidence'] >= 0.8) {
            $strengths[] = "Strong supporting evidence";
        }
        
        return $strengths;
    }
    
    private function identifyImprovements(array $scores, $output, string $outputType): array
    {
        $improvements = [];
        
        if ($scores['validity'] < 0.7) {
            $improvements[] = "Improve logical validity of arguments";
        }
        
        if ($scores['soundness'] < 0.7) {
            $improvements[] = "Verify truth of premises and soundness";
        }
        
        if ($scores['coherence'] < 0.7) {
            $improvements[] = "Enhance internal consistency and coherence";
        }
        
        if ($scores['evidence'] < 0.7) {
            $improvements[] = "Strengthen supporting evidence and examples";
        }
        
        return $improvements;
    }
    
    private function predictDeductiveReasoning(array $parameters): array
    {
        $premises = $parameters['premises'] ?? [];
        $confidence = 0.8;
        $risks = [];
        
        if (count($premises) > 10) {
            $confidence -= 0.2;
            $risks[] = [
                'type' => 'complexity',
                'description' => 'Many premises may lead to complex reasoning',
                'probability' => 0.5
            ];
        }
        
        return [
            'outcome' => 'Deductive conclusion will be derived',
            'confidence' => max(0.1, min(1.0, $confidence)),
            'risks' => $risks,
            'success_probabilities' => ['valid' => $confidence, 'invalid' => 1 - $confidence],
            'mitigations' => ['Verify each reasoning step', 'Check for logical fallacies']
        ];
    }
    
    private function predictInductiveReasoning(array $parameters): array
    {
        $observations = $parameters['observations'] ?? [];
        $confidence = 0.7;
        $risks = [];
        
        if (count($observations) < 5) {
            $confidence -= 0.2;
            $risks[] = [
                'type' => 'insufficient_data',
                'description' => 'Few observations may lead to weak generalization',
                'probability' => 0.6
            ];
        }
        
        return [
            'outcome' => 'Inductive generalization will be formed',
            'confidence' => max(0.1, min(1.0, $confidence)),
            'risks' => $risks,
            'success_probabilities' => ['strong' => $confidence, 'weak' => 1 - $confidence],
            'mitigations' => ['Gather more observations', 'Look for counterexamples']
        ];
    }
    
    private function predictAbductiveReasoning(array $parameters): array
    {
        return [
            'outcome' => 'Best explanation will be identified',
            'confidence' => 0.75,
            'risks' => [
                [
                    'type' => 'alternative_explanations',
                    'description' => 'Multiple plausible explanations may exist',
                    'probability' => 0.5
                ]
            ],
            'success_probabilities' => ['best' => 0.75, 'suboptimal' => 0.25],
            'mitigations' => ['Consider multiple hypotheses', 'Evaluate plausibility carefully']
        ];
    }
    
    private function predictConsistencyCheck(array $parameters): array
    {
        return [
            'outcome' => 'Consistency will be checked successfully',
            'confidence' => 0.9,
            'risks' => [],
            'success_probabilities' => ['accurate' => 0.9, 'missed_issues' => 0.1],
            'mitigations' => []
        ];
    }
    
    private function predictGenericReasoning(array $parameters): array
    {
        return [
            'outcome' => 'Reasoning will be performed with unknown quality',
            'confidence' => 0.5,
            'risks' => [
                [
                    'type' => 'unknown',
                    'description' => 'Unknown reasoning type may have unpredictable results',
                    'probability' => 0.5
                ]
            ],
            'success_probabilities' => ['success' => 0.5, 'failure' => 0.5],
            'mitigations' => ['Monitor reasoning closely', 'Validate results manually']
        ];
    }
}
