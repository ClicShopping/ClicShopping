<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Dashboard;


use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

/**
 * DashboardStatsCollector Class
 * All database queries now use DoctrineOrm instead of PDO
 */

class DashboardStatsCollector
{
    private string $prefix;
    
    public function __construct()
    {
        $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
    }
    
    /**
     * Collects all advanced statistics
     * 
     * @param int $days Number of days to analyze (default: 7)
     * @return array All statistics
     */
    public function collectAllStats(int $days = 7): array
    {
        return [
            'classification' => $this->getClassificationStats($days),
            'security' => $this->getSecurityStats($days),
            'security_monitoring' => $this->getSecurityMonitoringStats($days),
            'agents' => $this->getAgentsStats($days),
            'memory' => $this->getMemoryStats($days),
            'feedback' => $this->getFeedbackStats($days)
        ];
    }
    
    /**
     * Statistiques de Classification (Analytics vs Semantic)
     * 🔧 MIGRATED TO DOCTRINEORM
     */
    public function getClassificationStats(int $days = 7): array
    {
        try {
            // Compter les requêtes par type depuis rag_statistics
            $results = DoctrineOrm::select("
                SELECT 
                    classification_type as type,
                    COUNT(*) as count,
                    AVG(CASE WHEN confidence_score IS NOT NULL THEN confidence_score ELSE 0 END) as avg_confidence
                FROM {$this->prefix}rag_statistics 
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY classification_type
            ", [$days]);
            
            // Calculate statistics
            $analytics = ['count' => 0, 'avg_confidence' => 0];
            $semantic = ['count' => 0, 'avg_confidence' => 0];
            $total = 0;
            
            foreach ($results as $row) {
                $count = $row['count'] ?? 0;
                $type = $row['type'] ?? '';
                $avgConfidence = $row['avg_confidence'] ?? 0;
                
                $total += $count;
                if (stripos($type, 'analytics') !== false || stripos($type, 'sql') !== false) {
                    $analytics['count'] += (int)$count;
                    $analytics['avg_confidence'] = round($avgConfidence, 2);
                } else {
                    $semantic['count'] += (int)$count;
                    $semantic['avg_confidence'] = round($avgConfidence, 2);
                }
            }
            
            // Calculate percentages
            $analytics_percentage = $total > 0 ? round(($analytics['count'] / $total) * 100, 1) : 0;
            $semantic_percentage = $total > 0 ? round(($semantic['count'] / $total) * 100, 1) : 0;
            
            // Calculate overall precision
            $overall_precision = 0;
            if ($total > 0) {
                $weighted_confidence = ($analytics['count'] * $analytics['avg_confidence'] + 
                                     $semantic['count'] * $semantic['avg_confidence']) / $total;
                $overall_precision = round($weighted_confidence, 1);
            }
            
            return [
                'period_days' => $days,
                'total_requests' => $total,
                'overall_precision' => $overall_precision,
                'analytics' => array_merge($analytics, ['percentage' => $analytics_percentage]),
                'semantic' => array_merge($semantic, ['percentage' => $semantic_percentage]),
                'distribution' => [
                    'analytics' => $analytics_percentage,
                    'semantic' => $semantic_percentage
                ]
            ];
            
        } catch (\Exception $e) {
            error_log("DashboardStatsCollector: Error getting classification stats: " . $e->getMessage());
            return $this->getEmptyClassificationStats($days);
        }
    }
    
    private function getEmptyClassificationStats(int $days): array
    {
        return [
            'period_days' => $days,
            'total_requests' => 0,
            'overall_precision' => 0,
            'analytics' => ['count' => 0, 'avg_confidence' => 0, 'percentage' => 0],
            'semantic' => ['count' => 0, 'avg_confidence' => 0, 'percentage' => 0],
            'distribution' => ['analytics' => 0, 'semantic' => 0]
        ];
    }
    
    /**
     * Statistiques de Sécurité (LLM Guardrails)
     * 🔧 MIGRATED TO DOCTRINEORM
     */
    public function getSecurityStats(int $days = 7): array
    {
        try {
            // Get security scores from interactions
            $results = DoctrineOrm::select("
                SELECT 
                    COUNT(*) as total_evaluations,
                    AVG(CASE WHEN response_quality > 0 THEN response_quality ELSE 50 END) as avg_security_score,
                    SUM(CASE WHEN response_quality < 50 THEN 1 ELSE 0 END) as low_security_count
                FROM {$this->prefix}rag_interactions 
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ", [$days]);

            if (empty($results)) {
                return $this->getEmptySecurityStats($days);
            }

            $total_evaluations = $results[0]['total_evaluations'] ?? 0;
            if ($total_evaluations == 0) {
                return $this->getEmptySecurityStats($days);
            }

            $avg_score = round($results[0]['avg_security_score'] ?? 0, 1);
            $low_security_count = $results[0]['low_security_count'] ?? 0;

            return [
                'period_days' => $days,
                'total_evaluations' => $total_evaluations,
                'avg_security_score' => $avg_score,
                'low_security_count' => $low_security_count,
                'security_status' => $this->getSecurityStatus($avg_score)
            ];

        } catch (\Exception $e) {
            error_log("DashboardStatsCollector: Error getting security stats: " . $e->getMessage());
            return $this->getEmptySecurityStats($days);
        }
    }
    
    private function getEmptySecurityStats(int $days): array
    {
        return [
            'period_days' => $days,
            'total_evaluations' => 0,
            'avg_security_score' => 0,
            'low_security_count' => 0,
            'security_status' => 'unknown'
        ];
    }
    
    private function getSecurityStatus(float $score): string
    {
        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'poor';
    }
    
    /**
     * Security Monitoring Statistics (Prompt Injection Detection)
     * Collects real-time threat metrics from rag_security_events table
     * 
     * Requirements: 8.3
     * Task: 5.2.1
     * 
     * @param int $days Number of days to analyze (default: 7)
     * @return array Security monitoring statistics
     * 
     * 🔧 MIGRATED TO DOCTRINEORM
     */
    public function getSecurityMonitoringStats(int $days = 7): array
    {
        try {
            // Get overall statistics from rag_security_events
            $overallResults = DoctrineOrm::select("
                SELECT 
                    COUNT(*) as total_events,
                    SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_count,
                    SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
                    SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_count,
                    SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium_count,
                    SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low_count,
                    AVG(threat_score) as avg_threat_score,
                    AVG(detection_time_ms) as avg_detection_time
                FROM {$this->prefix}rag_security_events 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ", [$days]);
            
            if (empty($overallResults)) {
                return $this->getEmptySecurityMonitoringStats($days);
            }
            
            $totalEvents = $overallResults[0]['total_events'] ?? 0;
            
            if ($totalEvents == 0) {
                return $this->getEmptySecurityMonitoringStats($days);
            }
            
            // Get threat type distribution
            $threatTypeResults = DoctrineOrm::select("
                SELECT 
                    threat_type,
                    COUNT(*) as count,
                    AVG(threat_score) as avg_score
                FROM {$this->prefix}rag_security_events 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND threat_type IS NOT NULL
                GROUP BY threat_type
                ORDER BY count DESC
            ", [$days]);
            
            $threatTypes = [];
            foreach ($threatTypeResults as $row) {
                $threatTypes[] = [
                    'type' => $row['threat_type'],
                    'count' => (int)($row['count'] ?? 0),
                    'avg_score' => round($row['avg_score'] ?? 0, 3)
                ];
            }
            
            // Get detection method distribution
            $detectionMethodResults = DoctrineOrm::select("
                SELECT 
                    detection_method,
                    COUNT(*) as count
                FROM {$this->prefix}rag_security_events 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY detection_method
                ORDER BY count DESC
            ", [$days]);
            
            $detectionMethods = [];
            foreach ($detectionMethodResults as $row) {
                $detectionMethods[] = [
                    'method' => $row['detection_method'],
                    'count' => (int)($row['count'] ?? 0)
                ];
            }
            
            // Get language distribution
            $languageResults = DoctrineOrm::select("
                SELECT 
                    query_language,
                    COUNT(*) as count
                FROM {$this->prefix}rag_security_events 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND query_language IS NOT NULL
                GROUP BY query_language
                ORDER BY count DESC
            ", [$days]);
            
            $languages = [];
            foreach ($languageResults as $row) {
                $languages[] = [
                    'language' => $row['query_language'],
                    'count' => (int)($row['count'] ?? 0)
                ];
            }
            
            // Calculate detection and block rates
            $detectedThreats = array_sum(array_column($threatTypes, 'count'));
            $blockedCount = (int)($overallResults[0]['blocked_count'] ?? 0);
            
            $detectionRate = $totalEvents > 0 ? round(($detectedThreats / $totalEvents) * 100, 2) : 0;
            $blockRate = $detectedThreats > 0 ? round(($blockedCount / $detectedThreats) * 100, 2) : 0;
            
            // Calculate health score (0-100)
            $healthScore = $this->calculateSecurityHealthScore(
                $detectionRate,
                $blockRate,
                (int)($overallResults[0]['critical_count'] ?? 0),
                $totalEvents
            );
            
            return [
                'period_days' => $days,
                'total_events' => $totalEvents,
                'blocked_count' => $blockedCount,
                'critical_count' => (int)($overallResults[0]['critical_count'] ?? 0),
                'high_count' => (int)($overallResults[0]['high_count'] ?? 0),
                'medium_count' => (int)($overallResults[0]['medium_count'] ?? 0),
                'low_count' => (int)($overallResults[0]['low_count'] ?? 0),
                'avg_threat_score' => round($overallResults[0]['avg_threat_score'] ?? 0, 3),
                'avg_detection_time_ms' => round($overallResults[0]['avg_detection_time'] ?? 0, 2),
                'detection_rate' => $detectionRate,
                'block_rate' => $blockRate,
                'health_score' => $healthScore,
                'health_status' => $this->getHealthStatus($healthScore),
                'threat_types' => $threatTypes,
                'detection_methods' => $detectionMethods,
                'languages' => $languages,
                'detected_threats' => $detectedThreats
            ];
            
        } catch (\Exception $e) {
            error_log("DashboardStatsCollector: Error getting security monitoring stats: " . $e->getMessage());
            return $this->getEmptySecurityMonitoringStats($days);
        }
    }
    
    // Private utility methods
    
    private function getEmptySecurityMonitoringStats(int $days): array
    {
        return [
            'period_days' => $days,
            'total_events' => 0,
            'blocked_count' => 0,
            'critical_count' => 0,
            'high_count' => 0,
            'medium_count' => 0,
            'low_count' => 0,
            'avg_threat_score' => 0,
            'avg_detection_time_ms' => 0,
            'detection_rate' => 0,
            'block_rate' => 0,
            'health_score' => 0,
            'health_status' => 'unknown',
            'threat_types' => [],
            'detection_methods' => [],
            'languages' => [],
            'detected_threats' => 0
        ];
    }
    
    private function calculateSecurityHealthScore(
        float $detectionRate,
        float $blockRate,
        int $criticalCount,
        int $totalEvents
    ): float {
        // Detection score (40% weight)
        $detectionScore = min(100, $detectionRate);

        // Block rate score (40% weight) - inverted for false positives
        $blockScore = min(100, $blockRate);

        // Critical threat penalty (20% weight)
        $criticalPenalty = $totalEvents > 0 ? ($criticalCount / $totalEvents) * 100 : 0;
        $criticalScore = max(0, 100 - ($criticalPenalty * 10));

        // Weighted average
        $healthScore = ($detectionScore * 0.4) + ($blockScore * 0.2) + ($criticalScore * 0.4);

        return round($healthScore, 2);
    }
    
    private function getHealthStatus(float $score): string
    {
        if ($score >= 90) return 'excellent';
        if ($score >= 75) return 'good';
        if ($score >= 60) return 'fair';
        return 'poor';
    }
    
    /**
     * Statistiques des CoreAI
     */
    public function getAgentsStats(int $days = 7): array
    {
        try {
            // Compter l'usage par agent depuis rag_statistics
            $results = DoctrineOrm::select("
                SELECT 
                    agent_type as agent_name,
                    COUNT(*) as usage_count,
                    AVG(CASE WHEN confidence_score IS NOT NULL THEN confidence_score ELSE 0 END) as avg_confidence
                FROM {$this->prefix}rag_statistics 
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND agent_type IS NOT NULL
                GROUP BY agent_type
                ORDER BY usage_count DESC
            ", [$days]);

            $agents = [];
            $total_usage = 0;

            foreach ($results as $row) {
                $usage_count = $row['usage_count'] ?? 0;

                $agents[] = [
                    'name' => $row['agent_name'] ?: 'unknown',
                    'usage_count' => $usage_count,
                    'avg_confidence' => round($row['avg_confidence'] ?? 0, 2),
                    'success_rate' => 85.0 // Valeur par défaut
                ];

                $total_usage += $usage_count;
            }

            // 🔧 ADD WEBSEARCH AGENT DATA (2025-12-28)
            // WebSearch queries don't have agent_type, they use intent_type='web_search'
            // We need to add them separately from rag_interactions
            $websearchResults = DoctrineOrm::select("
                SELECT 
                    COUNT(DISTINCT i.interaction_id) as usage_count,
                    AVG(s.confidence_score) as avg_confidence
                FROM {$this->prefix}rag_interactions i
                LEFT JOIN {$this->prefix}rag_statistics s ON i.interaction_id = s.interaction_id
                WHERE i.date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    AND i.intent_type = 'web_search'
            ", [$days]);

            if (!empty($websearchResults) && ($websearchResults[0]['usage_count'] ?? 0) > 0) {
                $websearch_usage = $websearchResults[0]['usage_count'] ?? 0;
                $agents[] = [
                    'name' => 'web_search',
                    'usage_count' => $websearch_usage,
                    'avg_confidence' => round($websearchResults[0]['avg_confidence'] ?? 0, 2),
                    'success_rate' => 85.0 // Valeur par défaut
                ];
                $total_usage += $websearch_usage;
            }

            // Calculate percentages
            foreach ($agents as &$agent) {
                $agent['percentage'] = $total_usage > 0 ? round(($agent['usage_count'] / $total_usage) * 100, 1) : 0;
            }

            return [
                'period_days' => $days,
                'total_usage' => $total_usage,
                'agents' => $agents,
                'most_used' => !empty($agents) ? $agents[0]['name'] : 'unknown',
                'avg_success_rate' => $this->calculateAvgSuccessRate($agents)
            ];

        } catch (\Exception $e) {
            error_log("DashboardStatsCollector: Error getting agents stats: " . $e->getMessage());
            return $this->getEmptyAgentsStats($days);
        }
    }
    
    private function calculateAvgSuccessRate(array $agents): float
    {
        if (empty($agents)) return 0;

        $totalRate = 0;
        foreach ($agents as $agent) {
            $totalRate += $agent['success_rate'];
        }

        return round($totalRate / \count($agents), 1);
    }
    
    private function getEmptyAgentsStats(int $days): array
    {
        return [
            'period_days' => $days,
            'total_usage' => 0,
            'agents' => [],
            'most_used' => 'unknown',
            'avg_success_rate' => 0
        ];
    }
    
    /**
     * Statistiques de Mémoire Conversationnelle
     * 🔧 MIGRATED TO DOCTRINEORM
     */
    public function getMemoryStats(int $days = 7): array
    {
        try {
            // Analyze conversations
            $results = DoctrineOrm::select("
                SELECT 
                    COUNT(DISTINCT session_id) as total_conversations,
                    COUNT(*) as total_interactions,
                    AVG(CASE WHEN session_id IS NOT NULL THEN 1 ELSE 0 END) * 100 as context_usage_rate
                FROM {$this->prefix}rag_interactions 
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ", [$days]);

            if (empty($results)) {
                return $this->getEmptyMemoryStats($days);
            }

            $total_conversations = $results[0]['total_conversations'] ?? 0;
            if ($total_conversations == 0) {
                return $this->getEmptyMemoryStats($days);
            }

            $total_interactions = $results[0]['total_interactions'] ?? 0;
            $context_usage_rate = round($results[0]['context_usage_rate'] ?? 0, 1);
            $avg_length = $total_interactions / max($total_conversations, 1);

            return [
                'period_days' => $days,
                'total_conversations' => $total_conversations,
                'context_usage_rate' => $context_usage_rate,
                'avg_conversation_length' => round($avg_length, 1),
                'memory_effectiveness' => 'good'
            ];

        } catch (\Exception $e) {
            error_log("DashboardStatsCollector: Error getting memory stats: " . $e->getMessage());
            return $this->getEmptyMemoryStats($days);
        }
    }
    
    private function getEmptyMemoryStats(int $days): array
    {
        return [
            'period_days' => $days,
            'total_conversations' => 0,
            'context_usage_rate' => 0,
            'avg_conversation_length' => 0,
            'memory_effectiveness' => 'unknown'
        ];
    }
    
    /**
     * Statistiques de Feedback Utilisateur
     * 🔧 MIGRATED TO DOCTRINEORM
     */
    public function getFeedbackStats(int $days = 7): array
    {
        try {
            // Analyze feedbacks
            $results = DoctrineOrm::select("
                SELECT 
                    feedback_type,
                    COUNT(*) as count
                FROM {$this->prefix}rag_feedback 
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY feedback_type
            ", [$days]);

            $positive = 0;
            $negative = 0;
            $total = 0;

            foreach ($results as $row) {
                $count = $row['count'] ?? 0;
                $feedback_type = $row['feedback_type'] ?? '';
                $total += $count;

                if (\in_array($feedback_type, ['positive', 'helpful', 'thumbs_up'], true)) {
                    $positive += $count;
                } elseif (\in_array($feedback_type, ['negative', 'unhelpful', 'thumbs_down'], true)) {
                    $negative += $count;
                }
            }

            $satisfaction_rate = $total > 0 ? round(($positive / $total) * 100, 1) : 0;

            return [
                'period_days' => $days,
                'total_feedback' => $total,
                'positive_feedback' => $positive,
                'negative_feedback' => $negative,
                'satisfaction_rate' => $satisfaction_rate,
                'satisfaction_level' => $this->getSatisfactionLevel($satisfaction_rate)
            ];

        } catch (\Exception $e) {
            error_log("DashboardStatsCollector: Error getting feedback stats: " . $e->getMessage());
            return $this->getEmptyFeedbackStats($days);
        }
    }
    
    private function getSatisfactionLevel(float $rate): string
    {
        if ($rate >= 90) return 'excellent';
        if ($rate >= 75) return 'good';
        if ($rate >= 60) return 'fair';
        return 'poor';
    }
    
    private function getEmptyFeedbackStats(int $days): array
    {
        return [
            'period_days' => $days,
            'total_feedback' => 0,
            'positive_feedback' => 0,
            'negative_feedback' => 0,
            'satisfaction_rate' => 0,
            'satisfaction_level' => 'unknown'
        ];
    }
}
