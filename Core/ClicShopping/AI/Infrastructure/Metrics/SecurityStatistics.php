<?php
/**
 * Security Statistics Class
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\AI\Infrastructure\Metrics;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

/**
 * Class SecurityStatistics
 * Provides comprehensive security statistics and reporting
 * 
 * Requirements: 8.3
 */
class SecurityStatistics
{
    private $db;
    private string $prefix;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        if (Registry::exists('Db')) {
            $this->db = Registry::get('Db');
        }
        $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
    }
    
    /**
     * Calculate detection rates for a period
     * 
     * @param string $startDate Start date (Y-m-d H:i:s)
     * @param string $endDate End date (Y-m-d H:i:s)
     * @return array Detection rate statistics
     * 
     * Requirements: 8.3
     */
    public function calculateDetectionRates(string $startDate, string $endDate): array
    {
        if (!$this->db) {
            return ['error' => 'Database connection not available'];
        }
        
        try {
            $table = ':table_rag_security_events';
            
            // Get total events
            $totalQuery = "SELECT COUNT(*) as total FROM {$table} 
                          WHERE created_at BETWEEN :start_date AND :end_date";
            $totalResult = $this->db->prepare($totalQuery);
            $totalResult->bindValue(':start_date', $startDate);
            $totalResult->bindValue(':end_date', $endDate);
            $totalResult->execute();
            $totalEvents = $totalResult->fetch()['total'] ?? 0;
            
            // Get detected threats
            $threatsQuery = "SELECT COUNT(*) as threats FROM {$table} 
                            WHERE created_at BETWEEN :start_date AND :end_date 
                            AND threat_type IS NOT NULL";
            $threatsResult = $this->db->prepare($threatsQuery);
            $threatsResult->bindValue(':start_date', $startDate);
            $threatsResult->bindValue(':end_date', $endDate);
            $threatsResult->execute();
            $detectedThreats = $threatsResult->fetch()['threats'] ?? 0;
            
            // Get blocked queries
            $blockedQuery = "SELECT COUNT(*) as blocked FROM {$table} 
                            WHERE created_at BETWEEN :start_date AND :end_date 
                            AND blocked = 1";
            $blockedResult = $this->db->prepare($blockedQuery);
            $blockedResult->bindValue(':start_date', $startDate);
            $blockedResult->bindValue(':end_date', $endDate);
            $blockedResult->execute();
            $blockedCount = $blockedResult->fetch()['blocked'] ?? 0;
            
            // Get detection by method
            $methodQuery = "SELECT detection_method, COUNT(*) as count 
                           FROM {$table} 
                           WHERE created_at BETWEEN :start_date AND :end_date 
                           AND threat_type IS NOT NULL 
                           GROUP BY detection_method";
            $methodResult = $this->db->prepare($methodQuery);
            $methodResult->bindValue(':start_date', $startDate);
            $methodResult->bindValue(':end_date', $endDate);
            $methodResult->execute();
            $detectionByMethod = $methodResult->fetchAll();
            
            // Calculate rates
            $detectionRate = $totalEvents > 0 ? round(($detectedThreats / $totalEvents) * 100, 2) : 0;
            $blockRate = $detectedThreats > 0 ? round(($blockedCount / $detectedThreats) * 100, 2) : 0;
            
            return [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'total_events' => $totalEvents,
                'detected_threats' => $detectedThreats,
                'blocked_queries' => $blockedCount,
                'detection_rate' => $detectionRate,
                'block_rate' => $blockRate,
                'detection_by_method' => $detectionByMethod,
                'calculated_at' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to calculate detection rates: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate false positive rates
     * 
     * @param string $startDate Start date (Y-m-d H:i:s)
     * @param string $endDate End date (Y-m-d H:i:s)
     * @return array False positive rate statistics
     * 
     * Requirements: 8.3
     */
    public function calculateFalsePositiveRates(string $startDate, string $endDate): array
    {
        if (!$this->db) {
            return ['error' => 'Database connection not available'];
        }
        
        try {
            $table = ':table_rag_security_events';
            
            // Get total blocked queries
            $blockedQuery = "SELECT COUNT(*) as blocked FROM {$table} 
                            WHERE created_at BETWEEN :start_date AND :end_date 
                            AND blocked = 1";
            $blockedResult = $this->db->prepare($blockedQuery);
            $blockedResult->bindValue(':start_date', $startDate);
            $blockedResult->bindValue(':end_date', $endDate);
            $blockedResult->execute();
            $totalBlocked = $blockedResult->fetch()['blocked'] ?? 0;
            
            // Get false positives (queries with low threat scores that were blocked)
            // Assuming threat_score < 0.5 indicates potential false positive
            $fpQuery = "SELECT COUNT(*) as fp FROM {$table} 
                       WHERE created_at BETWEEN :start_date AND :end_date 
                       AND blocked = 1 
                       AND threat_score < 0.5";
            $fpResult = $this->db->prepare($fpQuery);
            $fpResult->bindValue(':start_date', $startDate);
            $fpResult->bindValue(':end_date', $endDate);
            $fpResult->execute();
            $falsePositives = $fpResult->fetch()['fp'] ?? 0;
            
            // Get false positives by threat type
            $fpByTypeQuery = "SELECT threat_type, COUNT(*) as count 
                             FROM {$table} 
                             WHERE created_at BETWEEN :start_date AND :end_date 
                             AND blocked = 1 
                             AND threat_score < 0.5 
                             GROUP BY threat_type";
            $fpByTypeResult = $this->db->prepare($fpByTypeQuery);
            $fpByTypeResult->bindValue(':start_date', $startDate);
            $fpByTypeResult->bindValue(':end_date', $endDate);
            $fpByTypeResult->execute();
            $fpByType = $fpByTypeResult->fetchAll();
            
            // Calculate false positive rate
            $fpRate = $totalBlocked > 0 ? round(($falsePositives / $totalBlocked) * 100, 2) : 0;
            
            return [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'total_blocked' => $totalBlocked,
                'false_positives' => $falsePositives,
                'false_positive_rate' => $fpRate,
                'false_positives_by_type' => $fpByType,
                'calculated_at' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to calculate false positive rates: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate daily security report
     * 
     * @param string|null $date Date (Y-m-d format), defaults to today
     * @return array Daily report data
     * 
     * Requirements: 8.3
     */
    public function generateDailyReport(?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        $startDate = $date . ' 00:00:00';
        $endDate = $date . ' 23:59:59';
        
        if (!$this->db) {
            return ['error' => 'Database connection not available'];
        }
        
        try {
            $table = ':table_rag_security_events';
            
            // Get summary statistics
            $summaryQuery = "SELECT 
                                COUNT(*) as total_events,
                                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_count,
                                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
                                SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_count,
                                SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium_count,
                                SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low_count,
                                AVG(threat_score) as avg_threat_score,
                                AVG(detection_time_ms) as avg_detection_time
                            FROM {$table}
                            WHERE created_at BETWEEN :start_date AND :end_date";
            $summaryResult = $this->db->prepare($summaryQuery);
            $summaryResult->bindValue(':start_date', $startDate);
            $summaryResult->bindValue(':end_date', $endDate);
            $summaryResult->execute();
            $summary = $summaryResult->fetch();
            
            // Get threats by type
            $threatsQuery = "SELECT threat_type, COUNT(*) as count, AVG(threat_score) as avg_score 
                            FROM {$table} 
                            WHERE created_at BETWEEN :start_date AND :end_date 
                            AND threat_type IS NOT NULL 
                            GROUP BY threat_type 
                            ORDER BY count DESC";
            $threatsResult = $this->db->prepare($threatsQuery);
            $threatsResult->bindValue(':start_date', $startDate);
            $threatsResult->bindValue(':end_date', $endDate);
            $threatsResult->execute();
            $threatsByType = $threatsResult->fetchAll();
            
            // Get detection methods
            $methodsQuery = "SELECT detection_method, COUNT(*) as count 
                            FROM {$table} 
                            WHERE created_at BETWEEN :start_date AND :end_date 
                            GROUP BY detection_method 
                            ORDER BY count DESC";
            $methodsResult = $this->db->prepare($methodsQuery);
            $methodsResult->bindValue(':start_date', $startDate);
            $methodsResult->bindValue(':end_date', $endDate);
            $methodsResult->execute();
            $detectionMethods = $methodsResult->fetchAll();
            
            // Get hourly distribution
            $hourlyQuery = "SELECT HOUR(created_at) as hour, COUNT(*) as count 
                           FROM {$table} 
                           WHERE created_at BETWEEN :start_date AND :end_date 
                           GROUP BY HOUR(created_at) 
                           ORDER BY hour";
            $hourlyResult = $this->db->prepare($hourlyQuery);
            $hourlyResult->bindValue(':start_date', $startDate);
            $hourlyResult->bindValue(':end_date', $endDate);
            $hourlyResult->execute();
            $hourlyDistribution = $hourlyResult->fetchAll();
            
            return [
                'report_type' => 'daily',
                'date' => $date,
                'summary' => [
                    'total_events' => (int)($summary['total_events'] ?? 0),
                    'blocked_count' => (int)($summary['blocked_count'] ?? 0),
                    'critical_count' => (int)($summary['critical_count'] ?? 0),
                    'high_count' => (int)($summary['high_count'] ?? 0),
                    'medium_count' => (int)($summary['medium_count'] ?? 0),
                    'low_count' => (int)($summary['low_count'] ?? 0),
                    'avg_threat_score' => round((float)($summary['avg_threat_score'] ?? 0), 3),
                    'avg_detection_time_ms' => round((float)($summary['avg_detection_time'] ?? 0), 2)
                ],
                'threats_by_type' => $threatsByType,
                'detection_methods' => $detectionMethods,
                'hourly_distribution' => $hourlyDistribution,
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to generate daily report: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate weekly security report
     * 
     * @param string|null $startDate Start date (Y-m-d format), defaults to 7 days ago
     * @return array Weekly report data
     * 
     * Requirements: 8.3
     */
    public function generateWeeklyReport(?string $startDate = null): array
    {
        $endDate = date('Y-m-d');
        $startDate = $startDate ?? date('Y-m-d', strtotime('-7 days'));
        
        if (!$this->db) {
            return ['error' => 'Database connection not available'];
        }
        
        try {
            $table = ':table_rag_security_events';
            
            // Get summary statistics
            $summaryQuery = "SELECT 
                                COUNT(*) as total_events,
                                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_count,
                                SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
                                SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_count,
                                AVG(threat_score) as avg_threat_score,
                                AVG(detection_time_ms) as avg_detection_time,
                                MAX(threat_score) as max_threat_score
                            FROM {$table}
                            WHERE created_at BETWEEN :start_date AND :end_date";
            $summaryResult = $this->db->prepare($summaryQuery);
            $summaryResult->bindValue(':start_date', $startDate . ' 00:00:00');
            $summaryResult->bindValue(':end_date', $endDate . ' 23:59:59');
            $summaryResult->execute();
            $summary = $summaryResult->fetch();
            
            // Get daily breakdown
            $dailyQuery = "SELECT DATE(created_at) as date, 
                                 COUNT(*) as events,
                                 SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked
                          FROM {$table} 
                          WHERE created_at BETWEEN :start_date AND :end_date 
                          GROUP BY DATE(created_at) 
                          ORDER BY date";
            $dailyResult = $this->db->prepare($dailyQuery);
            $dailyResult->bindValue(':start_date', $startDate . ' 00:00:00');
            $dailyResult->bindValue(':end_date', $endDate . ' 23:59:59');
            $dailyResult->execute();
            $dailyBreakdown = $dailyResult->fetchAll();
            
            // Get top threat types
            $topThreatsQuery = "SELECT threat_type, COUNT(*) as count, AVG(threat_score) as avg_score 
                               FROM {$table} 
                               WHERE created_at BETWEEN :start_date AND :end_date 
                               AND threat_type IS NOT NULL 
                               GROUP BY threat_type 
                               ORDER BY count DESC 
                               LIMIT 10";
            $topThreatsResult = $this->db->prepare($topThreatsQuery);
            $topThreatsResult->bindValue(':start_date', $startDate . ' 00:00:00');
            $topThreatsResult->bindValue(':end_date', $endDate . ' 23:59:59');
            $topThreatsResult->execute();
            $topThreats = $topThreatsResult->fetchAll();
            
            // Get detection method performance
            $methodPerfQuery = "SELECT detection_method, 
                                      COUNT(*) as count,
                                      AVG(detection_time_ms) as avg_time,
                                      AVG(threat_score) as avg_score
                               FROM {$table} 
                               WHERE created_at BETWEEN :start_date AND :end_date 
                               GROUP BY detection_method";
            $methodPerfResult = $this->db->prepare($methodPerfQuery);
            $methodPerfResult->bindValue(':start_date', $startDate . ' 00:00:00');
            $methodPerfResult->bindValue(':end_date', $endDate . ' 23:59:59');
            $methodPerfResult->execute();
            $methodPerformance = $methodPerfResult->fetchAll();
            
            return [
                'report_type' => 'weekly',
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'summary' => [
                    'total_events' => (int)($summary['total_events'] ?? 0),
                    'blocked_count' => (int)($summary['blocked_count'] ?? 0),
                    'critical_count' => (int)($summary['critical_count'] ?? 0),
                    'high_count' => (int)($summary['high_count'] ?? 0),
                    'avg_threat_score' => round((float)($summary['avg_threat_score'] ?? 0), 3),
                    'max_threat_score' => round((float)($summary['max_threat_score'] ?? 0), 3),
                    'avg_detection_time_ms' => round((float)($summary['avg_detection_time'] ?? 0), 2)
                ],
                'daily_breakdown' => $dailyBreakdown,
                'top_threats' => $topThreats,
                'method_performance' => $methodPerformance,
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to generate weekly report: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate trend analysis
     * Analyzes security trends over time
     * 
     * @param int $days Number of days to analyze (default: 30)
     * @return array Trend analysis data
     * 
     * Requirements: 8.3
     */
    public function generateTrendAnalysis(int $days = 30): array
    {
        if (!$this->db) {
            return ['error' => 'Database connection not available'];
        }
        
        try {
            $table = ':table_rag_security_events';
            $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $endDate = date('Y-m-d H:i:s');
            
            // Get daily event counts
            $dailyQuery = "SELECT DATE(created_at) as date, 
                                 COUNT(*) as total_events,
                                 SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_events,
                                 AVG(threat_score) as avg_threat_score
                          FROM {$table} 
                          WHERE created_at BETWEEN :start_date AND :end_date 
                          GROUP BY DATE(created_at) 
                          ORDER BY date";
            $dailyResult = $this->db->prepare($dailyQuery);
            $dailyResult->bindValue(':start_date', $startDate);
            $dailyResult->bindValue(':end_date', $endDate);
            $dailyResult->execute();
            $dailyData = $dailyResult->fetchAll();
            
            // Calculate trends
            $eventCounts = array_column($dailyData, 'total_events');
            $blockedCounts = array_column($dailyData, 'blocked_events');
            $threatScores = array_column($dailyData, 'avg_threat_score');
            
            $eventTrend = $this->calculateTrend($eventCounts);
            $blockedTrend = $this->calculateTrend($blockedCounts);
            $threatTrend = $this->calculateTrend($threatScores);
            
            // Get threat type trends
            $threatTypeQuery = "SELECT threat_type, DATE(created_at) as date, COUNT(*) as count 
                               FROM {$table} 
                               WHERE created_at BETWEEN :start_date AND :end_date 
                               AND threat_type IS NOT NULL 
                               GROUP BY threat_type, DATE(created_at) 
                               ORDER BY threat_type, date";
            $threatTypeResult = $this->db->prepare($threatTypeQuery);
            $threatTypeResult->bindValue(':start_date', $startDate);
            $threatTypeResult->bindValue(':end_date', $endDate);
            $threatTypeResult->execute();
            $threatTypeTrends = $threatTypeResult->fetchAll();
            
            // Group by threat type
            $threatTypeData = [];
            foreach ($threatTypeTrends as $row) {
                $type = $row['threat_type'];
                if (!isset($threatTypeData[$type])) {
                    $threatTypeData[$type] = [];
                }
                $threatTypeData[$type][] = [
                    'date' => $row['date'],
                    'count' => (int)$row['count']
                ];
            }
            
            // Calculate trend for each threat type
            $threatTypeTrendAnalysis = [];
            foreach ($threatTypeData as $type => $data) {
                $counts = array_column($data, 'count');
                $threatTypeTrendAnalysis[$type] = [
                    'data' => $data,
                    'trend' => $this->calculateTrend($counts),
                    'total' => array_sum($counts),
                    'avg' => round(array_sum($counts) / count($counts), 2)
                ];
            }
            
            return [
                'analysis_type' => 'trend',
                'period_days' => $days,
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'overall_trends' => [
                    'events' => [
                        'trend' => $eventTrend,
                        'data' => $dailyData
                    ],
                    'blocked' => [
                        'trend' => $blockedTrend
                    ],
                    'threat_score' => [
                        'trend' => $threatTrend
                    ]
                ],
                'threat_type_trends' => $threatTypeTrendAnalysis,
                'insights' => $this->generateInsights($eventTrend, $blockedTrend, $threatTrend, $threatTypeTrendAnalysis),
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to generate trend analysis: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate trend from data points
     * Returns 'increasing', 'decreasing', or 'stable'
     * 
     * @param array $data Array of numeric values
     * @return string Trend direction
     */
    private function calculateTrend(array $data): string
    {
        if (empty($data) || count($data) < 2) {
            return 'stable';
        }
        
        // Simple linear regression
        $n = count($data);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        
        foreach ($data as $i => $y) {
            $x = $i + 1;
            $sumX += $x;
            $sumY += (float)$y;
            $sumXY += $x * (float)$y;
            $sumX2 += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        
        // Determine trend based on slope
        if ($slope > 0.1) {
            return 'increasing';
        } elseif ($slope < -0.1) {
            return 'decreasing';
        } else {
            return 'stable';
        }
    }
    
    /**
     * Generate insights from trend data
     * 
     * @param string $eventTrend Event trend
     * @param string $blockedTrend Blocked trend
     * @param string $threatTrend Threat score trend
     * @param array $threatTypeTrends Threat type trends
     * @return array Insights
     */
    private function generateInsights(
        string $eventTrend,
        string $blockedTrend,
        string $threatTrend,
        array $threatTypeTrends
    ): array {
        $insights = [];
        
        // Event trend insights
        if ($eventTrend === 'increasing') {
            $insights[] = [
                'type' => 'warning',
                'message' => 'Security events are increasing. Monitor for potential attack patterns.',
                'priority' => 'high'
            ];
        } elseif ($eventTrend === 'decreasing') {
            $insights[] = [
                'type' => 'positive',
                'message' => 'Security events are decreasing. System security is improving.',
                'priority' => 'low'
            ];
        }
        
        // Blocked trend insights
        if ($blockedTrend === 'increasing') {
            $insights[] = [
                'type' => 'warning',
                'message' => 'Blocked queries are increasing. Review detection thresholds.',
                'priority' => 'medium'
            ];
        }
        
        // Threat score trend insights
        if ($threatTrend === 'increasing') {
            $insights[] = [
                'type' => 'critical',
                'message' => 'Average threat scores are increasing. Attacks are becoming more sophisticated.',
                'priority' => 'critical'
            ];
        }
        
        // Threat type insights
        foreach ($threatTypeTrends as $type => $data) {
            if ($data['trend'] === 'increasing' && $data['total'] > 10) {
                $insights[] = [
                    'type' => 'warning',
                    'message' => "Threat type '{$type}' is increasing. Consider updating detection patterns.",
                    'priority' => 'medium'
                ];
            }
        }
        
        return $insights;
    }
    
    /**
     * Get comprehensive security metrics
     * Combines all statistics into a single dashboard view
     * 
     * @param int $days Number of days to analyze (default: 7)
     * @return array Comprehensive metrics
     * 
     * Requirements: 8.3
     */
    public function getComprehensiveMetrics(int $days = 7): array
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $endDate = date('Y-m-d H:i:s');
        
        return [
            'period_days' => $days,
            'detection_rates' => $this->calculateDetectionRates($startDate, $endDate),
            'false_positive_rates' => $this->calculateFalsePositiveRates($startDate, $endDate),
            'trend_analysis' => $this->generateTrendAnalysis($days),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get security health score
     * Calculates an overall security health score (0-100)
     * 
     * @param int $days Number of days to analyze (default: 7)
     * @return array Health score and breakdown
     * 
     * Requirements: 8.3
     */
    public function getSecurityHealthScore(int $days = 7): array
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $endDate = date('Y-m-d H:i:s');
        
        $detectionRates = $this->calculateDetectionRates($startDate, $endDate);
        $fpRates = $this->calculateFalsePositiveRates($startDate, $endDate);
        
        // Calculate health score components
        $detectionScore = min(100, $detectionRates['detection_rate'] ?? 0);
        $fpScore = max(0, 100 - ($fpRates['false_positive_rate'] ?? 0) * 10);
        $blockScore = min(100, $detectionRates['block_rate'] ?? 0);
        
        // Weighted average
        $healthScore = round(
            ($detectionScore * 0.4) + 
            ($fpScore * 0.4) + 
            ($blockScore * 0.2),
            2
        );
        
        // Determine health status
        $status = 'poor';
        if ($healthScore >= 90) {
            $status = 'excellent';
        } elseif ($healthScore >= 75) {
            $status = 'good';
        } elseif ($healthScore >= 60) {
            $status = 'fair';
        }
        
        return [
            'health_score' => $healthScore,
            'status' => $status,
            'components' => [
                'detection' => $detectionScore,
                'false_positive' => $fpScore,
                'blocking' => $blockScore
            ],
            'period_days' => $days,
            'calculated_at' => date('Y-m-d H:i:s')
        ];
    }
}
