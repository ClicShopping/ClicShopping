<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Sites\ClicShoppingAdmin\Pages\Home\Actions\ProductsAttributes;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class AddProductOptionValues extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('ProductsAttributes');
  }

  public function execute()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Language = Registry::get('Language');

    $languages = $CLICSHOPPING_Language->getLanguages();

    $option_page = (isset($_GET['option_page']) && is_numeric($_GET['option_page'])) ? $_GET['option_page'] : 1;
    $value_page = (isset($_GET['value_page']) && is_numeric($_GET['value_page'])) ? $_GET['value_page'] : 1;
    $attribute_page = (isset($_GET['attribute_page']) && is_numeric($_GET['attribute_page'])) ? $_GET['attribute_page'] : 1;

    $page_info = 'option_page=' . HTML::sanitize($option_page) . '&value_page=' . HTML::sanitize($value_page) . '&attribute_page=' . HTML::sanitize($attribute_page);

    $value_name_array = HTML::sanitize($_POST['value_name']);
    $value_id = HTML::sanitize($_POST['value_id']);
    $option_id = HTML::sanitize($_POST['option_id']);


    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $value_name = HTML::sanitize($value_name_array[$languages[$i]['id']]);

      $this->app->db->save('products_options_values', [
          'products_options_values_id' => (int)$value_id,
          'language_id' => (int)$languages[$i]['id'],
          'products_options_values_name' => $value_name
        ]
      );

    }

    $this->app->db->save('products_options_values_to_products_options', ['products_options_id' => (int)$option_id,
        'products_options_values_id' => (int)$value_id
      ]
    );

    $CLICSHOPPING_Hooks->call('AddProductOptionValue', 'AddProductOptionValue');

    $this->app->redirect('ProductsAttributes', $page_info);
  }
}