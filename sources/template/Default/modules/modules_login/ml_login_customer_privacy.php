<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Class ml_login_customer_privacy
 *
 * Handles the display and configuration of the customer privacy module on the login page.
 *
 * Configuration keys:
 * - MODULE_LOGIN_CUSTOMER_PRIVACY_STATUS: Enable or disable the module.
 * - MODULE_LOGIN_CUSTOMER_PRIVACY_CONTENT_WIDTH: Set the width of the module content.
 * - MODULE_LOGIN_CUSTOMER_PRIVACY_SORT_ORDER: Set the display order of the module.
 *
 * Methods:
 * - __construct(): Initializes module properties and loads configuration.
 * - execute(): Renders the customer privacy block if on the login page, with optional caching.
 * - isEnabled(): Returns whether the module is enabled.
 * - check(): Checks if the module configuration is defined.
 * - install(): Installs the module configuration into the database.
 * - remove(): Removes the module configuration from the database.
 * - keys(): Returns the configuration keys used by this module.
 */
class ml_login_customer_privacy
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
   * @var string Cache block identifier
   */
  private mixed $cache_block;

  /**
   * @var int Language ID
   */
  private mixed $lang;

  /**
   * Constructor. Initializes module properties and loads configuration.
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->cache_block = 'login_customer_privacy_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_login_customer_privacy');
    $this->description = CLICSHOPPING::getDef('module_login_customer_privacy_description');

    if (\defined('MODULE_LOGIN_CUSTOMER_PRIVACY_STATUS')) {
      $this->sort_order = \defined('MODULE_LOGIN_CUSTOMER_PRIVACY_SORT_ORDER') ? (int)MODULE_LOGIN_CUSTOMER_PRIVACY_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_LOGIN_CUSTOMER_PRIVACY_STATUS') ? (MODULE_LOGIN_CUSTOMER_PRIVACY_STATUS == 'True') : false;
    }
  }

  /**
   * Executes the module: renders the customer privacy block if on the login page, with optional caching.
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');

    if (isset($_GET['Account'], $_GET['LogIn'])) {
      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $cache_id = $this->cache_block . $this->lang;
        $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);

        if ($cache_output !== false) {
          $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
          return;
        }
      }

      $content_width = \defined('MODULE_LOGIN_CUSTOMER_PRIVACY_CONTENT_WIDTH') ? (int)MODULE_LOGIN_CUSTOMER_PRIVACY_CONTENT_WIDTH : 12;

      $login_information_customers = '<!-- login_customer_privacy start -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/login_customer_privacy'));
      $login_information_customers .= ob_get_clean();

      $login_information_customers .= '<!-- login_customer_privacy end-->' . "\n";

      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $CLICSHOPPING_TemplateCache->setCache($cache_id, $login_information_customers);
      }

      $CLICSHOPPING_Template->addBlock($login_information_customers, $this->group);
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
    return \defined('MODULE_LOGIN_CUSTOMER_PRIVACY_STATUS');
  }

  /**
   * Installs the module configuration into the database.
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_LOGIN_CUSTOMER_PRIVACY_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to activate this module?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the display?',
        'configuration_key' => 'MODULE_LOGIN_CUSTOMER_PRIVACY_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Please enter a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_LOGIN_CUSTOMER_PRIVACY_SORT_ORDER',
        'configuration_value' => '7',
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
      'MODULE_LOGIN_CUSTOMER_PRIVACY_STATUS',
      'MODULE_LOGIN_CUSTOMER_PRIVACY_CONTENT_WIDTH',
      'MODULE_LOGIN_CUSTOMER_PRIVACY_SORT_ORDER'
    );
  }
}
