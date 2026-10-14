<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Sites\ClicShoppingAdmin\Pages\Home\Actions\ChatGpt;

use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\AiModelsAdmin;

class Delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
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
    if (isset($_GET['ChatGpt'], $_GET['Delete'], $_GET['cID']) && is_numeric($_GET['cID'])) {
      $ok = AiModelsAdmin::deleteModel((int)$_GET['cID']);

      if ($ok) {
        $this->messageStack->add('main', $this->app->getDef('success_model_deleted'), 'success');
      } else {
        $this->messageStack->add('main', $this->app->getDef('error_model_delete_default'), 'error');
      }
    }

    $this->app->redirect('ChatGpt');
  }
}
