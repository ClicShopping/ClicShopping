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

class ta_tell_a_friend_numeric_antispam
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

    $this->title = CLICSHOPPING::getDef('modules_tell_a_friend_antispam_numeric_antispam_title');
    $this->description = CLICSHOPPING::getDef('modules_tell_a_friend_antispam_numeric_antispam_description');

    if (\defined('CLICSHOPPING_APP_ANTISPAM_STATUS') && CLICSHOPPING_APP_ANTISPAM_STATUS == 'True') {
      if (\defined('CLICSHOPPING_APP_ANTISPAM_AM_STATUS') && CLICSHOPPING_APP_ANTISPAM_AM_STATUS == 'True') {
        if (\defined('CLICSHOPPING_APP_ANTISPAM_AM_TELL_A_FRIEND') && CLICSHOPPING_APP_ANTISPAM_AM_TELL_A_FRIEND == 'True') {
          if (\defined('MODULES_TELL_A_FRIEND_NUMERIC_ANTISPAM_STATUS')) {
            $this->enabled = (MODULES_TELL_A_FRIEND_NUMERIC_ANTISPAM_STATUS == 'True');
            $this->sort_order = \defined('MODULES_TELL_A_FRIEND_NUMERIC_ANTISPAM_SORT_ORDER') ? (int)MODULES_TELL_A_FRIEND_NUMERIC_ANTISPAM_SORT_ORDER : 0;
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

    if (isset($_GET['Products'], $_GET['TellAFriend'])) {
      $content_width = (int)MODULES_TELL_A_FRIEND_NUMERIC_ANTISPAM_CONTENT_WIDTH;

      $antispam = AntiSpam::getConfirmationNumericAntiSpam();
      $tell_a_friend_antispam = '<!--  tell_a_friend_antispam start -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/tell_a_friend_numeric_antispam'));

      $tell_a_friend_antispam .= ob_get_clean();

      $tell_a_friend_antispam .= '<!-- tell_a_friend_antispam end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($tell_a_friend_antispam, $this->group);
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULES_TELL_A_FRIEND_NUMERIC_ANTISPAM_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');


    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_TELL_A_FRIEND_NUMERIC_ANTISPAM_STATUS',
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
        'configuration_key' => 'MODULES_TELL_A_FRIEND_NUMERIC_ANTISPAM_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULES_TELL_A_FRIEND_NUMERIC_ANTISPAM_SORT_ORDER',
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
    return ['MODULES_TELL_A_FRIEND_NUMERIC_ANTISPAM_STATUS',
      'MODULES_TELL_A_FRIEND_NUMERIC_ANTISPAM_CONTENT_WIDTH',
      'MODULES_TELL_A_FRIEND_NUMERIC_ANTISPAM_SORT_ORDER'
    ];
  }
}
