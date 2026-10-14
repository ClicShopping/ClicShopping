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
use ClicShopping\Apps\Catalog\Products\Classes\Shop\ProductsListingContext;
use ClicShopping\Apps\Catalog\Products\Classes\Shop\ProductsListingRenderer;
use ClicShopping\Sites\Shop\ProductsListing;
use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\CockpitAI\ProductsTracking;

/**
 *
 *
 * This class handles the display and configuration of the "Products Listing" module in ClicShopping.
 * It manages the listing, sorting, and rendering of new products for customers, including
 * configuration options for display templates, columns, short descriptions, stock, and more.
 *
 */
class pl_products_listing
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

    $this->title = CLICSHOPPING::getDef('module_products_listing_title');
    $this->description = CLICSHOPPING::getDef('module_products_listing_description');

    if (\defined('MODULE_PRODUCTS_LISTING_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_LISTING_SORT_ORDER') ? (int)MODULE_PRODUCTS_LISTING_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_PRODUCTS_LISTING_STATUS') ? (MODULE_PRODUCTS_LISTING_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Category = Registry::get('Category');
    $CLICSHOPPING_Manufacturers = Registry::get('Manufacturers');

    // normalisation position module (left/right)
    $module_position = ($this->group === 'boxes_column_left') ? 'left' : 'right';

    if ($CLICSHOPPING_Category->getPath() || $CLICSHOPPING_Manufacturers->getID() || !isset($_GET['Search'])) {
     // if (\defined('MODULE_PRODUCTS_LISTING_MAX_DISPLAY') && (int)MODULE_PRODUCTS_LISTING_MAX_DISPLAY != 0) {
        if (!Registry::exists('ProductsListing')) {
          Registry::set('ProductsListing', new ProductsListing());
        }

        $ProductsListing = Registry::get('ProductsListing');
        $Qlisting = $ProductsListing->getData();

        $listingTotalRow = $ProductsListing->getTotalRow();

        $context = new ProductsListingContext(
          constantsPrefix: 'MODULE_PRODUCTS_LISTING',
          cssContainerClass: 'ModulesProductsListingContainer',
          cssHeadingClass: null,
          headingTextDef: null,
          tickerClasses: [
            'special' => 'ModulesProductsListingBootstrapTickerSpecial',
            'favorite' => 'ModulesProductsListingBootstrapTickerFavorite',
            'featured' => 'ModulesProductsListingBootstrapTickerFeatured',
            'new' => 'ModulesProductsListingBootstrapTickerNew',
          ],
          tickerPercentageClass: 'ModulesProductsListingBootstrapTickerPourcentage',
          group: $this->group,
          trackingCode: $this->code,
          modulePosition: $module_position,
          sortOrder: (int)$this->sort_order,
          trackingWeight: 0.45,
          listingCommentLabel: 'Products Listing',
          hiddenUrlField: 'Cpath',
          displayCartButton: defined('MODULE_PRODUCTS_LISTING_DISPLAY_CART_BUTTON') && MODULE_PRODUCTS_LISTING_DISPLAY_CART_BUTTON === 'True',
          displayDetailsButton: defined('MODULE_PRODUCTS_LISTING_DISPLAY_DETAILS_BUTTON') && MODULE_PRODUCTS_LISTING_DISPLAY_DETAILS_BUTTON === 'True',
          displaySortBar: false,
          sortColumns: [],
          displayViewSwitch: true,
          displayOptions: [
            'weight' => false,
            'quantityUnit' => false,
          ],
        );

        $renderer = new ProductsListingRenderer($context);

        $new_prods_content = '<!-- products listing start -->' . "\n";
        $new_prods_content .= '<div class="clearfix"></div>';
        $new_prods_content .= '<div class="mt-1"></div>';

        $new_prods_content .= '<div class="ModulesProductsListingContainer">';

          $new_prods_content .= '<div class="col-md-12 float-end">';
          $new_prods_content .= '<div style="padding-right:2em; padding-top:0.5rem;">';
          $new_prods_content .= '<div class="dropdown">';
          $new_prods_content .= '<div class="btn-group btn-group-sm float-end">';
          $new_prods_content .= '<button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" id="dropdownMenu2" aria-haspopup="true" aria-expanded="false">';
          $new_prods_content .= CLICSHOPPING::getDef('text_sort_by');
          $new_prods_content .= '</button>';
          $new_prods_content .= '<ul class="dropdown-menu text-start" aria-labelledby="dropdownMenu2">';

          // number of sort criterias
          $column_list =  $ProductsListing->getColumnList();
          $lc_text = CLICSHOPPING::getDef('table_heading_date');

          for ($col = 0, $n = \count($column_list); $col < $n; $col++) {
            switch ($column_list[$col]) {
              case 'PRODUCT_LIST_MODEL':
                $lc_text = CLICSHOPPING::getDef('table_heading_model');
                break;
              case 'PRODUCT_LIST_NAME':
                $lc_text = CLICSHOPPING::getDef('table_heading_products');
                break;
              case 'PRODUCT_LIST_MANUFACTURER':
                $lc_text = CLICSHOPPING::getDef('table_heading_manufacturer');
                break;
              case 'PRODUCT_LIST_PRICE':
                $lc_text = CLICSHOPPING::getDef('table_heading_price');
                break;
              case 'PRODUCT_LIST_QUANTITY':
                $lc_text = CLICSHOPPING::getDef('table_heading_quantity');
                break;
              case 'PRODUCT_LIST_WEIGHT':
                $lc_text = CLICSHOPPING::getDef('table_heading_weight');
                break;
              case 'PRODUCT_LIST_IMAGE':
                $lc_text = CLICSHOPPING::getDef('table_heading_image');
                break;
              case 'PRODUCT_LIST_DATE':
                $lc_text = CLICSHOPPING::getDef('table_heading_date');
                break;
            }

            if (($column_list[$col] != 'PRODUCT_LIST_BUY_NOW') && ($column_list[$col] != 'PRODUCT_LIST_IMAGE')) {
              if (isset($_GET['sort'])) {
                $lc_text = $CLICSHOPPING_ProductsCommon->createSortHeading(HTML::sanitize($_GET['sort'] ?? '1a'), $col + 1, $lc_text);
                $new_prods_content .= '<li><a href="#">' . $lc_text . '</a></li>';
              }
            }
          }

        $new_prods_content .= '</ul>';
        $new_prods_content .= '<div  style="padding-left:0.5rem;">' . $renderer->renderViewSwitch() . '</div>';
        $new_prods_content .= '</div>';
        $new_prods_content .= '</div>';
        $new_prods_content .= '</div>';
        $new_prods_content .= '<div class="clearfix"></div>';
        $new_prods_content .= '<div class="mt-1"></div>';

        $new_prods_content .= '<div class="boxContentsModulesProductsListing">';

        if ($listingTotalRow > 0) {
          $new_prods_content .= $renderer->renderList($Qlisting);

          if (($listingTotalRow > 0) && ((PREV_NEXT_BAR_LOCATION == '2') || (PREV_NEXT_BAR_LOCATION == '3'))) {
            if ((PREV_NEXT_BAR_LOCATION == '2') || (PREV_NEXT_BAR_LOCATION == '3')) {
              $new_prods_content .= '<div class="clearfix"></div>';
              $new_prods_content .= '<div class="col-md-6 pagenumber hidden-xs">';
              $new_prods_content .= $Qlisting->getPageSetLabel(CLICSHOPPING::getDef('text_display_number_of_items'));
              $new_prods_content .= '</div>';
              $new_prods_content .= '<div class="col-md-6 float-end">';
              $new_prods_content .= '<div class="float-end pagenav">' . $Qlisting->getPageSetLinks(CLICSHOPPING::getAllGET(array('page', 'info', 'x', 'y')), 'Shop') . '</div>';
              $new_prods_content .= '<div class="text-end">' . CLICSHOPPING::getDef('text_result_page') . '</div>';
              $new_prods_content .= '</div>';
              $new_prods_content .= '<div style="padding-top:10px;"></div>';
              $new_prods_content .= '<div class="clearfix"></div>';
            }
          }
        } else {
          $new_prods_content .= '<div class="mt-1"></div>';
          $new_prods_content .= '<div class="text-center alert alert-info">' . CLICSHOPPING::getDef('text_no_products') . '</div>';
        }

        $new_prods_content .= '</div>';

        $new_prods_content .= '</div>' . "\n";
        $new_prods_content .= '<!--  Products listing End -->' . "\n";

        $CLICSHOPPING_Template->addBlock($new_prods_content, $this->group);
      } // category / manufacturer / not search
  } // public function execute

  /**
   * Returns whether the module is enabled.
   *
   * @return bool
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Checks if the module configuration is defined.
   *
   * @return bool
   */
  public function check()
  {
    return \defined('MODULE_PRODUCTS_LISTING_STATUS');
  }

  /**
   * Installs the module configuration into the database.
   *
   * @return void
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_STATUS',
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
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_TEMPLATE',
        'configuration_value' => 'template_bootstrap_column_5.php',
        'configuration_description' => 'Select your template',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_multi_template_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the number of product do you want to display',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_MAX_DISPLAY',
        'configuration_value' => '6',
        'configuration_description' => 'Indicate the number of product do you want to display',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the number of column that you want to display ?',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_COLUMNS',
        'configuration_value' => '4',
        'configuration_description' => 'Choose a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display a short description ?',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_SHORT_DESCRIPTION',
        'configuration_value' => '0',
        'configuration_description' => 'Please indicate a number of your short description',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the quantity input field ?',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_DISPLAY_QUANTITY_INPUT',
        'configuration_value' => 'False',
        'configuration_description' => 'Show the editable quantity field next to the cart button. When disabled, the quantity is submitted automatically (and products with a minimum order quantity greater than 1 link to their product page).',
        'configuration_group_id' => '6',
        'sort_order' => '8',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display a message News / Specials / Favorites / Featured ?',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_TICKER',
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
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_POURCENTAGE_TICKER',
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
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_DISPLAY_STOCK',
        'configuration_value' => 'none',
        'configuration_description' => 'Display the stock (in stock, sold out, out of stock) ?',
        'configuration_group_id' => '6',
        'sort_order' => '6',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'none\', \'image\', \'number\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please choose the image size',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_IMAGE_MEDIUM',
        'configuration_value' => 'Small',
        'configuration_description' => 'What image size do you want to display?',
        'configuration_group_id' => '6',
        'sort_order' => '10',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'Small\', \'Medium\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the details button ?',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_DISPLAY_DETAILS_BUTTON',
        'configuration_value' => 'True',
        'configuration_description' => 'Display or remove the details button',
        'configuration_group_id' => '6',
        'sort_order' => '11',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the cart button ?',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_DISPLAY_CART_BUTTON',
        'configuration_value' => 'True',
        'configuration_description' => 'Display or remove the cart button',
        'configuration_group_id' => '6',
        'sort_order' => '13',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_SORT_ORDER',
        'configuration_value' => '100',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '50',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes the module configuration from the database.
   *
   * @return int
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Returns the configuration keys used by this module.
   *
   * @return array
   */
  public function keys()
  {
    return array(
      'MODULE_PRODUCTS_LISTING_STATUS',
      'MODULE_PRODUCTS_LISTING_TEMPLATE',
      'MODULE_PRODUCTS_LISTING_MAX_DISPLAY',
      'MODULE_PRODUCTS_LISTING_COLUMNS',
      'MODULE_PRODUCTS_LISTING_SHORT_DESCRIPTION',
      'MODULE_PRODUCTS_LISTING_DISPLAY_QUANTITY_INPUT',
      'MODULE_PRODUCTS_LISTING_TICKER',
      'MODULE_PRODUCTS_LISTING_POURCENTAGE_TICKER',
      'MODULE_PRODUCTS_LISTING_DISPLAY_STOCK',
      'MODULE_PRODUCTS_LISTING_IMAGE_MEDIUM',
      'MODULE_PRODUCTS_LISTING_DISPLAY_DETAILS_BUTTON',
      'MODULE_PRODUCTS_LISTING_DISPLAY_CART_BUTTON',
      'MODULE_PRODUCTS_LISTING_SORT_ORDER'
    );
  }
}
