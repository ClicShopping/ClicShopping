// ====================================================================
// ALERT SEVERITY DISTRIBUTION
// ====================================================================

console.log('🚀 severity_distribution.js file loaded!');

let severityChartInstance = null;

function initSeverityChart() {
  console.log('🔍 initSeverityChart called');
  
  // Don't recreate if already exists
  if (severityChartInstance) {
    console.log('ℹ️ Severity chart already exists, skipping');
    return;
  }

  const canvas = document.getElementById('alertSeverityChart');
  
  if (!canvas) {
    console.warn('⚠️ Severity chart: Canvas #alertSeverityChart not found (tab may not be visible yet)');
    return;
  }

  console.log('✅ Canvas found:', canvas);

  if (!window.APP_DATA) {
    console.error('❌ Severity chart: window.APP_DATA is undefined');
    return;
  }

  if (!window.APP_DATA.globalStats) {
    console.error('❌ Severity chart: window.APP_DATA.globalStats is undefined');
    console.log('Available APP_DATA keys:', Object.keys(window.APP_DATA));
    return;
  }

  const globalStats = window.APP_DATA.globalStats;
  const successCount = globalStats.success || 0;
  const errorCount = globalStats.errors || 0;

  console.log('📊 Creating severity chart with data:', { 
    successCount, 
    errorCount, 
    total: successCount + errorCount,
    globalStats 
  });

  // Only create chart if there's data
  if (successCount > 0 || errorCount > 0) {
    const severityCtx = canvas.getContext('2d');
    
    severityChartInstance = new Chart(severityCtx, {
      type: 'doughnut',
      data: {
        labels: ['Success', 'Errors'],
        datasets: [{
          data: [successCount, errorCount],
          backgroundColor: [
            'rgba(16, 185, 129, 0.8)',
            'rgba(239, 68, 68, 0.8)'
          ],
          borderColor: [
            'rgb(16, 185, 129)',
            'rgb(239, 68, 68)'
          ],
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { padding: 15, usePointStyle: true }
          },
          tooltip: {
            callbacks: {
              label: ctx => `${ctx.label}: ${ctx.parsed} request${ctx.parsed > 1 ? 's' : ''}`
            }
          }
        }
      }
    });
    console.log('✅ Severity chart created successfully with instance:', severityChartInstance);
  } else {
    console.warn('⚠️ Severity chart: No data available (success=0, errors=0)');
    canvas.parentElement.innerHTML = '<p class="text-muted text-center">No data available</p>';
  }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  console.log('📄 DOM Content Loaded - severity_distribution.js');
  
  // Try to initialize immediately (in case tab6 is active)
  setTimeout(() => {
    console.log('⏱️ Attempting initial chart creation after 100ms delay');
    initSeverityChart();
  }, 100);
  
  // Listen for tab changes to initialize when tab6 becomes visible
  const tab6Link = document.querySelector('a[href="#tab6"]');
  if (tab6Link) {
    console.log('✅ Found tab6 link, attaching event listener');
    tab6Link.addEventListener('shown.bs.tab', function(e) {
      console.log('🎯 Tab6 shown event fired!', e);
      setTimeout(() => {
        initSeverityChart();
      }, 50);
    });
  } else {
    console.warn('⚠️ Tab6 link not found in DOM');
  }
});
