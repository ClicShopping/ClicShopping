<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class pi_products_info_also_purchased
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

    $this->title = CLICSHOPPING::getDef('module_products_info_also_purchased_title');
    $this->description = CLICSHOPPING::getDef('module_products_info_also_purchased_description');

    if (\defined('MODULE_PRODUCTS_INFO_ALSO_PURCHASED_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_INFO_ALSO_PURCHASED_SORT_ORDER') ? (int)MODULE_PRODUCTS_INFO_ALSO_PURCHASED_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_PRODUCTS_INFO_ALSO_PURCHASED_STATUS') ? (MODULE_PRODUCTS_INFO_ALSO_PURCHASED_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    if ($CLICSHOPPING_ProductsCommon->getID()) {
      $CLICSHOPPING_Customer = Registry::get('Customer');
      $CLICSHOPPING_Db = Registry::get('Db');
      $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
      $CLICSHOPPING_Template = Registry::get('Template');
      $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');
      $CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');
      $CLICSHOPPING_Reviews = Registry::get('Reviews');

      if ($CLICSHOPPING_Customer->getCustomersGroupID() == 0) {
        $Qproducts = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                          p.products_image,
                                                          p.products_image_medium,
                                                          p.products_quantity as in_stock
                                                  from :table_orders_products opa,
                                                       :table_orders_products opb,
                                                       :table_orders o,
                                                       :table_products p,
                                                       :table_products_to_categories p2c,
                                                      :table_categories c
                                                  where opa.products_id = :products_id
                                                  and opa.orders_id = opb.orders_id
                                                  and opb.products_id <> :products_id
                                                  and opb.products_id = p.products_id
                                                  and opb.orders_id = o.orders_id
                                                  and p.products_status = 1
                                                  and p.products_archive = 0
                                                  and p.products_view = 1
                                                  and p.products_id = p2c.products_id
                                                  and p2c.categories_id = c.categories_id
                                                  and c.status = 1
                                                  group by p.products_id
                                                  order by o.date_purchased desc
                                                  limit :limit
                                                ');

        $Qproducts->bindInt(':products_id', $CLICSHOPPING_ProductsCommon->getID());
        $Qproducts->bindInt(':limit', MODULE_PRODUCTS_INFO_ALSO_PURCHASED_MAX_DISPLAY);
        $Qproducts->execute();
      } else { // mode B2B
        $Qproducts = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                           p.products_image,
                                                           p.products_image_medium,
                                                           p.products_quantity as in_stock
                                                  from :table_orders_products opa,
                                                       :table_orders_products opb,
                                                       :table_orders o,
                                                       :table_products p left join :table_products_groups g on p.products_id = g.products_id,
                                                       :table_products_to_categories p2c,
                                                       :table_categories c
                                                  where opa.products_id = :products_id
                                                  and opa.orders_id = opb.orders_id
                                                  and opb.products_id <> :products_id
                                                  and p.products_status = 1
                                                  and p.products_archive = 0
                                                  and opb.products_id = p.products_id
                                                  and opb.orders_id = o.orders_id
                                                  and g.customers_group_id = :customers_group_id
                                                  and g.products_group_view = 1
                                                  and g.price_group_view = 1
                                                  and p.products_id = p2c.products_id
                                                  and p2c.categories_id = c.categories_id
                                                  and c.status = 1
                                                  group by p.products_id
                                                  order by o.date_purchased desc
                                                  limit :limit
                                                  ');

        $Qproducts->bindInt(':products_id', $CLICSHOPPING_ProductsCommon->getID());
        $Qproducts->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
        $Qproducts->bindInt(':limit', MODULE_PRODUCTS_INFO_ALSO_PURCHASED_MAX_DISPLAY);
        $Qproducts->execute();
      }

      if ($Qproducts->rowCount() > 0) {
// display number of short description
        $products_short_description_number = (int)MODULE_PRODUCTS_INFO_ALSO_PURCHASED_SHORT_DESCRIPTION;
// word deletion feature removed; keep $delete_word neutral
        $delete_word = 0;
// quantity input toggle (replaces the former SHORT_DESCRIPTION_DELETE_WORLDS slot)
        $show_qty_input = \defined('MODULE_PRODUCTS_INFO_ALSO_PURCHASED_DISPLAY_QUANTITY_INPUT') && MODULE_PRODUCTS_INFO_ALSO_PURCHASED_DISPLAY_QUANTITY_INPUT === 'True';
// nbr of column to display  boostrap
        $bootstrap_column = (int)MODULE_PRODUCTS_INFO_ALSO_PURCHASED_COLUMNS;
// initialisation des boutons
        $size_button = $CLICSHOPPING_ProductsCommon->getSizeButton('xs');

// Template define
        $filename = '';
        $filename = $CLICSHOPPING_Template->getTemplateModulesFilename($this->group . '/template_html/' . MODULE_PRODUCTS_INFO_ALSO_PURCHASED_TEMPLATE);

        $new_prods_content = '<!-- Start products_also_purchased -->' . "\n";

        $new_prods_content .= '<div class="clearfix"></div>';
        $new_prods_content .= '<div class="mt-1"></div>';
        $new_prods_content .= '<div class="contentContainer">';
        $new_prods_content .= '<div class="contentText">';

        if (MODULE_PRODUCTS_INFO_ALSO_PURCHASED_TITLE == 'True') {
          $new_prods_content .= '<div>';
          $new_prods_content .= '<div class="page-title ModuleProductsInfoAlsoPurchasedHeading"><span class="ModuleProductsInfoAlsoPurchasedHeading"><h2>' . sprintf(CLICSHOPPING::getDef('module_products_info_also_purchased_name'), DateTime::getNow(CLICSHOPPING::getDef('date_format_short'))) . '</h2></span></div>';
          $new_prods_content .= '</div>';
        }

        $new_prods_content .= '<div class="ModuleProductsInfoAlsoPurchasedContainer">';
        $new_prods_content .= '<div class="d-flex flex-wrap">';
        $counter = 1;

        while ($Qproducts->fetch()) {
          $products_id = $Qproducts->valueInt('products_id');

          $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($products_id);
//product name
          $products_name = $CLICSHOPPING_ProductsCommon->getProductsName($products_id);
//Short description
          $products_short_description = $CLICSHOPPING_ProductsCommon->getProductsShortDescription(null, $delete_word, $products_short_description_number);
//Stock (good, alert, out of stock).
          $products_stock = $CLICSHOPPING_ProductsFunctionTemplate->getStock(MODULE_PRODUCTS_INFO_ALSO_PURCHASED_DISPLAY_STOCK, $products_id);
//Flash discount
          $products_flash_discount = $CLICSHOPPING_ProductsFunctionTemplate->getFlashDiscount($products_id, '<br />');
// Minimum quantity to take an order
          $min_order_quantity_products_display = $CLICSHOPPING_ProductsFunctionTemplate->getMinOrderQuantityProductDisplay($products_id);
// display a message in public function the customer group applied - before submit button
          $submit_button_view = $CLICSHOPPING_ProductsFunctionTemplate->getButtonView($products_id);
// button buy
          $button_buy_id = 'buttonBuyId_' . $counter++;
          $buy_button = HTML::button(CLICSHOPPING::getDef('button_buy_now'), null, null, 'primary', ['params' => 'id="' . $button_buy_id . '"'], 'sm');
          $CLICSHOPPING_ProductsCommon->getBuyButton($buy_button);
// Display an input allowing for the customer to insert a quantity

// **************************
          $input_quantity = '';

          if ($show_qty_input && $CLICSHOPPING_ProductsCommon->getProductsAllowingToInsertQuantity($products_id) != '') {
            if ($CLICSHOPPING_ProductsAttributes->getHasProductAttributes($products_id) === false) {
              $input_quantity = CLICSHOPPING::getDef('text_customer_quantity') . ' ' . $CLICSHOPPING_ProductsCommon->getProductsAllowingToInsertQuantity($products_id);
            }
          }

// **************************
// display the differents buttons before minorder qty
// **************************
          $submit_button = '';
          $form = '';
          $endform = '';

          if ($CLICSHOPPING_ProductsCommon->getProductsMinimumQuantity($products_id) != 0 && $CLICSHOPPING_ProductsCommon->getProductsQuantity($products_id) != 0) {
            if ($CLICSHOPPING_ProductsAttributes->getHasProductAttributes($products_id) === false) {
              $minQty = (int)$CLICSHOPPING_ProductsCommon->getProductsMinimumQuantity($products_id);

              if (!$show_qty_input && $minQty > 1) {
// Hidden input + minimum order quantity > 1: link to the product page (shows the min-qty notice).
                $submit_button = HTML::button(CLICSHOPPING::getDef('button_buy_now'), null, $products_name_url, 'primary', null, 'sm');
              } else {
                $form = HTML::form('cart_quantity', CLICSHOPPING::link(null, 'Cart&Add'), 'post', 'class="justify-content-center"', ['tokenize' => true]) . "\n";
                $form .= HTML::hiddenField('products_id', $products_id);

                if (isset($_GET['Id']) || isset($_GET['products_id'])) {
                  $form .= HTML::hiddenField('url', 'Products&Description');
                }

// When the visible field is hidden, still submit the (minimum) quantity.
                if (!$show_qty_input) {
                  $form .= HTML::hiddenField('cart_quantity', max(1, $minQty));
                }

                $endform = '</form>';
                $submit_button = $CLICSHOPPING_ProductsCommon->getProductsBuyButton($products_id);
              }
            }
          }
// Quantity type
          $products_quantity_unit = $CLICSHOPPING_ProductsFunctionTemplate->getProductQuantityUnitType($products_id);


// **************************************************
// Button Free - Must be above getProductsSoldOut
// **************************************************
          if ($CLICSHOPPING_ProductsCommon->getProductsOrdersView($products_id) != 1 && NOT_DISPLAY_PRICE_ZERO == 'false') {

            $submit_button = HTML::button(CLICSHOPPING::getDef('text_products_free'), '', $products_name_url, 'danger');
            $min_quantity = 0;
            $form = '';
            $endform = '';
            $input_quantity = '';
            $min_order_quantity_products_display = '';
          }

// **************************
// Display an information if the stock is sold out for all groups
// **************************
          if (!empty($CLICSHOPPING_ProductsCommon->getProductsSoldOut($products_id))) {
            $submit_button = $CLICSHOPPING_ProductsCommon->getProductsSoldOut($products_id);
            $min_quantity = 0;
            $input_quantity = '';
            $min_order_quantity_products_display = '';
          }

// See the button more view details
          $button_small_view_details = HTML::button(CLICSHOPPING::getDef('button_details'), null, CLICSHOPPING::link($products_name_url), 'info', null, 'sm');
// 10 - Display the image
          $products_image = HTML::link($products_name_url, HTML::image($CLICSHOPPING_Template->getDirectoryTemplateImages() . $Qproducts->value('products_image'), HTML::outputProtected($Qproducts->value('products_name')), MODULE_PRODUCTS_INFO_ALSO_PURCHASED_IMAGE_WIDTH, MODULE_PRODUCTS_INFO_ALSO_PURCHASED_IMAGE_HEIGHT, null, true));
//Ticker Image
          $products_image .= $CLICSHOPPING_ProductsFunctionTemplate->getTicker(MODULE_PRODUCTS_INFO_ALSO_PURCHASED_TICKER, $products_id, 'ModulesProductsInfoBootstrapTickerSpecial', 'ModulesProductsInfoBootstrapTickerFavorite', 'ModulesProductsInfoBootstrapTickerFeatured', 'ModulesProductsInfoBootstrapTickerNew');

          $ticker = $CLICSHOPPING_ProductsFunctionTemplate->getTickerPourcentage(MODULE_PRODUCTS_INFO_ALSO_PURCHASED_POURCENTAGE_TICKER, $products_id, 'ModulesProductsInfoBootstrapTickerPourcentage');

//******************************************************************************************************************
//            Options -- activate and insert code in template and css
//******************************************************************************************************************

// products model
          $products_model = $CLICSHOPPING_ProductsFunctionTemplate->getProductsModel($products_id);
// manufacturer
          $products_manufacturers = $CLICSHOPPING_ProductsFunctionTemplate->getProductsManufacturer($products_id);
// display the price by kilo
          $product_price_kilo = $CLICSHOPPING_ProductsFunctionTemplate->getProductsPriceByWeight($products_id);
// products_price
          $product_price = '';
// display date available
          $products_date_available = $CLICSHOPPING_ProductsFunctionTemplate->getProductsDateAvailable($products_id);
// display products only shop
          $products_only_shop = $CLICSHOPPING_ProductsFunctionTemplate->getProductsOnlyTheShop($products_id);
// display products only shop
          $products_only_web = $CLICSHOPPING_ProductsFunctionTemplate->getProductsOnlyOnTheWebSite($products_id);
// display products packaging
          $products_packaging = $CLICSHOPPING_ProductsFunctionTemplate->getProductsPackaging($products_id);
// display shipping delay
          $products_shipping_delay = $CLICSHOPPING_ProductsFunctionTemplate->getProductsShippingDelay($products_id);
// display products tag
          $tag = $CLICSHOPPING_ProductsFunctionTemplate->getProductsHeadTag($products_id);

          $products_tag = '';
          if (isset($tag) && \is_array($tag)) {
            foreach ($tag as $value) {
              $products_tag .= '#<span class="productTag">' . HTML::link(CLICSHOPPING::link(null, 'Search&keywords=' . HTML::outputProtected(mb_convert_encoding($value, "UTF-8", mb_detect_encoding($value, 'auto'))($value) . '&search_in_description=1&categories_id=&inc_subcat=1'), 'rel="nofollow"'), $value) . '</span> ';
            }
          }
// display products volume
          $products_volume = $CLICSHOPPING_ProductsFunctionTemplate->getProductsVolume($products_id);
// display products weight
          $products_weight = $CLICSHOPPING_ProductsFunctionTemplate->getProductsWeight($products_id);
// Reviews
          $avg_reviews = '<span class="ModulesReviews">' . HTML::stars($CLICSHOPPING_Reviews->getAverageProductReviews($products_id)) . '</span>';
// Json ltd
          $jsonLtd = $CLICSHOPPING_ProductsFunctionTemplate->getProductJsonLd($products_id);

//******************************************************************************************************************
//            End Options -- activate and insert code in template and css
//******************************************************************************************************************

// *************************
//      Template call
// **************************

          if (is_file($filename)) {
            ob_start();
            require($filename);
            $new_prods_content .= ob_get_clean();
          } else {
            echo CLICSHOPPING::getDef('template_does_not_exist') . '<br /> ' . $filename;
            exit;
          }
        } //while

        $new_prods_content .= '</div>';
        $new_prods_content .= '</div>';
        $new_prods_content .= '</div>';
        $new_prods_content .= '</div>' . "\n";

        $new_prods_content .= '<!-- end products also purchased -->' . "\n";

        $CLICSHOPPING_Template->addBlock($new_prods_content, $this->group);

      }// num row
    }// isset id
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_PRODUCTS_INFO_ALSO_PURCHASED_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'What type of template would you like to see displayed?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_TEMPLATE',
        'configuration_value' => 'template_bootstrap_column_5.php',
        'configuration_description' => 'Please indicate the type of template you would like displayed. <br /> <br /> <b> Note </b> <br /> - If you have opted for an online configuration, please choose a type of name. template like <u> template_line </u>. <br /> <br /> - If you opted for a column display, please choose a type of template name like <u> template_column </u> and then configure the number of columns. <br />',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_multi_template_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the title?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_TITLE',
        'configuration_value' => 'True',
        'configuration_description' => 'Displays the title of the module in the catalog',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Indicate the number of new products to display on the home page',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_MAX_DISPLAY',
        'configuration_value' => '6',
        'configuration_description' => 'Please indicate the maximum number of new products to display.',
        'configuration_group_id' => '6',
        'sort_order' => '5',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the number of product columns you would like displayed?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_COLUMNS',
        'configuration_value' => '6',
        'configuration_description' => 'Please indicate the number of product columns to display per line. <br /> <br /> Note: <br /> <br /> - Between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '6',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display a short description of the products on the page?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_SHORT_DESCRIPTION',
        'configuration_value' => '0',
        'configuration_description' => 'Please indicate the length of this description. <br /> <br /> <i> - 0 for no description <br> - 50 for the first 50 characters </i>',
        'configuration_group_id' => '6',
        'sort_order' => '7',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the quantity input field ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_DISPLAY_QUANTITY_INPUT',
        'configuration_value' => 'False',
        'configuration_description' => 'Show the editable quantity field next to the cart button. When disabled, the quantity is submitted automatically (and products with a minimum order quantity greater than 1 link to their product page).',
        'configuration_group_id' => '6',
        'sort_order' => '8',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display a message New / Special / Featured / Favorites?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_TICKER',
        'configuration_value' => 'False',
        'configuration_description' => 'Display a message New / Promotion / Selection / Favorites superimposed on the image of the product? <br /> <br /> the duration is configurable in the Configuration menu / my shop / Minimum / maximum values <br /> <br /> <i> (Value true = Yes - Value false = No) </i>',
        'configuration_group_id' => '6',
        'sort_order' => '9',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Would you like to display the percentage reduction of the price (special) ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_POURCENTAGE_TICKER',
        'configuration_value' => 'False',
        'configuration_description' => 'Show the percentage reduction of the price',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Would you like to display an image regarding the stock status of the product ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_DISPLAY_STOCK',
        'configuration_value' => 'none',
        'configuration_description' => 'Do you want to display an image indicating information on the stock of the product (In stock, practically sold out, out of stock) ?',
        'configuration_group_id' => '6',
        'sort_order' => '10',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'none\', \'image\', \'number\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Qu\'elle est la largeur des images concernant les produits complémentaires achetés ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_IMAGE_WIDTH',
        'configuration_value' => '',
        'configuration_description' => 'Veuillez indiquer la largeur des images qui seront affichées<br /><br /><strong>Note :</strong><br>Si le champs est vide, la taille de l\'image aura sa taille réelle. A défaut, elle sera recalculée en fonction de la nouvelle taille insérée',
        'configuration_group_id' => '6',
        'sort_order' => '10',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Image height',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_IMAGE_HEIGHT',
        'configuration_value' => '',
        'configuration_description' => 'Veuillez indiquer la hauteur des images qui seront affichées<br /><br /><strong>Note :</strong><br>Si le champs est vide, la taille de l\'image aura sa taille réelle. A défaut, elle sera recalculée en fonction de la nouvelle taille insérée',
        'configuration_group_id' => '6',
        'sort_order' => '11',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_SORT_ORDER',
        'configuration_value' => '2000',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '12',
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
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_STATUS',
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_TEMPLATE',
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_TITLE',
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_MAX_DISPLAY',
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_COLUMNS',
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_SHORT_DESCRIPTION',
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_DISPLAY_QUANTITY_INPUT',
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_POURCENTAGE_TICKER',
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_TICKER',
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_DISPLAY_STOCK',
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_IMAGE_WIDTH',
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_IMAGE_HEIGHT',
      'MODULE_PRODUCTS_INFO_ALSO_PURCHASED_SORT_ORDER'
    );
  }
}
