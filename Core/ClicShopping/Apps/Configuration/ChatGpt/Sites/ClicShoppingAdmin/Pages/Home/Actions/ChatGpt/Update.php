<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Sites\ClicShoppingAdmin\Pages\Home\Actions\ChatGpt;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\AiModelsAdmin;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
  public mixed $messageStack;

  public function __construct()
  {
    $this->app = Registry::get('ChatGpt');
    $this->messageStack = Registry::get('MessageStack');
  }

  public function execute()
  {
    if (isset($_GET['ChatGpt'], $_GET['Update'], $_GET['cID']) && is_numeric($_GET['cID'])) {
      $id = (int)$_GET['cID'];
      $providerId = (int)($_POST['ai_model_provider_id'] ?? 0);
      $technicalName = HTML::sanitize($_POST['model_technical_name'] ?? '');

      if (AiModelsAdmin::modelExists($providerId, $technicalName, $id)) {
        $this->messageStack->add('main', $this->app->getDef('error_model_exists'), 'error');
        $this->app->redirect('Edit&cID=' . $id);
      }

      $data = [
        'ai_model_provider_id' => $providerId,
        'model_technical_name' => $technicalName,
        'model_display_name' => HTML::sanitize($_POST['model_display_name'] ?? ''),
        'ai_model_description' => HTML::sanitize($_POST['ai_model_description'] ?? ''),
        'ai_model_context_window' => (int)($_POST['ai_model_context_window'] ?? 0),
        'ai_model_token_input_price' => (float)($_POST['ai_model_token_input_price'] ?? 0),
        'ai_model_token_output_price' => (float)($_POST['ai_model_token_output_price'] ?? 0),
        'ai_model_ai_capable' => isset($_POST['ai_model_ai_capable']) ? 1 : 0,
        'ai_model_status' => isset($_POST['ai_model_status']) ? 1 : 0,
        'ai_model_status_default' => isset($_POST['ai_model_status_default']) ? 1 : 0,
        'ai_model_status_fallback' => isset($_POST['ai_model_status_fallback']) ? 1 : 0,
      ];

      AiModelsAdmin::updateModel($id, $data);

      if (!empty($_POST['provider_api_key'])) {
        AiModelsAdmin::saveProviderCredential($providerId, $_POST['provider_api_key'], HTML::sanitize($_POST['provider_organisation'] ?? ''));
      }

      $this->messageStack->add('main', $this->app->getDef('success_model_saved'), 'success');
    }

    $this->app->redirect('ChatGpt');
  }
}
