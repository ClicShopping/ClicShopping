<?php
/**
 * PeriodicReportGenerator Class
 *
 * Generates comprehensive periodic reports summarizing autonomous agent activity
 * including objectives created, evaluations performed, consensus sessions,
 * and overall system health metrics.
 *
 * Implements Requirement 9.5: Periodic Report Generation
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\OM\Registry;
use DateTimeImmutable;
use Exception;

class PeriodicReportGenerator
{
  private $db;
  private ObjectiveMetricsTracker $objectiveTracker;
  private EvaluationMetricsTracker $evaluationTracker;
  private SystematicIssueDetector $issueDetector;

  /**
   * Constructor
   *
   * @param ObjectiveMetricsTracker $objectiveTracker Objective metrics tracker
   * @param EvaluationMetricsTracker $evaluationTracker Evaluation metrics tracker
   * @param SystematicIssueDetector $issueDetector Systematic issue detector
   */
  public function __construct(
    ObjectiveMetricsTracker $objectiveTracker,
    EvaluationMetricsTracker $evaluationTracker,
    SystematicIssueDetector $issueDetector
  ) {
    $this->db = Registry::get('Db');
    $this->objectiveTracker = $objectiveTracker;
    $this->evaluationTracker = $evaluationTracker;
    $this->issueDetector = $issueDetector;
  }

  /**
   * Generate comprehensive periodic report
   *
   * Creates a complete report covering all aspects of autonomous agent activity
   * for a specified time period.
   *
   * @param DateTimeImmutable $startDate Start of reporting period
   * @param DateTimeImmutable $endDate End of reporting period
   * @param string $reportType Type of report ('daily', 'weekly', 'monthly', 'custom')
   * @return array Comprehensive report data
   */
  public function generateReport(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate,
    string $reportType = 'custom'
  ): array {
    $report = [
      'report_metadata' => $this->generateReportMetadata($startDate, $endDate, $reportType),
      'executive_summary' => $this->generateExecutiveSummary($startDate, $endDate),
      'objective_metrics' => $this->objectiveTracker->getObjectiveMetricsSummary($startDate, $endDate),
      'evaluation_metrics' => $this->evaluationTracker->getEvaluationMetricsSummary($startDate, $endDate),
      'consensus_metrics' => $this->getConsensusMetrics($startDate, $endDate),
      'agent_performance' => $this->getAgentPerformanceBreakdown($startDate, $endDate),
      'systematic_issues' => $this->issueDetector->runComprehensiveDetection($startDate, $endDate),
      'trends_and_insights' => $this->generateTrendsAndInsights($startDate, $endDate),
      'recommendations' => $this->generateRecommendations($startDate, $endDate)
    ];

    // Store report in database
    $this->storeReport($report);

    return $report;
  }

  /**
   * Generate report metadata
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @param string $reportType Report type
   * @return array Report metadata
   */
  private function generateReportMetadata(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate,
    string $reportType
  ): array {
    $interval = $startDate->diff($endDate);

    return [
      'report_id' => $this->generateUuid(),
      'report_type' => $reportType,
      'generated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
      'period' => [
        'start_date' => $startDate->format('Y-m-d H:i:s'),
        'end_date' => $endDate->format('Y-m-d H:i:s'),
        'duration_days' => $interval->days,
        'duration_hours' => ($interval->days * 24) + $interval->h
      ],
      'version' => '1.0.0'
    ];
  }

  /**
   * Generate executive summary
   *
   * Creates a high-level summary of key metrics and highlights.
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @return array Executive summary
   */
  private function generateExecutiveSummary(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
  ): array {
    // Get key metrics
    $objectiveMetrics = $this->objectiveTracker->calculateSuccessRate($startDate, $endDate);
    $evaluationMetrics = $this->evaluationTracker->trackEvaluationFrequency($startDate, $endDate);
    $consensusMetrics = $this->getConsensusMetrics($startDate, $endDate);

    // Calculate health score (0-100)
    $healthScore = $this->calculateSystemHealthScore($objectiveMetrics, $evaluationMetrics, $consensusMetrics);

    return [
      'health_score' => $healthScore,
      'health_status' => $this->getHealthStatus($healthScore),
      'key_metrics' => [
        'total_objectives' => $objectiveMetrics['total'],
        'objective_success_rate' => $objectiveMetrics['success_rate'],
        'total_evaluations' => $evaluationMetrics['total_evaluations'],
        'total_consensus_sessions' => $consensusMetrics['total_sessions'],
        'consensus_success_rate' => $consensusMetrics['consensus_reached_percentage']
      ],
      'highlights' => $this->generateHighlights($startDate, $endDate),
      'concerns' => $this->generateConcerns($startDate, $endDate)
    ];
  }

  /**
   * Calculate system health score
   *
   * @param array $objectiveMetrics Objective metrics
   * @param array $evaluationMetrics Evaluation metrics
   * @param array $consensusMetrics Consensus metrics
   * @return float Health score (0-100)
   */
  private function calculateSystemHealthScore(
    array $objectiveMetrics,
    array $evaluationMetrics,
    array $consensusMetrics
  ): float {
    $scores = [];

    // Objective success rate (weight: 30%)
    $scores[] = ($objectiveMetrics['success_rate'] ?? 0) * 0.3;

    // Evaluation activity (weight: 20%)
    // Normalize evaluations per day (assume 10+ is excellent)
    $evalScore = min(100, ($evaluationMetrics['evaluations_per_day'] ?? 0) * 10);
    $scores[] = $evalScore * 0.2;

    // Consensus success rate (weight: 25%)
    $scores[] = ($consensusMetrics['consensus_reached_percentage'] ?? 0) * 0.25;

    // Low failure rate (weight: 25%)
    $failureScore = 100 - ($objectiveMetrics['failure_rate'] ?? 0);
    $scores[] = $failureScore * 0.25;

    return round(array_sum($scores), 2);
  }

  /**
   * Get health status label
   *
   * @param float $healthScore Health score
   * @return string Status label
   */
  private function getHealthStatus(float $healthScore): string
  {
    if ($healthScore >= 90) return 'Excellent';
    if ($healthScore >= 75) return 'Good';
    if ($healthScore >= 60) return 'Fair';
    if ($healthScore >= 40) return 'Poor';
    return 'Critical';
  }

  /**
   * Generate highlights
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @return array Array of highlight strings
   */
  private function generateHighlights(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
  ): array {
    $highlights = [];

    // Get top performing agents
    $topAgents = $this->getTopPerformingAgents($startDate, $endDate, 3);
    if (!empty($topAgents)) {
      $agentList = implode(', ', array_column($topAgents, 'agent_id'));
      $highlights[] = "Top performing agents: {$agentList}";
    }

    // Check for high success rate
    $successMetrics = $this->objectiveTracker->calculateSuccessRate($startDate, $endDate);
    if ($successMetrics['success_rate'] >= 80) {
      $highlights[] = "Excellent objective success rate: {$successMetrics['success_rate']}%";
    }

    // Check for active collaboration
    $collaborationCount = $this->getCollaborationCount($startDate, $endDate);
    if ($collaborationCount > 0) {
      $highlights[] = "{$collaborationCount} collaborative objectives created";
    }

    return $highlights;
  }

  /**
   * Generate concerns
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @return array Array of concern strings
   */
  private function generateConcerns(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
  ): array {
    $concerns = [];

    // Check for low success rate
    $successMetrics = $this->objectiveTracker->calculateSuccessRate($startDate, $endDate);
    if ($successMetrics['success_rate'] < 60) {
      $concerns[] = "Low objective success rate: {$successMetrics['success_rate']}%";
    }

    // Check for high failure rate
    if ($successMetrics['failure_rate'] > 30) {
      $concerns[] = "High objective failure rate: {$successMetrics['failure_rate']}%";
    }

    // Check for pending alerts
    $pendingAlerts = $this->issueDetector->getPendingAlerts();
    $criticalAlerts = array_filter($pendingAlerts, fn($a) => $a['severity'] === 'critical');
    if (count($criticalAlerts) > 0) {
      $concerns[] = count($criticalAlerts) . " critical systematic issues detected";
    }

    return $concerns;
  }

  /**
   * Get consensus metrics
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @return array Consensus metrics
   */
  private function getConsensusMetrics(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
  ): array {
    try {
      $sql = "SELECT 
                COUNT(*) as total_sessions,
                SUM(CASE WHEN consensus_reached = 1 THEN 1 ELSE 0 END) as consensus_reached,
                AVG(final_score) as avg_final_score,
                AVG(TIMESTAMPDIFF(SECOND, created_at, resolved_at)) as avg_resolution_time
              FROM :table_rag_agent_consensus_sessions 
              WHERE created_at >= :start_date 
                AND created_at <= :end_date";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':start_date', $startDate->format('Y-m-d H:i:s'));
      $stmt->bindValue(':end_date', $endDate->format('Y-m-d H:i:s'));
      $stmt->execute();

      $row = $stmt->fetch();

      $total = (int)$row['total_sessions'];
      $reached = (int)$row['consensus_reached'];

      return [
        'total_sessions' => $total,
        'consensus_reached' => $reached,
        'consensus_reached_percentage' => $total > 0 ? round(($reached / $total) * 100, 2) : 0,
        'avg_final_score' => $row['avg_final_score'] ? round((float)$row['avg_final_score'], 4) : 0,
        'avg_resolution_time_seconds' => $row['avg_resolution_time'] ? round((float)$row['avg_resolution_time'], 2) : 0,
        'avg_resolution_time_minutes' => $row['avg_resolution_time'] ? round((float)$row['avg_resolution_time'] / 60, 2) : 0
      ];
    } catch (Exception $e) {
      return [
        'total_sessions' => 0,
        'consensus_reached' => 0,
        'consensus_reached_percentage' => 0,
        'avg_final_score' => 0,
        'avg_resolution_time_seconds' => 0,
        'avg_resolution_time_minutes' => 0,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Get agent performance breakdown
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @return array Performance breakdown by agent
   */
  private function getAgentPerformanceBreakdown(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
  ): array {
    try {
      // Get unique agents
      $sql = "SELECT DISTINCT agent_id FROM :table_rag_agent_objectives 
              WHERE created_at >= :start_date AND created_at <= :end_date";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':start_date', $startDate->format('Y-m-d H:i:s'));
      $stmt->bindValue(':end_date', $endDate->format('Y-m-d H:i:s'));
      $stmt->execute();

      $agents = [];
      while ($row = $stmt->fetch()) {
        $agentId = $row['agent_id'];

        // Get metrics for this agent
        $objectiveMetrics = $this->objectiveTracker->calculateSuccessRate($startDate, $endDate, $agentId);
        $completionMetrics = $this->objectiveTracker->calculateAverageCompletionTime($startDate, $endDate, $agentId);

        // Get evaluation scores for this agent's outputs
        $avgScore = $this->getAgentAverageEvaluationScore($agentId, $startDate, $endDate);

        $agents[$agentId] = [
          'agent_id' => $agentId,
          'objectives' => [
            'total' => $objectiveMetrics['total'],
            'completed' => $objectiveMetrics['completed'],
            'failed' => $objectiveMetrics['failed'],
            'success_rate' => $objectiveMetrics['success_rate']
          ],
          'completion_time' => [
            'average_hours' => $completionMetrics['average_hours'],
            'median_hours' => $completionMetrics['median_hours']
          ],
          'evaluation_score' => [
            'average' => $avgScore
          ]
        ];
      }

      return $agents;
    } catch (Exception $e) {
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Get agent average evaluation score
   *
   * @param string $agentId Agent ID
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @return float Average score
   */
  private function getAgentAverageEvaluationScore(
    string $agentId,
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
  ): float {
    try {
      $sql = "SELECT AVG(overall_score) as avg_score
              FROM :table_rag_agent_evaluations 
              WHERE producer_agent_id = :agent_id
                AND evaluated_at >= :start_date 
                AND evaluated_at <= :end_date";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':agent_id', $agentId);
      $stmt->bindValue(':start_date', $startDate->format('Y-m-d H:i:s'));
      $stmt->bindValue(':end_date', $endDate->format('Y-m-d H:i:s'));
      $stmt->execute();

      $row = $stmt->fetch();

      return $row['avg_score'] ? round((float)$row['avg_score'], 4) : 0;
    } catch (Exception $e) {
      return 0;
    }
  }

  /**
   * Get top performing agents
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @param int $limit Number of agents to return
   * @return array Top performing agents
   */
  private function getTopPerformingAgents(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate,
    int $limit = 5
  ): array {
    try {
      $sql = "SELECT 
                producer_agent_id as agent_id,
                AVG(overall_score) as avg_score,
                COUNT(*) as evaluation_count
              FROM :table_rag_agent_evaluations 
              WHERE evaluated_at >= :start_date 
                AND evaluated_at <= :end_date
              GROUP BY producer_agent_id
              HAVING evaluation_count >= 3
              ORDER BY avg_score DESC
              LIMIT :limit";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':start_date', $startDate->format('Y-m-d H:i:s'));
      $stmt->bindValue(':end_date', $endDate->format('Y-m-d H:i:s'));
      $stmt->bindInt(':limit', $limit);
      $stmt->execute();

      $agents = [];
      while ($row = $stmt->fetch()) {
        $agents[] = [
          'agent_id' => $row['agent_id'],
          'avg_score' => round((float)$row['avg_score'], 4),
          'evaluation_count' => (int)$row['evaluation_count']
        ];
      }

      return $agents;
    } catch (Exception $e) {
      return [];
    }
  }

  /**
   * Get collaboration count
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @return int Number of collaborative objectives
   */
  private function getCollaborationCount(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
  ): int {
    try {
      $sql = "SELECT COUNT(*) as count
              FROM :table_rag_agent_objective_collaborations 
              WHERE created_at >= :start_date 
                AND created_at <= :end_date";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':start_date', $startDate->format('Y-m-d H:i:s'));
      $stmt->bindValue(':end_date', $endDate->format('Y-m-d H:i:s'));
      $stmt->execute();

      $row = $stmt->fetch();

      return (int)$row['count'];
    } catch (Exception $e) {
      return 0;
    }
  }

  /**
   * Generate trends and insights
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @return array Trends and insights
   */
  private function generateTrendsAndInsights(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
  ): array {
    return [
      'objective_trends' => $this->getObjectiveTrends($startDate, $endDate),
      'evaluation_trends' => $this->getEvaluationTrends($startDate, $endDate),
      'quality_trends' => $this->issueDetector->detectQualityDegradationTrends()
    ];
  }

  /**
   * Get objective trends
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @return array Objective trends
   */
  private function getObjectiveTrends(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
  ): array {
    // Compare current period to previous period
    $interval = $startDate->diff($endDate);
    $previousStart = (clone $startDate)->modify("-{$interval->days} days");
    $previousEnd = clone $startDate;

    $currentMetrics = $this->objectiveTracker->calculateSuccessRate($startDate, $endDate);
    $previousMetrics = $this->objectiveTracker->calculateSuccessRate($previousStart, $previousEnd);

    return [
      'success_rate_change' => $currentMetrics['success_rate'] - $previousMetrics['success_rate'],
      'total_objectives_change' => $currentMetrics['total'] - $previousMetrics['total'],
      'trend_direction' => $this->getTrendDirection($currentMetrics['success_rate'], $previousMetrics['success_rate'])
    ];
  }

  /**
   * Get evaluation trends
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @return array Evaluation trends
   */
  private function getEvaluationTrends(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
  ): array {
    // Compare current period to previous period
    $interval = $startDate->diff($endDate);
    $previousStart = (clone $startDate)->modify("-{$interval->days} days");
    $previousEnd = clone $startDate;

    $currentMetrics = $this->evaluationTracker->trackEvaluationFrequency($startDate, $endDate);
    $previousMetrics = $this->evaluationTracker->trackEvaluationFrequency($previousStart, $previousEnd);

    return [
      'evaluations_per_day_change' => $currentMetrics['evaluations_per_day'] - $previousMetrics['evaluations_per_day'],
      'total_evaluations_change' => $currentMetrics['total_evaluations'] - $previousMetrics['total_evaluations'],
      'trend_direction' => $this->getTrendDirection($currentMetrics['evaluations_per_day'], $previousMetrics['evaluations_per_day'])
    ];
  }

  /**
   * Get trend direction
   *
   * @param float $current Current value
   * @param float $previous Previous value
   * @return string Trend direction
   */
  private function getTrendDirection(float $current, float $previous): string
  {
    if ($previous == 0) return 'stable';
    
    $change = (($current - $previous) / $previous) * 100;
    
    if ($change > 10) return 'improving';
    if ($change < -10) return 'declining';
    return 'stable';
  }

  /**
   * Generate recommendations
   *
   * @param DateTimeImmutable $startDate Start date
   * @param DateTimeImmutable $endDate End date
   * @return array Recommendations
   */
  private function generateRecommendations(
    DateTimeImmutable $startDate,
    DateTimeImmutable $endDate
  ): array {
    $recommendations = [];

    // Check success rate
    $successMetrics = $this->objectiveTracker->calculateSuccessRate($startDate, $endDate);
    if ($successMetrics['success_rate'] < 70) {
      $recommendations[] = [
        'priority' => 'high',
        'category' => 'objective_success',
        'recommendation' => 'Investigate causes of low objective success rate and provide additional agent training or resources'
      ];
    }

    // Check evaluation activity
    $evalMetrics = $this->evaluationTracker->trackEvaluationFrequency($startDate, $endDate);
    if ($evalMetrics['evaluations_per_day'] < 5) {
      $recommendations[] = [
        'priority' => 'medium',
        'category' => 'evaluation_activity',
        'recommendation' => 'Increase inter-agent evaluation frequency to improve quality assurance'
      ];
    }

    // Check for systematic issues
    $issues = $this->issueDetector->runComprehensiveDetection($startDate, $endDate);
    if ($issues['issues_detected']['total'] > 0) {
      $recommendations[] = [
        'priority' => 'high',
        'category' => 'systematic_issues',
        'recommendation' => "Address {$issues['issues_detected']['total']} detected systematic issues to improve system health"
      ];
    }

    return $recommendations;
  }

  /**
   * Store report in database
   *
   * @param array $report Report data
   * @throws Exception If database operation fails
   */
  private function storeReport(array $report): void
  {
    try {
      $sql = "INSERT INTO :table_rag_agent_periodic_reports 
              (report_id, report_type, period_start, period_end, 
               report_data, generated_at)
              VALUES 
              (:report_id, :report_type, :period_start, :period_end,
               :report_data, :generated_at)";

      $stmt = $this->db->prepare($sql);
      $stmt->bindValue(':report_id', $report['report_metadata']['report_id']);
      $stmt->bindValue(':report_type', $report['report_metadata']['report_type']);
      $stmt->bindValue(':period_start', $report['report_metadata']['period']['start_date']);
      $stmt->bindValue(':period_end', $report['report_metadata']['period']['end_date']);
      $stmt->bindValue(':report_data', json_encode($report));
      $stmt->bindValue(':generated_at', $report['report_metadata']['generated_at']);
      $stmt->execute();
    } catch (Exception $e) {
      throw new Exception('Failed to store report: ' . $e->getMessage());
    }
  }

  /**
   * Get stored reports
   *
   * @param string|null $reportType Optional report type filter
   * @param int $limit Maximum number of reports to return
   * @return array Array of stored reports
   */
  public function getStoredReports(
    ?string $reportType = null,
    int $limit = 10
  ): array {
    try {
      $conditions = [];
      $params = [];

      if ($reportType) {
        $conditions[] = 'report_type = :report_type';
        $params[':report_type'] = $reportType;
      }

      $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

      $sql = "SELECT * FROM :table_rag_agent_periodic_reports 
              {$whereClause}
              ORDER BY generated_at DESC
              LIMIT :limit";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->bindInt(':limit', $limit);
      $stmt->execute();

      $reports = [];
      while ($row = $stmt->fetch()) {
        $reports[] = [
          'report_id' => $row['report_id'],
          'report_type' => $row['report_type'],
          'period_start' => $row['period_start'],
          'period_end' => $row['period_end'],
          'generated_at' => $row['generated_at'],
          'report_data' => json_decode($row['report_data'], true)
        ];
      }

      return $reports;
    } catch (Exception $e) {
      return ['error' => $e->getMessage()];
    }
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
}
