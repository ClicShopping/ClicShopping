<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Monitoring;


use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Cache\Cache;

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

class MonitoringAgent
{
  private SecurityLogger $logger;
  private Cache $cache;
  private bool $debug;

  // Monitored components
  private array $monitoredComponents = [];

  // System metrics
  private array $systemMetrics = [
    'uptime_start' => 0,
    'total_requests' => 0,
    'total_errors' => 0,
    'total_api_calls' => 0,
    'total_api_cost' => 0.0,
    'avg_response_time' => 0.0,
    'memory_peak_usage' => 0,
  ];

  // Metrics per component
  private array $componentMetrics = [];

  // Alert thresholds (compliant with Test 7.3 requirements)
  private array $alertThresholds = [
    'error_rate' => 0.1,              // 10% errors (Test 7.3 requirement)
    'response_time' => 10.0,          // 10 seconds (Test 7.3 requirement)
    'api_cost_per_hour' => 1.0,       // $1 per hour
    'memory_usage' => 0.9,            // 90% memory (Test 7.3 requirement)
    'cache_hit_rate' => 0.5,          // 50% minimum
  ];

  // Active alerts
  private array $activeAlerts = [];

  // Metrics history (last 24h)
  private array $metricsHistory = [];

  // Configuration
  private int $historyRetention = 86400; // 24h
  private int $alertCooldown = 1800;     // 30 minutes

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->logger = new SecurityLogger();
    $this->cache = new Cache(true);
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    $this->systemMetrics['uptime_start'] = time();

    // Load metrics from cache
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
    $this->checkAlertThresholds($snapshot);

    return $snapshot;
  }

  /**
   * Collects system metrics
   * 
   * @return array System metrics
   */
  private function collectSystemMetrics(): array
  {
    return [
      'uptime_seconds' => time() - $this->systemMetrics['uptime_start'],
      'total_requests' => $this->systemMetrics['total_requests'],
      'total_errors' => $this->systemMetrics['total_errors'],
      'error_rate' => $this->calculateErrorRate(),
      'total_api_calls' => $this->systemMetrics['total_api_calls'],
      'total_api_cost' => $this->systemMetrics['total_api_cost'],
      'avg_response_time' => $this->systemMetrics['avg_response_time'],
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
      case strpos($name, 'Planner') !== false:
        $metrics['type'] = 'planner';
        $metrics['total_plans'] = $componentStats['total_plans_created'] ?? 0;
        $metrics['avg_steps'] = $componentStats['avg_steps_per_plan'] ?? 0;
        break;

      case strpos($name, 'Memory') !== false:
        $metrics['type'] = 'memory';
        if (method_exists($component, 'getStats')) {
          $memStats = $component->getStats();
          $metrics['total_interactions'] = $memStats['total_interactions'] ?? 0;
          $metrics['memory_size'] = $memStats['total_size'] ?? 0;
        }
        break;

      case strpos($name, 'Correction') !== false:
        $metrics['type'] = 'correction';
        if (method_exists($component, 'getLearningStats')) {
          $learnStats = $component->getLearningStats();
          $metrics['correction_accuracy'] = $learnStats['correction_accuracy'] ?? 0;
          $metrics['learned_patterns'] = $learnStats['learned_patterns'] ?? 0;
        }
        break;

      case strpos($name, 'WebSearch') !== false:
        $metrics['type'] = 'web_search';
        $metrics['cache_hit_rate'] = $this->extractCacheHitRate($componentStats);
        break;
    }

    return $metrics;
  }

  /**
   * Records an event (request, error, etc.)
   *
   * @param string $eventType Event type
   * @param array $eventData Event data
   */
  public function recordEvent(string $eventType, array $eventData): void
  {
    switch ($eventType) {
      case 'request':
        $this->systemMetrics['total_requests']++;

        if (isset($eventData['execution_time'])) {
          $this->updateAverageResponseTime($eventData['execution_time']);
        }

        if (isset($eventData['component'])) {
          $this->recordComponentCall(
            $eventData['component'],
            $eventData['success'] ?? true,
            $eventData['execution_time'] ?? 0
          );
        }
        break;

      case 'error':
        $this->systemMetrics['total_errors']++;

        $this->logger->logSecurityEvent(
          "Error recorded: " . ($eventData['message'] ?? 'Unknown error'),
          'error'
        );
        break;

      case 'api_call':
        $this->systemMetrics['total_api_calls']++;

        if (isset($eventData['cost'])) {
          $this->systemMetrics['total_api_cost'] += $eventData['cost'];
        }
        break;
    }

    // Save periodically
    if ($this->systemMetrics['total_requests'] % 10 === 0) {
      $this->saveMetricsToCache();
    }
  }

  /**
   * Records a component call
   * 
   * @param string $componentName Component name
   * @param bool $success Success status
   * @param float $executionTime Execution time
   */
  private function recordComponentCall( string $componentName,  bool $success,  float $executionTime ): void
  {
    if (!isset($this->componentMetrics[$componentName])) {
      $this->componentMetrics[$componentName] = [
        'total_calls' => 0,
        'successful_calls' => 0,
        'failed_calls' => 0,
        'total_execution_time' => 0.0,
        'avg_execution_time' => 0.0,
        'last_execution' => null,
      ];
    }

    $metrics = &$this->componentMetrics[$componentName];
    $metrics['total_calls']++;
    $metrics['last_execution'] = time();

    if ($success) {
      $metrics['successful_calls']++;
    } else {
      $metrics['failed_calls']++;
    }

    $metrics['total_execution_time'] += $executionTime;
    $metrics['avg_execution_time'] =
      $metrics['total_execution_time'] / $metrics['total_calls'];
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
    $alertCount = count($this->activeAlerts);
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

  /**
   * Checks alert thresholds
   * 
   * @param array $snapshot Metrics snapshot
   */
  private function checkAlertThresholds(array $snapshot): void
  {
    $system = $snapshot['system'];

    // Alerte: Taux d'erreur élevé
    if ($system['error_rate'] > $this->alertThresholds['error_rate']) {
      $this->triggerAlert('high_error_rate', [
        'severity' => 'high',
        'message' => "Error rate exceeded threshold: " . round($system['error_rate'] * 100, 1) . "%",
        'current_value' => $system['error_rate'],
        'threshold' => $this->alertThresholds['error_rate'],
      ]);
    }

    // Alerte: Temps de réponse élevé
    if ($system['avg_response_time'] > $this->alertThresholds['response_time']) {
      $this->triggerAlert('slow_response', [
        'severity' => 'medium',
        'message' => "Response time exceeded threshold: {$system['avg_response_time']}s",
        'current_value' => $system['avg_response_time'],
        'threshold' => $this->alertThresholds['response_time'],
      ]);
    }

    // Alerte: Utilisation mémoire critique
    $memPct = $system['memory_usage']['percentage'];
    if ($memPct > $this->alertThresholds['memory_usage'] * 100) {
      $this->triggerAlert('high_memory_usage', [
        'severity' => 'critical',
        'message' => "Memory usage critical: {$memPct}%",
        'current_value' => $memPct,
        'threshold' => $this->alertThresholds['memory_usage'] * 100,
      ]);
    }

    // Alerte: Coût API élevé
    $apiCostPerHour = $this->estimateApiCostPerHour();
    if ($apiCostPerHour > $this->alertThresholds['api_cost_per_hour']) {
      $this->triggerAlert('high_api_cost', [
        'severity' => 'medium',
        'message' => "API cost rate high: $" . round($apiCostPerHour, 2) . "/hour",
        'current_value' => $apiCostPerHour,
        'threshold' => $this->alertThresholds['api_cost_per_hour'],
      ]);
    }
  }

  /**
   * Déclenche une alerte
   */
  private function triggerAlert(string $alertType, array $alertData): void
  {
    $alertKey = $alertType;

    // Vérifier le cooldown
    if (isset($this->activeAlerts[$alertKey])) {
      $lastTriggered = $this->activeAlerts[$alertKey]['triggered_at'];
      if (time() - $lastTriggered < $this->alertCooldown) {
        return; // Cooldown actif
      }
    }

    // Créer l'alerte
    $alert = array_merge($alertData, [
      'type' => $alertType,
      'triggered_at' => time(),
      'acknowledged' => false,
    ]);

    $this->activeAlerts[$alertKey] = $alert;

    // Logger l'alerte
    $this->logger->logSecurityEvent(
      "ALERT [{$alertData['severity']}]: {$alertData['message']}",
      'warning'
    );

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Alert triggered: {$alertType}",
        'info',
        $alertData
      );
    }
  }

  /**
   * Acquitte une alerte
   */
  public function acknowledgeAlert(string $alertType): bool
  {
    if (isset($this->activeAlerts[$alertType])) {
      $this->activeAlerts[$alertType]['acknowledged'] = true;
      $this->activeAlerts[$alertType]['acknowledged_at'] = time();
      
      $this->logger->logSecurityEvent(
        "Alert acknowledged: {$alertType}",
        'info'
      );
      
      return true;
    }

    return false;
  }

  /**
   * Résout une alerte (la supprime des alertes actives)
   * 
   * @param string $alertType Type d'alerte à résoudre
   * @param string $resolution Description de la résolution
   * @return bool True si l'alerte a été résolue
   */
  public function resolveAlert(string $alertType, string $resolution = ''): bool
  {
    if (isset($this->activeAlerts[$alertType])) {
      $alert = $this->activeAlerts[$alertType];
      
      // Logger la résolution
      $this->logger->logSecurityEvent(
        "Alert resolved: {$alertType} - {$resolution}",
        'info',
        [
          'alert_type' => $alertType,
          'severity' => $alert['severity'] ?? 'unknown',
          'resolution' => $resolution,
          'duration_minutes' => round((time() - $alert['triggered_at']) / 60, 1)
        ]
      );
      
      // Remove alert from active alerts
      unset($this->activeAlerts[$alertType]);
      
      return true;
    }

    return false;
  }

  /**
   * Escalade une alerte (augmente sa sévérité et envoie une notification)
   * 
   * @param string $alertType Type d'alerte à escalader
   * @return bool True si l'alerte a été escaladée
   */
  public function escalateAlert(string $alertType): bool
  {
    if (isset($this->activeAlerts[$alertType])) {
      $alert = &$this->activeAlerts[$alertType];
      
      // Augmenter la sévérité
      $severityLevels = ['low' => 'medium', 'medium' => 'high', 'high' => 'critical'];
      $currentSeverity = $alert['severity'] ?? 'medium';
      $newSeverity = $severityLevels[$currentSeverity] ?? 'critical';
      
      $alert['severity'] = $newSeverity;
      $alert['escalated'] = true;
      $alert['escalated_at'] = time();
      
      // Logger l'escalade
      $this->logger->logSecurityEvent(
        "ALERT ESCALATED: {$alertType} from {$currentSeverity} to {$newSeverity}",
        'warning',
        [
          'alert_type' => $alertType,
          'old_severity' => $currentSeverity,
          'new_severity' => $newSeverity,
          'message' => $alert['message'] ?? 'No message'
        ]
      );
      
      // Envoyer une notification email au propriétaire du magasin
      $this->sendEscalationEmail($alertType, $alert, $currentSeverity, $newSeverity);
      
      return true;
    }

    return false;
  }

  /**
   * Envoie un email de notification d'escalade d'alerte
   * 
   * @param string $alertType Type d'alerte
   * @param array $alert Données de l'alerte
   * @param string $oldSeverity Ancienne sévérité
   * @param string $newSeverity Nouvelle sévérité
   */
  private function sendEscalationEmail(string $alertType, array $alert, string $oldSeverity, string $newSeverity): void
  {
    try {
      // Vérifier que l'email du propriétaire est configuré
      if (!defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_EMAIL') || empty(CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_EMAIL)) {
        $this->logger->logSecurityEvent(
          "Cannot send escalation email: STORE_OWNER_EMAIL_ADDRESS not configured",
          'warning'
        );
        return;
      }

      // Prepare email content
      $storeName = defined('STORE_NAME') ? STORE_NAME : 'ClicShopping';
      $subject = "🚨 ALERT ESCALATED: {$alertType} - {$newSeverity}";
      
      $message = "Alert Escalation Notification\n";
      $message .= "================================\n\n";
      $message .= "Store: {$storeName}\n";
      $message .= "Alert Type: {$alertType}\n";
      $message .= "Severity: {$oldSeverity} → {$newSeverity}\n";
      $message .= "Message: " . ($alert['message'] ?? 'No message') . "\n\n";
      
      if (isset($alert['current_value']) && isset($alert['threshold'])) {
        $message .= "Current Value: " . $alert['current_value'] . "\n";
        $message .= "Threshold: " . $alert['threshold'] . "\n\n";
      }
      
      $message .= "Triggered: " . date('Y-m-d H:i:s', $alert['triggered_at']) . "\n";
      $message .= "Escalated: " . date('Y-m-d H:i:s', $alert['escalated_at']) . "\n";
      $message .= "Duration: " . round((time() - $alert['triggered_at']) / 60, 1) . " minutes\n\n";
      
      $message .= "Action Required:\n";
      $message .= "- Review the alert in the dashboard\n";
      $message .= "- Investigate the root cause\n";
      $message .= "- Take corrective action\n";
      $message .= "- Resolve the alert once fixed\n\n";
      
      $dashboardUrl = defined('HTTP_SERVER') && defined('DIR_WS_ADMIN') 
        ? HTTP_SERVER . DIR_WS_ADMIN . 'index.php?ChatGpt&Dashboard#tab3'
        : '';
      
      if (!empty($dashboardUrl)) {
        $message .= "Dashboard: {$dashboardUrl}\n\n";
      }
      
      $message .= "This is an automated notification from the RAG Monitoring System.\n";
      
      // Envoyer l'email
      $headers = "From: " . (defined('CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_EMAIL') ? CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_EMAIL : 'noreply@clicshopping.org') . "\r\n";
      $headers .= "Reply-To: " . CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_EMAIL . "\r\n";
      $headers .= "X-Mailer: ClicShopping RAG Monitoring\r\n";
      $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
      
      $emailSent = mail(CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_EMAIL, $subject, $message, $headers);
      
      if ($emailSent) {
        $this->logger->logSecurityEvent(
          "Escalation email sent to " . CLICSHOPPING_APP_CHATGPT_RA_SECURITY_ALERT_EMAIL,
          'info'
        );
      } else {
        $this->logger->logSecurityEvent(
          "Failed to send escalation email",
          'warning'
        );
      }
      
    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error sending escalation email: " . $e->getMessage(),
        'error'
      );
    }
  }

  /**
   * Gets active alerts
   * 
   * @return array Active alerts
   */
  public function getActiveAlerts(): array
  {
    return $this->activeAlerts;
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
    $total = $this->systemMetrics['total_requests'];
    return $total > 0
      ? $this->systemMetrics['total_errors'] / $total
      : 0.0;
  }

  private function updateAverageResponseTime(float $newTime): void
  {
    $total = $this->systemMetrics['total_requests'];
    $current = $this->systemMetrics['avg_response_time'];

    $this->systemMetrics['avg_response_time'] =
      (($current * ($total - 1)) + $newTime) / $total;
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
    $uptime = time() - $this->systemMetrics['uptime_start'];
    $uptimeHours = max(1, $uptime / 3600);

    return $this->systemMetrics['total_api_cost'] / $uptimeHours;
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
        $this->systemMetrics = $decoded['system'] ?? $this->systemMetrics;
        $this->componentMetrics = $decoded['components'] ?? $this->componentMetrics;
        $this->metricsHistory = $decoded['history'] ?? $this->metricsHistory;
        $this->activeAlerts = $decoded['alerts'] ?? $this->activeAlerts;
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
      'system' => $this->systemMetrics,
      'components' => $this->componentMetrics,
      'history' => $this->metricsHistory,
      'alerts' => $this->activeAlerts,
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
      'system_metrics' => $this->systemMetrics,
      'component_metrics' => $this->componentMetrics,
      'metrics_history' => $this->metricsHistory,
      'active_alerts' => $this->activeAlerts,
    ];

    switch ($format) {
      case 'json':
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

      case 'csv':
        return $this->exportToCsv($data);

      case 'html':
        return $this->exportToHtml($data);

      default:
        return json_encode($data);
    }
  }

  /**
   * Export au format CSV
   */
  public function exportToCsv(array $data): string
  {
    $timestamp = date('Y-m-d H:i:s');
    $output = '';
    
    // Section 1: System Metrics
    $output .= "=== SYSTEM METRICS ===\n";
    $output .= "Timestamp,Metric,Value\n";
    
    $healthReport = $data['health_report'] ?? [];
    $systemMetrics = $healthReport['system_metrics'] ?? [];
    
    // Overall health
    if (isset($healthReport['overall_health'])) {
      $output .= "$timestamp,health_score," . ($healthReport['overall_health']['score'] ?? 0) . "\n";
      $output .= "$timestamp,health_status," . ($healthReport['overall_health']['status'] ?? 'unknown') . "\n";
    }
    
    // System metrics
    $output .= "$timestamp,total_requests," . ($systemMetrics['total_requests'] ?? 0) . "\n";
    $output .= "$timestamp,error_rate," . ($systemMetrics['error_rate'] ?? 0) . "\n";
    $output .= "$timestamp,avg_response_time," . ($systemMetrics['avg_response_time'] ?? 0) . "\n";
    $output .= "$timestamp,total_errors," . ($systemMetrics['total_errors'] ?? 0) . "\n";
    
    if (isset($systemMetrics['memory_usage'])) {
      $output .= "$timestamp,memory_usage_percentage," . ($systemMetrics['memory_usage']['percentage'] ?? 0) . "\n";
      $output .= "$timestamp,memory_usage_current," . ($systemMetrics['memory_usage']['current'] ?? 0) . "\n";
      $output .= "$timestamp,memory_usage_limit," . ($systemMetrics['memory_usage']['limit'] ?? 0) . "\n";
    }
    
    // Section 2: Component Health
    $output .= "\n=== COMPONENT HEALTH ===\n";
    $output .= "Timestamp,Component,Status,Total_Calls,Successful_Calls,Success_Rate,Avg_Execution_Time\n";
    
    $componentHealth = $healthReport['component_health'] ?? [];
    $systemReport = $data['system_report'] ?? [];
    $components = $systemReport['components'] ?? [];
    
    foreach ($componentHealth as $comp) {
      $name = $comp['name'] ?? 'unknown';
      $status = $comp['status'] ?? 'unknown';
      $compData = $components[$name] ?? [];
      
      $totalCalls = $compData['total_calls'] ?? 0;
      $successfulCalls = $compData['successful_calls'] ?? 0;
      $successRate = $totalCalls > 0 ? round(($successfulCalls / $totalCalls) * 100, 2) : 0;
      $avgTime = $compData['avg_execution_time'] ?? 0;
      
      $output .= "$timestamp,$name,$status,$totalCalls,$successfulCalls,$successRate,$avgTime\n";
    }
    
    // Section 3: Token Statistics
    $output .= "\n=== TOKEN STATISTICS ===\n";
    $output .= "Timestamp,Metric,Value\n";
    
    $tokenStats = $data['token_stats'] ?? [];
    $output .= "$timestamp,total_tokens," . ($tokenStats['total_tokens'] ?? 0) . "\n";
    $output .= "$timestamp,input_tokens," . ($tokenStats['input_tokens'] ?? 0) . "\n";
    $output .= "$timestamp,output_tokens," . ($tokenStats['output_tokens'] ?? 0) . "\n";
    $output .= "$timestamp,cost_estimate," . ($tokenStats['cost_estimate'] ?? 0) . "\n";
    $output .= "$timestamp,total_requests," . ($tokenStats['total_requests'] ?? 0) . "\n";
    $output .= "$timestamp,avg_tokens_per_request," . ($tokenStats['avg_tokens_per_request'] ?? 0) . "\n";
    $output .= "$timestamp,cost_per_request," . ($tokenStats['cost_per_request'] ?? 0) . "\n";
    
    // Section 4: Feedback Statistics
    $output .= "\n=== FEEDBACK STATISTICS ===\n";
    $output .= "Timestamp,Metric,Value\n";
    
    $feedbackStats = $data['feedback_stats'] ?? [];
    $output .= "$timestamp,satisfaction_rate," . ($feedbackStats['satisfaction_rate'] ?? 0) . "\n";
    $output .= "$timestamp,feedback_ratio," . ($feedbackStats['feedback_ratio'] ?? 0) . "\n";
    $output .= "$timestamp,positive_feedback," . ($feedbackStats['positive'] ?? 0) . "\n";
    $output .= "$timestamp,negative_feedback," . ($feedbackStats['negative'] ?? 0) . "\n";
    $output .= "$timestamp,total_feedback," . ($feedbackStats['total_feedback'] ?? 0) . "\n";
    $output .= "$timestamp,total_interactions," . ($feedbackStats['total_interactions'] ?? 0) . "\n";
    
    // Section 5: Source Statistics
    $output .= "\n=== SOURCE STATISTICS ===\n";
    $output .= "Timestamp,Source,Count,Percentage,Success_Rate,Avg_Response_Time\n";
    
    $sourceStats = $data['source_stats'] ?? [];
    $sources = $sourceStats['sources'] ?? [];
    
    foreach ($sources as $source => $sourceData) {
      $count = $sourceData['count'] ?? 0;
      $percentage = $sourceData['percentage'] ?? 0;
      $successRate = $sourceData['success_rate'] ?? 0;
      $avgTime = $sourceData['avg_response_time'] ?? 0;
      
      $output .= "$timestamp,$source,$count,$percentage,$successRate,$avgTime\n";
    }
    
    // Section 6: Active Alerts
    $output .= "\n=== ACTIVE ALERTS ===\n";
    $output .= "Timestamp,Alert_Type,Severity,Message,Value,Threshold\n";
    
    $activeAlerts = $healthReport['active_alerts'] ?? [];
    
    if (empty($activeAlerts)) {
      $output .= "$timestamp,none,none,No active alerts,0,0\n";
    } else {
      foreach ($activeAlerts as $alert) {
        $type = $alert['type'] ?? 'unknown';
        $severity = $alert['severity'] ?? 'unknown';
        $message = str_replace([',', "\n", "\r"], [';', ' ', ' '], $alert['message'] ?? '');
        $value = $alert['value'] ?? 0;
        $threshold = $alert['threshold'] ?? 0;
        
        $output .= "$timestamp,$type,$severity,\"$message\",$value,$threshold\n";
      }
    }
    
    // Section 7: Global Statistics
    $output .= "\n=== GLOBAL STATISTICS ===\n";
    $output .= "Timestamp,Query_Type,Count,Percentage,Avg_Response_Time,Success_Rate\n";
    
    $globalStats = $data['global_stats'] ?? [];
    $queryTypes = $globalStats['query_types'] ?? [];
    
    foreach ($queryTypes as $type => $typeData) {
      $count = $typeData['count'] ?? 0;
      $percentage = $typeData['percentage'] ?? 0;
      $avgTime = $typeData['avg_response_time'] ?? 0;
      $successRate = $typeData['success_rate'] ?? 0;
      
      $output .= "$timestamp,$type,$count,$percentage,$avgTime,$successRate\n";
    }
    
    return $output;
  }

  /**
   * Export au format HTML (dashboard simple)
   */
  public function exportToHtml(array $data): string
  {
    $health = $data['health_report'];
    $statusColor = match($health['overall_health']['status']) {
      'healthy' => '#10b981',
      'warning' => '#f59e0b',
      'degraded' => '#ef4444',
      'critical' => '#dc2626',
      default => '#6b7280',
    };

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitoring Report</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f3f4f6;
        }
        .container { 
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        h1 { 
            margin: 0;
            color: #111827;
            font-size: 28px;
        }
        .timestamp {
            color: #6b7280;
            font-size: 14px;
            margin-top: 8px;
        }
        .health-score {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
        }
        .score-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            color: white;
            background: {$statusColor};
        }
        .score-details h2 {
            margin: 0 0 8px 0;
            font-size: 20px;
            color: #374151;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            background: {$statusColor};
            color: white;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .metric-card {
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        .metric-card h3 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-value {
            font-size: 28px;
            font-weight: bold;
            color: #111827;
        }
        .metric-unit {
            font-size: 14px;
            color: #6b7280;
            margin-left: 4px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            font-size: 20px;
            color: #111827;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e5e7eb;
        }
        .alert-item {
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 6px;
            border-left: 4px solid #ef4444;
            background: #fef2f2;
        }
        .alert-item.warning {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        .alert-item.info {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        .recommendation {
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 6px;
            background: #f0fdf4;
            border-left: 4px solid #10b981;
        }
        .recommendation strong {
            color: #065f46;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        td {
            color: #6b7280;
        }
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        .trend-stable { color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 System Monitoring Report</h1>
            <div class="timestamp">Generated: {$data['exported_at']}</div>
        </div>

        <div class="health-score">
            <div class="score-circle">{$health['overall_health']['score']}</div>
            <div class="score-details">
                <h2>Overall System Health</h2>
                <span class="status-badge">{$health['overall_health']['status']}</span>
HTML;

    if (!empty($health['overall_health']['issues'])) {
      $html .= '<ul style="margin-top: 12px; color: #6b7280;">';
      foreach ($health['overall_health']['issues'] as $issue) {
        $html .= "<li>{$issue}</li>";
      }
      $html .= '</ul>';
    }

    $html .= <<<HTML
            </div>
        </div>

        <div class="grid">
            <div class="metric-card">
                <h3>Total Requests</h3>
                <div class="metric-value">{$data['system_metrics']['total_requests']}</div>
            </div>
            <div class="metric-card">
                <h3>Error Rate</h3>
                <div class="metric-value">
HTML;

    $errorRate = round($health['system_metrics']['error_rate'] * 100, 2);
    $html .= $errorRate . '<span class="metric-unit">%</span>';

    $html .= <<<HTML
                </div>
            </div>
            <div class="metric-card">
                <h3>Avg Response Time</h3>
                <div class="metric-value">
HTML;

    $avgTime = round($health['system_metrics']['avg_response_time'], 2);
    $html .= $avgTime . '<span class="metric-unit">s</span>';

    $html .= <<<HTML
                </div>
            </div>
            <div class="metric-card">
                <h3>Memory Usage</h3>
                <div class="metric-value">
HTML;

    $memPct = $health['system_metrics']['memory_usage']['percentage'];
    $html .= $memPct . '<span class="metric-unit">%</span>';

    $html .= <<<HTML
                </div>
            </div>
        </div>
HTML;

    // Alertes actives
    if (!empty($data['active_alerts'])) {
      $html .= '<div class="section"><h2>🚨 Active Alerts</h2>';
      foreach ($data['active_alerts'] as $alert) {
        $alertClass = $alert['severity'] === 'high' ? 'alert-item' : 'alert-item warning';
        $html .= "<div class=\"{$alertClass}\"><strong>{$alert['type']}</strong>: {$alert['message']}</div>";
      }
      $html .= '</div>';
    }

    // Recommandations
    if (!empty($health['recommendations'])) {
      $html .= '<div class="section"><h2>💡 Recommendations</h2>';
      foreach ($health['recommendations'] as $rec) {
        $html .= "<div class=\"recommendation\"><strong>{$rec['category']}</strong>: {$rec['message']}</div>";
      }
      $html .= '</div>';
    }

    // Santé des composants
    $html .= '<div class="section"><h2>🔧 Component Health</h2><table><thead><tr><th>Component</th><th>Status</th><th>Total Calls</th><th>Success Rate</th><th>Avg Time</th></tr></thead><tbody>';

    foreach ($health['component_health'] as $comp) {
      $statusColor = $comp['status'] === 'healthy' ? '#10b981' : '#ef4444';
      $metrics = $data['component_metrics'][$comp['name']] ?? [];
      $totalCalls = $metrics['total_calls'] ?? 0;
      $successfulCalls = $metrics['successful_calls'] ?? 0;
      $successRate = $totalCalls > 0 ? round(($successfulCalls / $totalCalls) * 100, 1) : 0;
      $avgTime = round($metrics['avg_execution_time'] ?? 0, 2);

      $html .= "<tr>";
      $html .= "<td>{$comp['name']}</td>";
      $html .= "<td><span style=\"color: {$statusColor}; font-weight: 600;\">{$comp['status']}</span></td>";
      $html .= "<td>{$totalCalls}</td>";
      $html .= "<td>{$successRate}%</td>";
      $html .= "<td>{$avgTime}s</td>";
      $html .= "</tr>";
    }

    $html .= '</tbody></table></div>';

    // Tendances
    if (!empty($health['trends']) && !isset($health['trends']['insufficient_data'])) {
      $html .= '<div class="section"><h2>📈 Trends</h2><table><thead><tr><th>Metric</th><th>Trend</th><th>Change</th><th>Current Value</th></tr></thead><tbody>';

      foreach ($health['trends'] as $metric => $trend) {
        $trendClass = match($trend['trend']) {
          'increasing' => 'trend-up',
          'decreasing' => 'trend-down',
          default => 'trend-stable',
        };
        $trendIcon = match($trend['trend']) {
          'increasing' => '↗',
          'decreasing' => '↘',
          default => '→',
        };

        $html .= "<tr>";
        $html .= "<td>" . ucfirst(str_replace('_', ' ', $metric)) . "</td>";
        $html .= "<td class=\"{$trendClass}\">{$trendIcon} {$trend['trend']}</td>";
        $html .= "<td>{$trend['percent_change']}%</td>";
        $html .= "<td>{$trend['current_value']}</td>";
        $html .= "</tr>";
      }

      $html .= '</tbody></table></div>';
    }

    $html .= <<<HTML
    </div>
</body>
</html>
HTML;

    return $html;
  }

  //*******************
  // Not used
  //*******************


  /**
   * Efface une alerte
   */
  public function clearAlert(string $alertType): bool
  {
    if (isset($this->activeAlerts[$alertType])) {
      unset($this->activeAlerts[$alertType]);

      $this->logger->logSecurityEvent(
        "Alert cleared: {$alertType}",
        'info'
      );

      return true;
    }

    return false;
  }

  /**
   *   * Définit un seuil d'alerte
   */
  public function setAlertThreshold(string $metric, float $value): void
  {
    if (isset($this->alertThresholds[$metric])) {
      $this->alertThresholds[$metric] = $value;
    }
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
      'total_requests' => $this->systemMetrics['total_requests'],
      'error_rate' => round($this->calculateErrorRate() * 100, 2) . '%',
      'avg_response_time' => round($this->systemMetrics['avg_response_time'], 2) . 's',
      'active_alerts' => count($this->activeAlerts),
      'memory_usage' => $this->getMemoryUsagePercentage() . '%',
      'uptime' => $this->formatUptime(time() - $this->systemMetrics['uptime_start']),
    ];
  }

  /**
   * Formate l'uptime en format lisible
   */
  private function formatUptime(int $seconds): string
  {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);

    $parts = [];
    if ($days > 0) $parts[] = "{$days}d";
    if ($hours > 0) $parts[] = "{$hours}h";
    if ($minutes > 0) $parts[] = "{$minutes}m";

    return !empty($parts) ? implode(' ', $parts) : '0m';
  }

  /**
   * Réinitialise toutes les métriques
   */
  public function resetMetrics(): void
  {
    $this->systemMetrics = [
      'uptime_start' => time(),
      'total_requests' => 0,
      'total_errors' => 0,
      'total_api_calls' => 0,
      'total_api_cost' => 0.0,
      'avg_response_time' => 0.0,
      'memory_peak_usage' => 0,
    ];

    $this->componentMetrics = [];
    $this->metricsHistory = [];
    $this->activeAlerts = [];

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
    return [
      'total_calls' => $this->systemMetrics['total_api_calls'],
      'total_cost' => round($this->systemMetrics['total_api_cost'], 4),
      'cost_per_call' => $this->systemMetrics['total_api_calls'] > 0 ? round($this->systemMetrics['total_api_cost'] / $this->systemMetrics['total_api_calls'], 4) : 0,
      'estimated_cost_per_hour' => round($this->estimateApiCostPerHour(), 4),
      'estimated_cost_per_day' => round($this->estimateApiCostPerDay(), 2),
      'estimated_cost_per_month' => round($this->estimateApiCostPerDay() * 30, 2),
    ];
  }
}