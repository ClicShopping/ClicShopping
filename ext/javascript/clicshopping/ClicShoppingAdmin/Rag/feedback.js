// ====================================================================
// FEEDBACK DISTRIBUTION CHART
// ====================================================================

let feedbackDistributionChartInstance = null;

function createFeedbackDistributionChart() {
  const canvas = document.getElementById('feedbackDistributionChart');
  if (!canvas) {
    console.warn('Feedback distribution chart: Canvas not found');
    return;
  }

  // Destroy existing chart instance if it exists
  if (feedbackDistributionChartInstance) {
    feedbackDistributionChartInstance.destroy();
    feedbackDistributionChartInstance = null;
  }

  const ctx = canvas.getContext('2d');
  const feedbackStats = window.APP_DATA?.feedbackStats || {};
  const positive = feedbackStats.positive || 0;
  const negative = feedbackStats.negative || 0;
  const neutral = feedbackStats.neutral || 0;
  const total = positive + negative + neutral;

  if (total === 0) {
    canvas.parentElement.innerHTML = '<p class="text-muted text-center">No feedback data available</p>';
    return;
  }

  feedbackDistributionChartInstance = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Positifs', 'Négatifs', 'Neutres'],
      datasets: [{
        data: [positive, negative, neutral],
        backgroundColor: ['#43e97b', '#f5576c', '#ffd700'],
        borderWidth: 2,
        borderColor: '#fff'
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom' },
        tooltip: {
          callbacks: {
            label: ctx => {
              const pct = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0;
              return `${ctx.label}: ${ctx.parsed} (${pct}%)`;
            }
          }
        }
      }
    }
  });
  console.log('✅ Feedback distribution chart created successfully');
}



// ====================================================================
// ANALYZE FEEDBACKS VIA AI
// ====================================================================

function analyzeFeedbacks(type = 'all') {
  const resultDiv = document.getElementById('aiAnalysisResult');
  const loadingDiv = document.getElementById('aiAnalysisLoading');
  const contentDiv = document.getElementById('aiAnalysisContent');

  resultDiv.style.display = 'block';
  loadingDiv.style.display = 'block';
  contentDiv.innerHTML = '';

  const url = window.APP_DATA?.ajax?.analyzeFeedbacksUrl || '';
  if (!url) {
    console.error('Analyze feedbacks URL not defined in APP_DATA');
    loadingDiv.style.display = 'none';
    return;
  }

  fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ type: type, period_days: 30 })
  })
    .then(response => response.json())
    .then(data => {
      loadingDiv.style.display = 'none';
      if (data.success) {
        contentDiv.innerHTML =
          `<div class="alert alert-success">
            <h6>🤖 Analyse IA - ${data.type_label}</h6>
            <p><strong>Feedbacks analysés:</strong> ${data.feedbacks_analyzed}</p>
            <pre style="white-space: pre-wrap;">${data.full_analysis}</pre>
          </div>`;
      } else {
        contentDiv.innerHTML = `<div class="alert alert-danger">Erreur: ${data.error}</div>`;
      }
    })
    .catch(() => {
      loadingDiv.style.display = 'none';
      contentDiv.innerHTML = '<div class="alert alert-warning">Fonctionnalité en cours de développement</div>';
    });
}

// ====================================================================
// LOAD RECENT FEEDBACKS
// ====================================================================

function loadRecentFeedbacks() {
  const feedbackList = document.getElementById('feedbackList');
  if (!feedbackList) return;

  const url = window.APP_DATA?.ajax?.getFeedbacksUrl || '';
  if (!url) {
    console.error('Get feedbacks URL not defined in APP_DATA');
    feedbackList.innerHTML = '<p class="text-danger">Erreur de configuration</p>';
    return;
  }

  fetch(url)
    .then(response => response.json())
    .then(data => {
      if (data.success && Array.isArray(data.feedbacks) && data.feedbacks.length > 0) {
        let html = '<div class="list-group">';
        data.feedbacks.forEach(feedback => {
          const typeIcon = feedback.type === 'positive' ? '👍' : (feedback.type === 'negative' ? '👎' : '😐');
          const typeClass = feedback.type === 'positive' ? 'success' : (feedback.type === 'negative' ? 'danger' : 'secondary');

          html += '<div class="list-group-item">';
          html += '<div class="d-flex justify-content-between align-items-start">';
          html += '<div class="flex-grow-1">';
          html += `<h6>${typeIcon} <span class="badge bg-${typeClass}">${feedback.type}</span></h6>`;
          
          // Display full question (not truncated)
          const displayQuestion = feedback.question || '[Question non disponible]';
          html += `<p class="mb-1"><strong>Q:</strong> ${displayQuestion}</p>`;
          
          // Display corrected text for negative feedback
          if (feedback.type === 'negative' && feedback.corrected_text) {
            html += `<p class="mb-1"><strong>✏️ Correction:</strong> <span class="text-primary">${feedback.corrected_text}</span></p>`;
          }
          
          // Display comment if present
          if (feedback.comment) {
            html += `<p class="mb-1 text-muted"><em>💬 ${feedback.comment}</em></p>`;
          }
          
          // Display rating if present
          if (feedback.rating) {
            html += `<p class="mb-0"><strong>Note:</strong> ${feedback.rating}/5 ⭐</p>`;
          }
          
          html += '</div>';
          html += `<small class="text-muted">${feedback.date}</small>`;
          html += '</div></div>';
        });
        html += '</div>';
        feedbackList.innerHTML = html;
      } else {
        feedbackList.innerHTML = '<p class="text-muted">Aucun feedback récent</p>';
      }
    })
    .catch(() => {
      feedbackList.innerHTML = '<p class="text-danger">Erreur de chargement</p>';
    });
}

// ====================================================================
// INITIALIZE CHARTS AND FEEDBACKS ON DOM CONTENT LOADED
// ====================================================================

document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') {
    console.error('❌ Chart.js is not loaded!');
  }

  setTimeout(() => {
    createSecurityChart?.();
    createAgentsChart?.();
    createFeedbackDistributionChart?.();
    initSeverityChart?.();
  }, 500);

  loadRecentFeedbacks();

  const tabLinks = document.querySelectorAll('a[data-bs-toggle="tab"]');
  tabLinks.forEach(link => {
    link.addEventListener('shown.bs.tab', e => {
      const target = e.target.getAttribute('href');

      switch (target) {
        case '#tab6':
          setTimeout(() => {
            initSeverityChart?.();
            createAgentsChart?.();
          }, 100);
          break;
        case '#tab7':
          setTimeout(createSecurityChart, 100);
          break;
        case '#tab10':
          setTimeout(() => {
            loadCacheStats?.();
          }, 100);
          break;
        case '#tab11':
          setTimeout(() => {
            createFeedbackDistributionChart?.();
            loadRecentFeedbacks();
          }, 100);
          break;
      }
    });
  });
});
