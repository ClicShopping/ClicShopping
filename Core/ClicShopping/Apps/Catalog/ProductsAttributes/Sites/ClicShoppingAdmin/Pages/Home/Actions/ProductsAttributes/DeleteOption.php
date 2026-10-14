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

class DeleteOption extends \ClicShopping\OM\Domains\PagesActionsAbstract
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

    $option_id = (int)HTML::sanitize($_GET['option_id']);

    // 1. Collect value_ids linked to this option before the bridge is dropped
    $Qvalues = $this->app->db->prepare('select products_options_values_id
                                          from :table_products_options_values_to_products_options
                                         where products_options_id = :products_options_id
                                       ');
    $Qvalues->bindInt(':products_options_id', $option_id);
    $Qvalues->execute();

    $value_ids = [];

    while ($Qvalues->fetch()) {
      $value_ids[] = $Qvalues->valueInt('products_options_values_id');
    }

    // 2. Delete product attribute rows using this option
    $Qdelete = $this->app->db->prepare('delete
                                          from :table_products_attributes
                                          where options_id = :options_id
                                       ');
    $Qdelete->bindInt(':options_id', $option_id);
    $Qdelete->execute();

    // 3. Delete bridge rows for this option
    $Qdelete = $this->app->db->prepare('delete
                                          from :table_products_options_values_to_products_options
                                          where products_options_id = :products_options_id
                                       ');
    $Qdelete->bindInt(':products_options_id', $option_id);
    $Qdelete->execute();

    // 4. Drop values that no longer have ANY bridge link (orphans)
    foreach ($value_ids as $value_id) {
      $Qcheck = $this->app->db->prepare('select products_options_values_to_products_options_id
                                           from :table_products_options_values_to_products_options
                                          where products_options_values_id = :products_options_values_id
                                          limit 1
                                         ');
      $Qcheck->bindInt(':products_options_values_id', $value_id);
      $Qcheck->execute();

      if ($Qcheck->fetch() === false) {
        $this->app->db->delete('products_options_values', ['products_options_values_id' => $value_id]);
      }
    }

    // 5. Delete the option itself (all languages)
    $Qdelete = $this->app->db->prepare('delete
                                          from :table_products_options
                                          where products_options_id = :products_options_id
                                       ');
    $Qdelete->bindInt(':products_options_id', $option_id);
    $Qdelete->execute();

    $CLICSHOPPING_Hooks->call('ProductsAttributes', 'DeleteOption', ['option_id' => $option_id]);

    $this->app->redirect('ProductsAttributes&' . $page_info);
  }
}