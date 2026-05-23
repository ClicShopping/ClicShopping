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
use ClicShopping\Sites\Shop\Payment;

class cc_checkout_confirmation_process_order
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

    $this->title = CLICSHOPPING::getDef('module_checkout_confirmation_process_order_title');
    $this->description = CLICSHOPPING::getDef('module_checkout_confirmation_process_order_description');

    if (\defined('MODULE_CHECKOUT_CONFIRMATION_PROCESS_ORDER_STATUS')) {
      $this->sort_order = (int)MODULE_CHECKOUT_CONFIRMATION_PROCESS_ORDER_SORT_ORDER;
      $this->enabled = (MODULE_CHECKOUT_CONFIRMATION_PROCESS_ORDER_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Currencies = Registry::get('Currencies');
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (isset($_GET['Checkout'], $_GET['Confirmation']) && $CLICSHOPPING_Customer->isLoggedOn()) {

      if (!Registry::exists('Payment')) {
        Registry::set('Payment', new Payment());
      }

      $CLICSHOPPING_Payment = Registry::get('Payment');

      $content_width = (int)MODULE_CHECKOUT_CONFIRMATION_PROCESS_ORDER_CONTENT_WIDTH;

      if (\is_array($CLICSHOPPING_Payment->modules)) {
        if ($CLICSHOPPING_Payment->process_button() !== false) {
          $process_button = $CLICSHOPPING_Payment->process_button();
        } else {
         $process_button = HTML::button(CLICSHOPPING::getDef('module_checkout_confirmation_process_order_button_pay', ['total' => $CLICSHOPPING_Currencies->format($CLICSHOPPING_Order->info['total'], true, $CLICSHOPPING_Order->info['currency'], $CLICSHOPPING_Order->info['currency_value'])]), null, null, 'success', ['params' => 'id="payNow"']);
        }
      }

      $footer = '<!-- button listenner Start -->' . "\n";
      $footer .= '<script defer src="' . CLICSHOPPING::link($CLICSHOPPING_Template->getTemplateDefaultJavaScript('clicshopping/checkout_confirmation.js')) . '"></script>' . "\n";
      $footer .= '<!-- stop button listenner  -->' . "\n";

      $CLICSHOPPING_Template->addBlock($footer, 'footer_scripts');

      $confirmation = '  <!-- cc_checkout_confirmation_order_total start -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/checkout_confirmation_process_order'));

      $confirmation .= ob_get_clean();

      $confirmation .= '<!--  cc_checkout_confirmation_order_total end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($confirmation, $this->group);

    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_CHECKOUT_CONFIRMATION_PROCESS_ORDER_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_PROCESS_ORDER_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the module',
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_PROCESS_ORDER_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_CHECKOUT_CONFIRMATION_PROCESS_ORDER_SORT_ORDER',
        'configuration_value' => '320',
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
      'MODULE_CHECKOUT_CONFIRMATION_PROCESS_ORDER_STATUS',
      'MODULE_CHECKOUT_CONFIRMATION_PROCESS_ORDER_CONTENT_WIDTH',
      'MODULE_CHECKOUT_CONFIRMATION_PROCESS_ORDER_SORT_ORDER'
    );
  }
}