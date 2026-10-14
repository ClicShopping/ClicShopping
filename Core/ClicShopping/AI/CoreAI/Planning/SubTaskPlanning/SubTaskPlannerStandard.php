<?php
/**
 * SubTaskPlannerStandard - Default fallback planner for generic analytics queries
 * 
 * @copyright 2008 - https://www.clicshopping.org
 */

namespace ClicShopping\AI\CoreAI\Planning\SubTaskPlanning;


use ClicShopping\AI\CoreAI\Planning\TaskStep;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\DomainRegistry;


class SubTaskPlannerStandard
{
    private bool $debug;
    private ?SecurityLogger $securityLogger;
    
    public function __construct(bool $debug = false, ?SecurityLogger $securityLogger = null)
    {
        $this->debug = $debug;
        $this->securityLogger = $securityLogger;
    }
    
    public function canHandle(string $query): bool
    {
        return true; // Fallback planner, accepts all
    }
    
    public function createPlan(array $intent, string $query): array
    {
        $steps = [];

        // Check if this is a decomposed hybrid query with sub_queries
        if (isset($intent['sub_queries']) && is_array($intent['sub_queries'])) {
            
            $stepTypes = array_map(function($sq) { return $sq['type'] ?? 'unknown'; }, $intent['sub_queries']);
            
            $this->logDebug(
                "PLAN CREATION START - Creating " . count($intent['sub_queries']) . 
                " steps for hybrid query | Step types: " . json_encode($stepTypes)
            );
            
            if ($this->securityLogger) {
                $this->securityLogger->logSecurityEvent(
                    "Creating execution plan for hybrid query",
                    'info',
                    [
                        'total_steps' => count($intent['sub_queries']),
                        'step_types' => $stepTypes,
                        'query' => $query,
                        'plan_type' => 'hybrid_decomposed',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]
                );
            }
            
            // Create one ExecutionStep per sub-query
            foreach ($intent['sub_queries'] as $index => $subQuery) {
                $stepId = 'step_' . ($index + 1);
                
                // Set step type based on sub-query type
                // Map sub-query type to step type that PlanExecutor understands
                $subQueryType = $subQuery['type'] ?? 'analytics';
                $stepType = $this->mapSubQueryTypeToStepType($subQueryType);
                
                // Get sub-query text
                $subQueryText = $subQuery['text'] ?? $query;
                
                // Determine dependencies for correct execution order
                // For hybrid queries: analytics MUST run before web_search
                $dependsOn = [];
                $canRunParallel = true;
                
                // If this is a web_search step and there's an analytics step before it,
                // make it depend on the analytics step
                if ($stepType === 'web_search') {
                    // Check if there's an analytics step before this one
                    foreach ($intent['sub_queries'] as $prevIndex => $prevSubQuery) {
                        if ($prevIndex < $index) {
                            $prevType = $prevSubQuery['type'] ?? 'analytics';
                            if ($prevType === 'analytics') {
                                $dependsOn[] = 'step_' . ($prevIndex + 1);
                                $canRunParallel = false; // Cannot run in parallel if it depends on another step
                                
                                $this->logDebug(
                                    "Step $stepId (web_search) depends on step_" . ($prevIndex + 1) . " (analytics)"
                                );
                            }
                        }
                    }
                }
                
                // Create step with sub-query metadata
                $step = new TaskStep(
                    $stepId,
                    $stepType,
                    $subQueryText,
                    [
                        'sub_query' => $subQueryText,
                        'original_query' => $query,
                        'sub_query_type' => $subQueryType,
                        'sub_query_index' => $index,
                        'sub_query_confidence' => $subQuery['confidence'] ?? null,
                        'intent' => $intent,
                        'query_type' => 'hybrid_decomposed',
                        'data_source' => 'internal_database',
                        'tables' => $this->getTablesFromDomain(),
                        'processing_mode' => 'direct_sql',
                        'depends_on' => $dependsOn,
                        'can_run_parallel' => $canRunParallel,
                        'is_final' => ($index === count($intent['sub_queries']) - 1),
                    ]
                );
                
                $steps[] = $step;
                
                $this->logDebug(
                    "Created step $stepId | Type: $stepType | Sub-query: " . 
                    substr($subQueryText, 0, 100) . 
                    " | Depends on: " . json_encode($dependsOn) . 
                    " | Can run parallel: " . ($canRunParallel ? 'YES' : 'NO')
                );
                
                if ($this->securityLogger) {
                    $this->securityLogger->logSecurityEvent(
                        "Execution step created: $stepId",
                        'info',
                        [
                            'step_id' => $stepId,
                            'step_type' => $stepType,
                            'sub_query_type' => $subQueryType,
                            'sub_query_text' => $subQueryText,
                            'step_index' => $index,
                            'depends_on' => $dependsOn,
                            'can_run_parallel' => $canRunParallel,
                            'is_final' => ($index === count($intent['sub_queries']) - 1)
                        ]
                    );
                }
            }
            
            $this->logDebug(
                "PLAN CREATION COMPLETE - Created " . count($steps) . 
                " steps from hybrid query decomposition"
            );
            
            if ($this->securityLogger) {
                $this->securityLogger->logSecurityEvent(
                    "Execution plan created successfully",
                    'info',
                    [
                        'total_steps_created' => count($steps),
                        'step_ids' => array_map(function($s) { return $s->getId(); }, $steps),
                        'step_types' => array_map(function($s) { return $s->getType(); }, $steps),
                        'plan_type' => 'hybrid_decomposed'
                    ]
                );
            }
            
            return $steps;
        }
        
        // Existing single-step logic for non-hybrid queries
        $step1 = new TaskStep(
            'step_1',
            'analytics_query',
            $query,
            [
                'sub_query' => $query,
                'intent' => $intent,
                'query_type' => 'standard_analytics',
                'data_source' => 'internal_database',
                'tables' => $this->getTablesFromDomain(),
                'processing_mode' => 'direct_sql',
                'depends_on' => [],
                'can_run_parallel' => false,
                'is_final' => true,
            ]
        );
        $steps[] = $step1;

        return $steps;
    }
    
    public function getMetadata(): array
    {
        return [
            'name' => 'Standard Analytics Planner',
            'description' => 'Default planner for generic analytics queries',
            'steps_count' => 1,
            'step_types' => ['analytics_query'],
            'data_sources' => ['internal_database'],
            'processing_mode' => 'direct_sql',
            'supports_fallback' => true,
            'requires_external_data' => false,
            'is_fallback_planner' => true
        ];
    }
    
    /**
     * Map sub-query type to step type that PlanExecutor understands
     * 
     * @param string $subQueryType Sub-query type from HybridQueryDecomposer
     * @return string Step type for PlanExecutor
     */
    private function mapSubQueryTypeToStepType(string $subQueryType): string
    {
        $mapping = [
            'analytics' => 'analytics_query',
            'semantic' => 'semantic_search',
            'hybrid' => 'semantic_search',
            'web_search' => 'web_search',
            'web' => 'web_search',
            'calculator' => 'calculator',
        ];

        return $mapping[$subQueryType] ?? 'semantic_search';
    }
    
    private function getTablesFromDomain(): array
    {
        $domainApp = DomainRegistry::getInstance()->getActiveApp();
        if ($domainApp && method_exists($domainApp, 'getEntityConfig')) {
            $entityConfig = $domainApp->getEntityConfig();
            $tables = [];
            foreach ($entityConfig as $entity) {
                if (isset($entity['table'])) {
                    $tables[] = $entity['table'];
                }
            }
            return array_unique($tables);
        }
        
        return [];
    }
    
    private function logDebug(string $message): void
    {
        if ($this->securityLogger) {
            $this->securityLogger->logSecurityEvent($message, 'info');
        }
        
        if ($this->debug) {
            error_log("[SubTaskPlannerStandard] $message");
        }
    }
}