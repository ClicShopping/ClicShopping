/**
 * Chat Feedback System
 * 
 * Permet aux utilisateurs de donner un feedback sur les réponses du chat
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

(function() {
  'use strict';

  const ChatFeedback = {
    /**
     * Initialize feedback system
     */
    init: function() {
      console.log('ChatFeedback: Initializing...');
      
      // Add feedback buttons to existing messages
      this.addFeedbackToExistingMessages();
      
      // Listen for new messages
      this.observeNewMessages();
    },

    /**
     * Add feedback buttons to all existing assistant messages
     */
    addFeedbackToExistingMessages: function() {
      const messages = document.querySelectorAll('.chat-message.assistant, .message.assistant');
      messages.forEach(message => {
        if (!message.querySelector('.feedback-buttons')) {
          const interactionId = message.dataset.interactionId || this.generateTempId();
          this.addFeedbackButtons(message, interactionId);
        }
      });
    },

    /**
     * Observe DOM for new messages
     */
    observeNewMessages: function() {
      const chatContainer = document.querySelector('#chat-messages, .chat-container, .messages-container');
      if (!chatContainer) {
        console.warn('ChatFeedback: Chat container not found');
        return;
      }

      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          mutation.addedNodes.forEach((node) => {
            if (node.nodeType === 1 && node.classList && 
                (node.classList.contains('assistant') || node.classList.contains('message'))) {
              const interactionId = node.dataset.interactionId || this.generateTempId();
              this.addFeedbackButtons(node, interactionId);
            }
          });
        });
      });

      observer.observe(chatContainer, { childList: true, subtree: true });
    },

    /**
     * Add feedback buttons to a message element
     */
    addFeedbackButtons: function(messageElement, interactionId) {
      // Check if buttons already exist
      if (messageElement.querySelector('.feedback-buttons')) {
        return;
      }

      const feedbackContainer = document.createElement('div');
      feedbackContainer.className = 'feedback-container';
      feedbackContainer.innerHTML = `
        <div class="feedback-buttons" data-interaction-id="${interactionId}">
          <button class="btn btn-sm btn-feedback btn-positive" title="Utile">
            <i class="bi bi-hand-thumbs-up"></i> Utile
          </button>
          <button class="btn btn-sm btn-feedback btn-negative" title="Pas utile">
            <i class="bi bi-hand-thumbs-down"></i> Pas utile
          </button>
        </div>
        <div class="feedback-form" style="display:none;">
          <div class="form-group mt-2">
            <textarea class="form-control" rows="3" placeholder="Que pouvons-nous améliorer ? (optionnel)"></textarea>
          </div>
          <div class="mt-2">
            <button class="btn btn-sm btn-primary btn-submit-feedback">Envoyer</button>
            <button class="btn btn-sm btn-secondary btn-cancel-feedback">Annuler</button>
          </div>
        </div>
        <div class="feedback-confirmation alert alert-success" style="display:none;">
          <i class="bi bi-check-circle"></i> Merci pour votre feedback !
        </div>
      `;

      messageElement.appendChild(feedbackContainer);

      // Attach event listeners
      this.attachEventListeners(feedbackContainer, interactionId);
    },

    /**
     * Attach event listeners to feedback buttons
     */
    attachEventListeners: function(container, interactionId) {
      const btnPositive = container.querySelector('.btn-positive');
      const btnNegative = container.querySelector('.btn-negative');
      const btnSubmit = container.querySelector('.btn-submit-feedback');
      const btnCancel = container.querySelector('.btn-cancel-feedback');

      if (btnPositive) {
        btnPositive.addEventListener('click', () => {
          this.handlePositiveFeedback(interactionId, container);
        });
      }

      if (btnNegative) {
        btnNegative.addEventListener('click', () => {
          this.showFeedbackForm(container);
        });
      }

      if (btnSubmit) {
        btnSubmit.addEventListener('click', () => {
          const textarea = container.querySelector('textarea');
          const feedbackText = textarea ? textarea.value : '';
          this.submitFeedback(interactionId, 'negative', feedbackText, container);
        });
      }

      if (btnCancel) {
        btnCancel.addEventListener('click', () => {
          this.hideFeedbackForm(container);
        });
      }
    },

    /**
     * Handle positive feedback
     */
    handlePositiveFeedback: function(interactionId, container) {
      this.submitFeedback(interactionId, 'positive', '', container);
    },

    /**
     * Show feedback form
     */
    showFeedbackForm: function(container) {
      const buttons = container.querySelector('.feedback-buttons');
      const form = container.querySelector('.feedback-form');
      
      if (buttons) buttons.style.display = 'none';
      if (form) form.style.display = 'block';
    },

    /**
     * Hide feedback form
     */
    hideFeedbackForm: function(container) {
      const buttons = container.querySelector('.feedback-buttons');
      const form = container.querySelector('.feedback-form');
      
      if (form) form.style.display = 'none';
      if (buttons) buttons.style.display = 'block';
    },

    /**
     * Get AJAX URL
     */
    getAjaxUrl: function() {
      // URL will be set by the template
      return window.CHAT_FEEDBACK_AJAX_URL || 'ajax/RAG/record_feedback.php';
    },

    /**
     * Submit feedback to server
     */
    submitFeedback: function(interactionId, feedbackType, feedbackText, container) {
      const data = {
        interaction_id: interactionId,
        feedback_type: feedbackType,
        feedback_text: feedbackText
      };

      // Show loading state
      this.setLoadingState(container, true);

      // Send AJAX request
      fetch(this.getAjaxUrl(), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
      })
      .then(response => response.json())
      .then(result => {
        this.setLoadingState(container, false);
        
        if (result.success) {
          this.showConfirmation(container);
        } else {
          this.showError(container, result.error || 'Erreur lors de l\'enregistrement');
        }
      })
      .catch(error => {
        this.setLoadingState(container, false);
        this.showError(container, 'Erreur de connexion');
        console.error('ChatFeedback error:', error);
      });
    },

    /**
     * Set loading state
     */
    setLoadingState: function(container, isLoading) {
      const buttons = container.querySelectorAll('button');
      buttons.forEach(btn => {
        btn.disabled = isLoading;
      });
    },

    /**
     * Show confirmation message
     */
    showConfirmation: function(container) {
      const buttons = container.querySelector('.feedback-buttons');
      const form = container.querySelector('.feedback-form');
      const confirmation = container.querySelector('.feedback-confirmation');
      
      if (buttons) buttons.style.display = 'none';
      if (form) form.style.display = 'none';
      if (confirmation) {
        confirmation.style.display = 'block';
        
        // Hide after 3 seconds
        setTimeout(() => {
          confirmation.style.display = 'none';
        }, 3000);
      }
    },

    /**
     * Show error message
     */
    showError: function(container, message) {
      alert('Erreur: ' + message);
      this.hideFeedbackForm(container);
    },

    /**
     * Generate temporary ID for messages without interaction_id
     */
    generateTempId: function() {
      return 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
  };

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ChatFeedback.init());
  } else {
    ChatFeedback.init();
  }

  // Expose to global scope if needed
  window.ChatFeedback = ChatFeedback;

})();
