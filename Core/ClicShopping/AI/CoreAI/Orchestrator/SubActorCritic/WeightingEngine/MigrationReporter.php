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

/**
 * Migration Reporter for Adaptive Weighting System
 * 
 * Generates comprehensive reports on migration progress including:
 * - Daily summary reports
 * - Weekly trend analysis
 * - Context-specific performance
 * - Critic-specific analysis
 * - Recommendations for rollout adjustments
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine
 * @version 1.0.0
 * @since 2026-02-06
 */
class MigrationReporter
{
    private $db;
    private MigrationManager $migrationManager;
    
    /**
     * Constructor
     * 
     * @param MigrationManager $migrationManager Migration manager instance
     */
    public function __construct(MigrationManager $migrationManager)
    {
        $this->db = Registry::get('Db');
        $this->migrationManager = $migrationManager;
    }
    
    /**
     * Generate daily summary report
     * 
     * Creates a comprehensive daily report with statistics, trends, and recommendations.
     * 
     * Requirements: 30.5
     * 
     * @return string Formatted report
     */
    public function generateDailySummary(): string
    {
        $report = $this->migrationManager->generateProgressReport(1);
        
        $output = [];
        $output[] = "=================================================================";
        $output[] = "  ADAPTIVE WEIGHTING MIGRATION - DAILY SUMMARY";
        $output[] = "  Generated: " . $report['generated_at'];
        $output[] = "  Rollout Percentage: " . $report['rollout_percentage'] . "%";
        $output[] = "=================================================================";
        $output[] = "";
        
        // Overall Statistics
        $stats = $report['statistics'];
        $output[] = "OVERALL STATISTICS (Last 24 Hours)";
        $output[] = "-----------------------------------------------------------------";
        $output[] = sprintf("  Total Evaluations: %d", $stats['total_evaluations'] ?? 0);
        $output[] = sprintf("  Avg Weight Difference: %.4f", $stats['avg_weight_diff'] ?? 0);
        $output[] = sprintf("  Max Weight Difference: %.4f", $stats['max_weight_diff'] ?? 0);
        $output[] = sprintf("  Avg Consensus Difference: %.4f", $stats['avg_consensus_diff'] ?? 0);
        $output[] = sprintf("  Max Consensus Difference: %.4f", $stats['max_consensus_diff'] ?? 0);
        $output[] = sprintf("  Significant Differences (>0.1): %d", $stats['significant_differences'] ?? 0);
        $output[] = sprintf("  Adaptive Higher: %d", $stats['adaptive_higher'] ?? 0);
        $output[] = sprintf("  Adaptive Lower: %d", $stats['adaptive_lower'] ?? 0);
        $output[] = "";
        
        // Context Analysis
        if (!empty($report['context_analysis'])) {
            $output[] = "CONTEXT-SPECIFIC ANALYSIS";
            $output[] = "-----------------------------------------------------------------";
            foreach ($report['context_analysis'] as $context) {
                $output[] = sprintf(
                    "  %s (%s priority): %d evaluations, avg diff: %.4f, max diff: %.4f",
                    $context['context_type'],
                    $context['priority'],
                    $context['count'],
                    $context['avg_difference'],
                    $context['max_difference']
                );
            }
            $output[] = "";
        }
        
        // Critic Analysis
        if (!empty($report['critic_analysis'])) {
            $output[] = "TOP CRITICS BY WEIGHT DIFFERENCE";
            $output[] = "-----------------------------------------------------------------";
            foreach (array_slice($report['critic_analysis'], 0, 5) as $critic) {
                $output[] = sprintf(
                    "  %s: %d evaluations, avg diff: %.4f, max diff: %.4f",
                    $critic['critic_id'],
                    $critic['evaluations'],
                    $critic['avg_weight_difference'],
                    $critic['max_weight_difference']
                );
            }
            $output[] = "";
        }
        
        // Recommendations
        if (!empty($report['recommendations'])) {
            $output[] = "RECOMMENDATIONS";
            $output[] = "-----------------------------------------------------------------";
            foreach ($report['recommendations'] as $rec) {
                $confidence = strtoupper($rec['confidence']);
                $output[] = sprintf("  [%s] %s: %s", $confidence, $rec['type'], $rec['message']);
            }
            $output[] = "";
        }
        
        $output[] = "=================================================================";
        
        return implode("\n", $output);
    }
    
    /**
     * Generate weekly trend report
     * 
     * Analyzes trends over the past 7 days.
     * 
     * Requirements: 30.5
     * 
     * @return string Formatted report
     */
    public function generateWeeklyTrends(): string
    {
        $report = $this->migrationManager->generateProgressReport(7);
        
        $output = [];
        $output[] = "=================================================================";
        $output[] = "  ADAPTIVE WEIGHTING MIGRATION - WEEKLY TRENDS";
        $output[] = "  Generated: " . $report['generated_at'];
        $output[] = "  Period: Last 7 Days";
        $output[] = "=================================================================";
        $output[] = "";
        
        // Get daily statistics
        $dailyStats = $this->getDailyStatistics(7);
        
        if (!empty($dailyStats)) {
            $output[] = "DAILY STATISTICS";
            $output[] = "-----------------------------------------------------------------";
            $output[] = sprintf(
                "  %-12s  %10s  %15s  %15s",
                "Date",
                "Evals",
                "Avg Weight Diff",
                "Avg Consensus Diff"
            );
            $output[] = str_repeat("-", 65);
            
            foreach ($dailyStats as $day) {
                $output[] = sprintf(
                    "  %-12s  %10d  %15.4f  %15.4f",
                    $day['date'],
                    $day['total_evaluations'],
                    $day['avg_weight_difference'],
                    $day['avg_consensus_difference']
                );
            }
            $output[] = "";
        }
        
        // Trend Analysis
        $output[] = "TREND ANALYSIS";
        $output[] = "-----------------------------------------------------------------";
        
        if (count($dailyStats) >= 2) {
            $first = $dailyStats[0];
            $last = $dailyStats[count($dailyStats) - 1];
            
            $weightDiffTrend = $last['avg_weight_difference'] - $first['avg_weight_difference'];
            $consensusDiffTrend = $last['avg_consensus_difference'] - $first['avg_consensus_difference'];
            
            $output[] = sprintf("  Weight Difference Trend: %+.4f %s", 
                $weightDiffTrend,
                $weightDiffTrend > 0 ? "(increasing)" : "(decreasing)"
            );
            $output[] = sprintf("  Consensus Difference Trend: %+.4f %s",
                $consensusDiffTrend,
                $consensusDiffTrend > 0 ? "(increasing)" : "(decreasing)"
            );
        } else {
            $output[] = "  Insufficient data for trend analysis";
        }
        
        $output[] = "";
        $output[] = "=================================================================";
        
        return implode("\n", $output);
    }
    
    /**
     * Get daily statistics for trend analysis
     * 
     * @param int $days Number of days to retrieve
     * @return array Daily statistics
     */
    private function getDailyStatistics(int $days): array
    {
        try {
            $tablePrefix = CLICSHOPPING::getConfig('db_table_prefix', '');
            $sql = "
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_evaluations,
                    AVG(avg_weight_difference) as avg_weight_difference,
                    AVG(consensus_difference) as avg_consensus_difference
                FROM `{$tablePrefix}rag_agent_migration_log`
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Generate HTML report for dashboard
     * 
     * Creates an HTML-formatted report suitable for display in the dashboard.
     * 
     * Requirements: 30.5
     * 
     * @return string HTML report
     */
    public function generateHTMLReport(): string
    {
        $report = $this->migrationManager->generateProgressReport(7);
        $stats = $report['statistics'];
        
        $html = '<div class="migration-report">';
        
        // Header
        $html .= '<div class="report-header">';
        $html .= '<h3>Migration Progress Report</h3>';
        $html .= '<p class="text-muted">Generated: ' . htmlspecialchars($report['generated_at']) . '</p>';
        $html .= '<p><strong>Rollout Percentage:</strong> ' . $report['rollout_percentage'] . '%</p>';
        $html .= '</div>';
        
        // Statistics Cards
        $html .= '<div class="row mt-3">';
        
        $html .= $this->createStatCard('Total Evaluations', $stats['total_evaluations'] ?? 0, 'primary');
        $html .= $this->createStatCard('Avg Weight Diff', number_format($stats['avg_weight_diff'] ?? 0, 4), 'info');
        $html .= $this->createStatCard('Avg Consensus Diff', number_format($stats['avg_consensus_diff'] ?? 0, 4), 'warning');
        $html .= $this->createStatCard('Significant Diffs', $stats['significant_differences'] ?? 0, 'danger');
        
        $html .= '</div>';
        
        // Recommendations
        if (!empty($report['recommendations'])) {
            $html .= '<div class="mt-4">';
            $html .= '<h4>Recommendations</h4>';
            $html .= '<ul class="list-group">';
            
            foreach ($report['recommendations'] as $rec) {
                $badgeClass = $rec['confidence'] === 'high' ? 'success' : 'warning';
                $html .= '<li class="list-group-item">';
                $html .= '<span class="badge bg-' . $badgeClass . '">' . strtoupper($rec['confidence']) . '</span> ';
                $html .= '<strong>' . htmlspecialchars($rec['type']) . ':</strong> ';
                $html .= htmlspecialchars($rec['message']);
                $html .= '</li>';
            }
            
            $html .= '</ul>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Create a statistics card for HTML report
     * 
     * @param string $title Card title
     * @param mixed $value Card value
     * @param string $color Bootstrap color class
     * @return string HTML card
     */
    private function createStatCard(string $title, $value, string $color): string
    {
        return sprintf(
            '<div class="col-md-3">
                <div class="card border-%s">
                    <div class="card-body text-center">
                        <h5 class="card-title text-%s">%s</h5>
                        <p class="card-text display-6">%s</p>
                    </div>
                </div>
            </div>',
            $color,
            $color,
            htmlspecialchars($title),
            htmlspecialchars((string)$value)
        );
    }
}
