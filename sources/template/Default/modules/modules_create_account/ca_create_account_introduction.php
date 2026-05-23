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
 * Class ca_create_account_introduction
 *
 * This module displays an introduction section on the create account page.
 * It supports caching based on language, and its configuration can be managed
 * through the admin interface. The module can be enabled/disabled, its width
 * and sort order can be configured, and it integrates with the ClicShopping
 * template and cache systems.
 *
 * Public Properties:
 * - string $code: The class name of the module.
 * - string $group: The group/folder name where the module resides.
 * - string $title: The title of the module (from language definitions).
 * - string $description: The description of the module (from language definitions).
 * - int|null $sort_order: The display order of the module.
 * - bool $enabled: Whether the module is enabled.
 *
 * Methods:
 * - __construct(): Initializes module properties and checks configuration.
 * - execute(): Renders the introduction block, with optional caching.
 * - isEnabled(): Returns if the module is enabled.
 * - check(): Checks if the module configuration is defined.
 * - install(): Installs the module configuration into the database.
 * - remove(): Removes the module configuration from the database.
 * - keys(): Returns the configuration keys used by this module.
 */
class ca_create_account_introduction
{
  /**
   * @var string The class name of the module.
   */
  public string $code;

  /**
   * @var string The group/folder name where the module resides.
   */
  public string $group;

  /**
   * @var string The title of the module.
   */
  public $title;

  /**
   * @var string The description of the module.
   */
  public $description;

  /**
   * @var int|null The display order of the module.
   */
  public int|null $sort_order = 0;

  /**
   * @var bool Whether the module is enabled.
   */
  public bool $enabled = false;

  /**
   * @var string The cache block prefix.
   */
  protected string $cache_block;

  /**
   * @var int The current language ID.
   */
  protected int $lang;

  /**
   * Initializes module properties and checks configuration.
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->cache_block = 'create_account_introduction_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_create_account_introduction_title');
    $this->description = CLICSHOPPING::getDef('module_create_account_introduction_description');

    if (\defined('MODULE_CREATE_ACCOUNT_INTRODUCTION_STATUS')) {
      $this->sort_order = \defined('MODULE_CREATE_ACCOUNT_INTRODUCTION_SORT_ORDER') ? (int)MODULE_CREATE_ACCOUNT_INTRODUCTION_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_CREATE_ACCOUNT_INTRODUCTION_STATUS') ? (MODULE_CREATE_ACCOUNT_INTRODUCTION_STATUS == 'True') : false;
    }
  }

  /**
   * Renders the introduction block, with optional caching.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');

    if (isset($_GET['Account'], $_GET['Create']) && !isset($_GET['Success'])) {
      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        // Cache based only on language as the introduction text is static
        $cache_id = $this->cache_block . $this->lang;
        $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);

        if ($cache_output !== false) {
          $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
          return;
        }
      }

      $content_width = \defined('MODULE_CREATE_ACCOUNT_INTRODUCTION_CONTENT_WIDTH') ? (int)MODULE_CREATE_ACCOUNT_INTRODUCTION_CONTENT_WIDTH : 12;
      $create_account_introduction = '<!-- Start create_account_introduction -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/create_account_introduction'));
      $create_account_introduction .= ob_get_clean();

      $create_account_introduction .= '<!-- end create_account_introduction  -->' . "\n";

      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $CLICSHOPPING_TemplateCache->setCache($cache_id, $create_account_introduction);
      }

      $CLICSHOPPING_Template->addBlock($create_account_introduction, $this->group);
    }
  }

  /**
   * Returns if the module is enabled.
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
    return \defined('MODULE_CREATE_ACCOUNT_INTRODUCTION_STATUS');
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
        'configuration_key' => 'MODULE_CREATE_ACCOUNT_INTRODUCTION_STATUS',
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
        'configuration_key' => 'MODULE_CREATE_ACCOUNT_INTRODUCTION_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_CREATE_ACCOUNT_INTRODUCTION_SORT_ORDER',
        'configuration_value' => '50',
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
      'MODULE_CREATE_ACCOUNT_INTRODUCTION_STATUS',
      'MODULE_CREATE_ACCOUNT_INTRODUCTION_CONTENT_WIDTH',
      'MODULE_CREATE_ACCOUNT_INTRODUCTION_SORT_ORDER'
    );
  }
}
