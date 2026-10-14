/**
 * Agent Objectives Dashboard JavaScript
 * Handles loading and displaying agent objectives data
 */

(function() {
  'use strict';

  let allObjectives = [];

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAgentObjectives);
  } else {
    initAgentObjectives();
  }

  function initAgentObjectives() {
    console.log('Agent Objectives: Initializing...');
    
    // Check if we're on the objectives page
    if (!window.AgentObjectivesConfig) {
      console.log('Agent Objectives: Config not found, skipping initialization');
      return;
    }

    loadObjectives();
    setupActionConfirm();

    // Refresh every 30 seconds
    setInterval(loadObjectives, 30000);
  }

  // Currently selected objective + action awaiting confirmation
  let pendingAction = null;

  function setupActionConfirm() {
    const btn = document.getElementById('action-confirm-btn');
    if (btn && !btn.dataset.bound) {
      btn.addEventListener('click', executePendingAction);
      btn.dataset.bound = '1';
    }
  }

  // Opens the confirmation modal for a status transition (called from the table buttons)
  window.manageObjective = function(objectiveId, action, needsReason) {
    const labels = getLabels();
    pendingAction = { objectiveId: objectiveId, action: action };

    const titleEl = document.getElementById('action-confirm-title');
    const msgEl = document.getElementById('action-confirm-message');
    const reasonContainer = document.getElementById('action-reason-container');
    const reasonInput = document.getElementById('action-reason');

    if (titleEl) {
      titleEl.textContent = ((labels.confirm_title_prefix || '') + ' ' + actionLabel(action)).trim();
    }
    if (msgEl) {
      msgEl.textContent = [labels.confirm_message_prefix, actionLabel(action).toLowerCase(), labels.confirm_message_suffix]
        .filter(Boolean).join(' ').trim();
    }
    if (reasonInput) {
      reasonInput.value = '';
    }
    if (reasonContainer) {
      reasonContainer.style.display = needsReason ? 'block' : 'none';
    }

    const modal = new bootstrap.Modal(document.getElementById('actionConfirmModal'));
    modal.show();
  };

  function executePendingAction() {
    if (!pendingAction) {
      return;
    }

    const labels = getLabels();
    const reasonInput = document.getElementById('action-reason');
    const reason = reasonInput ? reasonInput.value.trim() : '';
    const endpoint = window.AgentObjectivesConfig.baseUrl + window.AgentObjectivesConfig.manageEndpoint;

    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        objective_id: pendingAction.objectiveId,
        action: pendingAction.action,
        reason: reason
      })
    })
      .then(response => response.json())
      .then(data => {
        const modalEl = document.getElementById('actionConfirmModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) {
          modal.hide();
        }

        if (data.success) {
          showToast(labels.action_success || '', 'success');
          loadObjectives();
        } else {
          showToast((labels.error_prefix || '') + (data.error || ''), 'danger');
        }
        pendingAction = null;
      })
      .catch(error => {
        console.error('Agent Objectives: Manage action error', error);
        showToast((labels.error_prefix || '') + error.message, 'danger');
        pendingAction = null;
      });
  }

  function getFilters() {
    return {
      agent_id: (document.getElementById('filter-agent')?.value || '').trim(),
      status: (document.getElementById('filter-status')?.value || '').trim(),
      priority: (document.getElementById('filter-priority')?.value || '').trim(),
    };
  }

  function loadObjectives(filters = null) {
    console.log('Agent Objectives: Loading data...');
    
    const endpoint = window.AgentObjectivesConfig.baseUrl + window.AgentObjectivesConfig.objectivesEndpoint;
    const url = new URL(endpoint, window.location.origin);
    const activeFilters = filters || null;
    if (activeFilters) {
      Object.keys(activeFilters).forEach(key => {
        if (activeFilters[key]) {
          url.searchParams.set(key, activeFilters[key]);
        }
      });
    }
    
    fetch(url.toString())
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        console.log('Agent Objectives: Data received', data);
        
        if (data.success) {
          updateObjectivesDisplay(data.data);
        } else {
          console.error('Agent Objectives: API returned error', data.error);
          showError('Failed to load objectives: ' + (data.error || 'Unknown error'));
        }
      })
      .catch(error => {
        console.error('Agent Objectives: Fetch error', error);
        showError('Failed to load objectives: ' + error.message);
      });
  }

  function updateObjectivesDisplay(data) {
    console.log('Agent Objectives: Updating display with', data);
    
    const objectives = data.objectives || [];
    allObjectives = objectives;
    
    // Calculate summary from objectives if not provided
    const summary = data.summary || calculateSummary(objectives);
    
    // Update summary cards
    updateSummaryCards(summary);
    
    // Update single objectives table with all objectives
    updateObjectivesTable(allObjectives);
  }

  function calculateSummary(objectives) {
    const summary = {
      total: objectives.length,
      active: 0,
      completed: 0,
      failed: 0,
      pending: 0
    };
    
    objectives.forEach(obj => {
      const status = obj.status || '';
      if (summary.hasOwnProperty(status)) {
        summary[status]++;
      }
    });
    
    return summary;
  }

  function updateSummaryCards(summary) {
    const cards = {
      'total-objectives': summary.total || 0,
      'active-objectives': summary.active || 0,
      'completed-objectives': summary.completed || 0,
      'failed-objectives': summary.failed || 0
    };

    Object.keys(cards).forEach(id => {
      const element = document.getElementById(id);
      if (element) {
        element.textContent = cards[id];
      }
    });
  }

  function updateObjectivesTable(objectives) {
    const tbody = document.getElementById('objectives-tbody');
    if (!tbody) {
      console.warn('Agent Objectives: Table body not found');
      return;
    }

    if (objectives.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center">${escapeHtml(getLabels().no_objectives || '')}</td></tr>`;
      return;
    }

    tbody.innerHTML = objectives.map(obj => `
      <tr>
        <td>${escapeHtml(obj.objective_id || '').substring(0, 12)}...</td>
        <td>${escapeHtml(obj.agent_id || '')}</td>
        <td>${escapeHtml(obj.goal_statement || '')}</td>
        <td><span class="badge bg-${getPriorityClass(obj.priority)}">${escapeHtml(obj.priority || '')}</span></td>
        <td><span class="badge bg-${getStatusClass(obj.status)}">${escapeHtml(obj.status || '')}</span></td>
        <td>${formatDate(obj.created_at)}</td>
        <td>${obj.estimated_completion_time ? formatDuration(obj.estimated_completion_time) : '—'}</td>
        <td>${buildActionButtons(obj)}</td>
      </tr>
    `).join('');
  }

  // Allowed manual transitions per current status
  const ACTIONS_BY_STATUS = {
    pending:   ['approve', 'cancel'],
    approved:  ['activate', 'cancel'],
    active:    ['complete', 'fail', 'cancel'],
    failed:    ['retry'],
    cancelled: ['retry'],
    completed: []
  };

  // Visual + behavioural metadata for each action (reason: requires a justification)
  const ACTION_META = {
    approve:  { class: 'success', icon: 'check-circle',    reason: false },
    activate: { class: 'primary', icon: 'play-circle',     reason: false },
    complete: { class: 'success', icon: 'check2-all',      reason: false },
    fail:     { class: 'danger',  icon: 'x-circle',        reason: true  },
    cancel:   { class: 'warning', icon: 'slash-circle',    reason: true  },
    retry:    { class: 'info',    icon: 'arrow-clockwise', reason: false }
  };

  function getLabels() {
    return (window.AgentObjectivesConfig && window.AgentObjectivesConfig.labels) || {};
  }

  function actionLabel(action) {
    const labels = getLabels();
    const map = labels.action_labels || {};
    return map[action] || labels[action] || action;
  }

  function buildActionButtons(obj) {
    const labels = getLabels();
    let html = `<button class="btn btn-sm btn-info mb-1" onclick="viewObjectiveDetails('${obj.objective_id}')"><i class="bi bi-eye"></i></button>`;
    const actions = ACTIONS_BY_STATUS[(obj.status || '').toLowerCase()] || [];

    actions.forEach(function(action) {
      const meta = ACTION_META[action];
      if (!meta) return;
      html += ` <button class="btn btn-sm btn-${meta.class} mb-1" onclick="manageObjective('${obj.objective_id}','${action}',${meta.reason})"><i class="bi bi-${meta.icon}"></i> ${escapeHtml(actionLabel(action))}</button>`;
    });

    if (actions.length === 0) {
      html += ` <span class="text-muted small">${escapeHtml(labels.no_actions || '')}</span>`;
    }

    return html;
  }

  function applyFiltersToObjectives(objectives) {
    const agentFilter = (document.getElementById('filter-agent')?.value || '').trim().toLowerCase();
    const statusFilter = (document.getElementById('filter-status')?.value || '').trim().toLowerCase();
    const priorityFilter = (document.getElementById('filter-priority')?.value || '').trim().toLowerCase();

    return objectives.filter(obj => {
      const agentValue = (obj.agent_id || '').toLowerCase();
      const statusValue = (obj.status || '').toLowerCase();
      const priorityValue = (obj.priority || '').toLowerCase();

      const agentOk = !agentFilter || agentValue === agentFilter || agentValue.includes(agentFilter);
      const statusOk = !statusFilter || statusValue === statusFilter || statusValue.includes(statusFilter);
      const priorityOk = !priorityFilter || priorityValue === priorityFilter || priorityValue.includes(priorityFilter);
      return agentOk && statusOk && priorityOk;
    });
  }

  function getStatusClass(status) {
    const classes = {
      'active': 'primary',
      'completed': 'success',
      'failed': 'danger',
      'pending': 'warning',
      'approved': 'info',
      'cancelled': 'secondary'
    };
    return classes[status] || 'secondary';
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
    if (!dateString) return '—';
    const date = new Date(dateString);
    return date.toLocaleString();
  }

  function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    return `${hours}h ${minutes}m`;
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Renders JSON success criteria / metrics (array or object) as a readable list.
  // Returns '' when there is nothing to show, so callers can skip the section.
  function formatStructured(value) {
    if (value === null || value === undefined || value === '') {
      return '';
    }
    if (Array.isArray(value)) {
      if (value.length === 0) return '';
      return '<ul class="mb-0">' + value.map(function(item) {
        return '<li>' + escapeHtml(typeof item === 'object' ? JSON.stringify(item) : String(item)) + '</li>';
      }).join('') + '</ul>';
    }
    if (typeof value === 'object') {
      const entries = Object.entries(value);
      if (entries.length === 0) return '';
      return '<ul class="mb-0">' + entries.map(function(pair) {
        const val = pair[1];
        return '<li><strong>' + escapeHtml(pair[0]) + ':</strong> ' + escapeHtml(typeof val === 'object' ? JSON.stringify(val) : String(val)) + '</li>';
      }).join('') + '</ul>';
    }
    return escapeHtml(String(value));
  }

  function showError(message) {
    console.error('Agent Objectives: Error -', message);
    showToast(message, 'danger');
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

  window.applyFilters = function() {
    updateObjectivesTable(applyFiltersToObjectives(allObjectives));
  };

  window.clearFilters = function() {
    const agent = document.getElementById('filter-agent');
    const status = document.getElementById('filter-status');
    const priority = document.getElementById('filter-priority');

    if (agent) agent.value = '';
    if (status) status.value = '';
    if (priority) priority.value = '';

    updateObjectivesTable(allObjectives);
  };

  // Make viewObjectiveDetails available globally
  window.viewObjectiveDetails = function(objectiveId) {
    console.log('Viewing objective:', objectiveId);
    
    // Fetch objective details
    const endpoint = window.AgentObjectivesConfig.baseUrl + 
                    window.AgentObjectivesConfig.objectivesEndpoint + 
                    '?objective_id=' + objectiveId;
    
    fetch(endpoint)
      .then(response => response.json())
      .then(data => {
        if (data.success && data.data.objectives.length > 0) {
          const objective = data.data.objectives[0];
          showObjectiveModal(objective);
        } else {
          showToast(getLabels().objective_not_found || '', 'danger');
        }
      })
      .catch(error => {
        console.error('Error loading objective details:', error);
        showToast(getLabels().error_loading_details || '', 'danger');
      });
  };

  function showObjectiveModal(objective) {
    const modalBody = document.getElementById('objective-details-body');
    if (!modalBody) {
      console.error('Modal body not found');
      return;
    }
    
    const labels = getLabels();
    const dash = '—';

    // Derived deadline = creation time + estimated duration (no stored deadline column)
    const deadline = (objective.created_at && objective.estimated_completion_time)
      ? formatDate(new Date(new Date(objective.created_at).getTime() + (objective.estimated_completion_time * 1000)).toISOString())
      : dash;

    modalBody.innerHTML = `
      <div class="row">
        <div class="col-md-6">
          <h5>${escapeHtml(labels.general_information || '')}</h5>
          <table class="table table-sm">
            <tr><th>${escapeHtml(labels.objective_id || '')}</th><td>${escapeHtml(objective.objective_id)}</td></tr>
            <tr><th>${escapeHtml(labels.agent || '')}</th><td>${escapeHtml(objective.agent_id)}</td></tr>
            <tr><th>${escapeHtml(labels.priority || '')}</th><td><span class="badge bg-${getPriorityClass(objective.priority)}">${escapeHtml(objective.priority)}</span></td></tr>
            <tr><th>${escapeHtml(labels.status || '')}</th><td><span class="badge bg-${getStatusClass(objective.status)}">${escapeHtml(objective.status)}</span></td></tr>
            <tr><th>${escapeHtml(labels.created || '')}</th><td>${formatDate(objective.created_at)}</td></tr>
            <tr><th>${escapeHtml(labels.deadline || '')}</th><td>${deadline}</td></tr>
          </table>
        </div>
        <div class="col-md-6">
          <h5>${escapeHtml(labels.timeline || '')}</h5>
          <table class="table table-sm">
            <tr><th>${escapeHtml(labels.estimated_completion || '')}</th><td>${objective.estimated_completion_time ? formatDuration(objective.estimated_completion_time) : dash}</td></tr>
            <tr><th>${escapeHtml(labels.approved_at || '')}</th><td>${objective.approved_at ? formatDate(objective.approved_at) : dash}</td></tr>
            <tr><th>${escapeHtml(labels.started_at || '')}</th><td>${objective.started_at ? formatDate(objective.started_at) : dash}</td></tr>
            <tr><th>${escapeHtml(labels.completed_at || '')}</th><td>${objective.completed_at ? formatDate(objective.completed_at) : dash}</td></tr>
          </table>
        </div>
      </div>
      <div class="row mt-3">
        <div class="col-md-12">
          <h5>${escapeHtml(labels.goal || '')}</h5>
          <p>${escapeHtml(objective.goal_statement || labels.no_description || '')}</p>
        </div>
      </div>
      ${formatStructured(objective.success_criteria) ? `
      <div class="row mt-3">
        <div class="col-md-12">
          <h5>${escapeHtml(labels.success_criteria || '')}</h5>
          ${formatStructured(objective.success_criteria)}
        </div>
      </div>
      ` : ''}
      ${formatStructured(objective.metrics) ? `
      <div class="row mt-3">
        <div class="col-md-12">
          <h5>${escapeHtml(labels.metrics || '')}</h5>
          ${formatStructured(objective.metrics)}
        </div>
      </div>
      ` : ''}
      ${objective.failure_reason ? `
      <div class="row mt-3">
        <div class="col-md-12">
          <h5>${escapeHtml(labels.failure_reason || '')}</h5>
          <div class="alert alert-danger">${escapeHtml(objective.failure_reason)}</div>
        </div>
      </div>
      ` : ''}
    `;
    
    // Show the modal using Bootstrap
    const modal = new bootstrap.Modal(document.getElementById('objectiveDetailsModal'));
    modal.show();
  }

  window.refreshData = function() {
    console.log('Agent Objectives: Refresh button clicked (refreshData)');
    loadObjectives();
    showToast(getLabels().objectives_refreshed || '', 'success');
  };

  window.refreshObjectives = function() {
    console.log('Agent Objectives: Refresh button clicked (refreshObjectives)');
    loadObjectives();
    showToast(getLabels().objectives_refreshed || '', 'success');
  };

  console.log('Agent Objectives: Script loaded');
  console.log('Agent Objectives: refreshObjectives function available:', typeof window.refreshObjectives);
  console.log('Agent Objectives: refreshData function available:', typeof window.refreshData);
})();
