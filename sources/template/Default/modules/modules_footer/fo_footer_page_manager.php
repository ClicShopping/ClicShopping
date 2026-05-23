<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class fo_footer_page_manager
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;
  public $pages;
  private $cache_id;
/**
   * fo_footer_page_manager module
   *
   * This module manages the display of custom pages in the footer section.
   * It supports configuration for enabling/disabling, content width, sort order,
   * and page selection. It also supports caching and customer group filtering.
   */
  public function __construct()
  {
    /**
     * Initializes module properties and configuration.
     */
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->title = CLICSHOPPING::getDef('module_footer_page_manager_title');
    $this->description = CLICSHOPPING::getDef('module_footer_page_manager_description');
    $this->cache_id = 'footer_page_manager_';

    if (\defined('MODULES_FOOTER_PAGE_MANAGER_STATUS')) {
      $this->sort_order = \defined('MODULES_FOOTER_PAGE_MANAGER_SORT_ORDER') ? (int)MODULES_FOOTER_PAGE_MANAGER_SORT_ORDER : 0;
      $this->enabled = \defined('MODULES_FOOTER_PAGE_MANAGER_STATUS') ? (MODULES_FOOTER_PAGE_MANAGER_STATUS == 'True') : false;
      $this->pages = \defined('MODULE_FOOTER_PAGE_MANAGER_DISPLAY_PAGES') ? MODULE_FOOTER_PAGE_MANAGER_DISPLAY_PAGES : 'all';
    }
  }

  /**
   * Executes the module logic, handles caching, and renders the footer page manager.
   *
   * - Checks if the module is enabled and if the customer is allowed to view.
   * - Uses cache if enabled and available.
   * - Queries the database for available pages for the customer group.
   * - Renders the footer block if pages are available.
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_PageManagerShop = Registry::get('PageManagerShop');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');

    if (\defined('MODE_VENTE_PRIVEE') && (MODE_VENTE_PRIVEE == 'false' || (\defined('MODE_VENTE_PRIVEE') && (MODE_VENTE_PRIVEE == 'true' && $CLICSHOPPING_Customer->isLoggedOn())))) {
      $cache_id = $this->cache_id . $CLICSHOPPING_Customer->getCustomersGroupID();

      if ($this->enabled && $CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);
        if ($cache_output !== false) {
          $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
          return;
        }
      }

      $Qpages = $CLICSHOPPING_Db->prepare('select count(*) as count
                                            from :table_pages_manager
                                            where status = 1
                                            and page_box = 0
                                            and (customers_group_id = :customers_group_id or customers_group_id = 99)
                                           ');
      $Qpages->bindInt(':customers_group_id', (int)$CLICSHOPPING_Customer->getCustomersGroupID());

      $Qpages->execute();

      if ($Qpages->valueInt('count') > 0) {
        $content_width = \defined('MODULE_FOOTER_PAGE_MANAGER_CONTENT_WIDTH') ? (int)MODULE_FOOTER_PAGE_MANAGER_CONTENT_WIDTH : 12;

        $link = $CLICSHOPPING_PageManagerShop->pageManagerDisplayFooter();

        $page_manager_footer = '<!-- footer page manager start -->' . "\n";

        ob_start();

        require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/footer_page_manager'));

        $page_manager_footer .= ob_get_clean();

        $page_manager_footer .= '<!-- footer page manager end -->' . "\n";

        $CLICSHOPPING_Template->addBlock($page_manager_footer, $this->group);
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
    return \defined('MODULES_FOOTER_PAGE_MANAGER_STATUS');
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
        'configuration_key' => 'MODULES_FOOTER_PAGE_MANAGER_STATUS',
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
        'configuration_key' => 'MODULE_FOOTER_PAGE_MANAGER_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULES_FOOTER_PAGE_MANAGER_SORT_ORDER',
        'configuration_value' => '10',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Indicate the page where the module is displayed',
        'configuration_key' => 'MODULE_FOOTER_PAGE_MANAGER_DISPLAY_PAGES',
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
    return array('MODULES_FOOTER_PAGE_MANAGER_STATUS',
      'MODULE_FOOTER_PAGE_MANAGER_CONTENT_WIDTH',
      'MODULES_FOOTER_PAGE_MANAGER_SORT_ORDER',
      'MODULE_FOOTER_PAGE_MANAGER_DISPLAY_PAGES'
    );
  }
}