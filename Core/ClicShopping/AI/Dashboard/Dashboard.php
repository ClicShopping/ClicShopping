<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Dashboard;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Infrastructure\Monitoring\MonitoringAgent;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\Dashboard\DecompositionStatsProvider;
use ClicShopping\AI\Infrastructure\Metrics\ColdCacheMetricsCollector;

/**
 * Dashboard Class
 * 
 * All database queries now use DoctrineOrm instead of PDO
 */

class Dashboard
{
  private $statsCollector;
  private ?MonitoringAgent $monitoringAgent = null;
  private string $prefix;

  public function __construct()
  {
    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $this->statsCollector = new DashboardStatsCollector();

    try {
      $this->monitoringAgent = new MonitoringAgent();
    } catch (\Exception $e) {
      error_log('Dashboard: unable to initialize MonitoringAgent - ' . $e->getMessage());
      $this->monitoringAgent = null;
    }
  }

  /**
   * Get all dashboard data in one call
   * @return array Complete dashboard data
   */
  public function getAllData(int $periodDays = 7): array
  {
    return [
      'health_report' => $this->getHealthReport(),
      'system_report' => $this->getSystemReport(),
      'global_stats' => $this->getGlobalStats(),
      'feedback_stats' => $this->getFeedbackStats($periodDays),
      'token_stats' => $this->getTokenStats($periodDays),
      'source_stats' => $this->getSourceStats($periodDays),
      'advanced_stats' => $this->statsCollector->collectAllStats($periodDays),
      'alert_stats' => $this->getAlertStats(),
      'websearch_stats' => $this->getWebSearchStats($periodDays),
      'cold_cache_metrics' => $this->getColdCacheMetrics($periodDays),
      'decomposition_stats' => $this->getDecompositionStats($periodDays)
    ];
  }

  /**
   * Calculate health report from rag_interactions and rag_statistics
   */
  public function getHealthReport(): array
  {
    $monitoringReport = null;

    if ($this->monitoringAgent !== null) {
      try {
        $monitoringReport = $this->monitoringAgent->getHealthReport();
      } catch (\Exception $e) {
        error_log('Dashboard: MonitoringAgent health report failed - ' . $e->getMessage());
      }
    }

    try {
      // 🔧 MIGRATED TO DOCTRINEORM
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      // Total requests
      $totalResult = DoctrineOrm::select("SELECT COUNT(*) as count FROM {$prefix}rag_interactions");
      $totalRequests = $totalResult[0]['count'] ?? 0;

      // Error rate
      $errorResult = DoctrineOrm::select("
        SELECT COUNT(*) as count 
        FROM {$prefix}rag_statistics 
        WHERE error_occurred = 1
      ");
      $totalErrors = $errorResult[0]['count'] ?? 0;
      $errorRate = $totalRequests > 0 ? $totalErrors / $totalRequests : 0;

      // Average response time
      $avgTimeResult = DoctrineOrm::select("
        SELECT AVG(response_time_ms) as avg_time 
        FROM {$prefix}rag_statistics 
        WHERE response_time_ms IS NOT NULL
      ");
      $avgResponseTime = ($avgTimeResult[0]['avg_time'] ?? 0) / 1000;

      // Total tokens and cost
      $tokensResult = DoctrineOrm::select("
        SELECT 
          SUM(tokens_total) as total_tokens,
          SUM(api_cost_usd) as total_cost
        FROM {$prefix}rag_statistics
      ");
      $totalTokens = $tokensResult[0]['total_tokens'] ?? 0;
      $totalCost = $tokensResult[0]['total_cost'] ?? 0;

      // Memory usage
      $memoryUsage = memory_get_usage(true);
      $memoryLimit = ini_get('memory_limit');
      $memoryLimitBytes = $memoryLimit === '-1' ? PHP_INT_MAX : (int)$memoryLimit * 1024 * 1024;
      $memoryPercentage = round(($memoryUsage / $memoryLimitBytes) * 100, 2);

      // Build component_health from rag_statistics
      $componentHealthResults = DoctrineOrm::select("
        SELECT 
          agent_type,
          COUNT(*) as total,
          SUM(CASE WHEN error_occurred = 0 THEN 1 ELSE 0 END) as success
        FROM {$prefix}rag_statistics
        WHERE agent_type IS NOT NULL
        GROUP BY agent_type
      ");
      
      $componentHealth = [];
      foreach ($componentHealthResults as $row) {
        $agentType = $row['agent_type'];
        $total = (int)$row['total'];
        $success = (int)$row['success'];
        $successRate = $total > 0 ? round(($success / $total) * 100, 1) : 0;
        
        $componentName = str_replace('_agent', '', $agentType);
        $componentName = ucfirst(str_replace('_', ' ', $componentName));
        
        $status = 'healthy';
        $issues = [];
        if ($successRate < 70) {
          $status = 'critical';
          $issues[] = "Taux de succès faible: {$successRate}%";
        } elseif ($successRate < 90) {
          $status = 'degraded';
          $issues[] = "Taux de succès sous-optimal: {$successRate}%";
        }
        
        $componentHealth[] = [
          'name' => $componentName,
          'status' => $status,
          'issues' => $issues
        ];
      }

      $report = [
        'overall_health' => [
          'status' => $errorRate < 0.1 ? 'healthy' : ($errorRate < 0.3 ? 'warning' : 'critical'),
          'score' => round((1 - $errorRate) * 100),
          'issues' => $errorRate > 0.1 ? ["Taux d'erreur élevé: " . round($errorRate * 100, 2) . "%"] : []
        ],
        'system_metrics' => [
          'total_requests' => $totalRequests,
          'error_rate' => $errorRate,
          'total_errors' => $totalErrors,
          'avg_response_time' => $avgResponseTime,
          'memory_usage' => [
            'percentage' => $memoryPercentage,
            'limit' => $memoryLimitBytes,
            'peak' => memory_get_peak_usage(true)
          ],
          'total_api_calls' => $totalRequests,
          'total_api_cost' => $totalCost,
          'total_tokens' => $totalTokens,
          'uptime_seconds' => 0
        ],
        'component_health' => $componentHealth,
        'recommendations' => [],
        'active_alerts' => [],
        'trends' => []
      ];

      if ($monitoringReport !== null) {
        if (!empty($monitoringReport['component_health'])) {
          $report['component_health'] = $monitoringReport['component_health'];
        }

        $report['recommendations'] = $monitoringReport['recommendations'] ?? $report['recommendations'];
        $report['active_alerts'] = $monitoringReport['active_alerts'] ?? $report['active_alerts'];
        $report['trends'] = $monitoringReport['trends'] ?? $report['trends'];

        if (!empty($monitoringReport['system_metrics'])) {
          $dbMetricKeys = ['total_requests', 'error_rate', 'total_errors', 'avg_response_time', 'total_api_calls', 'total_api_cost', 'total_tokens', 'memory_usage'];
          foreach ($monitoringReport['system_metrics'] as $key => $value) {
            if (!in_array($key, $dbMetricKeys, true)) {
              $report['system_metrics'][$key] = $value;
            }
          }
        }
      }

      return $report;
    } catch (\Exception $e) {
      error_log("Warning: Could not calculate health metrics: " . $e->getMessage());
      $fallback = $this->getDefaultHealthReport();

      if ($monitoringReport !== null) {
        $fallback['component_health'] = $monitoringReport['component_health'] ?? $fallback['component_health'];
        $fallback['recommendations'] = $monitoringReport['recommendations'] ?? $fallback['recommendations'];
        $fallback['active_alerts'] = $monitoringReport['active_alerts'] ?? $fallback['active_alerts'];
        $fallback['trends'] = $monitoringReport['trends'] ?? $fallback['trends'];
      }

      return $fallback;
    }
  }

  /**
   * Get system report with agent success rates
   */
  public function getSystemReport(): array
  {
    try {
      // 🔧 MIGRATED TO DOCTRINEORM
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      $agentStatsResults = DoctrineOrm::select("
        SELECT 
          agent_type,
          COUNT(*) as total,
          SUM(CASE WHEN error_occurred = 0 THEN 1 ELSE 0 END) as success,
          AVG(confidence_score) as avg_confidence,
          AVG(response_quality) as avg_quality
        FROM {$prefix}rag_statistics
        WHERE agent_type IS NOT NULL
        GROUP BY agent_type
      ");

      $agentStats = [];
      foreach ($agentStatsResults as $row) {
        $agentType = $row['agent_type'];
        $total = $row['total'];
        $success = $row['success'];
        $successRate = $total > 0 ? round(($success / $total) * 100, 1) : 0;

        $agentStats[$agentType] = [
          'success_rate' => $successRate . '%',
          'avg_confidence' => $row['avg_confidence'] ?? 0,
          'avg_quality' => $row['avg_quality'] ?? 0
        ];
      }

      // Calculate cache hit rate
      $cacheResults = DoctrineOrm::select("
        SELECT 
          COUNT(*) as total,
          SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as hits,
          AVG(response_quality) as avg_quality
        FROM {$prefix}rag_statistics
      ");
      $cacheTotal = $cacheResults[0]['total'] ?? 0;
      $cacheHits = $cacheResults[0]['hits'] ?? 0;
      $cacheAvgQuality = $cacheResults[0]['avg_quality'] ?? 0;

      // Build components array with detailed metrics
      // Use component name as key for easy lookup in dashboard
      $components = [];
      foreach ($agentStatsResults as $row) {
        $agentType = $row['agent_type'];
        $total = (int)$row['total'];
        $success = (int)$row['success'];
        $successRate = $total > 0 ? round(($success / $total) * 100, 1) : 0;
        
        $componentName = str_replace('_agent', '', $agentType);
        $componentName = ucfirst(str_replace('_', ' ', $componentName));
        
        $components[$componentName] = [
          'total_calls' => $total,
          'successful_calls' => $success,
          'success_rate' => $successRate,
          'avg_execution_time' => 0, // TODO: Add response_time_ms tracking
          'avg_confidence' => round($row['avg_confidence'] ?? 0, 1),
          'avg_quality' => round($row['avg_quality'] ?? 0, 1),
          'status' => $successRate >= 90 ? 'healthy' : ($successRate >= 70 ? 'warning' : 'critical')
        ];
      }
      
      // Add cache component
      $cacheHitRate = $cacheTotal > 0 ? round(($cacheHits / $cacheTotal) * 100, 1) : 0;
      $components['Cache'] = [
        'total_calls' => $cacheTotal,
        'successful_calls' => $cacheHits,
        'success_rate' => $cacheHitRate,
        'avg_execution_time' => 0,
        'avg_confidence' => 100,
        'avg_quality' => round($cacheAvgQuality, 1),
        'status' => $cacheHitRate >= 50 ? 'healthy' : ($cacheHitRate >= 30 ? 'warning' : 'critical')
      ];

      // 🔧 ADD WEBSEARCH COMPONENT (2025-12-28)
      // WebSearch queries use intent_type='web_search' in rag_interactions
      $websearchResults = DoctrineOrm::select("
        SELECT 
          COUNT(DISTINCT i.interaction_id) as total,
          SUM(CASE WHEN s.error_occurred = 0 THEN 1 ELSE 0 END) as success,
          AVG(s.confidence_score) as avg_confidence,
          AVG(s.response_quality) as avg_quality
        FROM {$prefix}rag_interactions i
        LEFT JOIN {$prefix}rag_statistics s ON i.interaction_id = s.interaction_id
        WHERE i.intent_type = 'web_search'
      ");
      
      if (!empty($websearchResults)) {
        $wsTotal = (int)($websearchResults[0]['total'] ?? 0);
        $wsSuccess = (int)($websearchResults[0]['success'] ?? 0);
        $wsSuccessRate = $wsTotal > 0 ? round(($wsSuccess / $wsTotal) * 100, 1) : 0;
        
        if ($wsTotal > 0) {
          $components['WebSearch'] = [
            'total_calls' => $wsTotal,
            'successful_calls' => $wsSuccess,
            'success_rate' => $wsSuccessRate,
            'avg_execution_time' => 0,
            'avg_confidence' => round($websearchResults[0]['avg_confidence'] ?? 0, 1),
            'avg_quality' => round($websearchResults[0]['avg_quality'] ?? 0, 1),
            'status' => $wsSuccessRate >= 90 ? 'healthy' : ($wsSuccessRate >= 70 ? 'warning' : 'critical')
          ];
          
          // Add to agentStats for backward compatibility
          $agentStats['web_search'] = [
            'success_rate' => $wsSuccessRate . '%',
            'avg_confidence' => $websearchResults[0]['avg_confidence'] ?? 0,
            'avg_quality' => $websearchResults[0]['avg_quality'] ?? 0
          ];
        }
      }

      return [
        'analytics' => ['success_rate' => $agentStats['analytics_agent']['success_rate'] ?? '0%'],
        'semantic' => ['success_rate' => $agentStats['semantic_agent']['success_rate'] ?? '0%'],
        'hybrid' => ['success_rate' => $agentStats['hybrid_agent']['success_rate'] ?? '0%'],
        'orchestrator' => ['success_rate' => $agentStats['orchestrator']['success_rate'] ?? '0%'],
        'websearch' => ['success_rate' => $agentStats['web_search']['success_rate'] ?? '0%'],
        'cache' => ['average_quality_score' => $cacheAvgQuality / 100],
        'components' => $components
      ];
    } catch (\Exception $e) {
      error_log("Warning: Could not calculate system report: " . $e->getMessage());
      return $this->getDefaultSystemReport();
    }
  }

  /**
   * Get global statistics
   */
  public function getGlobalStats(): array
  {
    try {
      // 🔧 MIGRATED TO DOCTRINEORM
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      $globalStatsResults = DoctrineOrm::select("
        SELECT 
          COUNT(*) as total,
          SUM(CASE WHEN error_occurred = 1 THEN 1 ELSE 0 END) as errors,
          AVG(confidence_score) as avg_confidence,
          AVG(response_quality) as avg_quality,
          AVG(security_score) as avg_security
        FROM {$prefix}rag_statistics
      ");
      
      $row = $globalStatsResults[0] ?? [];
      $total = $row['total'] ?? 0;
      $errors = $row['errors'] ?? 0;

      return [
        'total' => $total,
        'errors' => $errors,
        'success' => $total - $errors,
        'avg_confidence' => round($row['avg_confidence'] ?? 0, 1),
        'avg_quality' => round($row['avg_quality'] ?? 0, 1),
        'avg_security' => round($row['avg_security'] ?? 0, 1)
      ];
    } catch (\Exception $e) {
      error_log("Warning: Could not calculate global stats: " . $e->getMessage());
      return ['total' => 0, 'errors' => 0, 'success' => 0, 'avg_confidence' => 0, 'avg_quality' => 0, 'avg_security' => 0];
    }
  }

  /**
   * Get feedback statistics
   */
  public function getFeedbackStats(int $periodDays = 7): array
  {
    try {
      // 🔧 MIGRATED TO DOCTRINEORM
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      // Count interactions
      $interactionsResults = DoctrineOrm::select("
        SELECT COUNT(*) as total_interactions
        FROM {$prefix}rag_interactions
        WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
      ", [$periodDays]);
      $totalInteractions = $interactionsResults[0]['total_interactions'] ?? 0;

      // Count feedbacks by type
      $feedbackResults = DoctrineOrm::select("
        SELECT 
          feedback_type,
          COUNT(*) as count,
          AVG(CASE WHEN JSON_VALID(feedback_data) THEN JSON_EXTRACT(feedback_data, '$.rating') END) as avg_rating
        FROM {$prefix}rag_feedback
        WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY feedback_type
      ", [$periodDays]);

      $feedbackStats = [
        'total_interactions' => $totalInteractions,
        'total_feedback' => 0,
        'positive' => 0,
        'negative' => 0,
        'neutral' => 0,
        'avg_ratings' => [],
        'feedback_ratio' => 0,
        'satisfaction_rate' => 0
      ];

      foreach ($feedbackResults as $row) {
        $type = $row['feedback_type'];
        $count = $row['count'];
        $avgRating = $row['avg_rating'] ?? null;

        $feedbackStats['total_feedback'] += $count;
        $feedbackStats[$type] = $count;
        if ($avgRating) {
          $feedbackStats['avg_ratings'][$type] = round($avgRating, 1);
        }
      }

      // Calculate ratios
      if ($totalInteractions > 0) {
        $feedbackStats['feedback_ratio'] = round(($feedbackStats['total_feedback'] / $totalInteractions) * 100, 1);
      }

      $totalFeedbackForSatisfaction = $feedbackStats['positive'] + $feedbackStats['negative'] + $feedbackStats['neutral'];
      if ($totalFeedbackForSatisfaction > 0) {
        $feedbackStats['satisfaction_rate'] = round(
          (($feedbackStats['positive'] + ($feedbackStats['neutral'] * 0.5)) / $totalFeedbackForSatisfaction) * 100,
          1
        );
      }

      if ($feedbackStats['total_feedback'] === 0) {
        $feedbackStats['performance_status'] = 'no_data';
      } else {
        $feedbackStats['performance_status'] = 'good';
        if ($feedbackStats['satisfaction_rate'] < 70) {
          $feedbackStats['performance_status'] = 'critical';
        } elseif ($feedbackStats['feedback_ratio'] < 20) {
          $feedbackStats['performance_status'] = 'warning';
        }
      }

      return $feedbackStats;
    } catch (\Exception $e) {
      error_log("Warning: Could not calculate feedback stats: " . $e->getMessage());
      return $this->getDefaultFeedbackStats();
    }
  }

  /**
   * Get token statistics
   */
  public function getTokenStats(int $periodDays = 7): array
  {
    try {
      // 🔧 MIGRATED TO DOCTRINEORM
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      $tokensResults = DoctrineOrm::select("
        SELECT 
          SUM(tokens_prompt) as input_tokens,
          SUM(tokens_completion) as output_tokens,
          SUM(tokens_total) as total_tokens,
          SUM(api_cost_usd) as total_cost,
          COUNT(*) as total_requests
        FROM {$prefix}rag_statistics
        WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
          AND tokens_total IS NOT NULL
      ", [$periodDays]);
      
      $row = $tokensResults[0] ?? [];
      
      $inputTokens = (int)($row['input_tokens'] ?? 0);
      $outputTokens = (int)($row['output_tokens'] ?? 0);
      $totalTokens = (int)($row['total_tokens'] ?? 0);
      $totalCost = (float)($row['total_cost'] ?? 0);
      $totalRequests = (int)($row['total_requests'] ?? 0);
      
      // Calculate average tokens per request
      $avgTokensPerRequest = $totalRequests > 0 ? round($totalTokens / $totalRequests, 0) : 0;

      return [
        'input_tokens' => $inputTokens,
        'output_tokens' => $outputTokens,
        'total_tokens' => $totalTokens,
        'total_cost' => $totalCost,
        'cost_estimate' => $totalCost,
        'total_requests' => $totalRequests,
        'avg_tokens_per_request' => $avgTokensPerRequest,
        'period' => $periodDays . ' derniers jours'
      ];
    } catch (\Exception $e) {
      error_log("Warning: Could not collect token stats: " . $e->getMessage());
      return [
        'input_tokens' => 0, 
        'output_tokens' => 0, 
        'total_tokens' => 0, 
        'total_cost' => 0, 
        'cost_estimate' => 0, 
        'total_requests' => 0, 
        'avg_tokens_per_request' => 0,
        'period' => $periodDays . ' derniers jours'
      ];
    }
  }

  /**
   * Get alert statistics
   */
  public function getAlertStats(): array
  {
    try {
      // 🔧 MIGRATED TO DOCTRINEORM
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      $errorStatsResults = DoctrineOrm::select("
        SELECT 
          CASE 
            WHEN security_score < 0.5 THEN 'critical'
            WHEN security_score < 0.7 THEN 'warning'
            ELSE 'info'
          END as severity,
          COUNT(*) as count
        FROM {$prefix}rag_statistics
        WHERE error_occurred = 1
        GROUP BY severity
      ");

      $alertsBySeverity = ['critical' => 0, 'warning' => 0, 'info' => 0];
      foreach ($errorStatsResults as $row) {
        $severity = $row['severity'];
        $alertsBySeverity[$severity] = $row['count'];
      }

      return ['by_severity' => $alertsBySeverity];
    } catch (\Exception $e) {
      error_log("Warning: Could not calculate alert stats: " . $e->getMessage());
      return ['by_severity' => ['critical' => 0, 'warning' => 0, 'info' => 0]];
    }
  }

  /**
   * Get source statistics (documents, LLM, web_search, etc.)
   * 
   * @param int $periodDays Number of days to analyze
   * @return array Source statistics with breakdown
   */
  public function getSourceStats(int $periodDays = 7): array
  {
    try {
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');

      // Source breakdown is grouped on the populated classification_type column
      // (hybrid / web_search / analytics / semantic …) using the columns the writer (statisticsTracker)

      $sourceResults = DoctrineOrm::select("
        SELECT
          classification_type as source,
          COUNT(*) as count,
          AVG(response_time_ms) as avg_time,
          SUM(CASE WHEN error_occurred = 0 THEN 1 ELSE 0 END) as success_count,
          SUM(CASE WHEN error_occurred = 1 THEN 1 ELSE 0 END) as error_count
        FROM {$prefix}rag_statistics
        WHERE date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND classification_type IS NOT NULL
        AND classification_type <> ''
        GROUP BY classification_type
        ORDER BY count DESC
      ", [$periodDays]);
      
      $sources = [];
      $totalQueries = 0;
      
      foreach ($sourceResults as $row) {
        $source = $row['source'] ?? 'unknown';
        $count = (int)$row['count'];
        $successCount = (int)$row['success_count'];
        $errorCount = (int)$row['error_count'];
        $avgTime = round((float)$row['avg_time'], 2);
        
        $totalQueries += $count;
        
        $sources[$source] = [
          'count' => $count,
          'success_count' => $successCount,
          'error_count' => $errorCount,
          'success_rate' => $count > 0 ? round(($successCount / $count) * 100, 1) : 0,
          'avg_response_time' => $avgTime,
          'percentage' => 0 // Will be calculated after
        ];
      }
      
      // Calculate percentages
      foreach ($sources as $source => &$data) {
        $data['percentage'] = $totalQueries > 0 ? round(($data['count'] / $totalQueries) * 100, 1) : 0;
      }
      
      return [
        'sources' => $sources,
        'total_queries' => $totalQueries,
        'period_days' => $periodDays
      ];
      
    } catch (\Exception $e) {
      error_log('Dashboard: Error getting source stats - ' . $e->getMessage());
      return [
        'sources' => [],
        'total_queries' => 0,
        'period_days' => $periodDays
      ];
    }
  }

  /**
   * Format uptime seconds to human readable
   */
  public static function formatUptime(int $seconds): string
  {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    $parts = [];
    if ($days > 0) $parts[] = "{$days}j";
    if ($hours > 0) $parts[] = "{$hours}h";
    if ($minutes > 0) $parts[] = "{$minutes}m";

    return !empty($parts) ? implode(' ', $parts) : '0m';
  }

  // Default fallback methods
  private function getDefaultHealthReport(): array
  {
    return [
      'overall_health' => ['status' => 'unknown', 'score' => 0, 'issues' => ['Erreur calcul métriques']],
      'system_metrics' => [
        'total_requests' => 0,
        'error_rate' => 0,
        'total_errors' => 0,
        'avg_response_time' => 0,
        'memory_usage' => ['percentage' => 0, 'limit' => 0, 'peak' => 0],
        'total_api_calls' => 0,
        'total_api_cost' => 0,
        'total_tokens' => 0,
        'uptime_seconds' => 0
      ],
      'component_health' => [],
      'recommendations' => [],
      'active_alerts' => [],
      'trends' => []
    ];
  }

  private function getDefaultSystemReport(): array
  {
    // Default components with placeholder data
    $defaultComponents = [
      [
        'name' => 'Analytics',
        'success_rate' => '0%',
        'avg_confidence' => 0,
        'avg_quality' => 0,
        'status' => 'unknown'
      ],
      [
        'name' => 'Semantic',
        'success_rate' => '0%',
        'avg_confidence' => 0,
        'avg_quality' => 0,
        'status' => 'unknown'
      ],
      [
        'name' => 'Hybrid',
        'success_rate' => '0%',
        'avg_confidence' => 0,
        'avg_quality' => 0,
        'status' => 'unknown'
      ],
      [
        'name' => 'Orchestrator',
        'success_rate' => '0%',
        'avg_confidence' => 0,
        'avg_quality' => 0,
        'status' => 'unknown'
      ],
      [
        'name' => 'Cache',
        'success_rate' => '0%',
        'avg_confidence' => 0,
        'avg_quality' => 0,
        'status' => 'unknown'
      ]
    ];
    
    return [
      'analytics' => ['success_rate' => '0%'],
      'semantic' => ['success_rate' => '0%'],
      'hybrid' => ['success_rate' => '0%'],
      'orchestrator' => ['success_rate' => '0%'],
      'cache' => ['average_quality_score' => 0],
      'components' => $defaultComponents
    ];
  }

  private function getDefaultFeedbackStats(): array
  {
    return [
      'total_interactions' => 0,
      'total_feedback' => 0,
      'positive' => 0,
      'negative' => 0,
      'neutral' => 0,
      'avg_ratings' => [],
      'feedback_ratio' => 0,
      'satisfaction_rate' => 0,
      'performance_status' => 'unknown'
    ];
  }

  /**
   * Get WebSearch statistics
   * 
   * @param int $periodDays Number of days to analyze
   * @return array WebSearch statistics including queries, success rate, cache performance
   */
  public function getWebSearchStats(int $periodDays = 7): array
  {
    try {
      // 🔧 MIGRATED TO DOCTRINEORM
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      
      // Get WebSearch queries from rag_interactions where intent_type = 'web_search'
      // Join with rag_statistics to get performance metrics
      // Note: rag_interactions doesn't have a 'success' column, so we determine success from rag_statistics
      $webSearchResults = DoctrineOrm::select("
        SELECT 
          COUNT(DISTINCT i.interaction_id) as total_queries,
          SUM(CASE WHEN s.error_occurred = 0 THEN 1 ELSE 0 END) as successful_queries,
          SUM(CASE WHEN s.error_occurred = 1 THEN 1 ELSE 0 END) as failed_queries,
          AVG(s.response_time_ms) as avg_response_time,
          AVG(s.confidence_score) as avg_confidence,
          AVG(s.response_quality) as avg_quality,
          SUM(s.tokens_total) as total_tokens,
          SUM(s.api_cost_usd) as total_cost,
          SUM(CASE WHEN s.cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits,
          SUM(CASE WHEN s.cache_hit = 0 THEN 1 ELSE 0 END) as cache_misses
        FROM {$prefix}rag_interactions i
        LEFT JOIN {$prefix}rag_statistics s ON i.interaction_id = s.interaction_id
        WHERE i.date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
          AND i.intent_type = 'web_search'
      ", [$periodDays]);
      
      $row = $webSearchResults[0] ?? [];
      
      $totalQueries = (int)($row['total_queries'] ?? 0);
      $successfulQueries = (int)($row['successful_queries'] ?? 0);
      $failedQueries = (int)($row['failed_queries'] ?? 0);
      $avgResponseTime = round((float)($row['avg_response_time'] ?? 0), 2);
      $avgConfidence = round((float)($row['avg_confidence'] ?? 0), 1);
      $avgQuality = round((float)($row['avg_quality'] ?? 0), 1);
      $totalTokens = (int)($row['total_tokens'] ?? 0);
      $totalCost = (float)($row['total_cost'] ?? 0);
      $cacheHits = (int)($row['cache_hits'] ?? 0);
      $cacheMisses = (int)($row['cache_misses'] ?? 0);
      
      // Calculate success rate
      $successRate = $totalQueries > 0 ? round(($successfulQueries / $totalQueries) * 100, 1) : 0;
      
      // Calculate cache hit rate
      $totalCacheRequests = $cacheHits + $cacheMisses;
      $cacheHitRate = $totalCacheRequests > 0 ? round(($cacheHits / $totalCacheRequests) * 100, 1) : 0;
      
      // Get WebSearch queries by time period (for trend analysis)
      $trendResults = DoctrineOrm::select("
        SELECT 
          DATE(i.date_added) as query_date,
          COUNT(DISTINCT i.interaction_id) as count,
          AVG(s.response_time_ms) as avg_time
        FROM {$prefix}rag_interactions i
        LEFT JOIN {$prefix}rag_statistics s ON i.interaction_id = s.interaction_id
        WHERE i.date_added >= DATE_SUB(NOW(), INTERVAL ? DAY)
          AND i.intent_type = 'web_search'
        GROUP BY DATE(i.date_added)
        ORDER BY query_date ASC
      ", [$periodDays]);
      
      $trends = [];
      foreach ($trendResults as $trendRow) {
        $trends[] = [
          'date' => $trendRow['query_date'],
          'count' => (int)$trendRow['count'],
          'avg_time' => round((float)$trendRow['avg_time'], 2)
        ];
      }
      
      return [
        'total_queries' => $totalQueries,
        'successful_queries' => $successfulQueries,
        'failed_queries' => $failedQueries,
        'success_rate' => $successRate,
        'avg_response_time' => $avgResponseTime,
        'avg_confidence' => $avgConfidence,
        'avg_quality' => $avgQuality,
        'total_tokens' => $totalTokens,
        'total_cost' => $totalCost,
        'cache_hits' => $cacheHits,
        'cache_misses' => $cacheMisses,
        'cache_hit_rate' => $cacheHitRate,
        'trends' => $trends,
        'period_days' => $periodDays,
        'status' => $successRate >= 90 ? 'healthy' : ($successRate >= 70 ? 'warning' : 'critical')
      ];
      
    } catch (\Exception $e) {
      error_log('Dashboard: Error getting WebSearch stats - ' . $e->getMessage());
      return $this->getDefaultWebSearchStats($periodDays);
    }
  }

  /**
   * Get default WebSearch statistics (fallback)
   */
  private function getDefaultWebSearchStats(int $periodDays = 7): array
  {
    return [
      'total_queries' => 0,
      'successful_queries' => 0,
      'failed_queries' => 0,
      'success_rate' => 0,
      'avg_response_time' => 0,
      'avg_confidence' => 0,
      'avg_quality' => 0,
      'total_tokens' => 0,
      'total_cost' => 0,
      'cache_hits' => 0,
      'cache_misses' => 0,
      'cache_hit_rate' => 0,
      'trends' => [],
      'period_days' => $periodDays,
      'status' => 'unknown'
    ];
  }

  /**
   * Get cold cache metrics
   * 
   * Retrieves comprehensive metrics for cold cache performance, timeout events,
   * parallel execution, and hybrid query performance.
   * 
   * @param int $periodDays Number of days to analyze
   * @return array Cold cache metrics
   */
  public function getColdCacheMetrics(int $periodDays = 7): array
  {
    try {
      $metricsCollector = new ColdCacheMetricsCollector();
      return $metricsCollector->getAllMetrics($periodDays);
    } catch (\Exception $e) {
      error_log('Dashboard: Error getting cold cache metrics - ' . $e->getMessage());
      return $this->getDefaultColdCacheMetrics($periodDays);
    }
  }

  /**
   * Get default cold cache metrics (fallback)
   */
  private function getDefaultColdCacheMetrics(int $periodDays = 7): array
  {
    return [
      'cache_state_distribution' => [
        'cold' => 0,
        'warm' => 0,
        'expired' => 0,
        'total' => 0,
        'cold_percentage' => 0,
        'warm_percentage' => 0,
        'expired_percentage' => 0
      ],
      'cold_vs_warm_performance' => [
        'cold_avg_time' => 0,
        'warm_avg_time' => 0,
        'cold_count' => 0,
        'warm_count' => 0,
        'speedup_factor' => 0,
        'time_saved_per_query' => 0
      ],
      'timeout_events' => [
        'total_timeouts' => 0,
        'cold_timeouts' => 0,
        'warm_timeouts' => 0,
        'expired_timeouts' => 0,
        'timeout_rate' => 0
      ],
      'parallel_execution' => [
        'total_parallel_queries' => 0,
        'total_time_saved' => 0,
        'avg_speedup_factor' => 0,
        'analytics' => [
          'count' => 0,
          'avg_speedup' => 0,
          'time_saved' => 0,
          'percentage_faster' => 0
        ],
        'hybrid' => [
          'count' => 0,
          'avg_speedup' => 0,
          'time_saved' => 0,
          'percentage_faster' => 0
        ]
      ],
      'hybrid_query_metrics' => [
        'total_count' => 0,
        'avg_subqueries' => 0,
        'avg_execution_time' => 0,
        'success_rate' => 0,
        'time_distribution' => [
          'under_5s' => 0,
          'between_5_15s' => 0,
          'between_15_30s' => 0,
          'over_30s' => 0
        ]
      ],
      'cache_warming' => [
        'total_warmed' => 0,
        'success_rate' => 0,
        'last_run' => null,
        'next_run' => null,
        'queries_identified' => 0,
        'frequency_threshold' => 5,
        'expiring_soon' => 0,
        'expiration_warning_days' => 3,
        'note' => 'Cache warming scheduler not yet implemented (Task 15)'
      ],
      'period_days' => $periodDays
    ];
  }

  /**
   * Get hybrid query decomposition statistics
   * 
   * Task 6: Add performance monitoring for hybrid query decomposition
   * 
   * @param int $periodDays Number of days to analyze
   * @return array Decomposition statistics
   */
  public function getDecompositionStats(int $periodDays = 7): array
  {
    try {
      return DecompositionStatsProvider::getStats($periodDays);
    } catch (\Exception $e) {
      error_log('Dashboard: Failed to get decomposition stats - ' . $e->getMessage());
      
      return [
        'total_decompositions' => 0,
        'avg_time_ms' => 0,
        'min_time_ms' => 0,
        'max_time_ms' => 0,
        'cache_hit_rate' => 0,
        'error_rate' => 0,
        'slow_operation_rate' => 0,
        'period_days' => $periodDays,
        'daily_breakdown' => [],
        'error' => $e->getMessage()
      ];
    }
  }
}
