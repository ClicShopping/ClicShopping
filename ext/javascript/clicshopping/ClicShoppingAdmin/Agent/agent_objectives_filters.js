(function() {
  'use strict';

  if (typeof window.applyFilters !== 'function') {
    window.applyFilters = function() {
      var agent = (document.getElementById('filter-agent') ? document.getElementById('filter-agent').value : '').trim();
      var status = (document.getElementById('filter-status') ? document.getElementById('filter-status').value : '').trim();
      var priority = (document.getElementById('filter-priority') ? document.getElementById('filter-priority').value : '').trim();
      var tbody = document.getElementById('objectives-tbody');
      if (!tbody) return;

      var rows = tbody.querySelectorAll('tr');
      rows.forEach(function(row) {
        var cells = row.querySelectorAll('td');
        if (cells.length < 6) return;
        var agentText = (cells[1] ? cells[1].textContent : '').trim();
        var priorityText = (cells[3] ? cells[3].textContent : '').trim();
        var statusText = (cells[4] ? cells[4].textContent : '').trim();
        var match = (!agent || agentText === agent)
          && (!status || statusText === status)
          && (!priority || priorityText === priority);
        row.style.display = match ? '' : 'none';
      });
    };
  }

  if (typeof window.clearFilters !== 'function') {
    window.clearFilters = function() {
      var agent = document.getElementById('filter-agent');
      var status = document.getElementById('filter-status');
      var priority = document.getElementById('filter-priority');
      if (agent) agent.value = '';
      if (status) status.value = '';
      if (priority) priority.value = '';
      window.applyFilters();
    };
  }
})();
