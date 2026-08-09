/**
 * Chat Reset Context Handler
 * Gère la réinitialisation du contexte de conversation
 * 
 * @date 2025-12-02
 */

document.addEventListener("DOMContentLoaded", function() {
  console.log('ChatResetContext: Initializing...');

  const i18n = window.CHAT_CONFIG && window.CHAT_CONFIG.i18n
    ? window.CHAT_CONFIG.i18n
    : {};
  const t = (key, fallback) => {
    if (i18n && typeof i18n[key] === 'string' && i18n[key].length) {
      return i18n[key];
    }
    return fallback;
  };
  
  const resetContextButton = document.querySelector("#resetContextGpt");
  
  if (!resetContextButton) {
    console.warn('ChatResetContext: Button #resetContextGpt not found');
    return;
  }
  
  console.log('ChatResetContext: Button found, attaching handler');
  
  resetContextButton.addEventListener("click", function() {
    console.log('ChatResetContext: Button clicked');
    
    // Confirm with user before resetting context
    if (!confirm(t('reset_confirm', 'Voulez-vous vraiment créer un nouveau contexte? Cela effacera l\'historique de la conversation actuelle.'))) {
      console.log('ChatResetContext: User cancelled');
      return;
    }
    
    // Get chat output element
    const chatOutput = document.querySelector("#chatGpt-output");
    const messageInput = document.querySelector("#messageGpt");
    
    if (!chatOutput) {
      console.error('ChatResetContext: Chat output element not found');
      return;
    }
    
    // Show loading indicator
    const loadingDiv = document.createElement("div");
    loadingDiv.className = "alert alert-info";
    loadingDiv.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> <span>' + t('reset_loading', 'Création d\'un nouveau contexte...') + '</span>';
    chatOutput.appendChild(loadingDiv);
    
    // Get AJAX URL for resetting context
    const ajaxUrl = window.CHAT_CONFIG && window.CHAT_CONFIG.resetContextUrl 
      ? window.CHAT_CONFIG.resetContextUrl 
      : '/ClicShoppingAdmin/ajax/RAG/reset_context.php';
    
    console.log('ChatResetContext: Sending request to:', ajaxUrl);
    
    // Send AJAX request to reset context
    fetch(ajaxUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ 
        action: 'reset_context',
        timestamp: Date.now()
      }),
    })
    .then(response => {
      console.log('ChatResetContext: Response received, status:', response.status);
      
      // Garde partagée : rejette aussi un 200 non-JSON (redirection suivie vers login, page WAF)
      return window.ChatHttp.expectJson(response);
    })
    .then(data => {
      console.log('ChatResetContext: Data parsed:', data);
      
      // Remove loading indicator
      if (loadingDiv && loadingDiv.parentNode) {
        loadingDiv.parentNode.removeChild(loadingDiv);
      }
      
      // Check success
      if (!data.success) {
        console.error('ChatResetContext: Request failed:', data.error);
        chatOutput.innerHTML += '<div class="alert alert-danger">' + t('error_context_prefix', 'Erreur: ') + (data.error || t('error_context_unknown', 'Erreur inconnue')) + '</div>';
        return;
      }
      
      console.log('ChatResetContext: Context reset successful');
      
      // Clear chat output
      chatOutput.innerHTML = '';
      
      // Clear message input
      if (messageInput) {
        messageInput.value = '';
      }
      
      // Display success message
      const successDiv = document.createElement("div");
      successDiv.className = "alert alert-success";
      successDiv.innerHTML = '<strong>' + t('reset_success_title', '✅ Nouveau contexte créé!') + '</strong><br>' + t('reset_success_body', 'Vous pouvez maintenant commencer une nouvelle conversation.');
      chatOutput.appendChild(successDiv);
      
      // Store new context ID if provided
      if (data.new_context_id) {
        console.log('ChatResetContext: New context ID:', data.new_context_id);
        // Store in session storage for future requests
        sessionStorage.setItem('chat_context_id', data.new_context_id);
      }
      
      // Scroll to top
      chatOutput.scrollTop = 0;
      
      console.log('ChatResetContext: Context reset complete');
    })
    .catch(error => {
      console.error("ChatResetContext: Fetch error:", error);
      
      if (loadingDiv && loadingDiv.parentNode) {
        loadingDiv.parentNode.removeChild(loadingDiv);
      }
      
      chatOutput.innerHTML += '<div class="alert alert-danger">' + t('error_context_prefix', 'Erreur: ') + error.message + '</div>';
    });
  });
  
  console.log('ChatResetContext: Initialization complete');
});
