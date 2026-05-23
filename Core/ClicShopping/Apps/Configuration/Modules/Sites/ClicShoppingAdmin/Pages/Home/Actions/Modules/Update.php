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

      foreach ($_POST['configuration'] as $key => $value) {
        if ((\is_array($value)) && (!empty($value))) {
          $key = HTML::sanitize($key);
          $value = HTML::sanitize($value);

          $pages = '';
          $count = \count($value);

          for ($i = 0; $i < $count; $i++) {
            $pages = "$pages$value[$i]";

            $CLICSHOPPING_Db->save('configuration', ['configuration_value' => $pages], ['configuration_key' => $key]);
          }
        } else {
          $CLICSHOPPING_Db->save('configuration', ['configuration_value' => $value], ['configuration_key' => $key]);
        }
      }

      Cache::clear('configuration');

      $this->app->redirect('Modules&set=' . $set . '&module=' . $_GET['module']);
    }
  }
}