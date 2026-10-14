document.addEventListener('DOMContentLoaded', function() {
  let performanceChart;
  const timeRangeElement = document.getElementById('timeRange');
  const mcpServerElement = document.getElementById('mcpServer');
  let eventSource;
  const thrErrorEl = document.getElementById('thrError');
  const thrLatencyEl = document.getElementById('thrLatency');
  const thrDowntimeEl = document.getElementById('thrDowntime');
  const applyBtn = document.getElementById('applyThresholds');
  
  // Debug: Log button elements
  console.log('Performance Monitor initialized');
  console.log('Apply button found:', applyBtn !== null);
  console.log('Threshold inputs found:', {
    error: thrErrorEl !== null,
    latency: thrLatencyEl !== null,
    downtime: thrDowntimeEl !== null
  });

  function buildParams() {
    const range = timeRangeElement ? timeRangeElement.value : '24h';
    const mcpId = mcpServerElement ? mcpServerElement.value : 'all';
    const params = new URLSearchParams({ range, token: mcpToken, mcp_id: mcpId });
    if (thrErrorEl && thrErrorEl.value) params.set('threshold_error', thrErrorEl.value);
    if (thrLatencyEl && thrLatencyEl.value) params.set('threshold_latency', thrLatencyEl.value);
    if (thrDowntimeEl && thrDowntimeEl.value) params.set('threshold_downtime', thrDowntimeEl.value);
    return params.toString();
  }

  function initEventSource() {
    if (eventSource) eventSource.close();
    if (!GetPerformanceData) {
      showError('Missing performance endpoint. Please reload the page.');
      return;
    }
    if (!mcpToken) {
      showError('Missing MCP token. Please re-login to the admin.');
      return;
    }
    eventSource = new EventSource(GetPerformanceData + '?' + buildParams());
    
    eventSource.onmessage = function(event) {
      const data = JSON.parse(event.data);
      updateUI(data);
    };
    
    eventSource.addEventListener('server_not_running', function(event) {
      const data = JSON.parse(event.data);
      showServerNotRunningWarning(data);
      eventSource.close();
    });
    
    eventSource.addEventListener('error', function(event) {
      try {
        const data = JSON.parse(event.data);
        if (data.error) {
          showServerNotRunningWarning(data);
          eventSource.close();
        }
      } catch (e) {
        // Generic error handling
        console.error('EventSource error:', event);
      }
    });
    
    eventSource.onerror = function(error) {
      console.error('EventSource failed:', error);
      showError('Performance stream error. Check server logs or re-login.');
      eventSource.close();
      // Don't auto-retry if server is not running
      // User needs to start the server first
    };
  }
  
  function showError(message) {
    const container = document.getElementById('performanceMetrics');
    if (container) {
      container.innerHTML = `<div class="alert alert-danger" role="alert">${message}</div>`;
    }
    const recommendations = document.getElementById('recommendations');
    if (recommendations) {
      recommendations.innerHTML = `<div class="alert alert-info">${message}</div>`;
    }
  }

  function showServerNotRunningWarning(data) {
    // Clear existing content
    const container = document.getElementById('performanceMetrics');
    if (!container) return;
    
    // Create warning message
    const warningHTML = `
      <div class="alert alert-warning" role="alert">
        <h4 class="alert-heading"><i class="bi bi-exclamation-triangle"></i> ${data.error || 'MCP Server Not Running'}</h4>
        <p>${data.message || 'The MCP server is not running.'}</p>
        ${data.instructions ? `
          <hr>
          <p class="mb-0"><strong>To start monitoring:</strong></p>
          <ol class="mb-0">
            ${data.instructions.map(instruction => `<li>${instruction}</li>`).join('')}
          </ol>
        ` : ''}
        ${data.config ? `
          <hr>
          <p class="mb-0"><small><strong>Configuration:</strong> ${data.config.host}:${data.config.port}</small></p>
        ` : ''}
      </div>
    `;
    
    // Show warning in all metric areas
    if (container) container.innerHTML = warningHTML;
    
    const recommendations = document.getElementById('recommendations');
    if (recommendations) {
      recommendations.innerHTML = `
        <div class="alert alert-info">
          <strong>Note:</strong> Once you start the MCP server, refresh this page to begin monitoring.
        </div>
      `;
    }
    
    // Update stats to show N/A
    const stats = ['avgLatencyStat', 'maxLatencyStat', 'totalRequestsStat', 'dataPointsStat'];
    stats.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.textContent = 'N/A';
    });
  }

  function updateUI(data) {
    const elements = {
      requestRate: document.getElementById('requestRate'),
      avgLatency: document.getElementById('avgLatency'),
      errorRate: document.getElementById('errorRate'),
      uptime: document.getElementById('uptime')
    };
    if (elements.requestRate) elements.requestRate.textContent = data.metrics.request_rate.toFixed(2) + ' req/min';
    if (elements.avgLatency) elements.avgLatency.textContent = data.metrics.average_latency.toFixed(2) + ' ms';
    if (elements.errorRate) elements.errorRate.textContent = data.metrics.error_frequency.toFixed(2) + '%';
    if (elements.uptime) elements.uptime.textContent = data.metrics.uptime_percentage.toFixed(2) + '%';
    updateTrend('latencyTrend', data.trends.latency_trend);
    updateTrend('errorTrend', data.trends.error_trend);
    updateTrend('requestTrend', data.trends.request_trend);
    updateRecommendations(data.recommendations);
    updateChart(data.history);

    // Update statistics if available
    if (data.statistics) {
      updateStatistics(data.statistics);
    }
  }

  function updateStatistics(stats) {
    const elements = {
      avgLatencyStat: document.getElementById('avgLatencyStat'),
      maxLatencyStat: document.getElementById('maxLatencyStat'),
      totalRequestsStat: document.getElementById('totalRequestsStat'),
      dataPointsStat: document.getElementById('dataPointsStat')
    };

    if (elements.avgLatencyStat) elements.avgLatencyStat.textContent = stats.avg_latency + ' ms';
    if (elements.maxLatencyStat) elements.maxLatencyStat.textContent = stats.max_latency + ' ms';
    if (elements.totalRequestsStat) elements.totalRequestsStat.textContent = stats.total_requests.toLocaleString();
    if (elements.dataPointsStat) elements.dataPointsStat.textContent = stats.data_points;
  }

  function updateRecommendations(recommendations) {
    const container = document.getElementById('recommendations');
    if (!container) return;
    container.innerHTML = '';
    recommendations.forEach(rec => {
      const item = document.createElement('div');
      item.className = `list-group-item list-group-item-${rec.type} d-flex justify-content-between align-items-center`;
      item.innerHTML = `
                                  <div>
                                    <strong>${rec.priority.toUpperCase()}</strong>: ${rec.message}
                                  </div>
                                  <span class="badge badge-${rec.type} rounded-pill">${rec.type}</span>
                                `;
      container.appendChild(item);
    });
  }

  function updateTrend(elementId, trend) {
    const element = document.getElementById(elementId);
    const percentage = Math.min(100, Math.abs(trend.percentage));
    element.style.width = percentage + '%';
    element.className = `progress-bar ${getTrendClass(trend.direction)}`;
    element.setAttribute('aria-valuenow', percentage);
    element.setAttribute('title', `${trend.direction} by ${trend.percentage.toFixed(2)}%`);
  }

  function getTrendClass(direction) {
    switch(direction) {
      case 'increasing': return 'bg-warning';
      case 'decreasing': return 'bg-success';
      default: return 'bg-info';
    }
  }

  function updateChart(history) {
    const canvas = document.getElementById('performanceChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    if (performanceChart) {
      performanceChart.destroy();
      ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    const historyArray = Array.isArray(history) ? history.sort((a, b) => a.timestamp - b.timestamp) : Object.values(history || {}).sort((a, b) => a.timestamp - b.timestamp);
// config Chart ici
    performanceChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: historyArray.map(h => new Date(h.timestamp * 1000).toLocaleTimeString()),
        datasets: [
          {
            label: 'Latency (ms)',
            data: historyArray.map(h => h.latency),
            borderColor: 'rgb(75, 192, 192)',
            yAxisID: 'y1',
            tension: 0.1
          },
          {
            label: 'Error Rate (%)',
            data: historyArray.map(h => h.error_rate),
            borderColor: 'rgb(255, 99, 132)',
            yAxisID: 'y2',
            tension: 0.1
          },
          {
            label: 'Requests/min',
            data: historyArray.map(h => h.requests),
            borderColor: 'rgb(54, 162, 235)',
            yAxisID: 'y3',
            tension: 0.1
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y1: { type: 'linear', position: 'left', beginAtZero: true },
          y2: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } },
          y3: { type: 'linear', position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } }
        }
      }
    });
  }


  timeRangeElement.addEventListener('change', function() {
    initEventSource();
  });

  if (mcpServerElement) {
    mcpServerElement.addEventListener('change', function() {
      initEventSource();
    });
  }

  if (applyBtn) {
    applyBtn.addEventListener('click', function(e) {
      e.preventDefault(); // Prevent any default action
      console.log('Apply Thresholds clicked');
      console.log('Error threshold:', thrErrorEl ? thrErrorEl.value : 'N/A');
      console.log('Latency threshold:', thrLatencyEl ? thrLatencyEl.value : 'N/A');
      console.log('Downtime threshold:', thrDowntimeEl ? thrDowntimeEl.value : 'N/A');
      initEventSource();
    });
  } else {
    console.error('applyThresholds button not found!');
  }

  // Export data functionality
  const exportBtn = document.getElementById('exportData');
  if (exportBtn) {
    exportBtn.addEventListener('click', function() {
      const range = timeRangeElement ? timeRangeElement.value : '24h';
      const exportUrl = ExportPerformanceData;
      const params = new URLSearchParams({
        range: range,
        token: '<?php echo $mcp_token; ?>',
        format: 'json'
      });

      // Create download link
      const link = document.createElement('a');
      link.href = exportUrl + '?' + params.toString();
      link.download = `mcp_performance_${range}_${new Date().toISOString().split('T')[0]}.json`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    });
  }

  // Do not auto-start monitoring to avoid long-lived SSE connections by default.
  // The user can trigger it by changing filters or applying thresholds.
});
