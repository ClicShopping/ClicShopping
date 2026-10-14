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
 * AlertManager Class
 *
 * Intelligent alert manager that:
 * - Manages configurable alerting rules
 * - Supports multiple notification channels
 * - Implements rate limiting and grouping
 * - Maintains alert history
 * - Allows automatic escalation
 * - Manages alerts with priorities
 */

class AlertManager
{
  private SecurityLogger $logger;
  private Cache $cache;
  private bool $debug;

  // Règles d'alerte
  private array $alertRules = [];

  // Alertes actives
  private array $activeAlerts = [];

  // Historique des alertes
  private array $alertHistory = [];

  // Canaux de notification
  private array $notificationChannels = [];

  // Configuration
  private int $cooldownPeriod = 1800;      // 30 minutes
  private int $groupingWindow = 300;        // 5 minutes
  private int $maxHistorySize = 1000;
  private bool $enableGrouping = true;
  private bool $enableEscalation = true;

  // Seuils de sévérité
  private const SEVERITY_INFO = 'info';
  private const SEVERITY_WARNING = 'warning';
  private const SEVERITY_ERROR = 'error';
  private const SEVERITY_CRITICAL = 'critical';

  public function __construct()
  {
    $this->logger = new SecurityLogger();
    $this->cache = new Cache(true);
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    $this->loadAlertsFromCache();
    $this->initializeDefaultRules();
    $this->initializeDefaultChannels();

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "AlertManager initialized with " . count($this->alertRules) . " rules",
        'info'
      );
    }
  }

  /**
   * Ajoute une alerte à l'historique
   */
  private function addToHistory(array $alert): void
  {
    $this->alertHistory[] = $alert;

    // Limiter la taille de l'historique
    if (count($this->alertHistory) > $this->maxHistorySize) {
      array_shift($this->alertHistory);
    }
  }

  /**
   * Gets alert history
   * 
   * @param array $filters Filters to apply
   * @param int $limit Maximum number of results
   * @return array Alert history
   */
  public function getAlertHistory(array $filters = [], int $limit = 100): array
  {
    $history = $this->alertHistory;

    // Filtrer par sévérité
    if (isset($filters['severity'])) {
      $history = array_filter($history, fn($a) => $a['severity'] === $filters['severity']);
    }

    // Filtrer par type
    if (isset($filters['type'])) {
      $history = array_filter($history, fn($a) => $a['type'] === $filters['type']);
    }

    // Filtrer par date
    if (isset($filters['since'])) {
      $since = strtotime($filters['since']);
      $history = array_filter($history, fn($a) => $a['triggered_at'] >= $since);
    }

    // Limiter
    return array_slice(array_values($history), -$limit);
  }

  /**
   * Initialise les règles d'alerte par défaut
   */
  private function initializeDefaultRules(): void
  {
    // Règle: Taux d'erreur élevé
    $this->addRule('high_error_rate', [
      'message' => 'Error rate exceeded threshold',
      'severity' => self::SEVERITY_ERROR,
      'condition' => function($metrics) {
        return ($metrics['error_rate'] ?? 0) > 0.1;
      },
      'channels' => ['log', 'email'],
      'escalation_rules' => [
        'wait_time' => 1800,
      ],
    ]);

    // Règle: Temps de réponse lent
    $this->addRule('slow_response', [
      'message' => 'Response time exceeded threshold',
      'severity' => self::SEVERITY_WARNING,
      'condition' => function($metrics) {
        return ($metrics['avg_response_time'] ?? 0) > 5.0;
      },
      'channels' => ['log'],
    ]);

    // Règle: Utilisation mémoire critique
    $this->addRule('high_memory_usage', [
      'message' => 'Memory usage critical',
      'severity' => self::SEVERITY_CRITICAL,
      'condition' => function($metrics) {
        return ($metrics['memory_percentage'] ?? 0) > 90;
      },
      'channels' => ['log', 'email'],
      'escalation_rules' => [
        'wait_time' => 900,
      ],
    ]);

    // Règle: Coût API élevé
    $this->addRule('high_api_cost', [
      'message' => 'API cost rate high',
      'severity' => self::SEVERITY_WARNING,
      'condition' => function($metrics) {
        return ($metrics['api_cost_per_hour'] ?? 0) > 1.0;
      },
      'channels' => ['log'],
    ]);

    // Règle: Taux de cache faible
    $this->addRule('low_cache_hit_rate', [
      'message' => 'Cache hit rate below threshold',
      'severity' => self::SEVERITY_INFO,
      'condition' => function($metrics) {
        return ($metrics['cache_hit_rate'] ?? 1.0) < 0.5;
      },
      'channels' => ['log'],
    ]);
  }

  /**
   * Initialise les canaux de notification par défaut
   */
  private function initializeDefaultChannels(): void
  {
    // Canal: Log
    $this->addNotificationChannel('log', function($alert) {
      $this->logger->logSecurityEvent(
        "ALERT [{$alert['severity']}]: {$alert['message']}",
        $this->mapSeverityToLogLevel($alert['severity']),
        $alert
      );
    });

    // Canal: Email (placeholder - à implémenter selon vos besoins)
    $this->addNotificationChannel('email', function($alert) {
      // À implémenter avec votre système d'email
      if ($alert['severity'] === self::SEVERITY_CRITICAL) {
        // Envoyer un email urgent
      }
    });

    // Canal: Slack (placeholder)
    $this->addNotificationChannel('slack', function($alert) {
      // À implémenter avec Slack API
    });

    // Canal: SMS (placeholder)
    $this->addNotificationChannel('sms', function($alert) {
      if (in_array($alert['severity'], [self::SEVERITY_ERROR, self::SEVERITY_CRITICAL], true)) {
        // À implémenter avec votre système SMS
      }
    });
  }

  /**
   * Mappe la sévérité au niveau de log
   */
  private function mapSeverityToLogLevel(string $severity): string
  {
    return match($severity) {
      self::SEVERITY_INFO => 'info',
      self::SEVERITY_WARNING => 'warning',
      self::SEVERITY_ERROR => 'error',
      self::SEVERITY_CRITICAL => 'critical',
      default => 'notice',
    };
  }

  /**
   * Persistence
   */

  private function loadAlertsFromCache(): void
  {
    $cacheKey = 'alert_manager_state';
    $cached = $this->cache->getCachedResponse($cacheKey);

    if ($cached !== null) {
      $decoded = json_decode($cached, true);
      if (is_array($decoded)) {
        $this->activeAlerts = $decoded['active'] ?? [];
        $this->alertHistory = $decoded['history'] ?? [];
      }
    }
  }

  private function saveAlertsToCache(): void
  {
    $cacheKey = 'alert_manager_state';
    $data = [
      'active' => $this->activeAlerts,
      'history' => array_slice($this->alertHistory, -100),
      'saved_at' => time(),
    ];

    $encoded = json_encode($data);
    $this->cache->cacheResponse($cacheKey, $encoded, 86400);
  }

  /**
   * Réinitialise tous les alertes
   */
  public function reset(): void
  {
    $this->activeAlerts = [];
    $this->alertHistory = [];
    $this->saveAlertsToCache();

    $this->logger->logSecurityEvent(
      "All alerts reset",
      'info'
    );
  }

  /**
   * Export des alertes au format JSON
   */
  public function exportJSON(): string
  {
    return json_encode([
      'active_alerts' => $this->activeAlerts,
      'alert_history' => $this->alertHistory,
      'stats' => $this->getStats(),
      'exported_at' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  }

  /**
   * Destructor - Save state
   */
  public function __destruct()
  {
    $this->saveAlertsToCache();
  }



  /**
   * 🚨 Déclenche une alerte
   *
   * @param string $alertId Identifiant unique de l'alerte
   * @param array $alertData Données de l'alerte
   * @return bool True si l'alerte a été déclenchée
   */
  public function triggerAlert(string $alertId, array $alertData): bool
  {
    // Vérifier cooldown
    if ($this->isInCooldown($alertId)) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Alert {$alertId} in cooldown, skipping",
          'info'
        );
      }
      return false;
    }

    // Create alert
    $alert = $this->createAlert($alertId, $alertData);

    // Vérifier grouping
    if ($this->enableGrouping && $this->canGroup($alert)) {
      $this->groupAlert($alert);
      return true;
    }

    // Ajouter aux alertes actives
    $this->activeAlerts[$alertId] = $alert;

    // Ajouter à l'historique
    $this->addToHistory($alert);

    // Notifier selon les canaux
    $this->notify($alert);

    // Log
    $this->logger->logSecurityEvent(
      "Alert triggered: {$alertId} [{$alert['severity']}]",
      $this->mapSeverityToLogLevel($alert['severity']),
      $alertData
    );

    // Save
    $this->saveAlertsToCache();

    return true;
  }

  /**
   * Crée une structure d'alerte
   */
  private function createAlert(string $alertId, array $alertData): array
  {
    return [
      'id' => $alertId,
      'type' => $alertData['type'] ?? 'generic',
      'severity' => $alertData['severity'] ?? self::SEVERITY_WARNING,
      'message' => $alertData['message'] ?? 'Unknown alert',
      'details' => $alertData['details'] ?? [],
      'threshold' => $alertData['threshold'] ?? null,
      'current_value' => $alertData['current_value'] ?? null,
      'triggered_at' => time(),
      'acknowledged' => false,
      'acknowledged_at' => null,
      'acknowledged_by' => null,
      'resolved' => false,
      'resolved_at' => null,
      'escalated' => false,
      'escalation_level' => 0,
      'notification_count' => 0,
      'last_notification' => null,
    ];
  }

  /**
   * 📊 Ajoute une règle d'alerte
   *
   * @param string $ruleId Identifiant de la règle
   * @param array $ruleConfig Configuration de la règle
   */
  public function addRule(string $ruleId, array $ruleConfig): void
  {
    $this->alertRules[$ruleId] = array_merge([
      'id' => $ruleId,
      'enabled' => true,
      'condition' => null,
      'severity' => self::SEVERITY_WARNING,
      'cooldown' => $this->cooldownPeriod,
      'channels' => ['log'],
      'escalation_rules' => [],
    ], $ruleConfig);

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Alert rule added: {$ruleId}",
        'info'
      );
    }
  }


  /**
   * ✅ Acquitte une alerte
   *
   * @param string $alertId Identifiant de l'alerte
   * @param string $acknowledgedBy Qui acquitte
   * @return bool True si succès
   */
  public function acknowledgeAlert(string $alertId, string $acknowledgedBy = 'system'): bool
  {
    if (!isset($this->activeAlerts[$alertId])) {
      return false;
    }

    $this->activeAlerts[$alertId]['acknowledged'] = true;
    $this->activeAlerts[$alertId]['acknowledged_at'] = time();
    $this->activeAlerts[$alertId]['acknowledged_by'] = $acknowledgedBy;

    $this->logger->logSecurityEvent(
      "Alert acknowledged: {$alertId} by {$acknowledgedBy}",
      'info'
    );

    $this->saveAlertsToCache();

    return true;
  }

  /**
   * ✅ Résout une alerte
   *
   * @param string $alertId Identifiant de l'alerte
   * @param string $resolution Notes de résolution
   * @return bool True si succès
   */
  public function resolveAlert(string $alertId, string $resolution = ''): bool
  {
    if (!isset($this->activeAlerts[$alertId])) {
      return false;
    }

    $this->activeAlerts[$alertId]['resolved'] = true;
    $this->activeAlerts[$alertId]['resolved_at'] = time();
    $this->activeAlerts[$alertId]['resolution'] = $resolution;

    // Déplacer vers l'historique
    $this->addToHistory($this->activeAlerts[$alertId]);

    // Retirer des alertes actives
    unset($this->activeAlerts[$alertId]);

    $this->logger->logSecurityEvent(
      "Alert resolved: {$alertId}",
      'info'
    );

    $this->saveAlertsToCache();

    return true;
  }

  /**
   * 🔔 Notifie selon les canaux configurés
   */
  private function notify(array $alert): void
  {
    $rule = $this->alertRules[$alert['type']] ?? null;
    $channels = $rule['channels'] ?? ['log'];

    foreach ($channels as $channelName) {
      if (isset($this->notificationChannels[$channelName])) {
        $channel = $this->notificationChannels[$channelName];

        try {
          $channel['handler']($alert);

          if ($this->debug) {
            $this->logger->logSecurityEvent(
              "Alert sent to channel: {$channelName}",
              'info'
            );
          }
        } catch (\Exception $e) {
          $this->logger->logSecurityEvent(
            "Failed to send alert to {$channelName}: " . $e->getMessage(),
            'error'
          );
        }
      }
    }

    // Update notification counter
    if (isset($this->activeAlerts[$alert['id']])) {
      $this->activeAlerts[$alert['id']]['notification_count']++;
      $this->activeAlerts[$alert['id']]['last_notification'] = time();
    }
  }

  /**
   * 📢 Ajoute un canal de notification
   *
   * @param string $channelName Nom du canal
   * @param callable $handler Fonction de notification
   */
  public function addNotificationChannel(string $channelName, callable $handler): void
  {
    $this->notificationChannels[$channelName] = [
      'name' => $channelName,
      'handler' => $handler,
      'added_at' => time(),
    ];

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Notification channel added: {$channelName}",
        'info'
      );
    }
  }

  /**
   * Vérifie si une alerte est en cooldown
   */
  private function isInCooldown(string $alertId): bool
  {
    if (!isset($this->activeAlerts[$alertId])) {
      return false;
    }

    $alert = $this->activeAlerts[$alertId];
    $rule = $this->alertRules[$alert['type']] ?? null;
    $cooldown = $rule['cooldown'] ?? $this->cooldownPeriod;

    $lastNotification = $alert['last_notification'] ?? $alert['triggered_at'];

    return (time() - $lastNotification) < $cooldown;
  }

  /**
   * Vérifie si l'alerte peut être groupée
   */
  private function canGroup(array $alert): bool
  {
    foreach ($this->activeAlerts as $activeAlert) {
      if ($activeAlert['type'] === $alert['type'] &&
        $activeAlert['severity'] === $alert['severity'] &&
        !$activeAlert['resolved'] &&
        (time() - $activeAlert['triggered_at']) < $this->groupingWindow) {
        return true;
      }
    }

    return false;
  }

  /**
   * Groupe une alerte avec une existante
   */
  private function groupAlert(array $alert): void
  {
    foreach ($this->activeAlerts as $alertId => $activeAlert) {
      if ($activeAlert['type'] === $alert['type'] &&
        $activeAlert['severity'] === $alert['severity'] &&
        !$activeAlert['resolved']) {

        // Incrémenter le compteur de groupe
        if (!isset($this->activeAlerts[$alertId]['grouped_count'])) {
          $this->activeAlerts[$alertId]['grouped_count'] = 1;
        }
        $this->activeAlerts[$alertId]['grouped_count']++;
        $this->activeAlerts[$alertId]['last_grouped'] = time();

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Alert grouped with {$alertId} (count: {$this->activeAlerts[$alertId]['grouped_count']})",
            'info'
          );
        }

        break;
      }
    }
  }

  /**
   * 📈 Escalade une alerte
   *
   * @param string $alertId Identifiant de l'alerte
   * @return bool True si escaladé
   */
  public function escalateAlert(string $alertId): bool
  {
    if (!isset($this->activeAlerts[$alertId])) {
      return false;
    }

    if (!$this->enableEscalation) {
      return false;
    }

    $alert = &$this->activeAlerts[$alertId];
    $rule = $this->alertRules[$alert['type']] ?? null;

    if (!$rule || empty($rule['escalation_rules'])) {
      return false;
    }

    $alert['escalated'] = true;
    $alert['escalation_level']++;
    $alert['escalated_at'] = time();

    // Augmenter la sévérité
    $alert['severity'] = $this->increaseSeverity($alert['severity']);

    // Notifier avec escalation
    $this->notify($alert);

    $this->logger->logSecurityEvent(
      "Alert escalated: {$alertId} to level {$alert['escalation_level']}",
      'warning'
    );

    $this->saveAlertsToCache();

    return true;
  }

  /**
   * Augmente la sévérité d'un niveau
   */
  private function increaseSeverity(string $severity): string
  {
    return match($severity) {
      self::SEVERITY_INFO => self::SEVERITY_WARNING,
      self::SEVERITY_WARNING => self::SEVERITY_ERROR,
      self::SEVERITY_ERROR => self::SEVERITY_CRITICAL,
      default => self::SEVERITY_CRITICAL,
    };
  }

  /**
   * 🕐 Vérifie les alertes pour escalation automatique
   */
  public function checkAutoEscalation(): array
  {
    $escalated = [];

    foreach ($this->activeAlerts as $alertId => $alert) {
      if ($alert['resolved'] || $alert['acknowledged']) {
        continue;
      }

      $rule = $this->alertRules[$alert['type']] ?? null;
      if (!$rule || empty($rule['escalation_rules'])) {
        continue;
      }

      // Vérifier le temps d'attente
      $waitTime = $rule['escalation_rules']['wait_time'] ?? 3600; // 1h par défaut
      $timeSinceTrigger = time() - $alert['triggered_at'];

      if ($timeSinceTrigger > $waitTime && $alert['escalation_level'] < 3) {
        if ($this->escalateAlert($alertId)) {
          $escalated[] = $alertId;
        }
      }
    }

    return $escalated;
  }

  /**
   * Gets all active alerts
   * 
   * @param array $filters Filters to apply
   * @return array Active alerts
   */
  public function getActiveAlerts(array $filters = []): array
  {
    $alerts = $this->activeAlerts;

    // Filtrer par sévérité
    if (isset($filters['severity'])) {
      $alerts = array_filter($alerts, fn($a) => $a['severity'] === $filters['severity']);
    }

    // Filtrer par type
    if (isset($filters['type'])) {
      $alerts = array_filter($alerts, fn($a) => $a['type'] === $filters['type']);
    }

    // Filtrer par état d'acquittement
    if (isset($filters['acknowledged'])) {
      $alerts = array_filter($alerts, fn($a) => $a['acknowledged'] === $filters['acknowledged']);
    }

    return array_values($alerts);
  }

  /**
   * Gets alert statistics
   * 
   * @return array Statistics
   */
  public function getStats(): array
  {
    $activeCount = count($this->activeAlerts);
    $acknowledged = count(array_filter($this->activeAlerts, fn($a) => $a['acknowledged']));

    // Compter par sévérité
    $bySeverity = [
      self::SEVERITY_INFO => 0,
      self::SEVERITY_WARNING => 0,
      self::SEVERITY_ERROR => 0,
      self::SEVERITY_CRITICAL => 0,
    ];

    foreach ($this->activeAlerts as $alert) {
      $bySeverity[$alert['severity']]++;
    }

    return [
      'active_alerts' => $activeCount,
      'acknowledged' => $acknowledged,
      'unacknowledged' => $activeCount - $acknowledged,
      'by_severity' => $bySeverity,
      'total_rules' => count($this->alertRules),
      'enabled_rules' => count(array_filter($this->alertRules, fn($r) => $r['enabled'])),
      'notification_channels' => count($this->notificationChannels),
      'history_size' => count($this->alertHistory),
    ];
  }



  ///*************************
  /// not used
  /// ************************



  /**
   * Gets alert report
   * 
   * @param int $hours Number of hours to analyze
   * @return array Alert report
   */
  public function getAlertReport(int $hours = 24): array
  {
    $since = time() - ($hours * 3600);
    $recentAlerts = array_filter(
      $this->alertHistory,
      fn($a) => $a['triggered_at'] >= $since
    );

    // Compter par type
    $byType = [];
    foreach ($recentAlerts as $alert) {
      if (!isset($byType[$alert['type']])) {
        $byType[$alert['type']] = 0;
      }
      $byType[$alert['type']]++;
    }

    // Compter par sévérité
    $bySeverity = [
      self::SEVERITY_INFO => 0,
      self::SEVERITY_WARNING => 0,
      self::SEVERITY_ERROR => 0,
      self::SEVERITY_CRITICAL => 0,
    ];

    foreach ($recentAlerts as $alert) {
      $bySeverity[$alert['severity']]++;
    }

    // Alertes par heure
    $byHour = [];
    foreach ($recentAlerts as $alert) {
      $hour = date('Y-m-d H:00', $alert['triggered_at']);
      if (!isset($byHour[$hour])) {
        $byHour[$hour] = 0;
      }
      $byHour[$hour]++;
    }

    return [
      'period_hours' => $hours,
      'total_alerts' => count($recentAlerts),
      'by_type' => $byType,
      'by_severity' => $bySeverity,
      'by_hour' => $byHour,
      'avg_alerts_per_hour' => count($recentAlerts) / max(1, $hours),
      'acknowledged_rate' => count(array_filter($recentAlerts, fn($a) => $a['acknowledged'])) / max(1, count($recentAlerts)) * 100,
    ];
  }

  /**
   * Désactive une règle d'alerte
   */
  public function disableRule(string $ruleId): bool
  {
    if (!isset($this->alertRules[$ruleId])) {
      return false;
    }

    $this->alertRules[$ruleId]['enabled'] = false;

    $this->logger->logSecurityEvent(
      "Alert rule disabled: {$ruleId}",
      'info'
    );

    return true;
  }

  /**
   * Active une règle d'alerte
   */
  public function enableRule(string $ruleId): bool
  {
    if (!isset($this->alertRules[$ruleId])) {
      return false;
    }

    $this->alertRules[$ruleId]['enabled'] = true;

    $this->logger->logSecurityEvent(
      "Alert rule enabled: {$ruleId}",
      'info'
    );

    return true;
  }

  /**
   * Gets critical unacknowledged alerts
   * 
   * @return array Critical alerts
   */
  public function getCriticalUnacknowledged(): array
  {
    return array_filter(
      $this->activeAlerts,
      fn($a) => $a['severity'] === self::SEVERITY_CRITICAL && !$a['acknowledged']
    );
  }

  /**
   * Configuration des paramètres
   */
  public function setCooldownPeriod(int $seconds): void
  {
    $this->cooldownPeriod = $seconds;
  }

  public function setGroupingWindow(int $seconds): void
  {
    $this->groupingWindow = $seconds;
  }

  public function setMaxHistorySize(int $size): void
  {
    $this->maxHistorySize = $size;
  }

  public function setEnableGrouping(bool $enable): void
  {
    $this->enableGrouping = $enable;
  }

  public function setEnableEscalation(bool $enable): void
  {
    $this->enableEscalation = $enable;
  }

  /**
   * 🔍 Évalue les règles d'alerte
   *
   * @param array $metrics Métriques à évaluer
   * @return array Alertes déclenchées
   */
  public function evaluateRules(array $metrics): array
  {
    $triggeredAlerts = [];

    foreach ($this->alertRules as $ruleId => $rule) {
      if (!$rule['enabled']) {
        continue;
      }

      // Évaluer la condition
      $shouldTrigger = $this->evaluateCondition($rule['condition'], $metrics);

      if ($shouldTrigger) {
        $alertData = [
          'type' => $rule['id'],
          'severity' => $rule['severity'],
          'message' => $rule['message'] ?? "Rule {$ruleId} triggered",
          'details' => $metrics,
          'rule' => $rule,
        ];

        if ($this->triggerAlert($ruleId, $alertData)) {
          $triggeredAlerts[] = $ruleId;
        }
      }
    }

    return $triggeredAlerts;
  }

  /**
   * Évalue une condition
   */
  private function evaluateCondition(callable $condition = null, array $metrics): bool
  {
    if (!$condition) {
      return false;
    }

    try {
      return (bool)$condition($metrics);
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Error evaluating alert condition: " . $e->getMessage(),
          'error'
        );
      }
      return false;
    }
  }
}