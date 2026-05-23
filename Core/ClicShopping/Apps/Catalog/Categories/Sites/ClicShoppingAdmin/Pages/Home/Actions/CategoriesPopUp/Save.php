<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Sites\ClicShoppingAdmin\Pages\Home\Actions\CategoriesPopUp;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Save extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Categories');
  }

  public function execute()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Language = Registry::get('Language');

    if (isset($_POST['select_category_id'])) {
      $select_category_id = HTML::sanitize($_POST['select_category_id']);

      if (empty($select_category_id)) {
        $select_category_id = 0;
      }

      $sql_data_array = ['parent_id' => $select_category_id];
      $insert_sql_data = ['date_added' => 'now()'];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $this->app->db->save('categories', $sql_data_array);

      $categories_id = $this->app->db->lastInsertId();

      $languages = $CLICSHOPPING_Language->getLanguages();

      for ($i = 0, $n = \count($languages); $i < $n; $i++) {
        $categories_name_array = HTML::sanitize($_POST['categories_name']);
        $language_id = $languages[$i]['id'];

        $sql_data_array = ['categories_name' => HTML::sanitize($categories_name_array[$language_id])];

        $insert_sql_data = [
          'categories_id' => (int)$categories_id,
          'language_id' => (int)$language_id
        ];

        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

        $this->app->db->save('categories_description', $sql_data_array);
      }

      $CLICSHOPPING_Hooks->call('CategoriesPopUp', 'Insert');

      echo '<style>color: #c4071b;</style>Success';
      //    echo "From Server : ".json_encode($_POST)."<br>";
    } else {
      echo 'Error <br />';
    }

    exit;
  }
}