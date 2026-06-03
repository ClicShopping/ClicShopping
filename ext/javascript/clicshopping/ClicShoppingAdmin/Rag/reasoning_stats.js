// ====================================================================
// REASONING AGENT STATS (Data Scientist dashboard)
// Fetches persistent reasoning-mode statistics and renders the panel.
// ====================================================================
document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  var cfg = window.ReasoningStatsConfig;
  if (!cfg || !cfg.url || !document.getElementById('reasoning-stats-content')) {
    return;
  }

  var periodSelect = document.getElementById('reasoning-period');

  function setText(id, value) {
    var el = document.getElementById(id);
    if (el) {
      el.textContent = (value === undefined || value === null) ? '--' : value;
    }
  }

  function load(days) {
    var sep = cfg.url.indexOf('?') > -1 ? '&' : '?';
    fetch(cfg.url + sep + 'period=' + encodeURIComponent(days))
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.success) {
          console.error('Reasoning stats:', res.error);
          return;
        }
        var d = res.data || {};

        setText('reasoning-total', d.total_reasonings);
        setText('reasoning-success-rate', d.success_rate);
        setText('reasoning-failed', d.failed_reasonings);
        setText('reasoning-avg-steps', d.avg_steps);

        var tbody = document.getElementById('reasoning-by-mode');
        if (tbody && d.by_mode) {
          tbody.innerHTML = '';
          Object.keys(d.by_mode).forEach(function (mode) {
            var m = d.by_mode[mode] || {};
            var tr = document.createElement('tr');
            var label = mode.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            tr.innerHTML =
              '<td>' + label + '</td>' +
              '<td class="text-end">' + (m.count || 0) + '</td>' +
              '<td class="text-end">' + (m.successful || 0) + '</td>' +
              '<td class="text-end">' + (m.failed || 0) + '</td>' +
              '<td class="text-end">' + (m.avg_confidence || 0) + '</td>';
            tbody.appendChild(tr);
          });
        }
      })
      .catch(function (e) { console.error('Reasoning stats error:', e); });
  }

  load(periodSelect ? periodSelect.value : 30);

  if (periodSelect) {
    periodSelect.addEventListener('change', function () { load(this.value); });
  }
});
