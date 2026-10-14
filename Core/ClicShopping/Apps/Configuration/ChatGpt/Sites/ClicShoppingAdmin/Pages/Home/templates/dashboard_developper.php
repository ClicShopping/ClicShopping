<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Developper dashboard (technical/ops view): Component, Latency & Fast-Lane,
 * Security, Performance & Cache, Export & API. Hosts Configure + cache/stats reset.
 */
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Infrastructure\Cache\ClassificationCache;
use ClicShopping\AI\Infrastructure\Cache\RagCache;
use ClicShopping\AI\Infrastructure\Cache\TranslationCache;

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
            <span class="col-md-3 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title') . ' — ' . $CLICSHOPPING_ChatGpt->getDef('dashboard_developper_title'); ?></span>
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
        <li class="nav-item"><?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_ChatGpt->getDef('tab_component') . '</a>'; ?></li>
        <li class="nav-item"><?php echo '<a href="#tab_latency" role="tab" data-bs-toggle="tab" class="nav-link">⚡ ' . $CLICSHOPPING_ChatGpt->getDef('tab_latency_fastlane') . '</a>'; ?></li>
        <li class="nav-item"><?php echo '<a href="#tab7" role="tab" data-bs-toggle="tab" class="nav-link">🔒 ' . $CLICSHOPPING_ChatGpt->getDef('tab_security') . '</a>'; ?></li>
        <li class="nav-item"><?php echo '<a href="#tab_cache" role="tab" data-bs-toggle="tab" class="nav-link">💾 ' . $CLICSHOPPING_ChatGpt->getDef('tab_cache') . '</a>'; ?></li>
        <li class="nav-item"><?php echo '<a href="#tab_performance" role="tab" data-bs-toggle="tab" class="nav-link">⚡ ' . $CLICSHOPPING_ChatGpt->getDef('tab_performance') . '</a>'; ?></li>
        <li class="nav-item"><?php echo '<a href="#tab12" role="tab" data-bs-toggle="tab" class="nav-link">📥 ' . $CLICSHOPPING_ChatGpt->getDef('tab_export_api') . '</a>'; ?></li>
        <?php endif; ?>
      </ul>
      <div class="tabsClicShopping">
        <div class="tab-content">
          <?php if ($config['rag_enabled']): ?>
          <div class="tab-pane active" id="tab2">
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
                        window.SourceDistributionConfig = {
                          labels: <?php echo json_encode(array_map(function($s) { return ucfirst(str_replace('_', ' ', $s)); }, array_keys($sourceStats['sources']))); ?>,
                          data: <?php echo json_encode(array_column($sourceStats['sources'], 'count')); ?>,
                          totalQueries: <?php echo $sourceStats['total_queries']; ?>
                        };
                      </script>
                      <script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Rag/source_distribution_chart.js'); ?>"></script>
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
          <div class="tab-pane" id="tab_cache">
            <div style="padding: 20px;">
              <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('tab_cache'); ?></h5>

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
          </div>
          <div class="tab-pane" id="tab_performance">
            <div style="padding: 20px;">
              <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('tab_performance'); ?></h5>
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
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php include __DIR__ . '/dashboard/_modal_reset_stats.php'; ?>
<?php include __DIR__ . '/dashboard/_scripts.php'; ?>
<?php include __DIR__ . '/dashboard/_modal_reset_cache.php'; ?>
