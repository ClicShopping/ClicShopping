// ====================================================================
// FLUSH QUERY CACHE
// ====================================================================

function flushQueryCache() {
  if (!confirm('⚠️ Êtes-vous sûr de vouloir vider le cache des requêtes?\n\nCela supprimera toutes les requêtes mises en cache et les prochaines requêtes seront plus lentes.')) {
    return;
  }

  const resultDiv = document.getElementById('cache-flush-result');
  resultDiv.style.display = 'block';
  resultDiv.className = 'alert alert-info';
  resultDiv.textContent = '⏳ Vidage du cache en cours...';

  const cacheManageUrl = window.APP_DATA?.ajax?.cacheManageUrl || '';
  if (!cacheManageUrl) {
    console.error('Cache manage URL not defined in APP_DATA');
    return;
  }

  fetch(cacheManageUrl, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=flush_query_cache'
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        resultDiv.className = 'alert alert-success';
        resultDiv.textContent = '✅ Cache vidé avec succès! ' + data.entries_deleted + ' entrées supprimées.';

        setTimeout(() => {
          loadCacheStats();
          resultDiv.style.display = 'none';
        }, 2000);
      } else {
        resultDiv.className = 'alert alert-danger';
        resultDiv.textContent = '❌ Erreur: ' + data.message;
      }
    })
    .catch(error => {
      resultDiv.className = 'alert alert-danger';
      resultDiv.textContent = '❌ Erreur réseau: ' + error.message;
    });
}

// ====================================================================
// LOAD CACHE STATS ON PAGE LOAD AND INTERVAL
// ====================================================================

document.addEventListener('DOMContentLoaded', () => {
  const tab10 = document.querySelector('#tab10');
  if (tab10?.classList.contains('active')) loadCacheStats();

  document.querySelector('a[href="#tab10"]')?.addEventListener('shown.bs.tab', () => loadCacheStats());

  setInterval(() => {
    if (tab10?.classList.contains('active')) loadCacheStats();
  }, 30000);
});



// ====================================================================
// FLUSH CACHE GENERIC
// ====================================================================

function flushCache() {
  if (!confirm('Êtes-vous sûr de vouloir vider le cache des requêtes?')) return;

  const url = window.APP_DATA?.ajax?.manageCacheUrl || '';
  if (!url) {
    console.error('Manage cache URL not defined in APP_DATA');
    return;
  }

  fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=flush'
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert('✅ Cache vidé avec succès');
        location.reload();
      } else {
        alert('❌ Erreur: ' + data.message);
      }
    })
    .catch(() => alert('❌ Erreur réseau'));
}
