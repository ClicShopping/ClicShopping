<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsLength\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ProductsLength\Classes\ClicShoppingAdmin\ProductsLengthAdmin;
use ClicShopping\Apps\Configuration\ProductsLength\ProductsLength as ProductsLengthApp;

class ProductsContentTab2 implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the ProductsLength app.
   *
   * Ensures that the ProductsLength app is registered in the Registry.
   * If not already present, it registers a new instance of ProductsLengthApp.
   * Then, retrieves the app instance from the Registry and loads the necessary definitions.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('ProductsLength')) {
      Registry::set('ProductsLength', new ProductsLengthApp());
    }

    $this->app = Registry::get('ProductsLength');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/page_content_tab_2');
  }

  /**
   * Retrieves product length and related dimensions for a specific product ID from the database.
   *
   * This method checks if a product ID is provided via the `pID` GET parameter. If a valid product ID exists,
   * it queries the database to fetch the product's length class ID, width, height, depth, and volume. The result
   * is returned as an associative array. If no product ID is provided, the method returns 0.00.
   *
   * @return array|float Returns an associative array containing the product length and dimension information
   *                     if a valid product ID is provided; otherwise, returns 0.00.
   */
  private function getProductsProductsLength()
  {
    if (isset($_GET['pID'])) {
      $Qproducts = $this->app->db->prepare('select products_length_class_id,
                                                     products_dimension_width,
                                                     products_dimension_height,
                                                     products_dimension_depth,
                                                     products_volume
                                              from :table_products
                                              where products_id = :products_id
                                            ');
      $Qproducts->bindInt(':products_id', HTML::sanitize($_GET['pID']));

      $Qproducts->execute();
      $result = $Qproducts->toArray();

      return $result;
    } else {
      return 0.00;
    }
  }

  /**
   * Displays the HTML content for the products length form and appends it using JavaScript.
   *
   * @return string The generated HTML output for the products length specifics or false if the feature is disabled.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS') || CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS == 'False') {
      return false;
    }

    $product_length = $this->getProductsProductsLength();

    $products_length_class_id = $product_length['products_length_class_id'] ?? '0.00';
    $products_dimension_width = $product_length['products_dimension_width'] ?? '0.00';
    $products_dimension_height = $product_length['products_dimension_height'] ?? '0.00';
    $products_dimension_depth = $product_length['products_dimension_depth'] ?? '0.00';
    $products_volume = $product_length['products_volume'] ?? '0.00';

    if ($products_length_class_id === 0) {
      $products_length_class_id = PRODUCTS_LENGTH_UNIT;
    } else {
      $products_length_class_id = $products_length_class_id;
    }

    $content = '<!----- Products Lenght ---->';
    $content .= '<div class="row col-md-12">';
    $content .= '<div class="col-md-12">';
    $content .= '<div class="row">';

    $content .= '<div class="col-md-5">';
    $content .= '<div class="form-group row">';
    $content .= '<label for="' . $this->app->getDef('text_products_length') . '" class="col-5 col-form-label">' . $this->app->getDef('text_products_length') . '</label>';
    $content .= '<div class="col-md-6 row">';
    $content .= HTML::inputField('products_dimension_width', $products_dimension_width, 'placeholder="' . $this->app->getDef('text_products_dimension_width') . ' size="12" "id="products_dimension_width" class="form-control"') . ' <br />' . HTML::inputField('products_dimension_height', $products_dimension_height, 'placeholder="' . $this->app->getDef('text_products_dimension_height') . '" size="12" id="products_dimension_height" class="form-control"') . ' <br />' . HTML::inputField('products_dimension_depth', $products_dimension_depth, 'placeholder="' . $this->app->getDef('text_products_dimension_depth') . '" size="12" id="products_dimension_width" class="form-control"');
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';

    $content .= '<div class="mt-1"></div>';
    $content .= '<div class="col-md-5">';
    $content .= '<div class="form-group row">';
    $content .= '<label for="' . $this->app->getDef('text_products_length_type') . '" class="col-5 col-form-label">' . $this->app->getDef('text_products_length_type') . '</label>';
    $content .= '<div class="col-md-6 row">';
    $content .= HTML::selectField('products_length_class_id', ProductsLengthAdmin::getClassesPullDown(), $products_length_class_id);
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';

    $content .= '<div class="col-md-5">';
    $content .= '<div class="form-group row">';
    $content .= '<label for="' . $this->app->getDef('text_products_volume') . '" class="col-5 col-form-label">' . $this->app->getDef('text_products_volume') . '</label>';
    $content .= '<div class="col-md-6 row">';
    $content .= HTML::inputField('products_volume', $products_volume, 'placeholder="' . $this->app->getDef('text_products_volume') . '" id="products_volume" class="form-control"');
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';

    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';

    $output = <<<EOD
<!-- ######################## -->
<!--  Start ProductsLength Hooks      -->
<!-- ######################## -->
<script>
$('#tab2Shipping').append(
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