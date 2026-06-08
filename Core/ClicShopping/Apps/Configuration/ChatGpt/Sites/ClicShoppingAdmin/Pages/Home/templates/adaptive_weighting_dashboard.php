<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\AI\Infrastructure\Metrics\AdaptiveWeightingMetricsProvider;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

// Get period from query parameter (default: 7 days)
$periodDays = isset($_GET['period']) ? (int)$_GET['period'] : 7;
$domain = isset($_GET['domain']) ? $_GET['domain'] : null;

// Load metrics
$metricsProvider = new AdaptiveWeightingMetricsProvider();
$metrics = $metricsProvider->getAllMetrics($periodDays);

$weightStats = $metrics['weight_stats'] ?? [];
$weightDistribution = $metrics['weight_distribution'] ?? [];
$criticWeightHistory = $metrics['critic_weight_history'] ?? [];
$weightsByDomain = $metrics['weights_by_domain'] ?? [];
$domainMatchQuality = $metrics['domain_match_quality'] ?? [];
$consensusComparison = $metrics['consensus_comparison'] ?? [];
$weightAnomalies = $metrics['weight_anomalies'] ?? [];
$weightTrends = $metrics['weight_trends'] ?? [];
$topWeightedCritics = $metrics['top_weighted_critics'] ?? [];
$criticDomainPerformance = $metrics['critic_domain_performance'] ?? [];

// Helper functions
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

function formatWeight($weight) {
  return number_format($weight, 4);
}
?>

<style>
.adaptive-weighting-dashboard {
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

.data-table {
  width: 100%;
  margin-top: 15px;
  border-collapse: collapse;
}

.data-table th {
  background: #007bff;
  color: white;
  padding: 10px;
  text-align: left;
}

.data-table td {
  padding: 10px;
  border-bottom: 1px solid #ddd;
}

.data-table tr:hover {
  background: #f8f9fa;
}

.chart-container {
  margin-top: 20px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 6px;
}

.badge-custom {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: 0.85rem;
  font-weight: bold;
}

.filter-bar {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 6px;
  margin-bottom: 20px;
}

.domain-badge {
  display: inline-block;
  padding: 5px 10px;
  margin: 2px;
  border-radius: 4px;
  background: #e9ecef;
  font-size: 0.85rem;
}

.comparison-bar {
  height: 30px;
  background: #e9ecef;
  border-radius: 15px;
  overflow: hidden;
  position: relative;
  margin: 10px 0;
}

.comparison-fill-dynamic {
  height: 100%;
  background: linear-gradient(90deg, #28a745, #20c997);
  float: left;
}

.comparison-fill-static {
  height: 100%;
  background: linear-gradient(90deg, #6c757d, #adb5bd);
  float: left;
}
</style>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span class="col-md-1 logoHeading">
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_ChatGpt->getDef('heading_title_adaptive_weighting'), '40', '40'); ?>
          </span>
          <span class="col-md-7 pageHeading">
            <?php echo '&nbsp;' . $CLICSHOPPING_ChatGpt->getDef('heading_title_adaptive_weighting'); ?>
          </span>
          <span class="col-md-4 text-end">
            <?php 
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_refresh'), null, null, 'primary', ['params' => 'onclick="location.reload()"']) . ' ';
              echo HTML::button($CLICSHOPPING_ChatGpt->getDef('button_back_dashboard'), null, $CLICSHOPPING_ChatGpt->link('DashboardDataScientist'), 'warning');
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="adaptive-weighting-dashboard">
    <!-- Filter Bar -->
    <div class="filter-bar">
      <div class="row">
        <div class="col-md-6">
          <label><?php echo $CLICSHOPPING_ChatGpt->getDef('text_filter_by_period'); ?>:</label>
          <select class="form-select" onchange="window.location.href='<?php echo $CLICSHOPPING_ChatGpt->link('AdaptiveWeightingDashboard'); ?>&period=' + this.value">
            <option value="7" <?php echo $periodDays == 7 ? 'selected' : ''; ?>><?php echo $CLICSHOPPING_ChatGpt->getDef('text_7_days'); ?></option>
            <option value="30" <?php echo $periodDays == 30 ? 'selected' : ''; ?>><?php echo $CLICSHOPPING_ChatGpt->getDef('text_30_days'); ?></option>
            <option value="90" <?php echo $periodDays == 90 ? 'selected' : ''; ?>><?php echo $CLICSHOPPING_ChatGpt->getDef('text_90_days'); ?></option>
          </select>
        </div>
      </div>
    </div>

    <!-- Weight Statistics Overview -->
    <div class="metric-card">
      <h3><i class="bi bi-bar-chart"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_weight_stats'); ?></h3>
      <div class="metric-grid">
        <div class="metric-item">
          <div class="metric-value"><?php echo $weightStats['total_weight_calculations'] ?? 0; ?></div>
          <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_total_weight_calculations'); ?></div>
        </div>
        <div class="metric-item">
          <div class="metric-value"><?php echo formatWeight($weightStats['avg_weight'] ?? 0); ?></div>
          <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_weight'); ?></div>
        </div>
        <div class="metric-item">
          <div class="metric-value"><?php echo $weightStats['total_evaluations'] ?? 0; ?></div>
          <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_total_evaluations'); ?></div>
        </div>
        <div class="metric-item">
          <div class="metric-value"><?php echo $weightStats['active_critics'] ?? 0; ?></div>
          <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_active_critics'); ?></div>
        </div>
      </div>
    </div>

    <!-- Consensus Comparison -->
    <div class="metric-card">
      <h3><i class="bi bi-shuffle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_consensus_comparison'); ?></h3>
      <div class="metric-grid">
        <div class="metric-item">
          <div class="metric-value"><?php echo $consensusComparison['total_comparisons'] ?? 0; ?></div>
          <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_total_comparisons'); ?></div>
        </div>
        <div class="metric-item">
          <div class="metric-value" style="color: #28a745;"><?php echo formatWeight($consensusComparison['avg_dynamic_consensus'] ?? 0); ?></div>
          <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_dynamic_consensus'); ?></div>
        </div>
        <div class="metric-item">
          <div class="metric-value" style="color: #6c757d;"><?php echo formatWeight($consensusComparison['avg_static_consensus'] ?? 0); ?></div>
          <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_static_consensus'); ?></div>
        </div>
        <div class="metric-item">
          <div class="metric-value" style="color: <?php echo ($consensusComparison['dynamic_better_percentage'] ?? 0) >= 50 ? '#28a745' : '#dc3545'; ?>">
            <?php echo $consensusComparison['dynamic_better_percentage'] ?? 0; ?>%
          </div>
          <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_dynamic_better_percentage'); ?></div>
        </div>
      </div>

      <!-- Comparison Visualization -->
      <div class="comparison-bar">
        <div class="comparison-fill-dynamic" style="width: <?php echo $consensusComparison['dynamic_better_percentage'] ?? 0; ?>%"></div>
        <div class="comparison-fill-static" style="width: <?php echo 100 - ($consensusComparison['dynamic_better_percentage'] ?? 0); ?>%"></div>
      </div>
      <div class="text-center">
        <small>
          <span style="color: #28a745;">■</span> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_dynamic_consensus'); ?> 
          vs 
          <span style="color: #6c757d;">■</span> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_static_consensus'); ?>
        </small>
      </div>

      <!-- Recent Comparisons Table -->
      <?php if (!empty($consensusComparison['recent_comparisons'])): ?>
      <table class="data-table mt-3">
        <thead>
          <tr>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluation_id'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_dynamic_consensus'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_static_consensus'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_difference'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_created_at'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (array_slice($consensusComparison['recent_comparisons'], 0, 10) as $comparison): ?>
          <tr>
            <td><code><?php echo htmlspecialchars(substr($comparison['evaluation_id'], 0, 8)); ?>...</code></td>
            <td><strong style="color: #28a745;"><?php echo formatWeight($comparison['dynamic_consensus']); ?></strong></td>
            <td><strong style="color: #6c757d;"><?php echo formatWeight($comparison['static_consensus']); ?></strong></td>
            <td style="color: <?php echo $comparison['difference'] > 0 ? '#28a745' : '#dc3545'; ?>">
              <?php echo ($comparison['difference'] > 0 ? '+' : '') . formatWeight($comparison['difference']); ?>
            </td>
            <td><?php echo date('Y-m-d H:i', strtotime($comparison['created_at'])); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <!-- Weights by Domain -->
    <?php if (!empty($weightsByDomain)): ?>
    <div class="metric-card">
      <h3><i class="bi bi-diagram-3"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_weights_by_domain'); ?></h3>
      <table class="data-table">
        <thead>
          <tr>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_domain'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_weight_domain'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_weight_count'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_critic_count'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($weightsByDomain as $domainData): ?>
          <tr>
            <td><span class="domain-badge"><?php echo htmlspecialchars($domainData['domain']); ?></span></td>
            <td><strong><?php echo formatWeight($domainData['avg_weight']); ?></strong></td>
            <td><?php echo $domainData['weight_count']; ?></td>
            <td><?php echo $domainData['critic_count']; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Domain Match Quality -->
    <?php if (!empty($domainMatchQuality['match_quality_distribution'])): ?>
    <div class="metric-card">
      <h3><i class="bi bi-bullseye"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_domain_match_quality'); ?></h3>
      <div class="metric-grid">
        <div class="metric-item" style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);">
          <div class="metric-value" style="color: #28a745;"><?php echo $domainMatchQuality['match_quality_distribution']['high_match'] ?? 0; ?></div>
          <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_high_match'); ?></div>
        </div>
        <div class="metric-item" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
          <div class="metric-value" style="color: #17a2b8;"><?php echo $domainMatchQuality['match_quality_distribution']['medium_match'] ?? 0; ?></div>
          <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_medium_match'); ?></div>
        </div>
        <div class="metric-item" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);">
          <div class="metric-value" style="color: #ffc107;"><?php echo $domainMatchQuality['match_quality_distribution']['low_match'] ?? 0; ?></div>
          <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_low_match'); ?></div>
        </div>
        <div class="metric-item" style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);">
          <div class="metric-value" style="color: #dc3545;"><?php echo $domainMatchQuality['match_quality_distribution']['no_match'] ?? 0; ?></div>
          <div class="metric-label"><?php echo $CLICSHOPPING_ChatGpt->getDef('text_no_match'); ?></div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Top Weighted Critics -->
    <?php if (!empty($topWeightedCritics)): ?>
    <div class="metric-card">
      <h3><i class="bi bi-trophy"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_top_weighted_critics'); ?></h3>
      <table class="data-table">
        <thead>
          <tr>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_critic_id'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_weight'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_min_weight'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_max_weight'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_weight_count'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topWeightedCritics as $critic): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($critic['critic_id']); ?></strong></td>
            <td><strong><?php echo formatWeight($critic['avg_weight']); ?></strong></td>
            <td><?php echo formatWeight($critic['min_weight']); ?></td>
            <td><?php echo formatWeight($critic['max_weight']); ?></td>
            <td><?php echo $critic['weight_count']; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Critic Domain Performance -->
    <?php if (!empty($criticDomainPerformance)): ?>
    <div class="metric-card">
      <h3><i class="bi bi-person-badge"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_critic_domain_performance'); ?></h3>
      <?php foreach (array_slice($criticDomainPerformance, 0, 10) as $performance): ?>
      <div class="mb-3 p-3" style="background: #f8f9fa; border-radius: 6px;">
        <h5><?php echo htmlspecialchars($performance['critic_id']); ?></h5>
        <div class="row">
          <?php foreach ($performance['domains'] as $domain): ?>
          <div class="col-md-4 mb-2">
            <span class="domain-badge"><?php echo htmlspecialchars($domain['domain']); ?></span>
            <br>
            <small>
              <?php echo $CLICSHOPPING_ChatGpt->getDef('text_avg_weight'); ?>: <strong><?php echo formatWeight($domain['avg_weight']); ?></strong>
              <br>
              <?php echo $CLICSHOPPING_ChatGpt->getDef('text_evaluations'); ?>: <?php echo $domain['evaluation_count']; ?>
            </small>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Weight Anomalies -->
    <?php if (!empty($weightAnomalies)): ?>
    <div class="metric-card">
      <h3><i class="bi bi-exclamation-triangle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_weight_anomalies'); ?></h3>
      <table class="data-table">
        <thead>
          <tr>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_critic_id'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_anomaly_type'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_severity'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_llm_analysis'); ?></th>
            <th><?php echo $CLICSHOPPING_ChatGpt->getDef('text_detected_at'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($weightAnomalies as $anomaly): ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($anomaly['critic_id']); ?></strong></td>
            <td><?php echo htmlspecialchars($anomaly['anomaly_type']); ?></td>
            <td>
              <span class="badge bg-<?php echo getSeverityBadgeClass($anomaly['severity']); ?>">
                <?php echo htmlspecialchars($anomaly['severity']); ?>
              </span>
            </td>
            <td><?php echo htmlspecialchars($anomaly['llm_analysis']); ?></td>
            <td><?php echo date('Y-m-d H:i', strtotime($anomaly['detected_at'])); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="metric-card">
      <h3><i class="bi bi-check-circle"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_weight_anomalies'); ?></h3>
      <div class="alert alert-success">
        <?php echo $CLICSHOPPING_ChatGpt->getDef('text_no_anomalies'); ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Export Functionality -->
    <div class="metric-card">
      <h3><i class="bi bi-download"></i> <?php echo $CLICSHOPPING_ChatGpt->getDef('text_export_metrics'); ?></h3>
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
<div class="py-4"></div>
<?php
// Include JavaScript file
use ClicShopping\OM\CLICSHOPPING;

$jsPath = CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'ext/javascript/clicshopping/ClicShoppingAdmin/Agent/adaptive_weighting.js';
echo '<script src="' . $jsPath . '"></script>';
?>
