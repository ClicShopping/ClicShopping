<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class chs_thank_you
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

    $this->title = CLICSHOPPING::getDef('module_checkout_success_thank_you_title');
    $this->description = CLICSHOPPING::getDef('module_checkout_success_thank_you_description');

    if (\defined('MODULE_CHECKOUT_SUCCESS_THANK_YOU_STATUS')) {
      $this->sort_order = \defined('MODULE_CHECKOUT_SUCCESS_THANK_YOU_SORT_ORDER') ? (int)MODULE_CHECKOUT_SUCCESS_THANK_YOU_SORT_ORDER : 0;
      $this->enabled = (\defined('MODULE_CHECKOUT_SUCCESS_THANK_YOU_STATUS') && MODULE_CHECKOUT_SUCCESS_THANK_YOU_STATUS == 'True');
    }
  }

  public function execute()
  {

    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (isset($_GET['Checkout'], $_GET['Success'])) {
      if ($CLICSHOPPING_Customer->getCustomerGuestAccount($CLICSHOPPING_Customer->getID()) == 1) {
        $guest_account = 1;
        $text_info = CLICSHOPPING::getDef('module_checkout_success_create_account_success', ['store_name' => \defined('STORE_NAME') ? STORE_NAME : '',
            'store_name_address' => STORE_NAME_ADDRESS,
          ]
        );
        $contact = '';
      } else {
        $guest_account = 0;
        $text_info = sprintf(CLICSHOPPING::getDef('module_checkout_success_text_see_orders', ['store_name' => \defined('STORE_NAME') ? STORE_NAME : '', 'store_name_address' => \defined('STORE_NAME_ADDRESS') ? STORE_NAME_ADDRESS : '',
            'account_history' => '<a href="' . CLICSHOPPING::link(null, 'Account&History') . '">' . CLICSHOPPING::getDef('module_checkout_success_text_order_history') . '</a>',
            'my_account' => '<a href="' . CLICSHOPPING::link(null, 'Account&Main') . '">' . CLICSHOPPING::getDef('module_checkout_success_text_account') . '</a>',
          ]
        ), CLICSHOPPING::link(null, 'Account&HistoryInfo')
        );

        $contact = sprintf(CLICSHOPPING::getDef('module_checkout_success_text_contact_store_owner', ['store_name' => \defined('STORE_NAME') ? STORE_NAME : '',
            'account_history' => '<a href="' . CLICSHOPPING::link(null, 'Account&History') . '">' . CLICSHOPPING::getDef('module_checkout_success_text_order_history') . '</a>',
            'contact' => '<a href="index.php?Info&Contact">' . CLICSHOPPING::getDef('module_checkout_success_text_contact') . '</a>'
          ]
        ), CLICSHOPPING::link(null, 'info&Contact')
        );
      }

      $content_width = (\defined('MODULE_CHECKOUT_SUCCESS_THANK_YOU_CONTENT_WIDTH') ? (int)MODULE_CHECKOUT_SUCCESS_THANK_YOU_CONTENT_WIDTH : 12)  ;

      $thank_you = '<!-- cs_thank_you start -->' . "\n";

      ob_start();

      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/thank_you'));
      $thank_you .= ob_get_clean();

      $thank_you .= '<!-- cs_thank_you end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($thank_you, $this->group);
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_CHECKOUT_SUCCESS_THANK_YOU_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Enable Product Downloads Module',
        'configuration_key' => 'MODULE_CHECKOUT_SUCCESS_THANK_YOU_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Should ordered product download links be shown on the checkout success page ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Select the width to display?',
        'configuration_key' => 'MODULE_CHECKOUT_SUCCESS_THANK_YOU_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort Order',
        'configuration_key' => 'MODULE_CHECKOUT_SUCCESS_THANK_YOU_SORT_ORDER',
        'configuration_value' => '1',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '3',
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
    return array('MODULE_CHECKOUT_SUCCESS_THANK_YOU_STATUS',
      'MODULE_CHECKOUT_SUCCESS_THANK_YOU_CONTENT_WIDTH',
      'MODULE_CHECKOUT_SUCCESS_THANK_YOU_SORT_ORDER'
    );
  }
}

