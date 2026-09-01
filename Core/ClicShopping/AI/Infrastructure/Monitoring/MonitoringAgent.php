<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Monitoring;

use ClicShopping\AI\InterfacesAI\AgentInterface;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Cache\Cache;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Infrastructure\Monitoring\SubMonitoring\MetricsExporter;
use ClicShopping\AI\Infrastructure\Monitoring\SubMonitoring\AlertManager;

/**
 * MonitoringAgent Class
 *
 * Centralized system monitoring agent that:
 * - Collects metrics from all components
 * - Aggregates statistics in real-time
 * - Detects anomalies and generates alerts
 * - Produces system health reports
 * - Tracks API costs and performance
 * - Analyzes trends over time
 */

class MonitoringAgent implements AgentInterface
{

  /** @inheritDoc */
  public function getAgentId(): string
  {
    return 'monitoring';
  }

  /** @inheritDoc */
  public function getCapabilities(): array
  {
    return ['monitoring', 'metrics_collection'];
  }

  /** @inheritDoc */
  public function getStats(): array
  {
    return [];
  }

  private SecurityLogger $logger;
  private Cache $cache;
  private bool $debug;
  private MetricsExporter $metricsExporter;
  private AlertManager $alertManager;

  // Monitored components
  private array $monitoredComponents = [];

  // Platform metrics, read from rag_interactions/rag_statistics once per request
  private ?array $platformMetrics = null;

  // Metrics per component
  private array $componentMetrics = [];

  // Metrics history (last 24h)
  private array $metricsHistory = [];

  // Configuration
  private int $historyRetention = 86400; // 24h

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->logger = new SecurityLogger();
    $this->cache = new Cache(true);
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    $this->metricsExporter = new MetricsExporter();
    $this->alertManager = new AlertManager($this->logger, $this->debug);

    // Load alert state from cache (restores persisted alerts into the AlertManager)
    $this->loadMetricsFromCache();

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "MonitoringAgent initialized",
        'info'
      );
    }
  }

  /**
   * Registers a component for monitoring
   *
   * @param string $componentName Component name
   * @param object $component Component instance
   * @param array $metricsToTrack Metrics to track
   */
  public function registerComponent(string $componentName, object $component, array $metricsToTrack = []): void
  {
    $this->monitoredComponents[$componentName] = [
      'instance' => $component,
      'metrics_to_track' => $metricsToTrack,
      'registered_at' => time(),
    ];

    // Initialize component metrics
    $this->componentMetrics[$componentName] = [
      'total_calls' => 0,
      'successful_calls' => 0,
      'failed_calls' => 0,
      'total_execution_time' => 0.0,
      'avg_execution_time' => 0.0,
      'last_execution' => null,
      'custom_metrics' => [],
    ];

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Component registered: {$componentName}",
        'info'
      );
    }
  }

  /**
   * Collects metrics from all components
   *
   * @return array Complete metrics snapshot
   */
  public function collectMetrics(): array
  {
    $snapshot = [
      'timestamp' => time(),
      'system' => $this->collectSystemMetrics(),
      'components' => [],
    ];

    // Collect metrics from each component
    foreach ($this->monitoredComponents as $name => $config) {
      $snapshot['components'][$name] = $this->collectComponentMetrics($name, $config['instance']);
    }

    // Add to current metrics
    $this->componentMetrics = array_merge(
      $this->componentMetrics,
      $snapshot['components']
    );

    // Save snapshot to history
    $this->addToHistory($snapshot);

    // Check alert thresholds
    $this->alertManager->checkAlertThresholds($snapshot, $this->estimateApiCostPerHour());

    return $snapshot;
  }

  /**
   * Collects system metrics
   * 
   * @return array System metrics
   */
  private function collectSystemMetrics(): array
  {
    $platform = $this->platformMetrics();

    return [
      'total_requests' => $platform['total_requests'],
      'total_errors' => $platform['total_errors'],
      'error_rate' => $this->calculateErrorRate(),
      'total_api_calls' => $platform['total_api_calls'],
      'total_api_cost' => $platform['total_api_cost'],
      'avg_response_time' => $platform['avg_response_time'],
      'memory_usage' => [
        'current' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => $this->getMemoryLimit(),
        'percentage' => $this->getMemoryUsagePercentage(),
      ],
      'php_version' => PHP_VERSION,
      'server_load' => $this->getServerLoad(),
    ];
  }

  /**
   * Collects metrics from a specific component
   * 
   * @param string $name Component name
   * @param object $component Component instance
   * @return array Component metrics
   */
  private function collectComponentMetrics(string $name, object $component): array
  {
    $metrics = $this->componentMetrics[$name] ?? [];

    // Méthode getStats() standardisée
    if (method_exists($component, 'getStats')) {
      $componentStats = $component->getStats();
      $metrics = array_merge($metrics, $componentStats);
    }

    // Méthodes spécifiques par type de composant
    switch (true) {
      case str_contains($name, 'Planner'):
        $metrics['type'] = 'planner';
        $metrics['total_plans'] = $componentStats['total_plans_created'] ?? 0;
        $metrics['avg_steps'] = $componentStats['avg_steps_per_plan'] ?? 0;
        break;

      case str_contains($name, 'Memory'):
        $metrics['type'] = 'memory';
        if (method_exists($component, 'getStats')) {
          $memStats = $component->getStats();
          $metrics['total_interactions'] = $memStats['total_interactions'] ?? 0;
          $metrics['memory_size'] = $memStats['total_size'] ?? 0;
        }
        break;

      case str_contains($name, 'Correction'):
        $metrics['type'] = 'correction';
        if (method_exists($component, 'getLearningStats')) {
          $learnStats = $component->getLearningStats();
          $metrics['correction_accuracy'] = $learnStats['correction_accuracy'] ?? 0;
          $metrics['learned_patterns'] = $learnStats['learned_patterns'] ?? 0;
        }
        break;

      case str_contains($name, 'WebSearch'):
        $metrics['type'] = 'web_search';
        $metrics['cache_hit_rate'] = $this->extractCacheHitRate($componentStats);
        break;
    }

    return $metrics;
  }

  /**
   * Gets a complete system health report
   *
   * @return array Health report
   */
  public function getHealthReport(): array
  {
    $snapshot = $this->collectMetrics();

    return [
      'overall_health' => $this->calculateOverallHealth($snapshot),
      'system_metrics' => $snapshot['system'],
      'component_health' => $this->analyzeComponentHealth($snapshot['components']),
      'active_alerts' => $this->getActiveAlerts(),
      'recommendations' => $this->generateRecommendations($snapshot),
      'trends' => $this->analyzeTrends(),
      'generated_at' => date('Y-m-d H:i:s'),
    ];
  }

  /**
   * Calculates overall system health (0-100)
   * 
   * @param array $snapshot Metrics snapshot
   * @return array Health score and status
   */
  private function calculateOverallHealth(array $snapshot): array
  {
    $healthScore = 100;
    $issues = [];

    $system = $snapshot['system'];

    // Facteur 1: Taux d'erreur
    $errorRate = $system['error_rate'];
    if ($errorRate > 0.15) {
      $healthScore -= 30;
      $issues[] = "High error rate: " . round($errorRate * 100, 1) . "%";
    } elseif ($errorRate > 0.10) {
      $healthScore -= 15;
      $issues[] = "Moderate error rate: " . round($errorRate * 100, 1) . "%";
    }

    // Facteur 2: Temps de réponse
    $avgTime = $system['avg_response_time'];
    if ($avgTime > 5.0) {
      $healthScore -= 20;
      $issues[] = "Slow response time: {$avgTime}s";
    } elseif ($avgTime > 3.0) {
      $healthScore -= 10;
      $issues[] = "Elevated response time: {$avgTime}s";
    }

    // Facteur 3: Utilisation mémoire
    $memPct = $system['memory_usage']['percentage'];
    if ($memPct > 90) {
      $healthScore -= 25;
      $issues[] = "Critical memory usage: {$memPct}%";
    } elseif ($memPct > 75) {
      $healthScore -= 10;
      $issues[] = "High memory usage: {$memPct}%";
    }

    // Factor 4: Active alerts
    $alertCount = count($this->alertManager->getActiveAlerts());
    if ($alertCount > 5) {
      $healthScore -= 15;
      $issues[] = "Multiple active alerts: {$alertCount}";
    } elseif ($alertCount > 0) {
      $healthScore -= 5;
    }

    $healthScore = max(0, $healthScore);

    $status = 'healthy';
    if ($healthScore < 50) {
      $status = 'critical';
    } elseif ($healthScore < 70) {
      $status = 'degraded';
    } elseif ($healthScore < 85) {
      $status = 'warning';
    }

    return [
      'score' => $healthScore,
      'status' => $status,
      'issues' => $issues,
    ];
  }

  /**
   * Analyzes health of each component
   * 
   * @param array $components Components data
   * @return array Component health analysis
   */
  private function analyzeComponentHealth(array $components): array
  {
    $health = [];

    foreach ($components as $name => $metrics) {
      $componentHealth = [
        'name' => $name,
        'status' => 'healthy',
        'issues' => [],
      ];

      // Vérifier le taux de succès
      $totalCalls = $metrics['total_calls'] ?? 0;
      $failedCalls = $metrics['failed_calls'] ?? 0;

      if ($totalCalls > 0) {
        $failureRate = $failedCalls / $totalCalls;

        if ($failureRate > 0.2) {
          $componentHealth['status'] = 'critical';
          $componentHealth['issues'][] = "High failure rate: " . round($failureRate * 100, 1) . "%";
        } elseif ($failureRate > 0.1) {
          $componentHealth['status'] = 'degraded';
          $componentHealth['issues'][] = "Elevated failure rate: " . round($failureRate * 100, 1) . "%";
        }
      }

      // Vérifier le temps d'exécution
      $avgTime = $metrics['avg_execution_time'] ?? 0;
      if ($avgTime > 3.0) {
        $componentHealth['status'] = 'warning';
        $componentHealth['issues'][] = "Slow execution: {$avgTime}s";
      }

      $health[$name] = $componentHealth;
    }

    return $health;
  }

  /** Acknowledge an active alert. Delegates to AlertManager. */
  public function acknowledgeAlert(string $alertType): bool
  {
    return $this->alertManager->acknowledgeAlert($alertType);
  }

  /** Resolve an active alert. Delegates to AlertManager. */
  public function resolveAlert(string $alertType, string $resolution = ''): bool
  {
    return $this->alertManager->resolveAlert($alertType, $resolution);
  }

  /** Escalate an active alert. Delegates to AlertManager. */
  public function escalateAlert(string $alertType): bool
  {
    return $this->alertManager->escalateAlert($alertType);
  }

  /** Active alerts. Delegates to AlertManager. */
  public function getActiveAlerts(): array
  {
    return $this->alertManager->getActiveAlerts();
  }

  /** Clear a specific alert. Delegates to AlertManager. */
  public function clearAlert(string $alertType): bool
  {
    return $this->alertManager->clearAlert($alertType);
  }

  /** Set an alert threshold. Delegates to AlertManager. */
  public function setAlertThreshold(string $metric, float $value): void
  {
    $this->alertManager->setAlertThreshold($metric, $value);
  }

  /**
   * Génère des recommandations basées sur les métriques
   */
  private function generateRecommendations(array $snapshot): array
  {
    $recommendations = [];
    $system = $snapshot['system'];

    // Recommandation: Taux d'erreur
    if ($system['error_rate'] > 0.1) {
      $recommendations[] = [
        'priority' => 'high',
        'category' => 'reliability',
        'message' => "Investigate error sources and implement correction strategies",
        'action' => 'review_error_logs',
      ];
    }

    // Recommandation: Performance
    if ($system['avg_response_time'] > 3.0) {
      $recommendations[] = [
        'priority' => 'medium',
        'category' => 'performance',
        'message' => "Optimize slow components or increase cache usage",
        'action' => 'performance_tuning',
      ];
    }

    // Recommandation: Mémoire
    if ($system['memory_usage']['percentage'] > 75) {
      $recommendations[] = [
        'priority' => 'medium',
        'category' => 'resources',
        'message' => "Consider increasing memory limit or optimizing memory usage",
        'action' => 'memory_optimization',
      ];
    }

    // Recommandation: Coûts API
    $apiCostPerDay = $this->estimateApiCostPerDay();
    if ($apiCostPerDay > 10.0) {
      $recommendations[] = [
        'priority' => 'medium',
        'category' => 'cost',
        'message' => "High API costs detected. Increase cache usage or review query optimization",
        'action' => 'cost_optimization',
      ];
    }

    return $recommendations;
  }

  /**
   * Analyse les tendances sur l'historique
   */
  private function analyzeTrends(): array
  {
    if (count($this->metricsHistory) < 2) {
      return ['insufficient_data' => true];
    }

    // Prendre les 12 dernières heures
    $recentHistory = array_slice($this->metricsHistory, -12, 12);

    $trends = [
      'error_rate' => $this->calculateTrend($recentHistory, 'error_rate'),
      'response_time' => $this->calculateTrend($recentHistory, 'avg_response_time'),
      'api_cost' => $this->calculateTrend($recentHistory, 'api_cost_rate'),
      'memory_usage' => $this->calculateTrend($recentHistory, 'memory_percentage'),
    ];

    return $trends;
  }

  /**
   * Calculates metric trend
   * 
   * @param string $metricName Metric name
   * @param int $period Period in seconds
   * @return array Trend data
   */
  private function calculateTrend(array $history, string $metric): array
  {
    $values = [];

    foreach ($history as $snapshot) {
      $value = $this->extractMetricValue($snapshot, $metric);
      if ($value !== null) {
        $values[] = $value;
      }
    }

    if (count($values) < 2) {
      return ['trend' => 'stable', 'change' => 0];
    }

    $first = $values[0];
    $last = end($values);

    $change = $last - $first;
    $percentChange = $first != 0 ? ($change / $first) * 100 : 0;

    $trend = 'stable';
    if ($percentChange > 10) {
      $trend = 'increasing';
    } elseif ($percentChange < -10) {
      $trend = 'decreasing';
    }

    return [
      'trend' => $trend,
      'change' => round($change, 2),
      'percent_change' => round($percentChange, 1),
      'current_value' => round($last, 2),
    ];
  }

  /**
   * Extrait une valeur métrique d'un snapshot
   */
  private function extractMetricValue(array $snapshot, string $metric): ?float
  {
    switch ($metric) {
      case 'error_rate':
        return $snapshot['system']['error_rate'] ?? null;

      case 'avg_response_time':
        return $snapshot['system']['avg_response_time'] ?? null;

      case 'api_cost_rate':
        return $snapshot['system']['total_api_cost'] ?? null;

      case 'memory_percentage':
        return $snapshot['system']['memory_usage']['percentage'] ?? null;

      default:
        return null;
    }
  }

  /**
   * Ajoute un snapshot à l'historique
   */
  private function addToHistory(array $snapshot): void
  {
    $this->metricsHistory[] = $snapshot;

    // Nettoyer les vieux snapshots (> 24h)
    $cutoff = time() - $this->historyRetention;
    $this->metricsHistory = array_filter(
      $this->metricsHistory,
      fn($s) => $s['timestamp'] > $cutoff
    );

    // Réindexer
    $this->metricsHistory = array_values($this->metricsHistory);
  }

  /**
   * Utilitaires
   */

  private function calculateErrorRate(): float
  {
    $platform = $this->platformMetrics();

    return $platform['total_requests'] > 0
      ? $platform['total_errors'] / $platform['total_requests']
      : 0.0;
  }

  /**
   * Reads the platform counters from the tables the dashboards read, so the thresholds judge the
   * SAME population the screens show. Numerator and denominator are both interactions.
   *
   * @return array{total_requests:int,total_errors:int,total_api_calls:int,total_api_cost:float,avg_response_time:float,api_cost_last_hour:float}
   */
  private function platformMetrics(): array
  {
    if ($this->platformMetrics !== null) {
      return $this->platformMetrics;
    }

    $prefix = CLICSHOPPING::getConfig('db_table_prefix');

    $this->platformMetrics = [
      'total_requests' => 0,
      'total_errors' => 0,
      'total_api_calls' => 0,
      'total_api_cost' => 0.0,
      'avg_response_time' => 0.0,
      'api_cost_last_hour' => 0.0,
    ];

    try {
      $rows = DoctrineOrm::select("SELECT COUNT(*) as total FROM {$prefix}rag_interactions");
      $this->platformMetrics['total_requests'] = (int)($rows[0]['total'] ?? 0);

      $rows = DoctrineOrm::select("
        SELECT COUNT(DISTINCT interaction_id) as errors,
               COUNT(*) as api_calls,
               SUM(api_cost_usd) as cost,
               AVG(response_time_ms) as avg_ms
        FROM {$prefix}rag_statistics
      ");
      $this->platformMetrics['total_api_calls'] = (int)($rows[0]['api_calls'] ?? 0);
      $this->platformMetrics['total_api_cost'] = (float)($rows[0]['cost'] ?? 0);
      $this->platformMetrics['avg_response_time'] = (float)($rows[0]['avg_ms'] ?? 0) / 1000;

      $rows = DoctrineOrm::select("
        SELECT COUNT(DISTINCT interaction_id) as errors
        FROM {$prefix}rag_statistics
        WHERE error_occurred = 1 AND interaction_id IS NOT NULL
      ");
      $this->platformMetrics['total_errors'] = (int)($rows[0]['errors'] ?? 0);

      $rows = DoctrineOrm::select("
        SELECT SUM(api_cost_usd) as cost
        FROM {$prefix}rag_statistics
        WHERE date_added >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
      ");
      $this->platformMetrics['api_cost_last_hour'] = (float)($rows[0]['cost'] ?? 0);
    } catch (\Exception $e) {
      error_log('MonitoringAgent: platform metrics unavailable - ' . $e->getMessage());
    }

    return $this->platformMetrics;
  }

  private function getMemoryLimit(): int
  {
    $limit = ini_get('memory_limit');

    if ($limit == -1) {
      return PHP_INT_MAX;
    }

    $value = (int)$limit;
    $unit = strtolower(substr($limit, -1));

    switch ($unit) {
      case 'g': $value *= 1024;
      case 'm': $value *= 1024;
      case 'k': $value *= 1024;
    }

    return $value;
  }

  /**
   * Gets memory usage percentage
   * 
   * @return float Memory usage percentage
   */
  private function getMemoryUsagePercentage(): float
  {
    $current = memory_get_usage(true);
    $limit = $this->getMemoryLimit();

    return $limit > 0 ? round(($current / $limit) * 100, 2) : 0;
  }

  /**
   * Gets server load (load average)
   * 
   * @return float Server load
   */
  private function getServerLoad(): ?array
  {
    if (function_exists('sys_getloadavg')) {
      $load = sys_getloadavg();
      return [
        '1min' => round($load[0], 2),
        '5min' => round($load[1], 2),
        '15min' => round($load[2], 2),
      ];
    }

    return null;
  }

  /**
   * Estimations de coûts API
   */
  private function estimateApiCostPerHour(): float
  {
    return $this->platformMetrics()['api_cost_last_hour'];
  }

  /**
   * Estimation des coûts API par jour
   */
  private function estimateApiCostPerDay(): float
  {
    return $this->estimateApiCostPerHour() * 24;
  }

  /**
   * Extrait le taux de cache d'un composant
   */
  private function extractCacheHitRate(array $stats): float
  {
    if (isset($stats['cache_hit_rate'])) {
      $rate = (string)$stats['cache_hit_rate'];
      return (float)str_replace('%', '', $rate) / 100;
    }

    $hits = $stats['cache_hits'] ?? 0;
    $total = $stats['total_requests'] ?? 0;

    return $total > 0 ? $hits / $total : 0;
  }

  /**
   * Persistence
   */

  private function loadMetricsFromCache(): void
  {
    $cacheKey = 'monitoring_agent_metrics';
    $cached = $this->cache->getCachedResponse($cacheKey);

    if ($cached !== null) {
      $decoded = json_decode($cached, true);
      if (is_array($decoded)) {
        $this->componentMetrics = $decoded['components'] ?? $this->componentMetrics;
        $this->metricsHistory = $decoded['history'] ?? $this->metricsHistory;
        $this->alertManager->restoreAlerts($decoded['alerts'] ?? []);
      }
    }
  }

  /**
   * Saves metrics to cache
   */
  private function saveMetricsToCache(): void
  {
    $cacheKey = 'monitoring_agent_metrics';
    $data = [
      'components' => $this->componentMetrics,
      'history' => $this->metricsHistory,
      'alerts' => $this->alertManager->getActiveAlerts(),
      'saved_at' => time(),
    ];

    $encoded = json_encode($data);
    $this->cache->cacheResponse($cacheKey, $encoded, 86400);
  }


  /**
   * Destructor - Save metrics
   */
  public function __destruct()
  {
    $this->saveMetricsToCache();
  }

  /**
   * Export / Reporting
   */
  public function exportMetrics(string $format = 'json'): string
  {
    $data = [
      'exported_at' => date('Y-m-d H:i:s'),
      'health_report' => $this->getHealthReport(),
      'system_metrics' => $this->collectSystemMetrics(),
      'component_metrics' => $this->componentMetrics,
      'metrics_history' => $this->metricsHistory,
      'active_alerts' => $this->alertManager->getActiveAlerts(),
    ];

    return $this->metricsExporter->export($data, $format);
  }

  /**
   * Render the current metrics snapshot to CSV.
   * Delegates to MetricsExporter (formatting extracted 2026-06-20).
   */
  public function exportToCsv(array $data): string
  {
    return $this->metricsExporter->exportToCsv($data);
  }

  /**
   * Render the current metrics snapshot to HTML (simple dashboard).
   * Delegates to MetricsExporter (formatting extracted 2026-06-20).
   */
  public function exportToHtml(array $data): string
  {
    return $this->metricsExporter->exportToHtml($data);
  }

  /**
   * Définit la rétention de l'historique des métriques
   */
  public function setHistoryRetention(int $seconds): void
  {
    $this->historyRetention = max(3600, $seconds);
  }

  /**
   * Gets a quick system summary
   * 
   * @return array Quick summary
   */
  public function getQuickSummary(): array
  {
    $snapshot = $this->collectMetrics();
    $health = $this->calculateOverallHealth($snapshot);

    return [
      'status' => $health['status'],
      'health_score' => $health['score'],
      'total_requests' => $this->platformMetrics()['total_requests'],
      'error_rate' => round($this->calculateErrorRate() * 100, 2) . '%',
      'avg_response_time' => round($this->platformMetrics()['avg_response_time'], 2) . 's',
      'active_alerts' => count($this->alertManager->getActiveAlerts()),
      'memory_usage' => $this->getMemoryUsagePercentage() . '%',
    ];
  }

  /**
   * Réinitialise toutes les métriques
   */
  public function resetMetrics(): void
  {
    $this->platformMetrics = null;
    $this->componentMetrics = [];
    $this->metricsHistory = [];
    $this->alertManager->clearAllAlerts();

    $this->saveMetricsToCache();

    $this->logger->logSecurityEvent(
      "All metrics reset",
      'info'
    );
  }

  /**
   * Gets detailed statistics for a component
   * 
   * @param string $componentName Component name
   * @return array|null Component statistics or null
   */
  public function getComponentStats(string $componentName): ?array
  {
    if (!isset($this->componentMetrics[$componentName])) {
      return null;
    }

    $metrics = $this->componentMetrics[$componentName];

    // Calculate additional metrics
    $totalCalls = $metrics['total_calls'] ?? 0;
    $successfulCalls = $metrics['successful_calls'] ?? 0;
    $failedCalls = $metrics['failed_calls'] ?? 0;

    $successRate = $totalCalls > 0 ? ($successfulCalls / $totalCalls) * 100 : 0;
    $failureRate = $totalCalls > 0 ? ($failedCalls / $totalCalls) * 100 : 0;

    return array_merge($metrics, [
      'success_rate' => round($successRate, 2) . '%',
      'failure_rate' => round($failureRate, 2) . '%',
      'uptime' => $totalCalls > 0 ? 'active' : 'idle',
    ]);
  }

  /**
   * Gets history for a specific metric
   * 
   * @param string $metricName Metric name
   * @param int $limit Maximum number of entries
   * @return array Metric history
   */
  public function getMetricHistory(string $metric, int $limit = 24): array
  {
    $history = [];

    foreach (array_slice($this->metricsHistory, -$limit) as $snapshot) {
      $value = $this->extractMetricValue($snapshot, $metric);

      if ($value !== null) {
        $history[] = [
          'timestamp' => $snapshot['timestamp'],
          'value' => $value,
        ];
      }
    }

    return $history;
  }

  /**
   * Déclenche une collecte manuelle de métriques
   */
  public function forceCollectMetrics(): array
  {
    $snapshot = $this->collectMetrics();
    $this->saveMetricsToCache();

    return $snapshot;
  }

  /**
   * Vérifie si le système est en bonne santé
   */
  public function isHealthy(): bool
  {
    $snapshot = $this->collectMetrics();
    $health = $this->calculateOverallHealth($snapshot);

    return in_array($health['status'], ['healthy', 'warning'], true);
  }

  /**
   * Gets API metrics
   * 
   * @return array API metrics
   */
  public function getApiMetrics(): array
  {
    $platform = $this->platformMetrics();

    return [
      'total_calls' => $platform['total_api_calls'],
      'total_cost' => round($platform['total_api_cost'], 4),
      'cost_per_call' => $platform['total_api_calls'] > 0 ? round($platform['total_api_cost'] / $platform['total_api_calls'], 4) : 0,
      'estimated_cost_per_hour' => round($this->estimateApiCostPerHour(), 4),
      'estimated_cost_per_day' => round($this->estimateApiCostPerDay(), 2),
      'estimated_cost_per_month' => round($this->estimateApiCostPerDay() * 30, 2),
    ];
  }
}