<?php
/**
 * SubTaskPlannerPatternAnalysis
 * 
 * Planner spécialisé pour l'analyse de patterns et tendances
 * Responsibility : Createsr des plans pour identifier motifs dominants, tendances, styles
 */

namespace ClicShopping\AI\CoreAI\Planning\SubTaskPlanning;


use ClicShopping\AI\CoreAI\Planning\TaskStep;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\DomainsAI\DomainRegistry;


class SubTaskPlannerPatternAnalysis
{
    private bool $debug;
    private ?SecurityLogger $securityLogger;
    
    public function __construct(bool $debug = false, ?SecurityLogger $securityLogger = null)
    {
        $this->debug = $debug;
        $this->securityLogger = $securityLogger;
    }
    
    /**
     * Detects si une requête concerne l'analyse de patterns
     * 
     * NOTE: Pure LLM Mode - This method uses simple keyword matching for FUTURE USE.
     * Current implementation should delegate to LLM-based detection.
     */
    public function canHandle(string $query): bool
    {
        // 🌍 Traduire en anglais pour analyse multilingue cohérente
        $translatedQuery = SemanticAgent::translateToEnglish($query, 80);
        
        if ($this->debug) {
            $this->logDebug("Pattern analysis detection - Original: " . substr($query, 0, 50) . 
                           " → Translated: " . substr($translatedQuery, 0, 50));
        }

        // Utiliser la requête traduite en priorité
        $queryToAnalyze = !empty($translatedQuery) ? $translatedQuery : $query;

        // Simple keyword matching (replaces deleted PatternAnalysisPattern)
        $patternKeywords = ['pattern', 'trend', 'style', 'dominant', 'recurring', 'common'];
        $queryLower = strtolower($queryToAnalyze);
        
        foreach ($patternKeywords as $keyword) {
            if (strpos($queryLower, $keyword) !== false) {
                if ($this->debug) {
                    $this->logDebug("Pattern analysis keyword detected: $keyword");
                }
                return true;
            }
        }
        
        return false;
        
        if ($matches && $this->debug) {
            $this->logDebug("Pattern analysis detected using PatternAnalysisPattern class");
        }

        return $matches;
    }
    
    /**
     * Creates le plan d'analyse de patterns (4 étapes déterministes)
     */
    public function createPlan(array $intent, string $query): array
    {
        if ($this->debug) {
            $this->logDebug("Creating pattern analysis plan for query: " . substr($query, 0, 100));
        }
        
        $steps = [];

        // Step 1: Charger le catalogue produits
        $step1 = new TaskStep(
            'step_1',
            'load_product_catalog_data',
            'Load our product catalog for pattern analysis',
            [
                'intent' => $intent,
                'data_source' => 'internal_database',
                'scope' => 'our_product_catalog',
                'tables' => $this->getTablesFromDomain(),
                'depends_on' => [],
                'can_run_parallel' => false,
                'is_final' => false,
            ]
        );
        $steps[] = $step1;

        // Step 2: Extraction des patterns
        $step2 = new TaskStep(
            'step_2',
            'pattern_extraction',
            'Extract patterns from internal data',
            [
                'intent' => $intent,
                'analysis_type' => 'pattern_detection',
                'extraction_methods' => ['keyword_frequency', 'category_clustering', 'price_patterns', 'seasonal_trends'],
                'depends_on' => ['step_1'],
                'can_run_parallel' => false,
                'is_final' => false,
            ]
        );
        $steps[] = $step2;

        // Step 3: Classement par fréquence et pertinence
        $step3 = new TaskStep(
            'step_3',
            'pattern_frequency_ranking',
            'Rank patterns by frequency and relevance',
            [
                'intent' => $intent,
                'ranking_criteria' => ['frequency', 'relevance', 'market_impact', 'revenue_correlation'],
                'scoring_method' => 'weighted_composite',
                'depends_on' => ['step_2'],
                'can_run_parallel' => false,
                'is_final' => false,
            ]
        );
        $steps[] = $step3;

        // Step 4: Synthèse des résultats
        $step4 = new TaskStep(
            'step_4',
            'pattern_synthesis',
            'Synthesize pattern analysis results',
            [
                'intent' => $intent,
                'synthesis_type' => 'pattern_insights',
                'output_format' => ['top_patterns', 'trend_analysis', 'recommendations'],
                'depends_on' => ['step_1', 'step_2', 'step_3'],
                'can_run_parallel' => false,
                'is_final' => true,
            ]
        );
        $steps[] = $step4;

        if ($this->debug) {
            $this->logDebug("Created pattern analysis plan with " . count($steps) . " steps");
        }

        return $steps;
    }
    
    /**
     * Gets les planner metadata
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'Pattern Analysis Planner',
            'description' => 'Specialized planner for identifying patterns, trends and dominant motifs',
            'steps_count' => 4,
            'step_types' => ['load_product_catalog_data', 'pattern_extraction', 'pattern_frequency_ranking', 'pattern_synthesis'],
            'data_sources' => ['internal_database'],
            'analysis_methods' => ['keyword_frequency', 'category_clustering', 'price_patterns', 'seasonal_trends'],
            'supports_fallback' => false,
            'requires_external_data' => false
        ];
    }
    
    /**
     * Get tables from active domain configuration
     * 
     * Uses DomainRegistry to load entity configuration from the active domain app.
     * Falls back to empty array if no domain is active (let LLM discover tables).
     * 
     * Added for domain-agnostic table loading (Priority 2)
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
        
        // Fallback: empty array (let LLM discover tables)
        return [];
    }
    
    private function logDebug(string $message): void
    {
        if ($this->securityLogger) {
            $this->securityLogger->logSecurityEvent($message, 'info');
        }
        
        if ($this->debug) {
            error_log("[SubTaskPlannerPatternAnalysis] $message");
        }
    }
}