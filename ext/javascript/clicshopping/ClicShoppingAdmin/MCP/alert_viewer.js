const alertsPerPage = 10;
let currentPage = 1;

function initAlertViewer() {
  // Basic signal to confirm the script runs
  console.log('Alert viewer initialized');

  const filterEl = document.getElementById('alertFilter');
  const clearBtn = document.getElementById('clearAlerts');

  if (filterEl) {
    filterEl.addEventListener('change', function () {
      console.log('Alert filter changed to:', this.value);
      loadAlerts(1, this.value);
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      if (!confirm('Are you sure you want to clear all alerts?')) return;
      fetch(clearAlerts, {
        method: 'POST',
        body: JSON.stringify({ 'clear': true }),
        headers: {
          'Content-Type': 'application/json'
        }
      })
        .then(response => {
          if (!response.ok) {
            return response.json().then(errorData => {
              throw new Error(errorData.message || 'Server returned an error.');
            });
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            loadAlerts();
            alert(data.message);
          } else {
            alert(data.message);
          }
        })
        .catch(error => {
          console.error('Failed to clear alerts:', error);
          alert('Failed to clear alerts. See console for details.');
        });
    });
  }

  loadAlerts();
  setInterval(() => {
    const filter = filterEl ? filterEl.value : 'all';
    loadAlerts(currentPage, filter);
  }, 60000);
}

function loadAlerts(page = 1, filter = 'all') {
  if (!gptAlerts) {
    console.error('Missing alerts endpoint.');
    return;
  }

  const url = `${gptAlerts}?page=${page}&filter=${filter}&per_page=${alertsPerPage}`;
  console.log('Loading alerts:', url);

  fetch(url)
    .then(response => response.json())
    .then(data => {
      const alertsList = document.getElementById('mcpAlertsList');
      if (!alertsList) return;
      alertsList.innerHTML = '';

      if (!data || !Array.isArray(data.alerts) || data.alerts.length === 0) {
        alertsList.innerHTML = `<tr><td colspan="4" class="text-center">${text_no_alert}</td></tr>`;
        return;
      }

      data.alerts.forEach(alert => {
        const row = document.createElement('tr');

        let rowClass = '';
        let badgeClass = '';

        switch (alert.type) {
          case 'critical':
          case 'error':
            rowClass = 'table-danger';
            badgeClass = 'text-bg-danger';
            break;
          case 'warning':
            rowClass = 'table-warning';
            badgeClass = 'text-bg-warning';
            break;
          case 'info':
            rowClass = 'table-info';
            badgeClass = 'text-bg-info';
            break;
          default:
            rowClass = '';
            badgeClass = 'text-bg-secondary';
        }

        // Build server info display
        let serverDisplay = '<span class="badge bg-secondary">N/A</span>';
        if (alert.server && alert.server.server_url) {
          serverDisplay = `<span class="badge bg-info">${alert.server.server_url}</span>`;
        }

        row.className = rowClass;
        row.innerHTML = `
          <td>${alert.alert_timestamp}</td>
          <td>${serverDisplay}</td>
          <td><span class="badge ${badgeClass}">${alert.type}</span></td>
          <td>${alert.message}</td>
        `;
        alertsList.appendChild(row);
      });

      updatePagination(data.total_pages, page);
      currentPage = page;
    })
    .catch(error => {
      console.error('Failed to load alerts:', error);
      const alertsList = document.getElementById('mcpAlertsList');
      if (alertsList) {
        alertsList.innerHTML =
          '<tr><td colspan="4" class="text-center text-danger">Failed to load alerts</td></tr>';
      }
    });
}

function updatePagination(totalPages, currentPage) {
  const pagination = document.getElementById('mcpAlertsPagination');
  if (!pagination) return;
  pagination.innerHTML = '';
  if (!totalPages || totalPages <= 1) return;

  const nav = document.createElement('nav');
  const ul = document.createElement('ul');
  ul.className = 'pagination pagination-sm';

  const prevLi = document.createElement('li');
  prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
  prevLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>`;
  ul.appendChild(prevLi);

  for (let i = 1; i <= totalPages; i++) {
    const li = document.createElement('li');
    li.className = `page-item ${i === currentPage ? 'active' : ''}`;
    li.innerHTML = `<a class="page-link" href="#" data-page="${i}">${i}</a>`;
    ul.appendChild(li);
  }

  const nextLi = document.createElement('li');
  nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
  nextLi.innerHTML = `<a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>`;
  ul.appendChild(nextLi);

  nav.appendChild(ul);
  pagination.appendChild(nav);

  ul.addEventListener('click', (e) => {
    e.preventDefault();
    const target = e.target;
    if (!target || !target.dataset) return;
    const page = target.dataset.page;
    if (page) {
      const filterEl = document.getElementById('alertFilter');
      const filter = filterEl ? filterEl.value : 'all';
      loadAlerts(parseInt(page, 10), filter);
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAlertViewer);
} else {
  initAlertViewer();
}
