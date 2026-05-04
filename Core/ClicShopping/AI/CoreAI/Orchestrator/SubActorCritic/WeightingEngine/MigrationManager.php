<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

/**
 * Migration Manager for Adaptive Weighting System
 * 
 * Manages the migration from static to adaptive weighting with:
 * - Parallel calculation of both weight types
 * - Gradual rollout with percentage control
 * - Comparison logging and analysis
 * - Rollback mechanisms
 * - Progress reporting
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine
 * @version 1.0.0
 * @since 2026-02-06
 */
class MigrationManager
{
    private $db;
    private array $config;
    private string $logPath;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Registry::get('Db');
        $this->config = $this->loadConfig();
        $this->logPath = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Log/adaptive_weighting_migration.log';
    }
    
    /**
     * Load configuration
     * 
     * @return array Configuration array
     */
    private function loadConfig(): array
    {
        $configPath = CLICSHOPPING::getConfig('dir_root', 'Shop') . 
                     'Core/ClicShopping/Apps/Configuration/ChatGpt/config/adaptive_weighting.php';
        
        if (file_exists($configPath)) {
            return require $configPath;
        }
        
        return [
            'MIGRATION_MODE' => false,
            'ADAPTIVE_WEIGHT_ROLLOUT_PERCENTAGE' => 0,
        ];
    }
    
    /**
     * Check if migration mode is enabled
     * 
     * @return bool True if migration mode is active
     */
    public function isMigrationMode(): bool
    {
        return $this->config['MIGRATION_MODE'] ?? false;
    }
    
    /**
     * Get rollout percentage
     * 
     * @return int Percentage (0-100)
     */
    public function getRolloutPercentage(): int
    {
        return (int)($this->config['ADAPTIVE_WEIGHT_ROLLOUT_PERCENTAGE'] ?? 0);
    }
    
    /**
     * Determine if this evaluation should use adaptive weighting
     * 
     * Uses deterministic selection based on evaluation ID hash
     * to ensure consistent behavior across retries.
     * 
     * @param string $evaluationId Evaluation identifier
     * @return bool True if should use adaptive weighting
     */
    public function shouldUseAdaptiveWeighting(string $evaluationId): bool
    {
        // If not in migration mode, check if adaptive weighting is fully enabled
        if (!$this->isMigrationMode()) {
            return $this->config['ADAPTIVE_WEIGHTING_ENABLED'] ?? false;
        }
        
        $rolloutPercentage = $this->getRolloutPercentage();
        
        // 0% = no adaptive weighting
        if ($rolloutPercentage === 0) {
            return false;
        }
        
        // 100% = all adaptive weighting
        if ($rolloutPercentage >= 100) {
            return true;
        }
        
        // Deterministic selection based on evaluation ID hash
        // This ensures the same evaluation always gets the same decision
        $hash = crc32($evaluationId);
        $bucket = $hash % 100;
        
        return $bucket < $rolloutPercentage;
    }
    
    /**
     * Log migration comparison
     * 
     * Stores comparison between static and adaptive weights/consensus
     * for analysis during migration.
     * 
     * @param string $evaluationId Evaluation identifier
     * @param array $staticWeights Static weights [criticId => weight]
     * @param array $adaptiveWeights Adaptive weights [criticId => weight]
     * @param float $staticConsensus Static consensus value
     * @param float $dynamicConsensus Dynamic consensus value
     * @param array $context Evaluation context
     * @return void
     */
    public function logMigrationComparison(
        string $evaluationId,
        array $staticWeights,
        array $adaptiveWeights,
        float $staticConsensus,
        float $dynamicConsensus,
        array $context = []
    ): void {
        if (!$this->isMigrationMode()) {
            return;
        }
        
        try {
            // Calculate weight differences
            $weightDifferences = [];
            foreach ($staticWeights as $criticId => $staticWeight) {
                $adaptiveWeight = $adaptiveWeights[$criticId] ?? 0.0;
                $weightDifferences[$criticId] = abs($adaptiveWeight - $staticWeight);
            }
            
            $avgWeightDifference = count($weightDifferences) > 0 
                ? array_sum($weightDifferences) / count($weightDifferences)
                : 0.0;
            
            $consensusDifference = abs($dynamicConsensus - $staticConsensus);
            
            // Store in database
            $tablePrefix = CLICSHOPPING::getConfig('db_table_prefix', '');
            $sql = "
                INSERT INTO `{$tablePrefix}rag_agent_migration_log` 
                (evaluation_id, static_weights, adaptive_weights, weight_differences, 
                 avg_weight_difference, static_consensus, dynamic_consensus, 
                 consensus_difference, evaluation_context, created_at)
                VALUES (:evaluation_id, :static_weights, :adaptive_weights, :weight_differences,
                        :avg_weight_difference, :static_consensus, :dynamic_consensus,
                        :consensus_difference, :evaluation_context, NOW())
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':evaluation_id' => $evaluationId,
                ':static_weights' => json_encode($staticWeights),
                ':adaptive_weights' => json_encode($adaptiveWeights),
                ':weight_differences' => json_encode($weightDifferences),
                ':avg_weight_difference' => $avgWeightDifference,
                ':static_consensus' => $staticConsensus,
                ':dynamic_consensus' => $dynamicConsensus,
                ':consensus_difference' => $consensusDifference,
                ':evaluation_context' => json_encode($context),
            ]);
            
            // Log to file
            $this->logToFile("Migration comparison logged for evaluation {$evaluationId}: " .
                           "avg_weight_diff={$avgWeightDifference}, consensus_diff={$consensusDifference}");
            
        } catch (\Exception $e) {
            $this->logToFile("Error logging migration comparison: " . $e->getMessage());
        }
    }
    
    /**
     * Generate migration progress report
     * 
     * Analyzes migration data and generates a comprehensive report
     * showing how adaptive weighting compares to static weighting.
     * 
     * @param int $days Number of days to analyze (default: 1 for daily)
     * @return array Report data
     */
    public function generateProgressReport(int $days = 1): array
    {
        try {
            $tablePrefix = CLICSHOPPING::getConfig('db_table_prefix', '');
            $sql = "
                SELECT 
                    COUNT(*) as total_evaluations,
                    AVG(avg_weight_difference) as avg_weight_diff,
                    MAX(avg_weight_difference) as max_weight_diff,
                    AVG(consensus_difference) as avg_consensus_diff,
                    MAX(consensus_difference) as max_consensus_diff,
                    SUM(CASE WHEN consensus_difference > 0.1 THEN 1 ELSE 0 END) as significant_differences,
                    SUM(CASE WHEN dynamic_consensus > static_consensus THEN 1 ELSE 0 END) as adaptive_higher,
                    SUM(CASE WHEN dynamic_consensus < static_consensus THEN 1 ELSE 0 END) as adaptive_lower
                FROM `{$tablePrefix}rag_agent_migration_log`
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            // Get context-specific analysis
            $contextAnalysis = $this->analyzeByContext($days);
            
            // Get critic-specific analysis
            $criticAnalysis = $this->analyzeByCritic($days);
            
            $report = [
                'period_days' => $days,
                'generated_at' => date('Y-m-d H:i:s'),
                'rollout_percentage' => $this->getRolloutPercentage(),
                'statistics' => $stats,
                'context_analysis' => $contextAnalysis,
                'critic_analysis' => $criticAnalysis,
                'recommendations' => $this->generateRecommendations($stats, $contextAnalysis),
            ];
            
            // Log report generation
            $this->logToFile("Migration progress report generated for {$days} days: " .
                           "{$stats['total_evaluations']} evaluations analyzed");
            
            return $report;
            
        } catch (\Exception $e) {
            $this->logToFile("Error generating progress report: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'generated_at' => date('Y-m-d H:i:s'),
            ];
        }
    }
    
    /**
     * Analyze migration data by evaluation context
     * 
     * @param int $days Number of days to analyze
     * @return array Context-specific analysis
     */
    private function analyzeByContext(int $days): array
    {
        try {
            $tablePrefix = CLICSHOPPING::getConfig('db_table_prefix', '');
            $sql = "
                SELECT 
                    evaluation_context,
                    COUNT(*) as count,
                    AVG(consensus_difference) as avg_diff,
                    MAX(consensus_difference) as max_diff
                FROM `{$tablePrefix}rag_agent_migration_log`
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY evaluation_context
                ORDER BY avg_diff DESC
                LIMIT 10
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $analysis = [];
            foreach ($results as $row) {
                $context = json_decode($row['evaluation_context'], true);
                $analysis[] = [
                    'context_type' => $context['outputType'] ?? 'unknown',
                    'priority' => $context['priorityLevel'] ?? 'unknown',
                    'count' => $row['count'],
                    'avg_difference' => round($row['avg_diff'], 4),
                    'max_difference' => round($row['max_diff'], 4),
                ];
            }
            
            return $analysis;
            
        } catch (\Exception $e) {
            $this->logToFile("Error analyzing by context: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Analyze migration data by critic
     * 
     * @param int $days Number of days to analyze
     * @return array Critic-specific analysis
     */
    private function analyzeByCritic(int $days): array
    {
        try {
            $tablePrefix = CLICSHOPPING::getConfig('db_table_prefix', '');
            $sql = "
                SELECT 
                    evaluation_id,
                    static_weights,
                    adaptive_weights,
                    weight_differences
                FROM `{$tablePrefix}rag_agent_migration_log`
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $criticStats = [];
            
            foreach ($results as $row) {
                $weightDiffs = json_decode($row['weight_differences'], true);
                
                foreach ($weightDiffs as $criticId => $diff) {
                    if (!isset($criticStats[$criticId])) {
                        $criticStats[$criticId] = [
                            'count' => 0,
                            'total_diff' => 0.0,
                            'max_diff' => 0.0,
                        ];
                    }
                    
                    $criticStats[$criticId]['count']++;
                    $criticStats[$criticId]['total_diff'] += $diff;
                    $criticStats[$criticId]['max_diff'] = max($criticStats[$criticId]['max_diff'], $diff);
                }
            }
            
            // Calculate averages and sort
            $analysis = [];
            foreach ($criticStats as $criticId => $stats) {
                $analysis[] = [
                    'critic_id' => $criticId,
                    'evaluations' => $stats['count'],
                    'avg_weight_difference' => round($stats['total_diff'] / $stats['count'], 4),
                    'max_weight_difference' => round($stats['max_diff'], 4),
                ];
            }
            
            // Sort by average difference descending
            usort($analysis, function($a, $b) {
                return $b['avg_weight_difference'] <=> $a['avg_weight_difference'];
            });
            
            return array_slice($analysis, 0, 10); // Top 10
            
        } catch (\Exception $e) {
            $this->logToFile("Error analyzing by critic: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate recommendations based on migration data
     * 
     * @param array $stats Overall statistics
     * @param array $contextAnalysis Context-specific analysis
     * @return array Recommendations
     */
    private function generateRecommendations(array $stats, array $contextAnalysis): array
    {
        $recommendations = [];
        
        // Check if ready to increase rollout
        if ($stats['total_evaluations'] >= 100) {
            $avgDiff = $stats['avg_consensus_diff'] ?? 0;
            $significantDiffs = $stats['significant_differences'] ?? 0;
            $significantPct = $stats['total_evaluations'] > 0 
                ? ($significantDiffs / $stats['total_evaluations']) * 100 
                : 0;
            
            if ($avgDiff < 0.05 && $significantPct < 10) {
                $recommendations[] = [
                    'type' => 'increase_rollout',
                    'message' => 'Low average difference and few significant differences. Consider increasing rollout percentage.',
                    'confidence' => 'high',
                ];
            } elseif ($avgDiff > 0.15 || $significantPct > 25) {
                $recommendations[] = [
                    'type' => 'investigate',
                    'message' => 'High differences detected. Investigate before increasing rollout.',
                    'confidence' => 'high',
                ];
            } else {
                $recommendations[] = [
                    'type' => 'continue_monitoring',
                    'message' => 'Moderate differences. Continue monitoring before adjusting rollout.',
                    'confidence' => 'medium',
                ];
            }
        } else {
            $recommendations[] = [
                'type' => 'collect_more_data',
                'message' => 'Insufficient data. Collect at least 100 evaluations before adjusting rollout.',
                'confidence' => 'high',
            ];
        }
        
        // Check for context-specific issues
        foreach ($contextAnalysis as $context) {
            if ($context['avg_difference'] > 0.2) {
                $recommendations[] = [
                    'type' => 'context_investigation',
                    'message' => "High differences for {$context['context_type']} context. Review adaptive weighting logic.",
                    'confidence' => 'medium',
                    'context' => $context['context_type'],
                ];
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Rollback to static weighting
     * 
     * Disables adaptive weighting and reverts to static reputation-based weighting.
     * 
     * @param string $reason Reason for rollback
     * @return bool Success status
     */
    public function rollback(string $reason): bool
    {
        try {
            // Update configuration file
            $configPath = CLICSHOPPING::getConfig('dir_root', 'Shop') . 
                         'Core/ClicShopping/Apps/Configuration/ChatGpt/config/adaptive_weighting.php';
            
            if (!file_exists($configPath)) {
                throw new \Exception("Configuration file not found: {$configPath}");
            }
            
            $config = file_get_contents($configPath);
            
            // Disable adaptive weighting
            $config = preg_replace(
                "/'ADAPTIVE_WEIGHTING_ENABLED'\s*=>\s*true/",
                "'ADAPTIVE_WEIGHTING_ENABLED' => false",
                $config
            );
            
            // Disable migration mode
            $config = preg_replace(
                "/'MIGRATION_MODE'\s*=>\s*true/",
                "'MIGRATION_MODE' => false",
                $config
            );
            
            // Reset rollout percentage
            $config = preg_replace(
                "/'ADAPTIVE_WEIGHT_ROLLOUT_PERCENTAGE'\s*=>\s*\d+/",
                "'ADAPTIVE_WEIGHT_ROLLOUT_PERCENTAGE' => 0",
                $config
            );
            
            file_put_contents($configPath, $config);
            
            // Log rollback
            $this->logRollback($reason);
            
            $this->logToFile("Rollback completed: {$reason}");
            
            return true;
            
        } catch (\Exception $e) {
            $this->logToFile("Error during rollback: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log rollback event
     * 
     * @param string $reason Reason for rollback
     * @return void
     */
    private function logRollback(string $reason): void
    {
        try {
            $tablePrefix = CLICSHOPPING::getConfig('db_table_prefix', '');
            $sql = "
                INSERT INTO `{$tablePrefix}rag_agent_migration_rollback`
                (reason, rollback_at, rollout_percentage_at_rollback)
                VALUES (:reason, NOW(), :rollout_percentage)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':reason' => $reason,
                ':rollout_percentage' => $this->getRolloutPercentage(),
            ]);
            
        } catch (\Exception $e) {
            $this->logToFile("Error logging rollback: " . $e->getMessage());
        }
    }
    
    /**
     * Export migration data for analysis
     * 
     * @param int $days Number of days to export
     * @param string $format Export format ('csv' or 'json')
     * @return string|array Exported data
     */
    public function exportMigrationData(int $days = 7, string $format = 'json')
    {
        try {
            $tablePrefix = CLICSHOPPING::getConfig('db_table_prefix', '');
            $sql = "
                SELECT *
                FROM `{$tablePrefix}rag_agent_migration_log`
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY created_at DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if ($format === 'csv') {
                return $this->convertToCSV($data);
            }
            
            return $data;
            
        } catch (\Exception $e) {
            $this->logToFile("Error exporting migration data: " . $e->getMessage());
            return $format === 'csv' ? '' : [];
        }
    }
    
    /**
     * Convert data to CSV format
     * 
     * @param array $data Data to convert
     * @return string CSV string
     */
    private function convertToCSV(array $data): string
    {
        if (empty($data)) {
            return '';
        }
        
        $csv = [];
        
        // Header
        $csv[] = implode(',', array_keys($data[0]));
        
        // Rows
        foreach ($data as $row) {
            $csv[] = implode(',', array_map(function($value) {
                if (is_array($value) || is_object($value)) {
                    return '"' . str_replace('"', '""', json_encode($value)) . '"';
                }
                return '"' . str_replace('"', '""', $value) . '"';
            }, $row));
        }
        
        return implode("\n", $csv);
    }
    
    /**
     * Log message to file
     * 
     * @param string $message Message to log
     * @return void
     */
    private function logToFile(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        file_put_contents($this->logPath, $logMessage, FILE_APPEND);
    }
}
