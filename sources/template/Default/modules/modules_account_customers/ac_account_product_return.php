<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\Apps\Orders\ReturnOrders\Classes\Shop\ReturnProduct;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ac_account_product_return
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

    $this->title = CLICSHOPPING::getDef('module_account_product_return_title');
    $this->description = CLICSHOPPING::getDef('module_account_product_return_description');

    if (\defined('MODULE_ACCOUNT_PRODUCT_RETURN_STATUS')) {
      $this->sort_order = \defined('MODULE_ACCOUNT_PRODUCT_RETURN_SORT_ORDER') ? (int)MODULE_ACCOUNT_PRODUCT_RETURN_SORT_ORDER : 0;
      $this->enabled = (\defined('MODULE_ACCOUNT_PRODUCT_RETURN_STATUS') && MODULE_ACCOUNT_PRODUCT_RETURN_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    if (isset($_GET['Account']) && isset($_GET['ProductReturn']) && isset($_GET['product_id'])) {
      $order_id = HTML::sanitize($_GET['order_id']);
      $products_id = (int)HTML::sanitize($_GET['product_id']);

      $info_customer = ReturnProduct::getInfoCustomer($order_id);

      $content_width = \defined('MODULE_ACCOUNT_PRODUCT_RETURN_CONTENT_WIDTH') ? (int)MODULE_ACCOUNT_PRODUCT_RETURN_CONTENT_WIDTH : 12;
// main customer
      $customers_name = Hash::displayDecryptedDataText($info_customer['customers_name']);
      $customers_street_address = Hash::displayDecryptedDataText($info_customer['customers_street_address']);
      $customers_suburb = Hash::displayDecryptedDataText($info_customer['customers_suburb']);
      $customers_city = Hash::displayDecryptedDataText($info_customer['customers_city']);
      $customers_postcode = Hash::displayDecryptedDataText($info_customer['customers_postcode']);
      $customers_state = $info_customer['customers_state'];
      $customers_telephone = Hash::displayDecryptedDataText($info_customer['customers_telephone']);
      $customers_country = $info_customer['customers_country'];
      $date_purchased = $info_customer['date_purchased'];
      $customers_email_address = $info_customer['customers_email_address'];

// delivery address
      $delivery_name = Hash::displayDecryptedDataText($info_customer['delivery_name']);
      $delivery_street_address = Hash::displayDecryptedDataText($info_customer['delivery_street_address']);
      $delivery_suburb = Hash::displayDecryptedDataText($info_customer['delivery_suburb']);
      $delivery_city = Hash::displayDecryptedDataText($info_customer['delivery_city']);
      $delivery_postcode = Hash::displayDecryptedDataText($info_customer['delivery_postcode']);
      $delivery_state = $info_customer['delivery_state'];
      $delivery_country = $info_customer['delivery_country'];

// product
      $product_name = $CLICSHOPPING_ProductsCommon->getProductsName($products_id);
      $product_model = $CLICSHOPPING_ProductsCommon->getProductsModel($products_id);
      $product_quantity = 1;
      $purchased_date = DateTime::toShort($info_customer['date_purchased']);

      $reason_return = ReturnProduct::getDropDownReason();

      if (defined('CLICSHOPPING_APP_RETURN_ORDERS_RO_WITHDRAWAL')) {
        $withdrawal = (int)CLICSHOPPING_APP_RETURN_ORDERS_RO_WITHDRAWAL;
      } else {
        $withdrawal = '';
      }
      $reason_opened = ReturnProduct::getDropDownReasonOpened();

      $return_product = '<!-- Start account_product_return --> ' . "\n";

      $form = HTML::form('customer_return', CLICSHOPPING::link(null, 'Account&ProductReturn&Process&order_id=' . $order_id . '&product_id=' . $products_id), 'post', 'id="product_return"', ['tokenize' => true, 'action' => 'process']);
      $endform = '</form>';

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/account_product_return'));

      $return_product .= ob_get_clean();

      $return_product .= '<!-- end account_product_return -->' . "\n";

      $CLICSHOPPING_Template->addBlock($return_product, $this->group);

    } // php_self
  } // function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_ACCOUNT_PRODUCT_RETURN_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_ACCOUNT_PRODUCT_RETURN_STATUS',
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
        'configuration_key' => 'MODULE_ACCOUNT_PRODUCT_RETURN_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_ACCOUNT_PRODUCT_RETURN_SORT_ORDER',
        'configuration_value' => '120',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '105',
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
      'MODULE_ACCOUNT_PRODUCT_RETURN_STATUS',
      'MODULE_ACCOUNT_PRODUCT_RETURN_CONTENT_WIDTH',
      'MODULE_ACCOUNT_PRODUCT_RETURN_SORT_ORDER'
    );
  }
}
