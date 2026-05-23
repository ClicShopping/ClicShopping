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

class ta_tell_a_friend_send_friend
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

    $this->title = CLICSHOPPING::getDef('modules_tell_a_friend_send_friend_title');
    $this->description = CLICSHOPPING::getDef('modules_tell_a_friend_send_friend_description');

    if (\defined('MODULES_TELL_A_FRIEND_SEND_FRIEND_STATUS')) {
      $this->sort_order = \defined('MODULES_TELL_A_FRIEND_SEND_FRIEND_SORT_ORDER') ? (int)MODULES_TELL_A_FRIEND_SEND_FRIEND_SORT_ORDER : 0;
      $this->enabled = (MODULES_TELL_A_FRIEND_SEND_FRIEND_STATUS == 'True');
    }
  }

  public function execute()
  {

    $CLICSHOPPING_Template = Registry::get('Template');
    $content_width = (int)MODULES_TELL_A_FRIEND_SEND_FRIEND_CONTENT_WIDTH;

    if (isset($_GET['Products'], $_GET['TellAFriend'])) {

      $name = HTML::inputField('to_name', NULL, 'required aria-required="true" id="inputToName" placeholder="' . CLICSHOPPING::getDef('modules_tell_a_friend_field_friend_name') . '" minlength="' . (int)ENTRY_FIRST_NAME_MIN_LENGTH . '"');
      $customer_email = HTML::inputField('to_email_address', NULL, 'title="' . CLICSHOPPING::getDef('modules_tell_a_friend_field_friend_email') . '" data-bs-toggle="tooltip" data-placement="right" required aria-required="true" id="inputFromEmail" placeholder="' . CLICSHOPPING::getDef('modules_tell_a_friend_field_friend_email') . '"', 'email');

      $data = '<!-- ta_tell_a_friend_send_friend start -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/tell_a_friend_send_friend'));

      $data .= ob_get_clean();

      $data .= '<!-- ta_tell_a_friend_send_friend end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($data, $this->group);
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULES_TELL_A_FRIEND_SEND_FRIEND_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_TELL_A_FRIEND_SEND_FRIEND_STATUS',
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
        'configuration_key' => 'MODULES_TELL_A_FRIEND_SEND_FRIEND_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULES_TELL_A_FRIEND_SEND_FRIEND_SORT_ORDER',
        'configuration_value' => '20',
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
    return array('MODULES_TELL_A_FRIEND_SEND_FRIEND_STATUS',
      'MODULES_TELL_A_FRIEND_SEND_FRIEND_CONTENT_WIDTH',
      'MODULES_TELL_A_FRIEND_SEND_FRIEND_SORT_ORDER'
    );
  }
}
