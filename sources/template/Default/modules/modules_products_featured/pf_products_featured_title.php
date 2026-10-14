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
 * Class pf_products_featured_title
 *
 * Handles the display and configuration of the "Products Featured Title" module.
 * This module manages the featured products title block, including its rendering,
 * caching, and configuration in the ClicShopping system.
 *
 * @package ClicShopping\Modules\ProductsFeatured
 */
class pf_products_featured_title
{
  /**
   * Module code identifier.
   * @var string
   */
  public string $code;

  /**
   * Module group name (directory).
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
   * Sort order for display.
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
  private mixed $cache_block;

  /**
   * Current language ID.
   * @var int
   */
  private mixed $lang;

  /**
   * Constructor. Initializes module properties and configuration.
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->cache_block = 'products_featured_title_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_products_featured_title_tilte');
    $this->description = CLICSHOPPING::getDef('module_products_featured_title_title_description');

    if (\defined('MODULE_PRODUCTS_FEATURED_TITLE_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_FEATURED_TITLE_SORT_ORDER') ? (int)MODULE_PRODUCTS_FEATURED_TITLE_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_PRODUCTS_FEATURED_TITLE_STATUS') ? (MODULE_PRODUCTS_FEATURED_TITLE_STATUS == 'True') : false;
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
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');

    if (isset($_GET['Products'], $_GET['Featured'])) {
    if ($this->enabled) {
      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $cache_id = $this->cache_block . $this->lang;
        $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);

        if ($cache_output !== false) {
          $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
          return;
        }
      }

        $content_width = \defined('MODULE_PRODUCTS_FEATURED_CONTENT_WIDTH') ? (int)MODULE_PRODUCTS_FEATURED_CONTENT_WIDTH : 12;
        $text_position = \defined('MODULE_PRODUCTS_FEATURED_POSITION') ? MODULE_PRODUCTS_FEATURED_POSITION : 'float-none';

        $products_featured_title = '<!-- products featured title start -->' . "\n";
        $products_featured_title .= '<div class="ModulesProductsFeaturedContainer">';

        ob_start();
	require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_featured_title'));
	$products_featured_title .= ob_get_clean();

        $products_featured_title .= '</div>' . "\n";
	$products_featured_title .= '<!-- products featured title end -->' . "\n";

	if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
	    $CLICSHOPPING_TemplateCache->setCache($cache_id, $products_featured_title);
	}

	$CLICSHOPPING_Template->addBlock($products_featured_title, $this->group);
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
    return \defined('MODULE_PRODUCTS_FEATURED_TITLE_STATUS');
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
        'configuration_key' => 'MODULE_PRODUCTS_FEATURED_TITLE_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the display?',
        'configuration_key' => 'MODULE_PRODUCTS_FEATURED_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Please enter a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Where do you want to display the module?',
        'configuration_key' => 'MODULE_PRODUCTS_FEATURED_POSITION',
        'configuration_value' => 'float-none',
        'configuration_description' => 'Display the module on the left or on the right',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\', \'float-none\'))',
        'date_added' => 'now()'
      ]
    );
    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_PRODUCTS_FEATURED_TITLE_SORT_ORDER',
        'configuration_value' => '10',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '12',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes the module configuration from the database.
   *
   * @return int Number of affected rows.
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Returns the configuration keys for this module.
   *
   * @return array
   */
  public function keys()
  {
    return array(
      'MODULE_PRODUCTS_FEATURED_TITLE_STATUS',
      'MODULE_PRODUCTS_FEATURED_CONTENT_WIDTH',
      'MODULE_PRODUCTS_FEATURED_POSITION',
      'MODULE_PRODUCTS_FEATURED_TITLE_SORT_ORDER'
    );
  }
}
