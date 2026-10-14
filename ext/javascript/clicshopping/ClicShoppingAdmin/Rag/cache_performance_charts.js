// ====================================================================
// CACHE PERFORMANCE CHARTS
// ====================================================================

let cachePerformanceCharts = {
  hitMiss: null,
  costSavings: null,
  responseTime: null,
  cacheSize: null
};

function destroyCachePerformanceCharts() {
  Object.keys(cachePerformanceCharts).forEach(key => {
    const chart = cachePerformanceCharts[key];
    if (chart) {
      chart.destroy();
      cachePerformanceCharts[key] = null;
    }
  });
}

function renderCachePerformanceEmptyState(canvas, message) {
  if (!canvas || !canvas.parentElement) {
    return;
  }
  canvas.parentElement.innerHTML = `<p class="text-muted text-center">${message}</p>`;
}

function buildCachePerformanceCharts(data) {
  const hitMissCanvas = document.getElementById('cacheHitMissChart');
  const costCanvas = document.getElementById('cacheCostSavingsChart');
  const responseCanvas = document.getElementById('cacheResponseTimeChart');
  const sizeCanvas = document.getElementById('cacheSizeChart');

  if (!hitMissCanvas || !costCanvas || !responseCanvas || !sizeCanvas) {
    console.warn('Cache performance charts: canvas elements not found');
    return;
  }

  destroyCachePerformanceCharts();

  const hitMiss = data.hit_miss || {};
  if (!hitMiss.labels || hitMiss.labels.length === 0) {
    renderCachePerformanceEmptyState(hitMissCanvas, 'No cache hit/miss data available');
  } else {
    cachePerformanceCharts.hitMiss = new Chart(hitMissCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: hitMiss.labels,
        datasets: [
          {
            label: 'Hits',
            data: hitMiss.hits || [],
            borderColor: '#4caf50',
            backgroundColor: 'rgba(76, 175, 80, 0.15)',
            tension: 0.35,
            fill: true
          },
          {
            label: 'Misses',
            data: hitMiss.misses || [],
            borderColor: '#f44336',
            backgroundColor: 'rgba(244, 67, 54, 0.15)',
            tension: 0.35,
            fill: true
          },
          {
            label: 'Hit Rate (%)',
            data: hitMiss.hit_rate || [],
            borderColor: '#1976d2',
            backgroundColor: 'rgba(25, 118, 210, 0.1)',
            tension: 0.35,
            fill: false,
            yAxisID: 'yRate'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true
          },
          yRate: {
            beginAtZero: true,
            max: 100,
            position: 'right',
            grid: { drawOnChartArea: false },
            ticks: { callback: value => value + '%' }
          }
        },
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });
  }

  const costSavings = data.cost_savings || {};
  if (!costSavings.labels || costSavings.labels.length === 0) {
    renderCachePerformanceEmptyState(costCanvas, 'No cache cost data available');
  } else {
    cachePerformanceCharts.costSavings = new Chart(costCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: costSavings.labels,
        datasets: [
          {
            label: 'Cost Saved (USD)',
            data: costSavings.cost_saved || [],
            borderColor: '#43a047',
            backgroundColor: 'rgba(67, 160, 71, 0.15)',
            tension: 0.35,
            fill: true
          },
          {
            label: 'Cost Spent (USD)',
            data: costSavings.cost_spent || [],
            borderColor: '#ff9800',
            backgroundColor: 'rgba(255, 152, 0, 0.15)',
            tension: 0.35,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.dataset.label}: $${Number(ctx.parsed.y || 0).toFixed(4)}`
            }
          }
        }
      }
    });
  }

  const responseTime = data.response_time || {};
  if (!responseTime.labels || responseTime.labels.length === 0) {
    renderCachePerformanceEmptyState(responseCanvas, 'No response time data available');
  } else {
    cachePerformanceCharts.responseTime = new Chart(responseCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: responseTime.labels,
        datasets: [
          {
            label: 'Cached (ms)',
            data: responseTime.cached || [],
            borderColor: '#009688',
            backgroundColor: 'rgba(0, 150, 136, 0.15)',
            tension: 0.35,
            fill: true
          },
          {
            label: 'Uncached (ms)',
            data: responseTime.uncached || [],
            borderColor: '#e91e63',
            backgroundColor: 'rgba(233, 30, 99, 0.15)',
            tension: 0.35,
            fill: true
          },
          {
            label: 'Average (ms)',
            data: responseTime.average || [],
            borderColor: '#3f51b5',
            backgroundColor: 'rgba(63, 81, 181, 0.1)',
            tension: 0.35,
            fill: false
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });
  }

  const cacheSize = data.cache_size || {};
  if (!cacheSize.labels || cacheSize.labels.length === 0) {
    renderCachePerformanceEmptyState(sizeCanvas, 'No cache size data available');
  } else {
    cachePerformanceCharts.cacheSize = new Chart(sizeCanvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels: cacheSize.labels,
        datasets: [
          {
            label: 'Size (MB)',
            data: cacheSize.sizes || [],
            backgroundColor: 'rgba(96, 125, 139, 0.6)',
            borderColor: 'rgba(96, 125, 139, 1)',
            borderWidth: 1
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.dataset.label}: ${Number(ctx.parsed.y || 0).toFixed(2)} MB`
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true
          }
        }
      }
    });
  }
}

function loadCachePerformanceCharts() {
  const url = window.APP_DATA?.ajax?.cachePerformanceUrl || '';

  if (!url) {
    console.error('Cache performance URL not defined in APP_DATA');
    return;
  }

  if (typeof Chart === 'undefined') {
    console.error('Chart.js is not available for cache performance charts');
    return;
  }

  fetch(url)
    .then(response => response.json())
    .then(payload => {
      if (!payload.success) {
        console.error('Cache performance charts failed:', payload.error);
        return;
      }

      buildCachePerformanceCharts(payload.data || {});
    })
    .catch(error => console.error('Cache performance charts error:', error));
}

document.addEventListener('DOMContentLoaded', () => {
  loadCachePerformanceCharts();
});
