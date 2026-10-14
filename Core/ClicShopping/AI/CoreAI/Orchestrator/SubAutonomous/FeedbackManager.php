<?php
/**
 * ClicShopping AI - Agent Local Objectives and Evaluation System
 *
 * @copyright 2025 ClicShopping(tm). All rights reserved.
 * @license   MIT License
 * @version   1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use DateTimeImmutable;
use Exception;

/**
 * FeedbackManager Class
 *
 * Manages collaborative feedback delivery and tracking between autonomous agents.
 * Provides database persistence for feedback, acknowledgment tracking, feedback history,
 * categorization, and improvement pattern analysis.
 *
 * This class handles agent-to-agent feedback (stored in rag_agent_inter_feedback table),
 * which is distinct from user feedback on chat interactions.
 *
 * Requirements: 5.1, 5.2, 5.3, 5.4, 5.5
 */
class FeedbackManager
{
  private $db;

  /**
   * Constructor
   *
   * Initializes the feedback manager with database connection.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Deliver feedback to a target agent
   *
   * Creates and persists feedback from one agent to another regarding a specific output.
   * The feedback is structured with type categorization and detailed text.
   *
   * @param string $targetAgentId The agent receiving the feedback
   * @param string $outputId The output that triggered the feedback
   * @param array $feedback Feedback data containing:
   *                        - source_agent_id: Agent providing feedback
   *                        - feedback_type: Type (correctness, efficiency, completeness, best_practice)
   *                        - feedback_text: Detailed feedback content
   *                        - strengths: Array of identified strengths (optional)
   *                        - improvements: Array of suggested improvements (optional)
   * @return string The feedback ID
   * @throws Exception If required fields are missing or database operation fails
   */
  public function deliverFeedback(
    string $targetAgentId,
    string $outputId,
    array $feedback
  ): string {
    // Validate required fields
    if (empty($targetAgentId)) {
      throw new Exception('Target agent ID cannot be empty');
    }

    if (empty($outputId)) {
      throw new Exception('Output ID cannot be empty');
    }

    if (!isset($feedback['source_agent_id']) || empty($feedback['source_agent_id'])) {
      throw new Exception('Source agent ID is required in feedback data');
    }

    if (!isset($feedback['feedback_type']) || empty($feedback['feedback_type'])) {
      throw new Exception('Feedback type is required');
    }

    if (!isset($feedback['feedback_text']) || empty($feedback['feedback_text'])) {
      throw new Exception('Feedback text is required');
    }

    // Validate feedback type
    $validTypes = ['correctness', 'efficiency', 'completeness', 'best_practice'];
    if (!in_array($feedback['feedback_type'], $validTypes, true)) {
      throw new Exception('Invalid feedback type. Must be one of: ' . implode(', ', $validTypes));
    }

    try {
      $feedbackId = $this->generateFeedbackId();

      $sql = "INSERT INTO :table_rag_agent_inter_feedback 
              (feedback_id, target_agent_id, source_agent_id, output_id, 
               feedback_type, feedback_text, acknowledged, agent_response, 
               created_at, acknowledged_at)
              VALUES 
              (:feedback_id, :target_agent_id, :source_agent_id, :output_id,
               :feedback_type, :feedback_text, :acknowledged, :agent_response,
               :created_at, :acknowledged_at)";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':feedback_id', $feedbackId);
      $stmt->bindValue(':target_agent_id', $targetAgentId);
      $stmt->bindValue(':source_agent_id', $feedback['source_agent_id']);
      $stmt->bindValue(':output_id', $outputId);
      $stmt->bindValue(':feedback_type', $feedback['feedback_type']);
      $stmt->bindValue(':feedback_text', $feedback['feedback_text']);
      $stmt->bindValue(':acknowledged', false);
      $stmt->bindValue(':agent_response', null);
      $stmt->bindValue(':created_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->bindValue(':acknowledged_at', null);
      $stmt->execute();

      return $feedbackId;
    } catch (Exception $e) {
      throw new Exception('Failed to deliver feedback: ' . $e->getMessage());
    }
  }

  /**
   * Acknowledge feedback
   *
   * Marks feedback as acknowledged by the target agent and optionally records
   * a response from the agent.
   *
   * @param string $feedbackId The feedback ID to acknowledge
   * @param string $agentId The agent acknowledging the feedback (must be target agent)
   * @param string|null $response Optional response from the agent
   * @throws Exception If feedback not found, agent mismatch, or database operation fails
   */
  public function acknowledgeFeedback(
    string $feedbackId,
    string $agentId,
    ?string $response = null
  ): void {
    try {
      // Verify the feedback exists and belongs to this agent
      $sql = "SELECT target_agent_id, acknowledged 
              FROM :table_rag_agent_inter_feedback 
              WHERE feedback_id = :feedback_id";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':feedback_id', $feedbackId);
      $stmt->execute();

      $row = $stmt->fetch();

      if (!$row) {
        throw new Exception("Feedback not found: {$feedbackId}");
      }

      if ($row['target_agent_id'] !== $agentId) {
        throw new Exception("Agent {$agentId} is not the target of feedback {$feedbackId}");
      }

      if ($row['acknowledged']) {
        throw new Exception("Feedback {$feedbackId} has already been acknowledged");
      }

      // Update feedback with acknowledgment
      $sql = "UPDATE :table_rag_agent_inter_feedback 
              SET acknowledged = :acknowledged,
                  agent_response = :agent_response,
                  acknowledged_at = :acknowledged_at
              WHERE feedback_id = :feedback_id";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':feedback_id', $feedbackId);
      $stmt->bindValue(':acknowledged', true);
      $stmt->bindValue(':agent_response', $response);
      $stmt->bindValue(':acknowledged_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->execute();
    } catch (Exception $e) {
      throw new Exception('Failed to acknowledge feedback: ' . $e->getMessage());
    }
  }

  /**
   * Get feedback history for an agent
   *
   * Retrieves the complete history of feedback received by a specific agent,
   * ordered chronologically from most recent to oldest.
   *
   * @param string $agentId The agent ID
   * @param array $filters Optional filters:
   *                       - acknowledged: bool - Filter by acknowledgment status
   *                       - feedback_type: string - Filter by feedback type
   *                       - source_agent_id: string - Filter by source agent
   *                       - limit: int - Limit number of results
   * @return array Array of feedback records with all details
   */
  public function getFeedbackHistory(string $agentId, array $filters = []): array
  {
    try {
      $conditions = ['target_agent_id = :agent_id'];
      $params = [':agent_id' => $agentId];

      // Apply filters
      if (isset($filters['acknowledged'])) {
        $conditions[] = 'acknowledged = :acknowledged';
        $params[':acknowledged'] = $filters['acknowledged'];
      }

      if (isset($filters['feedback_type'])) {
        $conditions[] = 'feedback_type = :feedback_type';
        $params[':feedback_type'] = $filters['feedback_type'];
      }

      if (isset($filters['source_agent_id'])) {
        $conditions[] = 'source_agent_id = :source_agent_id';
        $params[':source_agent_id'] = $filters['source_agent_id'];
      }

      $whereClause = implode(' AND ', $conditions);
      $limitClause = isset($filters['limit']) ? 'LIMIT ' . (int)$filters['limit'] : '';

      $sql = "SELECT feedback_id, target_agent_id, source_agent_id, output_id,
                     feedback_type, feedback_text, acknowledged, agent_response,
                     created_at, acknowledged_at
              FROM :table_rag_agent_inter_feedback 
              WHERE {$whereClause}
              ORDER BY created_at DESC
              {$limitClause}";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $history = [];
      while ($row = $stmt->fetch()) {
        $history[] = [
          'feedback_id' => $row['feedback_id'],
          'target_agent_id' => $row['target_agent_id'],
          'source_agent_id' => $row['source_agent_id'],
          'output_id' => $row['output_id'],
          'feedback_type' => $row['feedback_type'],
          'feedback_text' => $row['feedback_text'],
          'acknowledged' => (bool)$row['acknowledged'],
          'agent_response' => $row['agent_response'],
          'created_at' => $row['created_at'],
          'acknowledged_at' => $row['acknowledged_at']
        ];
      }

      return $history;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Categorize feedback
   *
   * Analyzes a collection of feedback and groups it by type, providing
   * statistics and insights about the feedback distribution.
   *
   * @param array $feedback Array of feedback records (from getFeedbackHistory)
   * @return array Categorized feedback with statistics:
   *               - by_type: Feedback grouped by type
   *               - statistics: Count and percentage for each type
   *               - total_count: Total number of feedback items
   */
  public function categorizeFeedback(array $feedback): array
  {
    $categorized = [
      'by_type' => [
        'correctness' => [],
        'efficiency' => [],
        'completeness' => [],
        'best_practice' => []
      ],
      'statistics' => [
        'correctness' => ['count' => 0, 'percentage' => 0],
        'efficiency' => ['count' => 0, 'percentage' => 0],
        'completeness' => ['count' => 0, 'percentage' => 0],
        'best_practice' => ['count' => 0, 'percentage' => 0]
      ],
      'total_count' => count($feedback)
    ];

    // Group feedback by type
    foreach ($feedback as $item) {
      $type = $item['feedback_type'];
      if (isset($categorized['by_type'][$type])) {
        $categorized['by_type'][$type][] = $item;
        $categorized['statistics'][$type]['count']++;
      }
    }

    // Calculate percentages
    if ($categorized['total_count'] > 0) {
      foreach ($categorized['statistics'] as $type => &$stats) {
        $stats['percentage'] = round(($stats['count'] / $categorized['total_count']) * 100, 2);
      }
    }

    return $categorized;
  }

  /**
   * Track improvement patterns
   *
   * Analyzes feedback history to identify patterns and trends in agent performance.
   * Provides insights into areas of improvement, recurring issues, and progress over time.
   *
   * @param string $agentId The agent ID to analyze
   * @param array $options Analysis options:
   *                       - time_period: int - Number of days to analyze (default: 30)
   *                       - min_feedback_count: int - Minimum feedback items for pattern detection (default: 5)
   * @return array Improvement patterns including:
   *               - trends: Feedback trends over time
   *               - recurring_issues: Most common feedback types
   *               - improvement_areas: Suggested focus areas
   *               - acknowledgment_rate: Percentage of acknowledged feedback
   *               - response_rate: Percentage of feedback with agent responses
   */
  public function trackImprovementPatterns(string $agentId, array $options = []): array
  {
    $timePeriod = $options['time_period'] ?? 30;
    $minFeedbackCount = $options['min_feedback_count'] ?? 5;

    try {
      // Get feedback from the specified time period
      $sql = "SELECT feedback_type, acknowledged, agent_response, created_at
              FROM :table_rag_agent_inter_feedback 
              WHERE target_agent_id = :agent_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
              ORDER BY created_at ASC";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindInt(':days', $timePeriod);
      $stmt->execute();

      $feedbackItems = [];
      while ($row = $stmt->fetch()) {
        $feedbackItems[] = $row;
      }

      $totalCount = count($feedbackItems);

      // Initialize patterns
      $patterns = [
        'total_feedback_count' => $totalCount,
        'time_period_days' => $timePeriod,
        'sufficient_data' => $totalCount >= $minFeedbackCount,
        'trends' => [],
        'recurring_issues' => [],
        'improvement_areas' => [],
        'acknowledgment_rate' => 0,
        'response_rate' => 0
      ];

      if ($totalCount === 0) {
        $patterns['message'] = 'No feedback data available for the specified time period';
        return $patterns;
      }

      // Calculate acknowledgment and response rates
      $acknowledgedCount = 0;
      $responseCount = 0;
      $typeCounts = [
        'correctness' => 0,
        'efficiency' => 0,
        'completeness' => 0,
        'best_practice' => 0
      ];

      foreach ($feedbackItems as $item) {
        if ($item['acknowledged']) {
          $acknowledgedCount++;
        }
        if (!empty($item['agent_response'])) {
          $responseCount++;
        }
        if (isset($typeCounts[$item['feedback_type']])) {
          $typeCounts[$item['feedback_type']]++;
        }
      }

      $patterns['acknowledgment_rate'] = round(($acknowledgedCount / $totalCount) * 100, 2);
      $patterns['response_rate'] = round(($responseCount / $totalCount) * 100, 2);

      // Identify recurring issues (most common feedback types)
      arsort($typeCounts);
      foreach ($typeCounts as $type => $count) {
        if ($count > 0) {
          $patterns['recurring_issues'][] = [
            'type' => $type,
            'count' => $count,
            'percentage' => round(($count / $totalCount) * 100, 2)
          ];
        }
      }

      // Suggest improvement areas based on most frequent feedback types
      $topIssues = array_slice($patterns['recurring_issues'], 0, 2);
      foreach ($topIssues as $issue) {
        if ($issue['percentage'] > 30) {
          $patterns['improvement_areas'][] = [
            'area' => $issue['type'],
            'priority' => 'high',
            'reason' => "Represents {$issue['percentage']}% of feedback"
          ];
        } elseif ($issue['percentage'] > 15) {
          $patterns['improvement_areas'][] = [
            'area' => $issue['type'],
            'priority' => 'medium',
            'reason' => "Represents {$issue['percentage']}% of feedback"
          ];
        }
      }

      // Analyze trends over time (weekly breakdown)
      $patterns['trends'] = $this->analyzeFeedbackTrends($feedbackItems, $timePeriod);

      return $patterns;
    } catch (Exception $e) {
      return [
        'error' => 'Failed to track improvement patterns: ' . $e->getMessage(),
        'total_feedback_count' => 0,
        'sufficient_data' => false
      ];
    }
  }

  /**
   * Analyze feedback trends over time
   *
   * Breaks down feedback into weekly periods to identify trends.
   *
   * @param array $feedbackItems Feedback items ordered by created_at
   * @param int $timePeriod Total time period in days
   * @return array Weekly trend data
   */
  private function analyzeFeedbackTrends(array $feedbackItems, int $timePeriod): array
  {
    $weeks = ceil($timePeriod / 7);
    $trends = [];

    // Initialize weekly buckets
    for ($i = 0; $i < $weeks; $i++) {
      $trends["week_" . ($i + 1)] = [
        'count' => 0,
        'types' => []
      ];
    }

    // Distribute feedback into weekly buckets
    foreach ($feedbackItems as $item) {
      $createdAt = new DateTimeImmutable($item['created_at']);
      $now = new DateTimeImmutable();
      $daysDiff = $now->diff($createdAt)->days;
      $weekIndex = floor($daysDiff / 7);

      if ($weekIndex < $weeks) {
        $weekKey = "week_" . ($weeks - $weekIndex);
        $trends[$weekKey]['count']++;

        $type = $item['feedback_type'];
        if (!isset($trends[$weekKey]['types'][$type])) {
          $trends[$weekKey]['types'][$type] = 0;
        }
        $trends[$weekKey]['types'][$type]++;
      }
    }

    return $trends;
  }

  /**
   * Generate a unique feedback ID
   *
   * @return string A unique identifier for feedback
   */
  private function generateFeedbackId(): string
  {
    return uniqid('feedback_', true);
  }

  /**
   * Get unacknowledged feedback count
   *
   * Returns the number of unacknowledged feedback items for an agent.
   *
   * @param string $agentId The agent ID
   * @return int Count of unacknowledged feedback
   */
  public function getUnacknowledgedCount(string $agentId): int
  {
    try {
      $sql = "SELECT COUNT(*) as count 
              FROM :table_rag_agent_inter_feedback 
              WHERE target_agent_id = :agent_id
              AND acknowledged = FALSE";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->execute();

      $row = $stmt->fetch();
      return (int)($row['count'] ?? 0);
    } catch (Exception $e) {
      return 0;
    }
  }

  /**
   * Get feedback by ID
   *
   * Retrieves a specific feedback record by its ID.
   *
   * @param string $feedbackId The feedback ID
   * @return array|null Feedback record or null if not found
   */
  public function getFeedbackById(string $feedbackId): ?array
  {
    try {
      $sql = "SELECT feedback_id, target_agent_id, source_agent_id, output_id,
                     feedback_type, feedback_text, acknowledged, agent_response,
                     created_at, acknowledged_at
              FROM :table_rag_agent_inter_feedback 
              WHERE feedback_id = :feedback_id";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':feedback_id', $feedbackId);
      $stmt->execute();

      $row = $stmt->fetch();

      if (!$row) {
        return null;
      }

      return [
        'feedback_id' => $row['feedback_id'],
        'target_agent_id' => $row['target_agent_id'],
        'source_agent_id' => $row['source_agent_id'],
        'output_id' => $row['output_id'],
        'feedback_type' => $row['feedback_type'],
        'feedback_text' => $row['feedback_text'],
        'acknowledged' => (bool)$row['acknowledged'],
        'agent_response' => $row['agent_response'],
        'created_at' => $row['created_at'],
        'acknowledged_at' => $row['acknowledged_at']
      ];
    } catch (Exception $e) {
      return null;
    }
  }
}
