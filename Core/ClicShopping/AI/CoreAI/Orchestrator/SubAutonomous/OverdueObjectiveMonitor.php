<?php
/**
 * OverdueObjectiveMonitor Class
 *
 * Background job that monitors objectives for overdue status and generates alerts.
 * This class provides automated detection and alerting for objectives that exceed
 * their estimated completion time while remaining in active status.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\AI\Infrastructure\Monitoring\AlertManager;
use ClicShopping\AI\Security\SecurityLogger;
use Exception;

class OverdueObjectiveMonitor
{
  private ObjectiveRegistry $registry;
  private AlertManager $alertManager;
  private SecurityLogger $logger;
  private bool $debug;

  // Configuration
  private int $checkInterval = 300; // 5 minutes
  private int $alertCooldown = 1800; // 30 minutes
  private array $alertedObjectives = [];

  /**
   * Constructor
   *
   * Initializes the monitor with required dependencies.
   *
   * @param ObjectiveRegistry $registry The objective registry to monitor
   * @param AlertManager $alertManager The alert manager for generating alerts
   */
  public function __construct(
    ObjectiveRegistry $registry,
    AlertManager $alertManager
  ) {
    $this->registry = $registry;
    $this->alertManager = $alertManager;
    $this->logger = new SecurityLogger();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') 
      && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
  }

  /**
   * Run the monitoring check
   *
   * Checks for overdue objectives and generates alerts for any found.
   * This method should be called periodically by a cron job or scheduler.
   *
   * @return array Summary of monitoring results
   */
  public function run(): array
  {
    try {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          'Starting overdue objective monitoring check',
          'info'
        );
      }

      // Get all overdue objectives
      $overdueObjectives = $this->registry->getOverdueObjectives();

      $results = [
        'checked_at' => date('Y-m-d H:i:s'),
        'overdue_count' => count($overdueObjectives),
        'alerts_generated' => 0,
        'alerts_skipped' => 0,
        'objectives' => []
      ];

      // Process each overdue objective
      foreach ($overdueObjectives as $objective) {
        $objectiveId = $objective->getId();
        
        // Check if we should alert for this objective
        if ($this->shouldAlert($objectiveId)) {
          $this->generateAlert($objective);
          $results['alerts_generated']++;
          $this->alertedObjectives[$objectiveId] = time();
        } else {
          $results['alerts_skipped']++;
        }

        // Add to results
        $results['objectives'][] = [
          'id' => $objectiveId,
          'agent_id' => $objective->getAgentId(),
          'goal' => $objective->getGoalStatement(),
          'elapsed_time' => $objective->getElapsedTime(),
          'estimated_time' => $objective->getEstimatedCompletionTime(),
          'overdue_by' => $objective->getElapsedTime() - $objective->getEstimatedCompletionTime()
        ];
      }

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Monitoring check complete: {$results['overdue_count']} overdue, " .
          "{$results['alerts_generated']} alerts generated",
          'info'
        );
      }

      return $results;
    } catch (Exception $e) {
      $this->logger->logSecurityEvent(
        'Error during overdue objective monitoring: ' . $e->getMessage(),
        'error'
      );

      return [
        'checked_at' => date('Y-m-d H:i:s'),
        'error' => $e->getMessage(),
        'overdue_count' => 0,
        'alerts_generated' => 0,
        'alerts_skipped' => 0,
        'objectives' => []
      ];
    }
  }

  /**
   * Generate an alert for an overdue objective
   *
   * Creates and triggers an alert through the AlertManager for an objective
   * that has exceeded its estimated completion time.
   *
   * @param LocalObjective $objective The overdue objective
   * @return bool True if alert was generated successfully
   */
  private function generateAlert(LocalObjective $objective): bool
  {
    try {
      $objectiveId = $objective->getId();
      $overdueBy = $objective->getElapsedTime() - $objective->getEstimatedCompletionTime();
      $overdueMinutes = round($overdueBy / 60);

      $alertData = [
        'type' => 'overdue_objective',
        'severity' => $this->calculateSeverity($objective),
        'message' => "Objective '{$objective->getGoalStatement()}' is overdue by {$overdueMinutes} minutes",
        'details' => [
          'objective_id' => $objectiveId,
          'agent_id' => $objective->getAgentId(),
          'goal_statement' => $objective->getGoalStatement(),
          'priority' => $objective->getPriority(),
          'estimated_completion_time' => $objective->getEstimatedCompletionTime(),
          'elapsed_time' => $objective->getElapsedTime(),
          'overdue_by_seconds' => $overdueBy,
          'overdue_by_minutes' => $overdueMinutes,
          'created_at' => $objective->getCreatedAt()->format('Y-m-d H:i:s')
        ],
        'threshold' => $objective->getEstimatedCompletionTime(),
        'current_value' => $objective->getElapsedTime()
      ];

      $alertId = "overdue_objective_{$objectiveId}";
      $success = $this->alertManager->triggerAlert($alertId, $alertData);

      if ($success && $this->debug) {
        $this->logger->logSecurityEvent(
          "Alert generated for overdue objective: {$objectiveId}",
          'warning',
          $alertData['details']
        );
      }

      return $success;
    } catch (Exception $e) {
      $this->logger->logSecurityEvent(
        "Failed to generate alert for objective {$objective->getId()}: " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }

  /**
   * Calculate alert severity based on objective properties
   *
   * Determines the appropriate severity level for an overdue alert based on
   * the objective's priority and how overdue it is.
   *
   * @param LocalObjective $objective The overdue objective
   * @return string Severity level (info, warning, error, critical)
   */
  private function calculateSeverity(LocalObjective $objective): string
  {
    $priority = $objective->getPriority();
    $overdueBy = $objective->getElapsedTime() - $objective->getEstimatedCompletionTime();
    $overduePercentage = ($overdueBy / $objective->getEstimatedCompletionTime()) * 100;

    // Critical priority objectives are always at least error level
    if ($priority === 'critical') {
      return $overduePercentage > 50 ? 'critical' : 'error';
    }

    // High priority objectives
    if ($priority === 'high') {
      if ($overduePercentage > 100) {
        return 'error';
      }
      return $overduePercentage > 50 ? 'warning' : 'info';
    }

    // Medium priority objectives
    if ($priority === 'medium') {
      return $overduePercentage > 100 ? 'warning' : 'info';
    }

    // Low priority objectives
    return 'info';
  }

  /**
   * Check if an alert should be generated for an objective
   *
   * Implements cooldown logic to prevent alert spam. An alert will only be
   * generated if the cooldown period has elapsed since the last alert.
   *
   * @param string $objectiveId The objective ID
   * @return bool True if an alert should be generated
   */
  private function shouldAlert(string $objectiveId): bool
  {
    // Check if we've already alerted for this objective
    if (!isset($this->alertedObjectives[$objectiveId])) {
      return true;
    }

    // Check if cooldown period has elapsed
    $lastAlertTime = $this->alertedObjectives[$objectiveId];
    $timeSinceLastAlert = time() - $lastAlertTime;

    return $timeSinceLastAlert >= $this->alertCooldown;
  }

  /**
   * Get monitoring statistics
   *
   * Returns statistics about the monitoring activity including alert counts
   * and currently tracked objectives.
   *
   * @return array Monitoring statistics
   */
  public function getStats(): array
  {
    return [
      'check_interval' => $this->checkInterval,
      'alert_cooldown' => $this->alertCooldown,
      'tracked_objectives' => count($this->alertedObjectives),
      'last_check' => date('Y-m-d H:i:s')
    ];
  }

  /**
   * Set the check interval
   *
   * @param int $seconds Interval in seconds between checks
   */
  public function setCheckInterval(int $seconds): void
  {
    $this->checkInterval = $seconds;
  }

  /**
   * Set the alert cooldown period
   *
   * @param int $seconds Cooldown period in seconds
   */
  public function setAlertCooldown(int $seconds): void
  {
    $this->alertCooldown = $seconds;
  }

  /**
   * Clear the alerted objectives cache
   *
   * Resets the tracking of which objectives have been alerted, allowing
   * immediate re-alerting if needed.
   */
  public function clearAlertCache(): void
  {
    $this->alertedObjectives = [];
    
    if ($this->debug) {
      $this->logger->logSecurityEvent(
        'Cleared overdue objective alert cache',
        'info'
      );
    }
  }

  /**
   * Get list of currently alerted objectives
   *
   * @return array Array of objective IDs and their last alert times
   */
  public function getAlertedObjectives(): array
  {
    return $this->alertedObjectives;
  }
}
