<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Infrastructure\Metrics\ActorCriticDashboardAggregator;
use ClicShopping\AI\Infrastructure\Metrics\ActorMetricsCollector;
use ClicShopping\AI\Infrastructure\Metrics\CriticMetricsCollector;
use ClicShopping\AI\Infrastructure\Monitoring\AlertManager;
use ClicShopping\AI\Infrastructure\Monitoring\DocumentationGenerator;
use ClicShopping\AI\Infrastructure\Monitoring\MetricsCollector;
use ClicShopping\AI\Infrastructure\Monitoring\MonitoringAgent;
use ClicShopping\AI\Infrastructure\Monitoring\StatsAggregator;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

// -----------------------------------------------------
// 1. Initialisation ClicShopping (Votre code de base)
// -----------------------------------------------------

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');

// Vérification de l'accès administrateur
AdministratorAdmin::hasUserAccess();

// -----------------------------------------------------
// 2. Logique d'Exportation
// -----------------------------------------------------


if (isset($_GET['export'])) {
  $exportType = strtolower($_GET['export']);
  $output = null;
  $mimeType = 'application/json'; // Par défaut

  try {
    // --- Instanciation des classes avec gestion des dépendances ---
    $monitoringAgent = new MonitoringAgent();
    // MetricsCollector a besoin de MonitoringAgent
    $metricsCollector = new MetricsCollector($monitoringAgent);
    $alertManager = new AlertManager();
    $statsAggregator = new StatsAggregator();
    // Le DocumentationGenerator n'a pas besoin de dépendances dans son constructeur
    $docGenerator = new DocumentationGenerator();
  } catch (\Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode([
      'error' => 'Failed to initialize monitoring classes',
      'message' => $e->getMessage(),
      'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
    exit;
  }

  try {
    switch ($exportType) {
      // ------------------------------------
      // Exports CSV
      // ------------------------------------
      case 'csv':
        $output = $monitoringAgent->exportMetrics('csv');
        $mimeType = 'text/csv; charset=utf-8';
        // Set download headers
        header('Content-Disposition: attachment; filename="rag_statistics_' . date('Y-m-d_His') . '.csv"');
        break;

      // ------------------------------------
      // Exports JSON
      // ------------------------------------
      case 'health':
        $output = $monitoringAgent->exportMetrics();
        break;

      case 'metrics':
        $output = $metricsCollector->exportStatsD();
        break;

      case 'alerts':
        $output = $alertManager->exportJSON();
        break;

      case 'stats':
        $output = $statsAggregator->exportJSON();
        break;

      // ------------------------------------
      // Exports Prometheus
      // ------------------------------------
      case 'prometheus':
        $output = $metricsCollector->exportPrometheus();
        $mimeType = 'text/plain; version=0.0.4; charset=utf-8';
        break;

      // ------------------------------------
      // Exports HTML / Documentation
      // ------------------------------------
      case 'html_dashboard':
        $output = $monitoringAgent->exportMetrics('html');
        $mimeType = 'text/html; charset=utf-8';
        break;

      case 'documentation':
        $output = $docGenerator->generateMarkdown();
        $mimeType = 'text/markdown; charset=utf-8';
        break;

      // ------------------------------------
      // Actor-Critic Metrics Exports
      // ------------------------------------
      case 'actor_critic_dashboard':
        $aggregator = new ActorCriticDashboardAggregator();
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
        $output = $aggregator->getDashboardData($days);
        break;

      case 'actor_metrics':
        $aggregator = new ActorCriticDashboardAggregator();
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
        $output = $aggregator->getActorMetricsSummary($days);
        break;

      case 'critic_metrics':
        $aggregator = new ActorCriticDashboardAggregator();
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
        $output = $aggregator->getCriticMetricsSummary($days);
        break;

      case 'actor_critic_utilization':
        $aggregator = new ActorCriticDashboardAggregator();
        $output = $aggregator->getUtilizationMetrics();
        break;

      case 'actor_critic_alerts':
        $aggregator = new ActorCriticDashboardAggregator();
        $output = $aggregator->getAlertsSummary();
        break;

      case 'actor_critic_trends':
        $aggregator = new ActorCriticDashboardAggregator();
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
        $output = $aggregator->getTrends($days);
        break;

      case 'actor_critic_prometheus':
        $aggregator = new ActorCriticDashboardAggregator();
        $output = $aggregator->exportPrometheus();
        $mimeType = 'text/plain; version=0.0.4; charset=utf-8';
        break;

      case 'actors_csv':
        $actorMetrics = new ActorMetricsCollector();
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
        $metrics = $actorMetrics->getAllActorsMetrics($days);
        
        $csv = "Actor ID,Total Executions,Success Rate (%),Avg Execution Time (ms),Avg Quality Score,Performance Score\n";
        foreach ($metrics as $actorId => $data) {
          $csv .= sprintf(
            "%s,%d,%.2f,%.2f,%.4f,%.4f\n",
            $actorId,
            $data['total_executions'],
            $data['success_rate'],
            $data['avg_execution_time_ms'],
            $data['avg_quality_score'],
            $data['performance_score']
          );
        }
        $output = $csv;
        $mimeType = 'text/csv; charset=utf-8';
        header('Content-Disposition: attachment; filename="actor_metrics_' . date('Y-m-d_His') . '.csv"');
        break;

      case 'critics_csv':
        $criticMetrics = new CriticMetricsCollector();
        $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
        $metrics = $criticMetrics->getAllCriticsMetrics($days);
        
        $csv = "Critic ID,Total Evaluations,Avg Evaluation Time (ms),Avg Agreement,Consistency,Performance Score\n";
        foreach ($metrics as $criticId => $data) {
          $csv .= sprintf(
            "%s,%d,%.2f,%.4f,%.4f,%.4f\n",
            $criticId,
            $data['total_evaluations'],
            $data['avg_evaluation_time_ms'],
            $data['avg_agreement'],
            $data['agreement_consistency'],
            $data['performance_score']
          );
        }
        $output = $csv;
        $mimeType = 'text/csv; charset=utf-8';
        header('Content-Disposition: attachment; filename="critic_metrics_' . date('Y-m-d_His') . '.csv"');
        break;

      default:
        // Type d'exportation non reconnu
        header('HTTP/1.0 400 Bad Request');
        header('Content-Type: application/json');
        echo json_encode([
          'error' => 'Type d\'exportation non valide', 
          'available_types' => [
            'csv', 'health', 'metrics', 'alerts', 'stats', 'prometheus', 'html_dashboard', 'documentation',
            'actor_critic_dashboard', 'actor_metrics', 'critic_metrics', 'actor_critic_utilization',
            'actor_critic_alerts', 'actor_critic_trends', 'actor_critic_prometheus', 'actors_csv', 'critics_csv'
          ]
        ]);
        exit;
    }
  } catch (\Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode([
      'error' => 'Export failed',
      'export_type' => $exportType,
      'message' => $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
    exit;
  }

  // ------------------------------------
  // 3. Envoi de la Réponse
  // ------------------------------------

  if ($output !== null) {
    // Envoi des Headers HTTP
    header('Content-Type: ' . $mimeType);

    // Détermine l'extension et encode si c'est du JSON
    if (str_contains($mimeType, 'json')) {
      // S'assurer que la sortie est un tableau/objet avant d'encoder
      if (is_array($output) || is_object($output)) {
        $output = json_encode($output, JSON_PRETTY_PRINT);
      }
    }

    // Affichage du contenu
    echo $output;

  } else {
    // Erreur si $output est null
    header('HTTP/1.0 500 Internal Server Error');
    echo json_encode(['error' => 'Erreur lors de la génération de l\'exportation (La méthode a retourné null).']);
  }

  // Arrêter l'exécution après l'exportation (réussie ou en erreur)
  exit;
}
// Fin du bloc 'if (isset($_GET['export']))'


// -----------------------------------------------------
// 4. Réponse par défaut (si pas de paramètre 'export')
// -----------------------------------------------------

// Ce code est exécuté uniquement si le script est appelé SANS le paramètre ?export=...
header('HTTP/1.0 200 OK');
echo "Script d'exportation en cours d'exécution. Utilisez le paramètre ?export=health (ou autre) pour obtenir des données.";