<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class pi_products_info_price
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_products_info_price');
    $this->description = CLICSHOPPING::getDef('module_products_info_price_description');

    if (\defined('MODULE_PRODUCTS_INFO_PRICE_STATUS')) {
      $this->sort_order = (int)MODULE_PRODUCTS_INFO_PRICE_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_PRODUCTS_INFO_PRICE_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    if ($CLICSHOPPING_ProductsCommon->getID() && isset($_GET['Products'])) {
      $content_width = \defined('MODULE_PRODUCTS_INFO_PRICE_CONTENT_WIDTH') ? (int)MODULE_PRODUCTS_INFO_PRICE_CONTENT_WIDTH : 12;
      $text_position = \defined('MODULE_PRODUCTS_INFO_PRICE_POSITION') ? MODULE_PRODUCTS_INFO_PRICE_POSITION : 'float-none';

      $CLICSHOPPING_Customer = Registry::get('Customer');
      $CLICSHOPPING_Template = Registry::get('Template');
      $CLICSHOPPING_Category = Registry::get('Category');
      $CLICSHOPPING_Weight = Registry::get('Weight');
      $CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');

//possible bug avec shopping cart pour les produits qui ne sont pas dans les catégories _123 au lieu 0_123
      $cPath = $CLICSHOPPING_Category->getProductPath($CLICSHOPPING_ProductsCommon->getId());

      if ($CLICSHOPPING_ProductsCommon->getProductsGroupView() == 1 || $CLICSHOPPING_ProductsCommon->getProductsView() == 1) {

// display the price/weight
        if (!empty($CLICSHOPPING_ProductsCommon->getProductsPriceByWeight())) {
          $weight_symbol = $CLICSHOPPING_ProductsCommon->getSymbolWeightByProducts($CLICSHOPPING_ProductsCommon->getWeightClassIdByProducts($CLICSHOPPING_ProductsCommon->getID()));
          $product_price_kilo = CLICSHOPPING::getDef('text_products_info_price_by_weight') . ' ' . $CLICSHOPPING_ProductsCommon->getProductsPriceByWeight() . ' / ' . $weight_symbol;
        } else {
          $product_price_kilo = '';
        }
// Products attributes
        if ($CLICSHOPPING_ProductsAttributes->getHasProductAttributes($CLICSHOPPING_ProductsCommon->getId()) > 1) {
          $clic_has_product_attributes = $CLICSHOPPING_ProductsAttributes->getHasProductAttributes($CLICSHOPPING_ProductsCommon->getID());
        }

// Minimum quantity to take an order
        if ($CLICSHOPPING_ProductsCommon->getProductsMinimumQuantityToTakeAnOrder() > 1) {
          $min_order_quantity_products_display = CLICSHOPPING::getDef('min_qty_order_product') . ' ' . $CLICSHOPPING_ProductsCommon->getProductsMinimumQuantityToTakeAnOrder();
        } else {
          $min_order_quantity_products_display = '';
        }

// display the differents prices before button
        $product_price = $CLICSHOPPING_ProductsCommon->getCustomersPrice();

// display a message in public function the customer group applied - before submit button
        if ($CLICSHOPPING_ProductsCommon->getProductsMinimumQuantity() != 0 && $CLICSHOPPING_ProductsCommon->getProductsQuantity() != 0) {
          $submit_button_view = $CLICSHOPPING_ProductsCommon->getProductsAllowingTakeAnOrderMessage();
        }

// display the differents buttons before minorder qty
        if (STOCK_ALLOW_CHECKOUT == 'true' && PRE_ORDER_AUTORISATION == 'true' && $CLICSHOPPING_ProductsCommon->getProductsQuantity() < 1) {
          $buy_button = HTML::button(CLICSHOPPING::getDef('button_pre_order'), null, null, 'success', null, 'lg');
          $CLICSHOPPING_ProductsCommon->getBuyButton($buy_button);
          $submit_button = $CLICSHOPPING_ProductsCommon->getProductsBuyButton();
        } elseif (STOCK_ALLOW_CHECKOUT == 'true') {
          $buy_button = HTML::button(CLICSHOPPING::getDef('button_cart'), null, null, 'success', null, 'lg');
          $CLICSHOPPING_ProductsCommon->getBuyButton($buy_button);
          $submit_button = $CLICSHOPPING_ProductsCommon->getProductsBuyButton();
        } elseif ($CLICSHOPPING_ProductsCommon->getProductsMinimumQuantity() != 0 && $CLICSHOPPING_ProductsCommon->getProductsQuantity() != 0) {
          $buy_button = HTML::button(CLICSHOPPING::getDef('button_cart'), null, null, 'success', null, 'lg');
          $CLICSHOPPING_ProductsCommon->getBuyButton($buy_button);
          $submit_button = $CLICSHOPPING_ProductsCommon->getProductsBuyButton();
        } else {
          $submit_button = '';
        }

// Display an input allowing for the customer to insert a quantity
        if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
          if ($CLICSHOPPING_ProductsCommon->getProductsOrdersView() != 0) {
            $input_quantity = CLICSHOPPING::getDef('customer_quantity') . ' ' . $CLICSHOPPING_ProductsCommon->getProductsAllowingToInsertQuantity();
          } else {
            $input_quantity = '';
          }
        } else {
          if ($CLICSHOPPING_ProductsCommon->getPriceGroupView() != 0) {
            $input_quantity = CLICSHOPPING::getDef('customer_quantity') . ' ' . $CLICSHOPPING_ProductsCommon->getProductsAllowingToInsertQuantity();
          } else {
            $input_quantity = '';
          }
        }

// Quantity type
        if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
          if (!empty($CLICSHOPPING_ProductsCommon->getProductQuantityUnitType())) {
            $products_quantity_unit = CLICSHOPPING::getDef('text_products_quantity_type') . ' ' . $CLICSHOPPING_ProductsCommon->getProductQuantityUnitType();
          }
        } else {
          if (!empty($CLICSHOPPING_ProductsCommon->getProductQuantityUnitTypeCustomersGroup())) {
            $products_quantity_unit = CLICSHOPPING::getDef('text_products_quantity_type') . ' ' . $CLICSHOPPING_ProductsCommon->getProductQuantityUnitTypeCustomersGroup();
          }
        }
// Display an information if the stock is sold out for all groups
        if ($CLICSHOPPING_ProductsCommon->getProductsSoldOut() != '') {
          $submit_button = $CLICSHOPPING_ProductsCommon->getProductsSoldOut();
          $min_quantity = 0;
          $input_quantity = '';
          $min_order_quantity_products_display = '';
        }

//===============================================================================

        $products_price_content = '<!-- Start product price -->' . "\n";
// Strong relations with pi_products_info_options.php = Don't delete
        if (($CLICSHOPPING_ProductsAttributes->getCountProductsAttributes($CLICSHOPPING_ProductsCommon->getId()) == 0) || (\defined('MODULE_PRODUCTS_INFO_OPTIONS_SORT_ORDER') && MODULE_PRODUCTS_INFO_PRICE_SORT_ORDER < MODULE_PRODUCTS_INFO_OPTIONS_SORT_ORDER)) {
          $products_price_content .= HTML::form('cart_quantity', CLICSHOPPING::link(null, 'Cart&Add&cPath=' . $cPath, ' SSL'), 'post', null, ['tokenize' => true]) . "\n";
          if ($CLICSHOPPING_ProductsCommon->getID()) {
            $products_price_content .= HTML::hiddenField('url', 'Products&Description&products_id=' . $CLICSHOPPING_ProductsCommon->getId());
          }
        }

        $products_price_content .= '<div class="contentText"  style="float:' . MODULE_PRODUCTS_INFO_PRICE_POSITION . ';">';

        if ($CLICSHOPPING_ProductsCommon->getProductsArchive() == 0) {
          $products_price_content .= HTML::hiddenField('products_id', $CLICSHOPPING_ProductsCommon->getID());

          ob_start();
          require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_info_price'));
          $products_price_content .= ob_get_clean();

// Strong relations with pi_products_options.php Don't delete
          if ($CLICSHOPPING_ProductsAttributes->getCountProductsAttributes() == 0 || (\defined('MODULE_PRODUCTS_INFO_OPTIONS_SORT_ORDER') && MODULE_PRODUCTS_INFO_PRICE_SORT_ORDER >= MODULE_PRODUCTS_INFO_OPTIONS_SORT_ORDER)) {
            $products_price_content .= '</form>' . "\n";
          }
        } // end products_group_view

      } else {
// ----------------------------------------------------------------//
// Affichage de l'archive                                         //
// ----------------------------------------------------------------//
        $products_price_content = '<!-- Start products_archives -->' . "\n";
        $products_price_content .= '<div class="mt-1"></div>';
        $products_price_content .= '<h3 class="text-center">' . CLICSHOPPING::getDef('products_not_sell') . '</h3>';
        $products_price_content .= '<div class="buttonSet"><span class="buttonAction">' . HTML::button(CLICSHOPPING::getDef('button_continue'), CLICSHOPPING::link(), 'primary') . '</span></div>' . "\n";
        $products_price_content .= '<!-- products_archives end -->' . "\n";
      }

      $products_price_content .= '<!-- end products_PRICE -->' . "\n";

      $CLICSHOPPING_Template->addBlock($products_price_content, $this->group);

    } // $GET['Products_id']
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_PRODUCTS_INFO_PRICE_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_PRICE_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the module',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_PRICE_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Where Do you want to display the module ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_PRICE_POSITION',
        'configuration_value' => 'float-none',
        'configuration_description' => 'Select where you want display the module',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\', \'float-none\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_PRICE_SORT_ORDER',
        'configuration_value' => '95',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  public function keys()
  {
    return array(
      'MODULE_PRODUCTS_INFO_PRICE_STATUS',
      'MODULE_PRODUCTS_INFO_PRICE_CONTENT_WIDTH',
      'MODULE_PRODUCTS_INFO_PRICE_POSITION',
      'MODULE_PRODUCTS_INFO_PRICE_SORT_ORDER'
    );
  }
}
