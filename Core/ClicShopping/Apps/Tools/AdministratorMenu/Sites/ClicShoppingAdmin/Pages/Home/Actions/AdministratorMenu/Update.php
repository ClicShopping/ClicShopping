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

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('AdministratorMenu');
  }

  public function execute()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    if (isset($_GET['cID'])) {
      $id = HTML::sanitize($_GET['cID']);
    }

    if (isset($_GET['cPath'])) {
      $cPath = HTML::sanitize($_GET['cPath']);
    }

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

    if (isset($_POST['access_administrator'])) {
      $access = $_POST['access_administrator'];
    }

    if (isset($_POST['move_to_category_id'])) {
      $move_to_category_id = $_POST['move_to_category_id'];
    }

    if (isset($_POST['label'])) {
      $label_array = HTML::sanitize($_POST['label']);
    }

    if (isset($b2b_menu)) {
      $b2b_menu = 1;
    } else {
      $b2b_menu = 0;
    }

    $sql_data_array = ['sort_order' => (int)$sort_order,
      'link' => $link,
      'image' => $image,
      'b2b_menu' => (int)$b2b_menu,
      'access' => (int)$access,
      'parent_id' => (int)$move_to_category_id
    ];

    $this->app->db->save('administrator_menu', $sql_data_array, ['id' => (int)$id]);

    $languages = $CLICSHOPPING_Language->getLanguages();

    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $language_id = $languages[$i]['id'];

      $sql_data_array = ['label' => HTML::sanitize($label_array[$language_id])];
      $insert_array = ['id' => (int)$id,
        'language_id' => (int)$languages[$i]['id']
      ];

      $this->app->db->save('administrator_menu_description', $sql_data_array, $insert_array);
    }

    Cache::clear('menu-administrator');

    $this->app->redirect('AdministratorMenu&cPath=' . $cPath . '&cID=' . $id);
  }
}