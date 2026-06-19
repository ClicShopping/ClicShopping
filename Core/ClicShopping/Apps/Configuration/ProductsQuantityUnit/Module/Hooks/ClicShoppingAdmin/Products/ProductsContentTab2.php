<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ProductsQuantityUnit\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ProductsQuantityUnit\Classes\ClicShoppingAdmin\ProductsQuantityUnitAdmin;
use ClicShopping\Apps\Configuration\ProductsQuantityUnit\ProductsQuantityUnit as ProductsQuantityUnitApp;

class ProductsContentTab2 implements HooksInterface
{
  public mixed $app;
  protected $qteUnit;

  /**
   * Class constructor that initializes registry instances for 'ProductsQuantityUnit'
   * and 'ProductsQuantityUnitAdmin' if they do not already exist. Assigns these
   * instances to class properties for further use.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('ProductsQuantityUnit')) {
      Registry::set('ProductsQuantityUnit', new ProductsQuantityUnitApp());
    }

    if (!Registry::exists('ProductsQuantityUnitAdmin')) {
      Registry::set('ProductsQuantityUnitAdmin', new ProductsQuantityUnitAdmin());
    }

    $this->qteUnit = Registry::get('ProductsQuantityUnitAdmin');
    $this->app = Registry::get('ProductsQuantityUnit');
  }

  /**
   * Retrieves the quantity unit ID associated with a specific product ID,
   * if the product ID is provided in the GET request parameter 'pID'.
   * The method queries the database for the corresponding
   * 'products_quantity_unit_id' of the given product.
   *
   * @return int|null Returns the quantity unit ID as an integer if found,
   * or null if no product ID is set in the GET parameters.
   */
  private function getQtyUnit()
  {
    if (isset($_GET['pID'])) {
      $QtyUnit = $this->app->db->prepare('select products_quantity_unit_id
                                             from :table_products
                                             where  products_id = :products_id
                                           ');
      $QtyUnit->bindInt(':products_id', HTML::sanitize($_GET['pID']));

      $QtyUnit->execute();

      return $QtyUnit->valueInt('products_quantity_unit_id');
    }
  }


  /**
   * Displays the product quantity unit dropdown and generates the required HTML output for inclusion
   * in a specific section of the product page interface, using predefined definitions and hooks.
   *
   * @return string|false Returns the HTML content to display the dropdown for product quantity unit if the feature
   *                      is enabled. Returns false if the feature is disabled.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_PRODUCTS_QUANTITY_UNIT_PQ_STATUS') || CLICSHOPPING_APP_PRODUCTS_QUANTITY_UNIT_PQ_STATUS == 'False') {
      return false;
    }

    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Products/page_content_tab_2');

    $products_quantity_unit_drop_down = $this->qteUnit->productsQuantityUnitDropDown();

    $content = '<div class="col-md-5">';
    $content .= '<div class="form-group row">';
    $content .= '<label for="' . $this->app->getDef('text_products_quantity_unit') . '" class="col-5 col-form-label">' . $this->app->getDef('text_products_quantity_unit') . '</label>';
    $content .= '<div class="col-md-5">';
    $content .= HTML::selectMenu('products_quantity_unit_id', $products_quantity_unit_drop_down, $this->getQtyUnit());
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';


    $output = <<<EOD
<!-- ######################## -->
<!--  Start Product Qty Unit Hooks      -->
<!-- ######################## -->
<script>
$('#tab2ContentRow5').prepend(
    '{$content}'
);
</script>
<!-- ######################## -->
<!--  End TwitterApp      -->
<!-- ######################## -->

EOD;
    return $output;

  }
}