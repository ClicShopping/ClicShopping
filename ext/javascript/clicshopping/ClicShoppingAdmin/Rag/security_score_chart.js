let securityChartInstance = null;
function createSecurityChart() {
  const ctx = document.getElementById('securityChart');

  // Absent canvas is the NORMAL case: this bundle is shared by the three metier dashboards, and
  // the canvas only exists in the legacy security branch. Not an error, so it must not shout.
  if (!ctx) {
    return;
  }



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
  } catch (error) {
    console.error('❌ Error creating security chart:', error);
  }
}