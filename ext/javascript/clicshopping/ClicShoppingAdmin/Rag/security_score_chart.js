let securityChartInstance = null;
function createSecurityChart() {
  console.log('🔍 createSecurityChart called');
  const ctx = document.getElementById('securityChart');

  if (!ctx) {
    console.error('❌ securityChart canvas not found!');
    return;
  }

  console.log('✅ securityChart canvas found');


  console.log('📊 Security data:', { score: securityScore });

  // Détruire l'ancien chart s'il existe
  if (securityChartInstance) {
    securityChartInstance.destroy();
  }

  try {
    securityChartInstance = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Score Sécurité'],
        datasets: [{
          label: 'Score',
          data: [securityScore],
          backgroundColor: ['#667eea'],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        scales: {
          y: {
            beginAtZero: true,
            max: 100
          }
        },
        plugins: {
          legend: {
            display: false
          }
        }
      }
    });
    console.log('✅ Security chart created successfully');
  } catch (error) {
    console.error('❌ Error creating security chart:', error);
  }
}