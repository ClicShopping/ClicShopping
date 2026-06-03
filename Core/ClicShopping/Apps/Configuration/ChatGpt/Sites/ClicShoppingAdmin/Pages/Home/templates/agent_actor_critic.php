<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\AI\Infrastructure\Metrics\ActorCriticMetricsProvider;
use ClicShopping\AI\Infrastructure\Metrics\ReputationMetricsProvider;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

// Load metrics
$metricsProvider = new ActorCriticMetricsProvider();
$metrics = $metricsProvider->getAllMetrics(7);

$registryStats = $metrics['registry_stats'] ?? [];
$actorMetrics = $metrics['actor_metrics'] ?? [];
$criticMetrics = $metrics['critic_metrics'] ?? [];
$coordinationMetrics = $metrics['coordination_metrics'] ?? [];
$utilizationMetrics = $metrics['utilization_metrics'] ?? [];
$recentCoordinations = $metrics['recent_coordinations'] ?? [];

// Load reputation metrics
$reputationProvider = new ReputationMetricsProvider();
$reputationMetrics = $reputationProvider->getAllMetrics(7);

$reputationStats = $reputationMetrics['reputation_stats'] ?? [];
$topCritics = $reputationMetrics['top_critics'] ?? [];
$reputationAlerts = $reputationMetrics['reputation_alerts'] ?? [];

// Helper functions for badge classes
function getStatusBadgeClass($status) {
  switch ($status) {
    case 'established':
      return 'success';
    case 'establishing':
      return 'info';
    case 'bootstrapping':
      return 'warning';
    default:
      return 'secondary';
  }
}

function getSeverityBadgeClass($severity) {
  switch ($severity) {
    case 'critical':
      return 'danger';
    case 'high':
      return 'warning';
    case 'medium':
      return 'info';
    case 'low':
      return 'secondary';
    default:
      return 'secondary';
  }
}
?>

<style>
.actor-critic-dashboard {
  padding: 20px;
}

.metric-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  margin-bottom: 20px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.metric-card h3 {
  margin-top: 0;
  color: #333;
  font-size: 1.2rem;
  border-bottom: 2px solid #007bff;
  padding-bottom: 10px;
}

.metric-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-top: 15px;
}

.metric-item {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 6px;
  text-align: center;
}

.metric-value {
  font-size: 2rem;
  font-weight: bold;
  color: #007bff;
}

.metric-label {
  font-size: 0.9rem;
  color: #666;
  margin-top: 5px;
}

.agent-table {
  width: 100%;
  margin-top: 15px;
  border-collapse: collapse;
}

.agent-table th {
  background: #007bff;
  color: white;
  padding: 10px;
  text-align: left;
}

.agent-table td {
  padding: 10px;
  border-bottom: 1px solid #ddd;
}

.agent-table tr:hover {
  background: #f8f9fa;
}

.timeline {
  margin-top: 15px;
  max-height: 400px;
  overflow-y: auto;
}

.timeline-item {
  padding: 10px;
  border-left: 3px solid #007bff;
  margin-bottom: 10px;
  background: #f8f9fa;
}

.timeline-item:hover {
  background: #e9ecef;
}

.score-badge {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 0.85rem;
  font-weight: bold;
}

.score-excellent {
  background: #28a745;
  color: white;
}

.score-good {
  background: #17a2b8;
  color: white;
}

.score-fair {
  background: #ffc107;
  color: #333;
}

.score-poor {
  background: #dc3545;
  color: white;
}

.utilization-bar {
  height: 20px;
  background: #e9ecef;
  border-radius: 10px;
  overflow: hidden;
  margin-top: 5px;
}

.utilization-fill {
  height: 100%;
  background: linear-gradient(90deg, #007bff, #0056b3);
  transition: width 0.3s ease;
}

.accordion-button {
  font-weight: 600;
  font-size: 1.1rem;
}

.accordion-button:not(.collapsed) {
  background-color: #e7f3ff;
  color: #0056b3;
}

.accordion-button:focus {
  box-shadow: none;
  border-color: rgba(0,123,255,.25);
}

.accordion-body {
  padding: 1.5rem;
}

.accordion-item {
  margin-bottom: 0.5rem;
  border: 1px solid #dee2e6;
  border-radius: 0.375rem;
}
</style>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span class="col-md-1 logoHeading">
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title_actor_critic'), '40', '40'); ?>
          </span>
          <span class="col-md-7 pageHeading">
            <?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title_actor_critic'); ?>
          </span>
          <span class="col-md-4 text-end">
            <?php 
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_refresh'), null, null, 'primary', ['params' => 'onclick="location.reload()"']) . ' ';
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back_dashboard'), null, $CLICSHOPPING_ChatGpt->link('Dashboard'), 'warning');
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="actor-critic-dashboard">
    <ul class="nav nav-tabs flex-column flex-sm-row" id="actorCriticTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <a class="nav-link active"
           id="actor-critic-tab"
           data-bs-toggle="tab"
           data-bs-target="#actor-critic-section"
           data-toggle="tab"
           href="#actor-critic-section"
           role="tab"
           aria-controls="actor-critic-section"
           aria-selected="true">
          <?php echo $CLICSHOPPING_ChatGpt->getDef('text_actor_critic'); ?>
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link"
           id="reputation-tab"
           data-bs-toggle="tab"
           data-bs-target="#reputation-section"
           data-toggle="tab"
           href="#reputation-section"
           role="tab"
           aria-controls="reputation-section"
           aria-selected="false">
          <?php echo $CLICSHOPPING_ChatGpt->getDef('text_reputation_metrics'); ?>
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link"
           id="adaptive-weighting-tab"
           data-bs-toggle="tab"
           data-bs-target="#adaptive-weighting-section"
           data-toggle="tab"
           href="#adaptive-weighting-section"
           role="tab"
           aria-controls="adaptive-weighting-section"
           aria-selected="false">
          <?php echo $CLICSHOPPING_ChatGpt->getDef('text_adaptive_weighting'); ?>
        </a>
      </li>
    </ul>

    <div class="tab-content" id="actorCriticTabContent">
      <div class="tab-pane fade show active" id="actor-critic-section" role="tabpanel" aria-labelledby="actor-critic-tab">
        
        <!-- Accordion for Actor-Critic Tab -->
        <div class="accordion" id="actorCriticAccordion">
          
          <!-- Registry Overview Section -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingRegistry">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRegistry" aria-expanded="true" aria-controls="collapseRegistry">
                <i class="bi bi-diagram-3"></i>&nbsp;&nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('text_registry_overview'); ?>
              </button>
            </h2>
            <div id="collapseRegistry" class="accordion-collapse collapse show" aria-labelledby="headingRegistry" data-bs-parent="#actorCriticAccordion">
              <div class="accordion-body">
                <div class="metric-grid">
                  <div class="metric-item">
                    <div class="metric-value"><?php echo $registryStats['total_actors'] ?? 0; ?></div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_total_actors'); ?></div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-value"><?php echo $registryStats['total_critics'] ?? 0; ?></div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_total_critics'); ?></div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-value"><?php echo $registryStats['separation_ratio'] ?? 0; ?>%</div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_separation_ratio'); ?></div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-value"><?php echo $registryStats['total_agents'] ?? 0; ?></div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_total_agents'); ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Actor Metrics Section -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingActor">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseActor" aria-expanded="false" aria-controls="collapseActor">
                <i class="bi bi-play-circle"></i>&nbsp;&nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('text_actor_metrics'); ?>
              </button>
            </h2>
            <div id="collapseActor" class="accordion-collapse collapse" aria-labelledby="headingActor" data-bs-parent="#actorCriticAccordion">
              <div class="accordion-body">
                <div class="metric-grid">
                  <div class="metric-item">
                    <div class="metric-value"><?php echo $actorMetrics['total_executions'] ?? 0; ?></div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_total_executions'); ?></div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-value"><?php echo $actorMetrics['avg_execution_time'] ?? 0; ?>ms</div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_execution_time'); ?></div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-value"><?php echo $actorMetrics['success_rate'] ?? 0; ?>%</div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_success_rate'); ?></div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-value"><?php echo number_format($actorMetrics['avg_quality_score'] ?? 0, 2); ?></div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_quality_score'); ?></div>
                  </div>
                </div>

                <!-- Top Actors Table -->
                <?php if (!empty($actorMetrics['top_actors'])): ?>
                <table class="agent-table mt-3">
                  <thead>
                    <tr>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_actor_id'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_executions'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_time'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_quality'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_success_rate'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($actorMetrics['top_actors'] as $actor): ?>
                    <tr>
                      <td><strong><?php echo htmlspecialchars($actor['actor_id']); ?></strong></td>
                      <td><?php echo $actor['executions']; ?></td>
                      <td><?php echo $actor['avg_execution_time']; ?>ms</td>
                      <td><?php echo number_format($actor['avg_quality_score'], 2); ?></td>
                      <td>
                        <?php 
                          $rate = $actor['success_rate'];
                          $class = $rate >= 95 ? 'score-excellent' : ($rate >= 85 ? 'score-good' : ($rate >= 70 ? 'score-fair' : 'score-poor'));
                          echo "<span class='score-badge {$class}'>{$rate}%</span>";
                        ?>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Critic Metrics Section -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingCritic">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCritic" aria-expanded="false" aria-controls="collapseCritic">
                <i class="bi bi-clipboard-check"></i>&nbsp;&nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('text_critic_metrics'); ?>
              </button>
            </h2>
            <div id="collapseCritic" class="accordion-collapse collapse" aria-labelledby="headingCritic" data-bs-parent="#actorCriticAccordion">
              <div class="accordion-body">
                <div class="metric-grid">
                  <div class="metric-item">
                    <div class="metric-value"><?php echo $criticMetrics['total_evaluations'] ?? 0; ?></div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_total_evaluations'); ?></div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-value"><?php echo $criticMetrics['avg_evaluation_time'] ?? 0; ?>ms</div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_evaluation_time'); ?></div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-value"><?php echo number_format($criticMetrics['avg_overall_score'] ?? 0, 2); ?></div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_overall_score'); ?></div>
                  </div>
                </div>

                <!-- Top Critics Table -->
                <?php if (!empty($criticMetrics['top_critics'])): ?>
                <table class="agent-table mt-3">
                  <thead>
                    <tr>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_critic_id'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluations'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_time'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_score'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_accuracy'); ?></th>
                      <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_completeness'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($criticMetrics['top_critics'] as $critic): ?>
                    <tr>
                      <td><strong><?php echo htmlspecialchars($critic['critic_id']); ?></strong></td>
                      <td><?php echo $critic['evaluations']; ?></td>
                      <td><?php echo $critic['avg_evaluation_time']; ?>ms</td>
                      <td><?php echo number_format($critic['avg_overall_score'], 2); ?></td>
                      <td><?php echo number_format($critic['avg_accuracy'], 2); ?></td>
                      <td><?php echo number_format($critic['avg_completeness'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Coordination Metrics Section -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingCoordination">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCoordination" aria-expanded="false" aria-controls="collapseCoordination">
                <i class="bi bi-shuffle"></i>&nbsp;&nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('text_coordination_metrics'); ?>
              </button>
            </h2>
            <div id="collapseCoordination" class="accordion-collapse collapse" aria-labelledby="headingCoordination" data-bs-parent="#actorCriticAccordion">
              <div class="accordion-body">
                <div class="metric-grid">
                  <div class="metric-item">
                    <div class="metric-value"><?php echo $coordinationMetrics['total_coordinations'] ?? 0; ?></div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_total_coordinations'); ?></div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-value"><?php echo $coordinationMetrics['avg_total_time'] ?? 0; ?>ms</div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_coordination_time'); ?></div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-value"><?php echo number_format($coordinationMetrics['avg_consensus_score'] ?? 0, 2); ?></div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_consensus_score'); ?></div>
                  </div>
                  <div class="metric-item">
                    <div class="metric-value"><?php echo number_format($coordinationMetrics['avg_critics_per_coordination'] ?? 0, 1); ?></div>
                    <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_critics_per_coordination'); ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Utilization Metrics Section -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingUtilization">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUtilization" aria-expanded="false" aria-controls="collapseUtilization">
                <i class="bi bi-speedometer2"></i>&nbsp;&nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('text_utilization_metrics'); ?>
              </button>
            </h2>
            <div id="collapseUtilization" class="accordion-collapse collapse" aria-labelledby="headingUtilization" data-bs-parent="#actorCriticAccordion">
              <div class="accordion-body">
                <div class="row">
                  <div class="col-md-6">
                    <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('text_actor_utilization'); ?></h5>
                    <div class="utilization-bar">
                      <div class="utilization-fill" style="width: <?php echo min(100, $utilizationMetrics['actor_utilization'] ?? 0); ?>%"></div>
                    </div>
                    <p class="mt-2"><?php echo number_format($utilizationMetrics['actor_utilization'] ?? 0, 2); ?>%</p>
                  </div>
                  <div class="col-md-6">
                    <h5><?php echo $CLICSHOPPING_ChatGpt->getDef('text_critic_utilization'); ?></h5>
                    <div class="utilization-bar">
                      <div class="utilization-fill" style="width: <?php echo min(100, $utilizationMetrics['critic_utilization'] ?? 0); ?>%"></div>
                    </div>
                    <p class="mt-2"><?php echo number_format($utilizationMetrics['critic_utilization'] ?? 0, 2); ?>%</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Coordinations Timeline Section -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingTimeline">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTimeline" aria-expanded="false" aria-controls="collapseTimeline">
                <i class="bi bi-clock-history"></i>&nbsp;&nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('text_recent_coordinations'); ?>
              </button>
            </h2>
            <div id="collapseTimeline" class="accordion-collapse collapse" aria-labelledby="headingTimeline" data-bs-parent="#actorCriticAccordion">
              <div class="accordion-body">
                <div class="timeline">
                  <?php if (!empty($recentCoordinations)): ?>
                    <?php foreach ($recentCoordinations as $coord): ?>
                    <div class="timeline-item">
                      <div class="row">
                        <div class="col-md-3">
                          <strong><?php echo htmlspecialchars($coord['actor_id']); ?></strong>
                        </div>
                        <div class="col-md-2">
                          <?php 
                            $score = $coord['consensus_score'];
                            $class = $score >= 0.9 ? 'score-excellent' : ($score >= 0.75 ? 'score-good' : ($score >= 0.6 ? 'score-fair' : 'score-poor'));
                            echo "<span class='score-badge {$class}'>" . number_format($score, 2) . "</span>";
                          ?>
                        </div>
                        <div class="col-md-2">
                          <?php echo $coord['num_critics']; ?> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_critics'); ?>
                        </div>
                        <div class="col-md-2">
                          <?php echo $coord['total_time_ms']; ?>ms
                        </div>
                        <div class="col-md-3 text-end">
                          <small><?php echo $coord['created_at']; ?></small>
                        </div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="alert alert-info">
                      <?php echo $CLICSHOPPING_ChatGpt->getDef('text_no_coordinations'); ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Export Functionality Section -->
          <div class="accordion-item">
            <h2 class="accordion-header" id="headingExport">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExport" aria-expanded="false" aria-controls="collapseExport">
                <i class="bi bi-download"></i>&nbsp;&nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('text_export_metrics'); ?>
              </button>
            </h2>
            <div id="collapseExport" class="accordion-collapse collapse" aria-labelledby="headingExport" data-bs-parent="#actorCriticAccordion">
              <div class="accordion-body">
                <div class="row">
                  <div class="col-md-12">
                    <?php 
                      echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_export_csv'), null, null, 'success', ['params' => 'onclick="exportMetrics(\'csv\')"']) . ' ';
                      echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_export_json'), null, null, 'info', ['params' => 'onclick="exportMetrics(\'json\')"']);
                    ?>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div><!-- End Accordion -->
      </div><!-- End Tab 1 -->

      <div class="tab-pane fade" id="reputation-section" role="tabpanel" aria-labelledby="reputation-tab">
        <!-- Reputation Metrics -->
        <div class="metric-card">
          <h3><i class="bi bi-star-fill"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_reputation_metrics'); ?></h3>
          <div class="metric-grid">
            <div class="metric-item">
              <div class="metric-value"><?php echo number_format($reputationStats['avg_reputation'] ?? 0, 3); ?></div>
              <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_reputation'); ?></div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?php echo $reputationStats['total_critics'] ?? 0; ?></div>
              <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_total_critics'); ?></div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?php echo $reputationStats['established_count'] ?? 0; ?></div>
              <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_established_critics'); ?></div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?php echo count($reputationAlerts); ?></div>
              <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_active_alerts'); ?></div>
            </div>
          </div>

          <!-- Top Critics Table -->
          <table class="agent-table">
            <thead>
              <tr>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_critic_id'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_reputation'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluations'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_status'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($topCritics)): ?>
                <tr>
                  <td colspan="4" style="text-align: center;">
                    <?php echo $CLICSHOPPING_ChatGpt->getDef('text_no_data'); ?>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($topCritics as $critic): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($critic['critic_id']); ?></strong></td>
                    <td><strong><?php echo number_format($critic['reputation_score'], 3); ?></strong></td>
                    <td><?php echo $critic['total_evaluations']; ?></td>
                    <td>
                      <span class="score-badge score-<?php echo getStatusBadgeClass($critic['status']); ?>">
                        <?php echo $critic['status']; ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Reputation Alerts -->
        <?php if (!empty($reputationAlerts)): ?>
        <div class="metric-card">
          <h3><i class="bi bi-exclamation-triangle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_reputation_alerts'); ?></h3>
          <table class="agent-table">
            <thead>
              <tr>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_critic_id'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_alert_type'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_severity'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_message'); ?></th>
                <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_created_at'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reputationAlerts as $alert): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($alert['critic_id']); ?></strong></td>
                  <td><?php echo $alert['alert_type']; ?></td>
                  <td>
                    <span class="score-badge score-<?php echo getSeverityBadgeClass($alert['severity']); ?>">
                      <?php echo $alert['severity']; ?>
                    </span>
                  </td>
                  <td><?php echo htmlspecialchars($alert['message']); ?></td>
                  <td><?php echo date('Y-m-d H:i', strtotime($alert['created_at'])); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Adaptive Weighting Tab -->
    <div class="tab-pane fade" id="adaptive-weighting-section" role="tabpanel" aria-labelledby="adaptive-weighting-tab">
      
      <!-- Accordion for Adaptive Weighting Tab -->
      <div class="accordion" id="adaptiveWeightingAccordion">
        
        <!-- Statistics Overview Section -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingWeightStats">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWeightStats" aria-expanded="true" aria-controls="collapseWeightStats">
              <i class="bi bi-bar-chart"></i>&nbsp;&nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('text_registry_overview'); ?>
            </button>
          </h2>
          <div id="collapseWeightStats" class="accordion-collapse collapse show" aria-labelledby="headingWeightStats" data-bs-parent="#adaptiveWeightingAccordion">
            <div class="accordion-body">
              <div class="row">
                <div class="col-md-3">
                  <div class="card text-center">
                    <div class="card-body">
                      <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_total_weights'); ?></h5>
                      <h2 id="stat-total-weights">-</h2>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card text-center">
                    <div class="card-body">
                      <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_weight'); ?></h5>
                      <h2 id="stat-avg-weight">-</h2>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card text-center">
                    <div class="card-body">
                      <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_consensus_count'); ?></h5>
                      <h2 id="stat-consensus-count">-</h2>
                    </div>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="card text-center">
                    <div class="card-body">
                      <h5 class="card-title"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_difference'); ?></h5>
                      <h2 id="stat-avg-difference">-</h2>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Adaptive Weights Section -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingAdaptiveWeights">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdaptiveWeights" aria-expanded="false" aria-controls="collapseAdaptiveWeights">
              <i class="bi bi-sliders"></i>&nbsp;&nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('text_recent_adaptive_weights'); ?>
            </button>
          </h2>
          <div id="collapseAdaptiveWeights" class="accordion-collapse collapse" aria-labelledby="headingAdaptiveWeights" data-bs-parent="#adaptiveWeightingAccordion">
            <div class="accordion-body">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluation_id'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_critic'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_raw_weight'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_normalized_weight'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_explanation'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_created'); ?></th>
                  </tr>
                </thead>
                <tbody id="adaptive-weights-tbody">
                  <tr><td colspan="6" class="text-center">Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Consensus Comparison Section -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="headingConsensusComparison">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseConsensusComparison" aria-expanded="false" aria-controls="collapseConsensusComparison">
              <i class="bi bi-arrows-collapse"></i>&nbsp;&nbsp;<?php echo $CLICSHOPPING_ChatGpt->getDef('text_consensus_comparison'); ?>
            </button>
          </h2>
          <div id="collapseConsensusComparison" class="accordion-collapse collapse" aria-labelledby="headingConsensusComparison" data-bs-parent="#adaptiveWeightingAccordion">
            <div class="accordion-body">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluation_id'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_dynamic_consensus'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_static_consensus'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_difference'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_difference_percent'); ?></th>
                    <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_created'); ?></th>
                  </tr>
                </thead>
                <tbody id="consensus-comparison-tbody">
                  <tr><td colspan="6" class="text-center">Loading...</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div><!-- End Accordion -->
    </div><!-- End Tab 3 -->
  </div>
</div>
<div class="py-4"></div>
<script>
window.AdaptiveWeightingConfig = {
  baseUrl: '<?php echo CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin'); ?>',
  weightsEndpoint: 'ajax/Agent/get_adaptive_weights.php',
  consensusEndpoint: 'ajax/Agent/get_agent_consensus.php'
};
window.ExportMetricsConfig = {
  baseUrl: '<?php echo CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin'); ?>'
};
</script>
<script src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Agent/export_metrics.js'); ?>"></script>
<script src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/Agent/adaptive_weighting_tab.js'); ?>"></script>
