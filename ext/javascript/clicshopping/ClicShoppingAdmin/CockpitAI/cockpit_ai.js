/**
 * CockpitAI Module - JavaScript Enhancements
 * Additional interactive features for the strategic product analysis interface
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

(function($) {
  'use strict';

  /**
   * CockpitAI namespace
   */
  window.CockpitAI = window.CockpitAI || {};

  /**
   * Format number with locale
   */
  CockpitAI.formatNumber = function(num, decimals) {
    decimals = decimals || 0;
    return parseFloat(num).toFixed(decimals);
  };

  /**
   * Format date
   */
  CockpitAI.formatDate = function(dateString) {
    if (!dateString) return 'N/A';
    var date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
  };

  /**
   * Get quadrant color class
   */
  CockpitAI.getQuadrantColor = function(code) {
    var colors = {
      'Q1': 'success',
      'Q2': 'warning',
      'Q3': 'danger',
      'Q4': 'info',
      'Q_intermediate': 'secondary'
    };
    return colors[code] || 'secondary';
  };

  /**
   * Get priority color class
   */
  CockpitAI.getPriorityColor = function(priority) {
    var colors = {
      'critical': 'danger',
      'high': 'warning',
      'medium': 'info',
      'low': 'secondary'
    };
    return colors[priority] || 'secondary';
  };

  /**
   * Get priority icon
   */
  CockpitAI.getPriorityIcon = function(priority) {
    var icons = {
      'critical': 'exclamation-triangle',
      'high': 'exclamation-circle',
      'medium': 'info-circle',
      'low': 'check-circle'
    };
    return icons[priority] || 'check-circle';
  };

  /**
   * Get factor status icon
   */
  CockpitAI.getFactorStatusIcon = function(status) {
    var icons = {
      'valid': 'check-circle text-success',
      'missing': 'x-circle text-danger',
      'not_analyzed': 'question-circle text-warning'
    };
    return icons[status] || 'question-circle text-muted';
  };

  /**
   * Show toast notification
   */
  CockpitAI.showToast = function(message, type) {
    type = type || 'info';
    var toastHtml = '<div class="toast align-items-center text-white bg-' + type + ' border-0" role="alert" aria-live="assertive" aria-atomic="true">' +
      '<div class="d-flex">' +
      '<div class="toast-body">' + message + '</div>' +
      '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
      '</div>' +
      '</div>';
    
    var toastContainer = $('#CockpitAI-toast-container');
    if (toastContainer.length === 0) {
      toastContainer = $('<div id="CockpitAI-toast-container" class="toast-container position-fixed top-0 end-0 p-3"></div>');
      $('body').append(toastContainer);
    }
    
    var toastElement = $(toastHtml);
    toastContainer.append(toastElement);
    
    var toast = new bootstrap.Toast(toastElement[0]);
    toast.show();
    
    toastElement.on('hidden.bs.toast', function() {
      $(this).remove();
    });
  };

  /**
   * Export analysis to JSON
   */
  CockpitAI.exportToJSON = function(data) {
    var dataStr = JSON.stringify(data, null, 2);
    var dataUri = 'data:application/json;charset=utf-8,' + encodeURIComponent(dataStr);
    
    var exportFileDefaultName = 'CockpitAI-analysis-' + Date.now() + '.json';
    
    var linkElement = document.createElement('a');
    linkElement.setAttribute('href', dataUri);
    linkElement.setAttribute('download', exportFileDefaultName);
    linkElement.click();
  };

  /**
   * Copy analysis to clipboard
   */
  CockpitAI.copyToClipboard = function(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function() {
        CockpitAI.showToast('Analysis copied to clipboard', 'success');
      }).catch(function(err) {
        console.error('Failed to copy: ', err);
        CockpitAI.showToast('Failed to copy to clipboard', 'danger');
      });
    } else {
      // Fallback for older browsers
      var textArea = document.createElement('textarea');
      textArea.value = text;
      textArea.style.position = 'fixed';
      textArea.style.left = '-999999px';
      document.body.appendChild(textArea);
      textArea.select();
      try {
        document.execCommand('copy');
        CockpitAI.showToast('Analysis copied to clipboard', 'success');
      } catch (err) {
        console.error('Failed to copy: ', err);
        CockpitAI.showToast('Failed to copy to clipboard', 'danger');
      }
      document.body.removeChild(textArea);
    }
  };

  /**
   * Initialize tooltips
   */
  CockpitAI.initTooltips = function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  };

  /**
   * Initialize on document ready
   */
  $(document).ready(function() {
    CockpitAI.initTooltips();
  });

})(jQuery);
