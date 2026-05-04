<?php
/**
 * ObjectiveRegistry Class
 *
 * Central repository for tracking all agent objectives. Provides database persistence,
 * conflict detection, metrics tracking, and query capabilities for local objectives.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use DateTimeImmutable;
use Exception;

class ObjectiveRegistry
{
  private $db;
  private array $stateTransitionLog = [];
  private AuthorizationManager $authManager;
  private AuditLogger $auditLogger;

  /**
   * Constructor
   *
   * Initializes the registry with database connection and security components.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
    $this->authManager = new AuthorizationManager();
    $this->auditLogger = new AuditLogger();
  }

  /**
   * Register a new objective
   *
   * Persists a LocalObjective to the database and returns its ID.
   * Validates that all required fields are present and checks authorization.
   *
   * @param LocalObjective $objective The objective to register
   * @return string The objective ID
   * @throws Exception If database operation fails or authorization denied
   */
  public function registerObjective(LocalObjective $objective): string
  {
    try {
      $data = $objective->toArray();
      
      // Check authorization
      $objectiveScope = [
        'domain' => $data['domain'] ?? null,
        'priority' => $data['priority']
      ];
      
      $authorized = $this->authManager->verifyObjectiveCreationAuth(
        $data['agent_id'],
        $objectiveScope
      );
      
      if (!$authorized) {
        $this->auditLogger->logObjectiveCreation(
          $data['agent_id'],
          $data['objective_id'],
          'denied',
          $data
        );
        throw new Exception('Agent not authorized to create objective');
      }

      $sql = "INSERT INTO :table_rag_agent_objectives 
                (objective_id, agent_id, goal_statement, success_criteria, 
                 priority, estimated_completion_time, status, conflicts_with, 
                 created_at, completed_at, metrics, failure_reason)
              VALUES 
                (:objective_id, :agent_id, :goal_statement, :success_criteria,
                 :priority, :estimated_completion_time, :status, :conflicts_with,
                 :created_at, :completed_at, :metrics, :failure_reason)";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':objective_id', $data['objective_id']);
      $stmt->bindValue(':agent_id', $data['agent_id']);
      $stmt->bindValue(':goal_statement', $data['goal_statement']);
      $stmt->bindValue(':success_criteria', json_encode($data['success_criteria']));
      $stmt->bindValue(':priority', $data['priority']);
      $stmt->bindInt(':estimated_completion_time', $data['estimated_completion_time']);
      $stmt->bindValue(':status', $data['status']);
      $stmt->bindValue(':conflicts_with', $data['conflicts_with']);
      $stmt->bindValue(':created_at', $data['created_at']);
      $stmt->bindValue(':completed_at', $data['completed_at']);
      $stmt->bindValue(':metrics', json_encode($data['metrics']));
      $stmt->bindValue(':failure_reason', $data['failure_reason']);
      $stmt->execute();

      // Log the initial state transition
      $this->logStateTransition(
        $data['objective_id'],
        null,
        'pending',
        'Objective created'
      );
      
      // Log successful creation
      $this->auditLogger->logObjectiveCreation(
        $data['agent_id'],
        $data['objective_id'],
        'success',
        $data
      );

      return $data['objective_id'];
    } catch (Exception $e) {
      // Log failed creation
      if (isset($data)) {
        $this->auditLogger->logObjectiveCreation(
          $data['agent_id'] ?? 'unknown',
          $data['objective_id'] ?? 'unknown',
          'failed',
          $data ?? []
        );
      }
      throw new Exception('Failed to register objective: ' . $e->getMessage());
    }
  }

  /**
   * Update objective status
   *
   * Updates the status of an objective and logs the state transition.
   * Also updates relevant timestamps based on the new status.
   *
   * @param string $objectiveId The objective ID
   * @param string $status The new status
   * @param string|null $reason Optional reason for the status change
   * @throws Exception If objective not found or database operation fails
   */
  public function updateObjectiveStatus(
    string $objectiveId,
    string $status,
    ?string $reason = null
  ): void {
    try {
      // Get current status for logging
      $currentObjective = $this->getObjective($objectiveId);
      if (!$currentObjective) {
        throw new Exception("Objective not found: {$objectiveId}");
      }

      $oldStatus = $currentObjective->getStatus();

      // Build update query based on status
      $updates = ['status = :status'];
      $params = [':status' => $status, ':objective_id' => $objectiveId];

      // Set timestamps based on status
      if ($status === 'approved') {
        $updates[] = 'approved_at = :approved_at';
        $params[':approved_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
      } elseif ($status === 'active') {
        $updates[] = 'started_at = :started_at';
        $params[':started_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
      } elseif (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
        $updates[] = 'completed_at = :completed_at';
        $params[':completed_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
      }

      $sql = "UPDATE :table_rag_agent_objectives 
              SET " . implode(', ', $updates) . "
              WHERE objective_id = :objective_id";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      // Log the state transition
      $this->logStateTransition(
        $objectiveId,
        $oldStatus,
        $status,
        $reason ?? "Status updated to {$status}"
      );
    } catch (Exception $e) {
      throw new Exception('Failed to update objective status: ' . $e->getMessage());
    }
  }

  /**
   * Get an objective by ID
   *
   * Retrieves a LocalObjective from the database by its ID.
   *
   * @param string $objectiveId The objective ID
   * @return LocalObjective|null The objective or null if not found
   */
  public function getObjective(string $objectiveId): ?LocalObjective
  {
    try {
      $sql = "SELECT * FROM :table_rag_agent_objectives 
              WHERE objective_id = :objective_id";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':objective_id', $objectiveId);
      $stmt->execute();

      $row = $stmt->fetch();

      if (!$row) {
        return null;
      }

      return $this->hydrateObjective($row);
    } catch (Exception $e) {
      return null;
    }
  }

  /**
   * Get objectives by agent ID
   *
   * Retrieves all objectives for a specific agent.
   *
   * @param string $agentId The agent ID
   * @return array Array of LocalObjective instances
   */
  public function getObjectivesByAgent(string $agentId): array
  {
    try {
      $sql = "SELECT * FROM :table_rag_agent_objectives 
              WHERE agent_id = :agent_id
              ORDER BY created_at DESC";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->execute();

      $objectives = [];
      while ($row = $stmt->fetch()) {
        $objectives[] = $this->hydrateObjective($row);
      }

      return $objectives;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Get objectives by status
   *
   * Retrieves all objectives with a specific status.
   *
   * @param string $status The status to filter by
   * @return array Array of LocalObjective instances
   */
  public function getObjectivesByStatus(string $status): array
  {
    try {
      $sql = "SELECT * FROM :table_rag_agent_objectives 
              WHERE status = :status
              ORDER BY created_at DESC";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':status', $status);
      $stmt->execute();

      $objectives = [];
      while ($row = $stmt->fetch()) {
        $objectives[] = $this->hydrateObjective($row);
      }

      return $objectives;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Query objectives with filters
   *
   * Flexible query method supporting multiple filter criteria:
   * - agent: Filter by agent ID
   * - status: Filter by status
   * - priority: Filter by priority
   * - created_after: Filter by creation date (DateTime or string)
   * - created_before: Filter by creation date (DateTime or string)
   *
   * @param array $filters Associative array of filter criteria
   * @return array Array of LocalObjective instances
   */
  public function queryObjectives(array $filters): array
  {
    try {
      $conditions = [];
      $params = [];

      // Build WHERE clause from filters
      if (isset($filters['agent'])) {
        $conditions[] = 'agent_id = :agent_id';
        $params[':agent_id'] = $filters['agent'];
      }

      if (isset($filters['status'])) {
        $conditions[] = 'status = :status';
        $params[':status'] = $filters['status'];
      }

      if (isset($filters['priority'])) {
        $conditions[] = 'priority = :priority';
        $params[':priority'] = $filters['priority'];
      }

      if (isset($filters['created_after'])) {
        $conditions[] = 'created_at >= :created_after';
        $date = $filters['created_after'] instanceof \DateTimeInterface
          ? $filters['created_after']->format('Y-m-d H:i:s')
          : $filters['created_after'];
        $params[':created_after'] = $date;
      }

      if (isset($filters['created_before'])) {
        $conditions[] = 'created_at <= :created_before';
        $date = $filters['created_before'] instanceof \DateTimeInterface
          ? $filters['created_before']->format('Y-m-d H:i:s')
          : $filters['created_before'];
        $params[':created_before'] = $date;
      }

      $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

      // Add LIMIT and OFFSET if provided
      $limitClause = '';
      if (isset($filters['limit'])) {
        $limitClause = ' LIMIT ' . (int)$filters['limit'];
        if (isset($filters['offset'])) {
          $limitClause .= ' OFFSET ' . (int)$filters['offset'];
        }
      }

      $sql = "SELECT * FROM :table_rag_agent_objectives 
              {$whereClause}
              ORDER BY created_at DESC{$limitClause}";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $objectives = [];
      while ($row = $stmt->fetch()) {
        $objectives[] = $this->hydrateObjective($row);
      }

      return $objectives;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Get metrics for an objective
   *
   * Retrieves all metrics recorded for a specific objective.
   *
   * @param string $objectiveId The objective ID
   * @return array Array of metrics with name, value, and timestamp
   */
  public function getMetrics(string $objectiveId): array
  {
    try {
      $sql = "SELECT metric_name, metric_value, recorded_at 
              FROM :table_rag_agent_objective_metrics 
              WHERE objective_id = :objective_id
              ORDER BY recorded_at DESC";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':objective_id', $objectiveId);
      $stmt->execute();

      $metrics = [];
      while ($row = $stmt->fetch()) {
        $metrics[] = [
          'name' => $row['metric_name'],
          'value' => (float)$row['metric_value'],
          'recorded_at' => $row['recorded_at']
        ];
      }

      return $metrics;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Record a metric for an objective
   *
   * Stores a performance metric for an objective.
   *
   * @param string $objectiveId The objective ID
   * @param string $metricName The metric name
   * @param float $metricValue The metric value
   * @throws Exception If database operation fails
   */
  public function recordMetric(
    string $objectiveId,
    string $metricName,
    float $metricValue
  ): void {
    try {
      $sql = "INSERT INTO :table_rag_agent_objective_metrics 
              (objective_id, metric_name, metric_value, recorded_at)
              VALUES (:objective_id, :metric_name, :metric_value, :recorded_at)";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':objective_id', $objectiveId);
      $stmt->bindValue(':metric_name', $metricName);
      $stmt->bindValue(':metric_value', $metricValue);
      $stmt->bindValue(':recorded_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->execute();
    } catch (Exception $e) {
      throw new Exception('Failed to record metric: ' . $e->getMessage());
    }
  }

  /**
   * Get overdue objectives
   *
   * Retrieves all objectives that are active and have exceeded their
   * estimated completion time.
   *
   * @return array Array of LocalObjective instances
   */
  public function getOverdueObjectives(): array
  {
    try {
      $sql = "SELECT * FROM :table_rag_agent_objectives 
              WHERE status = 'active'
              ORDER BY created_at ASC";

      $stmt = $this->db->prepare($sql);
      $stmt->execute();

      $overdueObjectives = [];
      while ($row = $stmt->fetch()) {
        $objective = $this->hydrateObjective($row);
        if ($objective && $objective->isOverdue()) {
          $overdueObjectives[] = $objective;
        }
      }

      return $overdueObjectives;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Cancel an objective
   *
   * Marks an objective as cancelled and records the reason.
   *
   * @param string $objectiveId The objective ID
   * @param string $reason The cancellation reason
   * @throws Exception If database operation fails
   */
  public function cancelObjective(string $objectiveId, string $reason): void
  {
    try {
      $sql = "UPDATE :table_rag_agent_objectives 
              SET status = 'cancelled',
                  completed_at = :completed_at,
                  failure_reason = :reason
              WHERE objective_id = :objective_id";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':objective_id', $objectiveId);
      $stmt->bindValue(':completed_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->bindValue(':reason', $reason);
      $stmt->execute();

      // Get current status for logging
      $objective = $this->getObjective($objectiveId);
      $oldStatus = $objective ? $objective->getStatus() : 'unknown';

      // Log the state transition
      $this->logStateTransition(
        $objectiveId,
        $oldStatus,
        'cancelled',
        $reason
      );
    } catch (Exception $e) {
      throw new Exception('Failed to cancel objective: ' . $e->getMessage());
    }
  }

  /**
   * Mark an objective as completed
   *
   * Updates the objective status to completed, records completion time,
   * and stores performance metrics.
   *
   * @param string $objectiveId The objective ID
   * @param array $metrics Performance metrics for the completed objective
   * @throws Exception If objective not found or database operation fails
   */
  public function markCompleted(string $objectiveId, array $metrics): void
  {
    try {
      // Get current objective for validation and logging
      $objective = $this->getObjective($objectiveId);
      if (!$objective) {
        throw new Exception("Objective not found: {$objectiveId}");
      }

      $oldStatus = $objective->getStatus();

      // Update objective in database
      $sql = "UPDATE :table_rag_agent_objectives 
              SET status = 'completed',
                  completed_at = :completed_at,
                  metrics = :metrics
              WHERE objective_id = :objective_id";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':objective_id', $objectiveId);
      $stmt->bindValue(':completed_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->bindValue(':metrics', json_encode($metrics));
      $stmt->execute();

      // Store individual metrics in metrics table
      foreach ($metrics as $metricName => $metricValue) {
        if (is_numeric($metricValue)) {
          $this->recordMetric($objectiveId, $metricName, (float)$metricValue);
        }
      }

      // Log the state transition
      $this->logStateTransition(
        $objectiveId,
        $oldStatus,
        'completed',
        'Objective completed successfully with metrics'
      );
    } catch (Exception $e) {
      throw new Exception('Failed to mark objective as completed: ' . $e->getMessage());
    }
  }

  /**
   * Mark an objective as failed
   *
   * Updates the objective status to failed, records completion time,
   * and stores the failure reason.
   *
   * @param string $objectiveId The objective ID
   * @param string $reason Explanation of why the objective failed
   * @throws Exception If objective not found or database operation fails
   */
  public function markFailed(string $objectiveId, string $reason): void
  {
    try {
      // Get current objective for validation and logging
      $objective = $this->getObjective($objectiveId);
      if (!$objective) {
        throw new Exception("Objective not found: {$objectiveId}");
      }

      $oldStatus = $objective->getStatus();

      // Update objective in database
      $sql = "UPDATE :table_rag_agent_objectives 
              SET status = 'failed',
                  completed_at = :completed_at,
                  failure_reason = :reason
              WHERE objective_id = :objective_id";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':objective_id', $objectiveId);
      $stmt->bindValue(':completed_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->bindValue(':reason', $reason);
      $stmt->execute();

      // Log the state transition
      $this->logStateTransition(
        $objectiveId,
        $oldStatus,
        'failed',
        $reason
      );
    } catch (Exception $e) {
      throw new Exception('Failed to mark objective as failed: ' . $e->getMessage());
    }
  }

  /**
   * Log a state transition
   *
   * Records a state transition to the database with timestamp and reason.
   * Provides complete audit trail of all objective status changes.
   *
   * @param string $objectiveId The objective ID
   * @param string|null $oldStatus The previous status
   * @param string $newStatus The new status
   * @param string $reason The reason for the transition
   * @throws Exception If database operation fails
   */
  private function logStateTransition(
    string $objectiveId,
    ?string $oldStatus,
    string $newStatus,
    string $reason
  ): void {
    try {
      // Store in memory for backward compatibility
      $this->stateTransitionLog[] = [
        'objective_id' => $objectiveId,
        'old_status' => $oldStatus,
        'new_status' => $newStatus,
        'reason' => $reason,
        'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s')
      ];

      // Persist to database
      $sql = "INSERT INTO :table_rag_agent_objective_state_transitions 
              (objective_id, old_status, new_status, transition_reason, transitioned_at)
              VALUES (:objective_id, :old_status, :new_status, :reason, :transitioned_at)";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':objective_id', $objectiveId);
      $stmt->bindValue(':old_status', $oldStatus);
      $stmt->bindValue(':new_status', $newStatus);
      $stmt->bindValue(':reason', $reason);
      $stmt->bindValue(':transitioned_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->execute();
    } catch (Exception $e) {
      // Log error but don't fail the operation
      error_log('Failed to log state transition: ' . $e->getMessage());
    }
  }

  /**
   * Get state transition log from database
   *
   * Retrieves the complete history of state transitions for an objective.
   *
   * @param string $objectiveId The objective ID
   * @return array Array of state transitions with timestamps and reasons
   */
  public function getStateTransitionLog(string $objectiveId): array
  {
    try {
      $sql = "SELECT objective_id, old_status, new_status, transition_reason, transitioned_at
              FROM :table_rag_agent_objective_state_transitions 
              WHERE objective_id = :objective_id
              ORDER BY transitioned_at ASC";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':objective_id', $objectiveId);
      $stmt->execute();

      $transitions = [];
      while ($row = $stmt->fetch()) {
        $transitions[] = [
          'objective_id' => $row['objective_id'],
          'old_status' => $row['old_status'],
          'new_status' => $row['new_status'],
          'reason' => $row['transition_reason'],
          'timestamp' => $row['transitioned_at']
        ];
      }

      return $transitions;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Get all state transitions for multiple objectives
   *
   * Retrieves state transitions for a set of objectives, useful for
   * analyzing patterns across multiple objectives.
   *
   * @param array $objectiveIds Array of objective IDs
   * @return array Array of state transitions grouped by objective
   */
  public function getStateTransitionsForObjectives(array $objectiveIds): array
  {
    if (empty($objectiveIds)) {
      return [];
    }

    try {
      $placeholders = implode(',', array_fill(0, count($objectiveIds), '?'));
      $sql = "SELECT objective_id, old_status, new_status, transition_reason, transitioned_at
              FROM :table_rag_agent_objective_state_transitions 
              WHERE objective_id IN ({$placeholders})
              ORDER BY objective_id, transitioned_at ASC";

      $stmt = $this->db->prepare($sql);
      foreach ($objectiveIds as $index => $id) {
        $stmt->bindValue($index + 1, $id);
      }
      $stmt->execute();

      $transitions = [];
      while ($row = $stmt->fetch()) {
        $objectiveId = $row['objective_id'];
        if (!isset($transitions[$objectiveId])) {
          $transitions[$objectiveId] = [];
        }
        $transitions[$objectiveId][] = [
          'old_status' => $row['old_status'],
          'new_status' => $row['new_status'],
          'reason' => $row['transition_reason'],
          'timestamp' => $row['transitioned_at']
        ];
      }

      return $transitions;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Find related objectives using similarity search
   *
   * Searches for objectives that are related to the given objective based on
   * similarity of goals and success criteria. Excludes the objective itself
   * and filters by minimum similarity threshold.
   *
   * @param LocalObjective $objective The objective to find related objectives for
   * @param float $minSimilarity Minimum similarity threshold (0.0 - 1.0), default 0.3
   * @param array $statuses Optional array of statuses to filter by (default: active, pending, approved)
   * @return array Array of related objectives with similarity scores:
   *               - objective: The related LocalObjective
   *               - similarity: Similarity score (0.0 - 1.0)
   *               - agent_id: ID of the agent owning the related objective
   */
  public function findRelatedObjectives(
    LocalObjective $objective,
    float $minSimilarity = 0.3,
    array $statuses = ['active', 'pending', 'approved']
  ): array {
    try {
      // Get ConflictDetector for similarity calculation
      $conflictDetector = new ConflictDetector($this);

      $relatedObjectives = [];

      // Query objectives with specified statuses
      foreach ($statuses as $status) {
        $objectives = $this->getObjectivesByStatus($status);

        foreach ($objectives as $candidate) {
          // Skip the objective itself
          if ($candidate->getId() === $objective->getId()) {
            continue;
          }

          // Calculate similarity
          $similarity = $conflictDetector->calculateSimilarity($objective, $candidate);

          // Include if above threshold
          if ($similarity >= $minSimilarity) {
            $relatedObjectives[] = [
              'objective' => $candidate,
              'similarity' => $similarity,
              'agent_id' => $candidate->getAgentId()
            ];
          }
        }
      }

      // Sort by similarity (highest first)
      usort($relatedObjectives, function($a, $b) {
        return $b['similarity'] <=> $a['similarity'];
      });

      return $relatedObjectives;
    } catch (Exception $e) {
      error_log('Failed to find related objectives: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Notify agents about related objectives
   *
   * Creates notifications for agents about potential collaboration opportunities
   * when related objectives are detected. Stores notifications in the database
   * for later retrieval.
   *
   * @param LocalObjective $newObjective The newly created objective
   * @param array $relatedObjectives Array of related objectives from findRelatedObjectives()
   * @throws Exception If database operation fails
   */
  public function notifyRelatedObjectives(
    LocalObjective $newObjective,
    array $relatedObjectives
  ): void {
    try {
      foreach ($relatedObjectives as $related) {
        $relatedObjective = $related['objective'];
        $similarity = $related['similarity'];

        // Create notification for the agent owning the related objective
        $this->createCollaborationNotification(
          $relatedObjective->getAgentId(),
          $newObjective->getAgentId(),
          $newObjective->getId(),
          $relatedObjective->getId(),
          $similarity,
          'related_objective_detected'
        );

        // Also notify the new objective's agent about the existing related objective
        $this->createCollaborationNotification(
          $newObjective->getAgentId(),
          $relatedObjective->getAgentId(),
          $relatedObjective->getId(),
          $newObjective->getId(),
          $similarity,
          'related_objective_exists'
        );
      }
    } catch (Exception $e) {
      throw new Exception('Failed to notify related objectives: ' . $e->getMessage());
    }
  }

  /**
   * Create a collaboration notification
   *
   * Stores a notification in the database about a potential collaboration opportunity.
   *
   * @param string $targetAgentId Agent to notify
   * @param string $sourceAgentId Agent who created the related objective
   * @param string $sourceObjectiveId ID of the related objective
   * @param string $targetObjectiveId ID of the target agent's objective
   * @param float $similarity Similarity score
   * @param string $notificationType Type of notification
   * @throws Exception If database operation fails
   */
  private function createCollaborationNotification(
    string $targetAgentId,
    string $sourceAgentId,
    string $sourceObjectiveId,
    string $targetObjectiveId,
    float $similarity,
    string $notificationType
  ): void {
    try {
      $sql = "INSERT INTO :table_rag_agent_objective_notifications 
              (notification_id, target_agent_id, source_agent_id, 
               source_objective_id, target_objective_id, similarity_score,
               notification_type, status, created_at)
              VALUES 
              (:notification_id, :target_agent_id, :source_agent_id,
               :source_objective_id, :target_objective_id, :similarity_score,
               :notification_type, :status, :created_at)";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':notification_id', $this->generateUuid());
      $stmt->bindValue(':target_agent_id', $targetAgentId);
      $stmt->bindValue(':source_agent_id', $sourceAgentId);
      $stmt->bindValue(':source_objective_id', $sourceObjectiveId);
      $stmt->bindValue(':target_objective_id', $targetObjectiveId);
      $stmt->bindValue(':similarity_score', $similarity);
      $stmt->bindValue(':notification_type', $notificationType);
      $stmt->bindValue(':status', 'pending');
      $stmt->bindValue(':created_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->execute();
    } catch (Exception $e) {
      throw new Exception('Failed to create collaboration notification: ' . $e->getMessage());
    }
  }

  /**
   * Get pending notifications for an agent
   *
   * Retrieves all pending collaboration notifications for a specific agent.
   *
   * @param string $agentId The agent ID
   * @return array Array of notifications with objective details
   */
  public function getPendingNotifications(string $agentId): array
  {
    try {
      $sql = "SELECT n.*, 
                     o1.goal_statement as source_goal,
                     o2.goal_statement as target_goal
              FROM :table_rag_agent_objective_notifications n
              LEFT JOIN :table_rag_agent_objectives o1 ON n.source_objective_id = o1.objective_id
              LEFT JOIN :table_rag_agent_objectives o2 ON n.target_objective_id = o2.objective_id
              WHERE n.target_agent_id = :agent_id 
              AND n.status = 'pending'
              ORDER BY n.created_at DESC";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->execute();

      $notifications = [];
      while ($row = $stmt->fetch()) {
        $notifications[] = [
          'notification_id' => $row['notification_id'],
          'source_agent_id' => $row['source_agent_id'],
          'source_objective_id' => $row['source_objective_id'],
          'target_objective_id' => $row['target_objective_id'],
          'similarity_score' => (float)$row['similarity_score'],
          'notification_type' => $row['notification_type'],
          'source_goal' => $row['source_goal'],
          'target_goal' => $row['target_goal'],
          'created_at' => $row['created_at']
        ];
      }

      return $notifications;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Acknowledge a notification
   *
   * Marks a notification as acknowledged by the agent.
   *
   * @param string $notificationId The notification ID
   * @param string $response Optional response from the agent ('accepted', 'declined', 'deferred')
   * @throws Exception If database operation fails
   */
  public function acknowledgeNotification(
    string $notificationId,
    string $response = 'acknowledged'
  ): void {
    try {
      $sql = "UPDATE :table_rag_agent_objective_notifications 
              SET status = :status,
                  acknowledged_at = :acknowledged_at,
                  agent_response = :response
              WHERE notification_id = :notification_id";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':notification_id', $notificationId);
      $stmt->bindValue(':status', 'acknowledged');
      $stmt->bindValue(':acknowledged_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->bindValue(':response', $response);
      $stmt->execute();
    } catch (Exception $e) {
      throw new Exception('Failed to acknowledge notification: ' . $e->getMessage());
    }
  }

  /**
   * Generate a UUID v4
   *
   * @return string UUID string
   */
  private function generateUuid(): string
  {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
  }

  /**
   * Create a collaborative objective from merge suggestion
   *
   * Creates a new collaborative objective by merging two existing objectives.
   * Tracks both participating agents and their contributions.
   *
   * @param LocalObjective $obj1 First objective to merge
   * @param LocalObjective $obj2 Second objective to merge
   * @param array $mergeSuggestion Merge suggestion from ConflictDetector::suggestMerge()
   * @return string The ID of the newly created collaborative objective
   * @throws Exception If database operation fails
   */
  public function createCollaborativeObjective(
    LocalObjective $obj1,
    LocalObjective $obj2,
    array $mergeSuggestion
  ): string {
    try {
      // Create a new collaborative objective
      $collaborativeObjective = new LocalObjective(
        'collaborative', // Special agent ID for collaborative objectives
        $mergeSuggestion['combined_goal'],
        $mergeSuggestion['combined_criteria'],
        $mergeSuggestion['priority'],
        $mergeSuggestion['estimated_time']
      );

      // Register the collaborative objective
      $collaborativeId = $this->registerObjective($collaborativeObjective);

      // Track the collaboration in the database
      $this->createCollaborationRecord(
        $collaborativeId,
        [$obj1->getId(), $obj2->getId()],
        [$obj1->getAgentId(), $obj2->getAgentId()],
        $mergeSuggestion['similarity']
      );

      // Mark original objectives as merged
      $this->updateObjectiveStatus($obj1->getId(), 'cancelled', 'Merged into collaborative objective ' . $collaborativeId);
      $this->updateObjectiveStatus($obj2->getId(), 'cancelled', 'Merged into collaborative objective ' . $collaborativeId);

      return $collaborativeId;
    } catch (Exception $e) {
      throw new Exception('Failed to create collaborative objective: ' . $e->getMessage());
    }
  }

  /**
   * Create a collaboration record
   *
   * Stores information about a collaborative objective including participating
   * agents and their original objectives.
   *
   * @param string $collaborativeObjectiveId ID of the collaborative objective
   * @param array $originalObjectiveIds Array of original objective IDs
   * @param array $participatingAgents Array of agent IDs
   * @param float $similarity Similarity score that led to the merge
   * @throws Exception If database operation fails
   */
  private function createCollaborationRecord(
    string $collaborativeObjectiveId,
    array $originalObjectiveIds,
    array $participatingAgents,
    float $similarity
  ): void {
    try {
      $sql = "INSERT INTO :table_rag_agent_objective_collaborations 
              (collaboration_id, collaborative_objective_id, original_objectives,
               participating_agents, similarity_score, created_at)
              VALUES 
              (:collaboration_id, :collaborative_objective_id, :original_objectives,
               :participating_agents, :similarity_score, :created_at)";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':collaboration_id', $this->generateUuid());
      $stmt->bindValue(':collaborative_objective_id', $collaborativeObjectiveId);
      $stmt->bindValue(':original_objectives', json_encode($originalObjectiveIds));
      $stmt->bindValue(':participating_agents', json_encode($participatingAgents));
      $stmt->bindValue(':similarity_score', $similarity);
      $stmt->bindValue(':created_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->execute();

      // Initialize contribution tracking for each agent
      foreach ($participatingAgents as $agentId) {
        $this->initializeContribution($collaborativeObjectiveId, $agentId);
      }
    } catch (Exception $e) {
      throw new Exception('Failed to create collaboration record: ' . $e->getMessage());
    }
  }

  /**
   * Initialize contribution tracking for an agent
   *
   * Creates an initial contribution record for an agent participating in
   * a collaborative objective.
   *
   * @param string $collaborativeObjectiveId ID of the collaborative objective
   * @param string $agentId ID of the participating agent
   * @throws Exception If database operation fails
   */
  private function initializeContribution(
    string $collaborativeObjectiveId,
    string $agentId
  ): void {
    try {
      $sql = "INSERT INTO :table_rag_agent_objective_contributions 
              (contribution_id, collaborative_objective_id, agent_id,
               contribution_type, contribution_value, recorded_at)
              VALUES 
              (:contribution_id, :collaborative_objective_id, :agent_id,
               :contribution_type, :contribution_value, :recorded_at)";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':contribution_id', $this->generateUuid());
      $stmt->bindValue(':collaborative_objective_id', $collaborativeObjectiveId);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindValue(':contribution_type', 'initialization');
      $stmt->bindValue(':contribution_value', json_encode(['status' => 'joined']));
      $stmt->bindValue(':recorded_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->execute();
    } catch (Exception $e) {
      throw new Exception('Failed to initialize contribution: ' . $e->getMessage());
    }
  }

  /**
   * Record a contribution to a collaborative objective
   *
   * Tracks specific contributions made by agents to collaborative objectives.
   * Contributions can include work completed, resources provided, or milestones achieved.
   *
   * @param string $collaborativeObjectiveId ID of the collaborative objective
   * @param string $agentId ID of the contributing agent
   * @param string $contributionType Type of contribution (e.g., 'work_completed', 'milestone_achieved')
   * @param array $contributionValue Details of the contribution
   * @throws Exception If database operation fails
   */
  public function recordContribution(
    string $collaborativeObjectiveId,
    string $agentId,
    string $contributionType,
    array $contributionValue
  ): void {
    try {
      $sql = "INSERT INTO :table_rag_agent_objective_contributions 
              (contribution_id, collaborative_objective_id, agent_id,
               contribution_type, contribution_value, recorded_at)
              VALUES 
              (:contribution_id, :collaborative_objective_id, :agent_id,
               :contribution_type, :contribution_value, :recorded_at)";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':contribution_id', $this->generateUuid());
      $stmt->bindValue(':collaborative_objective_id', $collaborativeObjectiveId);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindValue(':contribution_type', $contributionType);
      $stmt->bindValue(':contribution_value', json_encode($contributionValue));
      $stmt->bindValue(':recorded_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->execute();
    } catch (Exception $e) {
      throw new Exception('Failed to record contribution: ' . $e->getMessage());
    }
  }

  /**
   * Get contributions for a collaborative objective
   *
   * Retrieves all contributions made to a collaborative objective,
   * grouped by agent.
   *
   * @param string $collaborativeObjectiveId ID of the collaborative objective
   * @return array Array of contributions grouped by agent ID
   */
  public function getContributions(string $collaborativeObjectiveId): array
  {
    try {
      $sql = "SELECT agent_id, contribution_type, contribution_value, recorded_at
              FROM :table_rag_agent_objective_contributions 
              WHERE collaborative_objective_id = :collaborative_objective_id
              ORDER BY agent_id, recorded_at ASC";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':collaborative_objective_id', $collaborativeObjectiveId);
      $stmt->execute();

      $contributions = [];
      while ($row = $stmt->fetch()) {
        $agentId = $row['agent_id'];
        if (!isset($contributions[$agentId])) {
          $contributions[$agentId] = [];
        }
        $contributions[$agentId][] = [
          'type' => $row['contribution_type'],
          'value' => json_decode($row['contribution_value'], true),
          'recorded_at' => $row['recorded_at']
        ];
      }

      return $contributions;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Get collaboration details for an objective
   *
   * Retrieves information about a collaborative objective including
   * participating agents and original objectives.
   *
   * @param string $collaborativeObjectiveId ID of the collaborative objective
   * @return array|null Collaboration details or null if not found
   */
  public function getCollaborationDetails(string $collaborativeObjectiveId): ?array
  {
    try {
      $sql = "SELECT * FROM :table_rag_agent_objective_collaborations 
              WHERE collaborative_objective_id = :collaborative_objective_id";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':collaborative_objective_id', $collaborativeObjectiveId);
      $stmt->execute();

      $row = $stmt->fetch();

      if (!$row) {
        return null;
      }

      return [
        'collaboration_id' => $row['collaboration_id'],
        'collaborative_objective_id' => $row['collaborative_objective_id'],
        'original_objectives' => json_decode($row['original_objectives'], true),
        'participating_agents' => json_decode($row['participating_agents'], true),
        'similarity_score' => (float)$row['similarity_score'],
        'created_at' => $row['created_at']
      ];
    } catch (Exception $e) {
      return null;
    }
  }

  /**
   * Get all collaborative objectives for an agent
   *
   * Retrieves all collaborative objectives that an agent is participating in.
   *
   * @param string $agentId The agent ID
   * @return array Array of collaborative objective IDs
   */
  public function getCollaborativeObjectivesForAgent(string $agentId): array
  {
    try {
      $sql = "SELECT collaborative_objective_id 
              FROM :table_rag_agent_objective_collaborations 
              WHERE JSON_CONTAINS(participating_agents, :agent_id, '$')";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', json_encode($agentId));
      $stmt->execute();

      $objectiveIds = [];
      while ($row = $stmt->fetch()) {
        $objectiveIds[] = $row['collaborative_objective_id'];
      }

      return $objectiveIds;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Calculate contribution attribution for a collaborative objective
   *
   * Analyzes all contributions and calculates the percentage attribution
   * for each participating agent based on their contributions.
   *
   * @param string $collaborativeObjectiveId ID of the collaborative objective
   * @return array Array of agent IDs with their attribution percentages
   */
  public function calculateContributionAttribution(string $collaborativeObjectiveId): array
  {
    try {
      $contributions = $this->getContributions($collaborativeObjectiveId);

      if (empty($contributions)) {
        return [];
      }

      // Calculate contribution scores for each agent
      $scores = [];
      $totalScore = 0;

      foreach ($contributions as $agentId => $agentContributions) {
        $agentScore = 0;

        foreach ($agentContributions as $contribution) {
          // Weight different contribution types
          $weight = match($contribution['type']) {
            'initialization' => 1,
            'work_completed' => 5,
            'milestone_achieved' => 10,
            'resource_provided' => 3,
            'review_completed' => 2,
            default => 1
          };

          $agentScore += $weight;
        }

        $scores[$agentId] = $agentScore;
        $totalScore += $agentScore;
      }

      // Calculate percentages
      $attributions = [];
      foreach ($scores as $agentId => $score) {
        $attributions[$agentId] = [
          'score' => $score,
          'percentage' => $totalScore > 0 ? ($score / $totalScore) * 100 : 0,
          'contribution_count' => count($contributions[$agentId])
        ];
      }

      // Sort by percentage (highest first)
      uasort($attributions, function($a, $b) {
        return $b['percentage'] <=> $a['percentage'];
      });

      return $attributions;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Get count of objectives matching filters
   *
   * Returns the total count of objectives matching the given filter criteria.
   * Useful for pagination.
   *
   * @param array $filters Associative array of filter criteria
   * @return int Total count of matching objectives
   */
  public function getObjectivesCount(array $filters): int
  {
    try {
      $conditions = [];
      $params = [];

      // Build WHERE clause from filters (same logic as queryObjectives)
      if (isset($filters['agent_id'])) {
        $conditions[] = 'agent_id = :agent_id';
        $params[':agent_id'] = $filters['agent_id'];
      }

      if (isset($filters['status'])) {
        $conditions[] = 'status = :status';
        $params[':status'] = $filters['status'];
      }

      if (isset($filters['priority'])) {
        $conditions[] = 'priority = :priority';
        $params[':priority'] = $filters['priority'];
      }

      if (isset($filters['created_after'])) {
        $conditions[] = 'created_at >= :created_after';
        $date = $filters['created_after'] instanceof \DateTimeInterface
          ? $filters['created_after']->format('Y-m-d H:i:s')
          : $filters['created_after'];
        $params[':created_after'] = $date;
      }

      if (isset($filters['created_before'])) {
        $conditions[] = 'created_at <= :created_before';
        $date = $filters['created_before'] instanceof \DateTimeInterface
          ? $filters['created_before']->format('Y-m-d H:i:s')
          : $filters['created_before'];
        $params[':created_before'] = $date;
      }

      $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

      $sql = "SELECT COUNT(*) as total FROM :table_rag_agent_objectives {$whereClause}";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $row = $stmt->fetch();
      return (int)($row['total'] ?? 0);
    } catch (Exception $e) {
      return 0;
    }
  }

  /**
   * Hydrate a LocalObjective from database row
   *
   * Converts a database row into a LocalObjective instance.
   * Uses closure binding to set private properties without deprecated setAccessible().
   *
   * @param array $row Database row
   * @return LocalObjective|null The hydrated objective or null on error
   */
  private function hydrateObjective(array $row): ?LocalObjective
  {
    try {
      // Create a new objective with minimal data
      $objective = new LocalObjective(
        $row['agent_id'],
        $row['goal_statement'],
        json_decode($row['success_criteria'], true),
        $row['priority'],
        (int)$row['estimated_completion_time']
      );

      // Use closure binding to set private properties (PHP 8.5 compatible)
      $hydrator = function() use ($row) {
        $this->objectiveId = $row['objective_id'];
        $this->status = $row['status'];
        
        if ($row['conflicts_with']) {
          $this->conflictsWith = $row['conflicts_with'];
        }
        
        $this->createdAt = new DateTimeImmutable($row['created_at']);
        
        if ($row['completed_at']) {
          $this->completedAt = new DateTimeImmutable($row['completed_at']);
        }
        
        if ($row['metrics']) {
          $this->metrics = json_decode($row['metrics'], true);
        }
        
        if ($row['failure_reason']) {
          $this->failureReason = $row['failure_reason'];
        }
      };

      // Bind the closure to the objective instance
      $boundHydrator = \Closure::bind($hydrator, $objective, LocalObjective::class);
      $boundHydrator();

      return $objective;
    } catch (Exception $e) {
      return null;
    }
  }
}
