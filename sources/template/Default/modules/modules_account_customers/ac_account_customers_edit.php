<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Pages\Account\Classes\Edit;

class ac_account_customers_edit
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

    $this->title = CLICSHOPPING::getDef('module_account_customers_edit_title');
    $this->description = CLICSHOPPING::getDef('module_account_customers_edit_description');

    if (\defined('MODULE_ACCOUNT_CUSTOMERS_EDIT_TITLE_STATUS')) {
      $this->sort_order = \defined('MODULE_ACCOUNT_CUSTOMERS_EDIT_TITLE_SORT_ORDER') ? (int)MODULE_ACCOUNT_CUSTOMERS_EDIT_TITLE_SORT_ORDER : 0;
      $this->enabled = (\defined('MODULE_ACCOUNT_CUSTOMERS_EDIT_TITLE_STATUS') && MODULE_ACCOUNT_CUSTOMERS_EDIT_TITLE_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (isset($_GET['Account']) && isset($_GET['Edit'])) {
      $account = Edit::getAccountEdit();

      $content_width = \defined('MODULE_ACCOUNT_CUSTOMERS_EDIT_CONTENT_WIDTH') ? (int)MODULE_ACCOUNT_CUSTOMERS_EDIT_CONTENT_WIDTH : 12;

      if (!isset($_GET['AddressBookProcess'])) {
        $customers_gender = $account['customers_gender'];
        $customers_firstname = Hash::displayDecryptedDataText($account['customers_firstname']);
        $customers_lastname = Hash::displayDecryptedDataText($account['customers_lastname']);
        $customers_dob = $account['customers_dob'];
        $customers_email_address = $account['customers_email_address'];

        $customers_telephone = Hash::displayDecryptedDataText($account['customers_telephone']);
        $customers_cellular_phone = Hash::displayDecryptedDataText($account['customers_cellular_phone']);
        $customers_company = Hash::displayDecryptedDataText($account['customers_company']);
        $customers_siret = $account['customers_siret'];
        $customers_ape = $account['customers_ape'];
        $customers_tva_intracom_code_iso = $account['customers_tva_intracom_code_iso'];
        $customers_tva_intracom = $account['customers_tva_intracom'];
      } else {
        $customers_gender = '';
        $customers_firstname = '';
        $customers_lastname = '';
        $customers_email_address = '';
        $customers_telephone = '';
      }

      $account_edit = '<!-- Start account_customers_edit --> ' . "\n";

      $form = HTML::form('account_edit', CLICSHOPPING::link(null, 'Account&Edit&Process'), 'post', null, ['tokenize' => true, 'action' => 'process']);
      $endform = '</form>';

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/account_customers_edit'));
      $account_edit .= ob_get_clean();

      $account_edit .= '<!-- end account_customers_edit -->' . "\n";

      $CLICSHOPPING_Template->addBlock($account_edit, $this->group);

    } // php_self
  } // function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_ACCOUNT_CUSTOMERS_EDIT_TITLE_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_ACCOUNT_CUSTOMERS_EDIT_TITLE_STATUS',
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
        'configuration_key' => 'MODULE_ACCOUNT_CUSTOMERS_EDIT_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_ACCOUNT_CUSTOMERS_EDIT_TITLE_SORT_ORDER',
        'configuration_value' => '120',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '100',
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
      'MODULE_ACCOUNT_CUSTOMERS_EDIT_TITLE_STATUS',
      'MODULE_ACCOUNT_CUSTOMERS_EDIT_CONTENT_WIDTH',
      'MODULE_ACCOUNT_CUSTOMERS_EDIT_TITLE_SORT_ORDER'
    );
  }
}
