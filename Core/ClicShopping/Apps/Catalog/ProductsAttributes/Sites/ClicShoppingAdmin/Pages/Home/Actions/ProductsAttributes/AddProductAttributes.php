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

class AddProductAttributes extends \ClicShopping\OM\Domains\PagesActionsAbstract
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

    if (isset($_POST['options_id']) && !empty($_POST['options_id'])) {
      $options_id = HTML::sanitize($_POST['options_id']);
    } else {
      $this->app->redirect('ProductsAttributes', 'option_page=1');
    }

    if (isset($_POST['values_id']) && !empty($_POST['values_id'])) {
      $values_id = HTML::sanitize($_POST['values_id']);
    } else {
      $this->app->redirect('ProductsAttributes', 'option_page=1');
    }

    $value_price = HTML::sanitize($_POST['value_price']);
    $price_prefix = HTML::sanitize($_POST['price_prefix']);
    $value_sort_order = HTML::sanitize($_POST['value_sort_order']);
    $products_attributes_reference = HTML::sanitize($_POST['products_attributes_reference']);
    $customers_group_id = HTML::sanitize($_POST['customers_group_id']);

    $products_attributes_image = $CLICSHOPPING_ProductsAttributesAdmin->uploadImage();

    $option_page = (isset($_GET['option_page']) && is_numeric($_GET['option_page'])) ? $_GET['option_page'] : 1;
    $value_page = (isset($_GET['value_page']) && is_numeric($_GET['value_page'])) ? $_GET['value_page'] : 1;
    $attribute_page = (isset($_GET['attribute_page']) && is_numeric($_GET['attribute_page'])) ? $_GET['attribute_page'] : 1;

    $page_info = 'option_page=' . HTML::sanitize($option_page) . '&value_page=' . HTML::sanitize($value_page) . '&attribute_page=' . HTML::sanitize($attribute_page);

    $insert_array = [
      'products_id' => (int)$products_id,
      'options_id' => (int)$options_id,
      'options_values_id' => (int)$values_id,
      'options_values_price' => (float)$value_price,
      'price_prefix' => $price_prefix,
      'products_options_sort_order' => (int)$value_sort_order,
      'products_attributes_reference' => $products_attributes_reference,
      'customers_group_id' => (int)$customers_group_id,
      'products_attributes_image' => $products_attributes_image
    ];

    $this->app->db->save('products_attributes', $insert_array);

    if (DOWNLOAD_ENABLED == 'true') {
      $products_attributes_id = $this->app->db->lastInsertId();

      $products_attributes_maxdays = HTML::sanitize($_POST['products_attributes_maxdays']);
      $products_attributes_maxcount = HTML::sanitize($_POST['products_attributes_maxcount']);

      $upload_file = new Upload('new_products_attributes_filename', $CLICSHOPPING_Template->getPathDownloadShopDirectory('Private'));

      $error = true;

      if ($upload_file->check() && $upload_file->save()) {
        $error = false;
      }

      if ($error === false) {
        $this->app->db->save('products_attributes_download', ['products_attributes_id' => (int)$products_attributes_id,
            'products_attributes_filename' => $upload_file->getFilename(),
            'products_attributes_maxdays' => (int)$products_attributes_maxdays,
            'products_attributes_maxcount' => (int)$products_attributes_maxcount
          ]
        );

      } else {
        $this->app->db->save('products_attributes_download', ['products_attributes_id' => (int)$products_attributes_id,
            'products_attributes_filename' => '',
            'products_attributes_maxdays' => (int)$products_attributes_maxdays,
            'products_attributes_maxcount' => (int)$products_attributes_maxcount
          ]
        );
      }
    }

    $CLICSHOPPING_Hooks->call('AddProductAttribute', 'Insert');

    $this->app->redirect('ProductsAttributes', $page_info);
  }
}