<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

use function defined;

/**
 * UIGenerator
 *
 * Manages GPT UI component generation.
 * Extracted from Gpt.php as part of code refactoring (Task 9).
 *
 * Responsibilities:
 * - Generate modal menu HTML
 * - Generate CKEditor integration scripts
 * - Generate chat interface components
 * - Generate JavaScript configuration
 */
class UIGenerator
{
  /**
   * Generates and returns the HTML for the GPT modal menu. The menu includes a chat interface triggered by a modal,
   * along with an option to toggle saving chat data. It verifies certain conditions such as the state of the ChatGPT
   * module and the presence of an API key before rendering the menu.
   *
   * @return string HTML content for the GPT modal menu
   */
  public static function gptModalMenu(): string
  {
    $output = '';
    $menu = '';
    $script = '';

    $output .= '<link rel="stylesheet" href="' . CLICSHOPPING::link('css/RAG/chat_feedback.css') . '">' . "\n";

    if (defined('CLICSHOPPING_APP_CHATGPT_CH_STATUS') && CLICSHOPPING_APP_CHATGPT_CH_STATUS == 'True') {
      $menu .= '
    <span class="col-md-2">
        <!-- Modal Chat avec Feedback -->
        <a href="#chatModal" data-bs-toggle="modal" data-bs-target="#chatModal"><span class="text-white"><i class="bi bi-chat-left-dots-fill" title="' . CLICSHOPPING::getDef('text_chat_open') . '"></i><span></a>
        <div class="modal fade modal-right" id="chatModal" tabindex="-1" role="dialog" aria-labelledby="chatModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="chatModalLabel">' . CLICSHOPPING::getDef('text_chat_title') . '</h5>
                        <div class="ms-auto">
                            ' . HTML::button(CLICSHOPPING::getDef('text_chat_close'), null, null, 'secondary', ['params' => 'data-bs-dismiss="modal"'], 'md') . '
                        </div>
                    </div>
                    <div class="modal-body">
                        <div class="mt-1"></div>
                        <div class="mt-1"></div>
                        <div class="mt-1"></div>
                        <div class="card">
                            <div class="input-group">
                                <!-- Container des messages avec ID pour le feedback -->
                                <div class="chat-box-message text-start" id="chat-messages">
                                    <div id="chatGpt-output" class="text-bg-light"></div>
                                    <div class="mt-1"></div>
                                    <div class="col-md-12">
                                        <div class="row">
                                            <span class="col-md-12">
                                                <button id="copyResultButton" class="btn btn-primary btn-sm d-none" data-clipboard-target="#chatGpt-output">
                                                    <i class="bi bi-clipboard" title="' . CLICSHOPPING::getDef('text_copy') . '"></i> ' . CLICSHOPPING::getDef('text_copy') . '
                                                </button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>  
                    </div>
                    <div class="modal-footer">
                         <div class="form-group col-md-12">
                            <textarea class="form-control" id="messageGpt" rows="3" placeholder="' . CLICSHOPPING::getDef('text_chat_message') . '"></textarea>
                        </div>                        
                        <div class="mt-1"></div>
                        <div class="form-group text-end col-md-12">
                            ' . HTML::button(CLICSHOPPING::getDef('text_chat_reset_context'), null, null, 'danger', ['params' => 'id="resetContextGpt"'], 'sm') . '
                            ' . HTML::button(CLICSHOPPING::getDef('text_chat_send'), null, null, 'primary', ['params' => 'id="sendGpt"'], 'sm') . '
                        </div>
                    </div>                        
                </div>
            </div>
        </div>
    </span>
';

      $httpServer = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin');
      $httpPath = CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin');

      $recordUrl = $httpServer . $httpPath . 'ajax/RAG/record_feedback.php';
      $ajaxUrl   = $httpServer . $httpPath . 'ajax/ChatGpt/chatGpt.php';

      $userId     = (int)(AdministratorAdmin::getUserAdminId() ?? 0);
      $languageId = (int)($_SESSION['languages_id'] ?? 1);

      $resetContextUrl = $httpServer . $httpPath . 'ajax/RAG/reset_context.php';
      $maxLength = 1000;

      $script .='
<script>
  // Configuration globale du chat modal
  window.CHAT_FEEDBACK_AJAX_URL = "' . $recordUrl . '";

  window.CHAT_CONFIG = {
    ajaxUrl: " ' . $ajaxUrl . '",
    feedbackUrl: "' . $recordUrl . '",
    resetContextUrl: "' . $resetContextUrl . '",
    i18n: {
      loading_analyzing: "' . CLICSHOPPING::getDef('text_chat_loading_analyzing') . '",
      loading_request: "' . CLICSHOPPING::getDef('text_chat_loading_request') . '",
      validation_empty: "' . CLICSHOPPING::getDef('text_chat_validation_empty') . '",
      validation_too_long: "' . CLICSHOPPING::getDef('text_chat_validation_too_long', ['maxLength' => $maxLength]) . '",
      error_prefix: "' . CLICSHOPPING::getDef('text_chat_error_prefix') . '",
      error_config_missing: "' . CLICSHOPPING::getDef('text_chat_error_config_missing') . '",
      error_unknown: "' . CLICSHOPPING::getDef('text_chat_error_unknown') . '",
      error_server: "' . CLICSHOPPING::getDef('text_chat_error_server') . '",
      error_empty_response: "' . CLICSHOPPING::getDef('text_chat_error_empty_response') . '",
      error_invalid_response: "' . CLICSHOPPING::getDef('text_chat_error_invalid_response') . '",
      metrics_confidence_title: "' . CLICSHOPPING::getDef('text_chat_metrics_confidence_title') . '",
      metrics_confidence_label: "' . CLICSHOPPING::getDef('text_chat_metrics_confidence_label') . '",
      metrics_security_title: "' . CLICSHOPPING::getDef('text_chat_metrics_security_title') . '",
      metrics_security_label: "' . CLICSHOPPING::getDef('text_chat_metrics_security_label') . '",
      metrics_hallucination_title: "' . CLICSHOPPING::getDef('text_chat_metrics_hallucination_title') . '",
      metrics_hallucination_label: "' . CLICSHOPPING::getDef('text_chat_metrics_hallucination_label') . '",
      metrics_quality_title: "' . CLICSHOPPING::getDef('text_chat_metrics_quality_title') . '",
      metrics_quality_label: "' . CLICSHOPPING::getDef('text_chat_metrics_quality_label') . '",
      metrics_relevance_title: "' . CLICSHOPPING::getDef('text_chat_metrics_relevance_title') . '",
      metrics_relevance_label: "' . CLICSHOPPING::getDef('text_chat_metrics_relevance_label') . '",
      metrics_fidelity_title: "' . CLICSHOPPING::getDef('text_chat_metrics_fidelity_title') . '",
      metrics_fidelity_label: "' . CLICSHOPPING::getDef('text_chat_metrics_fidelity_label') . '",
      metrics_to_verify_title: "' . CLICSHOPPING::getDef('text_chat_metrics_to_verify_title') . '",
      metrics_to_verify_label: "' . CLICSHOPPING::getDef('text_chat_metrics_to_verify_label') . '",
      reset_confirm: "' . CLICSHOPPING::getDef('text_chat_reset_confirm') . '",
      reset_loading: "' . CLICSHOPPING::getDef('text_chat_reset_loading') . '",
      reset_success_title: "' . CLICSHOPPING::getDef('text_chat_reset_success_title') . '",
      reset_success_body: "' . CLICSHOPPING::getDef('text_chat_reset_success_body') . '",
      error_context_prefix: "' . CLICSHOPPING::getDef('text_chat_error_context_prefix') . '",
      error_context_unknown: "' . CLICSHOPPING::getDef('text_chat_error_context_unknown') . '",
      error_context_missing_output: "' . CLICSHOPPING::getDef('text_chat_error_context_missing_output') . '",
      clarification_title: "' . CLICSHOPPING::getDef('text_chat_clarification_title') . '",
      clarification_placeholder: "' . CLICSHOPPING::getDef('text_chat_clarification_placeholder') . '",
      clarification_send: "' . CLICSHOPPING::getDef('text_chat_clarification_send') . '",
      clarification_info: "' . CLICSHOPPING::getDef('text_chat_clarification_info') . '",
      clarification_error_missing: "' . CLICSHOPPING::getDef('text_chat_clarification_error_missing') . '",
      clarification_error_empty: "' . CLICSHOPPING::getDef('text_chat_clarification_error_empty') . '",
      clarification_error_request: "' . CLICSHOPPING::getDef('text_chat_clarification_error_request') . '",
      clarification_status_sending: "' . CLICSHOPPING::getDef('text_chat_clarification_status_sending') . '",
      clarification_status_received: "' . CLICSHOPPING::getDef('text_chat_clarification_status_received') . '",
      clarification_error_invalid_response: "' . CLICSHOPPING::getDef('text_chat_clarification_error_invalid_response') . '",
      clarification_response_prefix: "' . CLICSHOPPING::getDef('text_chat_clarification_response_prefix') . '",
      clarification_retry: "' . CLICSHOPPING::getDef('text_chat_clarification_retry') . '"
    },
    userId: ' . $userId . ',
    languageId:  ' . $languageId . ',
    enableFeedback: true,
    enableDiagnostics: true,
    enableWebSearch: true,
    showConfidence: true,
    showTypeBadge: true,
    autoScroll: true,
    modalMode: true
  };
</script>
';

      // Charger les scripts JavaScript
      $script .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.2.7/purify.min.js"></script>' . "\n";
      $script .= '<script src="' . HTTP::getShopUrlDomain() . 'ext/javascript/clicshopping/ClicShoppingAdmin/ChatGpt/chat_clarification.js"></script>' . "\n";
      $script .= '<script src="' . HTTP::getShopUrlDomain() . 'ext/javascript/clicshopping/ClicShoppingAdmin/ChatGpt/chat_send.js"></script>' . "\n";
      $script .= '<script src="' . HTTP::getShopUrlDomain() . 'ext/javascript/clicshopping/ClicShoppingAdmin/ChatGpt/chat_feedback.js"></script>' . "\n";
      $script .= '<script src="' . HTTP::getShopUrlDomain() . 'ext/javascript/clicshopping/ClicShoppingAdmin/ChatGpt/chat_reset_context.js"></script>' . "\n";
    }

    return $output . $menu . $script;
  }

  /**
   * Generates and returns the parameters and script configuration for integrating a ChatGPT model into CKEditor.
   *
   * @return string|bool Returns the generated script as a string if successful, otherwise, returns false.
   */
  public static function gptCkeditorParameters(): string|bool
  {
    $model = CLICSHOPPING_APP_CHATGPT_CH_MODEL;

    $url = "https://api.openai.com/v1/chat/completions";

    $organization = '';
    if (!empty(CLICSHOPPING_APP_CHATGPT_CH_ORGANIZATION)) {
      $organization = 'let organizationGpt = "' . CLICSHOPPING_APP_CHATGPT_CH_ORGANIZATION . '";';
    }

    $script = '<script>
 let apiGptUrl = "' . $url . '";
 ' . $organization . '
 let modelGpt = "' . $model . '";
 let temperatureGpt = parseFloat("' . (float)CLICSHOPPING_APP_CHATGPT_CH_TEMPERATURE . '");
 let top_p_gpt = parseFloat("' . (float)CLICSHOPPING_APP_CHATGPT_CH_TOP_P . '");
 let frequency_penalty_gpt = parseFloat("' . (float)CLICSHOPPING_APP_CHATGPT_CH_FREQUENCY_PENALITY . '");
 let presence_penalty_gpt = parseFloat("' . (float)CLICSHOPPING_APP_CHATGPT_CH_PRESENCE_PENALITY . '");
 let max_tokens_gpt = parseInt("' . (int)CLICSHOPPING_APP_CHATGPT_CH_MAX_TOKEN . '");
 let reasoning_effort_gpt = "' . CLICSHOPPING_APP_CHATGPT_CH_REASONING_EFFORT . '";
 let verbosity_gpt = "' . CLICSHOPPING_APP_CHATGPT_CH_VERBOSITY . '";
 let nGpt = parseInt("' . (int)CLICSHOPPING_APP_CHATGPT_CH_MAX_RESPONSE . '");
 let best_of_gpt = parseInt("' . (int)CLICSHOPPING_APP_CHATGPT_CH_BESTOFF . '");
 let titleGpt = "' . CLICSHOPPING::getDef('text_chat_title') . '";
</script>';

    $script .= '<script>
 function callChatGpt(prompt, callback) {
   const payload = {
     prompt: prompt,
     model: modelGpt
   };

   if (modelGpt.startsWith("gpt-5-")) {
     payload.max_output_tokens = max_tokens_gpt;
     payload.reasoning_effort = reasoning_effort_gpt;
     payload.verbosity = verbosity_gpt;     
   } else {
     payload.temperature = temperatureGpt;
     payload.top_p = top_p_gpt;
     payload.frequency_penalty = frequency_penalty_gpt;
     payload.presence_penalty = presence_penalty_gpt;
     payload.max_tokens = max_tokens_gpt;
     payload.n = nGpt;
     payload.best_of = best_of_gpt;
   }

   fetch(apiGptUrl, {
     method: "POST",
     headers: {
       "Content-Type": "application/json"
     },
     body: JSON.stringify(payload)
   })
   .then(response => response.json())
   .then(data => callback(data))
   .catch(error => console.error("Erreur GPT :", error));
 }
</script>';

    $script .= '<!--start wysiwig preloader--><style>.blur {filter: blur(1px);opacity: 0.4;}</style><!--end wysiwzg preloader-->';
    $script .= '<script src="' . CLICSHOPPING::link('Shop/ext/javascript/cKeditor/dialogs/chatgpt.js') . '"></script>';

    return $script;
  }
}
