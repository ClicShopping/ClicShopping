<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Catalog\Products\Classes\Shop\ProductsListingContext;
use ClicShopping\Apps\Catalog\Products\Classes\Shop\ProductsListingRenderer;

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
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Category = Registry::get('Category');

    $module_position = ($this->group === 'boxes_column_left') ? 'left' : 'right';
    $new_products_category_id = $CLICSHOPPING_Category->getID();

    if (CLICSHOPPING::getBaseNameIndex() && !$CLICSHOPPING_Category->getPath()) {
      if (defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY') && (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY != 0) {
        $context = new ProductsListingContext(
          constantsPrefix: 'MODULE_FRONT_PAGE_NEW_PRODUCTS',
          cssContainerClass: 'ModuleFrontPageNewProductsContainer5',
          cssHeadingClass: 'ModuleFrontPageProductsNewHeading',
          headingTextDef: 'module_front_page_new_products_heading_title',
          tickerClasses: [
            'special'  => 'ModulesFrontPageTickerBootstrapTickerSpecial',
            'favorite' => 'ModulesFrontPageTickerBootstrapTickerFavorite',
            'featured' => 'ModulesFrontPageTickerBootstrapTickerFeatured',
            'new'      => 'ModulesFrontPageTickerBootstrapTickerNew',
          ],
          tickerPercentageClass: 'ModulesFrontPageTickerBootstrapTickerPourcentage',
          group: $this->group,
          trackingCode: $this->code,
          modulePosition: $module_position,
          sortOrder: (int)$this->sort_order,
          trackingWeight: 0.30,
          listingCommentLabel: 'New Products',
          displayCartButton: defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_CART_BUTTON') && MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_CART_BUTTON === 'True',
          displayDetailsButton: defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_DETAILS_BUTTON') && MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_DETAILS_BUTTON === 'True',
          displaySortBar: defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_FILTER') && MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_FILTER === 'True',
          sortColumns: ['MANUFACTURER', 'PRICE', 'DATE'],
          displayViewSwitch: true,
          displayOptions: [
            'weight' => false,
            'quantityUnit' => false,
          ],
        );

        // Tracking historically disabled on the home page new-products surface.
        $renderer = (new ProductsListingRenderer($context))->withTracking(false);

        // Dynamic ORDER BY honoring the sort dropdown; falls back to the
        // historical random ordering when no valid sort is requested.
        $order_by = $renderer->orderByClause('rand(), p.products_date_added desc');

        if ($CLICSHOPPING_Customer->getCustomersGroupID() != 0) {
          if ((!isset($new_products_category_id)) || ($new_products_category_id == 0)) {
// Display products no inside categories
            $Qproduct = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                            p.products_quantity as in_stock
                                                    from :table_products p left join :table_products_groups g on p.products_id = g.products_id left join :table_manufacturers m on p.manufacturers_id = m.manufacturers_id,
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
                                                    order by ' . $order_by . '
                                                    limit :products_limit
                                                    ');
            $Qproduct->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());
            $Qproduct->bindInt(':products_limit', defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY') ? (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY : 0);
            $Qproduct->execute();
          } else {
// SQL query to display new products for B2B group when inside a category
            $Qproduct = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                            p.products_quantity as in_stock
                                                      from :table_products p left join :table_products_groups g on p.products_id = g.products_id left join :table_manufacturers m on p.manufacturers_id = m.manufacturers_id,
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
                                                      order by ' . $order_by . '
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
                                                    from :table_products p left join :table_manufacturers m on p.manufacturers_id = m.manufacturers_id,
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
                                                    order by ' . $order_by . '
                                                    limit :products_limit
                                                  ');
            $Qproduct->bindInt(':products_limit', defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY') ? (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY : 0);
            $Qproduct->execute();
          } else {
// SQL query to display new products when inside a category
            $Qproduct = $CLICSHOPPING_Db->prepare('select p.products_id,
                                                            p.products_quantity as in_stock
                                                    from :table_products p left join :table_manufacturers m on p.manufacturers_id = m.manufacturers_id,
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
                                                    order by ' . $order_by . '
                                                    limit :products_limit
                                                  ');

            $Qproduct->bindInt(':parent_id', (int)$new_products_category_id);
            $Qproduct->bindInt(':products_limit', defined('MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY') ? (int)MODULE_FRONT_PAGE_NEW_PRODUCTS_MAX_DISPLAY : 0);
            $Qproduct->execute();
          }
        }

        if ($Qproduct->rowCount() > 0) {
          $new_prods_content = '<!-- ' . $context->listingCommentLabel . ' start -->' . "\n";
          $new_prods_content .= '<div class="col-md-12 ' . $context->cssContainerClass . '">';

          $heading = '';
          if ($context->isFrontTitleEnabled() && $context->cssHeadingClass !== null && $context->headingTextDef !== null) {
            $heading = '<div class="' . $context->cssHeadingClass . '"><h2>' . CLICSHOPPING::getDef($context->headingTextDef) . '</h2></div>';
          }

          $new_prods_content .= $renderer->renderHeaderRow($heading);
          $new_prods_content .= $renderer->renderList($Qproduct);

          $new_prods_content .= '</div>' . "\n";
          $new_prods_content .= '<!-- ' . $context->listingCommentLabel . ' End -->' . "\n";

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
        'configuration_value' => '3',
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
        'configuration_title' => 'Do you want to display the quantity input field ?',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_QUANTITY_INPUT',
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
        'configuration_title' => 'Do you want to display the details button ?',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_DETAILS_BUTTON',
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
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_CART_BUTTON',
        'configuration_value' => 'True',
        'configuration_description' => 'Display or remove the cart button',
        'configuration_group_id' => '6',
        'sort_order' => '12',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the sort filter ?',
        'configuration_key' => 'MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_FILTER',
        'configuration_value' => 'False',
        'configuration_description' => 'Display a sort dropdown (price, date) above the listing',
        'configuration_group_id' => '6',
        'sort_order' => '13',
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
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_QUANTITY_INPUT',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_TICKER',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_POURCENTAGE_TICKER',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_STOCK',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_IMAGE_MEDIUM',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_DETAILS_BUTTON',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_CART_BUTTON',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_DISPLAY_FILTER',
      'MODULE_FRONT_PAGE_NEW_PRODUCTS_SORT_ORDER'
    );
  }
}
