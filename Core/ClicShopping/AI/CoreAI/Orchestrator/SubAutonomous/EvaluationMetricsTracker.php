<?php
/**
 * EvaluationMetricsTracker Class
 *
 * Tracks and calculates aggregate metrics for inter-agent evaluations including
 * evaluation frequency, score distributions, and feedback quality assessment.
 *
 * Implements Requirement 9.2: Evaluation Metrics Tracking
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use DateTimeImmutable;
use Exception;

class EvaluationMetricsTracker
{
  private $db;

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Track evaluation frequency
   *
   * Calculates how often evaluations are performed over a time period,
   * broken down by evaluator, producer, and output type.
   *
   * @param DateTimeImmutable|null $startDate Start of time period (null for all time)
   * @param DateTimeImmutable|null $endDate End of time period (null for now)
   * @return array Evaluation frequency metrics:
   *               - total_evaluations: Total number of evaluations
   *               - evaluations_per_day: Average evaluations per day
   *               - by_evaluator: Breakdown by evaluator agent
   *               - by_producer: Breakdown by producer agent
   *               - by_output_type: Breakdown by output type
   */
  public function trackEvaluationFrequency(
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

      // Get total count
      $sql = "SELECT COUNT(*) as total FROM :table_rag_agent_evaluations {$whereClause}";
      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();
      $totalRow = $stmt->fetch();
      $totalEvaluations = (int)$totalRow['total'];

      // Calculate days in period
      $days = 1;
      if ($startDate && $endDate) {
        $interval = $startDate->diff($endDate);
        $days = max(1, $interval->days);
      } elseif ($startDate) {
        $interval = $startDate->diff(new DateTimeImmutable());
        $days = max(1, $interval->days);
      }

      // Get breakdown by evaluator
      $byEvaluator = $this->getEvaluationBreakdown('evaluator_agent_id', $whereClause, $params);

      // Get breakdown by producer
      $byProducer = $this->getEvaluationBreakdown('producer_agent_id', $whereClause, $params);

      // Get breakdown by output type
      $byOutputType = $this->getEvaluationBreakdown('output_type', $whereClause, $params);

      return [
        'total_evaluations' => $totalEvaluations,
        'evaluations_per_day' => round($totalEvaluations / $days, 2),
        'period_days' => $days,
        'by_evaluator' => $byEvaluator,
        'by_producer' => $byProducer,
        'by_output_type' => $byOutputType
      ];
    } catch (Exception $e) {
      return [
        'total_evaluations' => 0,
        'evaluations_per_day' => 0,
        'period_days' => 0,
        'by_evaluator' => [],
        'by_producer' => [],
        'by_output_type' => [],
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Get evaluation breakdown by field
   *
   * @param string $field Field to group by
   * @param string $whereClause WHERE clause
   * @param array $params Query parameters
   * @return array Breakdown with counts and percentages
   */
  private function getEvaluationBreakdown(
    string $field,
    string $whereClause,
    array $params
  ): array {
    try {
      $sql = "SELECT {$field}, COUNT(*) as count 
              FROM :table_rag_agent_evaluations 
              {$whereClause}
              GROUP BY {$field}
              ORDER BY count DESC";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $breakdown = [];
      $total = 0;

      while ($row = $stmt->fetch()) {
        $count = (int)$row['count'];
        $breakdown[$row[$field]] = $count;
        $total += $count;
      }

      // Calculate percentages
      $result = [];
      foreach ($breakdown as $key => $count) {
        $result[$key] = [
          'count' => $count,
          'percentage' => $total > 0 ? round(($count / $total) * 100, 2) : 0
        ];
      }

      return $result;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Calculate score distribution
   *
   * Analyzes the distribution of evaluation scores across all dimensions
   * (accuracy, completeness, efficiency, clarity, overall).
   *
   * @param DateTimeImmutable|null $startDate Start of time period (null for all time)
   * @param DateTimeImmutable|null $endDate End of time period (null for now)
   * @param string|null $outputType Optional output type to filter by
   * @return array Score distribution metrics for each dimension:
   *               - average: Average score
   *               - min: Minimum score
   *               - max: Maximum score
   *               - median: Median score
   *               - stddev: Standard deviation
   *               - distribution: Score ranges with counts
   */
  public function calculateScoreDistribution(
    ?DateTimeImmutable $startDate = null,
    ?DateTimeImmutable $endDate = null,
    ?string $outputType = null
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

      // Add output type filter
      if ($outputType) {
        $conditions[] = 'output_type = :output_type';
        $params[':output_type'] = $outputType;
      }

      $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

      // Get statistics for each score dimension
      $sql = "SELECT 
                COUNT(*) as count,
                AVG(accuracy_score) as avg_accuracy,
                AVG(completeness_score) as avg_completeness,
                AVG(efficiency_score) as avg_efficiency,
                AVG(clarity_score) as avg_clarity,
                AVG(overall_score) as avg_overall,
                MIN(overall_score) as min_overall,
                MAX(overall_score) as max_overall,
                STDDEV(overall_score) as stddev_overall
              FROM :table_rag_agent_evaluations 
              {$whereClause}";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $row = $stmt->fetch();

      $count = (int)$row['count'];

      if ($count === 0) {
        return [
          'count' => 0,
          'accuracy' => $this->getEmptyScoreMetrics(),
          'completeness' => $this->getEmptyScoreMetrics(),
          'efficiency' => $this->getEmptyScoreMetrics(),
          'clarity' => $this->getEmptyScoreMetrics(),
          'overall' => $this->getEmptyScoreMetrics()
        ];
      }

      // Calculate median for overall score
      $medianOverall = $this->calculateMedianScore('overall_score', $whereClause, $params);

      // Get score range distribution
      $distribution = $this->getScoreRangeDistribution($whereClause, $params);

      return [
        'count' => $count,
        'accuracy' => [
          'average' => round((float)$row['avg_accuracy'], 4),
          'median' => $this->calculateMedianScore('accuracy_score', $whereClause, $params)
        ],
        'completeness' => [
          'average' => round((float)$row['avg_completeness'], 4),
          'median' => $this->calculateMedianScore('completeness_score', $whereClause, $params)
        ],
        'efficiency' => [
          'average' => round((float)$row['avg_efficiency'], 4),
          'median' => $this->calculateMedianScore('efficiency_score', $whereClause, $params)
        ],
        'clarity' => [
          'average' => round((float)$row['avg_clarity'], 4),
          'median' => $this->calculateMedianScore('clarity_score', $whereClause, $params)
        ],
        'overall' => [
          'average' => round((float)$row['avg_overall'], 4),
          'min' => round((float)$row['min_overall'], 4),
          'max' => round((float)$row['max_overall'], 4),
          'median' => $medianOverall,
          'stddev' => round((float)($row['stddev_overall'] ?? 0), 4),
          'distribution' => $distribution
        ]
      ];
    } catch (Exception $e) {
      return [
        'count' => 0,
        'accuracy' => $this->getEmptyScoreMetrics(),
        'completeness' => $this->getEmptyScoreMetrics(),
        'efficiency' => $this->getEmptyScoreMetrics(),
        'clarity' => $this->getEmptyScoreMetrics(),
        'overall' => $this->getEmptyScoreMetrics(),
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Get empty score metrics structure
   *
   * @return array Empty metrics structure
   */
  private function getEmptyScoreMetrics(): array
  {
    return [
      'average' => 0,
      'min' => 0,
      'max' => 0,
      'median' => 0,
      'stddev' => 0
    ];
  }

  /**
   * Calculate median score for a dimension
   *
   * @param string $scoreField Score field name
   * @param string $whereClause WHERE clause
   * @param array $params Query parameters
   * @return float Median score
   */
  private function calculateMedianScore(
    string $scoreField,
    string $whereClause,
    array $params
  ): float {
    try {
      $sql = "SELECT {$scoreField} as score
              FROM :table_rag_agent_evaluations 
              {$whereClause}
              ORDER BY score";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $scores = [];
      while ($row = $stmt->fetch()) {
        $scores[] = (float)$row['score'];
      }

      if (empty($scores)) {
        return 0;
      }

      $count = count($scores);
      $middle = floor($count / 2);

      if ($count % 2 === 0) {
        return round(($scores[$middle - 1] + $scores[$middle]) / 2, 4);
      } else {
        return round($scores[$middle], 4);
      }
    } catch (Exception $e) {
      return 0;
    }
  }

  /**
   * Get score range distribution
   *
   * Categorizes scores into ranges (0-0.2, 0.2-0.4, 0.4-0.6, 0.6-0.8, 0.8-1.0)
   *
   * @param string $whereClause WHERE clause
   * @param array $params Query parameters
   * @return array Distribution by score range
   */
  private function getScoreRangeDistribution(
    string $whereClause,
    array $params
  ): array {
    try {
      $sql = "SELECT 
                SUM(CASE WHEN overall_score >= 0 AND overall_score < 0.2 THEN 1 ELSE 0 END) as range_0_20,
                SUM(CASE WHEN overall_score >= 0.2 AND overall_score < 0.4 THEN 1 ELSE 0 END) as range_20_40,
                SUM(CASE WHEN overall_score >= 0.4 AND overall_score < 0.6 THEN 1 ELSE 0 END) as range_40_60,
                SUM(CASE WHEN overall_score >= 0.6 AND overall_score < 0.8 THEN 1 ELSE 0 END) as range_60_80,
                SUM(CASE WHEN overall_score >= 0.8 AND overall_score <= 1.0 THEN 1 ELSE 0 END) as range_80_100,
                COUNT(*) as total
              FROM :table_rag_agent_evaluations 
              {$whereClause}";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $row = $stmt->fetch();
      $total = (int)$row['total'];

      if ($total === 0) {
        return [];
      }

      return [
        '0.0-0.2' => [
          'count' => (int)$row['range_0_20'],
          'percentage' => round(((int)$row['range_0_20'] / $total) * 100, 2)
        ],
        '0.2-0.4' => [
          'count' => (int)$row['range_20_40'],
          'percentage' => round(((int)$row['range_20_40'] / $total) * 100, 2)
        ],
        '0.4-0.6' => [
          'count' => (int)$row['range_40_60'],
          'percentage' => round(((int)$row['range_40_60'] / $total) * 100, 2)
        ],
        '0.6-0.8' => [
          'count' => (int)$row['range_60_80'],
          'percentage' => round(((int)$row['range_60_80'] / $total) * 100, 2)
        ],
        '0.8-1.0' => [
          'count' => (int)$row['range_80_100'],
          'percentage' => round(((int)$row['range_80_100'] / $total) * 100, 2)
        ]
      ];
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Assess feedback quality
   *
   * Analyzes the quality of feedback provided in evaluations by examining
   * feedback length, structure (strengths/improvements), and completeness.
   *
   * @param DateTimeImmutable|null $startDate Start of time period (null for all time)
   * @param DateTimeImmutable|null $endDate End of time period (null for now)
   * @return array Feedback quality metrics:
   *               - total_evaluations: Total evaluations analyzed
   *               - avg_feedback_length: Average feedback text length
   *               - avg_strengths_count: Average number of strengths listed
   *               - avg_improvements_count: Average number of improvements listed
   *               - complete_feedback_percentage: % with both strengths and improvements
   *               - empty_feedback_count: Number with empty feedback
   */
  public function assessFeedbackQuality(
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

      $sql = "SELECT 
                COUNT(*) as total,
                AVG(LENGTH(feedback)) as avg_feedback_length,
                SUM(CASE WHEN LENGTH(feedback) = 0 OR feedback IS NULL THEN 1 ELSE 0 END) as empty_feedback,
                SUM(CASE WHEN JSON_LENGTH(strengths) > 0 AND JSON_LENGTH(improvements) > 0 THEN 1 ELSE 0 END) as complete_feedback
              FROM :table_rag_agent_evaluations 
              {$whereClause}";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $row = $stmt->fetch();

      $total = (int)$row['total'];
      $avgFeedbackLength = $row['avg_feedback_length'] ? round((float)$row['avg_feedback_length'], 2) : 0;
      $emptyFeedback = (int)$row['empty_feedback'];
      $completeFeedback = (int)$row['complete_feedback'];

      // Calculate average array lengths
      $avgStrengthsCount = $this->calculateAverageArrayLength('strengths', $whereClause, $params);
      $avgImprovementsCount = $this->calculateAverageArrayLength('improvements', $whereClause, $params);

      return [
        'total_evaluations' => $total,
        'avg_feedback_length' => $avgFeedbackLength,
        'avg_strengths_count' => $avgStrengthsCount,
        'avg_improvements_count' => $avgImprovementsCount,
        'complete_feedback_count' => $completeFeedback,
        'complete_feedback_percentage' => $total > 0 ? round(($completeFeedback / $total) * 100, 2) : 0,
        'empty_feedback_count' => $emptyFeedback,
        'empty_feedback_percentage' => $total > 0 ? round(($emptyFeedback / $total) * 100, 2) : 0
      ];
    } catch (Exception $e) {
      return [
        'total_evaluations' => 0,
        'avg_feedback_length' => 0,
        'avg_strengths_count' => 0,
        'avg_improvements_count' => 0,
        'complete_feedback_count' => 0,
        'complete_feedback_percentage' => 0,
        'empty_feedback_count' => 0,
        'empty_feedback_percentage' => 0,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Calculate average JSON array length
   *
   * @param string $field JSON field name
   * @param string $whereClause WHERE clause
   * @param array $params Query parameters
   * @return float Average array length
   */
  private function calculateAverageArrayLength(
    string $field,
    string $whereClause,
    array $params
  ): float {
    try {
      $sql = "SELECT AVG(JSON_LENGTH({$field})) as avg_length
              FROM :table_rag_agent_evaluations 
              {$whereClause}";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $row = $stmt->fetch();

      return $row['avg_length'] ? round((float)$row['avg_length'], 2) : 0;
    } catch (Exception $e) {
      return 0;
    }
  }

  /**
   * Get comprehensive evaluation metrics summary
   *
   * Combines all evaluation metrics into a single comprehensive report.
   *
   * @param DateTimeImmutable|null $startDate Start of time period
   * @param DateTimeImmutable|null $endDate End of time period
   * @param string|null $outputType Optional output type to filter by
   * @return array Comprehensive evaluation metrics summary
   */
  public function getEvaluationMetricsSummary(
    ?DateTimeImmutable $startDate = null,
    ?DateTimeImmutable $endDate = null,
    ?string $outputType = null
  ): array {
    return [
      'frequency_metrics' => $this->trackEvaluationFrequency($startDate, $endDate),
      'score_distribution' => $this->calculateScoreDistribution($startDate, $endDate, $outputType),
      'feedback_quality' => $this->assessFeedbackQuality($startDate, $endDate),
      'period' => [
        'start_date' => $startDate ? $startDate->format('Y-m-d H:i:s') : 'all time',
        'end_date' => $endDate ? $endDate->format('Y-m-d H:i:s') : 'now'
      ],
      'output_type' => $outputType ?? 'all types'
    ];
  }
}
