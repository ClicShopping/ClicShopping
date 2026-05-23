<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;


class ms_shopping_cart_checkout_step
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

    $this->title = CLICSHOPPING::getDef('module_shopping_cart_checkout_step_title');
    $this->description = CLICSHOPPING::getDef('module_shopping_cart_checkout_step_description');

    if (\defined('MODULE_SHOPPING_CART_CHECKOUT_STEP_STATUS')) {
      $this->sort_order = \defined('MODULE_SHOPPING_CART_CHECKOUT_STEP_SORT_ORDER') ? (int)MODULE_SHOPPING_CART_CHECKOUT_STEP_SORT_ORDER : 0;
      $this->enabled = (MODULE_SHOPPING_CART_CHECKOUT_STEP_STATUS == 'True');
    }
  }

  public function execute()
  {

    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_ShoppingCart = Registry::get('ShoppingCart');

    if (isset($_GET['Cart']) && $CLICSHOPPING_ShoppingCart->getCountContents() > 0) {

      $content_width = \defined('MODULE_SHOPPING_CART_CHECKOUT_STEP_CONTENT_WIDTH') ? (int)MODULE_SHOPPING_CART_CHECKOUT_STEP_CONTENT_WIDTH : 0;

      $shopping_cart_information_customers = '  <!-- start ms_shopping_cart_step -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/shopping_cart_checkout_step'));

      $shopping_cart_information_customers .= ob_get_clean();

      $shopping_cart_information_customers .= '<!-- end ms_shopping_cart_step -->' . "\n";

      $CLICSHOPPING_Template->addBlock($shopping_cart_information_customers, $this->group);
    }
  } // function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_SHOPPING_CART_CHECKOUT_STEP_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_SHOPPING_CART_CHECKOUT_STEP_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the display?',
        'configuration_key' => 'MODULE_SHOPPING_CART_CHECKOUT_STEP_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Please enter a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_SHOPPING_CART_CHECKOUT_STEP_SORT_ORDER',
        'configuration_value' => '200',
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
      'MODULE_SHOPPING_CART_CHECKOUT_STEP_STATUS',
      'MODULE_SHOPPING_CART_CHECKOUT_STEP_CONTENT_WIDTH',
      'MODULE_SHOPPING_CART_CHECKOUT_STEP_SORT_ORDER'
    );
  }
}
