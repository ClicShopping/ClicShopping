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
 * Class he_header_page_manager_header_menu
 *
 * This class manages the display and configuration of the header menu for the page manager module in ClicShopping.
 * It handles module initialization, execution (including caching and template rendering), and configuration management.
 *
 * Properties:
 * - $code: The module code (class name).
 * - $group: The module group (directory name).
 * - $title: The module title (from language definitions).
 * - $description: The module description (from language definitions).
 * - $sort_order: The display sort order.
 * - $enabled: Whether the module is enabled.
 * - $pages: The pages where the module is displayed.
 * - $cache_block: The cache block identifier.
 * - $lang: The current language ID.
 *
 * Methods:
 * - __construct(): Initializes module properties and configuration.
 * - execute(): Executes the module logic, handles caching, and renders the menu.
 * - isEnabled(): Checks if the module is enabled.
 * - check(): Checks if the module configuration is defined.
 * - install(): Installs the module configuration in the database.
 * - remove(): Removes the module configuration from the database.
 * - keys(): Returns the configuration keys used by this module.
 */
class he_header_page_manager_header_menu
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
   * @var int|null Sort order
   */
  public int|null $sort_order = 0;

  /**
   * @var bool Module enabled status
   */
  public bool $enabled = false;

  /**
   * @var mixed Pages where the module is displayed
   */
  public $pages;

  /**
   * @var string Cache block identifier
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
    $this->cache_block = 'header_page_manager_menu_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_header_page_manager_header_menu_title');
    $this->description = CLICSHOPPING::getDef('module_header_page_manager_header_menu_description');

    if (\defined('MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_STATUS')) {
      $this->sort_order = \defined('MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_SORT_ORDER') ? (int)MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_STATUS') ? (MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_STATUS == 'True') : false;
      $this->pages = \defined('MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_DISPLAY_PAGES') ? MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_DISPLAY_PAGES : 'all';
    }
  }

  /**
   * Executes the module logic, handles caching and rendering.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_PageManagerShop = Registry::get('PageManagerShop');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');
    $CLICSHOPPING_Customer = Registry::get('Customer');

    if (\defined('MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_STATUS') && MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_STATUS == 'True') {
    if ($this->enabled) {
      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        // Include customer group ID to manage group-specific menus
        $cache_id = $this->cache_block . $this->lang . '_' . $CLICSHOPPING_Customer->getCustomersGroupID();
        $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);

        if ($cache_output !== false) {
          $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
          return;
        }
      }

        $content_width = \defined('MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_CONTENT_WIDTH') ? (int)MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_CONTENT_WIDTH : 12;
        $header_menu = $CLICSHOPPING_PageManagerShop->pageManagerDisplayHeaderMenu('<div class="menuHeaderPageManager">', '</div>');

        $data = '<!-- Start Page Manager Header Menu -->' . "\n";

        ob_start();
        require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/header_page_manager_header_menu'));
        $data .= ob_get_clean();

        $data .= '<!-- End Page Manager Header Menu -->' . "\n";

        if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
          $CLICSHOPPING_TemplateCache->setCache($cache_id, $data);
        }

        $CLICSHOPPING_Template->addBlock($data, $this->group);
      }
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
    return \defined('MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_STATUS');
  }

  /**
   * Installs the module configuration in the database.
   *
   * @return void
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please, select the width of your module ?',
        'configuration_key' => 'MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Indicate a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order display',
        'configuration_key' => 'MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_SORT_ORDER',
        'configuration_value' => '50',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Indicate the page where the module is displayed',
        'configuration_key' => 'MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_DISPLAY_PAGES',
        'configuration_value' => 'all',
        'configuration_description' => 'Select the page where the module is displayed.',
        'configuration_group_id' => '6',
        'sort_order' => '5',
        'set_function' => 'clic_cfg_set_select_pages_list',
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
    return array('MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_STATUS',
      'MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_CONTENT_WIDTH',
      'MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_SORT_ORDER',
      'MODULE_HEADER_PAGE_MANAGER_HEADER_MENU_DISPLAY_PAGES'
    );
  }
}
