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
use ClicShopping\Sites\Shop\Pages\Account\Classes\CreateAccount;

class cap_create_account_pro_registration
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

    $this->title = CLICSHOPPING::getDef('module_create_account_pro_registration_title');
    $this->description = CLICSHOPPING::getDef('module_create_account_pro_registration_description');

    if (\defined('MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_STATUS')) {
      $this->sort_order = \defined('MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_SORT_ORDER') ? (int)MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_STATUS') ? (MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (isset($_GET['Account'], $_GET['CreatePro']) && !isset($_GET['Success'])) {
      $content_width = \defined('MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_CONTENT_WIDTH') ? (int)MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_CONTENT_WIDTH : 12;

      $process = isset($_SESSION['process']);

      if (isset($_SESSION['entry_state_has_zones'])) {
        $entry_state_has_zones = $_SESSION['entry_state_has_zones'];
      } else {
        $entry_state_has_zones = false;
      }

      if (isset($_SESSION['entry_state_has_zones'])) {
        if (isset($_SESSION['country'])) {
          $country = HTML::sanitize($_SESSION['country']);
        } else {
          $country = 0;
        }
      }

      if (isset($_POST['country'])) {
        $default_country_pro = HTML::sanitize($_POST['country']);
      } else {
        $default_country_pro = CreateAccount::getCountryPro();
      }

      $create_account = '<!-- Start create_account_introduction start -->' . "\n";

      $form = HTML::form('create_account_pro', CLICSHOPPING::link(null, 'Account&CreatePro&Process'), 'post', 'id="usrForm"', ['tokenize' => true, 'action' => 'process']);
      $endform = '</form>';

      $footer_tag = '<!-- password start  -->' . "\n";
      $footer_tag .= '<script defer src="' . CLICSHOPPING::link($CLICSHOPPING_Template->getTemplateDefaultJavaScript('clicshopping/generate_password.js')) . '"></script>' . "\n";
      $footer_tag .= '<!--password  end  -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/create_account_pro_registration'));

      $create_account .= ob_get_clean();

      $create_account .= '<!-- End create_account_introduction end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($create_account, $this->group);
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_STATUS',
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
        'configuration_key' => 'MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_SORT_ORDER',
        'configuration_value' => '150',
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
      'MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_STATUS',
      'MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_CONTENT_WIDTH',
      'MODULE_CREATE_ACCOUNT_PRO_REGISTRATION_SORT_ORDER'
    );
  }
}
