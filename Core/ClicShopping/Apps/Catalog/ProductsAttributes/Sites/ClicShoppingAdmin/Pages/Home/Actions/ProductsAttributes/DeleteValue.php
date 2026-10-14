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

class DeleteValue extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('ProductsAttributes');
  }

  public function execute()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $option_page = (isset($_GET['option_page']) && is_numeric($_GET['option_page'])) ? $_GET['option_page'] : 1;
    $value_page = (isset($_GET['value_page']) && is_numeric($_GET['value_page'])) ? $_GET['value_page'] : 1;
    $attribute_page = (isset($_GET['attribute_page']) && is_numeric($_GET['attribute_page'])) ? $_GET['attribute_page'] : 1;

    $page_info = 'option_page=' . HTML::sanitize($option_page) . '&value_page=' . HTML::sanitize($value_page) . '&attribute_page=' . HTML::sanitize($attribute_page);

    $value_id = HTML::sanitize($_GET['value_id']);

    $Qdelete = $this->app->db->prepare('delete
                                          from :table_products_options_values
                                          where products_options_values_id = :products_options_values_id
                                        ');
    $Qdelete->bindInt(':products_options_values_id', $value_id);
    $Qdelete->execute();

    $Qdelete = $this->app->db->prepare('delete
                                          from :table_products_options_values_to_products_options
                                          where products_options_values_id = :products_options_values_id
                                          ');
    $Qdelete->bindInt(':products_options_values_id', $value_id);
    $Qdelete->execute();

    $Qdelete = $this->app->db->prepare('delete
                                          from :table_products_attributes
                                          where options_values_id = :options_values_id
                                        ');
    $Qdelete->bindInt(':options_values_id', $value_id);
    $Qdelete->execute();

    $CLICSHOPPING_Hooks->call('ProductsAttributes', 'DeleteValue', ['value_id' => (int)$value_id]);

    $this->app->redirect('ProductsAttributes&' . $page_info);
  }
}