/**
 * ChatGPT Dashboard Token Charts
 * Centralises Chart.js instantiation for every canvas that declares the
 * `chatgpt-token-chart` class and exposes its configuration via the
 * `data-chart-config` attribute.
 */
(function () {
  function initTokenCharts() {
    if (typeof Chart === 'undefined') {
      console.warn('Chart.js is required for token charts but was not found.');
      return;
    }

    document.querySelectorAll('.chatgpt-token-chart').forEach(function (canvas) {
      if (!canvas || canvas.dataset.chartInitialized === 'true') {
        return;
      }

      var configRaw = canvas.getAttribute('data-chart-config');
      if (!configRaw) {
        return;
      }

      var parsedConfig;
      try {
        parsedConfig = JSON.parse(configRaw);
      } catch (error) {
        console.error('Unable to parse chart config for', canvas.id, error);
        return;
      }

      var context = canvas.getContext('2d');
      if (!context) {
        return;
      }

      try {
        new Chart(context, parsedConfig);
        canvas.dataset.chartInitialized = 'true';
      } catch (chartError) {
        console.error('Failed to render token chart for', canvas.id, chartError);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTokenCharts);
  } else {
    initTokenCharts();
  }
})();

