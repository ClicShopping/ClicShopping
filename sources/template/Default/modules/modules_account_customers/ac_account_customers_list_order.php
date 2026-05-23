<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\AddressBook;

class ac_account_customers_list_order
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

    $this->title = CLICSHOPPING::getDef('module_account_customers_list_order_title');
    $this->description = CLICSHOPPING::getDef('module_account_customers_list_order_description');


    if (\defined('MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_TITLE_STATUS')) {
      $this->sort_order = \defined('MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_TITLE_SORT_ORDER') ? (int)MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_TITLE_SORT_ORDER : 0;
      $this->enabled = (\defined('MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_TITLE_STATUS') && MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_TITLE_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');

    if ((isset($_GET['Account']) && isset($_GET['Main'])) || (isset($_GET['Account']) && isset($_GET['Login']))) {

      $content_width = \defined('MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_CONTENT_WIDTH') ? (int)MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_CONTENT_WIDTH : 12;

      $Qorders = $CLICSHOPPING_Db->prepare('select o.orders_id,
                                                     o.date_purchased,
                                                     o.delivery_name,
                                                     o.delivery_country,
                                                     o.billing_name,
                                                     o.billing_country,
                                                     ot.text as order_total,
                                                     s.orders_status_name
                                             from :table_orders o,
                                                  :table_orders_total ot,
                                                  :table_orders_status s
                                             where o.customers_id = :customers_id
                                             and o.orders_id = ot.orders_id
                                             and (ot.class = :class or ot.class = :class1)
                                             and o.orders_status = s.orders_status_id
                                             and s.language_id = :language_id
                                             and s.public_flag = :public_flag
                                             order by orders_id desc limit 5
                                            ');

      $Qorders->bindInt(':customers_id', (int)$CLICSHOPPING_Customer->getID());
      $Qorders->bindInt(':language_id', (int)$CLICSHOPPING_Language->getId());
      $Qorders->bindValue(':public_flag', '1');
      $Qorders->bindValue(':class', 'ot_total');
      $Qorders->bindValue(':class1', 'TO');

      $Qorders->execute();

      $account_customers_title_content = '<!-- Start account_customers_title -->' . "\n";
      $account_customers_title_content .= '<div class="col-md-12">';

      $account_customers_title_content .= '<div class="card">
                                                <div class="card-header">
                                                  <div class="row">
                                                    <div class="col-md-11 ModuleAccountCustomersListOrderTitle"><h3>' . CLICSHOPPING::getDef('module_account_customers_list_order_order') . '</h3></div>
                                                      <div class="col-md-1 text-end">
                                                        <i class="bi bi-clock-history moduleAccountCustomersNotificationsIcon"></i>
                                                      </div>
                                                    </div>
                                                  </div>
                                                  <div class="card-block">
                                                    <div class="mt-1"></div>
                                                    <div class="card-text">
                                              ';

      if (AddressBook::countCustomerOrders() > 0) {
        $account_customers_title_content .= '<div class="ModuleAccountCustomersListOrderCustomer">';
        $account_customers_title_content .= '<div class="col-md-2">';
        $account_customers_title_content .= '<strong>' . CLICSHOPPING::getDef('overview_title') . '</strong>';
        $account_customers_title_content .= '</div>';
        $account_customers_title_content .= '<div class="mt-1"></div>';
        $account_customers_title_content .= '<div class="col-md-8">';
        $account_customers_title_content .= HTML::link(CLICSHOPPING::link(null, 'Account&History'), '<u>' . CLICSHOPPING::getDef('overview_show_all_orders') . '</u>');
        $account_customers_title_content .= '</div>';
        $account_customers_title_content .= '<div class="mt-1"></div>';
        $account_customers_title_content .= '<div class="col-md-10">';
        $account_customers_title_content .= '<p><strong>' . CLICSHOPPING::getDef('overview_previous_orders') . '</strong></p>';
        $account_customers_title_content .= '</div>';
        $account_customers_title_content .= '<div class="mt-1"></div>';

        $account_customers_title_content .= '<div class="d-flex flex-wrap">';

        while ($Qorders->fetch()) {
          if (!empty($Qorders->value('delivery_name'))) {
            $order_name = Hash::displayDecryptedDataText($Qorders->value('delivery_name'));
            $order_country = $Qorders->value('delivery_country');
          } else {
            $order_name = Hash::displayDecryptedDataText($Qorders->value('billing_name'));
            $order_country = $Qorders->value('billing_country');
          }

          $account_customers_title_content .= '<div class="col-md-12">';
          $account_customers_title_content .= '<span class="col-md-3">' . DateTime::toShort($Qorders->value('date_purchased')) . '</span>';
          $account_customers_title_content .= '<span class="col-md-1 m-2"> #' . $Qorders->valueInt('orders_id') . '</span>';
          $account_customers_title_content .= '<span class="col-md-3 m-2"> ' . HTML::outputProtected($order_name) . ', ' . HTML::outputProtected($order_country) . '</span>';
          $account_customers_title_content .= '<span class="col-md-2 m-2"> ' . $Qorders->value('orders_status_name') . '</span>';
          $account_customers_title_content .= '<span class="col-md-1 float-end">';
          $account_customers_title_content .= '<p class="float-end">' . HTML::button(CLICSHOPPING::getDef('button_view'), null, CLICSHOPPING::link(null, 'Account&HistoryInfo&order_id=' . (int)$Qorders->valueInt('orders_id')), 'info', null, 'sm') . '</p>';
          $account_customers_title_content .= '</span>';
          $account_customers_title_content .= '<span class="col-md-2 float-end">' . $Qorders->value('order_total') . '</span>';
          $account_customers_title_content .= '</div>';
        } // end while

        $account_customers_title_content .= '<div class="col-md-12">';
        $account_customers_title_content .= '<div>' . CLICSHOPPING::getDef('module_account_customers_list_order_order_text') . '</div>';
        $account_customers_title_content .= '<div class="mt-1"></div>';
        $account_customers_title_content .= '</div>';
        $account_customers_title_content .= '</div>';
        $account_customers_title_content .= '</div>';
      }

      $account_customers_title_content .= '<div class="hr"></div>';
      $account_customers_title_content .= '</div>' . "\n";
      $account_customers_title_content .= '</div>' . "\n";
      $account_customers_title_content .= '</div>' . "\n";
      $account_customers_title_content .= '</div>' . "\n";

      $account_customers_title_content .= '<!-- end account_customers_title -->' . "\n";

      $CLICSHOPPING_Template->addBlock($account_customers_title_content, $this->group);
    } // php_self
  } // function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_TITLE_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_TITLE_STATUS',
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
        'configuration_key' => 'MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_TITLE_SORT_ORDER',
        'configuration_value' => '10',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '10',
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
      'MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_TITLE_STATUS',
      'MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_CONTENT_WIDTH',
      'MODULE_ACCOUNT_CUSTOMERS_LIST_ORDER_TITLE_SORT_ORDER'
    );
  }
}
