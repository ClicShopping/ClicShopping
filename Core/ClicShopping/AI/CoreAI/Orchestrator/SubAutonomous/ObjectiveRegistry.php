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
