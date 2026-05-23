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
 * Class ph_products_favorites_title
 *
 * This class manages the display and configuration of the "Products Favorites Title" module
 * in the ClicShopping e-commerce platform. It handles module initialization, execution,
 * configuration installation/removal, and status checks.
 *
 * Public Properties:
 * - string $code: The class name.
 * - string $group: The module group (directory name).
 * - mixed $title: The module title (localized).
 * - mixed $description: The module description (localized).
 * - int|null $sort_order: The display sort order.
 * - bool $enabled: Whether the module is enabled.
 *
 * Methods:
 * - __construct(): Initializes the module properties.
 * - execute(): Renders the module if enabled, with optional caching.
 * - isEnabled(): Returns the enabled status.
 * - check(): Checks if the module is installed.
 * - install(): Installs the module configuration.<<<<<<<<<
 * - remove(): Removes the module configuration.
 * - keys(): Returns the configuration keys for this module.
 */
class ph_products_favorites_title
{
  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;
  private mixed $cache_block;
  private mixed $lang;

  /**
   * Constructor: Initializes module properties and configuration.
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->cache_block = 'products_favorites_title_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_products_favorites_title');
    $this->description = CLICSHOPPING::getDef('module_products_favorites_title_description');

    if (\defined('MODULE_PRODUCTS_FAVORITES_TITLE_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_FAVORITES_TITLE_SORT_ORDER') ? (int)MODULE_PRODUCTS_FAVORITES_TITLE_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_PRODUCTS_FAVORITES_TITLE_STATUS') ? (MODULE_PRODUCTS_FAVORITES_TITLE_STATUS == 'True') : false;
    }
  }

  /**
   * Executes the module: renders and caches the output if enabled.
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');

    if (isset($_GET['Products'], $_GET['Favorites'])) {
      if ($this->enabled) {
        if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
          $cache_id = $this->cache_block . $this->lang;
          $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);

          if ($cache_output !== false) {
            $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
            return;
          }
        }

          $content_width = \defined('MODULE_PRODUCTS_FAVORITES_CONTENT_WIDTH') ? (int)MODULE_PRODUCTS_FAVORITES_CONTENT_WIDTH : 12;
          $text_position = \defined('MODULE_PRODUCTS_FAVORITES_POSITION') ? MODULE_PRODUCTS_FAVORITES_POSITION : 'float-none';

          $content = '<!-- products favorites title start -->' . "\n";
          $content .= '<div class="ModulesProductsFavoritesContainer">';

        ob_start();
        require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_favorites_title'));
        $content .= ob_get_clean();

        $content .= '<!-- products favorites title end -->' . "\n";

        if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
          $CLICSHOPPING_TemplateCache->setCache($cache_id, $content);
        }

        $CLICSHOPPING_Template->addBlock($content, $this->group);
      }
    }
  }

  /**
   * Returns whether the module is enabled.
   *
   * @return bool
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Checks if the module is installed.
   *
   * @return bool
   */
  public function check()
  {
    return \defined('MODULE_PRODUCTS_FAVORITES_TITLE_STATUS');
  }

  /**
   * Installs the module configuration into the database.
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_FAVORITES_TITLE_STATUS',
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
        'configuration_key' => 'MODULE_PRODUCTS_FAVORITES_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_PRODUCTS_FAVORITES_POSITION',
        'configuration_value' => 'float-none',
        'configuration_description' => 'Display the module on the left or on the right',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\' \'float-none\'))',
        'date_added' => 'now()'
      ]
    );
    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_PRODUCTS_FAVORITES_TITLE_SORT_ORDER',
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
      'MODULE_PRODUCTS_FAVORITES_TITLE_STATUS',
      'MODULE_PRODUCTS_FAVORITES_CONTENT_WIDTH',
      'MODULE_PRODUCTS_FAVORITES_POSITION',
      'MODULE_PRODUCTS_FAVORITES_TITLE_SORT_ORDER'
    );
  }
}
