<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Marketing\Specials\Classes\Shop\SpecialsClass;
use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\CockpitAI\ProductsTracking;

class ps_products_special
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

    $this->title = CLICSHOPPING::getDef('module_products_special_title');
    $this->description = CLICSHOPPING::getDef('module_products_special_description');

    if (\defined('MODULE_PRODUCTS_SPECIAL_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_SPECIAL_SORT_ORDER') ? (int)MODULE_PRODUCTS_SPECIAL_SORT_ORDER : 0;
      $this->enabled = (MODULE_PRODUCTS_SPECIAL_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');
    $CLICSHOPPING_ProductsAttributes = Registry::get('ProductsAttributes');
    $CLICSHOPPING_Reviews = Registry::get('Reviews');
    $CLICSHOPPING_Language = Registry::get('Language');

    // normalisation position module (left/right)
    $module_position = ($this->group === 'boxes_column_left') ? 'left' : 'right';

    if (isset($_GET['Products'], $_GET['Specials'])) {
      if (\defined('MODULE_PRODUCTS_SPECIAL_MAX_DISPLAY') && (int)MODULE_PRODUCTS_SPECIAL_MAX_DISPLAY != 0) {
        $Qlisting = SpecialsClass::getListing();

        $Qlisting->setPageSet((int)MODULE_PRODUCTS_SPECIAL_MAX_DISPLAY);
        $Qlisting->execute();

        $listingTotalRow = $Qlisting->getPageSetTotalRows();

        $new_prods_content = '<!-- Specials Products start -->' . "\n";
        $new_prods_content .= '<div class="clearfix"></div>';
        $new_prods_content .= '<div class="contentText">';
        $new_prods_content .= '<div class="ModulesProductsSpecialsContainer">';

        if ($listingTotalRow > 0) {

          $new_prods_content .= '<div class="col-md-5 float-start">';
          $new_prods_content .= '<div class="page-title ModulesProductsSpecialsHeadingTitle">';
          $new_prods_content .= '<h1>' . CLICSHOPPING::getDef('text_heading_title') . '</h1>';
          $new_prods_content .= '</div>';
          $new_prods_content .= '</div>';

          $new_prods_content .= '<div class="col-md-5 float-end">';
          $new_prods_content .= '<div style="padding-right:2em; padding-top:0.5rem;">';
          $new_prods_content .= '<div class="dropdown">';
          $new_prods_content .= '<div class="btn-group btn-group-sm float-end">';
          $new_prods_content .= '<button type="button" class="btn btn-secondary dropdown-toggle"  data-bs-toggle="dropdown" id="dropdownMenu2" aria-haspopup="true" aria-expanded="false">';
          $new_prods_content .= CLICSHOPPING::getDef('text_sort_by');
          $new_prods_content .= '</button>';
          $new_prods_content .= '<ul class="dropdown-menu text-start" aria-labelledby="dropdownMenu2">';

// number of sort criterias
          $column_list = SpecialsClass::getCountColumnList();

          for ($col = 0, $n = \count($column_list); $col < $n; $col++) {
            switch ($column_list[$col]) {
              case 'MODULE_PRODUCTS_SPECIAL_LIST_DATE_ADDED':
                $lc_text = CLICSHOPPING::getDef('table_heading_date');
                break;
              case 'MODULE_PRODUCTS_SPECIAL_LIST_PRICE':
                $lc_text = CLICSHOPPING::getDef('table_heading_price');
                break;
              case 'MODULE_PRODUCTS_SPECIAL_LIST_MODEL':
                $lc_text = CLICSHOPPING::getDef('table_heading_model');
                break;
              case 'MODULE_PRODUCTS_SPECIAL_LIST_QUANTITY':
                $lc_text = CLICSHOPPING::getDef('table_heading_quantity');
                break;
              case 'MODULE_PRODUCTS_SPECIAL_LIST_WEIGHT':
                $lc_text = CLICSHOPPING::getDef('table_heading_weight');
                break;
            }

            $lc_text = $CLICSHOPPING_ProductsCommon->createSortHeading(HTML::sanitize($_GET['sort'] ?? '1a'), $col + 1, $lc_text);

            $new_prods_content .= '<li>' . $lc_text . '</li>';
          }

          $new_prods_content .= '</ul>';
          $new_prods_content .= '</div>';
          $new_prods_content .= '</div>';

          /*
                      if ((\defined('MODULE_HEADER_TAGS_GRID_LIST_VIEW_STATUS') && MODULE_HEADER_TAGS_GRID_LIST_VIEW_STATUS == 'True') && (strpos(MODULE_HEADER_TAGS_GRID_LIST_VIEW_PAGES, CLICSHOPPING::getBaseNameIndex() !== false))) {
                        $link = CLICSHOPPING::link(CLICSHOPPING::getIndex(), CLICSHOPPING::getAllGET(array('page', 'info', 'sort')));
                        $new_prods_content .= '<strong>' . CLICSHOPPING::getDef('text_view') . '</strong>';
                        $new_prods_content .= '<div class="btn-group">';
                        $new_prods_content .= '<a href="' . $link . '" id="list" class="btn btn-default btn-sm"><span class="bi bi-card-list"></span>' . CLICSHOPPING::getDef('text_view_list') . '</a>';
                        $new_prods_content .= '<a href="' . $link . '" id="grid" class="btn btn-default btn-sm"><span class="bi bi-grid-3x2-gap-fill"></span>' . CLICSHOPPING::getDef('text_view_grid') . '</a>';
                        $new_prods_content .= '</div>';
                      }
          */
          $new_prods_content .= '</div>';
          $new_prods_content .= '</div>';

          $new_prods_content .= '<div class="clearfix"></div>';
        }

        if (($listingTotalRow > 0) && ((PREV_NEXT_BAR_LOCATION == '1') || (PREV_NEXT_BAR_LOCATION == '3'))) {
          if ((PREV_NEXT_BAR_LOCATION == '1') || (PREV_NEXT_BAR_LOCATION == '3')) {
            $new_prods_content .= '<div class="clearfix"></div>';
            $new_prods_content .= '<div>';
            $new_prods_content .= '<div class="col-md-6 pagenumber hidden-xs">';
            $new_prods_content .= $Qlisting->getPageSetLabel(CLICSHOPPING::getDef('text_display_number_of_items'));
            $new_prods_content .= '</div>';
            $new_prods_content .= '<div class="col-md-6 float-end">';
            $new_prods_content .= '<div class="float-end pagenav">' . $Qlisting->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y')), 'Shop') . '</div>';
            $new_prods_content .= '<div class="text-end">' . CLICSHOPPING::getDef('text_result_page') . '</div>';
            $new_prods_content .= '</div>';
            $new_prods_content .= '</div>';
            $new_prods_content .= '<div class="mt-1"></div>';
            $new_prods_content .= '<div class="clearfix"></div>';
          }
        }

        $new_prods_content .= '<div class="mt-1"></div>';
        $new_prods_content .= '</div>' . "\n";
        $new_prods_content .= '<div class="boxContentsModulesProductsSpecials">';

        if ($listingTotalRow > 0) {
          $new_prods_content .= '<div class="d-flex flex-wrap">';

// display number of short description
          $products_short_description_number = \defined('MODULE_PRODUCTS_SPECIAL_SHORT_DESCRIPTION') ? (int)MODULE_PRODUCTS_SPECIAL_SHORT_DESCRIPTION : 0;
// delete words
          $delete_word = \defined('MODULE_PRODUCTS_SPECIAL_SHORT_DESCRIPTION_DELETE_WORLDS') ? (int)MODULE_PRODUCTS_SPECIAL_SHORT_DESCRIPTION_DELETE_WORLDS : 0;
// nbr of column to display  boostrap
          $bootstrap_column = \defined('MODULE_PRODUCTS_SPECIAL_COLUMNS') ? (int)MODULE_PRODUCTS_SPECIAL_COLUMNS : 0;
// initialisation des boutons
          $size_button = $CLICSHOPPING_ProductsCommon->getSizeButton('md');

// Template define
          $filename = $CLICSHOPPING_Template->getTemplateModulesFilename($this->group . '/template_html/' . MODULE_PRODUCTS_SPECIAL_TEMPLATE);
          $counter = 1;

          while ($Qlisting->fetch()) {
            $products_id = $Qlisting->valueInt('products_id');
            //trackingProduct
            ProductsTracking::insertProductTracking($products_id,  $this->code, $module_position, $this->sort_order, $CLICSHOPPING_Language->getId(), null, 0.60);

            $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($products_id);
//product name
            $products_name = $CLICSHOPPING_ProductsCommon->getProductsName($products_id);
//Stock (good, alert, out of stock).
            $products_stock = $CLICSHOPPING_ProductsFunctionTemplate->getStock(MODULE_PRODUCTS_SPECIAL_DISPLAY_STOCK, $products_id);
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
              $input_quantity = $CLICSHOPPING_ProductsFunctionTemplate->getDisplayInputQuantity(MODULE_PRODUCTS_SPECIAL_DELETE_BUY_BUTTON, $products_id);
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

            if (MODULE_PRODUCTS_SPECIAL_DELETE_BUY_BUTTON == 'False') {
              if ($CLICSHOPPING_ProductsCommon->getProductsMinimumQuantity($products_id) != 0 && $CLICSHOPPING_ProductsCommon->getProductsQuantity($products_id) != 0) {
                if ($CLICSHOPPING_ProductsAttributes->getHasProductAttributes($products_id) === false) {
                  $form = HTML::form('cart_quantity', CLICSHOPPING::link(null, 'Cart&Add'), 'post', 'class="justify-content-center"', ['tokenize' => true]) . "\n";
                  $form .= HTML::hiddenField('products_id', $products_id);
                  if (isset($_GET['Specials'])) $form .= HTML::hiddenField('url', 'Products&Specials');
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
              $form = '';
              $endform = '';
              $min_quantity = 0;
              $input_quantity = '';
              $min_order_quantity_products_display = '';
            }

// See the button more view details
            $button_small_view_details = $CLICSHOPPING_ProductsFunctionTemplate->getButtonViewDetails(MODULE_PRODUCTS_SPECIAL_DELETE_BUY_BUTTON, $products_id);
// Display the image
            $products_image = $CLICSHOPPING_ProductsFunctionTemplate->getImage(MODULE_PRODUCTS_SPECIAL_IMAGE_MEDIUM, $products_id);
// Ticker Image
            $products_image .= $CLICSHOPPING_ProductsFunctionTemplate->getTicker(MODULE_PRODUCTS_SPECIAL_TICKER, $products_id, 'ModulesProductsSpecialsBootstrapTickerSpecial', 'ModulesProductsSpecialsBootstrapTickerFavorites', 'ModulesProductsSpecialsBootstrapTickerFeatured', 'ModulesProductsSpecialsBootstrapTickerNew');

            $ticker = $CLICSHOPPING_ProductsFunctionTemplate->getTickerPourcentage(MODULE_PRODUCTS_SPECIAL_POURCENTAGE_TICKER, $products_id, 'ModulesProductsFeaturedBootstrapTickerPourcentage');

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

          $new_prods_content .= '</div>';  // flex
        } else {
          $new_prods_content .= '<div class="text-center alert alert-info">' . CLICSHOPPING::getDef('text_no_products') . '</div>';
        }

        if (($listingTotalRow > 0) && ((PREV_NEXT_BAR_LOCATION == '2') || (PREV_NEXT_BAR_LOCATION == '3'))) {
          if ((PREV_NEXT_BAR_LOCATION == '2') || (PREV_NEXT_BAR_LOCATION == '3')) {
            $new_prods_content .= '<div class="clearfix"></div>';
            $new_prods_content .= '<div class="mt-1"></div>';
            $new_prods_content .= '<div class="col-md-12">';
            $new_prods_content .= '<div class="col-md-6 pagenumber hidden-xs">';
            $new_prods_content .= $Qlisting->getPageSetLabel(CLICSHOPPING::getDef('text_display_number_of_items'));
            $new_prods_content .= '</div>';
            $new_prods_content .= '<div class="col-md-6 float-end">';
            $new_prods_content .= '<span class="float-end pagenav">' . $Qlisting->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y')), 'Shop') . '</span>';
            $new_prods_content .= '<span class="text-end">' . CLICSHOPPING::getDef('text_result_page') . '</span>';
            $new_prods_content .= '</div>';
            $new_prods_content .= '</div>';
            $new_prods_content .= '<div class="clearfix"></div>';
          }
        }

        $new_prods_content .= '</div>';
      } else {
        $new_prods_content = '<div class="text-center alert alert-info">' . CLICSHOPPING::getDef('text_no_products') . '</div>';
      } // max display product

      $new_prods_content .= '</div>' . "\n";

      $new_prods_content .= '<!--  Products special End -->' . "\n";

      $CLICSHOPPING_Template->addBlock($new_prods_content, $this->group);

    } // php_self
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_PRODUCTS_SPECIAL_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select your template ?',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_TEMPLATE',
        'configuration_value' => 'template_bootstrap_column_5.php',
        'configuration_description' => 'Select your template you want to display',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_multi_template_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the number of product do you want to display',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_MAX_DISPLAY',
        'configuration_value' => '6',
        'configuration_description' => 'Please, indicate the number of products you want to display.',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the number of column that you want to display ?',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_COLUMNS',
        'configuration_value' => '6',
        'configuration_description' => 'Choose a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display a short description ?',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_SHORT_DESCRIPTION',
        'configuration_value' => '0',
        'configuration_description' => 'Please indicate a number of your short description',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to remove words of your short description ?',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_SHORT_DESCRIPTION_DELETE_WORLDS',
        'configuration_value' => '0',
        'configuration_description' => 'Indicate Remove words of your short description for the first caracters',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display a message News / Specials / Favorites / Featured ?',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_TICKER',
        'configuration_value' => 'False',
        'configuration_description' => 'Display a message News / Specials / Favorites / Featured',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the discount pourcentage (specials) ?',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_POURCENTAGE_TICKER',
        'configuration_value' => 'False',
        'configuration_description' => 'Display the discount pourcentage (specials)',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the stock ?',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_DISPLAY_STOCK',
        'configuration_value' => 'none',
        'configuration_description' => 'Display the stock (in stock, sold out, out of stock) ?',
        'configuration_group_id' => '6',
        'sort_order' => '6',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'none\', \'image\', \'number\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate a arrival date sort order',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_LIST_DATE_ADDED',
        'configuration_value' => '1',
        'configuration_description' => 'This option allow to choose an order to display the product. Lowest is displayed in first; 0 for nothing',
        'configuration_group_id' => '6',
        'sort_order' => '5',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate a price sort order',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_LIST_PRICE',
        'configuration_value' => '0',
        'configuration_description' => 'This option allow to choose an order to display the product. Lowest is displayed in first; 0 for nothing',
        'configuration_group_id' => '6',
        'sort_order' => '6',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate a model sort order',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_LIST_MODEL',
        'configuration_value' => '0',
        'configuration_description' => 'This option allow to choose an order to display the product. Lowest is displayed in first; 0 for nothing',
        'configuration_group_id' => '6',
        'sort_order' => '7',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate a quantity sort order',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_LIST_QUANTITY',
        'configuration_value' => '0',
        'configuration_description' => 'This option allow to choose an order to display the product. Lowest is displayed in first; 0 for nothing',
        'configuration_group_id' => '6',
        'sort_order' => '8',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate a weight sort order',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_LIST_WEIGHT',
        'configuration_value' => '0',
        'configuration_description' => 'This option allow to choose an order to display the product. Lowest is displayed in first; 0 for nothing',
        'configuration_group_id' => '6',
        'sort_order' => '9',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please choose the image size',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_IMAGE_MEDIUM',
        'configuration_value' => 'Small',
        'configuration_description' => 'What image size do you want to display?',
        'configuration_group_id' => '6',
        'sort_order' => '10',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'Small\', \'Medium\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to remove the details button ?',
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_DELETE_BUY_BUTTON',
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
        'configuration_key' => 'MODULE_PRODUCTS_SPECIAL_SORT_ORDER',
        'configuration_value' => '100',
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
    return array(
      'MODULE_PRODUCTS_SPECIAL_STATUS',
      'MODULE_PRODUCTS_SPECIAL_TEMPLATE',
      'MODULE_PRODUCTS_SPECIAL_MAX_DISPLAY',
      'MODULE_PRODUCTS_SPECIAL_COLUMNS',
      'MODULE_PRODUCTS_SPECIAL_SHORT_DESCRIPTION',
      'MODULE_PRODUCTS_SPECIAL_SHORT_DESCRIPTION_DELETE_WORLDS',
      'MODULE_PRODUCTS_SPECIAL_TICKER',
      'MODULE_PRODUCTS_SPECIAL_POURCENTAGE_TICKER',
      'MODULE_PRODUCTS_SPECIAL_DISPLAY_STOCK',
      'MODULE_PRODUCTS_SPECIAL_LIST_DATE_ADDED',
      'MODULE_PRODUCTS_SPECIAL_LIST_PRICE',
      'MODULE_PRODUCTS_SPECIAL_LIST_MODEL',
      'MODULE_PRODUCTS_SPECIAL_LIST_QUANTITY',
      'MODULE_PRODUCTS_SPECIAL_LIST_WEIGHT',
      'MODULE_PRODUCTS_SPECIAL_IMAGE_MEDIUM',
      'MODULE_PRODUCTS_SPECIAL_DELETE_BUY_BUTTON',
      'MODULE_PRODUCTS_SPECIAL_SORT_ORDER'
    );
  }
}
