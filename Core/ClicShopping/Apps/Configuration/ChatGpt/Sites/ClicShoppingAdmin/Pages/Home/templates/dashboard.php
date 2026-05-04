<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
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
      
      // Redirect to refresh and avoid form resubmission
      header('Location: ' . $_SERVER['PHP_SELF'] . '?ChatGpt&Dashboard#tab3');
      exit;
    }
  } catch (\Exception $e) {
    error_log('Dashboard: Alert action failed - ' . $e->getMessage());
  }
}
?>
   <div class="contentBody">
    <div class="row">
      <div class="col-md-12">
        <div class="card card-block headerCard">
          <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title'), '40', '40'); ?></span>
            <span
              class="col-md-3 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title'); ?></span>
            <span class="col-md-8 text-end">
            <?php
              // Configure button - ALWAYS visible regardless of configuration state
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_configure'), null, $CLICSHOPPING_ChatGpt->link('Configure'),'primary') . ' ';

              // Feature-specific buttons - only visible when ChatGPT is enabled
              if ($config['chatgpt_enabled']) {
                // Help button
                echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_help'), null, $CLICSHOPPING_ChatGpt->link('Help'),'info') . ' ';
                // Competitor Configuration button - links to RagWebSearch page
                if (defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True') {
                  echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_rag_websearch_config'),  null, $CLICSHOPPING_ChatGpt->link('RagWebSearch'),'success') . ' ';

                  // Reset Cache button - opens modal
                  echo HTML::button($CLICSHOPPING_ChatGpt->getDef('text_ĥeading_remove_cache'), null,null, 'warning', ['params' => 'data-bs-toggle="modal" data-bs-target="#resetCacheModal"']) . ' ';
                  echo '&nbsp;';
                  // Reset All Stats button - opens modal
                  echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_reset_all_stats'), null, null,'danger', ['params' => 'data-bs-toggle="modal" data-bs-target="#resetStatsModal"']) . ' ';
                }
              }
              
              // Back button - ALWAYS visible
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back'), null, $CLICSHOPPING_ChatGpt->link('ChatGpt'),'primary');
            ?>
          </span>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-1"></div>

   <?php
       // ============================================================================
       // INFORMATIONAL MESSAGES FOR DISABLED FEATURES
       // ============================================================================
       // Display actionable guidance when features are not installed or disabled
       // Requirements: 1.4, 5.1, 5.4
       
       // ChatGPT Module Not Installed (Requirement 1.4)
       if (!$config['chatgpt_installed']) {
   ?>
     <div class="alert alert-warning" role="alert">
         <h5><i class="bi bi-exclamation-triangle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_title'); ?></h5>
         <p><?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_message'); ?></p>
         <hr>
         <p class="mb-0">
           <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_action'); ?></strong><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_step1'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_step2'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_step3'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_not_installed_step4'); ?>
         </p>
     </div>
   <?php
       // ChatGPT Module Disabled (Requirement 1.4)
       } elseif (!$config['chatgpt_enabled']) {
   ?>
     <div class="alert alert-info" role="alert">
         <h5><i class="bi bi-info-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_title'); ?></h5>
         <p><?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_message'); ?></p>
         <hr>
         <p class="mb-0">
           <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_action'); ?></strong><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_step1'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_step2'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_step3'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('chatgpt_disabled_step4'); ?>
         </p>
     </div>
   <?php
       }
       
       // RAG BI Not Installed (Requirement 5.1)
       if ($config['chatgpt_enabled'] && !$config['rag_installed']) {
   ?>
     <div class="alert alert-warning" role="alert">
         <h5><i class="bi bi-exclamation-triangle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_title'); ?></h5>
         <p><?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_message'); ?></p>
         <hr>
         <p class="mb-0">
           <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_action'); ?></strong><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_step1'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_step2'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_step3'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_step4'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_installed_step5'); ?>
         </p>
     </div>
   <?php
       // RAG BI Disabled (Requirement 5.1)
       } elseif ($config['chatgpt_enabled'] && !$config['rag_enabled']) {
   ?>
     <div class="alert alert-info" role="alert">
         <h5><i class="bi bi-info-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_title'); ?></h5>
         <p><?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_message'); ?></p>
         <hr>
         <p class="mb-0">
           <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_action'); ?></strong><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_step1'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_step2'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_step3'); ?><br>
           <?php echo $CLICSHOPPING_ChatGpt->getDef('rag_disabled_step4'); ?>
         </p>
     </div>
   <?php
       }
       
       // Display informational alert when RAG cache is disabled (existing alert)
       if ($config['rag_enabled'] && !$config['rag_cache_enabled']) {
   ?>
     <div class="alert alert-info text-center" role="alert">
         <i class="bi bi-info-circle"></i>
         <?php echo $CLICSHOPPING_ChatGpt->getDef('text_alert_dashboard'); ?>
     </div>
   <?php
    }
   ?>

    <div id="categoriesTabs" style="overflow: auto;">
      <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
        <li
          class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_ChatGpt->getDef('tab_general') . '</a>'; ?></li>
        <?php if ($config['rag_enabled']): ?>
        <li
          class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_ChatGpt->getDef('tab_component') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">🚨  ' . $CLICSHOPPING_ChatGpt->getDef('tab_alert') . ' <span class="badge bg-danger ms-2">' . count($activeAlerts) . '</span></a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab4" role="tab" data-bs-toggle="tab" class="nav-link">📈 ' . $CLICSHOPPING_ChatGpt->getDef('tab_trend') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab5" role="tab" data-bs-toggle="tab" class="nav-link">🎯 ' . $CLICSHOPPING_ChatGpt->getDef('tab_token_cost') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab6" role="tab" data-bs-toggle="tab" class="nav-link">🎯 ' . $CLICSHOPPING_ChatGpt->getDef('tab_classification_performance') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab_latency" role="tab" data-bs-toggle="tab" class="nav-link">⚡ Latence & Fast-Lane</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab7" role="tab" data-bs-toggle="tab" class="nav-link">🔒 ' . $CLICSHOPPING_ChatGpt->getDef('tab_security') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab10" role="tab" data-bs-toggle="tab" class="nav-link">⚡ ' . $CLICSHOPPING_ChatGpt->getDef('tab_performance_cache') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab11" role="tab" data-bs-toggle="tab" class="nav-link">⚡ ' . $CLICSHOPPING_ChatGpt->getDef('tab_feedback') . '</a>'; ?></li>
        <li
          class="nav-item"><?php echo '<a href="#tab12" role="tab" data-bs-toggle="tab" class="nav-link">📥 ' . $CLICSHOPPING_ChatGpt->getDef('tab_export_api') . '</a>'; ?></li>
        <?php endif; ?>
      </ul>
      
      <!-- Agent Monitoring Quick Access -->
      <?php if ($config['rag_enabled']): ?>
      <div class="mt-3 mb-3">
        <div class="card">
          <div class="card-header">
            <h6 class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent_monitoring_management'); ?></h6>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-3">
                <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_agent_objectives'), null, $CLICSHOPPING_ChatGpt->link('AgentObjectives'), 'primary', ['params' => 'style="width: 100%;"']); ?>
                <small class="text-muted d-block mt-1"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent_objectives_help'); ?></small>
              </div>
              <div class="col-md-3">
                <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_agent_evaluations'), null, $CLICSHOPPING_ChatGpt->link('AgentEvaluations'), 'info', ['params' => 'style="width: 100%;"']); ?>
                <small class="text-muted d-block mt-1"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent_evaluations_help'); ?></small>
              </div>
              <div class="col-md-3">
                <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_agent_alerts'), null, $CLICSHOPPING_ChatGpt->link('AgentAlerts'), 'warning', ['params' => 'style="width: 100%;"']); ?>
                <small class="text-muted d-block mt-1"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_agent_alerts_help'); ?></small>
              </div>
              <div class="col-md-3">
                <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_actor_critic'), null, $CLICSHOPPING_ChatGpt->link('AgentActorCritic'), 'success', ['params' => 'style="width: 100%;"']); ?>
                <small class="text-muted d-block mt-1"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_actor_critic_help'); ?></small>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
      
      <div class="tabsClicShopping">
        <div class="tab-content">
          <?php
          // -------------------------------------------------------------------
          //          TAB General - Overview
          // -------------------------------------------------------------------
          ?>
          <div class="tab-pane active" id="tab1">
            <div class="container-fluid py-4">
              <?php if ($config['rag_enabled']): ?>
              <!-- HEALTH SCORE SECTION -->
              <div class="row">
                <div class="col-12">
                  <div class="card">
                    <div class="card-body">
                      <div class="health-score">
                        <div>
                          <div class="health-circle <?php echo $healthReport['overall_health']['status'];?>">
                            <?php echo $healthReport['overall_health']['score'] ?>
                          </div>
                        </div>
                        <div style="flex: 1;">
                          <h3><?php echo $CLICSHOPPING_ChatGpt->getDef('section_health_score'); ?></h3>
                          <p><span class="status-badge <?php echo $healthReport['overall_health']['status'];?>">
                      <?php echo strtoupper($healthReport['overall_health']['status']); ?>
                    </span></p>

                          <?php if (!empty($healthReport['overall_health']['issues'])): ?>
                            <div style="margin-top: 15px;">
                              <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('status_problems_detected'); ?>:</h6>
                              <ul style="margin: 0; padding-left: 20px;">
                                <?php foreach ($healthReport['overall_health']['issues'] as $issue): ?>
                                  <li><?php echo htmlspecialchars($issue); ?></li>
                                <?php endforeach; ?>
                              </ul>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- METRICS CARDS -->
              <div class="row mt-4">
                <div class="col-md-3">
                  <div class="card metric-card">
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('metric_total_requests'); ?></div>
                    <div class="metric-value"><?php echo $healthReport['system_metrics']['total_requests'] ?? 0 ?></div>
                    <div class="metric-label" style="font-size: 0.8rem;"><?php echo $CLICSHOPPING_ChatGpt->getDef('metric_since_startup'); ?></div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card metric-card">
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('metric_error_rate'); ?></div>
                    <div class="metric-value"
                         style="color: <?php echo ($healthReport['system_metrics']['error_rate'] > 0.1 ? 'var(--danger)' : 'var(--success)');?>">
                      <?php echo round($healthReport['system_metrics']['error_rate'] * 100, 2); ?>%
                    </div>
                    <div class="metric-label" style="font-size: 0.8rem;">
                      <?php echo $healthReport['system_metrics']['total_errors'] ?? 0 ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('metric_errors_count'); ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card metric-card">
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('metric_response_time'); ?></div>
                    <div class="metric-value"><?php echo round($healthReport['system_metrics']['avg_response_time'], 2); ?><small
                        style="font-size: 1.2rem;">s</small></div>
                    <div class="metric-label" style="font-size: 0.8rem;"><?php echo $CLICSHOPPING_ChatGpt->getDef('metric_average'); ?></div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card metric-card">
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('metric_memory_usage'); ?></div>
                    <div class="metric-value"
                         style="color: <?php echo ($healthReport['system_metrics']['memory_usage']['percentage'] > 80 ? 'var(--warning)' : 'var(--success)');?>">
                      <?php echo $healthReport['system_metrics']['memory_usage']['percentage'] ?>%
                    </div>
                    <div class="metric-label" style="font-size: 0.8rem;"><?php echo $CLICSHOPPING_ChatGpt->getDef('metric_current_usage'); ?></div>
                  </div>
                </div>
              </div>
              <?php else: ?>
              <!-- RAG NOT ENABLED MESSAGE -->
              <div class="row">
                <div class="col-12">
                  <div class="alert alert-info">
                    <h5><i class="bi bi-info-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_alert_dashboard'); ?></h5>
                    <p><?php echo $CLICSHOPPING_ChatGpt->getDef('rag_not_enabled_message'); ?></p>
                    <hr>
                    <p class="mb-0">
                      <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('to_enable_rag'); ?>:</strong><br>
                      1. <?php echo $CLICSHOPPING_ChatGpt->getDef('click_configure_button'); ?><br>
                      2. <?php echo $CLICSHOPPING_ChatGpt->getDef('enable_rag_bi_feature'); ?><br>
                      3. <?php echo $CLICSHOPPING_ChatGpt->getDef('return_to_dashboard'); ?>
                    </p>
                  </div>
                </div>
              </div>
              <?php endif; ?>
              
              <?php if ($config['rag_enabled']): ?>

              <!-- TOKEN USAGE CARDS -->
              <div class="row mt-4">
                <div class="col-md-3">
                  <div class="card metric-card" style="background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 100%);">
                    <div class="metric-label">🎯 <?php echo $CLICSHOPPING_ChatGpt->getDef('metric_tokens_total'); ?></div>
                    <div class="metric-value" style="color: var(--info);">
                      <?php echo !empty($tokenDashboardStats['total_tokens']) ? number_format($tokenDashboardStats['total_tokens']) : '0' ?>
                    </div>
                    <div class="metric-label" style="font-size: 0.8rem;">
                      <?php echo !empty($tokenDashboardStats['period']) ? $tokenDashboardStats['period'] : $CLICSHOPPING_ChatGpt->getDef('metric_tokens_7days') ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card metric-card" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                    <div class="metric-label">💰 <?php echo $CLICSHOPPING_ChatGpt->getDef('metric_estimated_cost'); ?></div>
                    <div class="metric-value" style="color: var(--secondary);">
                      $<?php echo !empty($tokenDashboardStats['cost_estimate']) ? number_format($tokenDashboardStats['cost_estimate'], 2) : '0.00' ?>
                    </div>
                    <div class="metric-label" style="font-size: 0.8rem;">
                      <?php echo !empty($tokenDashboardStats['total_requests']) ? $tokenDashboardStats['total_requests'] . ' ' . $CLICSHOPPING_ChatGpt->getDef('metric_requests_count') : '0 ' . $CLICSHOPPING_ChatGpt->getDef('metric_requests_count') ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card metric-card" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                    <div class="metric-label">📊 <?php echo $CLICSHOPPING_ChatGpt->getDef('metric_tokens_input'); ?></div>
                    <div class="metric-value" style="color: var(--success);">
                      <?php echo !empty($tokenDashboardStats['input_tokens']) ? number_format($tokenDashboardStats['input_tokens']) : '0' ?>
                    </div>
                    <div class="metric-label" style="font-size: 0.8rem;"><?php echo $CLICSHOPPING_ChatGpt->getDef('metric_tokens_entry'); ?></div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card metric-card" style="background: linear-gradient(135deg, #fff3e0 0%, #ffcc80 100%);">
                    <div class="metric-label">📈 <?php echo $CLICSHOPPING_ChatGpt->getDef('metric_tokens_output'); ?></div>
                    <div class="metric-value" style="color: var(--warning);">
                      <?php echo !empty($tokenDashboardStats['output_tokens']) ? number_format($tokenDashboardStats['output_tokens']) : '0' ?>
                    </div>
                    <div class="metric-label" style="font-size: 0.8rem;"><?php echo $CLICSHOPPING_ChatGpt->getDef('metric_tokens_exit'); ?></div>
                  </div>
                </div>
              </div>

              <!-- FEEDBACK CARDS - NOUVEAU -->
              <div class="row mt-4">
                <div class="col-md-3">
                  <div class="card metric-card" style="background: linear-gradient(135deg, #fce4ec 0%, #f8bbd0 100%);">
                    <div class="metric-label">⭐ <?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_satisfaction_rate'); ?></div>
                    <div class="metric-value"
                         style="color: <?php echo $feedbackStats['satisfaction_rate'] >= 85 ? 'var(--success)' : ($feedbackStats['satisfaction_rate'] >= 70 ? 'var(--warning)' : 'var(--danger)'); ?>;">
                      <?php echo $feedbackStats['satisfaction_rate'] ?>%
                    </div>
                    <div class="metric-label" style="font-size: 0.8rem;">
                      <?php
                      if ($feedbackStats['satisfaction_rate'] >= 85) {
                        echo $CLICSHOPPING_ChatGpt->getDef('feedback_excellent');
                      } elseif ($feedbackStats['satisfaction_rate'] >= 70) {
                        echo $CLICSHOPPING_ChatGpt->getDef('feedback_good');
                      } else {
                        echo $CLICSHOPPING_ChatGpt->getDef('feedback_to_improve');
                      }
                      ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card metric-card" style="background: linear-gradient(135deg, #e1f5fe 0%, #b3e5fc 100%);">
                    <div class="metric-label">📊 <?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_ratio'); ?></div>
                    <div class="metric-value"
                         style="color: <?php echo $feedbackStats['feedback_ratio'] >= 40 ? 'var(--success)' : ($feedbackStats['feedback_ratio'] >= 20 ? 'var(--info)' : 'var(--warning)'); ?>;">
                      <?php echo $feedbackStats['feedback_ratio'] ?>%
                    </div>
                    <div class="metric-label" style="font-size: 0.8rem;">
                      <?php echo $feedbackStats['total_feedback'] ?> / <?php echo $feedbackStats['total_interactions'] ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_interactions'); ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card metric-card" style="background: linear-gradient(135deg, #e8f5e9 0%, #a5d6a7 100%);">
                    <div class="metric-label">👍 <?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_positive'); ?></div>
                    <div class="metric-value" style="color: var(--success);">
                      <?php echo $feedbackStats['positive'] ?>
                    </div>
                    <div class="metric-label" style="font-size: 0.8rem;">
                      <?php if (!empty($feedbackStats['avg_ratings']['positive'])): ?>
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_rating'); ?>: <?php echo $feedbackStats['avg_ratings']['positive'] ?>/5 ⭐
                      <?php else: ?>
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_no_rating'); ?>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card metric-card" style="background: linear-gradient(135deg, #ffebee 0%, #ef9a9a 100%);">
                    <div class="metric-label">👎 <?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_negative'); ?></div>
                    <div class="metric-value" style="color: var(--danger);">
                      <?php echo $feedbackStats['negative'] ?>
                    </div>
                    <div class="metric-label" style="font-size: 0.8rem;">
                      <?php if (!empty($feedbackStats['avg_ratings']['negative'])): ?>
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_rating'); ?>: <?php echo $feedbackStats['avg_ratings']['negative'] ?>/5 ⭐
                      <?php else: ?>
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_no_rating'); ?>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <?php
          // -------------------------------------------------------------------
          //          TAB Components
          // -------------------------------------------------------------------
          ?>
          <?php if ($config['rag_enabled']): ?>

          <div class="tab-pane" id="tab2">
            <div class="row mt-4">
              <div class="col-12">
                <div class="card">
                  <div class="card-header">
                    🔧 <?php echo $CLICSHOPPING_ChatGpt->getDef('component_metrics'); ?>
                  </div>
                  <div class="card-body">
                    <div class="table-responsive">
                      <table class="table table-hover table-sm">
                        <thead class="table-light">
                        <tr>
                          <th><?php echo $CLICSHOPPING_ChatGpt->getDef('component_name'); ?></th>
                          <th><?php echo $CLICSHOPPING_ChatGpt->getDef('component_calls_total'); ?></th>
                          <th><?php echo $CLICSHOPPING_ChatGpt->getDef('component_success_rate'); ?></th>
                          <th><?php echo $CLICSHOPPING_ChatGpt->getDef('component_avg_time'); ?></th>
                          <th><?php echo $CLICSHOPPING_ChatGpt->getDef('component_status_label'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($healthReport['component_health'] ?? [] as $comp): ?>
                          <tr>
                            <td><strong><?php echo htmlspecialchars($comp['name']); ?></strong></td>
                            <td><?php echo $systemReport['components'][$comp['name']]['total_calls'] ?? 'N/A' ?></td>
                            <td>
                              <?php
                              $total = $systemReport['components'][$comp['name']]['total_calls'] ?? 0;
                              $success = $systemReport['components'][$comp['name']]['successful_calls'] ?? 0;
                              $rate = $total > 0 ? round(($success / $total) * 100, 1) : 0;
                              ?>
                              <span
                                style="color: <?php echo ($rate >= 95 ? 'var(--success)' : ($rate >= 80 ? 'var(--warning)' : 'var(--danger)'));?>">
                            <?php echo $rate ?>%
                          </span>
                            </td>
                            <td><?php echo round($systemReport['components'][$comp['name']]['avg_execution_time'] ?? 0, 3); ?>s</td>
                            <td>
                          <span
                            class="badge bg-<?php echo $comp['status'] === 'healthy' ? 'success' : ($comp['status'] === 'degraded' ? 'warning' : 'danger');?>">
                            <?php echo strtoupper($comp['status']); ?>
                          </span>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                        
                        <!-- WebSearch Component Row -->
                        <?php if (!empty($websearchStats) && $websearchStats['total_queries'] > 0): ?>
                          <tr style="background-color: #f0f8ff;">
                            <td><strong>🌐 Web Search</strong></td>
                            <td><?php echo $websearchStats['total_queries']; ?></td>
                            <td>
                              <span style="color: <?php echo ($websearchStats['success_rate'] >= 95 ? 'var(--success)' : ($websearchStats['success_rate'] >= 80 ? 'var(--warning)' : 'var(--danger)'));?>">
                                <?php echo $websearchStats['success_rate']; ?>%
                              </span>
                            </td>
                            <td><?php echo round($websearchStats['avg_response_time'] / 1000, 3); ?>s</td>
                            <td>
                              <span class="badge bg-<?php echo $websearchStats['status'] === 'healthy' ? 'success' : ($websearchStats['status'] === 'warning' ? 'warning' : 'danger');?>">
                                <?php echo strtoupper($websearchStats['status']); ?>
                              </span>
                            </td>
                          </tr>
                        <?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div style="padding: 20px;">
              <div class="component-health">
                <?php foreach ($healthReport['component_health'] ?? [] as $comp) {
                  ?>
                  <div class="component-card <?php echo $comp['status'];;?>">
                    <h6><?php echo htmlspecialchars($comp['name']); ?></h6>
                    <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('component_status_label'); ?>:</strong> <span
                        class="badge bg-<?php echo $comp['status'] === 'healthy' ? 'success' : ($comp['status'] === 'degraded' ? 'warning' : 'danger');;?>">
                          <?php echo strtoupper($comp['status']); ?>
                        </span></p>
                    <?php
                    if (!empty($comp['issues'])) {
                    ?>
                      <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('component_problems'); ?>:</strong></p>
                      <ul style="margin: 0; padding-left: 20px; font-size: 0.9rem;">
                        <?php
                        foreach ($comp['issues'] as $issue) {
                          ?>
                          <li><?php echo htmlspecialchars($issue); ?></li>
                        <?php }  ?>
                      </ul>
                    <?php
                    }
                    ?>
                  </div>
                <?php
                }
                ?>
              </div>
            </div>

            <!-- Source Breakdown Section -->
            <div class="row mt-4">
              <div class="col-12">
                <div class="card">
                  <div class="card-header">
                    <h6><i class="bi bi-diagram-3"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('source_breakdown'); ?></h6>
                  </div>
                  <div class="card-body">
                    <?php if (!empty($sourceStats['sources'])): ?>
                      <div class="row">
                        <!-- Source Statistics Table -->
                        <div class="col-md-7">
                          <table class="table table-sm table-hover">
                            <thead class="table-light">
                            <tr>
                              <th><?php echo $CLICSHOPPING_ChatGpt->getDef('source'); ?></th>
                              <th class="text-end"><?php echo $CLICSHOPPING_ChatGpt->getDef('count'); ?></th>
                              <th class="text-end"><?php echo $CLICSHOPPING_ChatGpt->getDef('percentage'); ?></th>
                              <th class="text-end"><?php echo $CLICSHOPPING_ChatGpt->getDef('success_rate'); ?></th>
                              <th class="text-end"><?php echo $CLICSHOPPING_ChatGpt->getDef('avg_time'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($sourceStats['sources'] as $source => $data): ?>
                              <tr>
                                <td>
                                  <?php
                                  $sourceIcons = [
                                    'documents' => '📚',
                                    'embeddings' => '🔍',
                                    'llm' => '🤖',
                                    'web_search' => '🌐',
                                    'analytics' => '📊',
                                    'hybrid' => '🔀',
                                    'conversation_memory' => '💭'
                                  ];
                                  $icon = $sourceIcons[$source] ?? '❓';
                                  echo $icon . ' ' . ucfirst(str_replace('_', ' ', $source));
                                  ?>
                                </td>
                                <td class="text-end"><?php echo number_format($data['count']); ?></td>
                                <td class="text-end"><?php echo $data['percentage']; ?>%</td>
                                <td class="text-end">
                                    <span class="badge <?php echo $data['success_rate'] >= 90 ? 'bg-success' : ($data['success_rate'] >= 70 ? 'bg-warning' : 'bg-danger'); ?>">
                                      <?php echo $data['success_rate']; ?>%
                                    </span>
                                </td>
                                <td class="text-end"><?php echo number_format($data['avg_response_time'], 0); ?>ms</td>
                              </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                            <tr>
                              <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('total'); ?></strong></td>
                              <td class="text-end"><strong><?php echo number_format($sourceStats['total_queries']); ?></strong></td>
                              <td class="text-end"><strong>100%</strong></td>
                              <td colspan="2"></td>
                            </tr>
                            </tfoot>
                          </table>
                        </div>

                        <!-- Source Distribution Chart -->
                        <div class="col-md-5">
                          <canvas id="sourceDistributionChart" height="250"></canvas>
                        </div>
                      </div>

                      <script>
                        // Source Distribution Pie Chart
                        (function() {
                          const sourceCtx = document.getElementById('sourceDistributionChart');
                          if (sourceCtx) {
                            new Chart(sourceCtx.getContext('2d'), {
                              type: 'pie',
                              data: {
                                labels: <?php echo json_encode(array_map(function($s) { return ucfirst(str_replace('_', ' ', $s)); }, array_keys($sourceStats['sources']))); ?>,
                                datasets: [{
                                  data: <?php echo json_encode(array_column($sourceStats['sources'], 'count')); ?>,
                                  backgroundColor: [
                                    'rgba(54, 162, 235, 0.8)',   // documents - blue
                                    'rgba(75, 192, 192, 0.8)',   // embeddings - teal
                                    'rgba(255, 159, 64, 0.8)',   // llm - orange
                                    'rgba(153, 102, 255, 0.8)',  // web_search - purple
                                    'rgba(255, 99, 132, 0.8)',   // analytics - red
                                    'rgba(255, 206, 86, 0.8)',   // hybrid - yellow
                                    'rgba(201, 203, 207, 0.8)'   // conversation_memory - gray
                                  ],
                                  borderWidth: 2,
                                  borderColor: '#fff'
                                }]
                              },
                              options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                  legend: {
                                    position: 'bottom',
                                    labels: {
                                      padding: 10,
                                      font: {
                                        size: 11
                                      }
                                    }
                                  },
                                  tooltip: {
                                    callbacks: {
                                      label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = <?php echo $sourceStats['total_queries']; ?>;
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                        return label + ': ' + value + ' (' + percentage + '%)';
                                      }
                                    }
                                  }
                                }
                              }
                            });
                          }
                        })();
                      </script>
                    <?php else: ?>
                      <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('no_source_data'); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <?php
          // -------------------------------------------------------------------
          //          TAB Alerts
          // -------------------------------------------------------------------
          ?>

          <div class="tab-pane" id="tab3">
            <div style="padding: 20px;">
              <div class="row mb-3">
                <div class="col-12">
                  <h5>🚨 <?php echo $CLICSHOPPING_ChatGpt->getDef('tab_alert'); ?></h5>
                  <p class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('alert_description'); ?></p>
                </div>
              </div>
              
              <?php if (empty($activeAlerts)): ?>
                <div class="alert alert-success">
                  <i class="bi bi-check-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('alert_no_active'); ?>
                </div>
              <?php else: ?>
                <div class="alert alert-info mb-3">
                  <i class="bi bi-info-circle"></i> 
                  <strong><?php echo count($activeAlerts); ?></strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('alert_active_count'); ?>
                </div>
                
                <?php foreach ($activeAlerts as $alertType => $alert): ?>
                  <div class="alert-item <?php echo $alert['severity'] ?? 'medium';?> mb-3">
                    <div style="flex: 1;">
                      <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-<?php echo $alert['severity'] === 'critical' ? 'danger' : ($alert['severity'] === 'high' ? 'warning' : 'info'); ?> me-2">
                          <?php echo strtoupper($alert['severity'] ?? 'MEDIUM'); ?>
                        </span>
                        <strong><?php echo htmlspecialchars($alert['message']); ?></strong>
                      </div>
                      
                      <p style="margin: 5px 0; font-size: 0.9rem; color: #6b7280;">
                        <i class="bi bi-clock"></i> 
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('alert_triggered_ago'); ?> 
                        <?php echo round((time() - $alert['triggered_at']) / 60); ?> 
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('alert_minutes_ago'); ?>
                      </p>
                      
                      <?php if (isset($alert['current_value']) && isset($alert['threshold'])): ?>
                        <p style="margin: 5px 0; font-size: 0.85rem; color: #9ca3af;">
                          <i class="bi bi-graph-up"></i> 
                          <?php echo $CLICSHOPPING_ChatGpt->getDef('alert_current_value'); ?>: 
                          <strong><?php echo is_numeric($alert['current_value']) ? round($alert['current_value'], 2) : $alert['current_value']; ?></strong> | 
                          <?php echo $CLICSHOPPING_ChatGpt->getDef('alert_threshold'); ?>: 
                          <strong><?php echo is_numeric($alert['threshold']) ? round($alert['threshold'], 2) : $alert['threshold']; ?></strong>
                        </p>
                      <?php endif; ?>
                      
                      <?php if (!empty($alert['acknowledged'])): ?>
                        <p style="margin: 5px 0; font-size: 0.85rem; color: #10b981;">
                          <i class="bi bi-check-circle"></i> 
                          <?php echo $CLICSHOPPING_ChatGpt->getDef('alert_acknowledged_at'); ?> 
                          <?php echo date('Y-m-d H:i:s', $alert['acknowledged_at']); ?>
                        </p>
                      <?php endif; ?>
                    </div>
                    
                    <div class="d-flex flex-column gap-2">
                      <?php if (empty($alert['acknowledged'])): ?>
                        <form method="post" style="display: inline;">
                          <input type="hidden" name="action" value="acknowledge_alert">
                          <input type="hidden" name="alert_type" value="<?php echo htmlspecialchars($alertType);?>">
                          <button type="submit" class="btn btn-sm btn-outline-primary btn-action" title="<?php echo $CLICSHOPPING_ChatGpt->getDef('alert_acknowledge_tooltip'); ?>">
                            <i class="bi bi-check"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('alert_acknowledge'); ?>
                          </button>
                        </form>
                      <?php endif; ?>
                      
                      <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="resolve_alert">
                        <input type="hidden" name="alert_type" value="<?php echo htmlspecialchars($alertType);?>">
                        <input type="hidden" name="resolution" value="Resolved manually from dashboard">
                        <button type="submit" class="btn btn-sm btn-outline-success btn-action" title="<?php echo $CLICSHOPPING_ChatGpt->getDef('alert_resolve_tooltip'); ?>">
                          <i class="bi bi-check-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('alert_resolve'); ?>
                        </button>
                      </form>
                      
                      <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="escalate_alert">
                        <input type="hidden" name="alert_type" value="<?php echo htmlspecialchars($alertType);?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning btn-action" title="<?php echo $CLICSHOPPING_ChatGpt->getDef('alert_escalate_tooltip'); ?>">
                          <i class="bi bi-exclamation-triangle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('alert_escalate'); ?>
                        </button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
          <?php
          // -------------------------------------------------------------------
          //          TAB Trends
          // -------------------------------------------------------------------
          ?>
          <div class="tab-pane" id="tab4">
            <div style="padding: 20px;">
              <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('trend_analysis'); ?></h5>
              <?php if (isset($healthReport['trends']) && !isset($healthReport['trends']['insufficient_data'])): ?>
                <table class="table table-sm">
                  <thead>
                  <tr>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('trend_metric'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('trend_trend'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('trend_change'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('trend_current_value'); ?></th>
                  </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($healthReport['trends'] as $metric => $trend): ?>
                    <tr>
                      <td><?php echo ucfirst(str_replace('_', ' ', $metric)); ?></td>
                      <td class="trend-<?php echo $trend['trend'];?>">
                        <?php echo $trend['trend'] === 'increasing' ? '↗' : ($trend['trend'] === 'decreasing' ? '↘' : '→'); ?>
                        <?php echo ucfirst($trend['trend']); ?>
                      </td>
                      <td><?php echo $trend['percent_change'] ?>%</td>
                      <td><?php echo $trend['current_value'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <div class="alert alert-info"><?php echo $CLICSHOPPING_ChatGpt->getDef('trend_insufficient_data'); ?></div>
              <?php endif; ?>
            </div>
          </div>

          <?php
          // -------------------------------------------------------------------
          //          TAB Token & Cost
          // -------------------------------------------------------------------
          ?>
          <div class="tab-pane" id="tab5">
            <div style="padding: 20px;">
              <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('token_consumption_title'); ?></h5>

              <?php if (!empty($tokenDashboardStats)): ?>
                <!-- Résumé des tokens -->
                <div class="row mb-4">
                  <div class="col-md-6">
                    <div class="card">
                      <div class="card-header">
                        <h6><i class="bi bi-pie-chart"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('token_distribution'); ?></h6>
                      </div>
                      <div class="card-body" style="height: 210px; text-align: center;">
                        <canvas id="tokenDistributionChart" height="150"></canvas>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="card">
                      <div class="card-header">
                        <h6><i class="bi bi-currency-dollar"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('token_cost_analysis'); ?></h6>
                      </div>
                      <div class="card-body">
                        <table class="table table-sm table-borderless">
                          <tr>
                            <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('token_total_cost_7d'); ?>:</strong></td>
                            <td class="text-end">$<?php echo number_format($tokenDashboardStats['cost_estimate'] ?? 0, 4); ?>
                            </td>
                          </tr>
                          <tr>
                            <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('token_avg_cost_per_request'); ?>:</strong></td>
                            <td class="text-end">
                              $<?php echo ($tokenDashboardStats['total_requests'] ?? 0) > 0 ?
                                number_format(($tokenDashboardStats['total_cost'] ?? 0) / $tokenDashboardStats['total_requests'], 4) : '0.0000' ?>
                            </td>
                          </tr>
                          <tr>
                            <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('token_per_dollar'); ?>:</strong></td>
                            <td class="text-end">
                              <?php 
                              $costEstimate = $tokenDashboardStats['cost_estimate'] ?? 0;
                              if ($costEstimate > 0.0001) {
                                // Normal cost - show tokens per dollar
                                echo number_format(($tokenDashboardStats['total_tokens'] ?? 0) / $costEstimate, 0);
                              } elseif ($costEstimate > 0) {
                                // Very small cost - show as "~Free"
                                echo '~∞ (Free)';
                              } else {
                                // No cost data
                                echo 'N/A';
                              }
                              ?>
                            </td>
                          </tr>
                          <tr>
                            <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('token_efficiency'); ?>:</strong></td>
                            <td class="text-end">
                                <span
                                  class="badge badge-<?php echo ($tokenDashboardStats['avg_tokens_per_request'] ?? 0) < 1000 ? 'success' :
                                    (($tokenDashboardStats['avg_tokens_per_request'] ?? 0) < 2000 ? 'warning' : 'danger');?>">
                                  <?php echo number_format($tokenDashboardStats['avg_tokens_per_request'] ?? 0, 0); ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('token_tokens_per_req'); ?>
                                </span>
                            </td>
                          </tr>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Usage quotidien -->
                <?php if (!empty($tokenDashboardStats['daily_usage'])): ?>
                  <div class="card mb-4">
                    <div class="card-header">
                      <h6><i class="bi bi-calendar3"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('token_daily_usage'); ?></h6>
                    </div>
                    <div class="card-body" style="height: 280px; text-align: center;">
                      <canvas id="dailyTokenUsageChart" height="80"></canvas>
                    </div>
                  </div>
                <?php endif; ?>

                <!-- Top types de requêtes -->
                <?php if (!empty($tokenDashboardStats['top_request_types'])): ?>
                  <div class="card">
                    <div class="card-header">
                      <h6><i class="bi bi-list-ol"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('token_top_request_types'); ?></h6>
                    </div>
                    <div class="card-body">
                      <div class="table-responsive">
                        <table class="table table-sm table-hover">
                          <thead>
                          <tr>
                            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('token_request_type'); ?></th>
                            <th class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('token_count'); ?></th>
                            <th class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('token_tokens'); ?></th>
                            <th class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('token_average'); ?></th>
                            <th class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('token_percent_total'); ?></th>
                          </tr>
                          </thead>
                          <tbody>
                          <?php foreach ($tokenDashboardStats['top_request_types'] as $type): ?>
                            <tr>
                              <td>
                                <strong><?php echo htmlspecialchars($type['request_type']); ?></strong>
                              </td>
                              <td class="text-center"><?php echo $type['count'] ?></td>
                              <td class="text-center"><?php echo number_format($type['tokens']); ?></td>
                              <td class="text-center"><?php echo number_format($type['avg_tokens'] ?? 0, 0); ?></td>
                              <td class="text-center">
                                <?php
                                $percentage = ($tokenDashboardStats['total_tokens'] ?? 0) > 0 ?
                                  ($type['tokens'] / $tokenDashboardStats['total_tokens']) * 100 : 0;
                                ?>
                                <span class="badge badge-primary"><?php echo number_format($percentage, 1); ?>%</span>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>

                <?php
                $tab5Charts = [
                  [
                    'id' => 'tab5_total_tokens_daily',
                    'icon' => 'bi bi-bar-chart-line',
                    'title' => $tokenChartData['daily_total_tokens']['title'],
                    'description' => $CLICSHOPPING_ChatGpt->getDef('token_activity_30_days'),
                    'chart' => $tokenChartData['daily_total_tokens']['chart']
                  ],
                  [
                    'id' => 'tab5_total_tokens_monthly',
                    'icon' => 'bi bi-activity',
                    'title' => $tokenChartData['monthly_total_tokens']['title'],
                    'description' => $CLICSHOPPING_ChatGpt->getDef('token_cumulative_12_months'),
                    'chart' => $tokenChartData['monthly_total_tokens']['chart']
                  ],
                  [
                    'id' => 'tab5_cost_estimation',
                    'icon' => 'bi bi-currency-dollar',
                    'title' => $tokenChartData['cost_estimation']['title'],
                    'description' => $CLICSHOPPING_ChatGpt->getDef('token_cost_by_model'),
                    'chart' => $tokenChartData['cost_estimation']['chart']
                  ],
                ];
                ?>

                <div class="row mt-4">
                  <?php foreach ($tab5Charts as $chartMeta): ?>
                    <div class="col-md-4 mb-4">
                      <div class="card h-100">
                        <div class="card-header">
                          <h6><i class="<?php echo $chartMeta['icon']; ?>"></i> <?php echo $chartMeta['title']; ?></h6>
                          <?php if (!empty($chartMeta['description'])): ?>
                            <small class="text-muted d-block"><?php echo $chartMeta['description']; ?></small>
                          <?php endif; ?>
                        </div>
                        <div class="card-body" style="height: 260px;">
                          <?php
                          $chartConfig = htmlspecialchars(json_encode($chartMeta['chart'], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                          ?>
                          <canvas id="<?php echo $chartMeta['id']; ?>"
                                  class="chatgpt-token-chart"
                                  data-chart-config="<?php echo $chartConfig; ?>"
                                  style="width: 100%; height: 220px;"></canvas>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>

              <?php else: ?>
                <div class="alert alert-warning">
                  <i class="bi bi-exclamation-triangle"></i>
                  <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('token_no_data'); ?></strong><br>
                  <?php echo $CLICSHOPPING_ChatGpt->getDef('token_tracking_not_configured'); ?>
                  <hr>
                  <small>
                    <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('token_to_activate'); ?>:</strong><br>
                    1. <?php echo $CLICSHOPPING_ChatGpt->getDef('token_verify_tracker'); ?><br>
                    2. <?php echo $CLICSHOPPING_ChatGpt->getDef('token_make_requests'); ?><br>
                    3. <?php echo $CLICSHOPPING_ChatGpt->getDef('token_refresh_page'); ?>
                  </small>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <?php
          // -------------------------------------------------------------------
          //          TAB Classification & Performance
          // -------------------------------------------------------------------
          ?>

          <div class="tab-pane" id="tab6">
            <div class="row mt-4">
                <!-- <?php echo $CLICSHOPPING_ChatGpt->getDef('tab6_global_metrics'); ?> -->
                <div class="row mb-4">
                  <?php
                  if (!empty($advancedStats['agents']['total_usage'])) {
                    ?>
                  <div class="col-md-2">
                    <div class="card text-center"
                         style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo $advancedStats['agents']['total_usage']; ?></h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('agents_total_usage'); ?></p>
                        <small><?php echo $advancedStats['agents']['period_days']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('time_days'); ?></small>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="card text-center"
                         style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo $advancedStats['agents']['avg_success_rate']; ?>%</h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('agents_avg_success_rate'); ?></p>
                        <small><?php echo $CLICSHOPPING_ChatGpt->getDef('agents_all'); ?></small>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="card text-center"
                         style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo htmlspecialchars($advancedStats['agents']['most_used']); ?></h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('agents_most_used'); ?></p>
                        <small><?php echo $CLICSHOPPING_ChatGpt->getDef('agents_main'); ?></small>
                      </div>
                    </div>
                  </div>
                  <?php
                }
                ?>

                <?php
                if (!empty($advancedStats['classification']['total_requests'])) {
                  ?>
                  <div class="col-md-2">
                    <div class="card text-center"
                         style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo $advancedStats['classification']['overall_precision']; ?>%</h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('classification_global_precision'); ?></p>
                        <small><?php echo $CLICSHOPPING_ChatGpt->getDef('classification_based_on_confidence'); ?></small>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="card text-center"
                         style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo $advancedStats['classification']['analytics']['count']; ?></h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('classification_analytics'); ?></p>
                        <small><?php echo $advancedStats['classification']['analytics']['percentage']; ?>%</small>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-2">
                    <div class="card text-center"
                         style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo $advancedStats['classification']['semantic']['count']; ?></h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('classification_semantic'); ?></p>
                        <small><?php echo $advancedStats['classification']['semantic']['percentage']; ?>%</small>
                      </div>
                    </div>
                  </div>
                <?php
                }
                 ?>

              </div>
              <div class="col-md-6">
                <div class="card">
                  <div class="card-header">
                    <?php echo $CLICSHOPPING_ChatGpt->getDef('perf_evolution'); ?>
                  </div>
                  <div class="card-body">
                    <canvas id="performanceChart" height="80"></canvas>
                  </div>
                </div>
              </div>



              <div class="col-md-6">
                <div class="row">

                  <div class="col-md-6">
                    <div class="card">
                      <div class="card-header"><?php echo $CLICSHOPPING_ChatGpt->getDef('perf_severity_distribution'); ?></div>
                      <div class="card-body d-flex justify-content-center align-items-center" style="height:275px;">
                        <canvas id="alertSeverityChart"></canvas>
                      </div>
                    </div>
                  </div>
                  <?php
                  if (!empty($advancedStats['agents']['total_usage'])) {
                    ?>
                    <div class="col-md-6">
                      <div class="card">
                        <div class="card-header"><?php echo $CLICSHOPPING_ChatGpt->getDef('perf_agent_distribution'); ?></div>
                        <div class="card-body d-flex justify-content-center align-items-center" style="height:275px;">
                          <canvas id="agentsChart"></canvas>
                        </div>
                      </div>
                    </div>
                    <?php
                  }
                  ?>
                </div>
              </div>




            </div>

            <?php if (!empty($advancedStats['agents']['total_usage'])): ?>


              <!-- Détails par Agent -->
              <div class="row">
                <div class="col-md-12">
                  <div class="card">
                    <div class="card-header">
                      <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('perf_agent_performance'); ?></h6>
                    </div>
                    <div class="card-body">
                      <div class="table-responsive">
                        <table class="table table-striped">
                          <thead>
                          <tr>
                            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('perf_agent_name'); ?></th>
                            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('perf_agent_usage'); ?></th>
                            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('perf_agent_percentage'); ?></th>
                            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('perf_agent_success_rate'); ?></th>
                            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('perf_agent_avg_confidence'); ?></th>
                          </tr>
                          </thead>
                          <tbody>
                          <?php foreach ($advancedStats['agents']['agents'] as $agent): ?>
                            <tr>
                              <td><strong><?php echo htmlspecialchars($agent['name']); ?></strong></td>
                              <td><?php echo $agent['usage_count']; ?></td>
                              <td>
                                <div class="progress" style="width: 60px; height: 20px;">
                                  <div class="progress-bar bg-info" style="width: <?php echo $agent['percentage']; ?>%"></div>
                                </div>
                                <?php echo $agent['percentage']; ?>%
                              </td>
                              <td>
                          <span
                            class="badge <?php echo $agent['success_rate'] >= 80 ? 'bg-success' : ($agent['success_rate'] >= 60 ? 'bg-warning' : 'bg-danger');;?>">
                            <?php echo $agent['success_rate']; ?>%
                          </span>
                              </td>
                              <td><?php echo $agent['avg_confidence']; ?>%</td>
                            </tr>
                          <?php endforeach; ?>
                          
                          <!-- WebSearch Agent Row -->
                          <?php if (!empty($websearchStats) && $websearchStats['total_queries'] > 0): ?>
                            <tr style="background-color: #f0f8ff;">
                              <td><strong>🌐 Web Search</strong></td>
                              <td><?php echo $websearchStats['total_queries']; ?></td>
                              <td>
                                <?php 
                                $totalAgentUsage = $advancedStats['agents']['total_usage'] ?? 1;
                                $websearchPercentage = round(($websearchStats['total_queries'] / $totalAgentUsage) * 100, 1);
                                ?>
                                <div class="progress" style="width: 60px; height: 20px;">
                                  <div class="progress-bar bg-info" style="width: <?php echo $websearchPercentage; ?>%"></div>
                                </div>
                                <?php echo $websearchPercentage; ?>%
                              </td>
                              <td>
                                <span class="badge <?php echo $websearchStats['success_rate'] >= 80 ? 'bg-success' : ($websearchStats['success_rate'] >= 60 ? 'bg-warning' : 'bg-danger');?>">
                                  <?php echo $websearchStats['success_rate']; ?>%
                                </span>
                              </td>
                              <td><?php echo $websearchStats['avg_confidence']; ?>%</td>
                            </tr>
                          <?php endif; ?>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php else: ?>
              <div class="alert alert-info">
                <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('perf_no_agent_data'); ?></h6>
                <p><?php echo $CLICSHOPPING_ChatGpt->getDef('perf_agent_data_info'); ?></p>
              </div>
            <?php endif; ?>

            <!-- Reasoning Modes Statistics Section -->
            <?php
            // Get reasoning agent stats if available (from database for persistence)
            $reasoningStats = null;
            try {
              if ($config['rag_enabled'] && class_exists('ClicShopping\AI\CoreAI\Orchestrator\ReasoningAgent')) {
                $reasoningAgent = new \ClicShopping\AI\CoreAI\Orchestrator\ReasoningAgent();
                // Use getPersistentStats() to retrieve database statistics (persists across sessions)
                $reasoningStats = $reasoningAgent->getPersistentStats(30); // Last 30 days
              }
            } catch (\Exception $e) {
              // Silently fail if ReasoningAgent is not available
              error_log('Dashboard: Failed to load ReasoningAgent stats - ' . $e->getMessage());
            }

            if (!empty($reasoningStats) && !empty($reasoningStats['by_mode'])):
              $hasData = false;
              foreach ($reasoningStats['by_mode'] as $modeStats) {
                if ($modeStats['count'] > 0) {
                  $hasData = true;
                  break;
                }
              }

              if ($hasData):
            ?>
              <div class="row mt-4">
                <div class="col-md-12">
                  <div class="card">
                    <div class="card-header">
                      <h6><i class="bi bi-lightbulb"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('reasoning_modes_title'); ?></h6>
                    </div>
                    <div class="card-body">
                      <div class="table-responsive">
                        <table class="table table-striped">
                          <thead>
                          <tr>
                            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('reasoning_mode_usage'); ?></th>
                            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('reasoning_mode_usage'); ?></th>
                            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('reasoning_mode_success_rate'); ?></th>
                            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('reasoning_mode_avg_confidence'); ?></th>
                            <th>Metric</th>
                          </tr>
                          </thead>
                          <tbody>
                          <?php
                          $modeNames = [
                            'chain_of_thought' => $CLICSHOPPING_ChatGpt->getDef('reasoning_mode_cot'),
                            'tree_of_thought' => $CLICSHOPPING_ChatGpt->getDef('reasoning_mode_tot'),
                            'self_consistency' => $CLICSHOPPING_ChatGpt->getDef('reasoning_mode_sc'),
                          ];

                          $modeIcons = [
                            'chain_of_thought' => '🔗',
                            'tree_of_thought' => '🌳',
                            'self_consistency' => '🎯',
                          ];

                          foreach ($reasoningStats['by_mode'] as $mode => $modeStats):
                            if ($modeStats['count'] == 0) continue;

                            $successRate = $modeStats['count'] > 0
                              ? round(($modeStats['successful'] / $modeStats['count']) * 100, 1)
                              : 0;

                            $avgConfidence = round($modeStats['avg_confidence'] * 100, 1);

                            // Mode-specific metric
                            $specificMetric = '';
                            if ($mode === 'chain_of_thought') {
                              $specificMetric = round($modeStats['avg_steps'], 1) . ' ' . $CLICSHOPPING_ChatGpt->getDef('reasoning_mode_avg_steps');
                            } elseif ($mode === 'tree_of_thought') {
                              $specificMetric = round($modeStats['avg_paths'], 1) . ' ' . $CLICSHOPPING_ChatGpt->getDef('reasoning_mode_avg_paths');
                            } elseif ($mode === 'self_consistency') {
                              $specificMetric = round($modeStats['avg_attempts'], 1) . ' ' . $CLICSHOPPING_ChatGpt->getDef('reasoning_mode_avg_attempts');
                              $specificMetric .= ' / ' . round($modeStats['avg_agreement'] * 100, 1) . '% ' . $CLICSHOPPING_ChatGpt->getDef('reasoning_mode_avg_agreement');
                            }
                          ?>
                            <tr>
                              <td>
                                <strong><?php echo $modeIcons[$mode]; ?> <?php echo $modeNames[$mode]; ?></strong>
                              </td>
                              <td><?php echo $modeStats['count']; ?></td>
                              <td>
                                <span class="badge <?php echo $successRate >= 80 ? 'bg-success' : ($successRate >= 60 ? 'bg-warning' : 'bg-danger');?>">
                                  <?php echo $successRate; ?>%
                                </span>
                              </td>
                              <td><?php echo $avgConfidence; ?>%</td>
                              <td><small><?php echo $specificMetric; ?></small></td>
                            </tr>
                          <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>

                      <!-- Summary Cards -->
                      <div class="row mt-3">
                        <div class="col-md-4">
                          <div class="card text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <div class="card-body">
                              <h3><?php echo $reasoningStats['total_reasonings']; ?></h3>
                              <p class="mb-0">Total Reasonings</p>
                              <small><?php echo $reasoningStats['success_rate']; ?> success</small>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="card text-center" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                            <div class="card-body">
                              <h3><?php echo round($reasoningStats['avg_steps'], 1); ?></h3>
                              <p class="mb-0">Avg Steps</p>
                              <small>All modes</small>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-4">
                          <div class="card text-center" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                            <div class="card-body">
                              <?php
                              $mostUsedMode = '';
                              $maxCount = 0;
                              foreach ($reasoningStats['by_mode'] as $mode => $modeStats) {
                                if ($modeStats['count'] > $maxCount) {
                                  $maxCount = $modeStats['count'];
                                  $mostUsedMode = $mode;
                                }
                              }
                              ?>
                              <h3><?php echo $modeIcons[$mostUsedMode] ?? ''; ?></h3>
                              <p class="mb-0">Most Used</p>
                              <small><?php echo $modeNames[$mostUsedMode] ?? 'N/A'; ?></small>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php
              endif;
            endif;
            ?>

          </div>
          <?php
          // -------------------------------------------------------------------
          //          ONGLET Latency & Fast-Lane (TASK 4.4.2.3)
          // -------------------------------------------------------------------
          ?>
          <div class="tab-pane" id="tab_latency">
            <div class="container-fluid py-4">
              <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_title'); ?></h5>
              
              <?php if ($latencyMetrics !== null && !empty($latencyMetrics['overall']['count'])): ?>
                
                <!-- LATENCY METRICS CARDS -->
                <div class="row mt-4">
                  <div class="col-md-3">
                    <div class="card metric-card" style="background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 100%);">
                      <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_avg_global'); ?></div>
                      <div class="metric-value" style="color: var(--info);">
                        <?php echo round($latencyMetrics['overall']['mean'], 2); ?><small style="font-size: 1.2rem;">ms</small>
                      </div>
                      <div class="metric-label" style="font-size: 0.8rem;">
                        <?php echo $latencyMetrics['overall']['count']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_requests'); ?>
                      </div>
                    </div>
                  </div>
                  
                  <div class="col-md-3">
                    <div class="card metric-card" style="background: linear-gradient(135deg, #e8f5e8 0%, #c8e6c9 100%);">
                      <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_fast_lane'); ?></div>
                      <div class="metric-value" style="color: var(--success);">
                        <?php echo round($latencyMetrics['fast_lane']['mean'], 2); ?><small style="font-size: 1.2rem;">ms</small>
                      </div>
                      <div class="metric-label" style="font-size: 0.8rem;">
                        <?php echo $latencyMetrics['fast_lane']['count']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_fast_requests'); ?>
                      </div>
                    </div>
                  </div>
                  
                  <div class="col-md-3">
                    <div class="card metric-card" style="background: linear-gradient(135deg, #fff3e0 0%, #ffcc80 100%);">
                      <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_full_orchestration'); ?></div>
                      <div class="metric-value" style="color: var(--warning);">
                        <?php echo round($latencyMetrics['full_orchestration']['mean'], 2); ?><small style="font-size: 1.2rem;">ms</small>
                      </div>
                      <div class="metric-label" style="font-size: 0.8rem;">
                        <?php echo $latencyMetrics['full_orchestration']['count']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_full_requests'); ?>
                      </div>
                    </div>
                  </div>
                  
                  <div class="col-md-3">
                    <div class="card metric-card" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                      <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_performance_gain'); ?></div>
                      <div class="metric-value" style="color: var(--secondary);">
                        <?php echo $latencyMetrics['fast_lane_efficiency']['speedup_factor']; ?>x
                      </div>
                      <div class="metric-label" style="font-size: 0.8rem;">
                        <?php echo round($latencyMetrics['fast_lane_efficiency']['percentage_faster'], 1); ?>% <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_faster'); ?>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- PERCENTILES TABLE -->
                <div class="row mt-4">
                  <div class="col-md-12">
                    <div class="card">
                      <div class="card-header">
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_percentiles'); ?>
                      </div>
                      <div class="card-body">
                        <div class="table-responsive">
                          <table class="table table-hover table-sm">
                            <thead class="table-light">
                              <tr>
                                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_metric'); ?></th>
                                <th class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_min'); ?></th>
                                <th class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_median'); ?></th>
                                <th class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_p75'); ?></th>
                                <th class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_p90'); ?></th>
                                <th class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_p95'); ?></th>
                                <th class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_p99'); ?></th>
                                <th class="text-center"><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_max'); ?></th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_global'); ?></strong></td>
                                <td class="text-center"><?php echo round($latencyMetrics['overall']['min'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['overall']['percentiles']['p50'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['overall']['percentiles']['p75'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['overall']['percentiles']['p90'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['overall']['percentiles']['p95'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['overall']['percentiles']['p99'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['overall']['max'], 2); ?>ms</td>
                              </tr>
                              <tr style="background-color: #e8f5e9;">
                                <td><strong>🚀 Fast-Lane</strong></td>
                                <td class="text-center"><?php echo round($latencyMetrics['fast_lane']['min'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['fast_lane']['percentiles']['p50'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['fast_lane']['percentiles']['p75'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['fast_lane']['percentiles']['p90'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['fast_lane']['percentiles']['p95'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['fast_lane']['percentiles']['p99'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['fast_lane']['max'], 2); ?>ms</td>
                              </tr>
                              <tr style="background-color: #fff3e0;">
                                <td><strong>🔄 Orchestration</strong></td>
                                <td class="text-center"><?php echo round($latencyMetrics['full_orchestration']['min'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['full_orchestration']['percentiles']['p50'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['full_orchestration']['percentiles']['p75'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['full_orchestration']['percentiles']['p90'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['full_orchestration']['percentiles']['p95'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['full_orchestration']['percentiles']['p99'], 2); ?>ms</td>
                                <td class="text-center"><?php echo round($latencyMetrics['full_orchestration']['max'], 2); ?>ms</td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- CHARTS ROW -->
                <div class="row mt-4">
                  <div class="col-md-6">
                    <div class="card">
                      <div class="card-header">
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_comparison'); ?>
                      </div>
                      <div class="card-body" style="height: 300px;">
                        <canvas id="latencyComparisonChart"></canvas>
                      </div>
                    </div>
                  </div>
                  
                  <div class="col-md-6">
                    <div class="card">
                      <div class="card-header">
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_percentiles_dist'); ?>
                      </div>
                      <div class="card-body" style="height: 300px;">
                        <canvas id="percentilesChart"></canvas>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div class="row mt-4">
                  <div class="col-md-6">
                    <div class="card">
                      <div class="card-header">
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_query_distribution'); ?>
                      </div>
                      <div class="card-body" style="height: 300px;">
                        <canvas id="queryDistributionChart"></canvas>
                      </div>
                    </div>
                  </div>
                  
                  <div class="col-md-6">
                    <div class="card">
                      <div class="card-header">
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_speedup_factor'); ?>
                      </div>
                      <div class="card-body" style="height: 300px;">
                        <canvas id="efficiencyGaugeChart"></canvas>
                      </div>
                    </div>
                  </div>
                </div>
                
                <!-- EFFICIENCY ANALYSIS -->
                <div class="row mt-4">
                  <div class="col-md-6">
                    <div class="card">
                      <div class="card-header">
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_efficiency_analysis'); ?>
                      </div>
                      <div class="card-body"></div>
                        <table class="table table-sm table-borderless">
                          <tr>
                            <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_speedup'); ?>:</strong></td>
                            <td class="text-end">
                              <span class="badge bg-success" style="font-size: 1.1rem;">
                                <?php echo $latencyMetrics['fast_lane_efficiency']['speedup_factor']; ?>x <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_faster'); ?>
                              </span>
                            </td>
                          </tr>
                          <tr>
                            <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_time_saved'); ?>:</strong></td>
                            <td class="text-end">
                              <strong><?php echo round($latencyMetrics['fast_lane_efficiency']['time_saved_ms'], 2); ?>ms</strong>
                            </td>
                          </tr>
                          <tr>
                            <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_percent_faster'); ?>:</strong></td>
                            <td class="text-end">
                              <strong><?php echo round($latencyMetrics['fast_lane_efficiency']['percentage_faster'], 1); ?>%</strong>
                            </td>
                          </tr>
                          <tr>
                            <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_fast_lane_requests'); ?>:</strong></td>
                            <td class="text-end">
                              <?php 
                                $fastLanePercentage = ($latencyMetrics['overall']['count'] > 0) 
                                  ? round(($latencyMetrics['fast_lane']['count'] / $latencyMetrics['overall']['count']) * 100, 1) 
                                  : 0;
                              ?>
                              <?php echo $latencyMetrics['fast_lane']['count']; ?> / <?php echo $latencyMetrics['overall']['count']; ?>
                              (<?php echo $fastLanePercentage; ?>%)
                            </td>
                          </tr>
                          <tr>
                            <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_total_time_saved'); ?>:</strong></td>
                            <td class="text-end">
                              <?php 
                                $totalTimeSaved = $latencyMetrics['fast_lane']['count'] * $latencyMetrics['fast_lane_efficiency']['time_saved_ms'];
                              ?>
                              <strong><?php echo round($totalTimeSaved / 1000, 2); ?>s</strong>
                            </td>
                          </tr>
                        </table>
                      </div>
                    </div>
                  </div>
                  
                  <div class="col-md-6">
                    <div class="card">
                      <div class="card-header">
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_recommendations'); ?>
                      </div>
                      <div class="card-body">
                        <?php
                        $recommendations = [];
                        
                        if ($fastLanePercentage < 30) {
                          $recommendations[] = [
                            'icon' => '⚠️',
                            'text' => str_replace('{percentage}', $fastLanePercentage, $CLICSHOPPING_ChatGpt->getDef('latency_rec_low_usage_text')),
                            'type' => 'warning'
                          ];
                        } elseif ($fastLanePercentage > 70) {
                          $recommendations[] = [
                            'icon' => '✅',
                            'text' => str_replace('{percentage}', $fastLanePercentage, $CLICSHOPPING_ChatGpt->getDef('latency_rec_excellent_text')),
                            'type' => 'success'
                          ];
                        }
                        
                        if ($latencyMetrics['overall']['percentiles']['p95'] > 5000) {
                          $recommendations[] = [
                            'icon' => '🐌',
                            'text' => $CLICSHOPPING_ChatGpt->getDef('latency_rec_p95_high'),
                            'type' => 'danger'
                          ];
                        }
                        
                        if ($latencyMetrics['fast_lane_efficiency']['speedup_factor'] > 3) {
                          $recommendations[] = [
                            'icon' => '🚀',
                            'text' => str_replace('{factor}', $latencyMetrics['fast_lane_efficiency']['speedup_factor'], $CLICSHOPPING_ChatGpt->getDef('latency_rec_speedup_text')),
                            'type' => 'success'
                          ];
                        }
                        
                        if (empty($recommendations)) {
                          $recommendations[] = [
                            'icon' => '📊',
                            'text' => $CLICSHOPPING_ChatGpt->getDef('latency_rec_normal_text'),
                            'type' => 'info'
                          ];
                        }
                        ?>
                        
                        <ul class="list-unstyled mb-0">
                          <?php foreach ($recommendations as $rec): ?>
                            <li class="mb-2">
                              <div class="alert alert-<?php echo $rec['type']; ?> mb-0 py-2">
                                <?php echo $rec['icon']; ?> <?php echo $rec['text']; ?>
                              </div>
                            </li>
                          <?php endforeach; ?>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>
                
              <?php else: ?>
                <div class="alert alert-info mt-4">
                  <i class="bi bi-info-circle"></i>
                  <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_no_data'); ?></strong><br>
                  <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_auto_collect'); ?>
                  <hr>
                  <small>
                    <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('token_to_activate'); ?>:</strong><br>
                    1. <?php echo $CLICSHOPPING_ChatGpt->getDef('token_make_requests'); ?><br>
                    2. <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_auto_collect'); ?><br>
                    3. <?php echo $CLICSHOPPING_ChatGpt->getDef('token_refresh_page'); ?>
                  </small>
                </div>
              <?php endif; ?>
            </div>
          </div>
          
          <?php
          // -------------------------------------------------------------------
          //          TAB Security
          // -------------------------------------------------------------------
          ?>
          <div class="tab-pane" id="tab7">
            <div style="padding: 20px;">
              <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_title'); ?></h5>

              <?php 
              // Check if we have security monitoring data
              $hasSecurityMonitoring = !empty($advancedStats['security_monitoring']['total_events']);
              $hasLegacySecurity = !empty($advancedStats['security']['total_evaluations']);
              ?>

              <?php if ($hasSecurityMonitoring): ?>
                <?php $secMonitoring = $advancedStats['security_monitoring']; ?>
                
                <!-- Security Health Score -->
                <div class="row mb-4">
                  <div class="col-md-12">
                    <div class="card">
                      <div class="card-header">
                        <h6><i class="bi bi-shield-check"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('security_health_score'); ?></h6>
                      </div>
                      <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                          <span><?php echo $CLICSHOPPING_ChatGpt->getDef('overall_health'); ?></span>
                          <span class="badge <?php 
                            echo match($secMonitoring['health_status']) {
                              'excellent' => 'bg-success',
                              'good' => 'bg-info',
                              'fair' => 'bg-warning',
                              default => 'bg-danger'
                            };
                          ?> fs-5"><?php echo round($secMonitoring['health_score'], 1); ?>/100</span>
                        </div>
                        <div class="progress" style="height: 25px;">
                          <div class="progress-bar <?php 
                            echo match($secMonitoring['health_status']) {
                              'excellent' => 'bg-success',
                              'good' => 'bg-info',
                              'fair' => 'bg-warning',
                              default => 'bg-danger'
                            };
                          ?>" 
                          role="progressbar" 
                          style="width: <?php echo $secMonitoring['health_score']; ?>%;" 
                          aria-valuenow="<?php echo $secMonitoring['health_score']; ?>" 
                          aria-valuemin="0" 
                          aria-valuemax="100">
                            <?php echo round($secMonitoring['health_score'], 1); ?>%
                          </div>
                        </div>
                        <small class="text-muted"><?php echo ucfirst($secMonitoring['health_status']); ?> - <?php echo $CLICSHOPPING_ChatGpt->getDef('last'); ?> <?php echo $secMonitoring['period_days']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('time_days'); ?></small>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Threat Metrics -->
                <div class="row mb-4">
                  <div class="col-md-3">
                    <div class="card text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo $secMonitoring['total_events']; ?></h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('total_security_events'); ?></p>
                        <small><?php echo $secMonitoring['period_days']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('time_days'); ?></small>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card text-center" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo $secMonitoring['critical_count']; ?></h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('critical_threats'); ?></p>
                        <small><?php echo $CLICSHOPPING_ChatGpt->getDef('high'); ?>: <?php echo $secMonitoring['high_count']; ?></small>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card text-center" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo $secMonitoring['blocked_count']; ?></h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('blocked_queries'); ?></p>
                        <small><?php echo round($secMonitoring['block_rate'], 1); ?>% <?php echo $CLICSHOPPING_ChatGpt->getDef('block_rate'); ?></small>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="card text-center" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo $secMonitoring['detected_threats']; ?></h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('threats_detected'); ?></p>
                        <small><?php echo round($secMonitoring['detection_rate'], 1); ?>% <?php echo $CLICSHOPPING_ChatGpt->getDef('detection_rate'); ?></small>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Detection Accuracy -->
                <div class="row mb-4">
                  <div class="col-md-6">
                    <div class="card">
                      <div class="card-header">
                        <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('detection_accuracy'); ?></h6>
                      </div>
                      <div class="card-body">
                        <div class="mb-3">
                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo $CLICSHOPPING_ChatGpt->getDef('detection_rate'); ?></span>
                            <span class="badge bg-success"><?php echo round($secMonitoring['detection_rate'], 1); ?>%</span>
                          </div>
                          <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" style="width: <?php echo $secMonitoring['detection_rate']; ?>%"></div>
                          </div>
                        </div>
                        <div class="mb-3">
                          <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><?php echo $CLICSHOPPING_ChatGpt->getDef('block_rate'); ?></span>
                            <span class="badge bg-info"><?php echo round($secMonitoring['block_rate'], 1); ?>%</span>
                          </div>
                          <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-info" style="width: <?php echo $secMonitoring['block_rate']; ?>%"></div>
                          </div>
                        </div>
                        <small class="text-muted">
                          <?php echo $secMonitoring['detected_threats']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('threats_detected_period'); ?>
                        </small>
                      </div>
                    </div>
                  </div>

                  <!-- Attack Type Distribution -->
                  <div class="col-md-6">
                    <div class="card">
                      <div class="card-header">
                        <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('attack_type_distribution'); ?></h6>
                      </div>
                      <div class="card-body">
                        <?php if (!empty($secMonitoring['threat_types'])): ?>
                          <?php foreach ($secMonitoring['threat_types'] as $threat): ?>
                            <div class="mb-2">
                              <div class="d-flex justify-content-between align-items-center">
                                <span><?php echo ucfirst(str_replace('_', ' ', $threat['type'])); ?></span>
                                <span class="badge bg-secondary"><?php echo $threat['count']; ?></span>
                              </div>
                              <small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('avg_score'); ?>: <?php echo round($threat['avg_score'], 2); ?></small>
                            </div>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <p class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('no_threats_detected'); ?></p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Language Statistics -->
                <?php if (!empty($secMonitoring['languages'])): ?>
                <div class="row mb-4">
                  <div class="col-md-12">
                    <div class="card">
                      <div class="card-header">
                        <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('language_statistics'); ?></h6>
                      </div>
                      <div class="card-body">
                        <div class="row">
                          <?php foreach ($secMonitoring['languages'] as $lang): ?>
                            <div class="col-md-3 mb-2">
                              <div class="border rounded p-2 text-center">
                                <div class="fs-5 fw-bold"><?php echo strtoupper($lang['language']); ?></div>
                                <small class="text-muted"><?php echo $lang['count']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('events'); ?></small>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endif; ?>

              <?php elseif ($hasLegacySecurity): ?>
                <!-- Legacy Security Metrics (LLM Guardrails) -->
                <div class="row mb-4">
                  <div class="col-md-4">
                    <div class="card text-center"
                         style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo $advancedStats['security']['avg_security_score']; ?></h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_security_score'); ?></p>
                        <small><?php echo ucfirst($advancedStats['security']['security_status']); ?></small>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="card text-center"
                         style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo $advancedStats['security']['total_evaluations']; ?></h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('security_evaluations'); ?></p>
                        <small><?php echo $advancedStats['security']['period_days']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('time_days'); ?></small>
                      </div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="card text-center"
                         style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                      <div class="card-body">
                        <h3><?php echo $advancedStats['security']['low_security_count']; ?></h3>
                        <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('security_alerts_title'); ?></p>
                        <small><?php echo $CLICSHOPPING_ChatGpt->getDef('security_low_scores_label'); ?></small>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Graphique de Sécurité -->
                <div class="row">
                  <div class="col-md-4">
                    <div class="card">
                      <div class="card-header">
                        <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('security_scores_chart'); ?></h6>
                      </div>
                      <div class="card-body" style="height: 400px; text-align: center;">
                        <canvas id="securityChart"></canvas>
                      </div>
                    </div>
                  </div>
                </div>

              <?php else: ?>
                <div class="alert alert-info">
                  <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('security_no_data'); ?></h6>
                  <p><?php echo $CLICSHOPPING_ChatGpt->getDef('security_data_info'); ?></p>
                </div>
              <?php endif; ?>
            </div>
          </div>












          <?php
          // -------------------------------------------------------------------
          //          TAB Performance & Cache
          // -------------------------------------------------------------------
          ?>
          <div class="tab-pane" id="tab10">
            <div style="padding: 20px;">
              <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('perf_cache_title'); ?></h5>

              <!-- Cache Statistics Cards -->
              <div class="row mb-4">
                <div class="col-md-3">
                  <div class="card text-center"
                       style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="card-body">
                      <h3 id="cache-hit-rate">--%</h3>
                      <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_hit_rate'); ?></p>
                      <small><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_requests_from_cache'); ?></small>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card text-center"
                       style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <div class="card-body">
                      <h3 id="cache-entries">--</h3>
                      <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_cache_entries'); ?></p>
                      <small><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_cached_queries'); ?></small>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card text-center"
                       style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <div class="card-body">
                      <h3 id="cache-time-saved">-- ms</h3>
                      <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_time_saved'); ?></p>
                      <small><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_total_time_saved'); ?></small>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card text-center"
                       style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                    <div class="card-body">
                      <h3 id="cache-avg-saved">-- ms</h3>
                      <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_avg_savings'); ?></p>
                      <small><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_per_cached_request'); ?></small>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Detailed Statistics -->
              <div class="row">
                <div class="col-md-6">
                  <div class="card">
                    <div class="card-header">
                      <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_detailed_stats'); ?></h6>
                    </div>
                    <div class="card-body">
                      <table class="table table-sm">
                        <tr>
                          <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_total_hits'); ?>:</strong></td>
                          <td class="text-end" id="cache-total-hits">--</td>
                        </tr>
                        <tr>
                          <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_total_misses'); ?>:</strong></td>
                          <td class="text-end" id="cache-total-misses">--</td>
                        </tr>
                        <tr>
                          <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_avg_result_size'); ?>:</strong></td>
                          <td class="text-end" id="cache-avg-size">-- rows</td>
                        </tr>
                        <tr>
                          <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_last_update'); ?>:</strong></td>
                          <td class="text-end" id="cache-last-update">--</td>
                        </tr>
                      </table>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                      <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_actions'); ?></h6>
                      <button class="btn btn-danger btn-sm" onclick="flushQueryCache()">
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_flush_cache'); ?>
                      </button>
                    </div>
                    <div class="card-body">
                      <div class="alert alert-info">
                        <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_about_title'); ?></h6>
                        <p class="mb-2"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_about_description'); ?></p>
                        <ul class="mb-0">
                          <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_hit_label'); ?>:</strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_hit_description'); ?></li>
                          <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_miss_label'); ?>:</strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_miss_description'); ?></li>
                          <li><strong>TTL:</strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_ttl_default'); ?></li>
                        </ul>
                      </div>
                      <div id="cache-flush-result" class="alert" style="display: none;"></div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Performance Impact -->
              <div class="row mt-4">
                <div class="col-md-12">
                  <div class="card">
                    <div class="card-header">
                      <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_performance_impact'); ?></h6>
                    </div>
                    <div class="card-body">
                      <div class="row">
                        <div class="col-md-4 text-center">
                          <h3 id="cache-speedup" class="text-success">--x</h3>
                          <p><?php echo $CLICSHOPPING_ChatGpt->getDef('latency_speedup'); ?></p>
                        </div>
                        <div class="col-md-4 text-center">
                          <h3 id="cache-improvement" class="text-info">--%</h3>
                          <p><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_improvement'); ?></p>
                        </div>
                        <div class="col-md-4 text-center">
                          <h3 id="cache-tokens-saved" class="text-warning">--</h3>
                          <p><?php echo $CLICSHOPPING_ChatGpt->getDef('token_tokens'); ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('latency_time_saved'); ?></p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- File Caches Statistics -->
              <div class="row mt-4">
                <div class="col-md-12">
                  <div class="card">
                    <div class="card-header">
                      <h6>📁 <?php echo $CLICSHOPPING_ChatGpt->getDef('file_caches_statistics'); ?></h6>
                    </div>
                    <div class="card-body">
                      <div class="row">
                        <?php
                        // Get Translation Cache Statistics
                        try {
                          $translationCache = new TranslationCache();
                          $translationStats = $translationCache->getStatistics();
                        } catch (Exception $e) {
                          $translationStats = ['enabled' => false, 'file_count' => 0, 'total_size_mb' => 0];
                        }
                        
                        // Get Classification Cache Statistics
                        try {
                          $classificationCache = new ClassificationCache();
                          $classificationStats = $classificationCache->getStatistics();
                        } catch (Exception $e) {
                          $classificationStats = ['enabled' => false, 'file_count' => 0, 'total_size_mb' => 0];
                        }

                          try {
                            $ragCache = new RagCache();
                            $ragStats = $ragCache->getStats();
                          } catch (Exception $e) {
                            $ragStats = ['enabled' => false, 'file_count' => 0, 'total_size_mb' => 0];
                          }
                        ?>
                        
                        <!-- Translation Cache -->
                        <div class="col-md-6 mb-3">
                          <div class="card" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);">
                            <div class="card-body">
                              <h6 class="card-title">🌐 <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_translations'); ?></h6>
                              <table class="table table-sm table-borderless mb-0">
                                <tr>
                                  <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_status'); ?>:</strong></td>
                                  <td class="text-end">
                                    <?php if ($translationStats['enabled']): ?>
                                      <span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_enabled'); ?></span>
                                    <?php else: ?>
                                      <span class="badge bg-secondary"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_disabled'); ?></span>
                                    <?php endif; ?>
                                  </td>
                                </tr>
                                <tr>
                                  <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_files'); ?>:</strong></td>
                                  <td class="text-end"><?php echo number_format($translationStats['file_count']); ?></td>
                                </tr>
                                <tr>
                                  <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_size'); ?>:</strong></td>
                                  <td class="text-end"><?php echo $translationStats['total_size_mb']; ?> MB</td>
                                </tr>
                                <tr>
                                  <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_directory'); ?>:</strong></td>
                                  <td class="text-end"><small><?php echo basename($translationStats['directory']); ?></small></td>
                                </tr>
                              </table>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Classification Cache -->
                        <div class="col-md-6 mb-3">
                          <div class="card" style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);">
                            <div class="card-body">
                              <h6 class="card-title">🎯 <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_classification'); ?></h6>
                              <table class="table table-sm table-borderless mb-0">
                                <tr>
                                  <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_status'); ?>:</strong></td>
                                  <td class="text-end">
                                    <?php if ($classificationStats['enabled']): ?>
                                      <span class="badge bg-success"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_enabled'); ?></span>
                                    <?php else: ?>
                                      <span class="badge bg-secondary"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_disabled'); ?></span>
                                    <?php endif; ?>
                                  </td>
                                </tr>
                                <tr>
                                  <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_files'); ?>:</strong></td>
                                  <td class="text-end"><?php echo number_format($classificationStats['file_count']); ?></td>
                                </tr>
                                <tr>
                                  <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_size'); ?>:</strong></td>
                                  <td class="text-end"><?php echo $classificationStats['total_size_mb']; ?> MB</td>
                                </tr>
                                <tr>
                                  <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_directory'); ?>:</strong></td>
                                  <td class="text-end"><small><?php echo basename($classificationStats['directory']); ?></small></td>
                                </tr>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- WebSearch Cache Statistics -->
              <?php if (!empty($websearchStats) && $websearchStats['total_queries'] > 0): ?>
              <div class="row mt-4">
                <div class="col-md-12">
                  <div class="card">
                    <div class="card-header">
                      <h6>🌐 <?php echo $CLICSHOPPING_ChatGpt->getDef('websearch_cache_statistics') ?? 'Web Search Cache Statistics'; ?></h6>
                    </div>
                    <div class="card-body">
                      <div class="row">
                        <div class="col-md-3">
                          <div class="card text-center" style="background: linear-gradient(135deg, #e1f5fe 0%, #b3e5fc 100%);">
                            <div class="card-body">
                              <h3><?php echo $websearchStats['cache_hit_rate']; ?>%</h3>
                              <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_hit_rate') ?? 'Cache Hit Rate'; ?></p>
                              <small><?php echo $websearchStats['cache_hits']; ?> / <?php echo ($websearchStats['cache_hits'] + $websearchStats['cache_misses']); ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_requests') ?? 'requests'; ?></small>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="card text-center" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);">
                            <div class="card-body">
                              <h3><?php echo $websearchStats['total_queries']; ?></h3>
                              <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('websearch_total_queries') ?? 'Total Queries'; ?></p>
                              <small><?php echo $websearchStats['period_days']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('time_days') ?? 'days'; ?></small>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="card text-center" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);">
                            <div class="card-body">
                              <h3><?php echo $websearchStats['success_rate']; ?>%</h3>
                              <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('component_success_rate') ?? 'Success Rate'; ?></p>
                              <small><?php echo $websearchStats['successful_queries']; ?> / <?php echo $websearchStats['total_queries']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_requests') ?? 'requests'; ?></small>
                            </div>
                          </div>
                        </div>
                        <div class="col-md-3">
                          <div class="card text-center" style="background: linear-gradient(135deg, #fff3e0 0%, #ffcc80 100%);">
                            <div class="card-body">
                              <h3><?php echo round($websearchStats['avg_response_time'] / 1000, 2); ?>s</h3>
                              <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('component_avg_time') ?? 'Avg Response Time'; ?></p>
                              <small><?php echo $CLICSHOPPING_ChatGpt->getDef('websearch_per_query') ?? 'per query'; ?></small>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php endif; ?>
              
              <!-- ============================================================================ -->
              <!-- PERFORMANCE CHARTS SECTION (Task 7.3) -->
              <!-- ============================================================================ -->
              <div class="row mt-4">
                <div class="col-md-12">
                  <div class="card">
                    <div class="card-header">
                      <h6>📊 <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_performance_charts') ?? 'Cache Performance Charts'; ?></h6>
                    </div>
                    <div class="card-body">
                      <!-- Chart 1: Hit/Miss Rate Over Time -->
                      <div class="row mb-4">
                        <div class="col-md-12">
                          <h6 class="mb-3"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_hit_miss_chart_title') ?? 'Cache Hit/Miss Rate Over Time'; ?></h6>
                          <canvas id="cacheHitMissChart" style="max-height: 300px;"></canvas>
                        </div>
                      </div>
                      
                      <!-- Chart 2: API Cost Savings -->
                      <div class="row mb-4">
                        <div class="col-md-12">
                          <h6 class="mb-3"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_cost_savings_chart_title') ?? 'API Cost Savings Over Time'; ?></h6>
                          <canvas id="cacheCostSavingsChart" style="max-height: 300px;"></canvas>
                        </div>
                      </div>
                      
                      <!-- Chart 3: Response Time Comparison -->
                      <div class="row mb-4">
                        <div class="col-md-12">
                          <h6 class="mb-3"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_response_time_chart_title') ?? 'Average Response Time Comparison'; ?></h6>
                          <canvas id="cacheResponseTimeChart" style="max-height: 300px;"></canvas>
                        </div>
                      </div>
                      
                      <!-- Chart 4: Cache Size by Type -->
                      <div class="row mb-4">
                        <div class="col-md-6">
                          <h6 class="mb-3"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_size_chart_title') ?? 'Cache Size by Type'; ?></h6>
                          <canvas id="cacheSizeChart" style="max-height: 300px;"></canvas>
                        </div>
                        <div class="col-md-6">
                          <div class="alert alert-info mt-4">
                            <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_charts_info_title') ?? 'About These Charts'; ?></h6>
                            <ul class="mb-0">
                              <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_hit_miss_label') ?? 'Hit/Miss Rate'; ?>:</strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_hit_miss_desc') ?? 'Shows cache effectiveness over time'; ?></li>
                              <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_cost_savings_label') ?? 'Cost Savings'; ?>:</strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_cost_savings_desc') ?? 'API costs saved vs spent'; ?></li>
                              <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_response_time_label') ?? 'Response Time'; ?>:</strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_response_time_desc') ?? 'Cached vs uncached performance'; ?></li>
                              <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_size_label') ?? 'Cache Size'; ?>:</strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_size_desc') ?? 'Storage usage by cache type'; ?></li>
                            </ul>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div style="padding: 20px;">
              <div class="row">
                <div class="col-md-6">
                  <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_system_metrics'); ?></h5>
                  <table class="table table-sm table-borderless">
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_total_api_calls'); ?>:</strong></td>
                      <td class="text-end"><?php echo  $healthReport['system_metrics']['total_api_calls'] ?? 0; ?></td>
                    </tr>
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_total_api_cost'); ?>:</strong></td>
                      <td class="text-end">$<?php echo  round($healthReport['system_metrics']['total_api_cost'], 2); ?></td>
                    </tr>
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_uptime'); ?>:</strong></td>
                      <td class="text-end"><?php echo  formatUptime($healthReport['system_metrics']['uptime_seconds'] ?? 0); ?>
                      </td>
                    </tr>
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_php_version'); ?>:</strong></td>
                      <td class="text-end"><?php echo  phpversion(); ?></td>
                    </tr>
                  </table>
                </div>
                <div class="col-md-6">
                  <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_system_stats'); ?></h5>
                  <table class="table table-sm table-borderless">
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_memory_limit'); ?>:</strong></td>
                      <td class="text-end">
                        <?php echo  round($healthReport['system_metrics']['memory_usage']['limit'] / 1024 / 1024); ?> MB
                      </td>
                    </tr>
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_peak_memory'); ?>:</strong></td>
                      <td class="text-end">
                        <?php echo  round($healthReport['system_metrics']['memory_usage']['peak'] / 1024 / 1024); ?> MB
                      </td>
                    </tr>
                  </table>
                </div>
              </div>

              <?php
              if (!empty($healthReport['recommendations'])) {
                ?>
                <div class="recommendations mt-4">
                  <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('tab7_recommendations'); ?></h6>
                  <?php
                  foreach ($healthReport['recommendations'] as $rec){
                    ?>
                    <div class="recommendation-item">
                      <strong>[<?php echo  strtoupper($rec['priority']); ?>]</strong>
                      <?php echo  htmlspecialchars($rec['message']); ?>
                    </div>
                    <?php
                  }
                  ?>
                </div>
                <?php
              }
              ?>

              <!-- Cold Cache & Timeout Metrics Section -->
              <?php if (!empty($data['cold_cache_metrics'])): 
                $coldCacheMetrics = $data['cold_cache_metrics'];
              ?>
              <div class="mt-4">
                <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_metrics_title'); ?></h5>

                <!-- Cache State Distribution -->
                <div class="row mb-4">
                  <div class="col-md-12">
                    <div class="card">
                      <div class="card-header">
                        <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_state_title'); ?></h6>
                      </div>
                      <div class="card-body">
                        <div class="row">
                          <div class="col-md-4">
                            <div class="card text-center" style="background: linear-gradient(135deg, #e3f2fd 0%, #90caf9 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['cache_state_distribution']['cold']; ?></h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_cold_count'); ?></p>
                                <small><?php echo $coldCacheMetrics['cache_state_distribution']['cold_percentage']; ?>% <?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_cold_percentage'); ?></small>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-4">
                            <div class="card text-center" style="background: linear-gradient(135deg, #c8e6c9 0%, #66bb6a 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['cache_state_distribution']['warm']; ?></h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_warm_count'); ?></p>
                                <small><?php echo $coldCacheMetrics['cache_state_distribution']['warm_percentage']; ?>% <?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_warm_percentage'); ?></small>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-4">
                            <div class="card text-center" style="background: linear-gradient(135deg, #fff3e0 0%, #ffb74d 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['cache_state_distribution']['expired']; ?></h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_expired_count'); ?></p>
                                <small><?php echo $coldCacheMetrics['cache_state_distribution']['expired_percentage']; ?>%</small>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Cold vs Warm Performance -->
                <div class="row mb-4">
                  <div class="col-md-12">
                    <div class="card">
                      <div class="card-header">
                        <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_performance_title'); ?></h6>
                      </div>
                      <div class="card-body">
                        <div class="row">
                          <div class="col-md-3">
                            <div class="card text-center" style="background: linear-gradient(135deg, #ffebee 0%, #ef5350 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['cold_vs_warm_performance']['cold_avg_time']; ?>s</h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_cold_avg'); ?></p>
                                <small><?php echo $coldCacheMetrics['cold_vs_warm_performance']['cold_count']; ?> queries</small>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-3">
                            <div class="card text-center" style="background: linear-gradient(135deg, #e8f5e9 0%, #66bb6a 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['cold_vs_warm_performance']['warm_avg_time']; ?>s</h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_warm_avg'); ?></p>
                                <small><?php echo $coldCacheMetrics['cold_vs_warm_performance']['warm_count']; ?> queries</small>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-3">
                            <div class="card text-center" style="background: linear-gradient(135deg, #e1f5fe 0%, #29b6f6 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['cold_vs_warm_performance']['speedup_factor']; ?>x</h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_speedup'); ?></p>
                                <small><?php echo isset($coldCacheMetrics['cold_vs_warm_performance']['percentage_faster']) ? $coldCacheMetrics['cold_vs_warm_performance']['percentage_faster'] . '% faster' : ''; ?></small>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-3">
                            <div class="card text-center" style="background: linear-gradient(135deg, #f3e5f5 0%, #ab47bc 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['cold_vs_warm_performance']['time_saved_per_query']; ?>s</h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_time_saved'); ?></p>
                                <small>per query</small>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Parallel Execution Performance -->
                <?php if ($coldCacheMetrics['parallel_execution']['total_parallel_queries'] > 0): ?>
                <div class="row mb-4">
                  <div class="col-md-12">
                    <div class="card">
                      <div class="card-header">
                        <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_title'); ?></h6>
                      </div>
                      <div class="card-body">
                        <div class="row mb-3">
                          <div class="col-md-4">
                            <div class="card text-center" style="background: linear-gradient(135deg, #fff9c4 0%, #fdd835 100%);">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['parallel_execution']['total_parallel_queries']; ?></h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_total_queries'); ?></p>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-4">
                            <div class="card text-center" style="background: linear-gradient(135deg, #c5e1a5 0%, #7cb342 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['parallel_execution']['total_time_saved']; ?>s</h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_time_saved'); ?></p>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-4">
                            <div class="card text-center" style="background: linear-gradient(135deg, #b2dfdb 0%, #26a69a 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['parallel_execution']['avg_speedup_factor']; ?>x</h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_avg_speedup'); ?></p>
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- Analytics and Hybrid Breakdown -->
                        <div class="row">
                          <div class="col-md-6">
                            <div class="card">
                              <div class="card-header">
                                <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_analytics_title'); ?></h6>
                              </div>
                              <div class="card-body">
                                <table class="table table-sm">
                                  <tr>
                                    <td><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_analytics_count'); ?>:</td>
                                    <td class="text-end"><strong><?php echo $coldCacheMetrics['parallel_execution']['analytics']['count']; ?></strong></td>
                                  </tr>
                                  <tr>
                                    <td><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_analytics_speedup'); ?>:</td>
                                    <td class="text-end"><strong><?php echo $coldCacheMetrics['parallel_execution']['analytics']['avg_speedup']; ?>x</strong></td>
                                  </tr>
                                  <tr>
                                    <td><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_analytics_time_saved'); ?>:</td>
                                    <td class="text-end"><strong><?php echo $coldCacheMetrics['parallel_execution']['analytics']['time_saved']; ?>s</strong></td>
                                  </tr>
                                  <tr>
                                    <td><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_percentage_faster'); ?>:</td>
                                    <td class="text-end"><strong><?php echo $coldCacheMetrics['parallel_execution']['analytics']['percentage_faster']; ?>%</strong></td>
                                  </tr>
                                </table>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="card">
                              <div class="card-header">
                                <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_hybrid_title'); ?></h6>
                              </div>
                              <div class="card-body">
                                <table class="table table-sm">
                                  <tr>
                                    <td><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_hybrid_count'); ?>:</td>
                                    <td class="text-end"><strong><?php echo $coldCacheMetrics['parallel_execution']['hybrid']['count']; ?></strong></td>
                                  </tr>
                                  <tr>
                                    <td><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_hybrid_speedup'); ?>:</td>
                                    <td class="text-end"><strong><?php echo $coldCacheMetrics['parallel_execution']['hybrid']['avg_speedup']; ?>x</strong></td>
                                  </tr>
                                  <tr>
                                    <td><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_hybrid_time_saved'); ?>:</td>
                                    <td class="text-end"><strong><?php echo $coldCacheMetrics['parallel_execution']['hybrid']['time_saved']; ?>s</strong></td>
                                  </tr>
                                  <tr>
                                    <td><?php echo $CLICSHOPPING_ChatGpt->getDef('parallel_execution_percentage_faster'); ?>:</td>
                                    <td class="text-end"><strong><?php echo $coldCacheMetrics['parallel_execution']['hybrid']['percentage_faster']; ?>%</strong></td>
                                  </tr>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endif; ?>

                <!-- Hybrid Query Metrics -->
                <?php if ($coldCacheMetrics['hybrid_query_metrics']['total_count'] > 0): ?>
                <div class="row mb-4">
                  <div class="col-md-12">
                    <div class="card">
                      <div class="card-header">
                        <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('hybrid_query_metrics_title'); ?></h6>
                      </div>
                      <div class="card-body">
                        <div class="row mb-3">
                          <div class="col-md-3">
                            <div class="card text-center" style="background: linear-gradient(135deg, #e1bee7 0%, #ba68c8 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['hybrid_query_metrics']['total_count']; ?></h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('hybrid_query_total_count'); ?></p>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-3">
                            <div class="card text-center" style="background: linear-gradient(135deg, #b2dfdb 0%, #4db6ac 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['hybrid_query_metrics']['avg_subqueries']; ?></h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('hybrid_query_avg_subqueries'); ?></p>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-3">
                            <div class="card text-center" style="background: linear-gradient(135deg, #ffccbc 0%, #ff8a65 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['hybrid_query_metrics']['avg_execution_time']; ?>s</h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('hybrid_query_avg_execution_time'); ?></p>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-3">
                            <div class="card text-center" style="background: linear-gradient(135deg, #c5e1a5 0%, #9ccc65 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['hybrid_query_metrics']['success_rate']; ?>%</h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('hybrid_query_success_rate'); ?></p>
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- Time Distribution -->
                        <div class="row">
                          <div class="col-md-12">
                            <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('hybrid_query_time_distribution'); ?></h6>
                            <div class="row">
                              <div class="col-md-3">
                                <div class="card text-center">
                                  <div class="card-body">
                                    <h4><?php echo $coldCacheMetrics['hybrid_query_metrics']['time_distribution']['under_5s']; ?></h4>
                                    <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('hybrid_query_under_5s'); ?></p>
                                  </div>
                                </div>
                              </div>
                              <div class="col-md-3">
                                <div class="card text-center">
                                  <div class="card-body">
                                    <h4><?php echo $coldCacheMetrics['hybrid_query_metrics']['time_distribution']['between_5_15s']; ?></h4>
                                    <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('hybrid_query_5_to_15s'); ?></p>
                                  </div>
                                </div>
                              </div>
                              <div class="col-md-3">
                                <div class="card text-center">
                                  <div class="card-body">
                                    <h4><?php echo $coldCacheMetrics['hybrid_query_metrics']['time_distribution']['between_15_30s']; ?></h4>
                                    <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('hybrid_query_15_to_30s'); ?></p>
                                  </div>
                                </div>
                              </div>
                              <div class="col-md-3">
                                <div class="card text-center">
                                  <div class="card-body">
                                    <h4><?php echo $coldCacheMetrics['hybrid_query_metrics']['time_distribution']['over_30s']; ?></h4>
                                    <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('hybrid_query_over_30s'); ?></p>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endif; ?>

                <!-- Timeout Events -->
                <?php if ($coldCacheMetrics['timeout_events']['total_timeouts'] > 0): ?>
                <div class="row mb-4">
                  <div class="col-md-12">
                    <div class="card">
                      <div class="card-header">
                        <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_timeout_events_title'); ?></h6>
                      </div>
                      <div class="card-body">
                        <div class="row">
                          <div class="col-md-3">
                            <div class="card text-center" style="background: linear-gradient(135deg, #ffcdd2 0%, #e57373 100%); color: white;">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['timeout_events']['total_timeouts']; ?></h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_total_timeouts'); ?></p>
                                <small><?php echo $coldCacheMetrics['timeout_events']['timeout_rate']; ?>% rate</small>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-3">
                            <div class="card text-center" style="background: linear-gradient(135deg, #e1f5fe 0%, #81d4fa 100%);">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['timeout_events']['cold_timeouts']; ?></h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_cold_timeouts'); ?></p>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-3">
                            <div class="card text-center" style="background: linear-gradient(135deg, #c8e6c9 0%, #81c784 100%);">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['timeout_events']['warm_timeouts']; ?></h3>
                                <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('cold_cache_warm_timeouts'); ?></p>
                              </div>
                            </div>
                          </div>
                          <div class="col-md-3">
                            <div class="card text-center" style="background: linear-gradient(135deg, #fff9c4 0%, #fff176 100%);">
                              <div class="card-body">
                                <h3><?php echo $coldCacheMetrics['timeout_events']['expired_timeouts']; ?></h3>
                                <p class="mb-0">Expired Cache Timeouts</p>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <?php endif; ?>

                <!-- No Data Message -->
                <?php if ($coldCacheMetrics['cache_state_distribution']['total'] == 0): ?>
                <div class="alert alert-info">
                  <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('no_cold_cache_data'); ?></h6>
                  <p><?php echo $CLICSHOPPING_ChatGpt->getDef('data_will_be_collected'); ?></p>
                </div>
                <?php endif; ?>
              </div>
              <?php endif; ?>

              <!-- Hybrid Query Decomposition Statistics -->
              <?php if (!empty($decompositionStats) && $decompositionStats['total_decompositions'] > 0): ?>
              <div class="row mt-5">
                <div class="col-md-12">
                  <h5 class="mb-3">🔀 <?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_stats_title'); ?></h5>
                  
                  <!-- Decomposition Overview Cards -->
                  <div class="row mb-4">
                    <div class="col-md-3">
                      <div class="card text-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <div class="card-body">
                          <h3><?php echo $decompositionStats['total_decompositions']; ?></h3>
                          <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_total'); ?></p>
                          <small><?php echo $CLICSHOPPING_ChatGpt->getDef('last'); ?> <?php echo $decompositionStats['period_days']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('time_days'); ?></small>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="card text-center" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                        <div class="card-body">
                          <h3><?php echo round($decompositionStats['avg_time_ms'], 0); ?> ms</h3>
                          <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_avg_time'); ?></p>
                          <small><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_range'); ?>: <?php echo $decompositionStats['min_time_ms']; ?>-<?php echo $decompositionStats['max_time_ms']; ?> ms</small>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="card text-center" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                        <div class="card-body">
                          <h3><?php echo round($decompositionStats['cache_hit_rate'], 1); ?>%</h3>
                          <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_cache_hit_rate'); ?></p>
                          <small><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_cached_results'); ?></small>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-3">
                      <div class="card text-center" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                        <div class="card-body">
                          <h3><?php echo round($decompositionStats['error_rate'], 1); ?>%</h3>
                          <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_error_rate'); ?></p>
                          <small><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_success_rate'); ?>: <?php echo round(100 - $decompositionStats['error_rate'], 1); ?>%</small>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Performance Details -->
                  <div class="row">
                    <div class="col-md-6">
                      <div class="card">
                        <div class="card-header">
                          <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_performance_details'); ?></h6>
                        </div>
                        <div class="card-body">
                          <table class="table table-sm">
                            <tr>
                              <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_slow_operations'); ?>:</strong></td>
                              <td class="text-end">
                                <span class="badge <?php echo $decompositionStats['slow_operation_rate'] > 10 ? 'bg-warning' : 'bg-success'; ?>">
                                  <?php echo round($decompositionStats['slow_operation_rate'], 1); ?>%
                                </span>
                              </td>
                            </tr>
                            <tr>
                              <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_threshold'); ?>:</strong></td>
                              <td class="text-end">500 ms</td>
                            </tr>
                            <tr>
                              <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_min_time'); ?>:</strong></td>
                              <td class="text-end"><?php echo $decompositionStats['min_time_ms']; ?> ms</td>
                            </tr>
                            <tr>
                              <td><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_max_time'); ?>:</strong></td>
                              <td class="text-end"><?php echo $decompositionStats['max_time_ms']; ?> ms</td>
                            </tr>
                          </table>
                        </div>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="card">
                        <div class="card-header">
                          <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_about'); ?></h6>
                        </div>
                        <div class="card-body">
                          <div class="alert alert-info mb-0">
                            <p class="mb-2"><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_about_description'); ?></p>
                            <ul class="mb-0">
                              <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_what'); ?>:</strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_what_description'); ?></li>
                              <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_why'); ?>:</strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_why_description'); ?></li>
                              <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_performance'); ?>:</strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('decomposition_performance_description'); ?></li>
                            </ul>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              <?php endif; ?>
            </div>
          </div>


          <?php
          // -------------------------------------------------------------------
          //          TAB Feedback
          // -------------------------------------------------------------------
          ?>
          <div class="tab-pane" id="tab11">
            <div style="padding: 20px;">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('tab11_title'); ?></h5>
                  <div>
                    <button class="btn btn-primary btn-sm" onclick="analyzeFeedbacks('negative')">
                      <?php echo $CLICSHOPPING_ChatGpt->getDef('tab11_analyze_negative'); ?>
                    </button>
                    <button class="btn btn-success btn-sm ms-2" onclick="analyzeFeedbacks('positive')">
                      <?php echo $CLICSHOPPING_ChatGpt->getDef('tab11_analyze_positive'); ?>
                    </button>
                    <button class="btn btn-info btn-sm ms-2" onclick="analyzeFeedbacks('all')">
                      <?php echo $CLICSHOPPING_ChatGpt->getDef('tab11_analyze_complete'); ?>
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <!-- <?php echo $CLICSHOPPING_ChatGpt->getDef('tab11_metrics_summary'); ?> -->
                  <div class="row mb-4">
                    <div class="col-md-6">
                      <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_summary_7days'); ?></h6>
                      <table class="table table-sm">
                        <tr>
                          <td><?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_total_interactions'); ?>:</td>
                          <td><strong><?php echo $feedbackStats['total_interactions'] ?></strong></td>
                        </tr>
                        <tr>
                          <td><?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_total_feedback'); ?>:</td>
                          <td><strong><?php echo $feedbackStats['total_feedback'] ?></strong></td>
                        </tr>
                        <tr>
                          <td><?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_ratio_label'); ?>:</td>
                          <td>
                            <strong><?php echo $feedbackStats['feedback_ratio'] ?>%</strong>
                            <?php if ($feedbackStats['feedback_ratio'] >= 40): ?>
                              <span class="badge bg-success ms-2"><?php echo $CLICSHOPPING_ChatGpt->getDef('security_excellent'); ?></span>
                            <?php elseif ($feedbackStats['feedback_ratio'] >= 20): ?>
                              <span class="badge bg-info ms-2"><?php echo $CLICSHOPPING_ChatGpt->getDef('security_good'); ?></span>
                            <?php else: ?>
                              <span class="badge bg-warning ms-2"><?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_to_improve'); ?></span>
                            <?php endif; ?>
                          </td>
                        </tr>
                        <tr>
                          <td><?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_satisfaction_label'); ?>:</td>
                          <td>
                            <strong><?php echo $feedbackStats['satisfaction_rate'] ?>%</strong>
                            <?php if ($feedbackStats['satisfaction_rate'] >= 85): ?>
                              <span class="badge bg-success ms-2"><?php echo $CLICSHOPPING_ChatGpt->getDef('security_excellent'); ?></span>
                            <?php elseif ($feedbackStats['satisfaction_rate'] >= 70): ?>
                              <span class="badge bg-info ms-2"><?php echo $CLICSHOPPING_ChatGpt->getDef('security_good'); ?></span>
                            <?php else: ?>
                              <span class="badge bg-danger ms-2"><?php echo $CLICSHOPPING_ChatGpt->getDef('security_warning'); ?></span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      </table>
                    </div>
                    <div class="col-md-6">
                      <h6>📈 <?php echo $CLICSHOPPING_ChatGpt->getDef('perf_agent_distribution'); ?></h6>
                      <canvas id="feedbackDistributionChart" style="max-height: 200px;"></canvas>
                    </div>
                  </div>

                  <!-- Objectifs -->
                  <div class="alert alert-info">
                    <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_goals_title'); ?></h6>
                    <ul class="mb-0">
                      <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_min_ratio_goal'); ?>:</strong> 20% <?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_ratio_description'); ?></li>
                      <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_optimal_ratio_goal'); ?>:</strong> 40% <?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_optimal_ratio_description'); ?></li>
                      <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_min_satisfaction_goal'); ?>:</strong> 70%</li>
                      <li><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('feedback_optimal_satisfaction_goal'); ?>:</strong> 85%</li>
                    </ul>
                  </div>

                  <!-- Résultat de l'analyse IA -->
                  <div id="aiAnalysisResult" class="mt-4" style="display: none;">
                    <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('ai_analysis_title'); ?></h6>
                    <div class="alert alert-info">
                      <div id="aiAnalysisLoading" style="display: none;">
                        <div class="spinner-border spinner-border-sm me-2"></div>
                        <?php echo $CLICSHOPPING_ChatGpt->getDef('tab11_analysis_in_progress'); ?>
                      </div>
                      <div id="aiAnalysisContent"></div>
                    </div>
                  </div>

                  <!-- Recent feedbacks list -->
                  <?php if ($feedbackStats['total_feedback'] > 0): ?>
                    <div class="mt-4">
                      <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('tab11_recent_feedbacks'); ?></h6>
                      <div id="feedbackList">
                        <p class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('tab11_loading_feedbacks'); ?></p>
                      </div>
                    </div>
                  <?php else: ?>
                    <div class="alert alert-warning mt-4">
                      <h6><?php echo $CLICSHOPPING_ChatGpt->getDef('tab11_no_feedback'); ?></h6>
                      <p class="mb-0"><?php echo $CLICSHOPPING_ChatGpt->getDef('tab11_feedback_will_appear'); ?>
                      </p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php
          // -------------------------------------------------------------------
          //          TAB Export & API
          // -------------------------------------------------------------------
          ?>
          <div class="tab-pane" id="tab12">
            <?php  $ajax_export_url_base = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/RAG/export.php';  ?>
            <div style="padding: 20px;">
              <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_title'); ?></h5>
              <p><?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_download_metrics'); ?></p>

              <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
                <a href="<?php echo $ajax_export_url_base; ?>?export=csv" class="export-button csv" download="rag_statistics_<?php echo date('Y-m-d'); ?>.csv">📊 <?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_csv_export') ?? 'Exporter en CSV'; ?></a>
                <a href="<?php echo $ajax_export_url_base; ?>?export=health" class="export-button json" target="_blank" rel="noopener noreferrer">📋 <?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_json_health') ?? 'Rapport Santé JSON'; ?></a>
                <a href="<?php echo $ajax_export_url_base; ?>?export=metrics" class="export-button json" target="_blank" rel="noopener noreferrer">📊 <?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_json_metrics') ?? 'Métriques JSON'; ?></a>
                <a href="<?php echo $ajax_export_url_base; ?>?export=alerts" class="export-button json" target="_blank" rel="noopener noreferrer">🚨 <?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_json_alerts') ?? 'Alertes JSON'; ?></a>
                <a href="<?php echo $ajax_export_url_base; ?>?export=stats" class="export-button json" target="_blank" rel="noopener noreferrer">📈 <?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_json_stats') ?? 'Statistiques JSON'; ?></a>
                <a href="<?php echo $ajax_export_url_base; ?>?export=prometheus" class="export-button prometheus" target="_blank" rel="noopener noreferrer">🔧 <?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_prometheus_format') ?? 'Prometheus Format'; ?></a>
                <a href="<?php echo $ajax_export_url_base; ?>?export=html_dashboard" class="export-button html" target="_blank" rel="noopener noreferrer">🌐 <?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_html_dashboard') ?? 'Dashboard HTML'; ?></a>
                <a href="<?php echo $ajax_export_url_base; ?>?export=documentation" class="export-button html" target="_blank" rel="noopener noreferrer">📖 <?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_markdown_api') ?? 'API Ref Markdown'; ?></a>
              </div>

              <h5 class="mt-5"><?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_api_endpoints'); ?></h5>
              <table class="table table-sm table-striped">
                <thead>
                <tr>
                  <th><?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_endpoint'); ?></th>
                  <th><?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_description'); ?></th>
                </tr>
                </thead>
                <tbody>
                <tr>
                  <td><code>GET /dashboard.php?export=health</code></td>
                  <td><?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_health_report'); ?></td>
                </tr>
                <tr>
                  <td><code>GET /dashboard.php?export=metrics</code></td>
                  <td><?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_all_metrics_json'); ?></td>
                </tr>
                <tr>
                  <td><code>GET /dashboard.php?export=prometheus</code></td>
                  <td><?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_prometheus_metrics'); ?></td>
                </tr>
                <tr>
                  <td><code>GET /dashboard.php?export=documentation</code></td>
                  <td><?php echo $CLICSHOPPING_ChatGpt->getDef('tab12_api_documentation'); ?></td>
                </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; // End of $config['rag_enabled'] check for all RAG tabs ?>









<?php if ($config['chatgpt_enabled']): ?>
<!-- Modal de confirmation pour réinitialiser les stats -->
<div class="modal fade" id="resetStatsModal" tabindex="-1" aria-labelledby="resetStatsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="resetStatsModalLabel"><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_title'); ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_warning'); ?></strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_description'); ?></p>
        <ul>
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_item1'); ?></li>
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_item2'); ?></li>
          <li><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_item3'); ?></li>
        </ul>
        <p class="text-danger"><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_irreversible'); ?></strong></p>
        <p><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_confirm'); ?></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_cancel'); ?></button>
        <form method="post" action="<?php echo $CLICSHOPPING_ChatGpt->link('ChatGpt&ResetAllRagStats'); ?>" style="display: inline;">
          <input type="hidden" name="confirm_reset" value="yes">
          <button type="submit" class="btn btn-danger"><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_stats_yes'); ?></button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<div class="py-4"></div>

  <?php if ($config['rag_enabled']): ?>
  <?php
  // Generate AJAX URLs for JavaScript
  $ajax_analyze_feedbacks_url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/RAG/analyze_feedbacks.php';
  $ajax_get_feedbacks_url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/RAG/get_recent_feedbacks.php';
  $ajax_manage_cache_url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/RAG/manage_cache.php';
  $get_cache_stats_url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin'). 'ajax/RAG/get_cache_stats.php';
  $get_cache_performance_url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin'). 'ajax/RAG/get_cache_performance.php';
  ?>
<script>
  // Single injection of PHP data into APP_DATA
  window.APP_DATA = <?php
  // Unified APP_DATA structure with all necessary data
  $appData = [
    'ajax' => [
      'analyze' => $ajax_analyze_feedbacks_url,
      'get' => $ajax_get_feedbacks_url,
      'getFeedbacksUrl' => $ajax_get_feedbacks_url,
      'cache' => $ajax_manage_cache_url,
      'cacheStatsUrl' => $get_cache_stats_url,
      'cachePerformanceUrl' => $get_cache_performance_url,
      'analyzeFeedbacksUrl' => $ajax_analyze_feedbacks_url
    ],
    'systemReport' => $systemReport,
    'globalStats' => $globalStats,
    'tokenDashboardStats' => $tokenDashboardStats,
    'feedbackStats' => $feedbackStats
  ];
  
  // Add latency metrics if available
  if (isset($latencyMetrics) && $latencyMetrics !== null) {
    $appData['latencyMetrics'] = $latencyMetrics;
  }
  
  echo json_encode($appData, JSON_UNESCAPED_SLASHES);
  ?>;

  // Extraction after injection
  const analyticsPercentage = <?php echo $advancedStats['classification']['analytics']['percentage'] ?? 0; ?>;
  const semanticPercentage = <?php echo $advancedStats['classification']['semantic']['percentage'] ?? 0; ?>;
  const securityScore = <?php echo $advancedStats['security']['avg_security_score'] ?? 0; ?>;
  const agents = <?php echo json_encode($advancedStats['agents']['agents'] ?? []); ?>;
</script>
  <?php endif; ?>


<?php if ($config['rag_enabled']): ?>
<?php if (!defined('CHATGPT_TOKEN_CHARTS_JS')) {
  define('CHATGPT_TOKEN_CHARTS_JS', true);
?>
  <script defer src="<?php echo htmlspecialchars($tokenChartData['assets']['script'], ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php } ?>

<?php if (!empty($tokenDashboardStats)) { ?>
  <script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/token_distribution.js'); ?>"></script>
<?php } ?>

<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/performance_chart.js'); ?>"></script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/severity_distribution.js'); ?>"></script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/classification_chart.js'); ?>"></script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/security_score_chart.js'); ?>"></script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Agent/agent_chart.js'); ?>"></script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/load_cache.js'); ?>"></script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/get_cache_stats.js'); ?>"></script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/flush_cache.js'); ?>"></script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/feedback.js'); ?>"></script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/latency_charts.js'); ?>"></script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/cache_performance_charts.js'); ?>"></script>
<?php endif; ?>


  <script>
    // Uncomment to refresh dashboard every 30 seconds
    // setInterval(function() {
    //     location.reload();
    // }, 30000);
  </script>


<?php if ($config['chatgpt_enabled']): ?>
<!-- ============================================================================ -->
<!-- MODAL: Reset Cache -->
<!-- ============================================================================ -->
<!-- MODAL: Reset Cache -->
<!-- ============================================================================ -->
<div class="modal fade" id="resetCacheModal" tabindex="-1" aria-labelledby="resetCacheModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="resetCacheModalLabel">
          <i class="bi bi-trash"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_title'); ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle"></i>
          <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_warning_title'); ?></strong> <?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_warning_text'); ?>
        </div>
        
        <p><strong><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_select'); ?></strong></p>
        
        <!-- 2 Column Layout -->
        <div class="row">
          <!-- Left Column -->
          <div class="col-md-6">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_database" name="cache_types[]" value="database" checked>
              <label class="form-check-label" for="cache_database">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_database'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_database_desc'); ?></small>
              </label>
            </div>
            
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_schema" name="cache_types[]" value="schema" checked>
              <label class="form-check-label" for="cache_schema">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_schema'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_schema_desc'); ?></small>
              </label>
            </div>
            
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_intent" name="cache_types[]" value="intent" checked>
              <label class="form-check-label" for="cache_intent">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_intent'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_intent_desc'); ?></small>
              </label>
            </div>
            
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_ambiguity" name="cache_types[]" value="ambiguity" checked>
              <label class="form-check-label" for="cache_ambiguity">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_ambiguity'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_ambiguity_desc'); ?></small>
              </label>
            </div>
            
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_translation_ambiguity" name="cache_types[]" value="translation_ambiguity" checked>
              <label class="form-check-label" for="cache_translation_ambiguity">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_translation_ambiguity'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_translation_ambiguity_desc'); ?></small>
              </label>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_semantic" name="cache_types[]" value="semantic" checked>
              <label class="form-check-label" for="cache_semantic">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_semantic'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_semantic_desc'); ?></small>
              </label>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_sql" name="cache_types[]" value="sql" checked>
              <label class="form-check-label" for="cache_sql">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_sql'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_sql_desc'); ?></small>
              </label>
            </div>
          </div>
          <!-- Right Column -->
          <div class="col-md-6">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_context" name="cache_types[]" value="context" checked>
              <label class="form-check-label" for="cache_context">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_context'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_context_desc'); ?></small>
              </label>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_memory" name="cache_types[]" value="memory" checked>
              <label class="form-check-label" for="cache_memory">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_memory'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_memory_desc'); ?></small>
              </label>
            </div>
            
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_embedding" name="cache_types[]" value="embeddings" checked>
              <label class="form-check-label" for="cache_embedding">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_embedding'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_embedding_desc'); ?></small>
              </label>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_embedding_search" name="cache_types[]" value="embedding_search" checked>
              <label class="form-check-label" for="cache_embedding_search">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_embedding_search'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_embedding_search_desc'); ?></small>
              </label>
            </div>
            
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_translations" name="cache_types[]" value="translations" checked>
              <label class="form-check-label" for="cache_translations">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_translations'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_translations_desc'); ?></small>
              </label>
            </div>
            
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_classification" name="cache_types[]" value="classification" checked>
              <label class="form-check-label" for="cache_classification">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_classification'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_classification_desc'); ?></small>
              </label>
            </div>
            
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_hybrid" name="cache_types[]" value="hybrid" checked>
              <label class="form-check-label" for="cache_hybrid">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_hybrid') ?? 'Hybrid Query Cache'; ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_hybrid_desc') ?? 'Multi-temporal query results cache'; ?></small>
              </label>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_security" name="cache_types[]" value="security" checked>
              <label class="form-check-label" for="cache_security">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_security'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_security_desc'); ?></small>
              </label>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_reputation" name="cache_types[]" value="reputation" checked>
              <label class="form-check-label" for="cache_reputation">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_reputation'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_reputation_desc'); ?></small>
              </label>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="cache_config" name="cache_types[]" value="config" checked>
              <label class="form-check-label" for="cache_config">
                <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_config'); ?></strong>
                <br><small class="text-muted"><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_type_config_desc'); ?></small>
              </label>
            </div>
          </div>
        </div>
        
        <div id="cacheResetResult" class="mt-3" style="display: none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="bi bi-x"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_cancel'); ?>
        </button>
        <button type="button" class="btn btn-warning" id="confirmResetCache">
          <i class="bi bi-trash"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_confirm'); ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const confirmButton = document.getElementById('confirmResetCache');
  const resultDiv = document.getElementById('cacheResetResult');
  
  if (confirmButton) {
    confirmButton.addEventListener('click', function() {
      // Get selected cache types
      const checkboxes = document.querySelectorAll('input[name="cache_types[]"]:checked');
      const cacheTypes = Array.from(checkboxes).map(cb => cb.value);
      
      if (cacheTypes.length === 0) {
        resultDiv.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_select_one'); ?></div>';
        resultDiv.style.display = 'block';
        return;
      }
      
      // Disable button during processing
      confirmButton.disabled = true;
      confirmButton.innerHTML = '<i class="bi bi-arrow-repeat spinner-border spinner-border-sm"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_resetting'); ?>';
      
      // Display loading message
      resultDiv.innerHTML = '<div class="alert alert-info"><i class="bi bi-arrow-repeat spinner-border spinner-border-sm"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_in_progress'); ?></div>';
      resultDiv.style.display = 'block';
      
      // Send AJAX request
      fetch('<?php echo CLICSHOPPING::link('ajax/RAG/reset_cache.php'); ?>', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          cache_types: cacheTypes
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          let message = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_success'); ?></strong><br><br>';
          
          if (data.details) {
            message += '<ul class="mb-0">';
            if (data.details.files !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_files_deleted'); ?> : ' + data.details.files + '</li>';
            }
            if (data.details.translations !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_translations_deleted'); ?> : ' + data.details.translations + '</li>';
            }
            if (data.details.database !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_db_entries_deleted'); ?> : ' + data.details.database + '</li>';
            }
            if (data.details.prompts !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_prompts_deleted'); ?> : ' + data.details.prompts + '</li>';
            }
            if (data.details.semantic !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_semantic_deleted'); ?> : ' + data.details.semantic + '</li>';
            }
            if (data.details.schema !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_schema_deleted'); ?> : ' + data.details.schema + '</li>';
            }
            if (data.details.intent !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_intent_deleted'); ?> : ' + data.details.intent + '</li>';
            }
            if (data.details.ambiguity !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_ambiguity_deleted'); ?> : ' + data.details.ambiguity + '</li>';
            }
            if (data.details.schema !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_schema_deleted'); ?> : ' + data.details.schema + '</li>';
            }
            if (data.details.translation_ambiguity !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_translation_ambiguity_deleted'); ?> : ' + data.details.translation_ambiguity + '</li>';
            }
            if (data.details.context !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_context_deleted'); ?> : ' + data.details.context + '</li>';
            }
            if (data.details.embeddings !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_embeddings_deleted'); ?> : ' + data.details.embeddings + '</li>';
            }
            if (data.details.embedding_search !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_embedding_search_deleted'); ?> : ' + data.details.embedding_search + '</li>';
            }
            if (data.details.classification !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_classification_deleted'); ?> : ' + data.details.classification + '</li>';
            }
            if (data.details.hybrid !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_hybrid_deleted'); ?> : ' + data.details.hybrid + '</li>';
            }
            if (data.details.sql !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_sql_deleted'); ?> : ' + data.details.sql + '</li>';
            }
            if (data.details.memory !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_memory_deleted'); ?> : ' + data.details.memory + '</li>';
            }
            if (data.details.security !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_security_deleted'); ?> : ' + data.details.security + '</li>';
            }
            if (data.details.reputation !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_reputation_deleted'); ?> : ' + data.details.reputation + '</li>';
            }
            if (data.details.config !== undefined) {
              message += '<li><?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_config_deleted'); ?> : ' + data.details.config + '</li>';
            }
            message += '</ul>';
          }
          
          message += '</div>';
          resultDiv.innerHTML = message;
          
          // Close modal after 3 seconds
          setTimeout(function() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('resetCacheModal'));
            if (modal) {
              modal.hide();
            }
            // Reload page to update statistics
            location.reload();
          }, 3000);
        } else {
          resultDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_error'); ?></strong> ' + (data.message || '<?php echo $CLICSHOPPING_ChatGpt->getDef('cache_reset_error_occurred'); ?>') + '</div>';
        }
      })
      .catch(error => {
        console.error('Error:', error);
        resultDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <strong><?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_error'); ?></strong></div>';
      })
      .finally(() => {
        // Reactivate button
        confirmButton.disabled = false;
        confirmButton.innerHTML = '<i class="bi bi-trash"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('modal_reset_cache_confirm'); ?>';
      });
    });
  }
});
</script>
<?php endif; // End of $config['chatgpt_enabled'] check for reset cache modal ?>

<?php
// ============================================================================
// HELPER FUNCTIONS
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
