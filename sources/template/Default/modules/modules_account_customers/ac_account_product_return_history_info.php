<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\Apps\Orders\ReturnOrders\Classes\Shop\HistoryInfo;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ac_account_product_return_history_info
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

    $this->title = CLICSHOPPING::getDef('module_account_product_return_history_info_title');
    $this->description = CLICSHOPPING::getDef('module_account_product_return_history_info_description');

    if (\defined('MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_STATUS')) {
      $this->sort_order = \defined('MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_SORT_ORDER') ? (int)MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_SORT_ORDER : 0;
      $this->enabled = (\defined('MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_STATUS') && MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (isset($_GET['Account']) && isset($_GET['ProductReturnHistoryInfo'])) {
      $return_product = '<!-- Start account_product_return_history --> ' . "\n";

      $rId = HTML::sanitize($_GET['rId']);

      $HistoryCheckInfo = HistoryInfo::getHistoryInfoDisplay($rId);

      $return_description = HistoryInfo::getHistoryInfoListing(true, $rId);

      $product_name = $return_description['product_name'];
      $product_model = $return_description['product_model'];
      $return_ref = $return_description['return_ref'];
      $return_qty = $return_description['quantity'];
      $oID = $return_description['order_id'];
      $date_added = $return_description['date_added'];

      $form = HTML::form('product_return_history_info', CLICSHOPPING::link(null, 'Account&ProductReturnHistoryInfo&Process&rId=' . $rId . '&oId=' . $oID), 'post', 'id="ProductReturnHistoryInfo"', ['tokenize' => true, 'action' => 'process']);
      $endform = '</form>';

      $content_width = \defined('MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_CONTENT_WIDTH') ? (int)MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_CONTENT_WIDTH : 12;

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/account_product_return_history_info'));

      $return_product .= ob_get_clean();

      $return_product .= '<!-- end account_product_return_history -->' . "\n";

      $CLICSHOPPING_Template->addBlock($return_product, $this->group);
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_STATUS',
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
        'configuration_key' => 'MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_SORT_ORDER',
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
      'MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_STATUS',
      'MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_CONTENT_WIDTH',
      'MODULE_ACCOUNT_PRODUCT_RETURN_HISTORY_INFO_SORT_ORDER'
    );
  }
}