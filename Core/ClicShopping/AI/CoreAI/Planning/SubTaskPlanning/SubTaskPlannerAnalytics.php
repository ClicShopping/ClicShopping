<?php
/**
 * SubTaskPlannerAnalytics - Planner for basic analytics queries
 * Handles COUNT, SUM, AVG, MIN, MAX, ORDER BY, GROUP BY operations
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Planning\SubTaskPlanning;


use ClicShopping\AI\CoreAI\Planning\TaskStep;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\DomainRegistry;


class SubTaskPlannerAnalytics
{
    private bool $debug;
    private ?SecurityLogger $securityLogger;

    public function __construct(bool $debug = false, ?SecurityLogger $securityLogger = null)
    {
        $this->debug = $debug;
        $this->securityLogger = $securityLogger;
    }
    
    /**
     * Check if query can be handled by this planner
     * 
     * This is a catch-all planner for analytics queries.
     * It always returns true as it handles all basic analytics operations.
     * 
     * @param string $query User query to analyze
     * @return bool Always true (catch-all planner)
     */
    public function canHandle(string $query): bool
    {
        if ($this->debug) {
            $this->logDebug("Analytics planner (catch-all) can handle query: " . substr($query, 0, 50));
        }

        return true;
    }
    
    /**
     * Create analytics execution plan
     * 
     * @param array $intent Intent classification data
     * @param string $query User query
     * @return array Array of TaskStep objects
     */
    public function createPlan(array $intent, string $query): array
    {
        if ($this->debug) {
            $this->logDebug("Creating analytics plan");
        }

        $steps = [];
        
        $step1 = new TaskStep(
            'step_1',
            'analytics_query',
            $query,
            [
                'sub_query' => $query,
                'intent' => $intent,
                'data_source' => 'internal_database',
                'tables' => $this->getTablesFromDomain(),
                'processing_mode' => 'sql_generation',
                'depends_on' => [],
                'can_run_parallel' => false,
                'is_final' => true,
                'planner' => 'analytics_basic'
            ]
        );
        $steps[] = $step1;

        return $steps;
    }

    /**
     * Get tables from active domain configuration
     * 
     * @return array Array of table names from domain entity config
     */
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
    
    /**
     * Get planner metadata
     * 
     * @return array Planner configuration and capabilities
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'Basic Analytics Planner',
            'description' => 'Handles all basic analytics queries',
            'steps_count' => 1,
            'step_types' => ['analytics_query'],
            'data_sources' => ['internal_database'],
            'processing_mode' => 'sql_generation',
            'supports_operations' => ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'ORDER_BY', 'GROUP_BY'],
            'requires_external_data' => false,
            'is_catch_all' => true,
            'priority' => 'medium'
        ];
    }
    
    private function logDebug(string $message): void
    {
        if ($this->securityLogger) {
            $this->securityLogger->logSecurityEvent($message, 'info');
        }
        
        if ($this->debug) {
            error_log("[SubTaskPlannerAnalytics] $message");
        }
    }
}
