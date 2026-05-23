<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Critics;

use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Evaluation;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\EvaluationCriteria;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Prediction;
use ClicShopping\AI\DomainsAI\Analytics\Validator\AnalyticsQualityEvaluator;
use ClicShopping\AI\InterfacesAI\CriticAgentInterface;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\Registry;

/**
 * AnalyticsCriticWrapper - Minimal wrapper delegating to AnalyticsQualityEvaluator
 * 
 * This wrapper maintains CriticAgentInterface compatibility while delegating
 * all business logic to AnalyticsQualityEvaluator in DomainsAI/Analytics/Validator/.
 * 
 * Responsibilities:
 * - Implement CriticAgentInterface
 * - Route by output type (sql_query, query_results, schema_info)
 * - Convert ActionResult to evaluator input format
 * - Convert evaluator output to Evaluation/Feedback format
 * - Register in CriticRegistry
 * - NO business logic (pure delegation)
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Critics
 * @version 1.0.0
 * @since 2026-05-03
 */
class AnalyticsCriticWrapper implements CriticAgentInterface
{
    private string $criticId;
    private AnalyticsQualityEvaluator $evaluator;
    private SecurityLogger $securityLogger;
    private bool $debug;
    private array $evaluationHistory = [];

    /**
     * Constructor
     * 
     * @param AnalyticsQualityEvaluator|null $evaluator Optional evaluator instance (for testing)
     * @param bool $debug Enable debug logging
     */
    public function __construct(?AnalyticsQualityEvaluator $evaluator = null, bool $debug = false)
    {
        $this->criticId = 'analytics_critic_wrapper_' . uniqid();
        $this->debug = $debug;
        $this->securityLogger = new SecurityLogger();
        
        // Use provided evaluator or create a new one
        // Note: AnalyticsQualityEvaluator requires all validators to be injected
        // For production use, evaluator should be provided via dependency injection
        if ($evaluator === null) {
            throw new \InvalidArgumentException(
                "AnalyticsCriticWrapper requires an AnalyticsQualityEvaluator instance. " .
                "Please provide it via constructor injection."
            );
        }
        
        $this->evaluator = $evaluator;

        $this->registerInRegistry();
    }

    /**
     * Register this critic in the CriticRegistry
     */
    private function registerInRegistry(): void
    {
        try {
            if (!Registry::exists('CriticRegistry')) {
                Registry::set('CriticRegistry', new CriticRegistry());
            }
            $registry = Registry::get('CriticRegistry');
            $registry->registerCritic($this);
        } catch (\Exception $e) {
            if ($this->debug) {
                $this->securityLogger->logSecurityEvent(
                    "Failed to register AnalyticsCriticWrapper: " . $e->getMessage(),
                    'warning'
                );
            }
        }
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
     * Get evaluation criteria and capabilities
     * 
     * @return array<string, EvaluationCriteria> Map of output types to criteria
     */
    public function getEvaluationCriteria(): array
    {
        return [
            'sql_query' => new EvaluationCriteria(
                'sql_query',
                0.7,
                'analytics',
                ['accuracy' => 0.35, 'completeness' => 0.25, 'efficiency' => 0.25, 'clarity' => 0.15],
                ['sql_quality' => true, 'sql_security' => true, 'sql_performance' => true],
                ['accuracy' => 0.7, 'completeness' => 0.7, 'efficiency' => 0.6, 'clarity' => 0.6]
            ),
            'query_results' => new EvaluationCriteria(
                'query_results',
                0.65,
                'analytics',
                ['accuracy' => 0.4, 'completeness' => 0.3, 'efficiency' => 0.2, 'clarity' => 0.1],
                ['results_validation' => true],
                ['accuracy' => 0.7, 'completeness' => 0.7, 'efficiency' => 0.6, 'clarity' => 0.6]
            ),
            'schema_info' => new EvaluationCriteria(
                'schema_info',
                0.6,
                'analytics',
                ['accuracy' => 0.5, 'completeness' => 0.3, 'efficiency' => 0.1, 'clarity' => 0.1],
                ['schema_validation' => true],
                ['accuracy' => 0.8, 'completeness' => 0.7, 'efficiency' => 0.5, 'clarity' => 0.5]
            )
        ];
    }

    /**
     * Evaluate an action result
     * 
     * Routes to appropriate evaluator method based on output type and
     * converts result to Evaluation format.
     * 
     * @param ActionResult $result Result to evaluate
     * @return Evaluation Complete evaluation with scores and feedback
     */
    public function evaluateAction(ActionResult $result): Evaluation
    {
        $startTime = microtime(true);
        
        try {
            $outputType = $result->getOutputType();
            $output = $result->getOutput();
            
            if ($this->debug) {
                $this->securityLogger->logSecurityEvent(
                    "AnalyticsCriticWrapper evaluating action result: {$outputType}",
                    'info',
                    ['critic_id' => $this->criticId, 'result_id' => $result->getResultId()]
                );
            }
            
            // Route to appropriate evaluator method based on output type
            $evaluationResult = match($outputType) {
                'sql_query' => $this->evaluateSqlQueryOutput($output, $result),
                'query_results' => $this->evaluateQueryResultsOutput($output, $result),
                'schema_info' => $this->evaluateSchemaInfoOutput($output, $result),
                default => $this->evaluateGenericOutput($output, $result)
            };
            
            // Convert evaluator result to Evaluation
            $evaluation = $this->convertToEvaluation($result, $evaluationResult, $outputType);
            
            // Store evaluation history
            $this->evaluationHistory[] = [
                'evaluation_id' => $evaluation->getEvaluationId(),
                'output_type' => $outputType,
                'scores' => [
                    'accuracy' => $evaluation->getAccuracyScore(),
                    'completeness' => $evaluation->getCompletenessScore(),
                    'efficiency' => $evaluation->getEfficiencyScore(),
                    'clarity' => $evaluation->getClarityScore()
                ],
                'overall_score' => $evaluation->getOverallScore(),
                'evaluated_at' => date('Y-m-d H:i:s')
            ];
            
            $evaluationTime = microtime(true) - $startTime;
            
            if ($this->debug) {
                $this->securityLogger->logSecurityEvent(
                    "AnalyticsCriticWrapper completed evaluation",
                    'info',
                    [
                        'critic_id' => $this->criticId,
                        'overall_score' => $evaluation->getOverallScore(),
                        'evaluation_time' => $evaluationTime
                    ]
                );
            }
            
            return $evaluation;
            
        } catch (\Exception $e) {
            $this->securityLogger->logSecurityEvent(
                "AnalyticsCriticWrapper evaluation failed: " . $e->getMessage(),
                'error',
                ['critic_id' => $this->criticId, 'result_id' => $result->getResultId()]
            );
            
            // Return a default evaluation on error
            return $this->createErrorEvaluation($result, $e->getMessage());
        }
    }

    /**
     * Evaluate SQL query output
     * 
     * @param mixed $output Output data
     * @param ActionResult $result Action result
     * @return array Evaluation result
     */
    private function evaluateSqlQueryOutput($output, ActionResult $result): array
    {
        $sql = $this->extractSql($output);
        
        if (empty($sql)) {
            return [
                'dimension_scores' => [
                    'accuracy' => 0.0,
                    'completeness' => 0.0,
                    'efficiency' => 0.0,
                    'clarity' => 0.0
                ],
                'feedback' => 'No SQL query found in output',
                'strengths' => [],
                'improvements' => ['Provide a valid SQL query']
            ];
        }
        
        // Delegate to evaluator
        $context = $this->extractContext($result);
        return $this->evaluator->evaluateSqlQuery($sql, $context);
    }

    /**
     * Evaluate query results output
     * 
     * @param mixed $output Output data
     * @param ActionResult $result Action result
     * @return array Evaluation result
     */
    private function evaluateQueryResultsOutput($output, ActionResult $result): array
    {
        if (!is_array($output)) {
            return [
                'dimension_scores' => [
                    'accuracy' => 0.0,
                    'completeness' => 0.0,
                    'efficiency' => 0.0,
                    'clarity' => 0.0
                ],
                'feedback' => 'Query results must be an array',
                'strengths' => [],
                'improvements' => ['Provide query results as an array']
            ];
        }
        
        // Delegate to evaluator
        $context = $this->extractContext($result);
        return $this->evaluator->evaluateQueryResults($output, $context);
    }

    /**
     * Evaluate schema info output
     * 
     * @param mixed $output Output data
     * @param ActionResult $result Action result
     * @return array Evaluation result
     */
    private function evaluateSchemaInfoOutput($output, ActionResult $result): array
    {
        if (!is_array($output)) {
            return [
                'dimension_scores' => [
                    'accuracy' => 0.0,
                    'completeness' => 0.0,
                    'efficiency' => 0.0,
                    'clarity' => 0.0
                ],
                'feedback' => 'Schema info must be an array',
                'strengths' => [],
                'improvements' => ['Provide schema info as an array']
            ];
        }
        
        // Delegate to evaluator
        $context = $this->extractContext($result);
        return $this->evaluator->evaluateSchemaInfo($output, $context);
    }

    /**
     * Evaluate generic output
     * 
     * @param mixed $output Output data
     * @param ActionResult $result Action result
     * @return array Evaluation result
     */
    private function evaluateGenericOutput($output, ActionResult $result): array
    {
        $hasOutput = !empty($output);
        $isStructured = is_array($output);

        return [
            'dimension_scores' => [
                'accuracy' => $hasOutput ? 0.5 : 0.3,
                'completeness' => $hasOutput ? 0.5 : 0.3,
                'efficiency' => 0.5,
                'clarity' => $isStructured ? 0.6 : 0.4
            ],
            'feedback' => "Generic evaluation: output type '{$result->getOutputType()}' is not specifically supported",
            'strengths' => ['Output structure is valid'],
            'improvements' => ['Use supported output types: sql_query, query_results, or schema_info']
        ];
    }

    /**
     * Extract SQL string from output
     * 
     * @param mixed $output Output data
     * @return string SQL query string
     */
    private function extractSql($output): string
    {
        if (is_string($output)) {
            return $output;
        }
        
        if (is_array($output) && isset($output['sql'])) {
            return (string)$output['sql'];
        }
        
        return '';
    }

    /**
     * Extract context from ActionResult
     * 
     * @param ActionResult $result Action result
     * @return array Context array
     */
    private function extractContext(ActionResult $result): array
    {
        $executionMetrics = $result->getExecutionMetrics();
        
        return [
            'execution_time' => $executionMetrics['execution_time'] ?? 0.0,
            'result_id' => $result->getResultId(),
            'action_id' => $result->getActionId(),
            'producer_agent_id' => $result->getProducerAgentId()
        ];
    }

    /**
     * Convert evaluator result to Evaluation
     * 
     * @param ActionResult $result Original action result
     * @param array $evaluationResult Evaluator result
     * @param string $outputType Output type
     * @return Evaluation
     */
    private function convertToEvaluation(ActionResult $result, array $evaluationResult, string $outputType): Evaluation
    {
        $scores = $evaluationResult['dimension_scores'];
        $feedback = $evaluationResult['feedback'] ?? $this->evaluator->generateFeedback($scores, $outputType);
        $strengths = $evaluationResult['strengths'] ?? [];
        $improvements = $evaluationResult['improvements'] ?? [];

        return new Evaluation(
            $this->criticId,
            $result->getResultId(),
            $scores,
            $feedback,
            $strengths,
            $improvements
        );
    }

    /**
     * Create error evaluation
     * 
     * @param ActionResult $result Action result
     * @param string $errorMessage Error message
     * @return Evaluation
     */
    private function createErrorEvaluation(ActionResult $result, string $errorMessage): Evaluation
    {
        $scores = [
            'accuracy' => 0.0,
            'completeness' => 0.0,
            'efficiency' => 0.0,
            'clarity' => 0.0
        ];

        $feedback = "Evaluation error: " . $errorMessage;
        $strengths = [];
        $improvements = ['Fix evaluation error'];

        return new Evaluation(
            $this->criticId,
            $result->getResultId(),
            $scores,
            $feedback,
            $strengths,
            $improvements
        );
    }

    /**
     * Provide detailed feedback for an action result
     * 
     * @param ActionResult $result Result to provide feedback on
     * @return Feedback Structured feedback with strengths and improvements
     */
    public function provideFeedback(ActionResult $result): Feedback
    {
        $evaluation = $this->evaluateAction($result);
        
        return new Feedback(
            $result->getProducerAgentId(),
            $result->getResultId(),
            $evaluation->getOverallScore(),
            [
                'correctness' => [$evaluation->getFeedback()],
                'efficiency' => ['Check query performance and indexing'],
                'completeness' => ['Verify all required data is present'],
                'security' => ['Ensure SQL is safe from injection'],
                'best_practice' => ['Follow SQL best practices']
            ],
            $evaluation->getStrengths(),
            $evaluation->getImprovements()
        );
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
        
        $confidence = 0.5;
        $risks = [];
        $mitigations = [];
        
        // Check if SQL is provided in parameters
        if (isset($parameters['sql']) && !empty($parameters['sql'])) {
            $sql = (string)$parameters['sql'];
            
            // Quick pre-validation checks using evaluator's validators
            $qualityValidator = $this->evaluator->getQualityValidator();
            $securityValidator = $this->evaluator->getSecurityValidator();
            $performanceValidator = $this->evaluator->getPerformanceValidator();
            
            // Quality checks
            if ($qualityValidator->checkSelectWildcard(strtoupper($sql))) {
                $risks[] = [
                    'type' => 'quality',
                    'description' => 'Query uses SELECT * which may impact quality',
                    'probability' => 0.6
                ];
                $mitigations['quality'] = ['Use explicit column names instead of SELECT *'];
            }
            
            // Security checks
            $securityResult = $securityValidator->validateSqlSecurity($sql);
            if ($securityResult['security_score'] < 0.7) {
                $risks[] = [
                    'type' => 'security',
                    'description' => 'Query may have security issues',
                    'probability' => 0.7
                ];
                $mitigations['security'] = $securityResult['recommendations'] ?? ['Review SQL for security issues'];
            }
            
            // Performance checks
            if (!$qualityValidator->checkLimitClause(strtoupper($sql))) {
                $risks[] = [
                    'type' => 'performance',
                    'description' => 'Query lacks LIMIT clause which may impact performance',
                    'probability' => 0.5
                ];
                $mitigations['performance'] = ['Add LIMIT clause to restrict result set'];
            }
            
            $confidence = 0.7;
        }
        
        return new Prediction(
            $action->getActionId(),
            $this->criticId,
            ['predicted_quality_score' => $confidence],
            $confidence,
            $risks,
            ['success' => $confidence, 'partial' => 0.2, 'failure' => 1.0 - $confidence - 0.2],
            $mitigations
        );
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
}
