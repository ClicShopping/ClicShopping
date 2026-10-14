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
 * Class cap_create_account_pro_button_process
 *
 * Handles the display and configuration of the "Create Account Pro Button Process" module.
 * This module manages the rendering of the account creation button for professional accounts,
 * including caching, configuration, and template integration.
 *
 * @package modules_create_account_pro
 */
class cap_create_account_pro_button_process
{
  /**
   * Module code identifier.
   * @var string
   */
  public string $code;

  /**
   * Module group name.
   * @var string
   */
  public string $group;

  /**
   * Module title (localized).
   * @var string
   */
  public $title;

  /**
   * Module description (localized).
   * @var string
   */
  public $description;

  /**
   * Sort order for module display.
   * @var int|null
   */
  public int|null $sort_order = 0;

  /**
   * Module enabled status.
   * @var bool
   */
  public bool $enabled = false;

  /**
   * Cache block identifier.
   * @var string
   */
  protected string $cache_block;

  /**
   * Current language ID.
   * @var int
   */
  protected int $lang;

  /**
   * Constructor. Initializes module properties and configuration.
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->cache_block = 'create_account_pro_button_process_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_create_account_pro_button_process_title');
    $this->description = CLICSHOPPING::getDef('module_create_account_pro_button_process_description');

    if (\defined('MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_STATUS')) {
      $this->sort_order = \defined('MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_SORT_ORDER') ? (int)MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_STATUS') ? (MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_STATUS == 'True') : false;
    }
  }

  /**
   * Executes the module logic, rendering the button process template and handling caching.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');

    if (isset($_GET['Account'], $_GET['CreatePro']) && !isset($_GET['Success'])) {
      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        // Cache based only on language as the introduction text is static
        $cache_id = $this->cache_block . $this->lang;
        $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);

        if ($cache_output !== false) {
          $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
          return;
        }
      }

      $content_width = \defined('MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_CONTENT_WIDTH') ? (int)MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_CONTENT_WIDTH : 12;

      $create_account_pro = '<!-- Start create_account_pro_button_process start -->' . "\n";

      $endform = '</form>';

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/create_account_pro_button_process'));

      $create_account_pro .= ob_get_clean();

      $create_account_pro .= '<!-- End create_account_pro_button_process  -->' . "\n";

      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $CLICSHOPPING_TemplateCache->setCache($cache_id, $create_account_pro);
      }

      $CLICSHOPPING_Template->addBlock($create_account_pro, $this->group);
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
    return \defined('MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_STATUS');
  }

  /**
   * Installs the module configuration into the database.
   *
   * @return void
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_STATUS',
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
        'configuration_key' => 'MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_SORT_ORDER',
        'configuration_value' => '700',
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
   * @return int
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
      'MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_STATUS',
      'MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_CONTENT_WIDTH',
      'MODULE_CREATE_ACCOUNT_PRO_BUTTON_PROCESS_SORT_ORDER'
    );
  }
}
