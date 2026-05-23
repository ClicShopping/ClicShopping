<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation;

use ClicShopping\OM\Registry;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\AI\InterfacesAI\CriticAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;

/**
 * Reputation-Aware Critic Selector
 * 
 * Selects critics for evaluations using reputation weighting combined with
 * expertise and diversity scoring. Integrates with the Actor-Critic architecture
 * to provide intelligent critic selection that favors historically accurate evaluators.
 * 
 * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
 */
class ReputationAwareCriticSelector
{
    private $db;
    private CriticRegistry $criticRegistry;
    private ReputationCache $reputationCache;
    private array $selectionLog = [];
    
    /**
     * Constructor
     * 
     * @param CriticRegistry $criticRegistry Critic registry for accessing critics
     * @param ReputationCache $reputationCache Cache for reputation scores
     */
    public function __construct(
        CriticRegistry $criticRegistry,
        ReputationCache $reputationCache
    ) {
        $this->db = Registry::get('Db');
        $this->criticRegistry = $criticRegistry;
        $this->reputationCache = $reputationCache;
    }
    
    /**
     * Select critics for evaluation with reputation weighting
     * 
     * Combines expertise, reputation, and diversity scoring to select the best
     * critics for evaluating an action. Ensures minimum reputation threshold
     * and maintains diversity in selection.
     * 
     * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
     * 
     * @param Action $action Action to be evaluated
     * @param int $count Number of critics to select (default: 3)
     * @param string $excludeActorId Actor ID to exclude (prevent self-evaluation)
     * @return array<CriticAgentInterface> Selected critics
     */
    public function selectCritics(
        Action $action,
        int $count = 3,
        string $excludeActorId = ''
    ): array {
        $startTime = microtime(true);
        
        // Get qualified critics for this action's output type
        $outputType = $action->getOutputType();
        $qualifiedCritics = $this->criticRegistry->getQualifiedCritics($outputType);
        
        // Filter out excluded actor (prevent self-evaluation)
        $validCritics = array_filter(
            $qualifiedCritics,
            fn($c) => $c->getCriticId() !== $excludeActorId
        );
        
        if (count($validCritics) < 2) {
            error_log("ReputationAwareCriticSelector: Insufficient critics available. Required: 2, Available: " . count($validCritics));
            // Fall back to basic selection if insufficient critics
            return array_slice($validCritics, 0, min($count, count($validCritics)));
        }
        
        // Get reputation scores for all valid critics
        $criticIds = array_map(fn($c) => $c->getCriticId(), $validCritics);
        $reputations = $this->reputationCache->getMultiple($criticIds);
        
        // Score each critic
        $scoredCritics = [];
        foreach ($validCritics as $critic) {
            $criticId = $critic->getCriticId();
            
            // Calculate expertise score (0.0-1.0)
            $expertiseScore = $this->calculateExpertiseScore($critic, $action);
            
            // Get reputation score (0.5-1.0)
            $reputationScore = $reputations[$criticId] ?? 0.75; // Default for new critics
            
            // Ensure minimum reputation threshold (0.5)
            // Requirement 7.4: Don't exclude critics solely based on low reputation
            if ($reputationScore < 0.5) {
                $reputationScore = 0.5;
            }
            
            // Calculate diversity score (will be updated after selection)
            $diversityScore = 1.0; // Initial value
            
            // Combined score: 40% expertise, 30% reputation, 30% diversity
            // Requirement 7.1, 7.2: Reputation influences selection
            $totalScore = (0.4 * $expertiseScore) + 
                         (0.3 * $reputationScore) + 
                         (0.3 * $diversityScore);
            
            $scoredCritics[] = [
                'critic' => $critic,
                'critic_id' => $criticId,
                'expertise_score' => $expertiseScore,
                'reputation_score' => $reputationScore,
                'diversity_score' => $diversityScore,
                'total_score' => $totalScore
            ];
        }
        
        // Sort by total score descending
        usort($scoredCritics, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        
        // Select critics with diversity consideration
        // Requirement 7.3: Ensure diversity
        $selected = $this->selectWithDiversity($scoredCritics, $count);
        
        $selectionTime = (microtime(true) - $startTime) * 1000; // Convert to ms
        
        // Log selection decision
        // Requirement 7.5: Log selection decisions
        $this->logSelectionDecision($action, $selected, $selectionTime);
        
        // Return just the critic objects
        return array_map(fn($s) => $s['critic'], $selected);
    }
    
    /**
     * Calculate expertise score for critic evaluating action
     * 
     * Evaluates how well the critic's expertise matches the action's domain
     * and output type requirements.
     * 
     * @param CriticAgentInterface $critic Critic to evaluate
     * @param Action $action Action to be evaluated
     * @return float Expertise score (0.0-1.0)
     */
    private function calculateExpertiseScore(
        CriticAgentInterface $critic,
        Action $action
    ): float {
        $outputType = $action->getOutputType();
        $domain = $action->getDomain() ?? '';
        
        // Get critic's evaluation criteria
        $criteria = $critic->getEvaluationCriteria();
        
        if (!isset($criteria[$outputType])) {
            return 0.0; // No expertise for this output type
        }
        
        $criterion = $criteria[$outputType];
        
        // Base expertise level
        $expertiseLevel = 0.5;
        if (is_object($criterion) && method_exists($criterion, 'getExpertiseLevel')) {
            $expertiseLevel = $criterion->getExpertiseLevel();
        } elseif (is_array($criterion) && isset($criterion['expertise_level'])) {
            $expertiseLevel = $criterion['expertise_level'];
        }
        
        // Bonus for domain match
        $domainBonus = 0.0;
        if (!empty($domain)) {
            $criticDomain = null;
            if (is_object($criterion) && method_exists($criterion, 'getDomain')) {
                $criticDomain = $criterion->getDomain();
            } elseif (is_array($criterion) && isset($criterion['domain'])) {
                $criticDomain = $criterion['domain'];
            }
            
            if ($criticDomain === $domain) {
                $domainBonus = 0.2; // 20% bonus for domain match
            }
        }
        
        return min(1.0, $expertiseLevel + $domainBonus);
    }
    
    /**
     * Select critics with diversity consideration
     * 
     * Ensures selected critics have diverse reputation levels to maintain
     * balanced evaluation perspectives.
     * 
     * Requirement 7.3: Ensure diversity
     * 
     * @param array $scoredCritics Scored critics array
     * @param int $count Number to select
     * @return array Selected critics with scores
     */
    private function selectWithDiversity(array $scoredCritics, int $count): array
    {
        $selected = [];
        $selectedReputations = [];
        
        foreach ($scoredCritics as $candidate) {
            if (count($selected) >= $count) {
                break;
            }
            
            // Calculate diversity score based on reputation variance
            $diversityScore = $this->calculateDiversityScore(
                $candidate['reputation_score'],
                $selectedReputations
            );
            
            // Update total score with actual diversity
            $candidate['diversity_score'] = $diversityScore;
            $candidate['total_score'] = (0.4 * $candidate['expertise_score']) + 
                                       (0.3 * $candidate['reputation_score']) + 
                                       (0.3 * $diversityScore);
            
            $selected[] = $candidate;
            $selectedReputations[] = $candidate['reputation_score'];
        }
        
        // Re-sort by updated total score
        usort($selected, fn($a, $b) => $b['total_score'] <=> $a['total_score']);
        
        return array_slice($selected, 0, $count);
    }
    
    /**
     * Calculate diversity score for candidate critic
     * 
     * Higher score for critics with reputation different from already selected critics.
     * Promotes diversity in reputation levels.
     * 
     * @param float $candidateReputation Candidate's reputation score
     * @param array $selectedReputations Already selected reputations
     * @return float Diversity score (0.0-1.0)
     */
    private function calculateDiversityScore(
        float $candidateReputation,
        array $selectedReputations
    ): float {
        if (empty($selectedReputations)) {
            return 1.0; // First selection always has max diversity
        }
        
        // Calculate average distance from selected reputations
        $distances = array_map(
            fn($rep) => abs($candidateReputation - $rep),
            $selectedReputations
        );
        
        $avgDistance = array_sum($distances) / count($distances);
        
        // Normalize to 0.0-1.0 (max distance is 0.5 since reputation range is 0.5-1.0)
        return min(1.0, $avgDistance * 2.0);
    }
    
    /**
     * Log selection decision with full context
     * 
     * Records critic selection decisions including reputation factors,
     * expertise scores, and diversity metrics for analysis and auditing.
     * 
     * Requirement 7.5: Log selection decisions
     * 
     * @param Action $action Action being evaluated
     * @param array $selected Selected critics with scores
     * @param float $selectionTimeMs Selection time in milliseconds
     * @return void
     */
    private function logSelectionDecision(
        Action $action,
        array $selected,
        float $selectionTimeMs
    ): void {
        $selectionId = uniqid('selection_', true);
        $actionId = $action->getActionId();
        $outputType = $action->getOutputType();
        $domain = $action->getDomain() ?? '';
        
        // Calculate diversity metrics
        $reputations = array_column($selected, 'reputation_score');
        $reputationStdDev = $this->calculateStandardDeviation($reputations);
        $avgReputation = array_sum($reputations) / count($reputations);
        
        // Prepare selection log entry
        $logEntry = [
            'selection_id' => $selectionId,
            'action_id' => $actionId,
            'output_type' => $outputType,
            'domain' => $domain,
            'num_selected' => count($selected),
            'avg_reputation' => $avgReputation,
            'reputation_std_dev' => $reputationStdDev,
            'selection_time_ms' => $selectionTimeMs,
            'selected_critics' => array_map(function($s) {
                return [
                    'critic_id' => $s['critic_id'],
                    'expertise_score' => $s['expertise_score'],
                    'reputation_score' => $s['reputation_score'],
                    'diversity_score' => $s['diversity_score'],
                    'total_score' => $s['total_score']
                ];
            }, $selected),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Store in memory log
        $this->selectionLog[] = $logEntry;
        
        // Persist to database
        $this->persistSelectionLog($logEntry);
        
        // Log to error log for debugging
        error_log(sprintf(
            "ReputationAwareCriticSelector: Selected %d critics for action %s (output_type: %s, domain: %s) - Avg Reputation: %.3f, Std Dev: %.3f, Time: %.2fms",
            count($selected),
            $actionId,
            $outputType,
            $domain,
            $avgReputation,
            $reputationStdDev,
            $selectionTimeMs
        ));
    }
    
    /**
     * Persist selection log to database
     * 
     * @param array $logEntry Log entry data
     * @return void
     */
    private function persistSelectionLog(array $logEntry): void
    {
        try {
            $sql = "
                INSERT INTO :table_rag_agent_reputation_selection_log (
                    selection_id, action_id, output_type, domain,
                    num_selected, avg_reputation, reputation_std_dev,
                    selection_time_ms, selected_critics, created_at
                ) VALUES (
                    :selection_id, :action_id, :output_type, :domain,
                    :num_selected, :avg_reputation, :reputation_std_dev,
                    :selection_time_ms, :selected_critics, NOW()
                )
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'selection_id' => $logEntry['selection_id'],
                'action_id' => $logEntry['action_id'],
                'output_type' => $logEntry['output_type'],
                'domain' => $logEntry['domain'],
                'num_selected' => $logEntry['num_selected'],
                'avg_reputation' => $logEntry['avg_reputation'],
                'reputation_std_dev' => $logEntry['reputation_std_dev'],
                'selection_time_ms' => $logEntry['selection_time_ms'],
                'selected_critics' => json_encode($logEntry['selected_critics'])
            ]);
        } catch (\Exception $e) {
            error_log("ReputationAwareCriticSelector: Failed to persist selection log: " . $e->getMessage());
            // Don't throw - logging failure shouldn't break selection
        }
    }
    
    /**
     * Calculate standard deviation of array
     * 
     * @param array $values Numeric values
     * @return float Standard deviation
     */
    private function calculateStandardDeviation(array $values): float
    {
        if (empty($values)) {
            return 0.0;
        }
        
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(
            fn($v) => pow($v - $mean, 2),
            $values
        )) / count($values);
        
        return sqrt($variance);
    }
    
    /**
     * Get selection log for analysis
     * 
     * @param int $limit Number of recent selections to return
     * @return array Selection log entries
     */
    public function getSelectionLog(int $limit = 100): array
    {
        return array_slice($this->selectionLog, -$limit);
    }
    
    /**
     * Get selection statistics
     * 
     * @param int $days Number of days to analyze
     * @return array Selection statistics
     */
    public function getSelectionStatistics(int $days = 7): array
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_selections,
                    AVG(num_selected) as avg_critics_per_selection,
                    AVG(avg_reputation) as overall_avg_reputation,
                    AVG(reputation_std_dev) as avg_diversity,
                    AVG(selection_time_ms) as avg_selection_time_ms,
                    MIN(avg_reputation) as min_avg_reputation,
                    MAX(avg_reputation) as max_avg_reputation
                FROM :table_rag_agent_reputation_selection_log
                WHERE created_at > DATE_SUB(NOW(), INTERVAL :days DAY)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['days' => $days]);
            $stats = $stmt->fetch();
            
            return [
                'period_days' => $days,
                'total_selections' => (int)$stats['total_selections'],
                'avg_critics_per_selection' => (float)$stats['avg_critics_per_selection'],
                'overall_avg_reputation' => (float)$stats['overall_avg_reputation'],
                'avg_diversity' => (float)$stats['avg_diversity'],
                'avg_selection_time_ms' => (float)$stats['avg_selection_time_ms'],
                'min_avg_reputation' => (float)$stats['min_avg_reputation'],
                'max_avg_reputation' => (float)$stats['max_avg_reputation']
            ];
        } catch (\Exception $e) {
            error_log("ReputationAwareCriticSelector: Failed to get selection statistics: " . $e->getMessage());
            return [];
        }
    }
}
