<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Weight\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Weight\Classes\ClicShoppingAdmin\WeightAdmin;
use ClicShopping\Apps\Configuration\Weight\Weight as WeightApp;

class ProductsContentTab2 implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for the class.
   *
   * Initializes the Weight app in the registry if it does not already exist
   * and loads the necessary language definitions for the module.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Weight')) {
      Registry::set('Weight', new WeightApp());
    }

    $this->app = Registry::get('Weight');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/page_content_tab_2');
  }

  /**
   * Retrieves the weight class ID of a specific product from*/
  private function getProductsWeightId()
  {
    $Qproducts = $this->app->db->prepare('select products_weight_class_id
                                            from :table_products
                                            where products_id = :products_id
                                          ');
    $Qproducts->bindInt(':products_id', HTML::sanitize($_GET['pID']));

    $Qproducts->execute();

    return $Qproducts->valueInt('products_weight_class_id');
  }

  /**
   * Generates and returns the HTML content for displaying the product's weight type selection field.
   *
   * @return string|bool Returns the generated HTML content as a string if the weight module is enabled and the operation
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_WEIGHT_WE_STATUS') || CLICSHOPPING_APP_WEIGHT_WE_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['pID'])) {
      $products_weight_class_id = $this->getProductsWeightId();
    } else {
      $products_weight_class_id = 0;
    }

    if ($products_weight_class_id === 0) {
      $weight_class_id = SHIPPING_WEIGHT_UNIT;
    } else {
      $weight_class_id = $products_weight_class_id;
    }

    $content = '<div class="col-md-5">';
    $content .= '<div class="form-group row">';
    $content .= '<label for="' . $this->app->getDef('text_products_weight_type') . '" class="col-5 col-form-label">' . $this->app->getDef('text_products_weight_type') . '</label>';
    $content .= '<div class="col-md-5">';
    $content .= HTML::selectField('products_weight_class_id', WeightAdmin::getClassesPullDown(), $weight_class_id) . '<br />';
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';

    $output = <<<EOD
<!-- ######################## -->
<!--  Start Weight Hooks      -->
<!-- ######################## -->
<script>
$('#productsWeight').append(
    '{$content}'
);
</script>

<!-- ######################## -->
<!--  End Weight App      -->
<!-- ######################## -->
EOD;
    return $output;
  }
}