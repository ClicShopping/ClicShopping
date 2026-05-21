class HealthMonitor {
  constructor(url) {
    this.url = url;
    this.eventSource = null;
    this.isMonitoring = false;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 5;
    this.reconnectDelay = 3000;

    this.initializeElements();
    this.bindEvents();
  }

  initializeElements() {
    this.elements = {
      connectionStatus: document.getElementById('connectionStatus'),
      healthStatus: document.getElementById('healthStatus'),
      configStatus: document.getElementById('configStatus'),
      connectivityStatus: document.getElementById('connectivityStatus'),
      performanceStatus: document.getElementById('performanceStatus'),
      lastUpdate: document.getElementById('lastUpdate'),
      startBtn: document.getElementById('startMonitoring'),
      stopBtn: document.getElementById('stopMonitoring'),
      eventLog: document.getElementById('eventLog'),
      clearLogBtn: document.getElementById('clearLog'),
      mcpServer: document.getElementById('mcpServer'),
      serverInfo: document.getElementById('serverInfo'),
      serverInfoCard: document.getElementById('serverInfoCard'),
      serverName: document.getElementById('serverName'),
      serverHost: document.getElementById('serverHost'),
      serverPort: document.getElementById('serverPort'),
      serverSSL: document.getElementById('serverSSL')
    };
    
    // Debug logging
    console.log('Health Monitor initialized');
    console.log('MCP Server dropdown found:', this.elements.mcpServer !== null);
    console.log('Server info badge found:', this.elements.serverInfo !== null);
    console.log('Server info card found:', this.elements.serverInfoCard !== null);
    
    // Log dropdown options
    if (this.elements.mcpServer) {
      console.log('Dropdown options:', this.elements.mcpServer.options.length);
      for (let i = 0; i < this.elements.mcpServer.options.length; i++) {
        console.log(`  Option ${i}:`, this.elements.mcpServer.options[i].value, '-', this.elements.mcpServer.options[i].text);
      }
    }
  }

  bindEvents() {
    this.elements.startBtn.addEventListener('click', () => this.startMonitoring());
    this.elements.stopBtn.addEventListener('click', () => this.stopMonitoring());
    this.elements.clearLogBtn.addEventListener('click', () => this.clearLog());
    
    // Add server selection change event
    if (this.elements.mcpServer) {
      this.elements.mcpServer.addEventListener('change', () => {
        console.log('Server selection changed to:', this.elements.mcpServer.value);
        this.updateServerInfo();
        if (this.isMonitoring) {
          console.log('Restarting monitoring for new server...');
          this.stopMonitoring();
          setTimeout(() => this.startMonitoring(), 500);
        }
      });
    } else {
      console.error('MCP Server dropdown not found!');
    }
  }
  
  updateServerInfo() {
    if (!this.elements.mcpServer) return;
    
    const selectedOption = this.elements.mcpServer.options[this.elements.mcpServer.selectedIndex];
    if (selectedOption) {
      const serverText = selectedOption.text;
      
      // Update badge
      if (this.elements.serverInfo) {
        this.elements.serverInfo.textContent = 'Monitoring: ' + serverText;
        this.elements.serverInfo.style.display = 'inline-block';
      }
      
      // Update server info card
      if (this.elements.serverInfoCard) {
        // Parse server text: "Username (host:port)"
        const match = serverText.match(/^(.+?)\s*\((.+?):(\d+)\)$/);
        if (match) {
          const [, username, host, port] = match;
          if (this.elements.serverName) this.elements.serverName.textContent = username;
          if (this.elements.serverHost) this.elements.serverHost.textContent = host;
          if (this.elements.serverPort) this.elements.serverPort.textContent = port;
          if (this.elements.serverSSL) this.elements.serverSSL.textContent = host.includes('https') || port === '443' ? 'Yes' : 'No';
          this.elements.serverInfoCard.style.display = 'block';
        } else if (selectedOption.value === 'all') {
          if (this.elements.serverName) this.elements.serverName.textContent = 'All Servers';
          if (this.elements.serverHost) this.elements.serverHost.textContent = 'Multiple';
          if (this.elements.serverPort) this.elements.serverPort.textContent = 'Multiple';
          if (this.elements.serverSSL) this.elements.serverSSL.textContent = 'Mixed';
          this.elements.serverInfoCard.style.display = 'block';
        }
      }
      
      console.log('Server info updated:', serverText);
    }
  }
  
  getMonitoringUrl() {
    const mcpId = this.elements.mcpServer ? this.elements.mcpServer.value : 'all';
    return this.url + (this.url.includes('?') ? '&' : '?') + 'mcp_id=' + mcpId;
  }

  startMonitoring() {
    if (this.isMonitoring) return;

    this.log('Starting health monitoring...', 'info');
    this.isMonitoring = true;
    this.updateUI();
    this.updateServerInfo(); // Show which server is being monitored

    try {
      const monitoringUrl = this.getMonitoringUrl();
      console.log('Connecting to:', monitoringUrl);
      this.eventSource = new EventSource(monitoringUrl);

      this.eventSource.onopen = (event) => {
        this.reconnectAttempts = 0;
        this.updateConnectionStatus('connected', 'Connected', 'bg-success');
        this.log('EventSource connection opened', 'success');
      };

      // This is the only listener you need for the health data
      this.eventSource.addEventListener('healthcheck', (event) => {
        this.handleHealthData(event.data);
      });

      // Keep this listener for connection errors
      this.eventSource.onerror = (event) => {
        this.log('EventSource connection error', 'error');
        this.updateConnectionStatus('error', 'Connection Error', 'bg-danger');

        if (this.reconnectAttempts < this.maxReconnectAttempts) {
          this.scheduleReconnect();
        } else {
          this.log('Maximum reconnection attempts reached', 'error');
          this.stopMonitoring();
        }
      };

    } catch (error) {
      this.log(`Failed to create EventSource: ${error.message}`, 'error');
      this.stopMonitoring();
    }
  }

  stopMonitoring() {
    if (!this.isMonitoring) return;

    this.log('Stopping health monitoring...', 'info');
    this.isMonitoring = false;

    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }

    this.updateConnectionStatus('disconnected', 'Disconnected', 'bg-secondary');
    this.updateUI();
  }

  scheduleReconnect() {
    this.reconnectAttempts++;
    const delay = this.reconnectDelay * this.reconnectAttempts;

    this.log(`Reconnection attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts} in ${delay}ms`, 'warning');
    this.updateConnectionStatus('reconnecting', 'Reconnecting...', 'bg-warning');

    setTimeout(() => {
      if (this.isMonitoring && this.eventSource && this.eventSource.readyState === EventSource.CLOSED) {
        this.eventSource = null;
        this.startMonitoring();
      }
    }, delay);
  }

  handleHealthData(dataString) {
    try {
      // Correctly parse the JSON string from the server
      const data = JSON.parse(dataString);

      // Log the parsed data to verify its content
      console.log("Parsed data from server:", data);

      // Check if the status is 'ok' or 'error' as sent from the server
      if (data.status === 'ok') {
        this.updateHealthStatus(data);
        this.log(`Health check: OK`, 'success');
      } else {
        // Handle any non-'ok' status, including 'error'
        this.log(`Health check failed: ${data.message}`, 'error');
        this.updateHealthStatus({
          status: 'error',
          message: data.message,
          timestamp: data.timestamp,
          details: data.details
        });
      }

    } catch (error) {
      // This block will catch JSON parsing errors
      this.log(`Failed to parse health data: ${error.message}. Received data: ${dataString}`, 'error');
      this.updateHealthStatus({
        status: 'error',
        message: 'Malformed data from server',
        timestamp: new Date().toISOString(),
        details: { error: `Parsing error: ${error.message}` }
      });
    }
  }

  updateHealthStatus(data) {
    const statusColors = {
      'healthy': 'bg-success',
      'warning': 'bg-warning',
      'error': 'bg-danger',
      'unknown': 'bg-info'
    };

    const status = data.status || 'unknown';
    const colorClass = statusColors[status] || 'bg-secondary';

    this.elements.healthStatus.className = `badge ${colorClass}`;
    this.elements.healthStatus.textContent = status.toUpperCase();
    this.elements.lastUpdate.textContent = data.timestamp || new Date().toLocaleString();

    // Update server info if available in data
    if (data.server_info && this.elements.serverInfo) {
      const serverText = `${data.server_info.host}:${data.server_info.port}`;
      this.elements.serverInfo.textContent = 'Server: ' + serverText;
      this.elements.serverInfo.style.display = 'inline-block';
    }

    // Update detailed status if available
    if (data.details) {
      this.updateDetailedStatus(data.details);
    }
  }

  updateDetailedStatus(details) {
    const statusColors = {
      'healthy': 'text-success',
      'warning': 'text-warning',
      'error': 'text-danger'
    };

    // Configuration status
    if (details.configuration) {
      const config = details.configuration;
      const statusClass = statusColors[config.status] || 'text-info';
      const configStatusEl = this.elements.configStatus;
      configStatusEl.innerHTML = ''; // clear

      const statusDiv = document.createElement('div');
      statusDiv.className = statusClass;
      const strong = document.createElement('strong');
      strong.textContent = config.valid ? '✓ Valid' : '✗ Invalid';
      statusDiv.appendChild(strong);
      configStatusEl.appendChild(statusDiv);

      if (config.issues && config.issues.length) {
        const small = document.createElement('small');
        small.className = 'text-muted';
        small.textContent = config.issues.length + ' issue(s) found';
        configStatusEl.appendChild(small);
      } else {
        const small = document.createElement('small');
        small.className = 'text-muted';
        small.textContent = 'No issues';
        configStatusEl.appendChild(small);
      }
    }

    // Connectivity status
    if (details.connectivity) {
      const conn = details.connectivity;
      const statusClass = statusColors[conn.status] || 'text-info';
      const connStatusEl = this.elements.connectivityStatus;
      connStatusEl.innerHTML = ''; // clear

      const statusDiv = document.createElement('div');
      statusDiv.className = statusClass;
      const strong = document.createElement('strong');
      strong.textContent = conn.connected ? '✓ Connected' : '✗ Disconnected';
      statusDiv.appendChild(strong);
      connStatusEl.appendChild(statusDiv);

      const small = document.createElement('small');
      small.className = 'text-muted';
      if (conn.latency) {
        small.textContent = 'Latency: ' + conn.latency + 'ms';
      } else {
        small.textContent = conn.error || 'Checking...';
      }
      connStatusEl.appendChild(small);
    }

    // Performance status
    if (details && details.performance) {
      const perf = details.performance;
      const status = perf.status || 'healthy';
      const statusClass = statusColors[status] || 'text-info';

      const perfStatusEl = this.elements.performanceStatus;
      perfStatusEl.innerHTML = ''; // clear

      const statusDiv = document.createElement('div');
      statusDiv.className = statusClass;
      const strong = document.createElement('strong');
      strong.textContent = 'Performance';
      statusDiv.appendChild(strong);
      perfStatusEl.appendChild(statusDiv);

      const small = document.createElement('small');
      small.className = 'text-muted';
      const uptime = Math.floor((perf.uptime || 0) / 3600);
      const requests = perf.total_requests || 0;
      const errorRate = perf.error_rate || 0;
      const uptimeLine = document.createTextNode('Uptime: ' + uptime + 'h');
      small.appendChild(uptimeLine);
      const br1 = document.createElement('br');
      small.appendChild(br1);
      const requestsLine = document.createTextNode('Requests: ' + requests);
      small.appendChild(requestsLine);
      const br2 = document.createElement('br');
      small.appendChild(br2);
      const errorLine = document.createTextNode('Error Rate: ' + errorRate + '%');
      small.appendChild(errorLine);
      perfStatusEl.appendChild(small);
    }
  }

  updateConnectionStatus(status, text, colorClass) {
    this.elements.connectionStatus.className = `badge ${colorClass}`;
    this.elements.connectionStatus.textContent = text;
  }

  updateUI() {
    if (this.isMonitoring) {
      this.elements.startBtn.style.display = 'none';
      this.elements.stopBtn.style.display = 'inline-block';
    } else {
      this.elements.startBtn.style.display = 'inline-block';
      this.elements.stopBtn.style.display = 'none';
    }
  }

  log(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString();
    const colors = {
      'info': 'text-info',
      'success': 'text-success',
      'warning': 'text-warning',
      'error': 'text-danger'
    };

    // Get current server info
    let serverInfo = 'Unknown';
    if (this.elements.mcpServer) {
      const selectedOption = this.elements.mcpServer.options[this.elements.mcpServer.selectedIndex];
      if (selectedOption) {
        serverInfo = selectedOption.value === 'all' ? 'All Servers' : selectedOption.text;
      }
    }

    const colorClass = colors[type] || 'text-muted';

    // Build DOM elements individually to avoid innerHTML XSS
    const logEntry = document.createElement('div');
    logEntry.className = 'log-entry mb-1';

    const tsSpan = document.createElement('span');
    tsSpan.className = 'text-muted';
    tsSpan.textContent = `[${timestamp}]`;

    const badgeSpan = document.createElement('span');
    badgeSpan.className = 'badge bg-secondary';
    badgeSpan.style.fontSize = '0.7em';
    badgeSpan.textContent = serverInfo;

    const typeSpan = document.createElement('span');
    typeSpan.className = colorClass;
    typeSpan.textContent = `[${type.toUpperCase()}]`;

    const msgSpan = document.createElement('span');
    msgSpan.textContent = message;

    logEntry.appendChild(tsSpan);
    logEntry.appendChild(badgeSpan);
    logEntry.appendChild(typeSpan);
    logEntry.appendChild(msgSpan);

    // If this is the first entry, replace the placeholder
    if (this.elements.eventLog.innerHTML.includes('No events yet...') ||
        this.elements.eventLog.innerHTML.includes('text_no_event')) {
      this.elements.eventLog.innerHTML = '';
    }

    this.elements.eventLog.insertBefore(logEntry, this.elements.eventLog.firstChild);

    // Keep only last 50 entries
    while (this.elements.eventLog.children.length > 50) {
      this.elements.eventLog.removeChild(this.elements.eventLog.lastChild);
    }
  }

  clearLog() {
    this.elements.eventLog.innerHTML = '<div class="text-muted">Log cleared...</div>';
  }
}

// Initialize the health monitor when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  const monitor = new HealthMonitor(eventUrl);

  // Do not auto-start monitoring to avoid long-lived SSE connections by default.
  // User can start explicitly with the Start button.
});
