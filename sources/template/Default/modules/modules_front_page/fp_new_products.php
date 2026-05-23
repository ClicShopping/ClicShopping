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

class fp_new_products
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

    $this->title = CLICSHOPPING::getDef('module_front_page_new_products_title');
    $this->description = CLICSHOPPING::getDef('module_front_page_new_products_description');

    if (\defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_STATUS')) {
      $this->sort_order = defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_SORT_ORDER') ? (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_SORT_ORDER : 0;
      $this->enabled = (defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_STATUS') && MODULE_FRONT_PAGE_NEW_PRODUCTS_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');
    $CLICSHOPPING_Category = Registry::get('Category');
    $CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');
    $CLICSHOPPING_Reviews = Registry::get('Reviews');

    $new_products_category_id = $CLICSHOPPING_Category->getID();

    if (CLICSHOPPING::getBaseNameIndex() && !$CLICSHOPPING_Category->getPath()) {
      if (defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY') && (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY != 0) {
        if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
          if ((!isset($new_products_category_id)) || ($new_products_category_id == 0)) {
// Display products no inside categories
            $Qproduct = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                            p.products_quantity as in_stock
                                                    from :table_products p left join :table_products_groups g on p.products_id = g.products_id,
                                                         :table_products_to_categories p2c,
                                                         :table_categories c
                                                    where g.customers_group_id = :customers_group_id
                                                    and g.products_group_view = 1
                                                    and p.products_status = 1
                                                    and p.products_archive = 0
                                                    and p.products_id = p2c.products_id
                                                    and p2c.categories_id = c.categories_id
                                                    and c.status = 1
                                                    group by p.products_id
                                                    order by rand(),
                                                             p.products_date_added DESC
                                                    limit :products_limit
                                                    ');
            $Qproduct->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
            $Qproduct->bindInt(':products_limit', defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY') ? (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY : 0);
            $Qproduct->execute();
          } else {
// Requetes SQL pour afficher les nouveaux produits groupe B2B lorsque l'on se trouve dans une categorie
            $Qproduct = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                            p.products_quantity as in_stock
                                                      from :table_products p left join :table_products_groups g on p.products_id = g.products_id,
                                                            :table_products_to_categories p2c,
                                                            :table_categories c
                                                      where p.products_id = p2c.products_id
                                                      and p2c.categories_id = c.categories_id
                                                      and c.parent_id = :parent_id
                                                      and g.customers_group_id = :customers_group_id
                                                      and g.products_group_view = 1
                                                      and p.products_status = 1
                                                      and p.products_archive = 0
                                                      and c.virtual_categories = 0
                                                      and c.status = 1
                                                      group by p.products_id
                                                      order by rand(),
                                                               p.products_date_added DESC
                                                      limit :products_limit
                                                      ');
            $Qproduct->bindInt(':parent_id', (int)$new_products_category_id);
            $Qproduct->bindInt(':customers_group_id', $CLICSHOPPING_Customer->getCustomersGroupID());
            $Qproduct->bindInt(':products_limit', defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY') ? (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY : 0);
            $Qproduct->execute();
          }
        } else {
          if (!isset($new_products_category_id) || ($new_products_category_id == 0)) {
// Display products no inside categories
            $Qproduct = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                            p.products_quantity as in_stock
                                                    from :table_products p,
                                                         :table_products_to_categories p2c,
                                                         :table_categories c
                                                    where p.products_status = 1
                                                    and products_view = 1
                                                    and p.products_archive = 0
                                                    and p.products_id = p2c.products_id
                                                    and p2c.categories_id = c.categories_id
                                                    and c.virtual_categories = 0
                                                    and c.status = 1
                                                    group by p.products_id
                                                    order by rand(),
                                                             p.products_date_added DESC
                                                    limit :products_limit
                                                  ');
            $Qproduct->bindInt(':products_limit', defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY') ? (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY : 0);
            $Qproduct->execute();
          } else {
// Requetes SQL pour afficher les nouveaux produits lors que l'on ce trouve dans une categorie
            $Qproduct = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                            p.products_quantity as in_stock
                                                    from :table_products p,
                                                         :table_products_to_categories p2c,
                                                         :table_categories c
                                                    where p.products_id = p2c.products_id
                                                    and p2c.categories_id = c.categories_id
                                                    and c.parent_id = :parent_id
                                                    and p.products_status = 1
                                                    and p.products_view = 1
                                                    and p.products_archive = 0
                                                    and c.virtual_categories = 0
                                                    and c.status = 1
                                                    group by p.products_id
                                                    order by rand(),
                                                             p.products_date_added DESC
                                                    limit :products_limit
                                                  ');

            $Qproduct->bindInt(':parent_id', (int)$new_products_category_id);
            $Qproduct->bindInt(':products_limit', defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY') ? (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY : 0);
            $Qproduct->execute();
          }
        }

        if ($Qproduct->rowCount() > 0) {
// display number of short description
          $products_short_description_number = defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_SHORT_DESCRIPTION') ? (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_SHORT_DESCRIPTION : 0;
// delete words
          $delete_word = defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_SHORT_DESCRIPTION_DELETE_WORLDS') ? (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_SHORT_DESCRIPTION_DELETE_WORLDS : 0;
// nbr of column to display  boostrap
          $bootstrap_column = defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_COLUMNS') ? (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_COLUMNS : 0;
// initialisation des boutons
          $size_button = $CLICSHOPPING_ProductsCommon->getSizeButton('md');

// Template define
          $filename = $CLICSHOPPING_Template->getTemplateModulesFilename($this->group . '/template_html/' . MODULE_FRONT_PAGE_NEW_PRODUCTS_TEMPLATE);

          $new_prods_content = '<!-- New Products start -->' . "\n";
          $new_prods_content .= '<div class="col-md-12 ModuleFrontPageNewProductsContainer5">';

          if (defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_FRONT_TITLE') && MODULE_FRONT_PAGE_NEW_PRODUCTS_FRONT_TITLE == 'True') {
            $new_prods_content .= '<div class="ModuleFrontPageProductsNewHeading"><h2>' . CLICSHOPPING::getDef('module_front_page_new_products_heading_title') . '</h2></div>';
          }

          $new_prods_content .= '<div class="d-flex flex-wrap">';
          $counter = 1;

          while ($Qproduct->fetch()) {
            $products_id = $Qproduct->valueInt('products_id');
            $_POST['products_id'] = $products_id;

            $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($products_id);
//product name
            $products_name = $CLICSHOPPING_ProductsCommon->getProductsName($products_id);
//Stock (good, alert, out of stock).
            $products_stock = $CLICSHOPPING_ProductsFunctionTemplate->getStock(defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_STOCK') ? MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_STOCK : '', $products_id);
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
            if ($CLICSHOPPING_ProductsCommon->getProductsQuantity() > 0) {
              $input_quantity = $CLICSHOPPING_ProductsFunctionTemplate->getDisplayInputQuantity(defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_DELETE_BUY_BUTTON') ? MODULE_FRONT_PAGE_NEW_PRODUCTS_DELETE_BUY_BUTTON : '', $products_id);
            } else {
              $input_quantity = '';
            }

// display the differents prices before button
            $product_price = $CLICSHOPPING_ProductsCommon->getCustomersPrice($products_id);
//Short description
            $products_short_description = $CLICSHOPPING_ProductsCommon->getProductsShortDescription($products_id, $delete_word, $products_short_description_number);
// Reviews
            $avg_reviews = '<span class="ModulesReviews">' . HTML::stars($CLICSHOPPING_Reviews->getAverageProductReviews($products_id)) . '</span>';

// **************************
// display the differents buttons before minorder qty
// **************************
            $submit_button = '';
            $form = '';
            $endform = '';

            if (defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_DELETE_BUY_BUTTON') && MODULE_FRONT_PAGE_NEW_PRODUCTS_DELETE_BUY_BUTTON == 'False') {
              if ($CLICSHOPPING_ProductsCommon->getProductsMinimumQuantity($products_id) != 0 && $CLICSHOPPING_ProductsCommon->getProductsQuantity($products_id) != 0) {
                if ($CLICSHOPPING_ProductsAttributes->getHasProductAttributes($products_id) === false) {
                  $form = HTML::form('cart_quantity', CLICSHOPPING::link(null, 'Cart&Add'), 'post', 'class="justify-content-center"', ['tokenize' => true]) . "\n";
                  $form .= HTML::hiddenField('products_id', $products_id);
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
            if ($CLICSHOPPING_ProductsCommon->getProductsOrdersView($products_id) != 1 && defined('NOT_DISPLAY_PRICE_ZERO') && NOT_DISPLAY_PRICE_ZERO == 'false') {
              $submit_button = HTML::button(CLICSHOPPING::getDef('text_products_free'), '', $products_name_url, 'danger');
              $min_quantity = 0;
              $form = '';
              $endform = '';
              $input_quantity = '';
              $min_order_quantity_products_display = '';
            }

// **************************
// Display an information if the stock is sold_out for all groups
// **************************
            if (!empty($CLICSHOPPING_ProductsCommon->getProductsSoldOut($products_id))) {
              $submit_button = $CLICSHOPPING_ProductsCommon->getProductsSoldOut($products_id);
              $form = '';
              $endform = '';
              $min_quantity = 0;
              $input_quantity = '';
              $min_order_quantity_products_display = '';
            }

// See the button more view details
            $button_small_view_details = $CLICSHOPPING_ProductsFunctionTemplate->getButtonViewDetails(defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_DELETE_BUY_BUTTON') ? MODULE_FRONT_PAGE_NEW_PRODUCTS_DELETE_BUY_BUTTON : '', $products_id);

// Display the image
            $products_image = $CLICSHOPPING_ProductsFunctionTemplate->getImage(defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_IMAGE_MEDIUM') ? MODULE_FRONT_PAGE_NEW_PRODUCTS_IMAGE_MEDIUM : '', $products_id);

// Ticker Image
            $products_image .= $CLICSHOPPING_ProductsFunctionTemplate->getTicker(defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_TICKER') ? MODULE_FRONT_PAGE_NEW_PRODUCTS_TICKER : '', $products_id, 'ModulesFrontPageTickerBootstrapTickerSpecial', 'ModulesFrontPageTickerBootstrapTickerFavorite', 'ModulesFrontPageTickerBootstrapTickerFeatured', 'ModulesFrontPageTickerBootstrapTickerNew');

            $ticker = $CLICSHOPPING_ProductsFunctionTemplate->getTickerPourcentage(defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_POURCENTAGE_TICKER') ? MODULE_FRONT_PAGE_NEW_PRODUCTS_POURCENTAGE_TICKER : '', $products_id, 'ModulesFrontPageTickerBootstrapTickerPourcentage');

//******************************************************************************************************************
//            Options -- activate and insert code in template and css
//******************************************************************************************************************

// products model
            $products_model = $CLICSHOPPING_ProductsFunctionTemplate->getProductsModel($products_id);
// manufacturer
            $products_manufacturers = $CLICSHOPPING_ProductsFunctionTemplate->getProductsManufacturer($products_id);
// display the price by kilo
            $product_price_kilo = $CLICSHOPPING_ProductsFunctionTemplate->getProductsPriceByWeight($products_id);
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
                $products_tag .= '#<span class="productTag">' . HTML::link(CLICSHOPPING::link(null, 'Search&keywords=' . HTML::outputProtected(mb_convert_encoding($value, "UTF-8", mb_detect_encoding($value, 'auto')) . '&search_in_description=1&categories_id=&inc_subcat=1'), 'rel="nofollow"'), $value) . '</span> ';
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

          $new_prods_content .= '</div>' . "\n";
          $new_prods_content .= '</div>' . "\n";

          $new_prods_content .= '<!-- New Products End -->' . "\n";

          $CLICSHOPPING_Template->addBlock($new_prods_content, $this->group);
        }
      }
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select your template',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_TEMPLATE',
        'configuration_value' => 'template_bootstrap_column_5.php',
        'configuration_description' => 'Select your template',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_multi_template_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the title ?',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_FRONT_TITLE',
        'configuration_value' => 'True',
        'configuration_description' => 'Display the title',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the number to display',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY',
        'configuration_value' => '6',
        'configuration_description' => 'Indicate the number to display.',
        'configuration_group_id' => '6',
        'sort_order' => '5',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the number of column that you want to display ?',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_COLUMNS',
        'configuration_value' => '6',
        'configuration_description' => 'Choose a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '6',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display a short description ?',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_SHORT_DESCRIPTION',
        'configuration_value' => '0',
        'configuration_description' => 'Please indicate a number of your short description',
        'configuration_group_id' => '6',
        'sort_order' => '7',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to remove words of your short description ?',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_SHORT_DESCRIPTION_DELETE_WORLDS',
        'configuration_value' => '0',
        'configuration_description' => 'Please indicate a number',
        'configuration_group_id' => '6',
        'sort_order' => '8',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display a message News / Specials / Favorites / Featured ?',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_TICKER',
        'configuration_value' => 'False',
        'configuration_description' => 'Display a message News / Specials / Favorites / Featured',
        'configuration_group_id' => '6',
        'sort_order' => '9',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the discount pourcentage (specials) ?',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_POURCENTAGE_TICKER',
        'configuration_value' => 'False',
        'configuration_description' => 'Display the discount pourcentage (specials)',
        'configuration_group_id' => '6',
        'sort_order' => '9',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the stock ?',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_STOCK',
        'configuration_value' => 'none',
        'configuration_description' => 'Display the stock (in stock, sold out, out of stock) ?',
        'configuration_group_id' => '6',
        'sort_order' => '10',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'none\', \'image\', \'number\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please choose the image size',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_IMAGE_MEDIUM',
        'configuration_value' => 'Small',
        'configuration_description' => 'Choose a size, small or medium to display',
        'configuration_group_id' => '6',
        'sort_order' => '11',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'Small\', \'Medium\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to remove the details button ?',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_DELETE_BUY_BUTTON',
        'configuration_value' => 'False',
        'configuration_description' => 'Remove the button details',
        'configuration_group_id' => '6',
        'sort_order' => '11',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_SORT_ORDER',
        'configuration_value' => '110',
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
    return array('MODULE_FRONT_PAGE_NEW_PRODUCTS_STATUS',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_TEMPLATE',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_FRONT_TITLE',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_COLUMNS',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_SHORT_DESCRIPTION',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_SHORT_DESCRIPTION_DELETE_WORLDS',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_TICKER',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_POURCENTAGE_TICKER',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_STOCK',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_IMAGE_MEDIUM',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_DELETE_BUY_BUTTON',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_SORT_ORDER'
    );
  }
}
