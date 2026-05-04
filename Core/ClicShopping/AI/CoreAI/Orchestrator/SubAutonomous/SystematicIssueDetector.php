<?php
/**
 * SystematicIssueDetector Class
 *
 * Detects patterns indicating systematic issues in agent performance,
 * including consistently low scores, recurring failure patterns, and
 * quality degradation trends. Generates alerts for administrator review.
 *
 * Implements Requirement 9.3: Systematic Issue Detection
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use DateTimeImmutable;
use Exception;

class SystematicIssueDetector
{
  private $db;
  private EvaluationMetricsTracker $evaluationTracker;
  private ObjectiveMetricsTracker $objectiveTracker;

  // Thresholds for issue detection
  private float $lowScoreThreshold = 0.6; // Scores below this are considered low
  private float $failureRateThreshold = 0.3; // 30% failure rate triggers alert
  private int $minSampleSize = 5; // Minimum evaluations/objectives to detect pattern
  private float $trendDegradationThreshold = -0.1; // 10% degradation triggers alert

  /**
   * Constructor
   *
   * @param EvaluationMetricsTracker $evaluationTracker Evaluation metrics tracker
   * @param ObjectiveMetricsTracker $objectiveTracker Objective metrics tracker
   */
  public function __construct(
    EvaluationMetricsTracker $evaluationTracker,
    ObjectiveMetricsTracker $objectiveTracker
  ) {
    $this->db = Registry::get('Db');
    $this->evaluationTracker = $evaluationTracker;
    $this->objectiveTracker = $objectiveTracker;
  }

  /**
   * Detect low score patterns
   *
   * Identifies agents or output types with consistently low evaluation scores.
   *
   * @param DateTimeImmutable|null $startDate Start of analysis period
   * @param DateTimeImmutable|null $endDate End of analysis period
   * @return array Detected low score patterns with:
   *               - agent_id: Agent with low scores
   *               - output_type: Output type with low scores
   *               - average_score: Average score
   *               - evaluation_count: Number of evaluations
   *               - severity: 'critical', 'high', 'medium'
   */
  public function detectLowScorePatterns(
    ?DateTimeImmutable $startDate = null,
    ?DateTimeImmutable $endDate = null
  ): array {
    try {
      $conditions = [];
      $params = [];

      // Add time range filters
      if ($startDate) {
        $conditions[] = 'evaluated_at >= :start_date';
        $params[':start_date'] = $startDate->format('Y-m-d H:i:s');
      }

      if ($endDate) {
        $conditions[] = 'evaluated_at <= :end_date';
        $params[':end_date'] = $endDate->format('Y-m-d H:i:s');
      }

      $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

      // Detect low scores by producer agent
      $sql = "SELECT 
                producer_agent_id as agent_id,
                output_type,
                COUNT(*) as evaluation_count,
                AVG(overall_score) as average_score,
                MIN(overall_score) as min_score,
                MAX(overall_score) as max_score
              FROM :table_rag_agent_evaluations 
              {$whereClause}
              GROUP BY producer_agent_id, output_type
              HAVING evaluation_count >= :min_sample_size 
                AND average_score < :low_score_threshold
              ORDER BY average_score ASC";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->bindInt(':min_sample_size', $this->minSampleSize);
      $stmt->bindValue(':low_score_threshold', $this->lowScoreThreshold);
      $stmt->execute();

      $patterns = [];
      while ($row = $stmt->fetch()) {
        $avgScore = (float)$row['average_score'];
        
        // Determine severity based on score
        $severity = 'medium';
        if ($avgScore < 0.4) {
          $severity = 'critical';
        } elseif ($avgScore < 0.5) {
          $severity = 'high';
        }

        $patterns[] = [
          'type' => 'low_score_pattern',
          'agent_id' => $row['agent_id'],
          'output_type' => $row['output_type'],
          'average_score' => round($avgScore, 4),
          'min_score' => round((float)$row['min_score'], 4),
          'max_score' => round((float)$row['max_score'], 4),
          'evaluation_count' => (int)$row['evaluation_count'],
          'severity' => $severity,
          'threshold' => $this->lowScoreThreshold,
          'detected_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s')
        ];
      }

      return $patterns;
    } catch (Exception $e) {
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Detect high failure rate patterns
   *
   * Identifies agents with consistently high objective failure rates.
   *
   * @param DateTimeImmutable|null $startDate Start of analysis period
   * @param DateTimeImmutable|null $endDate End of analysis period
   * @return array Detected failure patterns with:
   *               - agent_id: Agent with high failure rate
   *               - total_objectives: Total objectives
   *               - failed_count: Number of failed objectives
   *               - failure_rate: Failure rate percentage
   *               - severity: 'critical', 'high', 'medium'
   */
  public function detectHighFailureRatePatterns(
    ?DateTimeImmutable $startDate = null,
    ?DateTimeImmutable $endDate = null
  ): array {
    try {
      $conditions = [];
      $params = [];

      // Add time range filters
      if ($startDate) {
        $conditions[] = 'created_at >= :start_date';
        $params[':start_date'] = $startDate->format('Y-m-d H:i:s');
      }

      if ($endDate) {
        $conditions[] = 'created_at <= :end_date';
        $params[':end_date'] = $endDate->format('Y-m-d H:i:s');
      }

      // Only consider terminal states
      $conditions[] = "status IN ('completed', 'failed', 'cancelled')";

      $whereClause = 'WHERE ' . implode(' AND ', $conditions);

      $sql = "SELECT 
                agent_id,
                COUNT(*) as total_objectives,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                (SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) / COUNT(*)) as failure_rate
              FROM :table_rag_agent_objectives 
              {$whereClause}
              GROUP BY agent_id
              HAVING total_objectives >= :min_sample_size 
                AND failure_rate >= :failure_rate_threshold
              ORDER BY failure_rate DESC";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->bindInt(':min_sample_size', $this->minSampleSize);
      $stmt->bindValue(':failure_rate_threshold', $this->failureRateThreshold);
      $stmt->execute();

      $patterns = [];
      while ($row = $stmt->fetch()) {
        $failureRate = (float)$row['failure_rate'];
        
        // Determine severity based on failure rate
        $severity = 'medium';
        if ($failureRate >= 0.5) {
          $severity = 'critical';
        } elseif ($failureRate >= 0.4) {
          $severity = 'high';
        }

        $patterns[] = [
          'type' => 'high_failure_rate',
          'agent_id' => $row['agent_id'],
          'total_objectives' => (int)$row['total_objectives'],
          'failed_count' => (int)$row['failed_count'],
          'completed_count' => (int)$row['completed_count'],
          'failure_rate' => round($failureRate * 100, 2),
          'severity' => $severity,
          'threshold' => $this->failureRateThreshold * 100,
          'detected_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s')
        ];
      }

      return $patterns;
    } catch (Exception $e) {
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Detect quality degradation trends
   *
   * Identifies agents whose performance is degrading over time by comparing
   * recent performance to historical baseline.
   *
   * @param int $recentDays Number of days for recent period (default: 7)
   * @param int $baselineDays Number of days for baseline period (default: 30)
   * @return array Detected degradation trends with:
   *               - agent_id: Agent with degrading performance
   *               - baseline_score: Historical average score
   *               - recent_score: Recent average score
   *               - degradation: Percentage change
   *               - severity: 'critical', 'high', 'medium'
   */
  public function detectQualityDegradationTrends(
    int $recentDays = 7,
    int $baselineDays = 30
  ): array {
    try {
      $now = new DateTimeImmutable();
      $recentStart = (clone $now)->modify("-{$recentDays} days");
      $baselineStart = (clone $now)->modify("-{$baselineDays} days");
      $baselineEnd = (clone $now)->modify("-{$recentDays} days");

      // Get baseline scores (older period)
      $baselineScores = $this->getAgentAverageScores($baselineStart, $baselineEnd);

      // Get recent scores
      $recentScores = $this->getAgentAverageScores($recentStart, $now);

      $trends = [];

      foreach ($recentScores as $agentId => $recentScore) {
        // Skip if no baseline data
        if (!isset($baselineScores[$agentId])) {
          continue;
        }

        $baselineScore = $baselineScores[$agentId];
        
        // Calculate degradation
        $degradation = $recentScore - $baselineScore;

        // Only report if degradation exceeds threshold
        if ($degradation <= $this->trendDegradationThreshold) {
          // Determine severity
          $severity = 'medium';
          if ($degradation <= -0.2) {
            $severity = 'critical';
          } elseif ($degradation <= -0.15) {
            $severity = 'high';
          }

          $trends[] = [
            'type' => 'quality_degradation',
            'agent_id' => $agentId,
            'baseline_score' => round($baselineScore, 4),
            'recent_score' => round($recentScore, 4),
            'degradation' => round($degradation, 4),
            'degradation_percentage' => round(($degradation / $baselineScore) * 100, 2),
            'severity' => $severity,
            'baseline_period' => "{$baselineDays} days ago to {$recentDays} days ago",
            'recent_period' => "last {$recentDays} days",
            'detected_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s')
          ];
        }
      }

      // Sort by degradation (worst first)
      usort($trends, function($a, $b) {
        return $a['degradation'] <=> $b['degradation'];
      });

      return $trends;
    } catch (Exception $e) {
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Get average scores by agent for a time period
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @return array Agent ID => average score mapping
   */
  private function getAgentAverageScores(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
  ): array {
    try {
      $sql = "SELECT 
                producer_agent_id,
                AVG(overall_score) as average_score,
                COUNT(*) as evaluation_count
              FROM :table_rag_agent_evaluations 
              WHERE evaluated_at >= :start_date 
                AND evaluated_at <= :end_date
              GROUP BY producer_agent_id
              HAVING evaluation_count >= :min_sample_size";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':start_date', $startDate->format('Y-m-d H:i:s'));
      $stmt->bindValue(':end_date', $endDate->format('Y-m-d H:i:s'));
      $stmt->bindInt(':min_sample_size', $this->minSampleSize);
      $stmt->execute();

      $scores = [];
      while ($row = $stmt->fetch()) {
        $scores[$row['producer_agent_id']] = (float)$row['average_score'];
      }

      return $scores;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Generate alerts for detected issues
   *
   * Creates alert records in the database for all detected systematic issues.
   *
   * @param array $issues Array of detected issues from detection methods
   * @return int Number of alerts generated
   */
  public function generateAlerts(array $issues): int
  {
    $alertCount = 0;

    try {
      foreach ($issues as $issue) {
        // Skip if error
        if (isset($issue['error'])) {
          continue;
        }

        // Create alert record
        $sql = "INSERT INTO :table_rag_agent_systematic_issue_alerts 
                (alert_id, issue_type, agent_id, severity, details, detected_at, status)
                VALUES 
                (:alert_id, :issue_type, :agent_id, :severity, :details, :detected_at, :status)";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':alert_id', $this->generateUuid());
        $stmt->bindValue(':issue_type', $issue['type']);
        $stmt->bindValue(':agent_id', $issue['agent_id']);
        $stmt->bindValue(':severity', $issue['severity']);
        $stmt->bindValue(':details', json_encode($issue));
        $stmt->bindValue(':detected_at', $issue['detected_at']);
        $stmt->bindValue(':status', 'pending');
        $stmt->execute();

        $alertCount++;
      }

      return $alertCount;
    } catch (Exception $e) {
      error_log('Failed to generate alerts: ' . $e->getMessage());
      return $alertCount;
    }
  }

  /**
   * Get pending alerts
   *
   * Retrieves all pending systematic issue alerts for administrator review.
   *
   * @param string|null $severity Optional severity filter
   * @param string|null $issueType Optional issue type filter
   * @return array Array of pending alerts
   */
  public function getPendingAlerts(
    ?string $severity = null,
    ?string $issueType = null
  ): array {
    try {
      $conditions = ["status = 'pending'"];
      $params = [];

      if ($severity) {
        $conditions[] = 'severity = :severity';
        $params[':severity'] = $severity;
      }

      if ($issueType) {
        $conditions[] = 'issue_type = :issue_type';
        $params[':issue_type'] = $issueType;
      }

      $whereClause = 'WHERE ' . implode(' AND ', $conditions);

      $sql = "SELECT * FROM :table_rag_agent_systematic_issue_alerts 
              {$whereClause}
              ORDER BY 
                FIELD(severity, 'critical', 'high', 'medium', 'low'),
                detected_at DESC";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $alerts = [];
      while ($row = $stmt->fetch()) {
        $alerts[] = [
          'alert_id' => $row['alert_id'],
          'issue_type' => $row['issue_type'],
          'agent_id' => $row['agent_id'],
          'severity' => $row['severity'],
          'details' => json_decode($row['details'], true),
          'detected_at' => $row['detected_at'],
          'status' => $row['status']
        ];
      }

      return $alerts;
    } catch (Exception $e) {
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Acknowledge an alert
   *
   * Marks an alert as acknowledged by an administrator.
   *
   * @param string $alertId Alert ID
   * @param string $acknowledgedBy Administrator ID
   * @param string|null $notes Optional notes
   * @throws Exception If database operation fails
   */
  public function acknowledgeAlert(
    string $alertId,
    string $acknowledgedBy,
    ?string $notes = null
  ): void {
    try {
      $sql = "UPDATE :table_rag_agent_systematic_issue_alerts 
              SET status = 'acknowledged',
                  acknowledged_by = :acknowledged_by,
                  acknowledged_at = :acknowledged_at,
                  admin_notes = :notes
              WHERE alert_id = :alert_id";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':alert_id', $alertId);
      $stmt->bindValue(':acknowledged_by', $acknowledgedBy);
      $stmt->bindValue(':acknowledged_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->bindValue(':notes', $notes);
      $stmt->execute();
    } catch (Exception $e) {
      throw new Exception('Failed to acknowledge alert: ' . $e->getMessage());
    }
  }

  /**
   * Resolve an alert
   *
   * Marks an alert as resolved after the issue has been addressed.
   *
   * @param string $alertId Alert ID
   * @param string $resolvedBy Administrator ID
   * @param string $resolution Resolution description
   * @throws Exception If database operation fails
   */
  public function resolveAlert(
    string $alertId,
    string $resolvedBy,
    string $resolution
  ): void {
    try {
      $sql = "UPDATE :table_rag_agent_systematic_issue_alerts 
              SET status = 'resolved',
                  resolved_by = :resolved_by,
                  resolved_at = :resolved_at,
                  resolution = :resolution
              WHERE alert_id = :alert_id";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':alert_id', $alertId);
      $stmt->bindValue(':resolved_by', $resolvedBy);
      $stmt->bindValue(':resolved_at', (new DateTimeImmutable())->format('Y-m-d H:i:s'));
      $stmt->bindValue(':resolution', $resolution);
      $stmt->execute();
    } catch (Exception $e) {
      throw new Exception('Failed to resolve alert: ' . $e->getMessage());
    }
  }

  /**
   * Run comprehensive issue detection
   *
   * Runs all detection methods and generates alerts for found issues.
   *
   * @param DateTimeImmutable|null $startDate Start of analysis period
   * @param DateTimeImmutable|null $endDate End of analysis period
   * @return array Summary of detected issues and generated alerts
   */
  public function runComprehensiveDetection(
    ?DateTimeImmutable $startDate = null,
    ?DateTimeImmutable $endDate = null
  ): array {
    $lowScorePatterns = $this->detectLowScorePatterns($startDate, $endDate);
    $failurePatterns = $this->detectHighFailureRatePatterns($startDate, $endDate);
    $degradationTrends = $this->detectQualityDegradationTrends();

    // Combine all issues
    $allIssues = array_merge($lowScorePatterns, $failurePatterns, $degradationTrends);

    // Generate alerts
    $alertCount = $this->generateAlerts($allIssues);

    return [
      'detection_run_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
      'period' => [
        'start_date' => $startDate ? $startDate->format('Y-m-d H:i:s') : 'all time',
        'end_date' => $endDate ? $endDate->format('Y-m-d H:i:s') : 'now'
      ],
      'issues_detected' => [
        'low_score_patterns' => count($lowScorePatterns),
        'failure_patterns' => count($failurePatterns),
        'degradation_trends' => count($degradationTrends),
        'total' => count($allIssues)
      ],
      'alerts_generated' => $alertCount,
      'issues' => $allIssues
    ];
  }

  /**
   * Generate UUID v4
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
   * Set detection thresholds
   *
   * Allows customization of detection thresholds.
   *
   * @param array $thresholds Threshold values to set
   */
  public function setThresholds(array $thresholds): void
  {
    if (isset($thresholds['low_score_threshold'])) {
      $this->lowScoreThreshold = (float)$thresholds['low_score_threshold'];
    }

    if (isset($thresholds['failure_rate_threshold'])) {
      $this->failureRateThreshold = (float)$thresholds['failure_rate_threshold'];
    }

    if (isset($thresholds['min_sample_size'])) {
      $this->minSampleSize = (int)$thresholds['min_sample_size'];
    }

    if (isset($thresholds['trend_degradation_threshold'])) {
      $this->trendDegradationThreshold = (float)$thresholds['trend_degradation_threshold'];
    }
  }

  /**
   * Get current thresholds
   *
   * @return array Current threshold values
   */
  public function getThresholds(): array
  {
    return [
      'low_score_threshold' => $this->lowScoreThreshold,
      'failure_rate_threshold' => $this->failureRateThreshold,
      'min_sample_size' => $this->minSampleSize,
      'trend_degradation_threshold' => $this->trendDegradationThreshold
    ];
  }
}
