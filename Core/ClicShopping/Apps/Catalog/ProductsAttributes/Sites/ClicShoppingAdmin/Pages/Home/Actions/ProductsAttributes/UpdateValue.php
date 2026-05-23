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

class UpdateValue extends \ClicShopping\OM\Domains\PagesActionsAbstract
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
    $CLICSHOPPING_Hooks->call('AddOptionName', 'PreAction');

    $value_name_array = $_POST['value_name'];
    $value_id = HTML::sanitize($_POST['value_id']);
    $option_id = HTML::sanitize($_POST['option_id']);
    $languages = $CLICSHOPPING_Language->getLanguages();

    $option_page = (isset($_GET['option_page']) && is_numeric($_GET['option_page'])) ? $_GET['option_page'] : 1;
    $value_page = (isset($_GET['value_page']) && is_numeric($_GET['value_page'])) ? $_GET['value_page'] : 1;
    $attribute_page = (isset($_GET['attribute_page']) && is_numeric($_GET['attribute_page'])) ? $_GET['attribute_page'] : 1;

    $page_info = 'option_page=' . HTML::sanitize($option_page) . '&value_page=' . HTML::sanitize($value_page) . '&attribute_page=' . HTML::sanitize($attribute_page);

    for ($i = 0, $n = \count($languages); $i < $n; $i++) {
      $value_name = HTML::sanitize($value_name_array[$languages[$i]['id']]);

      $Qupdate = $this->app->db->prepare('update :table_products_options_values
                                            set products_options_values_name = :products_options_values_name
                                            where products_options_values_id = :products_options_values_id
                                            and language_id = :language_id
                                          ');
      $Qupdate->bindValue(':products_options_values_name', $value_name);
      $Qupdate->bindInt(':products_options_values_id', $value_id);
      $Qupdate->bindInt(':language_id', $languages[$i]['id']);
      $Qupdate->execute();
    }

    $Qupdate = $this->app->db->prepare('update :table_products_options_values_to_products_options
                                          set products_options_id = :products_options_id
                                          where products_options_values_id = :products_options_values_id
                                        ');

    $Qupdate->bindInt(':products_options_id', $option_id);
    $Qupdate->bindInt(':products_options_values_id', $value_id);
    $Qupdate->execute();

    $CLICSHOPPING_Hooks->call('UpdateValue', 'Save');

    $this->app->redirect('ProductsAttributes&' . $page_info);
  }
}