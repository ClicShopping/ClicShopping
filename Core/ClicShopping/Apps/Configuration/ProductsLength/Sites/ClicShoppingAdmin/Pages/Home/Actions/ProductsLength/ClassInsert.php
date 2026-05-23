<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\ProductsLength\Sites\ClicShoppingAdmin\Pages\Home\Actions\ProductsLength;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ClassInsert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('ProductsLength');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    $products_length_class_id = HTML::sanitize($_POST['products_length_class_id']);
    $products_length_class_to_id = HTML::sanitize($_POST['products_length_class_to_id']);
    $products_length_class_rule = $_POST['products_length_class_rule'];

    if (isset($products_length_class_id)) {
      $sql_data_array = ['products_length_class_rule' => (float)$products_length_class_rule];

      $insert_sql_data = ['products_length_class_from_id' => (int)$products_length_class_id,
        'products_length_class_to_id' => (int)$products_length_class_to_id
      ];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $this->app->db->save('products_length_classes_rules', $sql_data_array);

      Cache::clear('products_length-classes');
      Cache::clear('products_length-rules');
    }

    $this->app->redirect('ProductsLength&page=' . $page);
  }
}