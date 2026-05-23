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

class ClassDeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('ProductsLength');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    $products_length_class_from_id = HTML::sanitize($_GET['wID']);
    $products_length_class_to_id = HTML::sanitize($_GET['tID']);

    $sql_array = [
      'products_length_class_from_id' => (int)$products_length_class_from_id,
      'products_length_class_from_id' => (int)$products_length_class_to_id
    ];

    $this->app->db->delete('products_length_classes_rules', $sql_array);

    $this->app->db->delete('products_length_classes', ['products_length_class_id' => (int)$products_length_class_from_id]);

    Cache::clear('products_length-classes');
    Cache::clear('products_length-rules');

    $this->app->redirect('ProductsLength&page=' . $page);
  }
}