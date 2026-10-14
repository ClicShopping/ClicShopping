// ====================================================================
// PERFORMANCE CHART
// ====================================================================

document.addEventListener('DOMContentLoaded', () => {
  console.log('🔍 Performance Chart: Loading...');
  
  const perfCtx = document.getElementById('performanceChart')?.getContext('2d');
  if (!perfCtx) {
    console.warn('⚠️ Performance Chart: Canvas not found');
    return;
  }

  const systemReport = window.APP_DATA?.systemReport || {};
  console.log('📊 Performance Chart: System Report Data:', systemReport);

  const analyticsSuccess = parseFloat((systemReport.analytics?.success_rate || '0').replace('%', '')) || 0;
  const semanticSuccess = parseFloat((systemReport.semantic?.success_rate || '0').replace('%', '')) || 0;
  const hybridSuccess = parseFloat((systemReport.hybrid?.success_rate || '0').replace('%', '')) || 0;
  const orchestratorSuccess = parseFloat((systemReport.orchestrator?.success_rate || '0').replace('%', '')) || 0;
  const websearchSuccess = parseFloat((systemReport.websearch?.success_rate || '0').replace('%', '')) || 0;
  const cacheSuccess = systemReport.cache?.average_quality_score
    ? Math.round(systemReport.cache.average_quality_score * 1000) / 10
    : 0;

  console.log('📈 Performance Chart: Data Points:', {
    analytics: analyticsSuccess,
    semantic: semanticSuccess,
    hybrid: hybridSuccess,
    orchestrator: orchestratorSuccess,
    websearch: websearchSuccess,
    cache: cacheSuccess
  });

  new Chart(perfCtx, {
    type: 'line',
    data: {
      labels: ['Analytics', 'Semantic', 'Hybrid', 'Orchestrator', 'WebSearch', 'Cache RAG'],
      datasets: [{
        label: 'Taux de Succès / Efficacité (%)',
        data: [analyticsSuccess, semanticSuccess, hybridSuccess, orchestratorSuccess, websearchSuccess, cacheSuccess],
        borderColor: 'rgb(102, 126, 234)',
        backgroundColor: 'rgba(102, 126, 234, 0.1)',
        tension: 0.4,
        fill: true,
        pointRadius: 5,
        pointHoverRadius: 7,
        pointBackgroundColor: 'rgb(102, 126, 234)',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: { callback: value => value + '%' }
        }
      },
      plugins: {
        legend: {
          display: true,
          labels: { usePointStyle: true, padding: 15 }
        },
        tooltip: {
          backgroundColor: 'rgba(0,0,0,0.8)',
          titleFont: { size: 13 },
          bodyFont: { size: 13 },
          padding: 12,
          callbacks: {
            label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y.toFixed(1)}%`
          }
        }
      }
    }
  });
  
  console.log('✅ Performance Chart: Created successfully with WebSearch data');
});
