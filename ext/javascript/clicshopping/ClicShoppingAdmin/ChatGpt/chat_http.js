/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * Shared response guard for every chat AJAX call.
 *
 * `response.ok` alone is blind to a 200 that is not JSON: an expired admin session answers 302
 * to login.php, fetch follows it silently, and JSON.parse then fails on "<!DOCTYPE html>" —
 * surfacing a parser message instead of the real cause. A WAF interstitial behaves the same way.
 */
window.ChatHttp = {
  /**
   * Translate a chat i18n key, falling back to the key itself.
   *
   * @param {string} key
   * @param {string} [fallback]
   * @returns {string}
   */
  label: function (key, fallback) {
    const i18n = (window.CHAT_CONFIG && window.CHAT_CONFIG.i18n) ? window.CHAT_CONFIG.i18n : {};

    if (typeof i18n[key] === 'string' && i18n[key].length) {
      return i18n[key];
    }

    return typeof fallback === 'string' ? fallback : key;
  },

  /**
   * Resolve a fetch response to parsed JSON, or throw an error naming the actual cause.
   *
   * @param {Response} response
   * @returns {Promise<Object>}
   */
  expectJson: function (response) {
    if (!response.ok) {
      return response.text().then(function (text) {
        console.error('ChatHttp: Server error response:', text.substring(0, 500));
        throw new Error(window.ChatHttp.label('error_server') + ' ' + response.status + ': ' + text.substring(0, 100));
      });
    }

    const contentType = (response.headers.get('content-type') || '').toLowerCase();

    // A followed redirect, or an HTML body, means the request never reached the chat endpoint.
    if (response.redirected || contentType.indexOf('json') === -1) {
      console.error('ChatHttp: Non-JSON response', {
        redirected: response.redirected,
        url: response.url,
        contentType: contentType
      });

      throw new Error(window.ChatHttp.label('error_session_expired'));
    }

    return response.json();
  }
};
