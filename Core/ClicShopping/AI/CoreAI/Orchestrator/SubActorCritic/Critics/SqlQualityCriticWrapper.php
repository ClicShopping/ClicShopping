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
use ClicShopping\AI\DomainsAI\Analytics\Validator\SqlQualityValidator;
use ClicShopping\AI\InterfacesAI\CriticAgentInterface;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\Registry;

/**
 * SqlQualityCriticWrapper - Minimal wrapper delegating to SqlQualityValidator
 * 
 * This wrapper maintains CriticAgentInterface compatibility while delegating
 * all business logic to SqlQualityValidator in DomainsAI/Analytics/Validator/.
 * 
 * Responsibilities:
 * - Implement CriticAgentInterface
 * - Convert ActionResult to validator input format
 * - Convert validator output to Evaluation/Feedback format
 * - Register in CriticRegistry
 * - NO business logic (pure delegation)
 */
class SqlQualityCriticWrapper implements CriticAgentInterface
{
    private string $criticId;
    private SqlQualityValidator $validator;
    private SecurityLogger $securityLogger;
    private bool $debug;

    /**
     * Constructor
     * 
     * @param SqlQualityValidator|null $validator Optional validator instance (for testing)
     * @param bool $debug Enable debug logging
     */
    public function __construct(?SqlQualityValidator $validator = null, bool $debug = false)
    {
        $this->criticId = 'sql_quality_critic_wrapper_' . uniqid();
        $this->validator = $validator ?? new SqlQualityValidator();
        $this->debug = $debug;
        $this->securityLogger = new SecurityLogger();

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
                    "Failed to register SqlQualityCriticWrapper: " . $e->getMessage(),
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
                0.6,
                'analytics',
                ['accuracy' => 0.4, 'completeness' => 0.3, 'efficiency' => 0.2, 'clarity' => 0.1],
                ['basic_sql_checks' => true],
                ['accuracy' => 0.6, 'completeness' => 0.6, 'efficiency' => 0.5, 'clarity' => 0.5]
            )
        ];
    }

    /**
     * Evaluate an action result
     * 
     * Delegates to SqlQualityValidator and converts result to Evaluation format.
     * 
     * @param ActionResult $result Result to evaluate
     * @return Evaluation Complete evaluation with scores and feedback
     */
    public function evaluateAction(ActionResult $result): Evaluation
    {
        $outputType = $result->getOutputType();
        $output = $result->getOutput();

        // Only evaluate sql_query output type
        if ($outputType !== 'sql_query') {
            return $this->createGenericEvaluation($result);
        }

        // Extract SQL from output
        $sql = $this->extractSql($output);
        
        if (empty($sql)) {
            return $this->createEmptyEvaluation($result);
        }

        // Delegate to validator
        $validationResult = $this->validator->evaluateSqlQuality($sql);

        // Convert validator result to Evaluation format
        return $this->convertToEvaluation($result, $validationResult);
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
     * Convert validator result to Evaluation
     * 
     * @param ActionResult $result Original action result
     * @param array $validationResult Validator result
     * @return Evaluation
     */
    private function convertToEvaluation(ActionResult $result, array $validationResult): Evaluation
    {
        $scores = $validationResult['scores'];
        $feedback = $this->validator->generateFeedback($validationResult);
        
        // Extract strengths and improvements from validator result
        $strengths = [];
        $improvements = [];
        
        if (isset($validationResult['issues'])) {
            foreach ($validationResult['issues'] as $issue) {
                $improvements[] = $issue;
            }
        }
        
        if (isset($validationResult['recommendations'])) {
            foreach ($validationResult['recommendations'] as $recommendation) {
                $improvements[] = $recommendation;
            }
        }
        
        // Identify strengths based on scores
        foreach ($scores as $dimension => $score) {
            if ($score >= 0.7) {
                $strengths[] = ucfirst($dimension) . ' is strong';
            }
        }
        
        if (empty($strengths)) {
            $strengths[] = 'Overall structure is correct';
        }

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
     * Create generic evaluation for non-SQL output types
     * 
     * @param ActionResult $result Action result
     * @return Evaluation
     */
    private function createGenericEvaluation(ActionResult $result): Evaluation
    {
        $output = $result->getOutput();
        $hasOutput = !empty($output);
        $isStructured = is_array($output);

        $scores = [
            'accuracy' => $hasOutput ? 0.55 : 0.3,
            'completeness' => $hasOutput ? 0.55 : 0.3,
            'efficiency' => 0.5,
            'clarity' => $isStructured ? 0.6 : 0.4
        ];

        $feedback = "Generic evaluation: output type '{$result->getOutputType()}' is not SQL query";
        $strengths = ['Output structure is valid'];
        $improvements = ['Consider using sql_query output type for SQL quality evaluation'];

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
     * Create evaluation for empty SQL
     * 
     * @param ActionResult $result Action result
     * @return Evaluation
     */
    private function createEmptyEvaluation(ActionResult $result): Evaluation
    {
        $scores = [
            'accuracy' => 0.0,
            'completeness' => 0.0,
            'efficiency' => 0.0,
            'clarity' => 0.0
        ];

        $feedback = "No SQL query found in output";
        $strengths = [];
        $improvements = ['Provide a valid SQL query for evaluation'];

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
                'efficiency' => ['Check indexes and limits'],
                'completeness' => ['Check filters'],
                'best_practice' => ['Avoid SELECT * if possible']
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
        // Basic prediction based on action type
        $actionType = $action->getType();
        $parameters = $action->getParameters();
        
        $confidence = 0.5;
        $risks = [];
        $mitigations = [];
        
        // Check if SQL is provided in parameters
        if (isset($parameters['sql']) && !empty($parameters['sql'])) {
            $sql = (string)$parameters['sql'];
            
            // Quick pre-validation checks
            if (stripos($sql, 'SELECT *') !== false) {
                $risks[] = [
                    'type' => 'efficiency',
                    'description' => 'Query uses SELECT * which may impact performance',
                    'probability' => 0.6
                ];
                $mitigations['efficiency'] = ['Use explicit column names instead of SELECT *'];
            }
            
            if (stripos($sql, 'LIMIT') === false && stripos($sql, 'SELECT') !== false) {
                $risks[] = [
                    'type' => 'performance',
                    'description' => 'Query lacks LIMIT clause which may return too many rows',
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
}
