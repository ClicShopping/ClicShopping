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
 * Class pi_products_info_model
 *
 * This module displays the product model information on the product info page.
 * It supports caching based on language and product ID, and allows configuration
 * of display width, position, and sort order via the admin interface.
 *
 * Configuration keys:
 * - MODULE_PRODUCTS_INFO_MODEL_STATUS: Enable/disable the module
 * - MODULE_PRODUCTS_INFO_MODEL_CONTENT_WIDTH: Width of the module (1-12)
 * - MODULE_PRODUCTS_INFO_MODEL_POSITION: Display position (float-end, float-start, float-none)
 * - MODULE_PRODUCTS_INFO_MODEL_SORT_ORDER: Sort order for display
 *
 * Methods:
 * - __construct(): Initializes module properties and configuration
 * - execute(): Renders the module content, uses cache if enabled
 * - isEnabled(): Returns if the module is enabled
 * - check(): Checks if the module is installed
 * - install(): Installs the module configuration
 * - remove(): Removes the module configuration
 * - keys(): Returns the configuration keys
 *
 * @package ClicShopping\Sites\Shop\Modules\ProductsInfo
 */
class pi_products_info_model
{
  /**
   * Module code identifier
   * @var string
   */
  public string $code;

  /**
   * Module group (directory name)
   * @var string
   */
  public string $group;

  /**
   * Module title (for admin display)
   * @var string
   */
  public $title;

  /**
   * Module description (for admin display)
   * @var string
   */
  public $description;

  /**
   * Sort order for display
   * @var int|null
   */
  public int|null $sort_order = 0;

  /**
   * Module enabled status
   * @var bool
   */
  public bool $enabled = false;

  /**
   * Cache block prefix
   * @var string
   */
  private mixed $cache_block;

  /**
   * Current language ID
   * @var int|string
   */
  private mixed $lang;

  /**
   * Constructor: Initializes module properties and configuration
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->cache_block = 'products_info_model_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_products_info_model');
    $this->description = CLICSHOPPING::getDef('module_products_info_model_description');

    if (\defined('MODULE_PRODUCTS_INFO_MODEL_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_INFO_MODEL_SORT_ORDER') ? (int)MODULE_PRODUCTS_INFO_MODEL_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_PRODUCTS_INFO_MODEL_STATUS') ? (MODULE_PRODUCTS_INFO_MODEL_STATUS == 'True') : false;
    }
  }

  /**
   * Executes the module: renders and outputs the product model info.
   * Uses cache if enabled.
   */
  public function execute()
  {
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');

    if ($CLICSHOPPING_ProductsCommon->getID() && isset($_GET['Products'])) {
      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        // Cache based on language and product ID
        $cache_id = $this->cache_block . $this->lang . '_' . $CLICSHOPPING_ProductsCommon->getID();
        $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);

        if ($cache_output !== false) {
          $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
          return;
        }
      }

      $content_width = \defined('MODULE_PRODUCTS_INFO_MODEL_CONTENT_WIDTH') ? (int)MODULE_PRODUCTS_INFO_MODEL_CONTENT_WIDTH : 12;
      $text_position = \defined('MODULE_PRODUCTS_INFO_MODEL_POSITION') ? MODULE_PRODUCTS_INFO_MODEL_POSITION : 'float-none';

      $products_model = $CLICSHOPPING_ProductsCommon->getProductsModel();

      $products_model_content = '<!-- Start products model -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_info_model'));
      $products_model_content .= ob_get_clean();

      $products_model_content .= '<!-- end products model -->' . "\n";

      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $CLICSHOPPING_TemplateCache->setCache($cache_id, $products_model_content);
      }

      $CLICSHOPPING_Template->addBlock($products_model_content, $this->group);
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
    return \defined('MODULE_PRODUCTS_INFO_MODEL_STATUS');
  }

  /**
   * Installs the module configuration in the database.
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_MODEL_STATUS',
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
        'configuration_key' => 'MODULE_PRODUCTS_INFO_MODEL_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_PRODUCTS_INFO_MODEL_POSITION',
        'configuration_value' => 'float-none',
        'configuration_description' => 'Select where you want display the module',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\', \'float-none\') ',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_MODEL_SORT_ORDER',
        'configuration_value' => '20',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '3',
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
      'MODULE_PRODUCTS_INFO_MODEL_STATUS',
      'MODULE_PRODUCTS_INFO_MODEL_CONTENT_WIDTH',
      'MODULE_PRODUCTS_INFO_MODEL_POSITION',
      'MODULE_PRODUCTS_INFO_MODEL_SORT_ORDER'
    );
  }
}
