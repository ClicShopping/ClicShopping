/**
 * Latency Charts - Chart.js Implementation
 * TASK 4.4.2.3: Visualisation temps réel des métriques de latence
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 * 
 * Date: 2025-12-05
 */

document.addEventListener('DOMContentLoaded', function() {
  // Check if we're on the latency tab
  const latencyTab = document.getElementById('tab_latency');
  if (!latencyTab) {
    console.log('Latency tab not found, skipping chart initialization');
    return;
  }

  // Get latency metrics from PHP data
  const latencyMetrics = window.APP_DATA?.latencyMetrics;
  
  if (!latencyMetrics || latencyMetrics.overall.count === 0) {
    console.log('No latency metrics available');
    return;
  }

  // ============================================================================
  // CHART 1: Latency Comparison Bar Chart
  // ============================================================================
  const latencyComparisonCtx = document.getElementById('latencyComparisonChart');
  if (latencyComparisonCtx) {
    new Chart(latencyComparisonCtx, {
      type: 'bar',
      data: {
        labels: ['Fast-Lane', 'Full Orchestration', 'Overall'],
        datasets: [{
          label: 'Mean Latency (ms)',
          data: [
            latencyMetrics.fast_lane.mean,
            latencyMetrics.full_orchestration.mean,
            latencyMetrics.overall.mean
          ],
          backgroundColor: [
            'rgba(76, 175, 80, 0.7)',   // Green for fast-lane
            'rgba(255, 152, 0, 0.7)',   // Orange for full orchestration
            'rgba(33, 150, 243, 0.7)'   // Blue for overall
          ],
          borderColor: [
            'rgba(76, 175, 80, 1)',
            'rgba(255, 152, 0, 1)',
            'rgba(33, 150, 243, 1)'
          ],
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: {
            display: true,
            text: 'Comparaison des Latences Moyennes',
            font: { size: 16, weight: 'bold' }
          },
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return context.parsed.y.toFixed(2) + ' ms';
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Latence (ms)'
            },
            ticks: {
              callback: function(value) {
                return value.toFixed(0) + ' ms';
              }
            }
          }
        }
      }
    });
  }

  // ============================================================================
  // CHART 2: Percentiles Line Chart
  // ============================================================================
  const percentilesCtx = document.getElementById('percentilesChart');
  if (percentilesCtx) {
    new Chart(percentilesCtx, {
      type: 'line',
      data: {
        labels: ['P50', 'P75', 'P90', 'P95', 'P99'],
        datasets: [
          {
            label: 'Fast-Lane',
            data: [
              latencyMetrics.fast_lane.percentiles.p50,
              latencyMetrics.fast_lane.percentiles.p75,
              latencyMetrics.fast_lane.percentiles.p90,
              latencyMetrics.fast_lane.percentiles.p95,
              latencyMetrics.fast_lane.percentiles.p99
            ],
            borderColor: 'rgba(76, 175, 80, 1)',
            backgroundColor: 'rgba(76, 175, 80, 0.1)',
            tension: 0.4,
            fill: true
          },
          {
            label: 'Full Orchestration',
            data: [
              latencyMetrics.full_orchestration.percentiles.p50,
              latencyMetrics.full_orchestration.percentiles.p75,
              latencyMetrics.full_orchestration.percentiles.p90,
              latencyMetrics.full_orchestration.percentiles.p95,
              latencyMetrics.full_orchestration.percentiles.p99
            ],
            borderColor: 'rgba(255, 152, 0, 1)',
            backgroundColor: 'rgba(255, 152, 0, 0.1)',
            tension: 0.4,
            fill: true
          },
          {
            label: 'Overall',
            data: [
              latencyMetrics.overall.percentiles.p50,
              latencyMetrics.overall.percentiles.p75,
              latencyMetrics.overall.percentiles.p90,
              latencyMetrics.overall.percentiles.p95,
              latencyMetrics.overall.percentiles.p99
            ],
            borderColor: 'rgba(33, 150, 243, 1)',
            backgroundColor: 'rgba(33, 150, 243, 0.1)',
            tension: 0.4,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: {
            display: true,
            text: 'Distribution des Percentiles',
            font: { size: 16, weight: 'bold' }
          },
          legend: {
            display: true,
            position: 'top'
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return context.dataset.label + ': ' + context.parsed.y.toFixed(2) + ' ms';
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Latence (ms)'
            },
            ticks: {
              callback: function(value) {
                return value.toFixed(0) + ' ms';
              }
            }
          }
        }
      }
    });
  }

  // ============================================================================
  // CHART 3: Query Distribution Pie Chart
  // ============================================================================
  const queryDistributionCtx = document.getElementById('queryDistributionChart');
  if (queryDistributionCtx) {
    const fastLaneCount = latencyMetrics.fast_lane.count;
    const fullOrchestrationCount = latencyMetrics.full_orchestration.count;
    
    new Chart(queryDistributionCtx, {
      type: 'doughnut',
      data: {
        labels: ['Fast-Lane', 'Full Orchestration'],
        datasets: [{
          data: [fastLaneCount, fullOrchestrationCount],
          backgroundColor: [
            'rgba(76, 175, 80, 0.8)',
            'rgba(255, 152, 0, 0.8)'
          ],
          borderColor: [
            'rgba(76, 175, 80, 1)',
            'rgba(255, 152, 0, 1)'
          ],
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: {
            display: true,
            text: 'Répartition des Requêtes',
            font: { size: 16, weight: 'bold' }
          },
          legend: {
            display: true,
            position: 'bottom'
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((context.parsed / total) * 100).toFixed(1);
                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
              }
            }
          }
        }
      }
    });
  }

  // ============================================================================
  // CHART 4: Efficiency Gauge Chart (using doughnut as gauge)
  // ============================================================================
  const efficiencyGaugeCtx = document.getElementById('efficiencyGaugeChart');
  if (efficiencyGaugeCtx) {
    const speedupFactor = latencyMetrics.fast_lane_efficiency.speedup_factor;
    const maxSpeedup = 10; // Maximum expected speedup
    const percentage = Math.min((speedupFactor / maxSpeedup) * 100, 100);
    
    new Chart(efficiencyGaugeCtx, {
      type: 'doughnut',
      data: {
        datasets: [{
          data: [percentage, 100 - percentage],
          backgroundColor: [
            percentage > 75 ? 'rgba(76, 175, 80, 0.8)' : 
            percentage > 50 ? 'rgba(255, 193, 7, 0.8)' : 
            'rgba(244, 67, 54, 0.8)',
            'rgba(224, 224, 224, 0.3)'
          ],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        circumference: 180,
        rotation: 270,
        cutout: '75%',
        plugins: {
          title: {
            display: true,
            text: 'Facteur d\'Accélération',
            font: { size: 16, weight: 'bold' }
          },
          legend: {
            display: false
          },
          tooltip: {
            enabled: false
          }
        }
      },
      plugins: [{
        id: 'gaugeText',
        afterDraw: function(chart) {
          const ctx = chart.ctx;
          const centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
          const centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2 + 20;
          
          ctx.save();
          ctx.font = 'bold 32px Arial';
          ctx.fillStyle = '#333';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillText(speedupFactor.toFixed(2) + 'x', centerX, centerY);
          ctx.restore();
        }
      }]
    });
  }

  console.log('✅ Latency charts initialized successfully');
});
