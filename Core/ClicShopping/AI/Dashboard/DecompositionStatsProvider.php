<?php
/**
 * DecompositionStatsProvider - Provides decomposition statistics for dashboard
 * 
 * Retrieves hybrid query decomposition metrics from rag_statistics table
 * for display in the ChatGPT dashboard.
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 * 
 * @created 2026-02-09
 * @see .kiro/specs/hybrid-query-decomposition/tasks.md (Task 6)
 */

namespace ClicShopping\AI\Dashboard;

use ClicShopping\OM\Registry;

/**
 * DecompositionStatsProvider
 * 
 * Provides decomposition performance statistics for dashboard visualization.
 */
class DecompositionStatsProvider
{
    /**
     * Get decomposition statistics
     * 
     * @param int $days Number of days to analyze (default: 7)
     * @return array Decomposition statistics
     */
    public static function getStats(int $days = 7): array
    {
        try {
            $db = Registry::get('Db');
            
            // Get overall statistics
            $sql = "SELECT 
                COUNT(*) as total_decompositions,
                AVG(response_time_ms) as avg_time_ms,
                MIN(response_time_ms) as min_time_ms,
                MAX(response_time_ms) as max_time_ms,
                SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits,
                SUM(CASE WHEN error_occurred = 1 THEN 1 ELSE 0 END) as errors,
                SUM(CASE WHEN response_time_ms > 500 THEN 1 ELSE 0 END) as slow_operations
            FROM :table_rag_statistics
            WHERE agent_type = 'hybrid_decomposition'
            AND date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)";
            
            $sql = $db->prepare($sql);
            $sql->bindInt(':days', $days);
            $sql->execute();
            
            $stats = $sql->fetch();
            
            if (!$stats || $stats['total_decompositions'] == 0) {
                return [
                    'total_decompositions' => 0,
                    'avg_time_ms' => 0,
                    'min_time_ms' => 0,
                    'max_time_ms' => 0,
                    'cache_hit_rate' => 0,
                    'error_rate' => 0,
                    'slow_operation_rate' => 0,
                    'period_days' => $days,
                    'daily_breakdown' => []
                ];
            }
            
            $total = (int)$stats['total_decompositions'];
            
            // Get daily breakdown
            $dailyBreakdown = self::getDailyBreakdown($days);
            
            return [
                'total_decompositions' => $total,
                'avg_time_ms' => round((float)$stats['avg_time_ms'], 2),
                'min_time_ms' => (int)$stats['min_time_ms'],
                'max_time_ms' => (int)$stats['max_time_ms'],
                'cache_hit_rate' => round(((int)$stats['cache_hits'] / $total) * 100, 2),
                'error_rate' => round(((int)$stats['errors'] / $total) * 100, 2),
                'slow_operation_rate' => round(((int)$stats['slow_operations'] / $total) * 100, 2),
                'period_days' => $days,
                'daily_breakdown' => $dailyBreakdown
            ];
            
        } catch (\Exception $e) {
            error_log("[DecompositionStatsProvider] Error: " . $e->getMessage());
            
            return [
                'error' => $e->getMessage(),
                'total_decompositions' => 0,
                'period_days' => $days
            ];
        }
    }
    
    /**
     * Get daily breakdown of decomposition metrics
     * 
     * @param int $days Number of days
     * @return array Daily breakdown
     */
    private static function getDailyBreakdown(int $days): array
    {
        try {
            $db = Registry::get('Db');
            
            $sql = "SELECT 
                DATE(date_added) as date,
                COUNT(*) as count,
                AVG(response_time_ms) as avg_time_ms,
                SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits,
                SUM(CASE WHEN error_occurred = 1 THEN 1 ELSE 0 END) as errors
            FROM :table_rag_statistics
            WHERE agent_type = 'hybrid_decomposition'
            AND date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY DATE(date_added)
            ORDER BY date DESC";
            
            $sql = $db->prepare($sql);
            $sql->bindInt(':days', $days);
            $sql->execute();
            
            $breakdown = [];
            while ($row = $sql->fetch()) {
                $count = (int)$row['count'];
                $breakdown[] = [
                    'date' => $row['date'],
                    'count' => $count,
                    'avg_time_ms' => round((float)$row['avg_time_ms'], 2),
                    'cache_hit_rate' => $count > 0 ? round(((int)$row['cache_hits'] / $count) * 100, 2) : 0,
                    'error_rate' => $count > 0 ? round(((int)$row['errors'] / $count) * 100, 2) : 0
                ];
            }
            
            return $breakdown;
            
        } catch (\Exception $e) {
            error_log("[DecompositionStatsProvider] Daily breakdown error: " . $e->getMessage());
            return [];
        }
    }
}
