document.addEventListener('DOMContentLoaded', function() {
  'use strict';

  var config = window.ResetCacheConfig;
  if (!config) return;

  var confirmButton = document.getElementById('confirmResetCache');
  var resultDiv = document.getElementById('cacheResetResult');

  if (!confirmButton) return;

  confirmButton.addEventListener('click', function() {
    var checkboxes = document.querySelectorAll('input[name="cache_types[]"]:checked');
    var cacheTypes = Array.from(checkboxes).map(function(cb) { return cb.value; });

    if (cacheTypes.length === 0) {
      resultDiv.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> ' + config.labels.selectOne + '</div>';
      resultDiv.style.display = 'block';
      return;
    }

    confirmButton.disabled = true;
    confirmButton.innerHTML = '<i class="bi bi-arrow-repeat spinner-border spinner-border-sm"></i> ' + config.labels.resetting;

    resultDiv.innerHTML = '<div class="alert alert-info"><i class="bi bi-arrow-repeat spinner-border spinner-border-sm"></i> ' + config.labels.inProgress + '</div>';
    resultDiv.style.display = 'block';

    fetch(config.resetUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        cache_types: cacheTypes
      })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
      if (data.success) {
        var message = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> <strong>' + config.labels.success + '</strong><br><br>';

        if (data.details) {
          message += '<ul class="mb-0">';
          var detailKeys = ['db', 'disk', 'logs'];

          detailKeys.forEach(function(key) {
            if (data.details[key] !== undefined && config.labels.details[key]) {
              message += '<li>' + config.labels.details[key] + ' : ' + data.details[key] + '</li>';
            }
          });
          message += '</ul>';
        }

        message += '</div>';
        resultDiv.innerHTML = message;

        setTimeout(function() {
          var modal = bootstrap.Modal.getInstance(document.getElementById('resetCacheModal'));
          if (modal) {
            modal.hide();
          }
          location.reload();
        }, 3000);
      } else {
        resultDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <strong>' + config.labels.error + '</strong> ' + (data.message || config.labels.errorOccurred) + '</div>';
      }
    })
    .catch(function(error) {
      console.error('Error:', error);
      resultDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <strong>' + config.labels.error + '</strong></div>';
    })
    .finally(function() {
      confirmButton.disabled = false;
      confirmButton.innerHTML = '<i class="bi bi-trash"></i> ' + config.labels.confirm;
    });
  });
});
