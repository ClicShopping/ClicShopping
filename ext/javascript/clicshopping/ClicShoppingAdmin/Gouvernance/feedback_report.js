// ====================================================================
// FEEDBACK JOURNAL — one analysis, rendered on the manager and the data scientist dashboards
// ====================================================================
// Wrapped: security_report.js already owns escapeHtml/interpolate in the global scope, and both
// scripts load on the same page.
(function () {
  'use strict';

  function escapeText(value) {
    const div = document.createElement('div');
    div.textContent = String(value ?? '');
    return div.innerHTML;
  }

  function interpolate(template, values) {
    return String(template).replace(/\{\{([A-Za-z0-9_-]+)\}\}/g, (_, key) => values[key] ?? key);
  }

  // The rows the finding designates. They carry the user's own words: escaped, always.
  function renderSamples(samples, labels) {
    if (!Array.isArray(samples) || !samples.length) {
      return '';
    }

    const rows = samples.map(sample => `
      <tr>
        <td class="text-nowrap small text-muted">${escapeText(sample.asked || '')}</td>
        <td class="small"><code>${escapeText(sample.id || '')}</code></td>
        <td class="small">${escapeText(sample.question || '')}</td>
      </tr>`).join('');

    return `
      <details class="mt-2">
        <summary class="small">${escapeText(labels.samples || '')} (${samples.length})</summary>
        <table class="table table-sm mt-2 mb-0"><tbody>${rows}</tbody></table>
      </details>`;
  }

  function renderFeedbackReport(data, labels) {
    const severityClass = {
      critical: 'border-danger',
      high: 'border-warning',
      medium: 'border-info'
    };

    // The green all-clear asserts health. It must never fire on a window that could not conclude:
    // there, nothing was found because nothing could be, which is not the same statement.
    if (!(data.findings || []).length && !data.unstable) {
      return `<div class="alert alert-success mb-0">${data.no_finding || ''}</div>`;
    }

    // Title, body and action come from the language files, already interpolated server-side.
    // Scope and population come from the database: escape the text, coerce the number.
    const findings = data.findings.map(finding => `
      <div class="card mb-3 border-start border-4 ${severityClass[finding.severity] || 'border-secondary'}">
        <div class="card-body">
          <h6 class="card-title">${finding.title}</h6>
          <p class="card-text">${finding.body}</p>
          <p class="card-text mb-1"><strong>&rarr;</strong> <em>${finding.action || ''}</em></p>
          <p class="small text-muted mb-0">
            ${escapeText(finding.scope || '')}
            <span class="badge bg-light text-dark ms-1">n = ${Number(finding.population) || 0}</span>
          </p>
          ${renderSamples(finding.figures && finding.figures.samples, labels)}
        </div>
      </div>`).join('');

    // Interpolated server-side from the thin_population figures, and absent above the threshold.
    const notice = data.unstable
      ? `<div class="alert alert-info small">
           ${data.unstable.notice || ''}
           ${data.unstable.withheld ? `<div class="mt-2">${data.unstable.withheld}</div>` : ''}
         </div>`
      : '';

    return `
      <p class="text-muted small">${labels.generated}</p>
      ${notice}
      ${findings}
      <div class="alert alert-secondary small mb-0">${data.limits || ''}</div>`;
  }

  // Displaying is not analysing: the stored run is reused for a day, so the recompute is a
  // gesture of its own. Rendered here, so both dashboards get it without touching a template.
  function renderHeader(data, labels) {
    const state = data.refreshed ? labels.runFresh : labels.runStored;

    return `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="badge ${data.refreshed ? 'bg-success' : 'bg-secondary'}">${escapeText(state || '')}</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="feedbackReportRecompute">
          <i class="bi bi-arrow-clockwise"></i> ${escapeText(labels.recompute || '')}
        </button>
      </div>`;
  }

  function loadFeedbackReport(force) {
    const url = window.APP_DATA?.ajax?.feedbackReportUrl || '';
    const body = document.getElementById('feedbackReportBody');
    const modalEl = document.getElementById('feedbackReportModal');

    if (!url || !body || !modalEl) {
      return;
    }

    const labels = window.APP_DATA?.labels?.feedback || {};

    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div></div>';
    // The markup sits inside a card: a positioned ancestor makes its own stacking context and the
    // backdrop paints over the dialog. Re-parent to body, where Bootstrap expects a modal.
    if (modalEl.parentElement !== document.body) {
      document.body.appendChild(modalEl);
    }
    new bootstrap.Modal(modalEl).show();

    fetch(url + (force ? (url.includes('?') ? '&' : '?') + 'force=1' : ''))
      .then(response => response.json())
      .then(payload => {
        if (!payload.success) {
          body.innerHTML = `<div class="alert alert-danger mb-0">${labels.reportFailed || ''}</div>`;
          return;
        }

        const data = payload.data;

        body.innerHTML = renderHeader(data, labels) + renderFeedbackReport(data, {
          samples: labels.samples,
          generated: interpolate(labels.generated || '', {
            date: data.generated_at || '',
            days: data.period_days
          })
        });

        const recompute = document.getElementById('feedbackReportRecompute');

        if (recompute) {
          recompute.addEventListener('click', () => loadFeedbackReport(true));
        }
      })
      .catch(() => {
        body.innerHTML = `<div class="alert alert-danger mb-0">${labels.reportFailed || ''}</div>`;
      });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.feedbackReportBtn').forEach(button => {
      button.addEventListener('click', () => loadFeedbackReport(false));
    });
  });
})();
