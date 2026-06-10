<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\AI\Infrastructure\Metrics\ActorCriticMetricsProvider;

define('PAGE_PARSE_START_TIME', microtime());
define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . '/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');

AdministratorAdmin::hasUserAccess();

// Check admin authentication
if (!Registry::exists('Administrators')) {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$format = $_GET['format'] ?? 'json';
$periodDays = (int)($_GET['period'] ?? 7);

try {
  $metricsProvider = new ActorCriticMetricsProvider();
  $metrics = $metricsProvider->getAllMetrics($periodDays);

  if ($format === 'csv') {
    // Export as CSV
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="actor_critic_metrics_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Registry Stats
    fputcsv($output, ['Registry Statistics']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Actors', $metrics['registry_stats']['total_actors'] ?? 0]);
    fputcsv($output, ['Total Critics', $metrics['registry_stats']['total_critics'] ?? 0]);
    fputcsv($output, ['Separation Ratio', ($metrics['registry_stats']['separation_ratio'] ?? 0) . '%']);
    fputcsv($output, []);

    // Actor Metrics
    fputcsv($output, ['Actor Performance Metrics']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Executions', $metrics['actor_metrics']['total_executions'] ?? 0]);
    fputcsv($output, ['Avg Execution Time (ms)', $metrics['actor_metrics']['avg_execution_time'] ?? 0]);
    fputcsv($output, ['Success Rate (%)', $metrics['actor_metrics']['success_rate'] ?? 0]);
    fputcsv($output, ['Avg Quality Score', $metrics['actor_metrics']['avg_quality_score'] ?? 0]);
    fputcsv($output, []);

    // Top Actors
    if (!empty($metrics['actor_metrics']['top_actors'])) {
      fputcsv($output, ['Top Actors']);
      fputcsv($output, ['Actor ID', 'Executions', 'Avg Time (ms)', 'Quality Score', 'Success Rate (%)']);
      foreach ($metrics['actor_metrics']['top_actors'] as $actor) {
        fputcsv($output, [
          $actor['actor_id'],
          $actor['executions'],
          $actor['avg_execution_time'],
          $actor['avg_quality_score'],
          $actor['success_rate']
        ]);
      }
      fputcsv($output, []);
    }

    // Critic Metrics
    fputcsv($output, ['Critic Performance Metrics']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Evaluations', $metrics['critic_metrics']['total_evaluations'] ?? 0]);
    fputcsv($output, ['Avg Evaluation Time (ms)', $metrics['critic_metrics']['avg_evaluation_time'] ?? 0]);
    fputcsv($output, ['Avg Overall Score', $metrics['critic_metrics']['avg_overall_score'] ?? 0]);
    fputcsv($output, []);

    // Top Critics
    if (!empty($metrics['critic_metrics']['top_critics'])) {
      fputcsv($output, ['Top Critics']);
      fputcsv($output, ['Critic ID', 'Evaluations', 'Avg Time (ms)', 'Avg Score', 'Accuracy', 'Completeness']);
      foreach ($metrics['critic_metrics']['top_critics'] as $critic) {
        fputcsv($output, [
          $critic['critic_id'],
          $critic['evaluations'],
          $critic['avg_evaluation_time'],
          $critic['avg_overall_score'],
          $critic['avg_accuracy'],
          $critic['avg_completeness']
        ]);
      }
      fputcsv($output, []);
    }

    // Coordination Metrics
    fputcsv($output, ['Coordination Metrics']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Coordinations', $metrics['coordination_metrics']['total_coordinations'] ?? 0]);
    fputcsv($output, ['Avg Total Time (ms)', $metrics['coordination_metrics']['avg_total_time'] ?? 0]);
    fputcsv($output, ['Avg Consensus Score', $metrics['coordination_metrics']['avg_consensus_score'] ?? 0]);
    fputcsv($output, ['Avg Critics per Coordination', $metrics['coordination_metrics']['avg_critics_per_coordination'] ?? 0]);

    fclose($output);
  } else {
    // Export as JSON
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="actor_critic_metrics_' . date('Y-m-d') . '.json"');

    echo json_encode($metrics, JSON_PRETTY_PRINT);
  }
} catch (\Exception $e) {
  error_log('Export Actor-Critic Metrics Error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Failed to export metrics']);
}
