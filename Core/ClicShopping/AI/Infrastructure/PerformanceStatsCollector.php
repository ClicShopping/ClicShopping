<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure;

use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\OM\Registry;

/**
 * PerformanceStatsCollector Class
 * Collects and analyzes performance statistics from RAG system
 * 
 * @note Migrated to DoctrineOrm: December 6, 2025
 */
class PerformanceStatsCollector
{
    private $db;
    
    public function __construct()
    {
        $this->db = Registry::get('Db');
    }
    
    /**
     * Collect all performance statistics
     * 
     * @param int $days Number of days to analyze
     * @return array Complete statistics
     */
    public function collectPerformanceStats(int $days = 7): array
    {
        return [
            'overview' => $this->getOverviewStats($days),
            'by_agent' => $this->getStatsByAgent($days),
            'by_classification' => $this->getStatsByClassification($days),
            'response_time_distribution' => $this->getResponseTimeDistribution($days),
            'errors' => $this->getErrorStats($days),
            'quality' => $this->getQualityStats($days),
            'cache' => $this->getCacheStats($days),
            'trends' => $this->getTrends($days)
        ];
    }
    
    /**
     * Get overview statistics
     * 
     * @param int $days Number of days
     * @return array Overview statistics
     */
    private function getOverviewStats(int $days): array
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_queries,
                    AVG(response_time_ms) as avg_response_time,
                    MIN(response_time_ms) as min_response_time,
                    MAX(response_time_ms) as max_response_time,
                    SUM(tokens_total) as total_tokens,
                    SUM(api_cost_usd) as total_cost,
                    SUM(CASE WHEN error_occurred = 1 THEN 1 ELSE 0 END) as total_errors
                FROM :table_rag_statistics
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindInt(':days', $days);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                return $this->getEmptyOverviewStats();
            }
            
            $total_queries = $stmt->valueInt('total_queries');
            $total_cost = $stmt->valueDecimal('total_cost');
            
            return [
                'total_queries' => $total_queries,
                'avg_response_time' => round($stmt->valueDecimal('avg_response_time'), 0),
                'min_response_time' => round($stmt->valueDecimal('min_response_time'), 0),
                'max_response_time' => round($stmt->valueDecimal('max_response_time'), 0),
                'total_tokens' => $stmt->valueInt('total_tokens'),
                'total_cost' => $total_cost,
                'avg_cost_per_query' => $total_queries > 0 ? round($total_cost / $total_queries, 4) : 0,
                'total_errors' => $stmt->valueInt('total_errors'),
                'error_rate' => $total_queries > 0 ? round(($stmt->valueInt('total_errors') / $total_queries) * 100, 2) : 0
            ];
            
        } catch (\Exception $e) {
            error_log("PerformanceStatsCollector: Error in getOverviewStats: " . $e->getMessage());
            return $this->getEmptyOverviewStats();
        }
    }
    
    /**
     * Get statistics by agent type
     * 
     * @param int $days Number of days
     * @return array Agent statistics
     */
    private function getStatsByAgent(int $days): array
    {
        try {
            $sql = "
                SELECT 
                    agent_type,
                    COUNT(*) as query_count,
                    AVG(response_time_ms) as avg_time,
                    AVG(confidence_score) as avg_confidence,
                    SUM(CASE WHEN error_occurred = 0 THEN 1 ELSE 0 END) as success_count
                FROM :table_rag_statistics
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
                    AND agent_type IS NOT NULL
                GROUP BY agent_type
                ORDER BY query_count DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindInt(':days', $days);
            $stmt->execute();
            
            $agents = [];
            while ($stmt->fetch()) {
                $query_count = $stmt->valueInt('query_count');
                $success_count = $stmt->valueInt('success_count');
                
                $agents[] = [
                    'name' => $stmt->value('agent_type') ?: 'unknown',
                    'query_count' => $query_count,
                    'avg_time' => round($stmt->valueDecimal('avg_time'), 0),
                    'avg_confidence' => round($stmt->valueDecimal('avg_confidence'), 2),
                    'success_rate' => $query_count > 0 ? round(($success_count / $query_count) * 100, 1) : 0
                ];
            }
            
            return $agents;
            
        } catch (\Exception $e) {
            error_log("PerformanceStatsCollector: Error in getStatsByAgent: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get statistics by classification type
     * 
     * @param int $days Number of days
     * @return array Classification statistics
     */
    private function getStatsByClassification(int $days): array
    {
        try {
            $sql = "
                SELECT 
                    classification_type,
                    COUNT(*) as count,
                    AVG(confidence_score) as avg_confidence
                FROM :table_rag_statistics
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
                    AND classification_type IS NOT NULL
                GROUP BY classification_type
                ORDER BY count DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindInt(':days', $days);
            $stmt->execute();
            
            $classifications = [];
            $total = 0;
            
            while ($stmt->fetch()) {
                $count = $stmt->valueInt('count');
                $total += $count;
                
                $classifications[] = [
                    'type' => $stmt->value('classification_type'),
                    'count' => $count,
                    'avg_confidence' => round($stmt->valueDecimal('avg_confidence'), 2)
                ];
            }
            
            foreach ($classifications as &$class) {
                $class['percentage'] = $total > 0 ? round(($class['count'] / $total) * 100, 1) : 0;
            }
            
            return $classifications;
            
        } catch (\Exception $e) {
            error_log("PerformanceStatsCollector: Error in getStatsByClassification: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get response time distribution
     * 
     * @param int $days Number of days
     * @return array Response time distribution
     */
    private function getResponseTimeDistribution(int $days): array
    {
        try {
            $sql = "
                SELECT 
                    SUM(CASE WHEN response_time_ms < 1000 THEN 1 ELSE 0 END) as under_1s,
                    SUM(CASE WHEN response_time_ms >= 1000 AND response_time_ms < 3000 THEN 1 ELSE 0 END) as between_1_3s,
                    SUM(CASE WHEN response_time_ms >= 3000 AND response_time_ms < 5000 THEN 1 ELSE 0 END) as between_3_5s,
                    SUM(CASE WHEN response_time_ms >= 5000 THEN 1 ELSE 0 END) as over_5s
                FROM :table_rag_statistics
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
                    AND response_time_ms IS NOT NULL
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindInt(':days', $days);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                return ['under_1s' => 0, 'between_1_3s' => 0, 'between_3_5s' => 0, 'over_5s' => 0];
            }
            
            return [
                'under_1s' => $stmt->valueInt('under_1s'),
                'between_1_3s' => $stmt->valueInt('between_1_3s'),
                'between_3_5s' => $stmt->valueInt('between_3_5s'),
                'over_5s' => $stmt->valueInt('over_5s')
            ];
            
        } catch (\Exception $e) {
            error_log("PerformanceStatsCollector: Error in getResponseTimeDistribution: " . $e->getMessage());
            return ['under_1s' => 0, 'between_1_3s' => 0, 'between_3_5s' => 0, 'over_5s' => 0];
        }
    }
    
    /**
     * Get error statistics
     * 
     * @param int $days Number of days
     * @return array Error statistics
     */
    private function getErrorStats(int $days): array
    {
        try {
            $sql = "
                SELECT 
                    error_type,
                    COUNT(*) as count
                FROM :table_rag_statistics
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
                    AND error_occurred = 1
                GROUP BY error_type
                ORDER BY count DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindInt(':days', $days);
            $stmt->execute();
            
            $errors = [];
            while ($stmt->fetch()) {
                $errors[] = [
                    'type' => $stmt->value('error_type') ?: 'unknown',
                    'count' => $stmt->valueInt('count')
                ];
            }
            
            return $errors;
            
        } catch (\Exception $e) {
            error_log("PerformanceStatsCollector: Error in getErrorStats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get quality statistics
     * 
     * @param int $days Number of days
     * @return array Quality statistics
     */
    private function getQualityStats(int $days): array
    {
        try {
            $sql = "
                SELECT 
                    AVG(response_quality) as avg_quality,
                    AVG(security_score) as avg_security,
                    SUM(CASE WHEN response_quality < 50 THEN 1 ELSE 0 END) as low_quality,
                    SUM(CASE WHEN response_quality >= 80 THEN 1 ELSE 0 END) as high_quality
                FROM :table_rag_statistics
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindInt(':days', $days);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                return ['avg_quality' => 0, 'avg_security' => 0, 'low_quality' => 0, 'high_quality' => 0];
            }
            
            return [
                'avg_quality' => round($stmt->valueDecimal('avg_quality'), 1),
                'avg_security' => round($stmt->valueDecimal('avg_security'), 1),
                'low_quality' => $stmt->valueInt('low_quality'),
                'high_quality' => $stmt->valueInt('high_quality')
            ];
            
        } catch (\Exception $e) {
            error_log("PerformanceStatsCollector: Error in getQualityStats: " . $e->getMessage());
            return ['avg_quality' => 0, 'avg_security' => 0, 'low_quality' => 0, 'high_quality' => 0];
        }
    }
    
    /**
     * Get cache statistics
     * 
     * @param int $days Number of days
     * @return array Cache statistics
     */
    private function getCacheStats(int $days): array
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as hits,
                    SUM(CASE WHEN cache_hit = 0 THEN 1 ELSE 0 END) as misses
                FROM :table_rag_statistics
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
                    AND cache_hit IS NOT NULL
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindInt(':days', $days);
            $stmt->execute();
            
            if (!$stmt->fetch()) {
                return ['total' => 0, 'hits' => 0, 'misses' => 0, 'hit_rate' => 0];
            }
            
            $total = $stmt->valueInt('total');
            $hits = $stmt->valueInt('hits');
            
            return [
                'total' => $total,
                'hits' => $hits,
                'misses' => $stmt->valueInt('misses'),
                'hit_rate' => $total > 0 ? round(($hits / $total) * 100, 1) : 0
            ];
            
        } catch (\Exception $e) {
            error_log("PerformanceStatsCollector: Error in getCacheStats: " . $e->getMessage());
            return ['total' => 0, 'hits' => 0, 'misses' => 0, 'hit_rate' => 0];
        }
    }
    
    /**
     * Get trends over time
     * 
     * @param int $days Number of days
     * @return array Trend data
     */
    private function getTrends(int $days): array
    {
        try {
            $sql = "
                SELECT 
                    DATE(date_added) as date,
                    COUNT(*) as queries,
                    AVG(response_time_ms) as avg_time,
                    SUM(CASE WHEN error_occurred = 1 THEN 1 ELSE 0 END) as errors
                FROM :table_rag_statistics
                WHERE date_added >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY DATE(date_added)
                ORDER BY date ASC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindInt(':days', $days);
            $stmt->execute();
            
            $trends = [];
            while ($stmt->fetch()) {
                $trends[] = [
                    'date' => $stmt->value('date'),
                    'queries' => $stmt->valueInt('queries'),
                    'avg_time' => round($stmt->valueDecimal('avg_time'), 0),
                    'errors' => $stmt->valueInt('errors')
                ];
            }
            
            return $trends;
            
        } catch (\Exception $e) {
            error_log("PerformanceStatsCollector: Error in getTrends: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get empty overview statistics
     * 
     * @return array Empty statistics structure
     */
    private function getEmptyOverviewStats(): array
    {
        return [
            'total_queries' => 0,
            'avg_response_time' => 0,
            'min_response_time' => 0,
            'max_response_time' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'avg_cost_per_query' => 0,
            'total_errors' => 0,
            'error_rate' => 0
        ];
    }
}
