<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Monitoring\SubMonitoring;

use ClicShopping\AI\Security\SecurityLogger;

/**
 * AlertManager
 *
 * Owns the alert lifecycle (thresholds, active alerts, cooldown, escalation,
 * acknowledge/resolve, escalation email). Extracted verbatim from MonitoringAgent
 * (2026-06-20). checkAlertThresholds receives the API cost as a parameter (computed
 * by MonitoringAgent::estimateApiCostPerHour) to avoid coupling back to the
 * system-metrics helpers.
 */
class AlertManager
{
  private SecurityLogger $logger;
  private bool $debug;

  // Alert thresholds (compliant with Test 7.3 requirements)
  private array $alertThresholds = [
    'error_rate' => 0.1,              // 10% errors (Test 7.3 requirement)
    'response_time' => 10.0,          // 10 seconds (Test 7.3 requirement)
    'api_cost_per_hour' => 1.0,       // $1 per hour
    'memory_usage' => 0.9,            // 90% memory (Test 7.3 requirement)
    'cache_hit_rate' => 0.5,          // 50% minimum
  ];
  private array $activeAlerts = [];
  private int $alertCooldown = 1800;     // 30 minutes

  public function __construct(SecurityLogger $logger, bool $debug)
  {
    $this->logger = $logger;
    $this->debug = $debug;
  }

  /** Restore persisted alerts (loaded from cache by MonitoringAgent). */
  public function restoreAlerts(array $alerts): void
  {
    $this->activeAlerts = $alerts;
  }

  /** Clear all active alerts (MonitoringAgent::resetMetrics). */
  public function clearAllAlerts(): void
  {
    $this->activeAlerts = [];
  }

  /**
   * Checks alert thresholds
   * 
   * @param array $snapshot Metrics snapshot
   */
  public function checkAlertThresholds(array $snapshot, float $apiCostPerHour): void
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
}
