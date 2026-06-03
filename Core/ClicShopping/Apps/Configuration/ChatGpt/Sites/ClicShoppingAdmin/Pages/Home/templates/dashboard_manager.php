<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Manager dashboard (business view): General, Alerts, Trend, Token & Cost, Feedback.
 */
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

include __DIR__ . '/dashboard/_data.php';
?>
   <div class="contentBody">
    <div class="row">
      <div class="col-md-12">
        <div class="card card-block headerCard">
          <div class="row">
          <span class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title'), '40', '40'); ?></span>
            <span class="col-md-3 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title') . ' — ' . $CLICSHOPPING_ChatGpt->getDef('dashboard_manager_title'); ?></span>
            <span class="col-md-8 text-end">
            <?php
              if ($config['chatgpt_enabled']) {
                echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_help'), null, $CLICSHOPPING_ChatGpt->link('Help'), 'info') . ' ';

                // Competitor (WebSearch) configuration - business-facing, lives in the Manager dashboard
                if (defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True') {
                  echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_rag_websearch_config'), null, $CLICSHOPPING_ChatGpt->link('RagWebSearch'), 'success') . ' ';
                }
              }
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back'), null, $CLICSHOPPING_ChatGpt->link('Dashboard'), 'primary');
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
        <li class="nav-item"><?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_ChatGpt->getDef('tab_general') . '</a>'; ?></li>
        <?php if ($config['rag_enabled']): ?>
        <li class="nav-item"><?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">🚨  ' . $CLICSHOPPING_ChatGpt->getDef('tab_alert') . ' <span class="badge bg-danger ms-2">' . count($activeAlerts) . '</span></a>'; ?></li>
        <li class="nav-item"><?php echo '<a href="#tab4" role="tab" data-bs-toggle="tab" class="nav-link">📈 ' . $CLICSHOPPING_ChatGpt->getDef('tab_trend') . '</a>'; ?></li>
        <li class="nav-item"><?php echo '<a href="#tab5" role="tab" data-bs-toggle="tab" class="nav-link">🎯 ' . $CLICSHOPPING_ChatGpt->getDef('tab_token_cost') . '</a>'; ?></li>
        <li class="nav-item"><?php echo '<a href="#tab11" role="tab" data-bs-toggle="tab" class="nav-link">⚡ ' . $CLICSHOPPING_ChatGpt->getDef('tab_feedback') . '</a>'; ?></li>
        <?php endif; ?>
      </ul>
      <div class="tabsClicShopping">
        <div class="tab-content">
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
          <?php if ($config['rag_enabled']): ?>
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
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php include __DIR__ . '/dashboard/_scripts.php'; ?>
