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

class ta_tell_a_friend_button_process
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

    $this->title = CLICSHOPPING::getDef('modules_tell_a_friend_button_process_title');
    $this->description = CLICSHOPPING::getDef('modules_tell_a_friend_button_process_description');

    if (\defined('MODULES_TELL_A_FRIEND_BUTTON_PROCESS_STATUS')) {
      $this->sort_order = \defined('MODULES_TELL_A_FRIEND_BUTTON_PROCESS_SORT_ORDER') ? (int)MODULES_TELL_A_FRIEND_BUTTON_PROCESS_SORT_ORDER : 0;
      $this->enabled = (MODULES_TELL_A_FRIEND_BUTTON_PROCESS_STATUS == 'True');
    }
  }

  public function execute()
  {

    $CLICSHOPPING_Template = Registry::get('Template');

    if (isset($_GET['Products'], $_GET['TellAFriend'])) {

      $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
      $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');

      $content_width = \defined('MODULES_TELL_A_FRIEND_BUTTON_PROCESS_CONTENT_WIDTH') ? (int)MODULES_TELL_A_FRIEND_BUTTON_PROCESS_CONTENT_WIDTH : 0;

      $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($CLICSHOPPING_ProductsCommon->getID());

      $button_back = HTML::button(CLICSHOPPING::getDef('button_back'), null, $products_name_url, 'primary');
      $button_process = HTML::button(CLICSHOPPING::getDef('button_continue'), null, null, 'success');

      $data = '<!-- ta_tell_a_friend_button_process start -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/tell_a_friend_button_process'));

      $data .= ob_get_clean();

      $data .= '<!-- ta_tell_a_friend_button_process end -->' . "\n";

      $CLICSHOPPING_Template->addBlock($data, $this->group);
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULES_TELL_A_FRIEND_BUTTON_PROCESS_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_TELL_A_FRIEND_BUTTON_PROCESS_STATUS',
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
        'configuration_key' => 'MODULES_TELL_A_FRIEND_BUTTON_PROCESS_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULES_TELL_A_FRIEND_BUTTON_PROCESS_SORT_ORDER',
        'configuration_value' => '1500',
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
    return array('MODULES_TELL_A_FRIEND_BUTTON_PROCESS_STATUS',
      'MODULES_TELL_A_FRIEND_BUTTON_PROCESS_CONTENT_WIDTH',
      'MODULES_TELL_A_FRIEND_BUTTON_PROCESS_SORT_ORDER'
    );
  }
}
