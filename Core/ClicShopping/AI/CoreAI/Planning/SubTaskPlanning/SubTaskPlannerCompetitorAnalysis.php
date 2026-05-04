<?php
/**
 * SubTaskPlannerCompetitorAnalysis - Specialized planner for competitive analysis
 * Creates deterministic plans to compare internal data with competitors
 * 
 * @copyright 2008 - https://www.clicshopping.org
 */

namespace ClicShopping\AI\CoreAI\Planning\SubTaskPlanning;


use ClicShopping\AI\CoreAI\Planning\TaskStep;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\DomainRegistry;


class SubTaskPlannerCompetitorAnalysis
{
    private bool $debug;
    private ?SecurityLogger $securityLogger;

    public function __construct(bool $debug = false, ?SecurityLogger $securityLogger = null)
    {
        $this->debug = $debug;
        $this->securityLogger = $securityLogger;
    }

    /**
     * Check if query is for competitive analysis
     * 
     * NOTE: Feature currently disabled in Pure LLM mode
     * Requires external data sources not yet implemented
     * 
     * @return bool Always returns false (feature disabled)
     */
    public function canHandle(string $query): bool
    {
      if ($this->debug) {
        $this->logDebug("Competitor analysis detection SKIPPED - Feature not supported");
      }
      
      return false;
    }

    /**
     * Create competitive analysis execution plan
     * Generates 3-step plan: collect internal data, collect competitor data, compare and synthesize
     * 
     * @return array List of TaskStep objects
     */
    public function createPlan(array $intent, string $query): array
    {
        if ($this->debug) {
            $this->logDebug("Creating competitor analysis plan");
        }

        $productName = $this->extractProductName($query);
        $steps = [];

        // Step 1: Collect internal data
        $step1 = new TaskStep(
            'step_1',
            'collect_our_product_data',
            'Collect our product data from internal database',
            [
                'intent' => $intent,
                'sub_query' => 'Load our products and pricing from internal database',
                'product_name' => $productName,
                'expected_output' => 'Our product dataset (price, features, availability)',
                'data_source' => 'internal_database',
                'tables' => $this->getTablesFromDomain(),
                'scope' => 'our_products_only',
                'depends_on' => [],
                'can_run_parallel' => false,
                'is_final' => false,
            ]
        );
        $steps[] = $step1;

        // Step 2: Collect competitor data
        $step2 = new TaskStep(
            'step_2',
            'collect_competitor_market_data',
            'Collect competitor data from external sources and cache',
            [
                'intent' => $intent,
                'sub_query' => 'Load competitor pricing and product data',
                'expected_output' => 'Competitor market dataset (external sources)',
                'data_source' => 'external_cache_and_web',
                'sources' => ['rag_web_cache_embedding', 'serpapi_if_needed'],
                'scope' => 'competitor_products_only',
                'depends_on' => ['step_1'],
                'can_run_parallel' => false,
                'is_final' => false,
            ]
        );
        $steps[] = $step2;

        // Step 3: Compare and synthesize
        $step3 = new TaskStep(
            'step_3',
            'competitive_analysis_synthesis',
            'Compare our products vs competitors and generate insights',
            [
                'intent' => $intent,
                'sub_query' => 'Analyze competitive positioning and pricing',
                'expected_output' => 'Competitive analysis with recommendations',
                'analysis_type' => 'our_vs_competitors_comparison',
                'comparison_dimensions' => ['price', 'features', 'positioning', 'value_proposition'],
                'depends_on' => ['step_1', 'step_2'],
                'can_run_parallel' => false,
                'is_final' => true,
            ]
        );
        $steps[] = $step3;

        return $steps;
    }

    /**
     * Get planner metadata
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'Competitor Analysis Planner',
            'description' => 'Specialized planner for competitive analysis and pricing comparison',
            'steps_count' => 3,
            'step_types' => ['collect_our_product_data', 'collect_competitor_market_data', 'competitive_analysis_synthesis'],
            'data_sources' => ['internal_database', 'external_cache_and_web'],
            'analysis_dimensions' => ['price', 'features', 'positioning', 'value_proposition'],
            'supports_fallback' => true,
            'requires_external_data' => true
        ];
    }

    /**
     * Extract product name from query
     * Uses regex patterns to identify product name in English queries
     * 
     * @return string Extracted product name
     */
    private function extractProductName(string $query): string
    {
        $patterns = [
            '/compare\s+the\s+price\s+of\s+(.+?)\s+with\s+(the\s+)?competitors?/iu',
            '/compare\s+(.+?)\s+with\s+(the\s+)?competitors?/iu',
            '/price\s+of\s+(.+?)\s+with\s+(the\s+)?competitors?/iu',
            '/(.+?)\s+vs\s+competitors?/iu',
            '/(.+?)\s+versus\s+competitors?/iu',
            '/(.+?)\s+against\s+competitors?/iu',
            '/(.+?)\s+competitors?/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query, $matches)) {
                $productName = trim($matches[1]);
                $productName = preg_replace('/^(the|a|an)\s+/i', '', $productName);
                $productName = rtrim($productName, '?!.,;:');
                $productName = preg_replace('/\s+/', ' ', $productName);

                if (!empty($productName)) {
                    return $productName;
                }
            }
        }

        $cleaned = preg_replace('/\b(compare|comparison|price|cost|with|against|versus|vs|competitor|competitors|competition|the|a|an)\b/i', '', $query);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        return trim($cleaned);
    }

    /**
     * Get tables from active domain configuration
     * Loads entity config from domain app via DomainRegistry
     * 
     * @return array Array of table names
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

    private function logDebug(string $message): void
    {
        if ($this->securityLogger) {
            $this->securityLogger->logSecurityEvent($message, 'info');
        }

        if ($this->debug) {
            error_log("[SubTaskPlannerCompetitorAnalysis] $message");
        }
    }
}