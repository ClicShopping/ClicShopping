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
 * Class pi_products_info_description
 *
 * Module to display the product description on the product info page.
 *
 * Configuration options:
 * - MODULE_PRODUCTS_INFO_DESCRIPTION_STATUS: Enable or disable the module.
 * - MODULE_PRODUCTS_INFO_DESCRIPTION_CONTENT_WIDTH: Set the width (1-12).
 * - MODULE_PRODUCTS_INFO_DESCRIPTION_POSITION: Set the float position (end, start, none).
 * - MODULE_PRODUCTS_INFO_DESCRIPTION_SORT_ORDER: Set the display sort order.
 *
 * Methods:
 * - __construct(): Initializes module properties and configuration.
 * - execute(): Renders the product description block if enabled and product is set.
 * - isEnabled(): Returns if the module is enabled.
 * - check(): Checks if the module is installed.
 * - install(): Installs configuration settings in the database.
 * - remove(): Removes configuration settings from the database.
 * - keys(): Returns the configuration keys used by this module.
 *
 * @package ClicShopping\Modules\ProductsInfo
 */
class pi_products_info_description
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
    $this->cache_block = 'products_info_description_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_products_info_description');
    $this->description = CLICSHOPPING::getDef('module_products_info_description_description');

    if (\defined('MODULE_PRODUCTS_INFO_DESCRIPTION_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_INFO_DESCRIPTION_SORT_ORDER') ? (int)MODULE_PRODUCTS_INFO_DESCRIPTION_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_PRODUCTS_INFO_DESCRIPTION_STATUS') ? (MODULE_PRODUCTS_INFO_DESCRIPTION_STATUS == 'True') : false;
    }
  }

  /**
   * Executes the module: displays the product description if applicable.
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

      $content_width = \defined('MODULE_PRODUCTS_INFO_DESCRIPTION_CONTENT_WIDTH') ? (int)MODULE_PRODUCTS_INFO_DESCRIPTION_CONTENT_WIDTH : 12;
      $text_position = \defined('MODULE_PRODUCTS_INFO_DESCRIPTION_POSITION') ? MODULE_PRODUCTS_INFO_DESCRIPTION_POSITION : 'float-none';

      $products_description = stripslashes($CLICSHOPPING_ProductsCommon->getProductsDescription());

      $products_description_content = '<!-- Start products description -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_info_description'));
      $products_description_content .= ob_get_clean();

      $products_description_content .= '<!-- end products description -->' . "\n";

      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $CLICSHOPPING_TemplateCache->setCache($cache_id, $products_description_content);
      }

      $CLICSHOPPING_Template->addBlock($products_description_content, $this->group);
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
    return \defined('MODULE_PRODUCTS_INFO_DESCRIPTION_STATUS');
  }

  /**
   * Installs the module configuration in the database.
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_DESCRIPTION_STATUS',
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
        'configuration_key' => 'MODULE_PRODUCTS_INFO_DESCRIPTION_CONTENT_WIDTH',
        'configuration_value' => '7',
        'configuration_description' => 'Select a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Where Do you want to display the module ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_DESCRIPTION_POSITION',
        'configuration_value' => 'float-start',
        'configuration_description' => 'Select where you want display the module',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\', \'float-none\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_DESCRIPTION_SORT_ORDER',
        'configuration_value' => '30',
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
      'MODULE_PRODUCTS_INFO_DESCRIPTION_STATUS',
      'MODULE_PRODUCTS_INFO_DESCRIPTION_CONTENT_WIDTH',
      'MODULE_PRODUCTS_INFO_DESCRIPTION_POSITION',
      'MODULE_PRODUCTS_INFO_DESCRIPTION_SORT_ORDER'
    );
  }
}
