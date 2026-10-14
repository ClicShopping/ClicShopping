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

class cc_checkout_confirmation_delivery_address
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

    $this->title = CLICSHOPPING::getDef('module_checkout_confirmation_delivery_address_title');
    $this->description = CLICSHOPPING::getDef('module_checkout_confirmation_delivery_address_description');

    if (\defined('MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_STATUS')) {
      $this->sort_order = (\defined('MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_SORT_ORDER') ? (int)MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_SORT_ORDER : 0);
      $this->enabled = (\defined('MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_STATUS') && MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Address = Registry::get('Address');

    if (isset($_GET['Checkout'], $_GET['Confirmation']) && $CLICSHOPPING_Customer->isLoggedOn()) {

      $content_width = (\defined('MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_CONTENT_WIDTH') ? (int)MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_CONTENT_WIDTH : 12);

      $delivery_address_link = HTML::link(CLICSHOPPING::link(null, 'Checkout&ShippingAddress'), '<span class="orderEdit"> (' . CLICSHOPPING::getDef('module_checkout_confirmation_delivery_address_text_edit') . ')</span>');
      $delivery_address = $CLICSHOPPING_Address->addressFormat($CLICSHOPPING_Order->delivery['format_id'], $CLICSHOPPING_Order->delivery, 1, ' ', '<br />');

      $shipping_link = HTML::link(CLICSHOPPING::link(null, 'Checkout&Shipping'), '<span class="orderEdit">(' . CLICSHOPPING::getDef('module_checkout_confirmation_delivery_address_text_edit') . ')</span>');
      $shipping_method = $CLICSHOPPING_Order->info['shipping_method'];

      $confirmation = '  <!-- processing_checkout_confirmation_delivery -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/checkout_confirmation_delivery_address'));

      $confirmation .= ob_get_clean();

      $confirmation .= '<!--  processing_checkout_confirmation_delivery -->' . "\n";

      $CLICSHOPPING_Template->addBlock($confirmation, $this->group);
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the module width',
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_SORT_ORDER',
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
    return array(
      'MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_STATUS',
      'MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_CONTENT_WIDTH',
      'MODULE_CHECKOUT_CONFIRMATION_DELIVERY_ADDRESS_SORT_ORDER'
    );
  }
}