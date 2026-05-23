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
  use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\CockpitAI\ProductsTracking;

class ms_shopping_cart_products_listing
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

    $this->title = CLICSHOPPING::getDef('module_shopping_cart_products_listing_title');
    $this->description = CLICSHOPPING::getDef('module_shopping_cart_products_listing_description');

    if (\defined('MODULE_SHOPPING_CART_PRODUCTS_LISTING_STATUS')) {
      $this->sort_order = \defined('MODULE_SHOPPING_CART_PRODUCTS_LISTING_SORT_ORDER') ? (int)MODULE_SHOPPING_CART_PRODUCTS_LISTING_SORT_ORDER : 0;
      $this->enabled = (MODULE_SHOPPING_CART_PRODUCTS_LISTING_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Prod = Registry::get('Prod');
    $CLICSHOPPING_Tax = Registry::get('Tax');
    $CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');
    $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');
    $CLICSHOPPING_Reviews = Registry::get('Reviews');
    $CLICSHOPPING_Language = Registry::get('Language');

    // normalisation position module (left/right)
    $module_position = ($this->group === 'boxes_column_left') ? 'left' : 'right';

    if (isset($_GET['Cart']) && $CLICSHOPPING_ShoppingCart->getCountContents() > 0) {
      $products = $CLICSHOPPING_ShoppingCart->get_products();

//        $content_width = (int)MODULE_SHOPPING_CART_PRODUCTS_LISTING_CONTENT_WIDTH;

      $form = HTML::form('cart_quantity', CLICSHOPPING::link(null, 'Cart&Update'), 'post', 'role="form" id="cart_quantity"', ['tokenize' => true]);
      $endform = '</form>';

      $shopping_cart = '<!-- ms_shopping_cart_products_listing -->' . "\n";
      $shopping_cart .= $form;
      $shopping_cart .= '<div>';
      $shopping_cart .= '<table id="cart" class="table table-hover table-condensed ModulesShoppingCartProductsListingTableHeading">';
      $shopping_cart .= '<thead>';
      $shopping_cart .= '<tr>';
      $shopping_cart .= '<th style="width:60%">' . CLICSHOPPING::getDef('table_heading_products') . '</th>';
      $shopping_cart .= '<th style="width:18%">' . CLICSHOPPING::getDef('table_heading_quantity') . '</th>';
      $shopping_cart .= '<th style="width:22%" class="text-end">' . CLICSHOPPING::getDef('table_heading_total') . '</th>';
      $shopping_cart .= '</tr>';
      $shopping_cart .= '</thead>';
      $shopping_cart .= '<tbody>';

      for ($i = 0, $n = \count($products); $i < $n; $i++) {
// Push all attributes information in an array
        if (isset($products[$i]['attributes']) && \is_array($products[$i]['attributes'])) {
          foreach ($products[$i]['attributes'] as $option => $value) {
            $shopping_cart .= HTML::hiddenField('id[' . $products[$i]['id'] . '][' . $option . ']', $value);

            $Qattributes = $CLICSHOPPING_ProductsAttributes->getProductsAttributesInfo($products[$i]['id'], $option, $CLICSHOPPING_Language->getId(), $value);

            $products[$i][$option]['products_attributes_values_name'] = $Qattributes->value('products_options_name');
            $products[$i][$option]['attributes_values_id'] = $value;
            $products[$i][$option]['products_attributes_values_name'] = $Qattributes->value('products_options_values_name');
            $products[$i][$option]['attributes_values_price'] = $Qattributes->valueDecimal('options_values_price');
            $products[$i][$option]['price_prefix'] = $Qattributes->value('price_prefix');
            $products[$i][$option]['products_attributes_reference'] = $Qattributes->value('products_attributes_reference');
            $products[$i][$option]['products_attributes_image'] = $Qattributes->value('products_attributes_image');
            $products[$i][$option]['products_options_type'] = $Qattributes->value('products_options_type');
          }
        }
      }

      $products_name = null;
      $products_option = null;

      for ($i = 0, $n = \count($products); $i < $n; $i++) {
        $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($CLICSHOPPING_Prod::getProductID($products[$i]['id']));

        $products_name = HTML::hiddenField('products_id[]', $products[$i]['id']);
        $products_name .= HTML::link($products_name_url, $products[$i]['name']);
        $remove_product_id = 'remove_product_' . $products[$i]['id'];

        $trash = '
          <a href="#" data-bs-toggle="modal" data-bs-target="#' . $remove_product_id[$i] . '"><i class="bi bi-trash2-fill"></i></a>
          <div class="modal hide fade active' . $remove_product_id[$i] . '" tabindex="-1" role="dialog" aria-labelledby="' . $remove_product_id[$i] . '" aria-hidden="true" id="' . $remove_product_id[$i] . '">
            <div class="modal-dialog modal-sm">
              <div class="modal-content">
                <div class="modal-content">
                  <div class="modal-header">
                  <h6 class="modal-title" id="myModalLabel">' . ClicShopping::getDef('text_title_modal_delete') . '</h6>
                  </div>
                  <div class="modal-body">
                    ' . CLICSHOPPING::getDef('text_title_modal_info') . '
                  </div>
                  <div class="modal-footer">
                     <span class="pull-start" data-bs-dismiss="modal" aria-hidden="true">' . HTML::button(ClicShopping::getDef('button_cancel'), null, null, 'light') . '</span>
                     <span class="pull-end">' . HTML::button(CLICSHOPPING::getDef('button_delete'), null, CLICSHOPPING::link(null, 'Cart&Delete&products_id=' . $products[$i]['id']), 'danger') . '</span>
                  </div>          
                </div>
              </div>
            </div>
          </div>
          ';

        $image = HTML::link($products_name_url, HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $products[$i]['image'], $products[$i]['name'], 50, 50)) . '&nbsp;&nbsp;&nbsp;';

        if (\defined('STOCK_CHECK') && STOCK_CHECK == 'true') {
// select the good qty in B2B to decrease the stock (see checkout_process to update stock)
          if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
            $QproductsQuantityCustomersGroupQuery = $CLICSHOPPING_Db->prepare('select products_quantity_fixed_group
                                                                                from :table_products_groups
                                                                                where products_id = :products_id
                                                                                and customers_group_id = :customers_group_id
                                                                              ');
            $QproductsQuantityCustomersGroupQuery->bindInt(':products_id', $CLICSHOPPING_Prod::getProductID($products[$i]['id']));
            $QproductsQuantityCustomersGroupQuery->bindInt(':customers_group_id', $CLICSHOPPING_Customer->getCustomersGroupID());
            $QproductsQuantityCustomersGroupQuery->execute();

// do the exact qty in function the customer group and product
            $products_quantity_customers_group[$i] = $QproductsQuantityCustomersGroupQuery->valueInt('products_quantity_fixed_group');
          } else {
            $products_quantity_customers_group[$i] = 1;
          }

          $stock_check = $CLICSHOPPING_ProductsCommon->getCheckStock($products[$i]['id'], $products[$i]['quantity'] * $products_quantity_customers_group[$i]);

          $products_stock = '';
          if (!empty($stock_check)) {
            $products_stock = '<p>' . $stock_check . '</p>';
          }
        } else {
          $products_stock = '';
        }

        $products_attributes = '';

        if (isset($products[$i]['attributes'])) {
          if (\is_array($products[$i]['attributes'])) {
            foreach ($products[$i]['attributes'] as $option => $value) {
              if (\is_array($products[$i][$option]) && isset($products[$i][$option])) {
                $products_attributes_values_name = $products[$i][$option]['products_attributes_values_name'];
                $products_attributes_reference = $products[$i][$option]['products_attributes_reference'];
                $products_attributes_image = $products[$i][$option]['products_attributes_image'];
                $products_options_type = $products[$i][$option]['products_options_type'] ?? '';

                if ($products_options_type === 'color_picker') {
                  $hex = '#' . ltrim($products_attributes_values_name, '#');
                  $color_swatch = '<span class="ModulesShoppingCartColorSwatch" style="background-color:' . HTML::outputProtected($hex) . ';"></span>';
                  $price_display = ($products[$i][$option]['attributes_values_price'] != 0) ? ' ' . $products[$i][$option]['price_prefix'] . $CLICSHOPPING_Currencies->displayPrice($products[$i][$option]['attributes_values_price'], $CLICSHOPPING_Tax->getTaxRate($products[$i]['tax_class_id']), '1') : '';
                  $products_attributes .= '<p class="ModulesShoppingCartproductsListingOption"> - ' . $color_swatch . HTML::outputProtected($products_attributes_values_name) . $price_display . '</p>';
                } else {
                  if (!\is_null($products[$i][$option]['products_attributes_image'])) {
                    if (is_file(CLICSHOPPING::getConfig('Shop') . $CLICSHOPPING_Template->getDirectoryTemplateImages() . $products_attributes_image)) {
                      $products_attributes_image = HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $products_attributes_image, $products_attributes_values_name . '   ', 30, 30);
                    } else {
                      $products_attributes_image = '     ';
                    }
                  } else {
                    $products_attributes_image = '     ';
                  }

                  $products_attributes .= '<p class="ModulesShoppingCartproductsListingOption"> - ' . $products_attributes_image . ' :  ' . $products_attributes_values_name . ' (' . $products_attributes_reference . ') ' . ' - ' . $CLICSHOPPING_Currencies->displayPrice($products[$i][$option]['attributes_values_price'], $CLICSHOPPING_Tax->getTaxRate($products[$i]['tax_class_id']), '1') . '</p>';
                }
              }
            }
          }
        }

        $button_update = HTML::button(null, 'bi bi-arrow-repeat btn-ShoppingCartRefresh', null, null, null, 'sm');

        $products_id = $CLICSHOPPING_Prod::getProductID($products[$i]['id']);
        $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($products_id);

        if (!empty($CLICSHOPPING_ProductsCommon->getProductsTickerSpecialsPourcentage($products_id))) {
          $ticker = HTML::link($products_name_url, HTML::tickerImage($CLICSHOPPING_ProductsCommon->getProductsTickerSpecialsPourcentage($products_id), 'ModulesShoppingCartBootstrapTickerPourcentage', true));
        } else {
          $ticker = '';
        }

        $total_overall_reviews = '<span class="ModulesReviews">' . HTML::stars($CLICSHOPPING_Reviews->getAverageProductReviews($products_id)) . '</span>';

        $cart = '<tr id="ShoppingCartContent" class="ModulesShoppingCartProductsListingContent">';
        $cart .= '<td id="ShoppingCartProducts" class="ModulesShoppingCartProductsListingContent" data-th="Product">';
        $cart .= '<div class="row">';
        $cart .= '<div id="ShoppingCartImage" class="col-sm-2 hidden-xs">' . $image . '</div>';
        $cart .= '<div class="col-sm-10">';
        $cart .= '<p id="ShoppingCartProductsName" class="nomargin text-start">' . $ticker . ' ' . $products_name . ' ' . $total_overall_reviews . ' ' . $products_stock . '</p>';
        $cart .= '<p id="ShoppingCartProductsOptions" class="small">' . $products_attributes . '</p>';
        $cart .= '</div>';
        $cart .= '</div>';
        $cart .= '</td>';
        $cart .= '<td id="ShoppingCartProductsQuantity"data-th="Quantity">';
        $cart .= HTML::inputField('cart_quantity[' . $i . ']', $products[$i]['quantity'], 'min="0"', 'number', null, 'form-control ModulesShoppingCartProductsListingShoppingCartQuantity') . ' ' . $button_update . ' ' . $trash;
        $cart .= HTML::hiddenField('products_id[' . $i . ']', $products[$i]['id'], 'id="products_id' . $products[$i]['id'] . '"');
        $cart .= '</td>';
        $cart .= '<td id="ShoppingCartPrice" data-th="Subtotal" class="text-end">' . $CLICSHOPPING_Currencies->displayPrice($products[$i]['final_price'], $CLICSHOPPING_Tax->getTaxRate($products[$i]['tax_class_id']), $products[$i]['quantity']) . '</td>';
        $cart .= '</tr>';

// display SaveMoney Hook
        //trackingProduct
        ProductsTracking::insertProductTracking((int)$products[$i]['id'],  $this->code, $module_position, $this->sort_order, $CLICSHOPPING_Language->getId(), null, 0.80);

        $cart .= Registry::get('Hooks')->output('Cart', 'AdditionalCheckoutSaveMoney', ['product_id' => (int)$products[$i]['id']]);

        ob_start();
        require($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/shopping_cart_products_listing'));
        $shopping_cart .= ob_get_clean();
      }

      $shopping_cart .= '</tbody>';
      $shopping_cart .= '</table>';

// display Free shipping Hook
      $shopping_cart .= Registry::get('Hooks')->output('Cart', 'FreeShipping');
      $shopping_cart .= Registry::get('Hooks')->output('Cart', 'AdditionalCheckoutInfoProductsDiscount');

      $shopping_cart .= '</div>';
      $shopping_cart .= '<!--  ms_shopping_cart_products_listing -->' . "\n";
      $shopping_cart .= $endform;

      $CLICSHOPPING_Template->addBlock($shopping_cart, $this->group);
    }
  } // function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_SHOPPING_CART_PRODUCTS_LISTING_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_SHOPPING_CART_PRODUCTS_LISTING_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of your listing ?',
        'configuration_key' => 'MODULE_SHOPPING_CART_PRODUCTS_LISTING_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );


    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_SHOPPING_CART_PRODUCTS_LISTING_SORT_ORDER',
        'configuration_value' => '10',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
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
    return [
      'MODULE_SHOPPING_CART_PRODUCTS_LISTING_STATUS',
      'MODULE_SHOPPING_CART_PRODUCTS_LISTING_CONTENT_WIDTH',
      'MODULE_SHOPPING_CART_PRODUCTS_LISTING_SORT_ORDER'
    ];
  }
}
