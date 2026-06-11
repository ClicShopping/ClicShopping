<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\AI\InterfacesAI\ObjectiveManagerInterface;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * Class ObjectiveManager
 *
 * Manages autonomous agent objectives including approval, conflict resolution,
 * and lifecycle management. Extracted from OrchestratorAgent to improve
 * separation of concerns and testability.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 */
class ObjectiveManager implements ObjectiveManagerInterface
{
  private $db;
  private SecurityLogger $securityLogger;
  private bool $debug;

  /**
   * Constructor
   *
   * @param mixed $db Database connection
   * @param SecurityLogger $securityLogger Logger for objective events
   * @param bool $debug Debug mode flag
   */
  public function __construct($db, SecurityLogger $securityLogger, bool $debug = false)
  {
    $this->db = $db;
    $this->securityLogger = $securityLogger;
    $this->debug = $debug;
  }

  /**
   * Approve or reject an objective
   *
   * @param string $objectiveId The objective ID to approve/reject
   * @param bool $approve True to approve, false to reject
   * @param string $reason Reason for the decision
   * @return array Approval result
   */
  public function approveObjective(string $objectiveId, bool $approve, string $reason = ''): array
  {
    try {
      $objectiveRegistry = new ObjectiveRegistry($this->db, $this->debug);
      $objective = $objectiveRegistry->getObjective($objectiveId);

      if (!$objective) {
        throw new \InvalidArgumentException("Objective {$objectiveId} not found");
      }

      if ($approve) {
        $objectiveRegistry->updateObjectiveStatus($objectiveId, 'approved');

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Orchestrator approved objective {$objectiveId}: {$reason}",
            'info'
          );
        }

        return [
          'success' => true,
          'objective_id' => $objectiveId,
          'status' => 'approved',
          'message' => 'Objective approved for execution'
        ];
      } else {
        $objectiveRegistry->cancelObjective($objectiveId, $reason);

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Orchestrator rejected objective {$objectiveId}: {$reason}",
            'warning'
          );
        }

        return [
          'success' => true,
          'objective_id' => $objectiveId,
          'status' => 'cancelled',
          'message' => 'Objective rejected',
          'reason' => $reason
        ];
      }

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error approving objective {$objectiveId}: " . $e->getMessage(),
        'error'
      );

      return [
        'success' => false,
        'objective_id' => $objectiveId,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Resolve conflicts between agent objectives
   *
   * @param array $conflictingObjectiveIds Array of conflicting objective IDs
   * @param string $resolutionStrategy Strategy: 'cancel_lower_priority', 'merge', 'sequence', 'allow_both'
   * @return array Resolution result
   */
  public function resolveObjectiveConflict(array $conflictingObjectiveIds, string $resolutionStrategy = 'cancel_lower_priority'): array
  {
    try {
      $objectiveRegistry = new ObjectiveRegistry($this->db, $this->debug);
      $objectives = [];

      // Load all conflicting objectives
      foreach ($conflictingObjectiveIds as $id) {
        $obj = $objectiveRegistry->getObjective($id);
        if ($obj) {
          $objectives[] = $obj;
        }
      }

      if (empty($objectives)) {
        throw new \InvalidArgumentException("No valid objectives found");
      }

      $result = match ($resolutionStrategy) {
        'cancel_lower_priority' => $this->cancelLowerPriorityObjectives($objectives, $objectiveRegistry),
        'merge' => $this->mergeObjectives($objectives, $objectiveRegistry),
        'sequence' => $this->sequenceObjectives($objectives, $objectiveRegistry),
        'allow_both' => $this->allowBothObjectives($objectives, $objectiveRegistry),
        default => throw new \InvalidArgumentException("Unknown resolution strategy: {$resolutionStrategy}")
      };

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Orchestrator resolved conflict using strategy '{$resolutionStrategy}'",
          'info'
        );
      }

      return $result;

    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error resolving objective conflict: " . $e->getMessage(),
        'error'
      );

      return [
        'success' => false,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Get all active objectives
   *
   * @return array Array of active objectives
   */
  public function getActiveObjectives(): array
  {
    try {
      $objectiveRegistry = new ObjectiveRegistry($this->db, $this->debug);
      return $objectiveRegistry->getObjectivesByStatus('active');
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Error getting active objectives: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }

  /**
   * Cancel lower priority objectives in a conflict
   *
   * @param array $objectives Array of LocalObjective instances
   * @param ObjectiveRegistry $registry Objective registry instance
   * @return array Resolution result
   */
  private function cancelLowerPriorityObjectives(array $objectives, ObjectiveRegistry $registry): array
  {
    // Sort by priority (critical > high > medium > low)
    $priorityOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
    usort($objectives, function($a, $b) use ($priorityOrder) {
      return ($priorityOrder[$b->getPriority()] ?? 0) <=> ($priorityOrder[$a->getPriority()] ?? 0);
    });

    // Keep highest priority, cancel others
    $kept = $objectives[0];
    $cancelled = [];

    for ($i = 1, $iMax = count($objectives); $i < $iMax; $i++) {
      $obj = $objectives[$i];
      $registry->cancelObjective($obj->getId(), 'Cancelled due to conflict with higher priority objective');
      $cancelled[] = $obj->getId();
    }

    return [
      'success' => true,
      'strategy' => 'cancel_lower_priority',
      'kept_objective' => $kept->getId(),
      'cancelled_objectives' => $cancelled
    ];
  }

  /**
   * Merge compatible objectives
   *
   * @param array $objectives Array of LocalObjective instances
   * @param ObjectiveRegistry $registry Objective registry instance
   * @return array Resolution result
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  private function mergeObjectives(array $objectives, ObjectiveRegistry $registry): array
  {
    // Placeholder for merge logic
    // In a full implementation, this would create a new collaborative objective
    return [
      'success' => true,
      'strategy' => 'merge',
      'message' => 'Objective merging not yet fully implemented',
      'objectives' => array_map(fn($obj) => $obj->getId(), $objectives)
    ];
  }

  /**
   * Sequence objectives to execute in order
   *
   * @param array $objectives Array of LocalObjective instances
   * @param ObjectiveRegistry $registry Objective registry instance
   * @return array Resolution result
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  private function sequenceObjectives(array $objectives, ObjectiveRegistry $registry): array
  {
    // Placeholder for sequencing logic
    return [
      'success' => true,
      'strategy' => 'sequence',
      'message' => 'Objective sequencing not yet fully implemented',
      'objectives' => array_map(fn($obj) => $obj->getId(), $objectives)
    ];
  }

  /**
   * Allow both objectives with constraints
   *
   * @param array $objectives Array of LocalObjective instances
   * @param ObjectiveRegistry $registry Objective registry instance
   * @return array Resolution result
   * @SuppressWarnings(PHPMD.UnusedFormalParameter)
   */
  private function allowBothObjectives(array $objectives, ObjectiveRegistry $registry): array
  {
    return [
      'success' => true,
      'strategy' => 'allow_both',
      'message' => 'Both objectives allowed to proceed',
      'objectives' => array_map(fn($obj) => $obj->getId(), $objectives)
    ];
  }
}
