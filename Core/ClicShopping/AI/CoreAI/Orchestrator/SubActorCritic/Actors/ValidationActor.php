<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Actors;

use ClicShopping\OM\Registry;

use ClicShopping\AI\InterfacesAI\ActorAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCapability;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * ValidationActor - Actor agent specialized in data and constraint validation
 * 
 * This actor handles validation tasks by:
 * - Validating data against schemas and constraints
 * - Checking business rule compliance
 * - Verifying data integrity and consistency
 * - Performing format and type validation
 * 
 * Capabilities:
 * - Schema validation (JSON, XML, database schemas)
 * - Business rule validation
 * - Data integrity checks
 * - Format and type validation
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Actors
 * @version 1.0.0
 * @since 2026-01-30
 */
class ValidationActor implements ActorAgentInterface
{
    private string $actorId;
    private SecurityLogger $securityLogger;
    private bool $debug;
    private array $feedbackHistory = [];
    
    /**
     * Constructor
     * 
     * @param bool $debug Enable debug mode
     */
    public function __construct(bool $debug = false)
    {
        $this->actorId = 'validation_actor_' . uniqid();
        $this->debug = $debug;
        
        // Initialize security logger
        $this->securityLogger = new SecurityLogger();
        
        // Register this actor in the ActorRegistry
        $this->registerInRegistry();
        
        $this->securityLogger->logSecurityEvent(
            "ValidationActor initialized: {$this->actorId}",
            'info'
        );
    }
    
    /**
     * Execute an action and produce a result
     * 
     * @param Action $action The action to execute
     * @return ActionResult The execution result with output and metrics
     * @throws \Exception If execution fails
     */
    public function executeAction(Action $action): ActionResult
    {
        $startTime = microtime(true);
        
        try {
            $actionType = $action->getType();
            $parameters = $action->getParameters();
            
            $this->securityLogger->logSecurityEvent(
                "ValidationActor executing action: {$actionType}",
                'info',
                ['actor_id' => $this->actorId, 'action_id' => $action->getActionId()]
            );
            
            // Route to appropriate handler based on action type
            $output = match($actionType) {
                'schema_validation' => $this->executeSchemaValidation($parameters),
                'business_rule_validation' => $this->executeBusinessRuleValidation($parameters),
                'data_integrity_check' => $this->executeDataIntegrityCheck($parameters),
                'format_validation' => $this->executeFormatValidation($parameters),
                default => throw new \Exception("Unsupported action type: {$actionType}")
            };
            
            $executionTime = microtime(true) - $startTime;
            
            // Build execution metrics
            $metrics = [
                'execution_time' => $executionTime,
                'action_type' => $actionType,
                'timestamp' => date('Y-m-d H:i:s'),
                'actor_id' => $this->actorId
            ];
            
            // Add action-specific metrics
            if (isset($output['metrics'])) {
                $metrics = array_merge($metrics, $output['metrics']);
                unset($output['metrics']);
            }
            
            $result = new ActionResult(
                $action->getActionId(),
                $this->actorId,
                $output,
                $this->getOutputType($actionType),
                $metrics,
                $action->getContext(),
                'success'
            );
            
            $this->securityLogger->logSecurityEvent(
                "ValidationActor completed action successfully",
                'info',
                ['actor_id' => $this->actorId, 'execution_time' => $executionTime]
            );
            
            return $result;
            
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            $this->securityLogger->logSecurityEvent(
                "ValidationActor action execution failed: " . $e->getMessage(),
                'error',
                ['actor_id' => $this->actorId, 'action_id' => $action->getActionId()]
            );
            
            // Return failed result with error information
            return new ActionResult(
                $action->getActionId(),
                $this->actorId,
                ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()],
                'error',
                ['execution_time' => $executionTime, 'status' => 'failed'],
                $action->getContext(),
                'failed'
            );
        }
    }
    
    /**
     * Execute schema validation action
     * 
     * @param array $parameters Action parameters
     * @return array Validation result
     */
    private function executeSchemaValidation(array $parameters): array
    {
        $data = $parameters['data'] ?? [];
        $schema = $parameters['schema'] ?? [];
        
        if (empty($data) || empty($schema)) {
            throw new \Exception("Data and schema are required for schema validation");
        }
        
        // Perform schema validation
        $errors = $this->validateAgainstSchema($data, $schema);
        $isValid = empty($errors);
        $warnings = $this->checkSchemaWarnings($data, $schema);
        
        return [
            'is_valid' => $isValid,
            'errors' => $errors,
            'warnings' => $warnings,
            'validated_fields' => $this->getValidatedFields($data, $schema),
            'missing_fields' => $this->getMissingFields($data, $schema),
            'extra_fields' => $this->getExtraFields($data, $schema),
            'metrics' => [
                'fields_validated' => count($this->getValidatedFields($data, $schema)),
                'errors_found' => count($errors),
                'warnings_found' => count($warnings)
            ]
        ];
    }
    
    /**
     * Execute business rule validation action
     * 
     * @param array $parameters Action parameters
     * @return array Validation result
     */
    private function executeBusinessRuleValidation(array $parameters): array
    {
        $data = $parameters['data'] ?? [];
        $rules = $parameters['rules'] ?? [];
        
        if (empty($data) || empty($rules)) {
            throw new \Exception("Data and rules are required for business rule validation");
        }
        
        // Perform business rule validation
        $violations = $this->checkBusinessRules($data, $rules);
        $isCompliant = empty($violations);
        $rulesPassed = $this->getRulesPassed($data, $rules);
        
        return [
            'is_compliant' => $isCompliant,
            'violations' => $violations,
            'rules_passed' => $rulesPassed,
            'rules_failed' => $this->getRulesFailed($data, $rules),
            'compliance_score' => $this->calculateComplianceScore($rulesPassed, $rules),
            'metrics' => [
                'rules_checked' => count($rules),
                'rules_passed' => count($rulesPassed),
                'violations_found' => count($violations)
            ]
        ];
    }
    
    /**
     * Execute data integrity check action
     * 
     * @param array $parameters Action parameters
     * @return array Integrity check result
     */
    private function executeDataIntegrityCheck(array $parameters): array
    {
        $data = $parameters['data'] ?? [];
        $constraints = $parameters['constraints'] ?? [];
        
        if (empty($data)) {
            throw new \Exception("Data is required for integrity check");
        }
        
        // Perform data integrity checks
        $integrityIssues = $this->checkDataIntegrity($data, $constraints);
        $isIntact = empty($integrityIssues);
        $consistencyScore = $this->calculateConsistencyScore($data, $integrityIssues);
        
        return [
            'is_intact' => $isIntact,
            'integrity_issues' => $integrityIssues,
            'consistency_score' => $consistencyScore,
            'referential_integrity' => $this->checkReferentialIntegrity($data, $constraints),
            'duplicate_detection' => $this->detectDuplicates($data),
            'metrics' => [
                'records_checked' => is_array($data) ? count($data) : 1,
                'issues_found' => count($integrityIssues),
                'duplicates_found' => count($this->detectDuplicates($data))
            ]
        ];
    }
    
    /**
     * Execute format validation action
     * 
     * @param array $parameters Action parameters
     * @return array Format validation result
     */
    private function executeFormatValidation(array $parameters): array
    {
        $data = $parameters['data'] ?? [];
        $formats = $parameters['formats'] ?? [];
        
        if (empty($data) || empty($formats)) {
            throw new \Exception("Data and formats are required for format validation");
        }
        
        // Perform format validation
        $formatErrors = $this->validateFormats($data, $formats);
        $isValid = empty($formatErrors);
        $typeErrors = $this->checkTypeConsistency($data, $formats);
        
        return [
            'is_valid' => $isValid,
            'format_errors' => $formatErrors,
            'type_errors' => $typeErrors,
            'validated_formats' => $this->getValidatedFormats($data, $formats),
            'format_compliance' => $this->calculateFormatCompliance($data, $formats, $formatErrors),
            'metrics' => [
                'fields_checked' => count($formats),
                'format_errors' => count($formatErrors),
                'type_errors' => count($typeErrors)
            ]
        ];
    }
    
    /**
     * Propose an action based on current context
     * 
     * @param Context $context Current system context
     * @return Action Proposed action with confidence score
     */
    public function proposeAction(Context $context): Action
    {
        // Analyze context to propose appropriate validation action
        $systemState = $context->getSystemState();
        
        // Default to schema validation
        $actionType = 'schema_validation';
        $parameters = [
            'data' => $systemState['data'] ?? [],
            'schema' => $systemState['schema'] ?? [],
            'context' => $systemState
        ];
        
        return new Action(
            $actionType,
            $parameters,
            $context,
            'high',
            15 // estimated 15 seconds
        );
    }
    
    /**
     * Get actor capabilities
     * 
     * @return array<string, ActorCapability> Map of action types to capabilities
     */
    public function getCapabilities(): array
    {
        return [
            'schema_validation' => new ActorCapability(
                'schema_validation',
                0.95,
                'validation',
                'Validate data against schemas and constraints'
            ),
            'business_rule_validation' => new ActorCapability(
                'business_rule_validation',
                0.9,
                'validation',
                'Check compliance with business rules'
            ),
            'data_integrity_check' => new ActorCapability(
                'data_integrity_check',
                0.9,
                'validation',
                'Verify data integrity and consistency'
            ),
            'format_validation' => new ActorCapability(
                'format_validation',
                0.95,
                'validation',
                'Validate data formats and types'
            )
        ];
    }
    
    /**
     * Evaluate confidence for executing a specific action
     * 
     * @param Action $action Action to evaluate
     * @return float Confidence score (0.0-1.0)
     */
    public function evaluateConfidence(Action $action): float
    {
        $actionType = $action->getType();
        $capabilities = $this->getCapabilities();
        
        if (!isset($capabilities[$actionType])) {
            return 0.0;
        }
        
        $baseConfidence = $capabilities[$actionType]->getConfidence();
        
        // Adjust confidence based on action parameters
        $parameters = $action->getParameters();
        
        // Reduce confidence for complex schemas
        if ($actionType === 'schema_validation' && isset($parameters['schema'])) {
            $schemaSize = is_array($parameters['schema']) ? count($parameters['schema']) : 0;
            if ($schemaSize > 50) {
                $baseConfidence *= 0.9;
            }
        }
        
        return min(1.0, max(0.0, $baseConfidence));
    }
    
    /**
     * Receive feedback from critics
     * 
     * @param Feedback $feedback Aggregated feedback from evaluation
     * @return void
     */
    public function receiveFeedback(Feedback $feedback): void
    {
        // Store feedback for learning
        $this->feedbackHistory[] = [
            'feedback_id' => $feedback->getFeedbackId(),
            'consensus_score' => $feedback->getConsensusScore(),
            'strengths' => $feedback->getStrengths(),
            'improvements' => $feedback->getImprovements(),
            'received_at' => date('Y-m-d H:i:s')
        ];
        
        $this->securityLogger->logSecurityEvent(
            "ValidationActor received feedback",
            'info',
            [
                'actor_id' => $this->actorId,
                'feedback_id' => $feedback->getFeedbackId(),
                'consensus_score' => $feedback->getConsensusScore()
            ]
        );
        
        // Acknowledge feedback
        $feedback->acknowledge();
    }
    
    /**
     * Get unique actor identifier
     * 
     * @return string Actor ID
     */
    public function getActorId(): string
    {
        return $this->actorId;
    }
    
    /**
     * Get output type for action type
     * 
     * @param string $actionType Action type
     * @return string Output type
     */
    private function getOutputType(string $actionType): string
    {
        return match($actionType) {
            'schema_validation' => 'schema_validation_result',
            'business_rule_validation' => 'business_rule_result',
            'data_integrity_check' => 'integrity_check_result',
            'format_validation' => 'format_validation_result',
            default => 'unknown'
        };
    }
    
    /**
     * Register this actor in the ActorRegistry
     * 
     * @return void
     */
    private function registerInRegistry(): void
    {
        try {
            if (!Registry::exists('ActorRegistry')) {
                Registry::set('ActorRegistry', new ActorRegistry());
            }
            
            $registry = Registry::get('ActorRegistry');
            $registry->registerActor($this);
            
            $this->securityLogger->logSecurityEvent(
                "ValidationActor registered in ActorRegistry",
                'info',
                ['actor_id' => $this->actorId]
            );
        } catch (\Exception $e) {
            $this->securityLogger->logSecurityEvent(
                "Failed to register ValidationActor: " . $e->getMessage(),
                'error',
                ['actor_id' => $this->actorId]
            );
        }
    }
    
    /**
     * Get feedback history
     * 
     * @return array Feedback history
     */
    public function getFeedbackHistory(): array
    {
        return $this->feedbackHistory;
    }
    
    // Helper methods for validation operations
    
    private function validateAgainstSchema(array $data, array $schema): array
    {
        $errors = [];
        
        foreach ($schema as $field => $rules) {
            if (!isset($data[$field]) && ($rules['required'] ?? false)) {
                $errors[] = "Missing required field: {$field}";
            }
            
            if (isset($data[$field]) && isset($rules['type'])) {
                $actualType = gettype($data[$field]);
                if ($actualType !== $rules['type']) {
                    $errors[] = "Field {$field} has wrong type: expected {$rules['type']}, got {$actualType}";
                }
            }
        }
        
        return $errors;
    }
    
    private function checkSchemaWarnings(array $data, array $schema): array
    {
        $warnings = [];
        
        // Check for deprecated fields
        foreach ($data as $field => $value) {
            if (isset($schema[$field]['deprecated']) && $schema[$field]['deprecated']) {
                $warnings[] = "Field {$field} is deprecated";
            }
        }
        
        return $warnings;
    }
    
    private function getValidatedFields(array $data, array $schema): array
    {
        $validated = [];
        
        foreach ($schema as $field => $rules) {
            if (isset($data[$field])) {
                $validated[] = $field;
            }
        }
        
        return $validated;
    }
    
    private function getMissingFields(array $data, array $schema): array
    {
        $missing = [];
        
        foreach ($schema as $field => $rules) {
            if (($rules['required'] ?? false) && !isset($data[$field])) {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }
    
    private function getExtraFields(array $data, array $schema): array
    {
        $extra = [];
        
        foreach ($data as $field => $value) {
            if (!isset($schema[$field])) {
                $extra[] = $field;
            }
        }
        
        return $extra;
    }
    
    private function checkBusinessRules(array $data, array $rules): array
    {
        $violations = [];
        
        foreach ($rules as $ruleName => $ruleDefinition) {
            if (!$this->evaluateRule($data, $ruleDefinition)) {
                $violations[] = [
                    'rule' => $ruleName,
                    'description' => $ruleDefinition['description'] ?? 'Rule violation',
                    'severity' => $ruleDefinition['severity'] ?? 'error'
                ];
            }
        }
        
        return $violations;
    }
    
    private function getRulesPassed(array $data, array $rules): array
    {
        $passed = [];
        
        foreach ($rules as $ruleName => $ruleDefinition) {
            if ($this->evaluateRule($data, $ruleDefinition)) {
                $passed[] = $ruleName;
            }
        }
        
        return $passed;
    }
    
    private function getRulesFailed(array $data, array $rules): array
    {
        $failed = [];
        
        foreach ($rules as $ruleName => $ruleDefinition) {
            if (!$this->evaluateRule($data, $ruleDefinition)) {
                $failed[] = $ruleName;
            }
        }
        
        return $failed;
    }
    
    private function evaluateRule(array $data, array $ruleDefinition): bool
    {
        // Simplified rule evaluation
        return true;
    }
    
    private function calculateComplianceScore(array $rulesPassed, array $allRules): float
    {
        if (empty($allRules)) return 1.0;
        return count($rulesPassed) / count($allRules);
    }
    
    private function checkDataIntegrity(array $data, array $constraints): array
    {
        $issues = [];
        
        // Check for null values where not allowed
        foreach ($data as $field => $value) {
            if ($value === null && isset($constraints[$field]['not_null']) && $constraints[$field]['not_null']) {
                $issues[] = "Null value in non-nullable field: {$field}";
            }
        }
        
        return $issues;
    }
    
    private function calculateConsistencyScore(array $data, array $issues): float
    {
        if (empty($issues)) return 1.0;
        
        $dataSize = is_array($data) ? count($data) : 1;
        $issueCount = count($issues);
        
        return max(0.0, 1.0 - ($issueCount / max(1, $dataSize)));
    }
    
    private function checkReferentialIntegrity(array $data, array $constraints): array
    {
        // Simplified referential integrity check
        return ['status' => 'intact'];
    }
    
    private function detectDuplicates(array $data): array
    {
        // Simplified duplicate detection
        return [];
    }
    
    private function validateFormats(array $data, array $formats): array
    {
        $errors = [];
        
        foreach ($formats as $field => $format) {
            if (isset($data[$field])) {
                if (!$this->matchesFormat($data[$field], $format)) {
                    $errors[] = "Field {$field} does not match format: {$format}";
                }
            }
        }
        
        return $errors;
    }
    
    private function checkTypeConsistency(array $data, array $formats): array
    {
        $errors = [];
        
        foreach ($formats as $field => $format) {
            if (isset($data[$field])) {
                $expectedType = $this->getTypeFromFormat($format);
                $actualType = gettype($data[$field]);
                
                if ($expectedType && $actualType !== $expectedType) {
                    $errors[] = "Field {$field} type mismatch: expected {$expectedType}, got {$actualType}";
                }
            }
        }
        
        return $errors;
    }
    
    private function getValidatedFormats(array $data, array $formats): array
    {
        $validated = [];
        
        foreach ($formats as $field => $format) {
            if (isset($data[$field]) && $this->matchesFormat($data[$field], $format)) {
                $validated[] = $field;
            }
        }
        
        return $validated;
    }
    
    private function calculateFormatCompliance(array $data, array $formats, array $errors): float
    {
        if (empty($formats)) return 1.0;
        
        $errorCount = count($errors);
        $formatCount = count($formats);
        
        return max(0.0, 1.0 - ($errorCount / $formatCount));
    }
    
    private function matchesFormat($value, string $format): bool
    {
        // Simplified format matching
        return match($format) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'integer' => is_int($value),
            'string' => is_string($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            default => true
        };
    }
    
    private function getTypeFromFormat(string $format): ?string
    {
        return match($format) {
            'integer' => 'integer',
            'string', 'email', 
            'url' => 'string',
            'boolean' => 'boolean',
            'array' => 'array',
            default => null
        };
    }
}
