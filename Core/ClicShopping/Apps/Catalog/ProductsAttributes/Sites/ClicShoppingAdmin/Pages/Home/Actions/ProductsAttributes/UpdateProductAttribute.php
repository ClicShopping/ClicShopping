<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\ProductsAttributes\Sites\ClicShoppingAdmin\Pages\Home\Actions\ProductsAttributes;

use ClicShopping\Apps\Catalog\ProductsAttributes\Classes\ClicShoppingAdmin\ProductsAttributesAdmin;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Upload;

class UpdateProductAttribute extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('ProductsAttributes');
  }

  public function execute()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');
    $CLICSHOPPING_ProductsAttributesAdmin = new ProductsAttributesAdmin();


    $products_id = HTML::sanitize($_POST['products_id']);
    $options_id = HTML::sanitize($_POST['options_id']);
    $values_id = HTML::sanitize($_POST['values_id']);
    $value_price = HTML::sanitize($_POST['value_price']);
    $price_prefix = HTML::sanitize($_POST['price_prefix']);
    $value_sort_order = HTML::sanitize($_POST['value_sort_order']);
    $attribute_id = HTML::sanitize($_POST['attribute_id']);
    $products_attributes_reference = HTML::sanitize($_POST['products_attributes_reference']);
    $customers_group_id = HTML::sanitize($_POST['customers_group_id']);

    $option_page = (isset($_GET['option_page']) && is_numeric($_GET['option_page'])) ? $_GET['option_page'] : 1;
    $value_page = (isset($_GET['value_page']) && is_numeric($_GET['value_page'])) ? $_GET['value_page'] : 1;
    $attribute_page = (isset($_GET['attribute_page']) && is_numeric($_GET['attribute_page'])) ? $_GET['attribute_page'] : 1;

    $products_attributes_image = $CLICSHOPPING_ProductsAttributesAdmin->uploadImage();

    if (\is_null($products_attributes_image)) {
      $products_attributes_image = HTML::sanitize($_POST['products_attributes_image']);
    }

    $page_info = 'option_page=' . HTML::sanitize($option_page) . '&value_page=' . HTML::sanitize($value_page) . '&attribute_page=' . HTML::sanitize($attribute_page);

    $Qupdate = $this->app->db->prepare('update :table_products_attributes
                                          set products_id = :products_id,
                                              options_id = :options_id,
                                              options_values_id = :options_values_id,
                                              options_values_price = :options_values_price,
                                              price_prefix = :price_prefix,
                                              products_options_sort_order = :products_options_sort_order,
                                              products_attributes_reference = :products_attributes_reference,
                                              customers_group_id = :customers_group_id,
                                              products_attributes_image = :products_attributes_image
                                          where products_attributes_id =:products_attributes_id
                                        ');

    $Qupdate->bindInt(':products_id', (int)$products_id);
    $Qupdate->bindInt(':options_id', (int)$options_id);
    $Qupdate->bindInt(':options_values_id', (int)$values_id);
    $Qupdate->bindValue(':options_values_price', (float)$value_price);
    $Qupdate->bindValue(':price_prefix', $price_prefix);
    $Qupdate->bindInt(':products_options_sort_order', $value_sort_order);
    $Qupdate->bindValue(':products_attributes_reference', $products_attributes_reference);
    $Qupdate->bindInt(':products_attributes_id', (int)$attribute_id);
    $Qupdate->bindInt(':customers_group_id', (int)$customers_group_id);
    $Qupdate->bindValue(':products_attributes_image', $products_attributes_image);

    $Qupdate->execute();

    if (DOWNLOAD_ENABLED == 'true') {
      $products_attributes_maxdays = HTML::sanitize($_POST['products_attributes_maxdays']);
      $products_attributes_maxcount = HTML::sanitize($_POST['products_attributes_maxcount']);

      $upload_file = new Upload('new_products_attributes_filename', $CLICSHOPPING_Template->getPathDownloadShopDirectory('Private'), null, null);

      $error = true;

      if ($upload_file->check() && $upload_file->save()) {
        $error = false;
      }

      if ($error === false) {
        $Qreplace = $this->app->db->prepare('replace :table_products_attributes_download
                                                set products_attributes_id = :products_attributes_id,
                                                    products_attributes_filename = :products_attributes_filename,
                                                    products_attributes_maxdays = :products_attributes_maxdays,
                                                    products_attributes_maxcount = :products_attributes_maxcount
                                              ');

        $Qreplace->bindInt(':products_attributes_id', (int)$attribute_id);
        $Qreplace->bindValue(':products_attributes_filename', $upload_file->getFilename());
        $Qreplace->bindInt(':products_attributes_maxdays', (int)$products_attributes_maxdays);
        $Qreplace->bindInt(':products_attributes_maxcount', (float)$products_attributes_maxcount);

        $Qreplace->execute();
      } else {
        $products_attributes_filename = HTML::sanitize($_POST['products_attributes_filename']);

        $QproductsFilename = $this->app->db->prepare('select products_attributes_id,
                                                                products_attributes_filename
                                                         from :table_products_attributes_download
                                                         where products_attributes_id = :products_attributes_id
                                                        ');
        $QproductsFilename->bindInt(':products_attributes_id', $attribute_id);
        $QproductsFilename->execute();

        $Qreplace = $this->app->db->prepare('replace :table_products_attributes_download
                                                set products_attributes_id = :products_attributes_id,
                                                    products_attributes_filename = :products_attributes_filename,
                                                    products_attributes_maxdays = :products_attributes_maxdays,
                                                    products_attributes_maxcount = :products_attributes_maxcount
                                              ');

        $Qreplace->bindInt(':products_attributes_id', (int)$attribute_id);
        $Qreplace->bindValue(':products_attributes_filename', $products_attributes_filename);
        $Qreplace->bindInt(':products_attributes_maxdays', (int)$products_attributes_maxdays);
        $Qreplace->bindInt(':products_attributes_maxcount', (float)$products_attributes_maxcount);

        $Qreplace->execute();
      }
    }

    $CLICSHOPPING_Hooks->call('UpdateProductAttribute', 'Save');

    $this->app->redirect('ProductsAttributes&' . $page_info);
  }
}