<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Modules\Sites\ClicShoppingAdmin\Pages\Home\Actions\Modules;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Modules');
  }

  public function execute()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (isset($_GET['Update'])) {
      $set = $_GET['set'] ?? '';

      $configuration = $_POST['configuration'] ?? [];

      if (\is_array($configuration)) {
        foreach ($configuration as $key => $value) {
          $key = HTML::sanitize($key);

          if (\is_array($value)) {
            $pages = '';

            foreach ($value as $item) {
              $pages .= HTML::sanitize(\is_scalar($item) ? (string)$item : '');
            }

            $configuration_value = $pages;
          } else {
            $configuration_value = $value;
          }

          $CLICSHOPPING_Db->save('configuration', ['configuration_value' => $configuration_value], ['configuration_key' => $key]);
        }
      }

      Cache::clear('configuration');

      $this->app->redirect('Modules&set=' . $set . '&module=' . ($_GET['module'] ?? ''));
    }
  }
}
