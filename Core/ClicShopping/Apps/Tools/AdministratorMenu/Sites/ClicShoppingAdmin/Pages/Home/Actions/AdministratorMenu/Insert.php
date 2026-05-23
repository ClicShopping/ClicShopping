<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\AdministratorMenu\Sites\ClicShoppingAdmin\Pages\Home\Actions\AdministratorMenu;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('AdministratorMenu');
  }

  public function execute()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    if (isset($_POST['sort_order'])) {
      $sort_order = HTML::sanitize($_POST['sort_order']);
    }

    if (isset($_POST['link'])) {
      $link = HTML::sanitize($_POST['link']);
    }

    if (isset($_POST['image'])) {
      $image = HTML::sanitize($_POST['image']);
    }

    if (isset($_POST['b2b_menu'])) {
      $b2b_menu = HTML::sanitize($_POST['b2b_menu']);
    } else {
      $b2b_menu = false;
    }

    if (isset($_POST['access_administrator'])) $access = $_POST['access_administrator'];

    $current_category_id = HTML::sanitize($_POST['move_to_category_id']);

    if ($b2b_menu == 'on') {
      $b2b_menu = 1;
    } else {
      $b2b_menu = 0;
    }

    $sql_data_array = [
      'sort_order' => (int)$sort_order,
      'link' => $link,
      'image' => $image,
      'b2b_menu' => (int)$b2b_menu,
      'access' => (int)$access,
      'status' => 1
    ];

    $insert_sql_data = [
      'parent_id' => (int)$current_category_id,
      'app_code' => Null
    ];

    $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

    $this->app->db->save('administrator_menu', $sql_data_array);

    $id = $this->app->db->lastInsertId();

    $languages = $CLICSHOPPING_Language->getLanguages();

    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $label_array = HTML::sanitize($_POST['label']);
      $language_id = $languages[$i]['id'];

      $sql_data_array = ['label' => HTML::sanitize($label_array[$language_id])];

      $insert_sql_data = [
        'id' => $id,
        'language_id' => $languages[$i]['id']
      ];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $this->app->db->save('administrator_menu_description', $sql_data_array);
    }

    Cache::clear('menu-administrator');

    $this->app->redirect('AdministratorMenu&cPath=' . $current_category_id . '&cID=' . (int)$id);
  }
}