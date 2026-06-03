<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\AI\Dashboard\Dashboard;
use ClicShopping\AI\Infrastructure\Cache\ClassificationCache;
use ClicShopping\AI\Infrastructure\Cache\RagCache;
use ClicShopping\AI\Infrastructure\Cache\TranslationCache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Dashboard\TokenChartDataProvider;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\AI\CoreAI\Orchestrator\OrchestratorAgent;

$CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

// ============================================================================
// CONFIGURATION STATE DETECTION
// ============================================================================
// Safely detect configuration state to prevent undefined constant errors
// This layer ensures graceful degradation when features are disabled or not installed

$config = [
    'chatgpt_installed' => defined('CLICSHOPPING_APP_CHATGPT_CH_STATUS'),
    'chatgpt_enabled' => defined('CLICSHOPPING_APP_CHATGPT_CH_STATUS') &&  CLICSHOPPING_APP_CHATGPT_CH_STATUS == 'True',
    'rag_installed' => defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS'),
    'rag_enabled' => defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') &&  CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True',
    'rag_cache_enabled' => defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER') &&  CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER == 'True'
];

// ============================================================================
// SAFE DASHBOARD DATA LOADING
// ============================================================================
// Initialize all data arrays as empty to ensure graceful degradation
// when RAG is disabled or data loading fails

$dashboard = null;
$data = [];
$healthReport = [];
$systemReport = [];
$globalStats = [];
$feedbackStats = [];
$tokenDashboardStats = [];
$sourceStats = [];
$tokenChartData = [];
$advancedStats = [];
$alertStats = [];
$activeAlerts = [];
$aggregatedStats = [];
$monitoring = null;
$aggregator = null;
$alertManager = null;
$orchestrator = null;
$websearchStats = []; // WebSearch statistics
$decompositionStats = []; // Hybrid query decomposition statistics

// Only attempt to load Dashboard data if RAG is enabled
if ($config['rag_enabled']) {
    try {
        $dashboard = new Dashboard();
        $data = $dashboard->getAllData(7);

        // Extract data only if successfully loaded
        $healthReport = $data['health_report'] ?? [];
        $systemReport = $data['system_report'] ?? [];
        $globalStats = $data['global_stats'] ?? [];
        $feedbackStats = $data['feedback_stats'] ?? [];
        $tokenDashboardStats = $data['token_stats'] ?? [];
        $sourceStats = $data['source_stats'] ?? [];
        $tokenChartData = TokenChartDataProvider::getChartsData();
        $advancedStats = $data['advanced_stats'] ?? [];
        $alertStats = $data['alert_stats'] ?? [];
        $websearchStats = $data['websearch_stats'] ?? []; // WebSearch statistics
        $decompositionStats = $data['decomposition_stats'] ?? []; // Hybrid query decomposition statistics

        // Variables for compatibility
        $activeAlerts = $healthReport['active_alerts'] ?? [];
    } catch (\Exception $e) {
        // Log the exception without exposing it to the UI
        error_log('Dashboard: Failed to load RAG data - ' . $e->getMessage());
        error_log('Dashboard: Exception trace - ' . $e->getTraceAsString());
        
        // Data arrays remain empty, dashboard will render gracefully
        // No user-facing error message - the UI will show "no data" states
    }
}

// ✅ TASK 4.4.2.3: Retrieve latency metrics
// 🔧 FIX 2025-12-05: DISABLED - Load via AJAX instead to avoid heavy initialization on every page load
// The OrchestratorAgent instantiation is too heavy for dashboard loading
// Metrics will be loaded via AJAX when user clicks on Fast-Lane tab
$latencyMetrics = null;

// All data is now retrieved via the Dashboard class above

// ============================================================================
// GESTION DES EXPORTS
// ============================================================================

if (isset($_GET['export'])) {
  // Redirect to AJAX export endpoint
  $ajax_export_url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/RAG/export.php?export=' . urlencode($_GET['export']);
}

// ============================================================================
// GESTION DES ALERTES
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $alertType = $_POST['alert_type'] ?? '';

  try {
    // Get MonitoringAgent instance
    $monitoringAgent = Registry::exists('MonitoringAgent') ? Registry::get('MonitoringAgent') : null;
    
    if ($monitoringAgent && !empty($alertType)) {
      if ($action === 'acknowledge_alert') {
        $monitoringAgent->acknowledgeAlert($alertType);
        $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_ChatGpt->getDef('alert_acknowledged_success'), 'success', 'header');
      } elseif ($action === 'resolve_alert') {
        $resolution = $_POST['resolution'] ?? 'Resolved manually';
        $monitoringAgent->resolveAlert($alertType, $resolution);
        $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_ChatGpt->getDef('alert_resolved_success'), 'success', 'header');
      } elseif ($action === 'escalate_alert') {
        $monitoringAgent->escalateAlert($alertType);
        $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
        $CLICSHOPPING_MessageStack->add($CLICSHOPPING_ChatGpt->getDef('alert_escalated_success'), 'warning', 'header');
      }
      
      // Redirect to refresh and avoid form resubmission.
      // Alert actions are submitted from the Alerts tab, which now lives in the Manager dashboard.
      header('Location: ' . $_SERVER['PHP_SELF'] . '?ChatGpt&DashboardManager#tab3');
      exit;
    }
  } catch (\Exception $e) {
    error_log('Dashboard: Alert action failed - ' . $e->getMessage());
  }
}

// ============================================================================
// HELPER FUNCTIONS (shared across the metier dashboards)
// ============================================================================
function formatUptime($seconds)
{
  $days = floor($seconds / 86400);
  $hours = floor(($seconds % 86400) / 3600);
  $minutes = floor(($seconds % 3600) / 60);

  $parts = [];
  if ($days > 0)
    $parts[] = "{$days}j";
  if ($hours > 0)
    $parts[] = "{$hours}h";
  if ($minutes > 0)
    $parts[] = "{$minutes}m";

  return !empty($parts) ? implode(' ', $parts) : '0m';
}
