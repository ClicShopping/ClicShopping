(function() {
  'use strict';

  var config = window.ExportMetricsConfig;
  if (!config) return;

  window.exportMetrics = function(format) {
    window.location.href = config.baseUrl + 'ajax/ChatGpt/export_actor_critic_metrics.php?format=' + format;
  };
})();
