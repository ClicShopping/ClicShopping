<?php
/**
 * UnauthorizedActionHandler
 *
 * Handles unauthorized action attempts by agents including action denial,
 * administrator alerts, and security incident tracking.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use Exception;

class UnauthorizedActionHandler
{
  private $db;
  private AuditLogger $auditLogger;
  private array $alertThresholds = [
    'single_violation' => 1,      // Alert on any single violation
    'repeated_violations' => 3,    // Alert if 3 violations in 1 hour
    'critical_action' => 1         // Alert immediately for critical actions
  ];
  
  /**
   * Constructor
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->auditLogger = new AuditLogger();
  }
  
  /**
   * Handle unauthorized action attempt
   *
   * @param string $agentId Agent identifier
   * @param string $actionType Type of action attempted
   * @param string $reason Reason for denial
   * @param array $context Additional context about the attempt
   * @return array Response with denial details
   */
  public function handleUnauthorizedAction(
    string $agentId, 
    string $actionType, 
    string $reason,
    array $context = []
  ): array {
    // Log the unauthorized attempt
    $this->auditLogger->logAuthorizationAttempt($agentId, $actionType, false, $reason);
    
    // Record security incident
    $incidentId = $this->recordSecurityIncident($agentId, $actionType, $reason, $context);
    
    // Check if alert should be generated
    $shouldAlert = $this->shouldGenerateAlert($agentId, $actionType);
    
    if ($shouldAlert) {
      $this->generateAdministratorAlert($agentId, $actionType, $reason, $incidentId, $context);
    }
    
    // Check for repeated violations
    $violationCount = $this->getRecentViolationCount($agentId);
    if ($violationCount >= $this->alertThresholds['repeated_violations']) {
      $this->handleRepeatedViolations($agentId, $violationCount);
    }
    
    return [
      'denied' => true,
      'reason' => $reason,
      'incident_id' => $incidentId,
      'alert_generated' => $shouldAlert,
      'violation_count' => $violationCount
    ];
  }
  
  /**
   * Record security incident
   *
   * @param string $agentId Agent identifier
   * @param string $actionType Type of action attempted
   * @param string $reason Reason for denial
   * @param array $context Additional context
   * @return string Incident identifier
   */
  private function recordSecurityIncident(
    string $agentId, 
    string $actionType, 
    string $reason,
    array $context
  ): string {
    $incidentId = $this->generateIncidentId();
    
    try {
      $Qinsert = $this->db->prepare('
        INSERT INTO :table_rag_agent_security_incidents
        (incident_id, agent_id, action_type, denial_reason, context, severity, status, created_at)
        VALUES
        (:incident_id, :agent_id, :action_type, :denial_reason, :context, :severity, "open", NOW())
      ');
      
      $severity = $this->calculateSeverity($actionType, $context);
      
      $Qinsert->bindValue(':incident_id', $incidentId);
      $Qinsert->bindValue(':agent_id', $agentId);
      $Qinsert->bindValue(':action_type', $actionType);
      $Qinsert->bindValue(':denial_reason', $reason);
      $Qinsert->bindValue(':context', json_encode($context));
      $Qinsert->bindValue(':severity', $severity);
      $Qinsert->execute();
      
      return $incidentId;
    } catch (Exception $e) {
      error_log("Failed to record security incident: " . $e->getMessage());
      return $incidentId;
    }
  }
  
  /**
   * Generate unique incident ID
   *
   * @return string Incident identifier
   */
  private function generateIncidentId(): string
  {
    return 'INC-' . date('Ymd') . '-' . uniqid();
  }
  
  /**
   * Calculate severity of security incident
   *
   * @param string $actionType Type of action attempted
   * @param array $context Additional context
   * @return string Severity level (low, medium, high, critical)
   */
  private function calculateSeverity(string $actionType, array $context): string
  {
    // Critical actions
    $criticalActions = [
      'modify_permissions',
      'delete_agent',
      'modify_security_settings',
      'access_sensitive_data'
    ];

    if (in_array($actionType, $criticalActions, true)) {
      return 'critical';
    }

    // High severity actions
    $highSeverityActions = [
      'approve_objective',
      'cancel_objective',
      'modify_agent_role'
    ];

    if (in_array($actionType, $highSeverityActions, true)) {
      return 'high';
    }

    // Check context for severity indicators
    if (isset($context['repeated_attempt']) && $context['repeated_attempt'] === true) {
      return 'high';
    }

    // Default to medium severity
    return 'medium';
  }
  
  /**
   * Check if alert should be generated
   *
   * @param string $agentId Agent identifier
   * @param string $actionType Type of action attempted
   * @return bool True if alert should be generated
   */
  private function shouldGenerateAlert(string $agentId, string $actionType): bool
  {
    // Always alert for critical actions
    $criticalActions = [
      'modify_permissions',
      'delete_agent',
      'modify_security_settings',
      'access_sensitive_data'
    ];

    if (in_array($actionType, $criticalActions, true)) {
      return true;
    }

    // Check for repeated violations
    $recentViolations = $this->getRecentViolationCount($agentId);
    if ($recentViolations >= $this->alertThresholds['repeated_violations']) {
      return true;
    }

    // Alert on first violation for new agents
    if ($this->isNewAgent($agentId)) {
      return true;
    }

    return false;
  }
  
  /**
   * Get recent violation count for agent
   *
   * @param string $agentId Agent identifier
   * @param int $hours Number of hours to look back (default 1)
   * @return int Number of violations
   */
  private function getRecentViolationCount(string $agentId, int $hours = 1): int
  {
    $Qcount = $this->db->prepare('
      SELECT COUNT(*) as count
      FROM :table_rag_agent_security_incidents
      WHERE agent_id = :agent_id
        AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
    ');

    $Qcount->bindValue(':agent_id', $agentId);
    $Qcount->bindInt(':hours', $hours);
    $Qcount->execute();

    if ($Qcount->fetch()) {
      return $Qcount->valueInt('count');
    }

    return 0;
  }
  
  /**
   * Check if agent is new (created within last 24 hours)
   *
   * @param string $agentId Agent identifier
   * @return bool True if agent is new
   */
  private function isNewAgent(string $agentId): bool
  {
    $Qcheck = $this->db->prepare('
      SELECT created_at
      FROM :table_rag_agent_roles
      WHERE agent_id = :agent_id
    ');

    $Qcheck->bindValue(':agent_id', $agentId);
    $Qcheck->execute();

    if ($Qcheck->fetch()) {
      $createdAt = strtotime($Qcheck->value('created_at'));
      $hoursSinceCreation = (time() - $createdAt) / 3600;
      return $hoursSinceCreation < 24;
    }

    return true; // Treat unknown agents as new
  }
  
  /**
   * Generate administrator alert
   *
   * @param string $agentId Agent identifier
   * @param string $actionType Type of action attempted
   * @param string $reason Reason for denial
   * @param string $incidentId Incident identifier
   * @param array $context Additional context
   * @return bool True if alert generated successfully
   */
  private function generateAdministratorAlert(
    string $agentId,
    string $actionType,
    string $reason,
    string $incidentId,
    array $context
  ): bool {
    try {
      $alertId = $this->generateAlertId();
      $severity = $this->calculateSeverity($actionType, $context);

      $message = sprintf(
        "Unauthorized action attempt by agent '%s': %s. Reason: %s. Incident ID: %s",
        $agentId,
        $actionType,
        $reason,
        $incidentId
      );

      $Qinsert = $this->db->prepare('
        INSERT INTO :table_rag_administrator_alerts
        (alert_id, alert_type, severity, message, agent_id, incident_id, context, status, created_at)
        VALUES
        (:alert_id, "unauthorized_action", :severity, :message, :agent_id, :incident_id, :context, "pending", NOW())
      ');

      $Qinsert->bindValue(':alert_id', $alertId);
      $Qinsert->bindValue(':severity', $severity);
      $Qinsert->bindValue(':message', $message);
      $Qinsert->bindValue(':agent_id', $agentId);
      $Qinsert->bindValue(':incident_id', $incidentId);
      $Qinsert->bindValue(':context', json_encode($context));
      $Qinsert->execute();

      // Also log to system error log for immediate visibility
      error_log("SECURITY ALERT: $message");

      return true;
    } catch (Exception $e) {
      error_log("Failed to generate administrator alert: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Generate unique alert ID
   *
   * @return string Alert identifier
   */
  private function generateAlertId(): string
  {
    return 'ALT-' . date('Ymd') . '-' . uniqid();
  }
  
  /**
   * Handle repeated violations
   *
   * @param string $agentId Agent identifier
   * @param int $violationCount Number of violations
   * @return void
   */
  private function handleRepeatedViolations(string $agentId, int $violationCount): void
  {
    // Generate high-severity alert
    $alertId = $this->generateAlertId();
    $message = sprintf(
      "Agent '%s' has %d unauthorized action attempts in the last hour. Consider suspending agent.",
      $agentId,
      $violationCount
    );

    try {
      $Qinsert = $this->db->prepare('
        INSERT INTO :table_rag_administrator_alerts
        (alert_id, alert_type, severity, message, agent_id, status, created_at)
        VALUES
        (:alert_id, "repeated_violations", "high", :message, :agent_id, "pending", NOW())
      ');

      $Qinsert->bindValue(':alert_id', $alertId);
      $Qinsert->bindValue(':message', $message);
      $Qinsert->bindValue(':agent_id', $agentId);
      $Qinsert->execute();

      error_log("SECURITY ALERT: $message");

      // Consider auto-suspending agent if violations exceed threshold
      if ($violationCount >= 5) {
        $this->suspendAgent($agentId, "Automatic suspension due to repeated unauthorized actions");
      }
    } catch (Exception $e) {
      error_log("Failed to handle repeated violations: " . $e->getMessage());
    }
  }
  
  /**
   * Suspend agent
   *
   * @param string $agentId Agent identifier
   * @param string $reason Reason for suspension
   * @return bool True if suspended successfully
   */
  private function suspendAgent(string $agentId, string $reason): bool
  {
    try {
      $Qupdate = $this->db->prepare('
        UPDATE :table_rag_agent_roles
        SET status = "suspended",
            suspension_reason = :reason,
            suspended_at = NOW()
        WHERE agent_id = :agent_id
      ');

      $Qupdate->bindValue(':agent_id', $agentId);
      $Qupdate->bindValue(':reason', $reason);
      $Qupdate->execute();

      // Log suspension
      $this->auditLogger->logAction($agentId, 'agent_suspended', 'success', ['reason' => $reason]);

      return true;
    } catch (Exception $e) {
      error_log("Failed to suspend agent: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Get security incidents for agent
   *
   * @param string $agentId Agent identifier
   * @param int $limit Maximum number of records to return
   * @return array Array of security incidents
   */
  public function getAgentSecurityIncidents(string $agentId, int $limit = 50): array
  {
    $Qincidents = $this->db->prepare('
      SELECT 
        incident_id,
        agent_id,
        action_type,
        denial_reason,
        context,
        severity,
        status,
        created_at,
        resolved_at
      FROM :table_rag_agent_security_incidents
      WHERE agent_id = :agent_id
      ORDER BY created_at DESC
      LIMIT :limit
    ');
    
    $Qincidents->bindValue(':agent_id', $agentId);
    $Qincidents->bindInt(':limit', $limit);
    $Qincidents->execute();
    
    $incidents = [];
    while ($Qincidents->fetch()) {
      $incidents[] = [
        'incident_id' => $Qincidents->value('incident_id'),
        'agent_id' => $Qincidents->value('agent_id'),
        'action_type' => $Qincidents->value('action_type'),
        'denial_reason' => $Qincidents->value('denial_reason'),
        'context' => json_decode($Qincidents->value('context'), true),
        'severity' => $Qincidents->value('severity'),
        'status' => $Qincidents->value('status'),
        'created_at' => $Qincidents->value('created_at'),
        'resolved_at' => $Qincidents->value('resolved_at')
      ];
    }
    
    return $incidents;
  }
  
  /**
   * Get pending administrator alerts
   *
   * @param int $limit Maximum number of records to return
   * @return array Array of pending alerts
   */
  public function getPendingAlerts(int $limit = 50): array
  {
    $Qalerts = $this->db->prepare('
      SELECT 
        alert_id,
        alert_type,
        severity,
        message,
        agent_id,
        incident_id,
        context,
        status,
        created_at
      FROM :table_rag_administrator_alerts
      WHERE status = "pending"
      ORDER BY 
        CASE severity
          WHEN "critical" THEN 1
          WHEN "high" THEN 2
          WHEN "medium" THEN 3
          WHEN "low" THEN 4
        END,
        created_at DESC
      LIMIT :limit
    ');
    
    $Qalerts->bindInt(':limit', $limit);
    $Qalerts->execute();
    
    $alerts = [];
    while ($Qalerts->fetch()) {
      $alerts[] = [
        'alert_id' => $Qalerts->value('alert_id'),
        'alert_type' => $Qalerts->value('alert_type'),
        'severity' => $Qalerts->value('severity'),
        'message' => $Qalerts->value('message'),
        'agent_id' => $Qalerts->value('agent_id'),
        'incident_id' => $Qalerts->value('incident_id'),
        'context' => json_decode($Qalerts->value('context'), true),
        'status' => $Qalerts->value('status'),
        'created_at' => $Qalerts->value('created_at')
      ];
    }
    
    return $alerts;
  }
  
  /**
   * Resolve alert
   *
   * @param string $alertId Alert identifier
   * @param string $resolution Resolution notes
   * @return bool True if resolved successfully
   */
  public function resolveAlert(string $alertId, string $resolution): bool
  {
    try {
      $Qupdate = $this->db->prepare('
        UPDATE :table_rag_administrator_alerts
        SET status = "resolved",
            resolution = :resolution,
            resolved_at = NOW()
        WHERE alert_id = :alert_id
      ');
      
      $Qupdate->bindValue(':alert_id', $alertId);
      $Qupdate->bindValue(':resolution', $resolution);
      $Qupdate->execute();
      
      return true;
    } catch (Exception $e) {
      error_log("Failed to resolve alert: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Resolve security incident
   *
   * @param string $incidentId Incident identifier
   * @param string $resolution Resolution notes
   * @return bool True if resolved successfully
   */
  public function resolveIncident(string $incidentId, string $resolution): bool
  {
    try {
      $Qupdate = $this->db->prepare('
        UPDATE :table_rag_agent_security_incidents
        SET status = "resolved",
            resolution = :resolution,
            resolved_at = NOW()
        WHERE incident_id = :incident_id
      ');
      
      $Qupdate->bindValue(':incident_id', $incidentId);
      $Qupdate->bindValue(':resolution', $resolution);
      $Qupdate->execute();
      
      return true;
    } catch (Exception $e) {
      error_log("Failed to resolve incident: " . $e->getMessage());
      return false;
    }
  }
}
