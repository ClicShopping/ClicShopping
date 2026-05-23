<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Sites\ClicShoppingAdmin\Pages\Home\Actions\Categories;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function execute()
  {
    $this->app = Registry::get('Categories');

    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Wysiwyg = Registry::get('Wysiwyg');

    if (isset($_GET['cPath'])) {
      $cPath = HTML::sanitize($_GET['cPath']);
    } else {
      $cPath = 0;
    }

    $sort_order = HTML::sanitize($_POST['sort_order']);

    $sql_data_array = ['sort_order' => (int)$sort_order];

    $parent_id = $_POST['move_to_category_id'];

    $insert_sql_data = [
      'parent_id' => (int)$parent_id,
      'date_added' => 'now()',
      'virtual_categories' => 0
    ];

    $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

    $this->app->db->save('categories', $sql_data_array);

    $categories_id = $this->app->db->lastInsertId();

    $languages = $CLICSHOPPING_Language->getLanguages();

    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $categories_name_array = HTML::sanitize($_POST['categories_name']);
      $language_id = $languages[$i]['id'];

      $sql_data_array = [
        'categories_name' => HTML::sanitize($categories_name_array[$language_id]),
        'categories_description' => $_POST['categories_description'][$language_id],
        'categories_seo_url' => HTML::sanitize($_POST['categories_seo_url'][$language_id]),
        'categories_head_title_tag' => HTML::sanitize($_POST['categories_head_title_tag'][$language_id]),
        'categories_head_desc_tag' => HTML::sanitize($_POST['categories_head_desc_tag'][$language_id]),
        'categories_head_keywords_tag' => HTML::sanitize($_POST['categories_head_keywords_tag'][$language_id])
      ];

      $insert_sql_data = [
        'categories_id' => $categories_id,
        'language_id' => $languages[$i]['id']
      ];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $this->app->db->save('categories_description', $sql_data_array);
    }

// Ajoute ou efface l'image dans la base de donees
    if (isset($_POST['delete_image'])) {
      $categories_image = '';

      $this->app->db->save('categories', ['categories_image' => $categories_image], ['categories_id' => (int)$categories_id]
      );


    } elseif (isset($_POST['categories_image']) && !\is_null($_POST['categories_image']) && ($_POST['categories_image'] != 'none')) {
      $categories_image = $_POST['categories_image'];

      if (isset($_POST['categories_image']) && !\is_null($_POST['categories_image']) && ($_POST['categories_image'] != 'none')) {
        $categories_image = $CLICSHOPPING_Wysiwyg::getWysiwygImageAlone($categories_image);
      } else {
        $categories_image = ($_POST['categories_previous_image'] ?? '');
      }

      $this->app->db->save('categories', ['categories_image' => $categories_image], ['categories_id' => (int)$categories_id]
      );
    }

    Cache::clear('categories');
    Cache::clear('products-also_purchased');
    Cache::clear('products_related');
    Cache::clear('products_cross_sell');
    Cache::clear('upcoming');

    $CLICSHOPPING_Hooks->call('Categories', 'Insert');

    $this->app->redirect('Categories&cPath=' . $cPath);
  }
}