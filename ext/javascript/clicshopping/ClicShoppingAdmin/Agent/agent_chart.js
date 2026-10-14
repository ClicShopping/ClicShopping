let agentsChartInstance = null;

function createAgentsChart() {
  console.log('🔍 createAgentsChart called');
  
  // Don't recreate if already exists
  if (agentsChartInstance) {
    console.log('ℹ️ Agents chart already exists, skipping');
    return;
  }
  
  const ctx = document.getElementById('agentsChart');

  if (!ctx) {
    console.warn('⚠️ agentsChart canvas not found (tab may not be visible yet)');
    return;
  }

  console.log('✅ agentsChart canvas found');

  // Check if agents variable is defined
  if (typeof agents === 'undefined') {
    console.error('❌ agents variable is not defined');
    return;
  }

  console.log('📊 Agents data:', agents);

  if (agents.length === 0) {
    console.warn('⚠️ No agents data available');
    ctx.parentElement.innerHTML = '<p class="text-muted text-center">No agents data available</p>';
    return;
  }

  try {
    agentsChartInstance = new Chart(ctx, {
      type: 'pie',
      data: {
        labels: agents.map(agent => agent.name),
        datasets: [{
          data: agents.map(agent => agent.percentage),
          backgroundColor: [
            '#667eea', '#f093fb', '#4facfe', '#43e97b',
            '#f5576c', '#00f2fe', '#38f9d7', '#764ba2'
          ],
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'right'
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                const agent = agents[context.dataIndex];
                return agent.name + ': ' + agent.usage_count + ' Usages (' + context.parsed + '%)';
              }
            }
          }
        }
      }
    });
    console.log('✅ Agents chart created successfully with instance:', agentsChartInstance);
  } catch (error) {
    console.error('❌ Error creating agents chart:', error);
  }
}