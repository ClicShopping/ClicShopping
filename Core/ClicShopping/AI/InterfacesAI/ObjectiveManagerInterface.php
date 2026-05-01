<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * Interface ObjectiveManagerInterface
 *
 * Defines the contract for managing autonomous agent objectives.
 * Handles objective approval, conflict resolution, and lifecycle management.
 *
 * @package ClicShopping\AI\InterfacesAI
 */
interface ObjectiveManagerInterface
{
  /**
   * Approve or reject an objective
   *
   * Called when an objective requires orchestrator approval due to:
   * - Conflicts with other objectives
   * - System-wide constraint violations
   * - High-priority objectives
   *
   * @param string $objectiveId The objective ID to approve/reject
   * @param bool $approve True to approve, false to reject
   * @param string $reason Reason for the decision
   * @return array Approval result
   *               [
   *                 'success' => bool,
   *                 'objective_id' => string,
   *                 'status' => string, // 'approved' or 'cancelled'
   *                 'message' => string,
   *                 'reason' => string|null,
   *                 'error' => string|null
   *               ]
   */
  public function approveObjective(string $objectiveId, bool $approve, string $reason = ''): array;

  /**
   * Resolve conflicts between agent objectives
   *
   * Called when ConflictDetector identifies conflicting objectives.
   * The orchestrator decides how to resolve the conflict:
   * - Cancel one objective (cancel_lower_priority)
   * - Merge objectives (merge)
   * - Sequence objectives (sequence)
   * - Allow both with constraints (allow_both)
   *
   * @param array $conflictingObjectiveIds Array of conflicting objective IDs
   * @param string $resolutionStrategy Strategy: 'cancel_lower_priority', 'merge', 'sequence', 'allow_both'
   * @return array Resolution result
   *               [
   *                 'success' => bool,
   *                 'strategy' => string,
   *                 'kept_objective' => string|null,
   *                 'cancelled_objectives' => array|null,
   *                 'objectives' => array|null,
   *                 'message' => string|null,
   *                 'error' => string|null
   *               ]
   */
  public function resolveObjectiveConflict(array $conflictingObjectiveIds, string $resolutionStrategy = 'cancel_lower_priority'): array;

  /**
   * Get all active objectives
   *
   * Returns all objectives with status 'active' from the objective registry.
   *
   * @return array Array of active objectives
   */
  public function getActiveObjectives(): array;
}
