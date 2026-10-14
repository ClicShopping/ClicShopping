<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsLength\Module\Hooks\ClicShoppingAdmin\Preview;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ProductsLength\Classes\ClicShoppingAdmin\ProductsLengthAdmin;
use ClicShopping\Apps\Configuration\ProductsLength\ProductsLength as ProductsLengthApp;

class PreviewContent implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method.
   * Initializes the ProductsLength application and loads the necessary definitions for the module.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('ProductsLength')) {
      Registry::set('ProductsLength', new ProductsLengthApp());
    }

    $this->app = Registry::get('ProductsLength');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Preview/preview_content');
  }

  /**
   * Retrieves product length information for a given product ID.
   *
   * This method fetches the product's length-related attributes from the database,
   * including length class ID, width, height, and depth, based on the provided product ID.
   *
   * @return array|null Returns an associative array containing the product length details,
   *                    or null if the product ID is not set.
   */
  private function getProductsProductsLength()
  {
    if (isset($_GET['pID'])) {
      $Qproducts = $this->app->db->prepare('select products_length_class_id,
                                                     products_dimension_width,
                                                     products_dimension_height,
                                                     products_dimension_depth
                                              from :table_products
                                              where products_id = :products_id
                                            ');
      $Qproducts->bindInt(':products_id', HTML::sanitize($_GET['pID']));

      $Qproducts->execute();
      $result = $Qproducts->toArray();

      return $result;
    }
  }

  /**
   * Generates and returns the HTML and JavaScript output necessary
   * to display product length and dimensions information, including width,
   * height, depth, and their corresponding unit title.
   *
   * @return string|false The generated HTML and JavaScript code as a string, or false if the app's status is disabled.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS') || CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS == 'False') {
      return false;
    }

    $product_length = $this->getProductsProductsLength();

    $products_length_class_id = $product_length['products_length_class_id'];
    $products_dimension_width = $product_length['products_dimension_width'];
    $products_dimension_height = $product_length['products_dimension_height'];
    $products_dimension_depth = $product_length['products_dimension_depth'];


    $content = '<!----- Products Lenght ---->';
    $content .= '<div class="col-md-12">';
    $content .= '<div class="row" id="tab1ContentRow8">';
    $content .= '<div class="col-md-12">';
    $content .= $this->app->getDef('text_products_length') . ' ' . $products_dimension_width . ' x ' . $products_dimension_height . ' x ' . $products_dimension_depth . ' ' . ProductsLengthAdmin::getLengthProductsTitle($products_length_class_id);
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';

    $output = <<<EOD
<!-- ######################## -->
<!--  Start ProductsLength Hooks      -->
<!-- ######################## -->
<script>
$('#tab1ContentRow3').append(
    '{$content}'
);
</script>

<!-- ######################## -->
<!--  End ProductsLength App      -->
<!-- ######################## -->

EOD;
    return $output;

  }
}