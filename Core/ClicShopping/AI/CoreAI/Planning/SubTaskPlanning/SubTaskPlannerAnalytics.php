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


use ClicShopping\AI\Config\TechnicalDefaults;
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

        $subQueries = $this->readableSubQueries($intent);

        if ($subQueries === []) {
            return [$this->buildStep('step_1', $query, $intent, true)];
        }

        $steps = [];
        $total = count($subQueries);

        foreach ($subQueries as $index => $subQuery) {
            $steps[] = $this->buildStep('step_' . ($index + 1), $subQuery, $intent, $index === $total - 1);
        }

        if ($this->debug) {
            $this->logDebug("Split into {$total} analytics step(s) — one schema window each");
        }

        return $steps;
    }

    /**
     * Read the sub-queries the metadata analysis produced, if they are usable.
     *
     * The analysis emits `{query, intent_type}` — NOT the `{text, type}` shape SubTaskPlannerStandard
     * consumes; reading the wrong key yields empty steps that all fall back to the whole question.
     * Only analytics leaves are kept: a web_search leaf here would send a merchant's internal
     * question to an external engine without the hybrid route ever being chosen.
     *
     * @param array $intent Intent carrying the metadata analysis
     * @return array<int, string> Sub-query texts, capped, or [] to keep the single-step plan
     */
    private function readableSubQueries(array $intent): array
    {
        $raw = $intent['sub_queries'] ?? null;

        if (!is_array($raw) || count($raw) < 2) {
            return [];
        }

        $texts = [];

        foreach ($raw as $subQuery) {
            $text = is_array($subQuery) ? ($subQuery['query'] ?? $subQuery['text'] ?? '') : $subQuery;
            $type = is_array($subQuery) ? ($subQuery['intent_type'] ?? $subQuery['type'] ?? 'analytics') : 'analytics';

            if (!is_string($text) || trim($text) === '' || $type !== 'analytics') {
                return [];
            }

            $texts[] = trim($text);
        }

        $cap = TechnicalDefaults::int('CLICSHOPPING_APP_CHATGPT_RA_MAX_ANALYTICS_STEPS');

        if (count($texts) > $cap) {
            // Silently keeping the first N would answer a different question than the one asked.
            if ($this->securityLogger !== null) {
                $this->securityLogger->logSecurityEvent(
                    'Analytics plan: ' . count($texts) . " sub-queries exceed the ceiling of {$cap} — "
                    . 'keeping the single-step plan rather than answering part of the question',
                    'warning'
                );
            }

            return [];
        }

        return $texts;
    }

    /**
     * Build one analytics step. Steps are independent: each carries a self-contained sub-query and
     * gets its own schema window, so none waits on another.
     *
     * @param string $stepId Step identifier
     * @param string $stepQuery Question this step answers
     * @param array $intent Intent classification data
     * @param bool $isFinal Whether this is the last step of the plan
     * @return TaskStep
     */
    private function buildStep(string $stepId, string $stepQuery, array $intent, bool $isFinal): TaskStep
    {
        return new TaskStep(
            $stepId,
            'analytics_query',
            $stepQuery,
            [
                'sub_query' => $stepQuery,
                'intent' => $intent,
                'data_source' => 'internal_database',
                'tables' => $this->getTablesFromDomain(),
                'processing_mode' => 'sql_generation',
                'depends_on' => [],
                'can_run_parallel' => true,
                'is_final' => $isFinal,
                'planner' => 'analytics_basic'
            ]
        );
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
