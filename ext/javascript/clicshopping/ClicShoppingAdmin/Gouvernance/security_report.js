// ====================================================================
// SECURITY JOURNAL — report and noise purge (tab7)
// ====================================================================

function renderSecurityReport(data, labels) {
  const severityClass = {
    critical: 'border-danger',
    high: 'border-warning',
    medium: 'border-info'
  };

  const findings = (data.findings || []).map(finding => `
    <div class="card mb-3 border-start border-4 ${severityClass[finding.severity] || 'border-secondary'}">
      <div class="card-body">
        <h6 class="card-title">${finding.title}</h6>
        <p class="card-text">${finding.body}</p>
        <p class="card-text mb-0"><strong>&rarr;</strong> <em>${finding.action || ''}</em></p>
      </div>
    </div>`).join('');

  // Every column comes from the database: escape the text, coerce the numbers. A detection row
  // carries a user query, so treating any of it as markup is how the journal becomes the payload.
  const rows = (data.detections || []).map(d => `
    <tr>
      <td><code>${escapeHtml(d.event_type)}</code></td>
      <td>${escapeHtml(d.reason)}</td>
      <td class="text-end">${Number(d.count) || 0}</td>
      <td class="text-end">${Number(d.blocked) || 0}</td>
      <td class="small text-muted">${escapeHtml(d.first_seen)} &rarr; ${escapeHtml(d.last_seen)}</td>
      <td class="small text-muted">${escapeHtml(d.sample)}</td>
    </tr>`).join('');

  const detections = rows
    ? `<h6 class="mt-4">${data.detections_label || ''}</h6>
       <div class="table-responsive">
         <table class="table table-sm align-middle">
           <tbody>${rows}</tbody>
         </table>
       </div>`
    : `<div class="alert alert-success small">${data.no_detection || ''}</div>`;

  const exposure = data.exposure || {};
  const coverage = exposure.coverage || {};
  const statusClass = { safe: 'success', low: 'info', watch: 'warning', danger: 'danger' };

  // One direction only: the bar fills as danger rises, so it can never be read backwards.
  const gauge = `
    <div class="d-flex justify-content-between align-items-center mb-1">
      <span class="badge bg-${statusClass[exposure.status] || 'secondary'}">${escapeHtml(exposure.status)}</span>
      <span class="small text-muted">${Number(exposure.level) || 0}/100</span>
    </div>
    <div class="progress mb-2" style="height: 10px;">
      <div class="progress-bar bg-${statusClass[exposure.status] || 'secondary'}"
           style="width: ${Number(exposure.level) || 0}%"></div>
    </div>
    <p class="small text-muted">${data.coverage_label || ''} — ${Number(coverage.events) || 0}
       (${(coverage.layers || []).map(escapeHtml).join(', ') || '—'})
       ${coverage.last_event ? escapeHtml(coverage.last_event) : ''}</p>`;

  return `
    <p class="text-muted small">${labels.generated}</p>
    ${gauge}
    <p class="small">${labels.population}</p>
    ${findings}
    ${detections}
    <div class="alert alert-secondary small mb-0">${data.limits || ''}</div>`;
}

// The sample is a user query: it is rendered as text, never as markup.
function escapeHtml(value) {
  const div = document.createElement('div');
  div.textContent = String(value ?? '');
  return div.innerHTML;
}

function interpolate(template, values) {
  return String(template).replace(/\{\{([A-Za-z0-9_-]+)\}\}/g, (_, key) => values[key] ?? key);
}

function loadSecurityReport() {
  const url = window.APP_DATA?.ajax?.securityReportUrl || '';
  const body = document.getElementById('securityReportBody');
  const modalEl = document.getElementById('securityReportModal');

  if (!url || !body || !modalEl) {
    return;
  }

  const labels = window.APP_DATA?.labels?.security || {};

  body.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div></div>';
  new bootstrap.Modal(modalEl).show();

  fetch(url)
    .then(response => response.json())
    .then(payload => {
      if (!payload.success) {
        body.innerHTML = `<div class="alert alert-danger mb-0">${labels.reportFailed || ''}</div>`;
        return;
      }

      const data = payload.data;
      body.innerHTML = renderSecurityReport(data, {
        generated: interpolate(labels.generated || '', { date: data.generated_at, days: data.period_days }),
        population: interpolate(labels.population || '', data.population)
      });
    })
    .catch(() => {
      body.innerHTML = `<div class="alert alert-danger mb-0">${labels.reportFailed || ''}</div>`;
    });
}

function clearSecurityNoise(button) {
  const url = window.APP_DATA?.ajax?.securityClearUrl || '';

  if (!url || !window.confirm(button.dataset.confirm || '')) {
    return;
  }

  const labels = window.APP_DATA?.labels?.security || {};

  button.disabled = true;

  fetch(url, { method: 'POST' })
    .then(response => response.json())
    .then(payload => {
      window.alert(payload.success ? payload.message : (labels.clearFailed || ''));

      if (payload.success) {
        window.location.reload();
      }
    })
    .catch(() => window.alert(labels.clearFailed || ''))
    .finally(() => {
      button.disabled = false;
    });
}

document.addEventListener('DOMContentLoaded', () => {
  const reportBtn = document.getElementById('securityReportBtn');
  const clearBtn = document.getElementById('securityClearBtn');

  if (reportBtn) {
    reportBtn.addEventListener('click', loadSecurityReport);
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', () => clearSecurityNoise(clearBtn));
  }
});
