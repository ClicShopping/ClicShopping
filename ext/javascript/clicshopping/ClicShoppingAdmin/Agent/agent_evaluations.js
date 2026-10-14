/**
 * Agent Evaluations Dashboard JavaScript
 * Handles loading and displaying agent evaluation data
 */

(function() {
  'use strict';

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAgentEvaluations);
  } else {
    initAgentEvaluations();
  }

  function initAgentEvaluations() {
    console.log('Agent Evaluations: Initializing...');
    
    if (!window.AgentEvaluationsConfig) {
      console.log('Agent Evaluations: Config not found, skipping initialization');
      return;
    }

    loadEvaluationStats();
    loadEvaluations();
    setInterval(function() {
      loadEvaluationStats();
      loadEvaluations();
    }, 30000);
    
    // Setup filter listeners
    setupFilters();
  }

  function setupFilters() {
    const filterBtn = document.getElementById('apply-filters');
    if (filterBtn) {
      filterBtn.addEventListener('click', loadEvaluations);
    }
  }

  function loadEvaluations() {
    console.log('Agent Evaluations: Loading data...');
    
    const endpoint = window.AgentEvaluationsConfig.baseUrl + window.AgentEvaluationsConfig.evaluationsEndpoint;
    
    // Get filter values
    const params = new URLSearchParams();
    const criticFilter = document.getElementById('critic-filter');
    const producerFilter = document.getElementById('producer-filter');
    const periodFilter = document.getElementById('period-filter');
    
    if (criticFilter && criticFilter.value) params.append('critic_id', criticFilter.value);
    if (producerFilter && producerFilter.value) params.append('producer_id', producerFilter.value);
    if (periodFilter && periodFilter.value) params.append('period', periodFilter.value);
    
    const url = params.toString() ? `${endpoint}?${params}` : endpoint;
    
    fetch(url)
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('Agent Evaluations: Data received', data);
        
        if (data.success) {
          updateEvaluationsDisplay(data.data);
        } else {
          console.error('Agent Evaluations: API returned error', data.error);
          showError('Failed to load evaluations: ' + (data.error || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Agent Evaluations: Fetch error', error);
        showError('Failed to load evaluations: ' + error.message);
      });
  }

  function loadEvaluationStats() {
    if (!window.AgentEvaluationsConfig) {
      return;
    }

    const endpoint = window.AgentEvaluationsConfig.baseUrl + window.AgentEvaluationsConfig.statsEndpoint;

    fetch(endpoint)
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          const stats = data.data || {};
          updateSummaryFromStats(stats);
          updateScoreDistributionFromStats(stats);
        }
      })
      .catch(error => {
        console.error('Agent Evaluations: Stats fetch error', error);
      });
  }

  function updateEvaluationsDisplay(data) {
    console.log('Agent Evaluations: Updating display');
    
    const evaluations = data.evaluations || [];
    
    // Calculate summary from evaluations if not provided
    const summary = data.summary || calculateSummary(evaluations);
    
    // Update summary stats
    updateSummaryStats(summary);
    
    // Update evaluations table
    updateEvaluationsTable(evaluations);
    
    // Update score distribution (Tab 2)
    updateScoreDistribution(evaluations);
    
    // Update consensus sessions (Tab 3) - always load from separate endpoint
    loadConsensusData();
  }

  function updateSummaryFromStats(stats) {
    const total = stats.total_evaluations || 0;
    const avgOverall = stats.average_scores ? (stats.average_scores.overall || 0) : 0;
    const consensusStats = stats.consensus_stats || {};
    const consensusSessions = consensusStats.total_sessions || 0;
    const consensusRate = consensusStats.consensus_rate || 0;

    updateElement('stat-total-evaluations', total);
    updateElement('stat-avg-score', avgOverall.toFixed(3));
    updateElement('stat-consensus-sessions', consensusSessions);
    updateElement('stat-consensus-rate', consensusRate.toFixed(1) + '%');
  }

  function updateScoreDistributionFromStats(stats) {
    if (!stats || !stats.average_scores) {
      return;
    }

    const averages = {
      accuracy: stats.average_scores.accuracy || 0,
      completeness: stats.average_scores.completeness || 0,
      efficiency: stats.average_scores.efficiency || 0,
      clarity: stats.average_scores.clarity || 0,
      overall: stats.average_scores.overall || 0
    };

    updateScoreChart(averages);

    const dimensions = [
      ['Accuracy', averages.accuracy],
      ['Completeness', averages.completeness],
      ['Efficiency', averages.efficiency],
      ['Clarity', averages.clarity],
      ['Overall', averages.overall]
    ];

    const tbody = document.getElementById('avg-scores-tbody');
    if (tbody) {
      tbody.innerHTML = dimensions.map(function([label, avg]) {
        const percentage = (avg * 100).toFixed(1);
        return `
        <tr>
          <td>${label}</td>
          <td>${avg.toFixed(3)}</td>
          <td>
            <div class="progress" style="height: 20px;">
              <div class="progress-bar bg-${getScoreBadgeClass(avg)}"
                   role="progressbar"
                   style="width: ${percentage}%"
                   aria-valuenow="${percentage}"
                   aria-valuemin="0"
                   aria-valuemax="100">
                ${percentage}%
              </div>
            </div>
          </td>
        </tr>
        `;
      }).join('');
    }
  }

  function calculateSummary(evaluations) {
    if (evaluations.length === 0) {
      return {
        total: 0,
        avg_score: 0,
        consensus_sessions: 0,
        consensus_rate: 0
      };
    }
    
    let totalScore = 0;
    
    evaluations.forEach(function(evaluation) {
      const score = evaluation.overall_score || 0;
      totalScore += score;
    });
    
    return {
      total: evaluations.length,
      avg_score: totalScore / evaluations.length,
      consensus_sessions: 0, // Would need separate endpoint
      consensus_rate: 0 // Would need separate endpoint
    };
  }

  function updateSummaryStats(summary) {
    updateElement('stat-total-evaluations', summary.total || 0);
    updateElement('stat-avg-score', (summary.avg_score || 0).toFixed(3));
    updateElement('stat-consensus-sessions', summary.consensus_sessions || 0);
    updateElement('stat-consensus-rate', ((summary.consensus_rate || 0) * 100).toFixed(1) + '%');
  }

  function updateEvaluationsTable(evaluations) {
    const tbody = document.getElementById('evaluations-tbody');
    if (!tbody) {
      console.warn('Agent Evaluations: Table body not found');
      return;
    }

    if (evaluations.length === 0) {
      tbody.innerHTML = '<tr><td colspan="10" class="text-center">No evaluations found</td></tr>';
      return;
    }

    tbody.innerHTML = evaluations.map(function(evaluation) {
      return `
      <tr>
        <td>${escapeHtml(evaluation.critic_id || '')}</td>
        <td>${escapeHtml(evaluation.producer_agent_id || 'N/A')}</td>
        <td>${escapeHtml(evaluation.output_type || 'N/A')}</td>
        <td><span class="badge bg-${getScoreBadgeClass(evaluation.overall_score)}">${(evaluation.overall_score || 0).toFixed(2)}</span></td>
        <td>${(evaluation.accuracy_score || 0).toFixed(2)}</td>
        <td>${(evaluation.completeness_score || 0).toFixed(2)}</td>
        <td>${(evaluation.efficiency_score || 0).toFixed(2)}</td>
        <td>${(evaluation.clarity_score || 0).toFixed(2)}</td>
        <td>${formatDate(evaluation.evaluated_at)}</td>
        <td>
          <button class="btn btn-sm btn-info" onclick="viewEvaluationDetails('${evaluation.evaluation_id}')">
            <i class="bi bi-eye"></i>
          </button>
        </td>
      </tr>
      `;
    }).join('');
  }

  function updateScoreChart(distribution) {
    // Chart.js integration for score distribution
    const canvas = document.getElementById('score-distribution-chart');
    if (!canvas) {
      console.warn('Agent Evaluations: Chart canvas not found');
      return;
    }
    
    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
      console.warn('Agent Evaluations: Chart.js not loaded');
      canvas.parentElement.innerHTML = '<p class="text-center text-muted">Chart.js not available</p>';
      return;
    }
    
    // Destroy existing chart if any
    if (window.evaluationScoreChart) {
      window.evaluationScoreChart.destroy();
    }
    
    // Create new chart
    const ctx = canvas.getContext('2d');
    window.evaluationScoreChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Accuracy', 'Completeness', 'Efficiency', 'Clarity', 'Overall'],
        datasets: [{
          label: 'Average Score',
          data: [
            distribution.accuracy || 0,
            distribution.completeness || 0,
            distribution.efficiency || 0,
            distribution.clarity || 0,
            distribution.overall || 0
          ],
          backgroundColor: [
            'rgba(54, 162, 235, 0.5)',
            'rgba(75, 192, 192, 0.5)',
            'rgba(255, 206, 86, 0.5)',
            'rgba(153, 102, 255, 0.5)',
            'rgba(255, 99, 132, 0.5)'
          ],
          borderColor: [
            'rgba(54, 162, 235, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(255, 206, 86, 1)',
            'rgba(153, 102, 255, 1)',
            'rgba(255, 99, 132, 1)'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        scales: {
          y: {
            beginAtZero: true,
            max: 1,
            ticks: {
              callback: function(value) {
                return (value * 100).toFixed(0) + '%';
              }
            }
          }
        },
        plugins: {
          legend: {
            display: false
          },
          title: {
            display: true,
            text: 'Average Scores by Dimension'
          }
        }
      }
    });
  }

  function updateScoreDistribution(evaluations) {
    console.log('Agent Evaluations: Updating score distribution');
    
    if (evaluations.length === 0) {
      return;
    }
    
    // Calculate average scores by dimension
    const dimensions = {
      'Accuracy': { total: 0, count: 0, key: 'accuracy_score' },
      'Completeness': { total: 0, count: 0, key: 'completeness_score' },
      'Efficiency': { total: 0, count: 0, key: 'efficiency_score' },
      'Clarity': { total: 0, count: 0, key: 'clarity_score' },
      'Overall': { total: 0, count: 0, key: 'overall_score' }
    };
    
    evaluations.forEach(function(evaluation) {
      Object.keys(dimensions).forEach(function(dim) {
        const key = dimensions[dim].key;
        if (evaluation[key] !== undefined) {
          dimensions[dim].total += evaluation[key];
          dimensions[dim].count++;
        }
      });
    });
    
    // Calculate averages
    const averages = {};
    Object.keys(dimensions).forEach(function(dim) {
      averages[dim.toLowerCase()] = dimensions[dim].count > 0 
        ? (dimensions[dim].total / dimensions[dim].count) 
        : 0;
    });
    
    // Update chart
    updateScoreChart(averages);
    
    // Update average scores table
    const tbody = document.getElementById('avg-scores-tbody');
    if (tbody) {
      tbody.innerHTML = Object.keys(dimensions).map(function(dim) {
        const avg = dimensions[dim].count > 0 
          ? (dimensions[dim].total / dimensions[dim].count) 
          : 0;
        const percentage = (avg * 100).toFixed(1);
        
        return `
        <tr>
          <td>${dim}</td>
          <td>${avg.toFixed(3)}</td>
          <td>
            <div class="progress" style="height: 20px;">
              <div class="progress-bar bg-${getScoreBadgeClass(avg)}" 
                   role="progressbar" 
                   style="width: ${percentage}%"
                   aria-valuenow="${percentage}" 
                   aria-valuemin="0" 
                   aria-valuemax="100">
                ${percentage}%
              </div>
            </div>
          </td>
        </tr>
        `;
      }).join('');
    }
  }

  function updateConsensusSessions(consensusSessions) {
    console.log('Agent Evaluations: Updating consensus sessions', consensusSessions.length);
    
    const tbody = document.getElementById('consensus-tbody');
    if (!tbody) {
      console.warn('Agent Evaluations: Consensus tbody not found');
      return;
    }
    
    if (consensusSessions.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center">No consensus sessions found</td></tr>';
      return;
    }
    
    tbody.innerHTML = consensusSessions.map(function(session) {
      const diffPercent = (session.difference_percent || (session.difference * 100)).toFixed(2);
      const diffClass = Math.abs(session.difference) > 0.05 ? 'text-warning' : 'text-success';
      
      return `
      <tr>
        <td>${session.id}</td>
        <td>${escapeHtml(session.evaluation_id || '').substring(0, 20)}...</td>
        <td>${(session.dynamic_consensus || 0).toFixed(3)}</td>
        <td>${(session.static_consensus || 0).toFixed(3)}</td>
        <td class="${diffClass}">${(session.difference || 0).toFixed(3)}</td>
        <td class="${diffClass}">${diffPercent}%</td>
        <td>${formatDate(session.created_at)}</td>
      </tr>
      `;
    }).join('');
  }

  function loadConsensusData() {
    console.log('Agent Evaluations: Loading consensus data...');
    
    if (!window.AgentEvaluationsConfig) {
      console.error('Agent Evaluations: Config not found!');
      return;
    }
    
    const baseUrl = window.AgentEvaluationsConfig.baseUrl;
    const endpoint = baseUrl + (baseUrl.endsWith('/') ? '' : '/') + 'ajax/Agent/get_agent_consensus.php';
    
    console.log('Agent Evaluations: Consensus endpoint URL:', endpoint);
    
    fetch(endpoint)
      .then(response => {
        console.log('Agent Evaluations: Consensus response status:', response.status);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('Agent Evaluations: Consensus data received:', data);
        if (data.success) {
          const sessions = data.data || [];
          console.log('Agent Evaluations: Consensus sessions count:', sessions.length);
          
          // Update consensus sessions display
          updateConsensusSessions(sessions);
          
          // Update consensus statistics
          updateConsensusStats(sessions);
        } else {
          console.error('Agent Evaluations: Consensus API error:', data.error);
          const tbody = document.getElementById('consensus-tbody');
          if (tbody) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No consensus sessions found</td></tr>';
          }
        }
      })
      .catch(error => {
        console.error('Agent Evaluations: Failed to load consensus data', error);
        const tbody = document.getElementById('consensus-tbody');
        if (tbody) {
          tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load consensus data: ' + error.message + '</td></tr>';
        }
      });
  }

  function updateConsensusStats(sessions) {
    console.log('Agent Evaluations: Updating consensus stats');
    
    // Update consensus sessions count
    updateElement('stat-consensus-sessions', sessions.length);
    
    // Calculate consensus rate (sessions where difference is small)
    if (sessions.length > 0) {
      const successfulConsensus = sessions.filter(s => Math.abs(s.difference) < 0.05).length;
      const consensusRate = successfulConsensus / sessions.length;
      updateElement('stat-consensus-rate', (consensusRate * 100).toFixed(1) + '%');
    }
  }

  function getStatusBadgeClass(status) {
    const statusLower = (status || '').toLowerCase();
    if (statusLower === 'reached' || statusLower === 'completed') return 'success';
    if (statusLower === 'failed') return 'danger';
    if (statusLower === 'pending' || statusLower === 'in_progress') return 'warning';
    return 'secondary';
  }

  function getScoreBadgeClass(score) {
    if (score >= 0.8) return 'success';
    if (score >= 0.6) return 'info';
    if (score >= 0.4) return 'warning';
    return 'danger';
  }

  function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString();
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function updateElement(id, value) {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = value;
    }
  }

  function showError(message) {
    console.error('Agent Evaluations: Error -', message);
  }

  // Global functions
  window.viewEvaluationDetails = function(evaluationId) {
    console.log('Viewing evaluation:', evaluationId);
    
    // Find the evaluation in the current data
    const endpoint = window.AgentEvaluationsConfig.baseUrl + 
                    (window.AgentEvaluationsConfig.baseUrl.endsWith('/') ? '' : '/') +
                    'ajax/Agent/get_agent_evaluations.php?evaluation_id=' + evaluationId;
    
    fetch(endpoint)
      .then(response => response.json())
      .then(data => {
        if (data.success && data.data.evaluations.length > 0) {
          const evaluation = data.data.evaluations[0];
          showEvaluationModal(evaluation);
        } else {
          alert('Évaluation non trouvée');
        }
      })
      .catch(error => {
        console.error('Error loading evaluation details:', error);
        alert('Erreur lors du chargement des détails');
      });
  };

  function showEvaluationModal(evaluation) {
    const modalBody = document.getElementById('evaluation-details-body');
    if (!modalBody) {
      console.error('Modal body not found');
      return;
    }
    
    modalBody.innerHTML = `
      <div class="row">
        <div class="col-md-6">
          <h5>Informations Générales</h5>
          <table class="table table-sm">
            <tr><th>ID:</th><td>${escapeHtml(evaluation.evaluation_id)}</td></tr>
            <tr><th>Critique:</th><td>${escapeHtml(evaluation.critic_id)}</td></tr>
            <tr><th>Producteur:</th><td>${escapeHtml(evaluation.producer_agent_id || 'N/A')}</td></tr>
            <tr><th>Type:</th><td>${escapeHtml(evaluation.output_type)}</td></tr>
            <tr><th>Date:</th><td>${formatDate(evaluation.evaluated_at)}</td></tr>
          </table>
        </div>
        <div class="col-md-6">
          <h5>Scores</h5>
          <table class="table table-sm">
            <tr><th>Overall:</th><td><span class="badge bg-${getScoreBadgeClass(evaluation.overall_score)}">${(evaluation.overall_score || 0).toFixed(3)}</span></td></tr>
            <tr><th>Accuracy:</th><td>${(evaluation.accuracy_score || 0).toFixed(3)}</td></tr>
            <tr><th>Completeness:</th><td>${(evaluation.completeness_score || 0).toFixed(3)}</td></tr>
            <tr><th>Efficiency:</th><td>${(evaluation.efficiency_score || 0).toFixed(3)}</td></tr>
            <tr><th>Clarity:</th><td>${(evaluation.clarity_score || 0).toFixed(3)}</td></tr>
          </table>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-md-12">
          <h5>Feedback</h5>
          <p>${escapeHtml(evaluation.feedback || 'Aucun feedback')}</p>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-md-6">
          <h5>Points Forts</h5>
          <ul>
            ${(evaluation.strengths || []).map(s => `<li>${escapeHtml(s)}</li>`).join('') || '<li>Aucun</li>'}
          </ul>
        </div>
        <div class="col-md-6">
          <h5>Améliorations</h5>
          <ul>
            ${(evaluation.improvements || []).map(i => `<li>${escapeHtml(i)}</li>`).join('') || '<li>Aucune</li>'}
          </ul>
        </div>
      </div>
    `;
    
    // Show the modal using Bootstrap
    const modal = new bootstrap.Modal(document.getElementById('evaluationDetailsModal'));
    modal.show();
  }

  window.exportEvaluations = function() {
    console.log('Exporting evaluations...');
    
    // Get current evaluations data
    const endpoint = window.AgentEvaluationsConfig.baseUrl + 
                    (window.AgentEvaluationsConfig.baseUrl.endsWith('/') ? '' : '/') +
                    'ajax/Agent/get_agent_evaluations.php?limit=1000';
    
    fetch(endpoint)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          exportToCSV(data.data.evaluations);
        } else {
          alert('Erreur lors de l\'export');
        }
      })
      .catch(error => {
        console.error('Error exporting:', error);
        alert('Erreur lors de l\'export');
      });
  };

  function exportToCSV(evaluations) {
    // Create CSV content
    const headers = ['ID', 'Critic', 'Producer', 'Type', 'Overall', 'Accuracy', 'Completeness', 'Efficiency', 'Clarity', 'Date'];
    const rows = evaluations.map(e => [
      e.evaluation_id,
      e.critic_id,
      e.producer_agent_id || '',
      e.output_type,
      e.overall_score,
      e.accuracy_score,
      e.completeness_score,
      e.efficiency_score,
      e.clarity_score,
      e.evaluated_at
    ]);
    
    let csv = headers.join(',') + '\n';
    rows.forEach(row => {
      csv += row.map(cell => `"${cell}"`).join(',') + '\n';
    });
    
    // Download
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'agent_evaluations_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
  }

  window.refreshData = function() {
    console.log('Agent Evaluations: Refresh button clicked');
    // Reload all data
    loadEvaluations();
    loadConsensusData();
    alert('Données actualisées!');
  };

  console.log('Agent Evaluations: Script loaded');
  console.log('Agent Evaluations: refreshData function available:', typeof window.refreshData);
})();
