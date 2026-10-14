<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Langues\Sites\ClicShoppingAdmin\Pages\Home\Actions\Langues;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Save extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Langues');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $lID = HTML::sanitize($_GET['lID']);
    $name = HTML::sanitize($_POST['name']);
    $code = HTML::sanitize(substr($_POST['code'], 0, 2));
    $image = HTML::sanitize($_POST['image']);
    $directory = HTML::sanitize($_POST['directory']);
    $sort_order = (int)HTML::sanitize($_POST['sort_order']);
    $locale = HTML::sanitize($_POST['locale']);

    $save_sql = [
      'name' => $name,
      'code' => $code,
      'image' => $image,
      'directory' => $directory,
      'sort_order' => (int)$sort_order,
      'status' => 1,
      'locale' => $locale
    ];

    $this->app->db->save('languages', $save_sql, ['languages_id' => (int)$lID]);

    if (isset($_POST['default'])) {
      $this->app->db->save('configuration', ['configuration_value' => $code],
        ['configuration_key' => 'DEFAULT_LANGUAGE']
      );
    }

    Cache::clear('languages-system-shop');
    Cache::clear('languages-system-admin');

    $this->app->redirect('Langues&page=' . $page . '&lID=' . $_GET['lID']);
  }
}