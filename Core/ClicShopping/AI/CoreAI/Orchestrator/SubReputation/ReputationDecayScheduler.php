<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationStore;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationCalculator;

/**
 * ReputationDecayScheduler - Scheduled job for applying reputation decay
 * 
 * This class implements the daily reputation decay scheduler that applies
 * decay to all critic reputations based on their recent performance.
 * 
 * The scheduler should be called by a cron job or scheduled task on a
 * configurable schedule (default: daily).
 * 
 * Requirements: 4.2
 */
class ReputationDecayScheduler
{
    private ReputationStore $store;
    private ReputationCalculator $calculator;
    private bool $debug;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->store = new ReputationStore();
        $this->calculator = new ReputationCalculator();
        $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                       CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    }
    
    /**
     * Execute reputation decay for all critics
     * 
     * This method should be called by a cron job or scheduled task.
     * It applies decay to all critics based on their recent performance.
     * 
     * Requirements: 4.2
     * 
     * @return array Summary of decay operation with keys: processed, updated, errors
     */
    public function executeDecay(): array
    {
        $startTime = microtime(true);
        $summary = [
            'processed' => 0,
            'updated' => 0,
            'errors' => 0,
            'skipped' => 0
        ];
        
        try {
            // Get decay configuration
            $decayConfig = $this->getDecayConfiguration();
            
            if (!$decayConfig['enabled']) {
                error_log("[ReputationDecayScheduler] Decay is disabled in configuration");
                return $summary;
            }
            
            if ($this->debug) {
                error_log("[ReputationDecayScheduler] Starting decay execution with config: " . 
                         json_encode($decayConfig));
            }
            
            // Get all critics with reputation data
            $critics = $this->store->getAllCritics();
            
            if ($this->debug) {
                error_log("[ReputationDecayScheduler] Found " . count($critics) . " critics to process");
            }
            
            foreach ($critics as $critic) {
                $summary['processed']++;
                
                try {
                    // Check if decay is needed
                    $periodsElapsed = $this->calculatePeriodsElapsed(
                        $critic['last_decay_at'],
                        $decayConfig['period_seconds']
                    );
                    
                    if ($periodsElapsed === 0) {
                        $summary['skipped']++;
                        continue; // No decay needed yet
                    }
                    
                    // Calculate recent performance
                    $recentPerformance = $this->calculateRecentPerformance(
                        $critic['critic_id'],
                        $decayConfig['recent_evaluation_count']
                    );
                    
                    // Apply decay
                    $oldReputation = (float)$critic['reputation_score'];
                    $newReputation = $this->calculator->applyDecay(
                        $oldReputation,
                        $recentPerformance,
                        $periodsElapsed,
                        $decayConfig['decay_factor']
                    );
                    
                    // Update reputation in database
                    $this->store->updateReputationAfterDecay(
                        $critic['critic_id'],
                        $newReputation
                    );
                    
                    $summary['updated']++;
                    
                } catch (\Exception $e) {
                    $summary['errors']++;
                    error_log("[ReputationDecayScheduler] Error processing critic {$critic['critic_id']}: " . 
                             $e->getMessage());
                }
            }
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            error_log(sprintf(
                "[ReputationDecayScheduler] Decay execution complete: processed=%d, updated=%d, skipped=%d, errors=%d, duration=%dms",
                $summary['processed'],
                $summary['updated'],
                $summary['skipped'],
                $summary['errors'],
                $duration
            ));
            
        } catch (\Exception $e) {
            error_log("[ReputationDecayScheduler] Fatal error during decay execution: " . $e->getMessage());
            $summary['errors']++;
        }
        
        return $summary;
    }
    
    /**
     * Get decay configuration from database or use defaults
     * 
     * Requirements: 4.2
     * 
     * @return array Decay configuration
     */
    private function getDecayConfiguration(): array
    {
        $defaults = [
            'enabled' => true,
            'decay_factor' => 0.95,
            'period_seconds' => 86400, // Daily (24 hours)
            'recent_evaluation_count' => 10
        ];
        
        try {
            $db = Registry::get('Db');
            $prefix = CLICSHOPPING::getConfig('db_table_prefix');
            
            $sql = "SELECT config_key, config_value 
                    FROM {$prefix}rag_agent_actor_critic_config 
                    WHERE config_key LIKE 'reputation_decay_%'";
            
            $result = $db->query($sql);
            
            $config = $defaults;
            
            while ($row = $result->fetch()) {
                $key = str_replace('reputation_decay_', '', $row['config_key']);
                $value = $row['config_value'];
                
                // Type conversion
                if ($key === 'enabled') {
                    $config[$key] = (bool)$value;
                } elseif ($key === 'decay_factor') {
                    $config[$key] = (float)$value;
                } elseif (in_array($key, ['period_seconds', 'recent_evaluation_count'], true)) {
                    $config[$key] = (int)$value;
                }
            }
            
            return $config;
            
        } catch (\Exception $e) {
            if ($this->debug) {
                error_log("[ReputationDecayScheduler] Failed to load config, using defaults: " . 
                         $e->getMessage());
            }
            return $defaults;
        }
    }
    
    /**
     * Calculate number of decay periods elapsed since last decay
     * 
     * @param string $lastDecayAt Last decay timestamp
     * @param int $periodSeconds Decay period in seconds
     * @return int Number of periods elapsed
     */
    private function calculatePeriodsElapsed(string $lastDecayAt, int $periodSeconds): int
    {
        $lastDecay = strtotime($lastDecayAt);
        $now = time();
        $elapsed = $now - $lastDecay;
        
        return (int)floor($elapsed / $periodSeconds);
    }
    
    /**
     * Calculate recent performance for a critic
     * 
     * Recent performance is the average reputation score from the last N evaluations.
     * 
     * @param string $criticId Critic ID
     * @param int $evaluationCount Number of recent evaluations to consider
     * @return float Recent performance score (0.0-1.0)
     */
    private function calculateRecentPerformance(string $criticId, int $evaluationCount): float
    {
        try {
            $db = Registry::get('Db');
            $prefix = CLICSHOPPING::getConfig('db_table_prefix');
            
            // Get recent evaluations
            $sql = "SELECT consensus_alignment, feedback_quality, consistency_score, expertise_accuracy
                    FROM {$prefix}rag_agent_reputation_history
                    WHERE critic_id = :critic_id
                    ORDER BY recorded_at DESC
                    LIMIT :limit";
            
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':critic_id', $criticId);
            $stmt->bindValue(':limit', $evaluationCount, \PDO::PARAM_INT);
            $stmt->execute();
            
            $evaluations = $stmt->fetchAll();
            
            if (empty($evaluations)) {
                // No recent evaluations, return neutral performance
                return 0.75;
            }
            
            // Calculate average performance using reputation formula
            $totalPerformance = 0;
            foreach ($evaluations as $eval) {
                $performance = (
                    0.4 * (float)$eval['consensus_alignment'] +
                    0.3 * (float)$eval['feedback_quality'] +
                    0.2 * (float)$eval['consistency_score'] +
                    0.1 * (float)$eval['expertise_accuracy']
                );
                $totalPerformance += $performance;
            }
            
            return $totalPerformance / count($evaluations);
            
        } catch (\Exception $e) {
            if ($this->debug) {
                error_log("[ReputationDecayScheduler] Error calculating recent performance for {$criticId}: " . 
                         $e->getMessage());
            }
            return 0.75; // Neutral performance on error
        }
    }
    
    /**
     * Check if decay should run based on schedule
     * 
     * This method can be used to determine if the decay job should execute
     * based on the last execution time.
     * 
     * @return bool True if decay should run
     */
    public function shouldRunDecay(): bool
    {
        try {
            $config = $this->getDecayConfiguration();
            
            if (!$config['enabled']) {
                return false;
            }
            
            $db = Registry::get('Db');
            $prefix = CLICSHOPPING::getConfig('db_table_prefix');
            
            // Check when decay was last run
            $sql = "SELECT MAX(last_decay_at) as last_decay
                    FROM {$prefix}rag_agent_reputation";
            
            $result = $db->query($sql);
            $row = $result->fetch();
            
            if (!$row || !$row['last_decay']) {
                return true; // Never run before
            }
            
            $lastDecay = strtotime($row['last_decay']);
            $now = time();
            $elapsed = $now - $lastDecay;
            
            // Run if at least one period has elapsed
            return $elapsed >= $config['period_seconds'];
            
        } catch (\Exception $e) {
            if ($this->debug) {
                error_log("[ReputationDecayScheduler] Error checking if decay should run: " . 
                         $e->getMessage());
            }
            return false;
        }
    }
}

