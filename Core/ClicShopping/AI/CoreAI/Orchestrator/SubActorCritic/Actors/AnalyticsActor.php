<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Actors;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

use ClicShopping\AI\InterfacesAI\ActorAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCapability;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;

use ClicShopping\AI\DomainsAI\Analytics\Agent\AnalyticsAgent;
use ClicShopping\AI\DomainsAI\Analytics\Agent\DatabaseSchemaManager;
use ClicShopping\AI\DomainsAI\Analytics\Executor\SqlQueryProcessor;
use ClicShopping\AI\DomainsAI\Analytics\Executor\QueryExecutor;
use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * AnalyticsActor - Actor agent specialized in SQL generation and execution
 * 
 * This actor handles analytics queries by:
 * - Generating SQL queries from natural language
 * - Executing queries against the database
 * - Validating query safety and correctness
 * - Providing confidence scores for actions
 * 
 * Capabilities:
 * - SQL generation from natural language
 * - Data query execution
 * - Schema-aware query construction
 * - Performance-optimized query generation
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Actors
 * @version 1.0.0
 * @since 2026-01-30
 */
class AnalyticsActor implements ActorAgentInterface
{
    private string $actorId;
    private ?AnalyticsAgent $analyticsAgent = null;
    private DatabaseSchemaManager $schemaManager;
    private SqlQueryProcessor $queryProcessor;
    private QueryExecutor $queryExecutor;
    private SecurityLogger $securityLogger;
    private mixed $db;
    private int $languageId;
    private bool $debug;
    private array $feedbackHistory = [];
    
    /**
     * Constructor
     * 
     * @param int|null $languageId Language ID for filtering results
     * @param bool $debug Enable debug mode
     */
    public function __construct(?int $languageId = null, bool $debug = false)
    {
        $this->actorId = 'analytics_actor_' . uniqid();
        $this->db = Registry::get('Db');
        $this->languageId = $languageId ?? Registry::get('Language')->getId();
        $this->debug = $debug;
        
        // Initialize security logger
        $this->securityLogger = new SecurityLogger();
        
        // Initialize schema manager
        $this->schemaManager = new DatabaseSchemaManager(
            $this->db,
            $this->securityLogger,
            $this->debug
        );
        
        // Initialize query processor
        $this->queryProcessor = new SqlQueryProcessor(
            $this->securityLogger,
            $this->languageId,
            $this->debug
        );
        
        // Initialize query executor
        $this->queryExecutor = new QueryExecutor(
            $this->db,
            $this->securityLogger,
            new \ClicShopping\AI\Security\DbSecurity(),
            $this->debug
        );
        
        // Register this actor in the ActorRegistry
        $this->registerInRegistry();
        
        $this->securityLogger->logSecurityEvent(
            "AnalyticsActor initialized: {$this->actorId}",
            'info'
        );
    }
    
    /**
     * Get or initialize AnalyticsAgent (lazy loading)
     * 
     * @return AnalyticsAgent
     */
    private function getAnalyticsAgent(): AnalyticsAgent
    {
        if ($this->analyticsAgent === null) {
            $this->analyticsAgent = new AnalyticsAgent($this->languageId, true, 'analytics_actor');
        }
        return $this->analyticsAgent;
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
                "AnalyticsActor executing action: {$actionType}",
                'info',
                ['actor_id' => $this->actorId, 'action_id' => $action->getActionId()]
            );
            
            // Route to appropriate handler based on action type
            $output = match($actionType) {
                'sql_generation' => $this->executeSqlGeneration($parameters),
                'data_query' => $this->executeDataQuery($parameters),
                'schema_query' => $this->executeSchemaQuery($parameters),
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
                "AnalyticsActor completed action successfully",
                'info',
                ['actor_id' => $this->actorId, 'execution_time' => $executionTime]
            );
            
            return $result;
            
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            $this->securityLogger->logSecurityEvent(
                "AnalyticsActor action execution failed: " . $e->getMessage(),
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
     * Execute SQL generation action
     * 
     * @param array $parameters Action parameters
     * @return array Generated SQL and metadata
     */
    private function executeSqlGeneration(array $parameters): array
    {
        $query = $parameters['query'] ?? '';
        
        if (empty($query)) {
            throw new \Exception("Query parameter is required for SQL generation");
        }
        
        // Use analytics agent to generate SQL (lazy loaded)
        $result = $this->getAnalyticsAgent()->processQuery($query);
        
        return [
            'sql' => $result['sql'] ?? '',
            'explanation' => $result['explanation'] ?? '',
            'tables_used' => $result['tables_used'] ?? [],
            'query_type' => $result['query_type'] ?? 'unknown',
            'metrics' => [
                'sql_length' => strlen($result['sql'] ?? ''),
                'tables_count' => count($result['tables_used'] ?? [])
            ]
        ];
    }
    
    /**
     * Execute data query action
     * 
     * @param array $parameters Action parameters
     * @return array Query results and metadata
     */
    private function executeDataQuery(array $parameters): array
    {
        $sql = $parameters['sql'] ?? '';
        
        if (empty($sql)) {
            throw new \Exception("SQL parameter is required for data query");
        }
        
        // Execute query
        $results = $this->queryExecutor->executeQuery($sql);
        
        return [
            'results' => $results['data'] ?? [],
            'row_count' => $results['row_count'] ?? 0,
            'execution_time' => $results['execution_time'] ?? 0,
            'metrics' => [
                'rows_returned' => $results['row_count'] ?? 0,
                'query_time' => $results['execution_time'] ?? 0
            ]
        ];
    }
    
    /**
     * Execute schema query action
     * 
     * @param array $parameters Action parameters
     * @return array Schema information
     */
    private function executeSchemaQuery(array $parameters): array
    {
        $tableName = $parameters['table'] ?? '';
        
        if (empty($tableName)) {
            // Return all tables from database schema
            $schema = $this->schemaManager->getDatabaseSchema();
            $tables = array_keys($schema);
            
            return [
                'tables' => $tables,
                'table_count' => count($tables),
                'metrics' => [
                    'tables_count' => count($tables)
                ]
            ];
        }
        
        // Return specific table schema
        $columns = $this->schemaManager->getTableSchema($tableName);
        $relationships = $this->schemaManager->getTableRelationships();
        
        return [
            'table' => $tableName,
            'columns' => $columns,
            'relationships' => $relationships[$tableName] ?? [],
            'metrics' => [
                'columns_count' => count($columns)
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
        // Analyze context to propose appropriate action
        $systemState = $context->getSystemState();
        
        // Default to SQL generation for analytics queries
        $actionType = 'sql_generation';
        $parameters = [
            'query' => $systemState['user_query'] ?? '',
            'context' => $systemState
        ];
        
        return new Action(
            $actionType,
            $parameters,
            $context,
            'medium',
            30 // estimated 30 seconds
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
            'sql_generation' => new ActorCapability(
                'sql_generation',
                0.9, // high confidence
                'analytics',
                'Generate SQL queries from natural language'
            ),
            'data_query' => new ActorCapability(
                'data_query',
                0.85,
                'analytics',
                'Execute SQL queries and return results'
            ),
            'schema_query' => new ActorCapability(
                'schema_query',
                0.95,
                'analytics',
                'Query database schema information'
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
            return 0.0; // Cannot handle this action type
        }
        
        $baseConfidence = $capabilities[$actionType]->getConfidence();
        
        // Adjust confidence based on action parameters
        $parameters = $action->getParameters();
        
        // Reduce confidence if query is very complex
        if ($actionType === 'sql_generation' && isset($parameters['query'])) {
            $queryLength = strlen($parameters['query']);
            if ($queryLength > 500) {
                $baseConfidence *= 0.9; // Reduce by 10% for very long queries
            }
        }
        
        // Reduce confidence if SQL is very complex
        if ($actionType === 'data_query' && isset($parameters['sql'])) {
            $sqlLength = strlen($parameters['sql']);
            if ($sqlLength > 1000) {
                $baseConfidence *= 0.85; // Reduce by 15% for very long SQL
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
            "AnalyticsActor received feedback",
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
            'sql_generation' => 'sql_query',
            'data_query' => 'query_results',
            'schema_query' => 'schema_info',
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
            // Get or create ActorRegistry instance
            if (!Registry::exists('ActorRegistry')) {
                Registry::set('ActorRegistry', new ActorRegistry());
            }
            
            $registry = Registry::get('ActorRegistry');
            $registry->registerActor($this);
            
            $this->securityLogger->logSecurityEvent(
                "AnalyticsActor registered in ActorRegistry",
                'info',
                ['actor_id' => $this->actorId]
            );
        } catch (\Exception $e) {
            $this->securityLogger->logSecurityEvent(
                "Failed to register AnalyticsActor: " . $e->getMessage(),
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
}
