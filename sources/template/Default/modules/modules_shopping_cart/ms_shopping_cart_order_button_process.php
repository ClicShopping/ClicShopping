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

class ms_shopping_cart_order_button_process
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

    $this->title = CLICSHOPPING::getDef('module_shopping_cart_order_button_process_title');
    $this->description = CLICSHOPPING::getDef('module_shopping_cart_show_total_description');

    if (\defined('MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_STATUS')) {
      $this->sort_order = \defined('MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_SORT_ORDER') ? (int)MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_SORT_ORDER : 0;
      $this->enabled = (MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');

    if (isset($_GET['Cart']) && $CLICSHOPPING_ShoppingCart->getCountContents() > 0) {
      if (\is_array($CLICSHOPPING_NavigationHistory)) {
        $back = \count($CLICSHOPPING_NavigationHistory->path) - 2;
      } else {
        $back = null;
      }

      $text_position = MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_POSITION;

      if (isset($CLICSHOPPING_NavigationHistory->path[$back])) {
        if (!\is_null(($CLICSHOPPING_NavigationHistory->path[$back]))) {
          $button_navigation_history = HTML::button(CLICSHOPPING::getDef('button_continue'), null, CLICSHOPPING::link($CLICSHOPPING_NavigationHistory->path[$back]['application'], CLICSHOPPING::arrayToString($CLICSHOPPING_NavigationHistory->path[$back]['get'], array('action')), $CLICSHOPPING_NavigationHistory->path[$back]['mode']), 'info');
        } else {
          $button_navigation_history = '';
        }
      } else {
        $button_navigation_history = '';
      }

      $save_shopping_cart = '';

      if (!$CLICSHOPPING_Customer->isLoggedOn()) {
        if ((MODE_MANAGEMENT_B2C_B2B == 'B2C' || MODE_MANAGEMENT_B2C_B2B == 'B2C_B2B') && (MODE_B2B_B2C == 'True')) {
          $save_shopping_cart = HTML::button(CLICSHOPPING::getDef('button_save_shopping_cart'), null, CLICSHOPPING::link(null, 'Account&Create'), 'info', null, 'lg');
        } elseif ((MODE_MANAGEMENT_B2C_B2B == 'B2B' ||  MODE_MANAGEMENT_B2C_B2B == 'B2C_B2B') && (MODE_B2B_B2C == 'True')) {
          $save_shopping_cart = HTML::button(CLICSHOPPING::getDef('button_save_shopping_cart'), null, CLICSHOPPING::link(null, 'Account&CreatePro'), 'info', null, 'lg');
        } elseif (MODE_MANAGEMENT_B2C_B2B == 'B2C' && (MODE_B2B_B2C == 'False')) {
          $save_shopping_cart = HTML::button(CLICSHOPPING::getDef('button_save_shopping_cart'), null, CLICSHOPPING::link(null, 'Account&Create'), 'info', null, 'lg');
        } elseif (MODE_MANAGEMENT_B2C_B2B == 'B2C' && (MODE_B2B_B2C == 'False')) {
          $save_shopping_cart = HTML::button(CLICSHOPPING::getDef('button_save_shopping_cart'), null, CLICSHOPPING::link(null, 'Account&CreatePro'), 'info', null, 'lg');
        }
      }

      $button_continue = HTML::button(CLICSHOPPING::getDef('button_order'), null, CLICSHOPPING::link(null, 'Checkout&Shipping'), 'success', null, 'lg');

      $content_width = \defined('MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_CONTENT_WIDTH') ? (int)MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_CONTENT_WIDTH : 0;

      $shopping_cart_information_customers = '  <!-- start ms_shopping_cart_order_button_process -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/shopping_cart_order_button_process'));
      $shopping_cart_information_customers .= ob_get_clean();

      $shopping_cart_information_customers .= '<!-- end ms_shopping_cart_order_button_process -->' . "\n";

      $CLICSHOPPING_Template->addBlock($shopping_cart_information_customers, $this->group);
    }
  } // function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the module ?',
        'configuration_key' => 'MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Where do you want to display the module?',
        'configuration_key' => 'MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_POSITION',
        'configuration_value' => 'float-none',
        'configuration_description' => 'Displays the module to the left or to the right ',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\', \'float-none\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_SORT_ORDER',
        'configuration_value' => '350',
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
      'MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_STATUS',
      'MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_CONTENT_WIDTH',
      'MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_POSITION',
      'MODULE_SHOPPING_CART_ORDER_BUTTON_PROCESS_SORT_ORDER'
    );
  }
}
