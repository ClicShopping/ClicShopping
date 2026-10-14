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
 * Class cap_create_account_pro_introduction
 *
 * Displays the introduction section for the professional account creation page.
 * Handles module configuration, caching, and rendering of the introduction content.
 */
class cap_create_account_pro_introduction
{
  /**
   * @var string Module code (class name)
   */
  public string $code;

  /**
   * @var string Module group (directory name)
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
   * @var int|null Sort order for display
   */
  public int|null $sort_order = 0;

  /**
   * @var bool Module enabled status
   */
  public bool $enabled = false;

  /**
   * @var string Cache block identifier prefix
   */
  private mixed $cache_block;

  /**
   * @var int Language ID
   */
  private mixed $lang;

  /**
   * Constructor. Initializes module properties and configuration.
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->cache_block = 'create_account_pro_introduction_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_create_account_pro_introduction_title');
    $this->description = CLICSHOPPING::getDef('module_create_account_pro_introduction_description');

    if (\defined('MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_STATUS')) {
      $this->sort_order = \defined('MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_SORT_ORDER') ? (int)MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_STATUS') ? (MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_STATUS == 'True') : false;
    }
  }

  /**
   * Executes the module logic: displays the introduction if enabled and handles caching.
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

      $content_width = \defined('MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_CONTENT_WIDTH') ? (int)MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_CONTENT_WIDTH : 12;

      $create_account_introduction = '<!-- Start create_account_pro_introduction -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/create_account_pro_introduction'));
      $create_account_introduction .= ob_get_clean();

      $create_account_introduction .= '<!-- end create_account_pro_introduction -->' . "\n";

      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $CLICSHOPPING_TemplateCache->setCache($cache_id, $create_account_introduction);
      }

      $CLICSHOPPING_Template->addBlock($create_account_introduction, $this->group);
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
    return \defined('MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_STATUS');
  }

  /**
   * Installs the module configuration into the database.
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_STATUS',
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
        'configuration_key' => 'MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_SORT_ORDER',
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
      'MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_STATUS',
      'MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_CONTENT_WIDTH',
      'MODULE_CREATE_ACCOUNT_PRO_INTRODUCTION_SORT_ORDER'
    );
  }
}
