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

class bm_shopping_cart
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;
  public $pages;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_boxes_shopping_cart_title');
    $this->description = CLICSHOPPING::getDef('module_boxes_shopping_cart_description');

    if (\defined('MODULE_BOXES_SHOPPING_CART_STATUS')) {
      $this->sort_order = \defined('MODULE_BOXES_SHOPPING_CART_SORT_ORDER') ? (int)MODULE_BOXES_SHOPPING_CART_SORT_ORDER : 0;
      $this->enabled = (\defined('MODULE_BOXES_SHOPPING_CART_STATUS') && MODULE_BOXES_SHOPPING_CART_STATUS == 'True');
      $this->pages = \defined('MODULE_BOXES_SHOPPING_CART_DISPLAY_PAGES') ? MODULE_BOXES_SHOPPING_CART_DISPLAY_PAGES : '';
      $this->group = ((\defined('MODULE_BOXES_SHOPPING_CART_CONTENT_PLACEMENT') && MODULE_BOXES_SHOPPING_CART_CONTENT_PLACEMENT == 'Left Column') ? 'boxes_column_left' : 'boxes_column_right');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Service = Registry::get('Service');
    $CLICSHOPPING_Banner = Registry::get('Banner');
    $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');

    $cart_contents_string = '';

    $products = $CLICSHOPPING_ShoppingCart->get_products();

    for ($i = 0, $n = \count($products); $i < $n; $i++) {

      $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($products[$i]['id']);

      $cart_contents_string .= '<div>';

      if ((isset($_SESSION['new_products_id_inCart'])) && ($_SESSION['new_products_id_inCart'] == $products[$i]['id'])) {
        $cart_contents_string .= '<span class="boxeNewItemShoppingCart">';
      }

      $cart_contents_string .= $products[$i]['quantity'] . '&nbsp;x&nbsp;';

      if ((isset($_SESSION['new_products_id_inCart'])) && ($_SESSION['new_products_id_inCart'] == $products[$i]['id'])) {
        $cart_contents_string .= '</span>';
      }

      $cart_contents_string .= '<span><a href="' . $products_name_url . '">';

      if ((isset($_SESSION['new_products_id_inCart'])) && ($_SESSION['new_products_id_inCart'] == $products[$i]['id'])) {
        $cart_contents_string .= '<span class="boxeNewItemShoppingCart">';
      }

      $cart_contents_string .= $products[$i]['name'];

      if ((isset($_SESSION['new_products_id_inCart'])) && ($_SESSION['new_products_id_inCart'] == $products[$i]['id'])) {
        $cart_contents_string .= '</span>';
      }

      $cart_contents_string .= '</a></span>';

      if ((isset($_SESSION['new_products_id_inCart'])) && ($_SESSION['new_products_id_inCart'] == $products[$i]['id'])) {
        unset($_SESSION['new_products_id_inCart']);
      }
      $cart_contents_string .= '</div>';
    }

    $cart_contents_string .= '<div class="hr"></div>' .
      '<div class="boxeShowTotalShoppingCart">' . $CLICSHOPPING_Currencies->format($CLICSHOPPING_ShoppingCart->show_total()) . '</div>' .
      '<div class="boxeShowTextShoppingCart">' . HTML::link(CLICSHOPPING::link(null, 'Cart'), CLICSHOPPING::getDef('header_title_cart_contents')) . '</div>' .
      '';
    /*
          } else {
            $cart_contents_string .= '<div class="boxContentsShoppingCart">' . CLICSHOPPING::getDef('module_boxes_shopping_cart_box_cart_empty') . '</div>';
          }
    */
    if ($CLICSHOPPING_ShoppingCart->getCountContents() > 0 || \defined('MODULE_BOXES_SHOPPING_CART_DISPLAY') && MODULE_BOXES_SHOPPING_CART_DISPLAY == 'True') {
      $shopping_cart_banner = '';

      if ($CLICSHOPPING_Service->isStarted('Banner')) {
        if ($banner = $CLICSHOPPING_Banner->bannerExists('dynamic', \defined('MODULE_BOXES_SHOPPING_CART_BANNER_GROUP') ? MODULE_BOXES_SHOPPING_CART_BANNER_GROUP : '')) {
          $shopping_cart_banner = $CLICSHOPPING_Banner->displayBanner('static', $banner) . '<br /><br />';
        } else {
          $shopping_cart_banner = '';
        }
      }

      $data = '<!-- boxe shopping cart start-->' . "\n";

      ob_start();
      require($CLICSHOPPING_Template->getTemplateModules('/modules_boxes/content/shopping_cart'));

      $data .= ob_get_clean();

      $data .= '<!-- boxe shopping cart end -->' . "\n";
    }

    $CLICSHOPPING_Template->addBlock($data, $this->group);
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_BOXES_SHOPPING_CART_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_BOXES_SHOPPING_CART_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );


    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display the basket ?',
        'configuration_key' => 'MODULE_BOXES_SHOPPING_CART_DISPLAY',
        'configuration_value' => 'True',
        'configuration_description' => 'If False, the basket will be displayed only if the customer choose a product',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );


    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please choose where the boxe must be displayed',
        'configuration_key' => 'MODULE_BOXES_SHOPPING_CART_CONTENT_PLACEMENT',
        'configuration_value' => 'Right Column',
        'configuration_description' => 'Choose where the boxe must be displayed',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'Left Column\', \'Right Column\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the banner group for the image',
        'configuration_key' => 'MODULE_BOXES_SHOPPING_CART_BANNER_GROUP',
        'configuration_value' => SITE_THEMA . '_boxe_shopping_cart',
        'configuration_description' => 'Indicate the banner group<br /><br /><strong>Note :</strong><br /><i>The group must be created or selected whtn you create a banner in Marketing / banner</i>',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_BOXES_SHOPPING_CART_SORT_ORDER',
        'configuration_value' => '120',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Indicate the page where the module is displayed',
        'configuration_key' => 'MODULE_BOXES_SHOPPING_CART_DISPLAY_PAGES',
        'configuration_value' => 'all',
        'configuration_description' => 'Select the page where the modules must be displayed',
        'configuration_group_id' => '6',
        'sort_order' => '5',
        'set_function' => 'clic_cfg_set_select_pages_list',
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
    return array('MODULE_BOXES_SHOPPING_CART_STATUS',
      'MODULE_BOXES_SHOPPING_CART_CONTENT_PLACEMENT',
      'MODULE_BOXES_SHOPPING_CART_DISPLAY',
      'MODULE_BOXES_SHOPPING_CART_BANNER_GROUP',
      'MODULE_BOXES_SHOPPING_CART_BANNER_GROUP',
      'MODULE_BOXES_SHOPPING_CART_SORT_ORDER',
      'MODULE_BOXES_SHOPPING_CART_DISPLAY_PAGES');
  }
}
