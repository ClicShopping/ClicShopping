(function() {
  'use strict';

  var config = window.SourceDistributionConfig;
  if (!config) return;

  var sourceCtx = document.getElementById('sourceDistributionChart');
  if (!sourceCtx) return;

  new Chart(sourceCtx.getContext('2d'), {
    type: 'pie',
    data: {
      labels: config.labels,
      datasets: [{
        data: config.data,
        backgroundColor: [
          'rgba(54, 162, 235, 0.8)',
          'rgba(75, 192, 192, 0.8)',
          'rgba(255, 159, 64, 0.8)',
          'rgba(153, 102, 255, 0.8)',
          'rgba(255, 99, 132, 0.8)',
          'rgba(255, 206, 86, 0.8)',
          'rgba(201, 203, 207, 0.8)'
        ],
        borderWidth: 2,
        borderColor: '#fff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            padding: 10,
            font: {
              size: 11
            }
          }
        },
        tooltip: {
          callbacks: {
            label: function(context) {
              var label = context.label || '';
              var value = context.parsed || 0;
              var total = config.totalQueries;
              var percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
              return label + ': ' + value + ' (' + percentage + '%)';
            }
          }
        }
      }
    }
  });
})();
