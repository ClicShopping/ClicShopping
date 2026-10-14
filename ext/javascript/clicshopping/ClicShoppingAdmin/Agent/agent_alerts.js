/**
 * Agent Alerts Dashboard JavaScript
 * Handles loading and displaying system alerts
 */

(function() {
  'use strict';

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAgentAlerts);
  } else {
    initAgentAlerts();
  }

  function initAgentAlerts() {
    console.log('Agent Alerts: Initializing...');
    
    if (!window.AgentAlertsConfig) {
      console.log('Agent Alerts: Config not found, skipping initialization');
      return;
    }

    loadAlerts();
    setInterval(loadAlerts, 30000);
  }

  function loadAlerts() {
    console.log('Agent Alerts: Loading data...');
    
    const endpoint = window.AgentAlertsConfig.baseUrl + window.AgentAlertsConfig.alertsEndpoint;
    
    fetch(endpoint)
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('Agent Alerts: Data received', data);
        
        if (data.success) {
          updateAlertsDisplay(data.data);
        } else {
          console.error('Agent Alerts: API returned error', data.error);
          showError('Failed to load alerts: ' + (data.error || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Agent Alerts: Fetch error', error);
        showError('Failed to load alerts: ' + error.message);
      });
  }

  // Last payload kept so the detail modals can render without re-fetching
  let lastAlertsData = {};

  function updateAlertsDisplay(data) {
    console.log('Agent Alerts: Updating display');
    clearError();
    lastAlertsData = data || {};

    // Update summary cards
    if (data.summary) {
      updateElement('alert-overdue', data.summary.overdue_objectives || 0);
      updateElement('alert-systematic', data.summary.systematic_issues || 0);
      updateElement('alert-consensus', data.summary.failed_consensus || 0);
      updateElement('alert-failed', data.summary.failed_objectives || 0);
    }
    
    // Update tables
    updateOverdueTable(data.overdue_objectives || []);
    updateSystematicTable(data.systematic_issues || []);
    updateConsensusTable(data.failed_consensus || []);
    updateFailedTable(data.failed_objectives || []);
  }

  function getLabels() {
    return (window.AgentAlertsConfig && window.AgentAlertsConfig.labels) || {};
  }

  function emptyRow(colspan, message) {
    return `<tr><td colspan="${colspan}" class="text-center">${escapeHtml(message)}</td></tr>`;
  }

  function updateOverdueTable(objectives) {
    const tbody = document.getElementById('overdue-tbody');
    if (!tbody) return;

    const labels = getLabels();

    if (objectives.length === 0) {
      tbody.innerHTML = emptyRow(8, labels.no_overdue || '');
      return;
    }

    tbody.innerHTML = objectives.map(obj => `
      <tr>
        <td>${escapeHtml(obj.objective_id || '')}</td>
        <td>${escapeHtml(obj.agent_id || '')}</td>
        <td>${escapeHtml(obj.goal_statement || '')}</td>
        <td><span class="badge bg-${getPriorityClass(obj.priority)}">${escapeHtml(obj.priority || '')}</span></td>
        <td>${formatDate(obj.created_at)}</td>
        <td>${obj.estimated_completion_time ? formatDuration(obj.estimated_completion_time) : (labels.na || '')}</td>
        <td class="text-danger">${calculateOverdue(obj.created_at, obj.estimated_completion_time)}</td>
        <td>${escalateCell(obj, labels)}</td>
      </tr>
    `).join('');
  }

  // Action cell for an overdue objective.
  // 'critical' is the escalation ceiling, so an already-critical objective is
  // shown as a locked "escalation sent" state (persists across reloads) instead
  // of an actionable button — this keeps large overdue lists manageable.
  function escalateCell(obj, labels) {
    if ((obj.priority || '').toLowerCase() === 'critical') {
      return `<button class="btn btn-sm btn-secondary" disabled><i class="bi bi-check2"></i> ${escapeHtml(labels.escalation_sent || '')}</button>`;
    }
    return `<button class="btn btn-sm btn-warning" onclick="escalateObjective('${obj.objective_id}', this)"><i class="bi bi-exclamation-triangle"></i> ${escapeHtml(labels.escalate || '')}</button>`;
  }

  function updateSystematicTable(issues) {
    const tbody = document.getElementById('systematic-tbody');
    if (!tbody) return;

    const labels = getLabels();

    if (issues.length === 0) {
      tbody.innerHTML = emptyRow(7, labels.no_systematic || '');
      return;
    }

    tbody.innerHTML = issues.map(issue => `
      <tr>
        <td>${escapeHtml(issue.agent_id || '')}</td>
        <td>${issue.evaluation_count || 0}</td>
        <td>${(issue.avg_score || 0).toFixed(3)}</td>
        <td>${(issue.min_score || 0).toFixed(3)}</td>
        <td>${(issue.max_score || 0).toFixed(3)}</td>
        <td><span class="badge bg-${issue.severity === 'critical' ? 'danger' : 'warning'}">${escapeHtml(issue.severity || '')}</span></td>
        <td>
          <button class="btn btn-sm btn-info" onclick="viewAgentDetails('${issue.agent_id}')">
            <i class="bi bi-eye"></i> ${escapeHtml(labels.details || '')}
          </button>
        </td>
      </tr>
    `).join('');
  }

  function updateConsensusTable(sessions) {
    const tbody = document.getElementById('consensus-tbody');
    if (!tbody) return;

    const labels = getLabels();

    if (sessions.length === 0) {
      tbody.innerHTML = emptyRow(6, labels.no_failed_consensus || '');
      return;
    }

    tbody.innerHTML = sessions.map(session => `
      <tr>
        <td>${escapeHtml(session.session_id || '')}</td>
        <td>${escapeHtml(session.output_id || '')}</td>
        <td>${Array.isArray(session.participating_agents) ? session.participating_agents.length : 0}</td>
        <td>${formatScores(session.initial_scores)}</td>
        <td>${formatDate(session.created_at)}</td>
        <td>
          <button class="btn btn-sm btn-info" onclick="viewConsensusDetails('${session.session_id}')">
            <i class="bi bi-eye"></i> ${escapeHtml(labels.details || '')}
          </button>
        </td>
      </tr>
    `).join('');
  }

  function updateFailedTable(objectives) {
    const tbody = document.getElementById('failed-tbody');
    if (!tbody) return;

    const labels = getLabels();

    if (objectives.length === 0) {
      tbody.innerHTML = emptyRow(7, labels.no_failed_objectives || '');
      return;
    }

    tbody.innerHTML = objectives.map(obj => `
      <tr>
        <td>${escapeHtml(obj.objective_id || '')}</td>
        <td>${escapeHtml(obj.agent_id || '')}</td>
        <td>${escapeHtml(obj.goal_statement || '')}</td>
        <td><span class="badge bg-${getPriorityClass(obj.priority)}">${escapeHtml(obj.priority || '')}</span></td>
        <td>${escapeHtml(obj.failure_reason || labels.na || '')}</td>
        <td>${formatDate(obj.completed_at)}</td>
        <td>
          <button class="btn btn-sm btn-info" onclick="viewObjectiveDetails('${obj.objective_id}')">
            <i class="bi bi-eye"></i> ${escapeHtml(labels.details || '')}
          </button>
        </td>
      </tr>
    `).join('');
  }

  function calculateOverdue(createdAt, estimatedTime) {
    const labels = getLabels();
    if (!createdAt || !estimatedTime) return labels.na || '';

    const created = new Date(createdAt);
    const deadline = new Date(created.getTime() + estimatedTime * 1000);
    const now = new Date();
    const overdue = Math.floor((now - deadline) / 1000);

    if (overdue <= 0) return labels.not_overdue || '';

    return formatDuration(overdue);
  }

  function formatScores(scores) {
    if (!scores || typeof scores !== 'object') return getLabels().na || '';
    return Object.entries(scores).map(([agent, score]) => 
      `${agent.split('_').pop()}: ${score.toFixed(2)}`
    ).join(', ');
  }

  function getPriorityClass(priority) {
    const classes = {
      'critical': 'danger',
      'high': 'warning',
      'medium': 'info',
      'low': 'secondary'
    };
    return classes[priority] || 'secondary';
  }

  function formatDate(dateString) {
    if (!dateString) return getLabels().na || '';
    const date = new Date(dateString);
    return date.toLocaleString();
  }

  function formatDuration(seconds) {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    
    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${minutes}m`;
    return `${minutes}m`;
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
    console.error('Agent Alerts: Error -', message);

    // Surface the failure in the UI: a silent error is otherwise indistinguishable
    // from a legitimate "no alerts" state and looks like missing data.
    let banner = document.getElementById('alerts-error');
    if (!banner) {
      const content = document.querySelector('.contentBody');
      if (!content) return;
      banner = document.createElement('div');
      banner.id = 'alerts-error';
      banner.className = 'alert alert-danger mt-3';
      content.insertBefore(banner, content.children[1] || null);
    }
    banner.textContent = message;
    banner.style.display = 'block';
  }

  function clearError() {
    const banner = document.getElementById('alerts-error');
    if (banner) {
      banner.style.display = 'none';
    }
  }

  // Lightweight Bootstrap toast (top-right), colour-coded by type (success / danger / info)
  function showToast(message, type) {
    if (!message) return;
    type = type || 'info';

    let container = document.getElementById('agent-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'agent-toast-container';
      container.className = 'toast-container position-fixed top-0 end-0 p-3';
      container.style.zIndex = '1090';
      document.body.appendChild(container);
    }

    const toastEl = document.createElement('div');
    toastEl.className = 'toast align-items-center text-white bg-' + type + ' border-0';
    toastEl.setAttribute('role', 'alert');
    toastEl.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${escapeHtml(message)}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>`;
    container.appendChild(toastEl);

    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', function() { toastEl.remove(); });
  }

  // Global functions
  window.refreshAlerts = loadAlerts;

  // Escalate an overdue objective to critical priority via the manage endpoint.
  // The clicked button is moved through processing -> sent states so the
  // administrator always knows where the request is.
  window.escalateObjective = function(id, btn) {
    if (!window.AgentAlertsConfig || !id) return;
    const labels = getLabels();

    let original = null;
    if (btn) {
      original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> ' + escapeHtml(labels.escalating || '');
    }

    const endpoint = window.AgentAlertsConfig.baseUrl + window.AgentAlertsConfig.manageEndpoint;

    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ objective_id: id, action: 'escalate' })
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showToast(labels.escalated || '', 'success');
          if (btn) {
            btn.className = 'btn btn-sm btn-secondary';
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-check2"></i> ' + escapeHtml(labels.escalation_sent || '');
            // Reflect the new critical priority on the row
            const row = btn.closest('tr');
            if (row && row.children[3]) {
              row.children[3].innerHTML = '<span class="badge bg-danger">critical</span>';
            }
          }
        } else {
          showToast(data.error || 'Failed to escalate objective', 'danger');
          if (btn) { btn.disabled = false; btn.innerHTML = original; }
        }
      })
      .catch(error => {
        showToast(error.message, 'danger');
        if (btn) { btn.disabled = false; btn.innerHTML = original; }
      });
  };

  // --- Detail modals (rendered from the last loaded payload, no extra fetch) ---

  function renderDetailRows(rows) {
    return '<table class="table table-sm">' + rows.map(function(r) {
      return '<tr><th style="width:40%">' + escapeHtml(r[0]) + '</th><td>' + r[1] + '</td></tr>';
    }).join('') + '</table>';
  }

  function openModal(modalId, bodyId, html) {
    const body = document.getElementById(bodyId);
    if (body) body.innerHTML = html;
    const modalEl = document.getElementById(modalId);
    if (modalEl) new bootstrap.Modal(modalEl).show();
  }

  window.viewAgentDetails = function(id) {
    const labels = getLabels();
    const issue = (lastAlertsData.systematic_issues || []).find(function(i) { return i.agent_id === id; });
    if (!issue) return;
    openModal('agentDetailsModal', 'agent-details-body', renderDetailRows([
      [labels.agent || '', escapeHtml(issue.agent_id || '')],
      [labels.evaluations || '', issue.evaluation_count || 0],
      [labels.avg_score || '', (issue.avg_score || 0).toFixed(3)],
      [labels.min_score || '', (issue.min_score || 0).toFixed(3)],
      [labels.max_score || '', (issue.max_score || 0).toFixed(3)],
      [labels.severity || '', '<span class="badge bg-' + (issue.severity === 'critical' ? 'danger' : 'warning') + '">' + escapeHtml(issue.severity || '') + '</span>']
    ]));
  };

  window.viewConsensusDetails = function(id) {
    const labels = getLabels();
    const session = (lastAlertsData.failed_consensus || []).find(function(s) { return String(s.session_id) === String(id); });
    if (!session) return;
    openModal('consensusDetailsModal', 'consensus-details-body', renderDetailRows([
      [labels.session_id || '', escapeHtml(String(session.session_id || ''))],
      [labels.output_id || '', escapeHtml(session.output_id || '')],
      [labels.participants || '', Array.isArray(session.participating_agents) ? session.participating_agents.length : 0],
      [labels.created || '', formatDate(session.created_at)]
    ]));
  };

  window.viewObjectiveDetails = function(id) {
    const labels = getLabels();
    const obj = (lastAlertsData.failed_objectives || []).find(function(o) { return o.objective_id === id; });
    if (!obj) return;
    openModal('alertObjectiveDetailsModal', 'alert-objective-details-body', renderDetailRows([
      [labels.objective_id || '', escapeHtml(obj.objective_id || '')],
      [labels.agent || '', escapeHtml(obj.agent_id || '')],
      [labels.goal || '', escapeHtml(obj.goal_statement || '')],
      [labels.priority || '', '<span class="badge bg-' + getPriorityClass(obj.priority) + '">' + escapeHtml(obj.priority || '') + '</span>'],
      [labels.failure_reason || '', escapeHtml(obj.failure_reason || labels.na || '')],
      [labels.failed_at || '', formatDate(obj.completed_at)]
    ]));
  };

  console.log('Agent Alerts: Script loaded');
})();
