<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
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
 * ValidationCritic - Critic agent specialized in evaluating validation results
 * 
 * This critic evaluates validation outputs by:
 * - Assessing validation thoroughness and coverage
 * - Evaluating error detection accuracy
 * - Checking validation rule completeness
 * - Analyzing validation performance
 * 
 * Evaluation Dimensions:
 * - Thoroughness: Completeness of validation checks
 * - Accuracy: Correctness of validation results
 * - Coverage: Breadth of validation rules applied
 * - Performance: Efficiency of validation process
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Critics
 * @version 1.0.0
 * @since 2026-01-30
 */
class ValidationCritic implements CriticAgentInterface
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
        $this->criticId = 'validation_critic_' . uniqid();
        $this->debug = $debug;
        
        // Initialize security logger
        $this->securityLogger = new SecurityLogger();
        
        // Register this critic in the CriticRegistry
        $this->registerInRegistry();
        
        $this->securityLogger->logSecurityEvent(
            "ValidationCritic initialized: {$this->criticId}",
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
                "ValidationCritic evaluating action result: {$outputType}",
                'info',
                ['critic_id' => $this->criticId, 'result_id' => $result->getResultId()]
            );
            
            // Route to appropriate evaluation method based on output type
            $scores = match($outputType) {
                'schema_validation_result' => $this->evaluateSchemaValidation($output, $result),
                'business_rule_result' => $this->evaluateBusinessRuleValidation($output, $result),
                'integrity_check_result' => $this->evaluateIntegrityCheck($output, $result),
                'format_validation_result' => $this->evaluateFormatValidation($output, $result),
                default => $this->evaluateGenericValidation($output, $result)
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
                "ValidationCritic completed evaluation",
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
                "ValidationCritic evaluation failed: " . $e->getMessage(),
                'error',
                ['critic_id' => $this->criticId, 'result_id' => $result->getResultId()]
            );
            
            throw new \Exception("Evaluation failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Evaluate schema validation output
     * 
     * @param array $output Validation output
     * @param ActionResult $result Full action result
     * @return array Dimension scores
     */
    private function evaluateSchemaValidation(array $output, ActionResult $result): array
    {
        $isValid = $output['is_valid'] ?? false;
        $errors = $output['errors'] ?? [];
        $warnings = $output['warnings'] ?? [];
        $validatedFields = $output['validated_fields'] ?? [];
        $missingFields = $output['missing_fields'] ?? [];
        
        // Thoroughness: Completeness of validation
        $thoroughnessScore = $this->evaluateThoroughness($validatedFields, $missingFields);
        
        // Accuracy: Correctness of error detection
        $accuracyScore = $this->evaluateAccuracy($isValid, $errors);
        
        // Coverage: Breadth of validation rules
        $coverageScore = $this->evaluateCoverage($validatedFields, $errors, $warnings);
        
        // Performance: Efficiency of validation
        $performanceScore = $this->evaluatePerformance($result->getExecutionMetrics());
        
        return [
            'thoroughness' => $thoroughnessScore,
            'accuracy' => $accuracyScore,
            'coverage' => $coverageScore,
            'performance' => $performanceScore
        ];
    }
    
    /**
     * Evaluate business rule validation output
     * 
     * @param array $output Validation output
     * @param ActionResult $result Full action result
     * @return array Dimension scores
     */
    private function evaluateBusinessRuleValidation(array $output, ActionResult $result): array
    {
        $isCompliant = $output['is_compliant'] ?? false;
        $violations = $output['violations'] ?? [];
        $rulesPassed = $output['rules_passed'] ?? [];
        $complianceScore = $output['compliance_score'] ?? 0.0;
        
        // Thoroughness: Completeness of rule checking
        $thoroughnessScore = $this->evaluateRuleThoroughness($rulesPassed, $violations);
        
        // Accuracy: Correctness of violation detection
        $accuracyScore = $this->evaluateViolationAccuracy($isCompliant, $violations);
        
        // Coverage: Breadth of rules checked
        $coverageScore = $complianceScore;
        
        // Performance: Efficiency of rule checking
        $performanceScore = $this->evaluatePerformance($result->getExecutionMetrics());
        
        return [
            'thoroughness' => $thoroughnessScore,
            'accuracy' => $accuracyScore,
            'coverage' => $coverageScore,
            'performance' => $performanceScore
        ];
    }
    
    /**
     * Evaluate integrity check output
     * 
     * @param array $output Integrity check output
     * @param ActionResult $result Full action result
     * @return array Dimension scores
     */
    private function evaluateIntegrityCheck(array $output, ActionResult $result): array
    {
        $isIntact = $output['is_intact'] ?? false;
        $integrityIssues = $output['integrity_issues'] ?? [];
        $consistencyScore = $output['consistency_score'] ?? 0.0;
        $duplicates = $output['duplicate_detection'] ?? [];
        
        // Thoroughness: Completeness of integrity checks
        $thoroughnessScore = $this->evaluateIntegrityThoroughness($integrityIssues, $duplicates);
        
        // Accuracy: Correctness of issue detection
        $accuracyScore = $this->evaluateIntegrityAccuracy($isIntact, $integrityIssues);
        
        // Coverage: Breadth of integrity checks
        $coverageScore = $consistencyScore;
        
        // Performance: Efficiency of integrity checking
        $performanceScore = $this->evaluatePerformance($result->getExecutionMetrics());
        
        return [
            'thoroughness' => $thoroughnessScore,
            'accuracy' => $accuracyScore,
            'coverage' => $coverageScore,
            'performance' => $performanceScore
        ];
    }
    
    /**
     * Evaluate format validation output
     * 
     * @param array $output Format validation output
     * @param ActionResult $result Full action result
     * @return array Dimension scores
     */
    private function evaluateFormatValidation(array $output, ActionResult $result): array
    {
        $isValid = $output['is_valid'] ?? false;
        $formatErrors = $output['format_errors'] ?? [];
        $typeErrors = $output['type_errors'] ?? [];
        $formatCompliance = $output['format_compliance'] ?? 0.0;
        
        // Thoroughness: Completeness of format checks
        $thoroughnessScore = $this->evaluateFormatThoroughness($formatErrors, $typeErrors);
        
        // Accuracy: Correctness of format validation
        $accuracyScore = $this->evaluateFormatAccuracy($isValid, $formatErrors, $typeErrors);
        
        // Coverage: Breadth of format rules
        $coverageScore = $formatCompliance;
        
        // Performance: Efficiency of format checking
        $performanceScore = $this->evaluatePerformance($result->getExecutionMetrics());
        
        return [
            'thoroughness' => $thoroughnessScore,
            'accuracy' => $accuracyScore,
            'coverage' => $coverageScore,
            'performance' => $performanceScore
        ];
    }
    
    /**
     * Evaluate generic validation output
     * 
     * @param mixed $output Generic output
     * @param ActionResult $result Full action result
     * @return array Dimension scores
     */
    private function evaluateGenericValidation($output, ActionResult $result): array
    {
        $hasOutput = !empty($output);
        $isStructured = is_array($output);
        
        return [
            'thoroughness' => $hasOutput ? 0.6 : 0.3,
            'accuracy' => $hasOutput ? 0.6 : 0.3,
            'coverage' => $isStructured ? 0.7 : 0.5,
            'performance' => 0.6
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
            'schema_validation' => $this->predictSchemaValidation($parameters),
            'business_rule_validation' => $this->predictBusinessRuleValidation($parameters),
            'data_integrity_check' => $this->predictIntegrityCheck($parameters),
            'format_validation' => $this->predictFormatValidation($parameters),
            default => $this->predictGenericValidation($parameters)
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
            'schema_validation_result' => new EvaluationCriteria(
                'schema_validation_result',
                0.95,
                'validation',
                ['thoroughness' => 0.3, 'accuracy' => 0.4, 'coverage' => 0.2, 'performance' => 0.1],
                ['schema_analysis' => true, 'error_detection' => true],
                ['thoroughness' => 0.8, 'accuracy' => 0.9, 'coverage' => 0.7, 'performance' => 0.6]
            ),
            'business_rule_result' => new EvaluationCriteria(
                'business_rule_result',
                0.9,
                'validation',
                ['thoroughness' => 0.3, 'accuracy' => 0.4, 'coverage' => 0.2, 'performance' => 0.1],
                ['rule_analysis' => true, 'violation_detection' => true],
                ['thoroughness' => 0.8, 'accuracy' => 0.9, 'coverage' => 0.7, 'performance' => 0.6]
            ),
            'integrity_check_result' => new EvaluationCriteria(
                'integrity_check_result',
                0.9,
                'validation',
                ['thoroughness' => 0.3, 'accuracy' => 0.4, 'coverage' => 0.2, 'performance' => 0.1],
                ['integrity_analysis' => true, 'consistency_check' => true],
                ['thoroughness' => 0.8, 'accuracy' => 0.9, 'coverage' => 0.7, 'performance' => 0.6]
            ),
            'format_validation_result' => new EvaluationCriteria(
                'format_validation_result',
                0.95,
                'validation',
                ['thoroughness' => 0.3, 'accuracy' => 0.4, 'coverage' => 0.2, 'performance' => 0.1],
                ['format_analysis' => true, 'type_checking' => true],
                ['thoroughness' => 0.8, 'accuracy' => 0.9, 'coverage' => 0.7, 'performance' => 0.6]
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
                'efficiency' => $evaluation->getOverallScore() >= 0.7 ? ['Good validation efficiency'] : ['Needs optimization'],
                'completeness' => $evaluation->getOverallScore() >= 0.7 ? ['Complete validation'] : ['Missing checks'],
                'best_practice' => $evaluation->getOverallScore() >= 0.7 ? ['Follows validation best practices'] : ['Improve validation rigor']
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
                "ValidationCritic registered in CriticRegistry",
                'info',
                ['critic_id' => $this->criticId]
            );
        } catch (\Exception $e) {
            $this->securityLogger->logSecurityEvent(
                "Failed to register ValidationCritic: " . $e->getMessage(),
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
    
    private function evaluateThoroughness(array $validatedFields, array $missingFields): float
    {
        $totalFields = count($validatedFields) + count($missingFields);
        if ($totalFields === 0) return 0.5;
        
        return count($validatedFields) / $totalFields;
    }
    
    private function evaluateAccuracy(bool $isValid, array $errors): float
    {
        $score = 0.7;
        
        if ($isValid && empty($errors)) {
            $score = 0.95;
        } elseif (!$isValid && !empty($errors)) {
            $score = 0.9;
        } elseif ($isValid && !empty($errors)) {
            $score = 0.5; // Inconsistent result
        }
        
        return $score;
    }
    
    private function evaluateCoverage(array $validatedFields, array $errors, array $warnings): float
    {
        $score = 0.6;
        
        // More validated fields indicate better coverage
        $score += min(0.3, count($validatedFields) * 0.05);
        
        // Having warnings shows thorough checking
        if (!empty($warnings)) {
            $score += 0.1;
        }
        
        return min(1.0, $score);
    }
    
    private function evaluatePerformance(array $metrics): float
    {
        $executionTime = $metrics['execution_time'] ?? 0.0;
        
        $score = 0.8;
        
        // Penalize slow validation
        if ($executionTime > 5.0) {
            $score -= 0.3;
        } elseif ($executionTime > 2.0) {
            $score -= 0.1;
        }
        
        return max(0.0, min(1.0, $score));
    }
    
    private function evaluateRuleThoroughness(array $rulesPassed, array $violations): float
    {
        $totalRules = count($rulesPassed) + count($violations);
        if ($totalRules === 0) return 0.5;
        
        return 0.5 + (count($rulesPassed) / $totalRules) * 0.5;
    }
    
    private function evaluateViolationAccuracy(bool $isCompliant, array $violations): float
    {
        if ($isCompliant && empty($violations)) return 0.95;
        if (!$isCompliant && !empty($violations)) return 0.9;
        return 0.5; // Inconsistent result
    }
    
    private function evaluateIntegrityThoroughness(array $integrityIssues, array $duplicates): float
    {
        $score = 0.7;
        
        // Having duplicate detection shows thoroughness
        if (is_array($duplicates)) {
            $score += 0.2;
        }
        
        return min(1.0, $score);
    }
    
    private function evaluateIntegrityAccuracy(bool $isIntact, array $integrityIssues): float
    {
        if ($isIntact && empty($integrityIssues)) return 0.95;
        if (!$isIntact && !empty($integrityIssues)) return 0.9;
        return 0.5; // Inconsistent result
    }
    
    private function evaluateFormatThoroughness(array $formatErrors, array $typeErrors): float
    {
        $score = 0.7;
        
        // Having both format and type checking shows thoroughness
        if (is_array($formatErrors) && is_array($typeErrors)) {
            $score += 0.2;
        }
        
        return min(1.0, $score);
    }
    
    private function evaluateFormatAccuracy(bool $isValid, array $formatErrors, array $typeErrors): float
    {
        $totalErrors = count($formatErrors) + count($typeErrors);
        
        if ($isValid && $totalErrors === 0) return 0.95;
        if (!$isValid && $totalErrors > 0) return 0.9;
        return 0.5; // Inconsistent result
    }
    
    private function generateFeedback(array $scores, $output, string $outputType): string
    {
        $feedback = [];
        
        $overallScore = ($scores['thoroughness'] * 0.3) + ($scores['accuracy'] * 0.4) + 
                       ($scores['coverage'] * 0.2) + ($scores['performance'] * 0.1);
        
        if ($overallScore >= 0.8) {
            $feedback[] = "Excellent validation with comprehensive coverage.";
        } elseif ($overallScore >= 0.6) {
            $feedback[] = "Good validation with some areas for improvement.";
        } else {
            $feedback[] = "Validation needs significant improvement.";
        }
        
        if ($scores['thoroughness'] < 0.6) {
            $feedback[] = "Thoroughness concerns: Increase validation coverage.";
        }
        
        if ($scores['accuracy'] < 0.6) {
            $feedback[] = "Accuracy issues: Verify error detection correctness.";
        }
        
        if ($scores['coverage'] < 0.6) {
            $feedback[] = "Coverage problems: Add more validation rules.";
        }
        
        if ($scores['performance'] < 0.6) {
            $feedback[] = "Performance issues: Optimize validation process.";
        }
        
        return implode(' ', $feedback);
    }
    
    private function identifyStrengths(array $scores, $output, string $outputType): array
    {
        $strengths = [];
        
        if ($scores['thoroughness'] >= 0.8) {
            $strengths[] = "Thorough and comprehensive validation";
        }
        
        if ($scores['accuracy'] >= 0.8) {
            $strengths[] = "Accurate error detection";
        }
        
        if ($scores['coverage'] >= 0.8) {
            $strengths[] = "Excellent validation coverage";
        }
        
        if ($scores['performance'] >= 0.8) {
            $strengths[] = "Efficient validation process";
        }
        
        return $strengths;
    }
    
    private function identifyImprovements(array $scores, $output, string $outputType): array
    {
        $improvements = [];
        
        if ($scores['thoroughness'] < 0.7) {
            $improvements[] = "Improve validation thoroughness and completeness";
        }
        
        if ($scores['accuracy'] < 0.7) {
            $improvements[] = "Enhance error detection accuracy";
        }
        
        if ($scores['coverage'] < 0.7) {
            $improvements[] = "Expand validation rule coverage";
        }
        
        if ($scores['performance'] < 0.7) {
            $improvements[] = "Optimize validation performance";
        }
        
        return $improvements;
    }
    
    private function predictSchemaValidation(array $parameters): array
    {
        $schema = $parameters['schema'] ?? [];
        $confidence = 0.9;
        $risks = [];
        
        if (count($schema) > 50) {
            $confidence -= 0.1;
            $risks[] = [
                'type' => 'complexity',
                'description' => 'Large schema may slow validation',
                'probability' => 0.4
            ];
        }
        
        return [
            'outcome' => 'Schema validation will be performed',
            'confidence' => max(0.1, min(1.0, $confidence)),
            'risks' => $risks,
            'success_probabilities' => ['accurate' => $confidence, 'issues' => 1 - $confidence],
            'mitigations' => ['Optimize schema structure', 'Cache validation results']
        ];
    }
    
    private function predictBusinessRuleValidation(array $parameters): array
    {
        $rules = $parameters['rules'] ?? [];
        $confidence = 0.85;
        $risks = [];
        
        if (count($rules) > 20) {
            $confidence -= 0.15;
            $risks[] = [
                'type' => 'complexity',
                'description' => 'Many rules may slow validation',
                'probability' => 0.5
            ];
        }
        
        return [
            'outcome' => 'Business rules will be validated',
            'confidence' => max(0.1, min(1.0, $confidence)),
            'risks' => $risks,
            'success_probabilities' => ['compliant' => $confidence, 'violations' => 1 - $confidence],
            'mitigations' => ['Prioritize critical rules', 'Parallelize rule checking']
        ];
    }
    
    private function predictIntegrityCheck(array $parameters): array
    {
        return [
            'outcome' => 'Data integrity will be checked',
            'confidence' => 0.9,
            'risks' => [],
            'success_probabilities' => ['intact' => 0.9, 'issues' => 0.1],
            'mitigations' => []
        ];
    }
    
    private function predictFormatValidation(array $parameters): array
    {
        return [
            'outcome' => 'Format validation will be performed',
            'confidence' => 0.95,
            'risks' => [],
            'success_probabilities' => ['valid' => 0.95, 'errors' => 0.05],
            'mitigations' => []
        ];
    }
    
    private function predictGenericValidation(array $parameters): array
    {
        return [
            'outcome' => 'Validation will be performed with unknown quality',
            'confidence' => 0.5,
            'risks' => [
                [
                    'type' => 'unknown',
                    'description' => 'Unknown validation type may have unpredictable results',
                    'probability' => 0.5
                ]
            ],
            'success_probabilities' => ['success' => 0.5, 'failure' => 0.5],
            'mitigations' => ['Monitor validation closely', 'Validate results manually']
        ];
    }
}
