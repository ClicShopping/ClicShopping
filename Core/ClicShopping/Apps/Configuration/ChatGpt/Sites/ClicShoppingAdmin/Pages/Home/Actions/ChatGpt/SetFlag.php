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

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('ChatGpt');
  }

  public function execute()
  {
    if (isset($_GET['cID'], $_GET['field'])) {
      $id = (int)$_GET['cID'];
      $field = HTML::sanitize($_GET['field']);
      $flag = (int)($_GET['flag'] ?? 0);

      if ($field === 'status') {
        AiModelsAdmin::setStatus($id, $flag);
      } elseif ($field === 'default') {
        AiModelsAdmin::setDefault($id);
      } elseif ($field === 'fallback') {
        AiModelsAdmin::setFallback($id);
      }
    }

    $this->app->redirect('ChatGpt');
  }
}
