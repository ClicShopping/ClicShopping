<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Data Scientist dashboard (analytics/ML view): Classification & Performance,
 * plus Agent Monitoring quick access. To be reorganized later.
 */
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

  $CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

include __DIR__ . '/dashboard/_data.php';
?>
   <div class="contentBody">
    <div class="row">
      <div class="col-md-12">
        <div class="card card-block headerCard">
          <div class="row">
          <span class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title'), '40', '40'); ?></span>
            <span class="col-md-3 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title') . ' — ' . $CLICSHOPPING_ChatGpt->getDef('dashboard_data_scientist_title'); ?></span>
            <span class="col-md-8 text-end">
            <?php
              // Configure button - ALWAYS visible regardless of configuration state
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_configure'), null, $CLICSHOPPING_ChatGpt->link('Configure'), 'primary') . ' ';

              if ($config['chatgpt_enabled']) {
                echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_help'), null, $CLICSHOPPING_ChatGpt->link('Help'), 'info') . ' ';

                if (defined('CLICSHOPPING_APP_CHATGPT_RA_STATUS') && CLICSHOPPING_APP_CHATGPT_RA_STATUS == 'True') {
                  echo HTML::button($CLICSHOPPING_ChatGpt->getDef('text_ĥeading_remove_cache'), null, null, 'warning', ['params' => 'data-bs-toggle="modal" data-bs-target="#resetCacheModal"']) . ' ';
                  echo '&nbsp;';
                  echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_reset_all_stats'), null, null, 'danger', ['params' => 'data-bs-toggle="modal" data-bs-target="#resetStatsModal"']) . ' ';
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
        <?php if ($config['rag_enabled']): ?>
        <li class="nav-item"><?php echo '<a href="#tab6" role="tab" data-bs-toggle="tab" class="nav-link active">🎯 ' . $CLICSHOPPING_ChatGpt->getDef('tab_classification_performance') . '</a>'; ?></li>
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
              <div class="col-md-3 mt-3">
                <?php echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_adaptive_weighting'), null, $CLICSHOPPING_ChatGpt->link('AdaptiveWeightingDashboard'), 'secondary', ['params' => 'style="width: 100%;"']); ?>
                <small class="text-muted d-block mt-1"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_adaptive_weighting_help'); ?></small>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <div class="tabsClicShopping">
        <div class="tab-content">
          <?php if ($config['rag_enabled']): ?>
          <div class="tab-pane active" id="tab6">
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
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php include __DIR__ . '/dashboard/_modal_reset_stats.php'; ?>
<?php include __DIR__ . '/dashboard/_scripts.php'; ?>
<?php include __DIR__ . '/dashboard/_modal_reset_cache.php'; ?>
