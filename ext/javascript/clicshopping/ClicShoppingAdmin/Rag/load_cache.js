// ====================================================================
// LOAD CACHE STATISTICS
// ====================================================================

function loadCacheStats() {
  const cacheStatsUrl = window.APP_DATA?.ajax?.cacheStatsUrl || '';

  if (!cacheStatsUrl) {
    console.error('Cache stats URL not defined in APP_DATA');
    return;
  }

  fetch(cacheStatsUrl)
    .then(response => response.json())
    .then(data => {
      if (!data.success) {
        console.error('Failed to load cache stats:', data.error);
        return;
      }

      const stats = data.data;

      // Update cards
      document.getElementById('cache-hit-rate').textContent = stats.hit_rate + '%';
      document.getElementById('cache-entries').textContent = stats.total_entries;
      document.getElementById('cache-time-saved').textContent = Math.round(stats.total_time_saved_ms) + ' ms';
      document.getElementById('cache-avg-saved').textContent = Math.round(stats.avg_time_saved_ms) + ' ms';

      // Update detailed stats
      document.getElementById('cache-total-hits').textContent = stats.total_hits;
      document.getElementById('cache-total-misses').textContent = stats.total_misses;
      document.getElementById('cache-avg-size').textContent = Math.round(stats.avg_result_count) + ' rows';
      document.getElementById('cache-last-update').textContent = new Date().toLocaleString();

      // Calculate performance metrics
      if (stats.total_hits > 0 && stats.avg_time_saved_ms > 0) {
        const speedup = (stats.avg_time_saved_ms / 100).toFixed(1); // Rough estimate
        const improvement = ((stats.total_hits / (stats.total_hits + stats.total_misses)) * 100).toFixed(1);
        const tokensSaved = Math.round(stats.total_hits * 500); // Estimate 500 tokens per cached query

        document.getElementById('cache-speedup').textContent = speedup + 'x';
        document.getElementById('cache-improvement').textContent = improvement + '%';
        document.getElementById('cache-tokens-saved').textContent = tokensSaved.toLocaleString();
      }
    })
    .catch(error => console.error('Error loading cache stats:', error));
}
