// ====================================================================
// TOKEN DISTRIBUTION CHART
// ====================================================================

let tokenDistributionChartInstance = null;

function createTokenDistributionChart() {
  console.log('🔍 createTokenDistributionChart called');
  
  // Don't recreate if already exists
  if (tokenDistributionChartInstance) {
    console.log('ℹ️ Token distribution chart already exists, skipping');
    return;
  }
  
  const canvas = document.getElementById('tokenDistributionChart');
  
  if (!canvas) {
    console.warn('⚠️ Token distribution chart: Canvas not found (tab may not be visible yet)');
    return;
  }

  console.log('✅ Canvas found:', canvas);

  if (!window.APP_DATA || !window.APP_DATA.tokenDashboardStats) {
    console.error('❌ Token distribution chart: window.APP_DATA.tokenDashboardStats is undefined');
    return;
  }

  const tokenStats = window.APP_DATA.tokenDashboardStats;
  const inputTokens = tokenStats.input_tokens || 0;
  const outputTokens = tokenStats.output_tokens || 0;

  console.log('📊 Creating token distribution chart with data:', { 
    inputTokens, 
    outputTokens, 
    total: inputTokens + outputTokens 
  });

  // Only create chart if there's data
  if (inputTokens > 0 || outputTokens > 0) {
    const ctx = canvas.getContext('2d');
    
    tokenDistributionChartInstance = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Input Tokens', 'Output Tokens'],
        datasets: [{
          data: [inputTokens, outputTokens],
          backgroundColor: [
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 99, 132, 0.8)'
          ],
          borderColor: [
            'rgb(54, 162, 235)',
            'rgb(255, 99, 132)'
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
              label: ctx => {
                const total = inputTokens + outputTokens;
                const percentage = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0;
                return `${ctx.label}: ${ctx.parsed.toLocaleString()} (${percentage}%)`;
              }
            }
          }
        }
      }
    });
    console.log('✅ Token distribution chart created successfully');
  } else {
    console.warn('⚠️ Token distribution chart: No data available');
    canvas.parentElement.innerHTML = '<p class="text-muted text-center">No token data available</p>';
  }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  console.log('📄 DOM Content Loaded - token_distribution.js');
  
  // Try to initialize immediately (in case tab5 is active)
  setTimeout(() => {
    console.log('⏱️ Attempting initial token distribution chart creation');
    createTokenDistributionChart();
  }, 100);
  
  // Listen for tab changes to initialize when tab5 becomes visible
  const tab5Link = document.querySelector('a[href="#tab5"]');
  if (tab5Link) {
    console.log('✅ Found tab5 link, attaching event listener');
    tab5Link.addEventListener('shown.bs.tab', function(e) {
      console.log('🎯 Tab5 shown event fired!', e);
      setTimeout(() => {
        createTokenDistributionChart();
      }, 50);
    });
  } else {
    console.warn('⚠️ Tab5 link not found in DOM');
  }
});
