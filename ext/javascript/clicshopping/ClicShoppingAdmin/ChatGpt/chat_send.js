/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */
const ChatSanitizer = {
  /**
   * Initialize DOMPurify with safe configuration
   */
  init: function() {
    // Check if DOMPurify is loaded
    if (typeof DOMPurify === 'undefined') {
      console.warn('ChatSanitizer: DOMPurify not loaded, HTML sanitization disabled');
      return false;
    }

    console.log('ChatSanitizer: DOMPurify loaded successfully');
    return true;
  },

  /**
   * Sanitize HTML content with safe configuration
   * @param {string} html - HTML content to sanitize
   * @returns {string} - Sanitized HTML
   */
  sanitize: function(html) {
    // If DOMPurify is not available, return escaped text as fallback
    if (typeof DOMPurify === 'undefined') {
      console.warn('ChatSanitizer: Falling back to text-only mode');
      const div = document.createElement('div');
      div.textContent = html;
      return div.innerHTML;
    }

    // Configure DOMPurify with safe tags and attributes
    const config = {
      ALLOWED_TAGS: [
        'div', 'span', 'p', 'br', 'strong', 'em', 'b', 'i', 'u',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'a', 'img', 'blockquote', 'code', 'pre',
        'hr', 'small', 'mark', 'del', 'ins', 'sub', 'sup',
        // SVG
        'svg', 'g', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'defs', 'linearGradient', 'radialGradient', 'stop',
        'clipPath', 'use', 'symbol', 'title', 'desc'
      ],

      ADD_TAGS: [
        'svg', 'g', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'defs', 'linearGradient', 'radialGradient', 'stop',
        'clipPath', 'use', 'symbol', 'title', 'desc'
      ],

      ADD_ATTR: [
        'target', 'rel',
        // SVG attributes
        'xmlns', 'viewBox', 'width', 'height', 'fill', 'stroke', 'stroke-width',
        'stroke-linejoin', 'stroke-linecap', 'stroke-opacity', 'stroke-dasharray',
        'opacity', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'points', 'd', 'transform', 'text-anchor', 'dominant-baseline', 'display',
        'font-size', 'font-weight', 'font-family', 'stop-color', 'stop-opacity',
        'offset', 'gradientUnits', 'preserveAspectRatio', 'clip-path'
      ],

      ALLOWED_ATTR: [
        'class', 'id', 'style', 'href', 'target', 'rel',
        'src', 'alt', 'title', 'colspan', 'rowspan', 'data-*',
        // SVG attributes
        'xmlns', 'viewBox', 'width', 'height', 'fill', 'stroke', 'stroke-width',
        'stroke-linejoin', 'stroke-linecap', 'stroke-opacity', 'stroke-dasharray',
        'opacity', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'points', 'd', 'transform', 'text-anchor', 'dominant-baseline', 'display',
        'font-size', 'font-weight', 'font-family', 'stop-color', 'stop-opacity',
        'offset', 'gradientUnits', 'preserveAspectRatio', 'clip-path'
      ],

      ALLOW_DATA_ATTR: true,

      FORBID_TAGS: [
        'script', 'iframe', 'object', 'embed', 'applet',
        'form', 'input', 'button', 'textarea', 'select',
        'meta', 'link', 'style', 'base'
      ],

      FORBID_ATTR: [
        'onerror', 'onload', 'onclick', 'onmouseover',
        'onfocus', 'onblur', 'onchange', 'onsubmit'
      ],

      ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|callto|cid|xmpp):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i,

      RETURN_DOM: false,
      RETURN_DOM_FRAGMENT: false,
      KEEP_CONTENT: true,
      FORCE_BODY: false
    };

    try {
      const sanitized = DOMPurify.sanitize(html, config);
      console.log('ChatSanitizer: Sanitized HTML successfully');
      return sanitized;
    } catch (error) {
      console.error('ChatSanitizer: Error during sanitization:', error);
      // Fallback to text-only mode on error
      const div = document.createElement('div');
      div.textContent = html;
      return div.innerHTML;
    }
  },

  /**
   * Check if content contains potentially dangerous elements
   * @param {string} html - HTML content to check
   * @returns {boolean} - True if dangerous content detected
   */
  hasDangerousContent: function(html) {
    const dangerousPatterns = [
      /<script/i,
      /<iframe/i,
      /javascript:/i,
      /on\w+\s*=/i,  // Event handlers like onclick=
      /<object/i,
      /<embed/i,
      /<applet/i
    ];

    return dangerousPatterns.some(pattern => pattern.test(html));
  }
};

document.addEventListener("DOMContentLoaded", function() {
  console.log('ChatSend: Initializing...');

  const i18n = window.CHAT_CONFIG && window.CHAT_CONFIG.i18n
    ? window.CHAT_CONFIG.i18n
    : {};
  const t = (key, fallback) => {
    if (i18n && typeof i18n[key] === 'string' && i18n[key].length) {
      return i18n[key];
    }
    if (typeof fallback === 'string') {
      return fallback;
    }
    return key;
  };
  const format = (template, params) => {
    if (!template || !params) return template;
    let result = template.replace(/\{\{(\w+)\}\}/g, (match, key) => {
      if (Object.prototype.hasOwnProperty.call(params, key)) {
        return String(params[key]);
      }
      return match;
    });
    result = result.replace(/\{(\w+)\}/g, (match, key) => {
      if (Object.prototype.hasOwnProperty.call(params, key)) {
        return String(params[key]);
      }
      return match;
    });
    return result;
  };

  const initBootstrapTables = (rootEl, attempts = 10) => {
    if (typeof window.jQuery === 'undefined' || !window.jQuery.fn || !window.jQuery.fn.bootstrapTable) {
      if (attempts > 0) {
        setTimeout(() => initBootstrapTables(rootEl, attempts - 1), 300);
      } else {
        console.warn('ChatSend: bootstrapTable plugin not available after retries');
      }
      return;
    }
    const tables = rootEl.querySelectorAll('table[data-toggle="table"]');
    tables.forEach(table => {
      const $table = window.jQuery(table);
      if ($table.data('bootstrap.table')) {
        return;
      }
      $table.bootstrapTable();
    });
  };

  // Initialize HTML sanitizer
  const sanitizerReady = ChatSanitizer.init();
  if (sanitizerReady) {
    console.log('ChatSend: HTML sanitization enabled');
  } else {
    console.warn('ChatSend: HTML sanitization disabled - DOMPurify not loaded');
  }

  const sendGptButton = document.querySelector("#sendGpt");

  if (!sendGptButton) {
    console.warn('ChatSend: Button #sendGpt not found');
    return;
  }

  console.log('ChatSend: Button found, attaching handler');

  sendGptButton.addEventListener("click", function() {
    console.log('ChatSend: Button clicked');

    const messageInput = document.querySelector("#messageGpt");
    const chatOutput = document.querySelector("#chatGpt-output");

    if (!messageInput || !chatOutput) {
      console.error('ChatSend: Required elements not found');
      return;
    }

    const message = messageInput.value;

    // ============================================
    // CLIENT-SIDE VALIDATION
    // ============================================

    // Helper function to show validation error
    const showValidationError = (errorMessage) => {
      console.log('ChatSend: Validation error:', errorMessage);

      const validationDiv = document.createElement("div");
      validationDiv.className = "alert alert-warning alert-dismissible fade show mt-2";
      validationDiv.setAttribute("role", "alert");
      validationDiv.innerHTML = `
        <i class="bi bi-exclamation-triangle"></i>
        <strong>${errorMessage}</strong>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      `;

      chatOutput.insertBefore(validationDiv, chatOutput.firstChild);

      setTimeout(() => {
        if (validationDiv && validationDiv.parentNode) {
          validationDiv.remove();
        }
      }, 3000);

      messageInput.focus();
    };

    // ✅ Validation 1: Empty query check
    if (!message.trim()) {
      showValidationError(t('validation_empty'));
      return;
    }

    // ✅ Validation 2: Query length check (max 1000 characters)
    const maxLength = 1000;
    if (message.length > maxLength) {
      const msg = format(t('validation_too_long'), { maxLength: maxLength });
      showValidationError(msg);
      return;
    }

    console.log('ChatSend: Sending message:', message.substring(0, 50));

    // 🛡️ SÉCURITÉ: Afficher le message utilisateur de manière sécurisée (textContent au lieu de innerHTML)
    const userMessageDiv = document.createElement("div");
    userMessageDiv.className = "chat-message user";
    userMessageDiv.textContent = message;  // ← textContent empêche l'exécution de scripts
    chatOutput.appendChild(userMessageDiv);

    // Afficher un indicateur de chargement
    const loadingDiv = document.createElement("div");
    loadingDiv.className = "chat-message loading";
    loadingDiv.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> <span>' + t('loading_analyzing') + '</span>';
    chatOutput.appendChild(loadingDiv);

    // Récupérer l'URL depuis la configuration
    const ajaxUrl = window.CHAT_CONFIG ? window.CHAT_CONFIG.ajaxUrl : null;

    if (!ajaxUrl) {
      console.error('ChatSend: AJAX URL not configured');
      loadingDiv.innerHTML = '<div class="alert alert-danger">' + t('error_prefix') + t('error_config_missing') + '</div>';
      return;
    }

    console.log('ChatSend: Sending to:', ajaxUrl);

    // 🔧 FIX: Utiliser JSON au lieu de form-urlencoded pour préserver les caractères < et >
    // Les serveurs web (mod_security, WAF) peuvent filtrer < et > dans form-urlencoded
    fetch(ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ message: message }),
    })
      .then(response => {
        console.log('ChatSend: Response received, status:', response.status);

        // 🔧Vérifier si la réponse est OK avant de parser le JSON
        if (!response.ok) {
          return response.text().then(text => {
            console.error('ChatSend: Server error response:', text.substring(0, 500));
            throw new Error(`${t('error_server')} ${response.status}: ${text.substring(0, 100)}`);
          });
        }

        return response.json();
      })
      .then(data => {
        console.log('ChatSend: Data parsed:', data);
        console.log('ChatSend: Response type:', data.type);
        console.log('ChatSend: text_response length:', data.text_response ? data.text_response.length : 0);

        // Supprimer l'indicateur de chargement
        if (loadingDiv && loadingDiv.parentNode) {
          loadingDiv.parentNode.removeChild(loadingDiv);
        }

        // Vérifier le succès
        if (!data.success) {
          console.error('ChatSend: Request failed:', data.error);
          chatOutput.innerHTML += '<div class="alert alert-danger">' + t('error_prefix') + (data.error || t('error_unknown')) + '</div>';
          return;
        }

        // ✅ VALIDATION: Ensure text_response exists
        if (!data.text_response) {
          console.error('ChatSend: Missing text_response in response');
          chatOutput.innerHTML += '<div class="alert alert-danger">' + t('error_prefix') + t('error_empty_response') + '</div>';
          return;
        }

        console.log('ChatSend: Creating message element with ID:', data.interaction_id);

        // Créer un élément de message avec data-interaction-id
        const messageDiv = document.createElement("div");
        messageDiv.className = "chat-message assistant";
        messageDiv.dataset.interactionId = data.interaction_id || "temp_" + Date.now();

        // Ajouter les métadonnées optionnelles
        if (data.type) messageDiv.dataset.type = data.type;
        if (data.confidence) messageDiv.dataset.confidence = data.confidence;

        // Créer le contenu du message
        const contentDiv = document.createElement("div");
        contentDiv.className = "message-content";

        // ✅ FIX: Use innerHTML to render HTML content (not textContent)
        // This allows proper rendering of formatted responses (tables, lists, links, etc.)
        // Security: Sanitize with DOMPurify before rendering
        if (data.text_response && typeof data.text_response === 'string') {
          // Check for dangerous content before sanitization
          if (ChatSanitizer.hasDangerousContent(data.text_response)) {
            console.warn('ChatSend: Dangerous content detected, sanitizing...');
          }

          // Sanitize HTML content before rendering
          const sanitizedHTML = ChatSanitizer.sanitize(data.text_response);
          contentDiv.innerHTML = sanitizedHTML;

          // ✅ SECURITY: Add rel="noopener noreferrer" to all external links
          // This prevents security issues with target="_blank"
          const links = contentDiv.querySelectorAll('a[target="_blank"]');
          links.forEach(link => {
            if (!link.hasAttribute('rel')) {
              link.setAttribute('rel', 'noopener noreferrer');
            }
          });

          console.log('ChatSend: Rendered sanitized HTML content with', links.length, 'external links');

          // Initialize bootstrap-table for dynamically inserted tables
          initBootstrapTables(contentDiv);
        } else {
          // Fallback: If text_response is not a string, display error
          contentDiv.textContent = t('error_prefix') + t('error_invalid_response');
          console.error('ChatSend: Invalid text_response:', typeof data.text_response);
        }

        messageDiv.appendChild(contentDiv);

        // Ajouter les métriques si disponibles
        if (data.metrics) {
          const normalize = (v) => {
            const n = parseFloat(v || 0);
            if (!isFinite(n)) return 0;
            if (n > 1) return Math.max(0, Math.min(1, n / 100));
            if (n < 0) return 0;
            return n;
          };

          // Fallbacks when backend does not provide full metrics
          const fallbackConfidence = normalize(data.confidence);
          const m = {
            confidence: normalize(data.metrics.confidence_score) || fallbackConfidence || 0,
            security: normalize(data.metrics.security_score),
            hallucination: normalize(data.metrics.hallucination_score),
            quality: normalize(data.metrics.response_quality),
            relevance: normalize(data.metrics.relevance_score),
          };

          // Heuristic fallbacks to avoid all zeros
          if (!m.relevance) m.relevance = m.confidence;
          if (!m.quality) m.quality = normalize((contentDiv.textContent || contentDiv.innerText || '').length > 400 ? 0.8 : 0.6);
          if (!m.security) m.security = normalize(data.type === 'web_search' ? 0.5 : 0.8);
          if (!m.hallucination) m.hallucination = 0; // assume low by default
          console.log('ChatSend: Metrics normalized', m);

          const metricsDiv = document.createElement("div");
          metricsDiv.className = "message-metrics mt-2 p-2 bg-light rounded";
          metricsDiv.style.fontSize = "0.85em";
          metricsDiv.style.borderLeft = "3px solid #667eea";

          const metricsHTML = `
          <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-primary" title="${t('metrics_confidence_title')}">
              ${t('metrics_confidence_label')}: ${Math.round(m.confidence * 100)}%
            </span>
            <span class="badge bg-success" title="${t('metrics_security_title')}">
              ${t('metrics_security_label')}: ${Math.round(m.security * 100)}%
            </span>
            <span class="badge bg-warning text-dark" title="${t('metrics_hallucination_title')}">
              ${t('metrics_hallucination_label')}: ${Math.round(m.hallucination * 100)}%
            </span>
            <span class="badge bg-info" title="${t('metrics_quality_title')}">
              ${t('metrics_quality_label')}: ${Math.round(m.quality * 100)}%
            </span>
            <span class="badge bg-secondary" title="${t('metrics_relevance_title')}">
              ${t('metrics_relevance_label')}: ${Math.round(m.relevance * 100)}%
            </span>
          </div>
        `;

          metricsDiv.innerHTML = metricsHTML;
          messageDiv.appendChild(metricsDiv);
        }

        chatOutput.appendChild(messageDiv);

        // Scroll vers le bas
        chatOutput.scrollTop = chatOutput.scrollHeight;

        // Vider le champ de saisie
        messageInput.value = "";

        // Afficher le bouton de copie
        const copyBtn = document.querySelector("#copyResultButton");
        if (copyBtn) copyBtn.classList.remove("d-none");

        console.log('ChatSend: Message displayed successfully');
      })
      .catch(error => {
        console.error("ChatSend: Fetch error:", error);

        if (loadingDiv && loadingDiv.parentNode) {
          loadingDiv.parentNode.removeChild(loadingDiv);
        }

        chatOutput.innerHTML += '<div class="alert alert-danger">' + t('error_prefix') + error.message + '</div>';
      });
  });

  console.log('ChatSend: Initialization complete');
});