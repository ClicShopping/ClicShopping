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

/**
 * Module: ml_login_connexion
 *
 * Handles the display and configuration of the login connection module.
 *
 * - MODULE_LOGIN_CONNEXION_STATUS: Enable or disable the module.
 * - MODULE_LOGIN_CONNEXION_CONTENT_WIDTH: Set the width of the module content.
 * - MODULE_LOGIN_CONNEXION_POSITION: Set the display position of the module.
 * - MODULE_LOGIN_CONNEXION_SORT_ORDER: Set the display order of the module.
 */
class ml_login_connexion
{
  /**
   * @var string Module code
   */
  public string $code;

  /**
   * @var string Module group
   */
  public string $group;

  /**
   * @var string Module title
   */
  public $title;

  /**
   * @var string Module description
   */
  public $description;

  /**
   * @var int|null Sort order
   */
  public int|null $sort_order = 0;

  /**
   * @var bool Module enabled status
   */
  public bool $enabled = false;

  /**
   * Constructor. Initializes module properties and loads configuration.
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_login_connexion');
    $this->description = CLICSHOPPING::getDef('module_login_connexion_description');

    if (\defined('MODULE_LOGIN_CONNEXION_STATUS')) {
      $this->sort_order = (int)(defined('MODULE_LOGIN_CONNEXION_SORT_ORDER') ? MODULE_LOGIN_CONNEXION_SORT_ORDER : 0);
      $this->enabled = (defined('MODULE_LOGIN_CONNEXION_STATUS') && MODULE_LOGIN_CONNEXION_STATUS == 'True');
    }
  }

  /**
   * Executes the module: renders the login form if on the login page.
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (isset($_GET['Account'], $_GET['LogIn'])) {

      $content_width = (int)(defined('MODULE_LOGIN_CONNEXION_CONTENT_WIDTH') ? MODULE_LOGIN_CONNEXION_CONTENT_WIDTH : 12);

      $ml_login_connexion = '<!-- ml_login_connexion start-->' . "\n";

      $form = HTML::form('login', CLICSHOPPING::link(null, 'Account&LogIn&Process'), 'post', 'id="login"', ['tokenize' => true]);
      $endform = '</form>';

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/login_connexion'));

      $ml_login_connexion .= ob_get_clean();

      $ml_login_connexion .= '<!-- ml_login_connexion  end-->' . "\n";

      $CLICSHOPPING_Template->addBlock($ml_login_connexion, $this->group);
    }
  }

  /**
   * Checks if the module is enabled.
   *
   * @return bool
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Checks if the module configuration is defined.
   *
   * @return bool
   */
  public function check()
  {
    return \defined('MODULE_LOGIN_CONNEXION_STATUS');
  }

  /**
   * Installs the module configuration into the database.
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_LOGIN_CONNEXION_STATUS',
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
        'configuration_key' => 'MODULE_LOGIN_CONNEXION_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Where Do you want to display the module ?',
        'configuration_key' => 'MODULE_LOGIN_CONNEXION_POSITION',
        'configuration_value' => 'float-none',
        'configuration_description' => 'Select where you want display the module',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\', \'float-none\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_LOGIN_CONNEXION_SORT_ORDER',
        'configuration_value' => '5',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes the module configuration from the database.
   *
   * @return int Number of rows affected
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Returns the configuration keys used by this module.
   *
   * @return array
   */
  public function keys()
  {
    return array(
      'MODULE_LOGIN_CONNEXION_STATUS',
      'MODULE_LOGIN_CONNEXION_CONTENT_WIDTH',
      'MODULE_LOGIN_CONNEXION_POSITION',
      'MODULE_LOGIN_CONNEXION_SORT_ORDER'
    );
  }
}
