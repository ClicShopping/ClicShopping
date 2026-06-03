<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 * Shared dashboard scripts partial: APP_DATA injection + chart bundle.
 * Charts whose canvas is absent on the current metier dashboard simply do not render.
 */
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
?>
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
