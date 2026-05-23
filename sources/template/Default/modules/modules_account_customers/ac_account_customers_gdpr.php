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

class ac_account_customers_gdpr
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

    $this->title = CLICSHOPPING::getDef('module_account_customers_gdpr_title');
    $this->description = CLICSHOPPING::getDef('module_account_customers_gdpr_description');

    if (\defined('MODULE_ACCOUNT_CUSTOMERS_GDPR_TITLE_STATUS')) {
      $this->sort_order = \defined('MODULE_ACCOUNT_CUSTOMERS_GDPR_TITLE_SORT_ORDER') ? (int)MODULE_ACCOUNT_CUSTOMERS_GDPR_TITLE_SORT_ORDER : 0;
      $this->enabled = (\defined('MODULE_ACCOUNT_CUSTOMERS_GDPR_TITLE_STATUS') && MODULE_ACCOUNT_CUSTOMERS_GDPR_TITLE_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_GET['Account']) && isset($_GET['Gdpr'])) {
      $source_folder = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/Hooks/Shop/Account/';

      $files_get = $CLICSHOPPING_Template->getSpecificFiles($source_folder, 'AccountGdpr*');

      $content_width = \defined('MODULE_ACCOUNT_CUSTOMERS_GDPR_CONTENT_WIDTH') ? (int)MODULE_ACCOUNT_CUSTOMERS_GDPR_CONTENT_WIDTH : 12;

      $account_gdpr = '<!-- Start account_customers_gdpr --> ' . "\n";

      $form = HTML::form('gdpr_account', CLICSHOPPING::link(null, 'Account&Gdpr&Process'), 'post', 'id="gdpr_account"', ['tokenize' => true, 'action' => 'process']);
      $endform = '</form>';

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/account_customers_gdpr'));
      $account_gdpr .= ob_get_clean();

      $account_gdpr .= '<!-- end account_customers_gdpr -->' . "\n";

      $CLICSHOPPING_Template->addBlock($account_gdpr, $this->group);

    } // php_self
  } // function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_ACCOUNT_CUSTOMERS_GDPR_TITLE_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_ACCOUNT_CUSTOMERS_GDPR_TITLE_STATUS',
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
        'configuration_key' => 'MODULE_ACCOUNT_CUSTOMERS_GDPR_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_ACCOUNT_CUSTOMERS_GDPR_TITLE_SORT_ORDER',
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
    return Registry::get('Db')->exec('gdpr from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  public function keys()
  {
    return array(
      'MODULE_ACCOUNT_CUSTOMERS_GDPR_TITLE_STATUS',
      'MODULE_ACCOUNT_CUSTOMERS_GDPR_CONTENT_WIDTH',
      'MODULE_ACCOUNT_CUSTOMERS_GDPR_TITLE_SORT_ORDER'
    );
  }
}
