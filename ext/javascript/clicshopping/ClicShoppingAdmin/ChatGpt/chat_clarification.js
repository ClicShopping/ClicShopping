/**
 * Chat Clarification Module
 * 
 * Handles ambiguous queries and displays clarification questions
 * with clickable options for user disambiguation
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

(function() {
  'use strict';

  const i18n = window.CHAT_CONFIG && window.CHAT_CONFIG.i18n
    ? window.CHAT_CONFIG.i18n
    : {};
  const t = (key, fallback) => {
    if (i18n && typeof i18n[key] === 'string' && i18n[key].length) {
      return i18n[key];
    }
    return fallback;
  };

  /**
   * ChatClarification Class
   */
  class ChatClarification {
    constructor() {
      this.clarificationHistory = [];
      this.debug = true; // Set to false in production
    }

    /**
     * Check if response is a clarification request
     * 
     * @param {Object} response AJAX response
     * @returns {boolean} True if clarification needed
     */
    isClarificationNeeded(response) {
      return response && response.type === 'clarification_needed';
    }

    /**
     * Display clarification question with options
     * 
     * @param {Object} clarificationData Clarification data from server
     * @param {HTMLElement} container Container element
     * @param {Function} onOptionSelected Callback when option is selected
     */
    displayClarification(clarificationData, container, onOptionSelected) {
      if (!clarificationData || !clarificationData.clarification) {
        console.error(t('clarification_error_invalid_response', 'Invalid clarification data'));
        return;
      }

      const clarification = clarificationData.clarification;
      const ambiguity = clarificationData.ambiguity;

      // Create clarification container
      const clarificationDiv = document.createElement('div');
      clarificationDiv.className = 'chat-clarification';
      clarificationDiv.style.cssText = `
        background-color: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 15px;
        margin: 10px 0;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      `;

      // Add icon
      const icon = document.createElement('div');
      icon.innerHTML = '❓';
      icon.style.cssText = `
        font-size: 24px;
        margin-bottom: 10px;
      `;
      clarificationDiv.appendChild(icon);

      // Add question
      const question = document.createElement('div');
      question.className = 'clarification-question';
      question.textContent = clarification.question;
      question.style.cssText = `
        font-weight: bold;
        margin-bottom: 15px;
        color: #856404;
      `;
      clarificationDiv.appendChild(question);

      // Add options as buttons
      if (clarification.options && clarification.options.length > 0) {
        const optionsContainer = document.createElement('div');
        optionsContainer.className = 'clarification-options';
        optionsContainer.style.cssText = `
          display: flex;
          flex-wrap: wrap;
          gap: 10px;
        `;

        clarification.options.forEach((option, index) => {
          const button = document.createElement('button');
          button.className = 'btn btn-sm btn-warning clarification-option';
          button.textContent = option;
          button.style.cssText = `
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid #ffc107;
            background-color: #fff;
            color: #856404;
            cursor: pointer;
            transition: all 0.3s ease;
          `;

          // Hover effect
          button.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#ffc107';
            this.style.color = '#fff';
          });

          button.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '#fff';
            this.style.color = '#856404';
          });

          // Click handler
          button.addEventListener('click', () => {
            this.handleOptionSelected(option, clarification, onOptionSelected);
          });

          optionsContainer.appendChild(button);
        });

        clarificationDiv.appendChild(optionsContainer);
      }

      // Add manual input option
      const manualInputDiv = document.createElement('div');
      manualInputDiv.style.cssText = `
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #ffc107;
      `;

      const manualLabel = document.createElement('div');
      manualLabel.textContent = t('clarification_info', 'Ou saisissez votre réponse :');
      manualLabel.style.cssText = `
        font-size: 12px;
        color: #856404;
        margin-bottom: 5px;
      `;
      manualInputDiv.appendChild(manualLabel);

      const inputGroup = document.createElement('div');
      inputGroup.style.cssText = `
        display: flex;
        gap: 10px;
      `;

      const input = document.createElement('input');
      input.type = 'text';
      input.className = 'form-control form-control-sm';
      input.placeholder = t('clarification_placeholder', 'Votre réponse...');
      input.style.cssText = `
        flex: 1;
      `;

      const submitBtn = document.createElement('button');
      submitBtn.className = 'btn btn-sm btn-primary';
      submitBtn.textContent = t('clarification_send', 'Envoyer');
      submitBtn.addEventListener('click', () => {
        const value = input.value.trim();
        if (value) {
          this.handleOptionSelected(value, clarification, onOptionSelected);
        }
      });

      // Enter key handler
      input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          submitBtn.click();
        }
      });

      inputGroup.appendChild(input);
      inputGroup.appendChild(submitBtn);
      manualInputDiv.appendChild(inputGroup);
      clarificationDiv.appendChild(manualInputDiv);

      // Store in history
      this.clarificationHistory.push({
        timestamp: Date.now(),
        clarification: clarification,
        ambiguity: ambiguity
      });

      // Add to container
      container.appendChild(clarificationDiv);

      // Focus on input
      setTimeout(() => input.focus(), 100);

      if (this.debug) {
        console.log('Clarification displayed:', clarification);
      }
    }

    /**
     * Handle option selection
     * 
     * @param {string} option Selected option
     * @param {Object} clarification Clarification data
     * @param {Function} callback Callback function
     */
    handleOptionSelected(option, clarification, callback) {
      if (this.debug) {
        console.log('Option selected:', option);
      }

      // Build clarified query
      const clarifiedQuery = this.buildClarifiedQuery(
        clarification.original_query,
        option,
        clarification.ambiguity_type
      );

      // Call callback with clarified query
      if (callback && typeof callback === 'function') {
        callback(clarifiedQuery, option);
      }
    }

    /**
     * Build clarified query from original query and selected option
     * 
     * @param {string} originalQuery Original ambiguous query
     * @param {string} option Selected option
     * @param {string} ambiguityType Type of ambiguity
     * @returns {string} Clarified query
     */
    buildClarifiedQuery(originalQuery, option, ambiguityType) {
      switch (ambiguityType) {
        case 'missing_parameters':
          // Append the option to the original query
          return `${originalQuery} ${option}`;

        case 'multiple_entities':
          // Replace ambiguous reference with specific entity
          return `${originalQuery} (${option})`;

        case 'unresolved_reference':
          // Replace pronoun with specific reference
          return option;

        case 'low_confidence':
          // Use the option as new query
          return option;

        default:
          return `${originalQuery} - ${option}`;
      }
    }

    /**
     * Get clarification history
     * 
     * @returns {Array} History of clarifications
     */
    getHistory() {
      return this.clarificationHistory;
    }

    /**
     * Clear clarification history
     */
    clearHistory() {
      this.clarificationHistory = [];
    }
  }

  // Export to global scope
  window.ChatClarification = ChatClarification;

  // Auto-initialize if jQuery is available
  if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function() {
      console.log('ChatClarification module loaded');
    });
  }
})();
