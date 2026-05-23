<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\ProductsQuantityUnit\Sites\ClicShoppingAdmin\Pages\Home\Actions\ProductsQuantityUnit;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('ProductsQuantityUnit');
  }

  public function execute()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $languages = $CLICSHOPPING_Language->getLanguages();
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $products_quantity_unit_title_array = HTML::sanitize($_POST['products_quantity_unit_title']);
      $language_id = $languages[$i]['id'];

      $sql_data_array = ['products_quantity_unit_title' => $products_quantity_unit_title_array[$language_id]];

      if (empty($products_quantity_unit_id)) {
        $Qnext = $this->app->db->get('products_quantity_unit', 'max(products_quantity_unit_id) as products_quantity_unit_id');
        $products_quantity_unit_id = $Qnext->value('products_quantity_unit_id') + 1;
      }

      $insert_sql_data = ['products_quantity_unit_id' => (int)$products_quantity_unit_id,
        'language_id' => (int)$language_id
      ];

      $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

      $this->app->db->save('products_quantity_unit', $sql_data_array);
    }

    if (isset($_POST['default'])) {
      $this->app->db->save('configuration', [
        'configuration_value' => $products_quantity_unit_id
      ], [
          'configuration_key' => 'DEFAULT_PRODUCTS_QUANTITY_UNIT_STATUS_ID'
        ]
      );
    }

    Cache::clear('configuration');

    $this->app->redirect('ProductsQuantityUnit&page=' . $page . '&oID=' . $products_quantity_unit_id);
  }
}