<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Weight\Module\Hooks\ClicShoppingAdmin\Preview;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces\HooksInterface;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Weight\Classes\ClicShoppingAdmin\WeightAdmin;
use ClicShopping\Apps\Configuration\Weight\Weight as WeightApp;

class PreviewContent implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method that initializes the application by registering
   * the 'Weight' component in the Registry if it does not exist, and
   * loads relevant definitions specific to the module.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Weight')) {
      Registry::set('Weight', new WeightApp());
    }

    $this->app = Registry::get('Weight');
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/Preview/preview_content');
  }

  /**
   * Retrieves the weight class ID and weight of a product based on the provided product ID.
   *
   * This method queries the database to fetch the weight-related details of a product
   * identified by the "pID" parameter in the GET request. If the*/
  private function getProductsWeight()
  {
    if (isset($_GET['pID'])) {
      $Qproducts = $this->app->db->prepare('select products_weight_class_id,
                                                    products_weight
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
   * Generates and returns the HTML and JavaScript content for displaying the product's weight information.
   *
   * @return string|false The generated content as a string if the feature is enabled, or false if it is disabled.
   */
  public function display()
  {
    if (!\defined('CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS') || CLICSHOPPING_APP_PROUCTS_LENGTH_PL_STATUS == 'False') {
      return false;
    }

    $weight = $this->getProductsWeight();
    $products_weight = $weight['products_weight'];
    $products_weight_id = $weight['products_weight_class_id'];

    $content = '<!----- Products Lenght ---->';
    $content .= '<div class="col-md-12">';
    $content .= '<div class="row" id="tab1ContentRow8">';
    $content .= '<div class="col-md-12">';
    $content .= $this->app->getDef('text_products_weight') . ' ' . $products_weight . ' ' . WeightAdmin::getWeightTitle($products_weight_id);
    $content .= '</div>';
    $content .= '</div>';
    $content .= '</div>';

    $output = <<<EOD
<!-- ######################## -->
<!--  Start Weight Hooks      -->
<!-- ######################## -->
<script>
$('#tab1ContentRow3').append(
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