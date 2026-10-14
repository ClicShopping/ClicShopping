<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\Apps\Configuration\Antispam\Classes\Shop\AntiSpam;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class ca_create_account_numeric_antispam
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

    $this->title = CLICSHOPPING::getDef('modules_create_account_numeric_antispam_title');
    $this->description = CLICSHOPPING::getDef('modules_create_account_numeric_antispam_description');

    if (\defined('CLICSHOPPING_APP_ANTISPAM_STATUS') && CLICSHOPPING_APP_ANTISPAM_STATUS == 'True') {
      if (\defined('CLICSHOPPING_APP_ANTISPAM_AM_STATUS') && CLICSHOPPING_APP_ANTISPAM_AM_STATUS == 'True') {
        if (\defined('CLICSHOPPING_APP_ANTISPAM_AM_CREATE_ACCOUNT') && CLICSHOPPING_APP_ANTISPAM_AM_CREATE_ACCOUNT == 'True') {
          if (\defined('MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_STATUS')) {
            $this->enabled = \defined('MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_STATUS') ? (MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_STATUS == 'True') : false;
            $this->sort_order = \defined('MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_SORT_ORDER') ? (int)MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_SORT_ORDER : 0;
          }
        } else {
          $this->enabled = false;
        }
      }
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (isset($_GET['Account'], $_GET['Create']) && !isset($_GET['Success'])) {
      $content_width = \defined('MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_CONTENT_WIDTH') ? (int)MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_CONTENT_WIDTH : 12;

      $antispam = AntiSpam::getConfirmationNumericAntiSpam();
      $create_account_antispam = '<!--  create_account_antispam start -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/create_account_numeric_antispam'));

      $create_account_antispam .= ob_get_clean();

      $create_account_antispam .= '<!-- create_account_antispam end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($create_account_antispam, $this->group);
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_STATUS',
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
        'configuration_key' => 'MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_SORT_ORDER',
        'configuration_value' => '370',
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
    return ['MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_STATUS',
      'MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_CONTENT_WIDTH',
      'MODULES_CREATE_ACCOUNT_NUMERIC_ANTISPAM_SORT_ORDER'
    ];
  }
}
