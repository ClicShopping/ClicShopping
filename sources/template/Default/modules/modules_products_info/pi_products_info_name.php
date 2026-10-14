<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\CockpitAI\ProductsTracking;

/**
 * Class pi_products_info_name
 *
 * This module displays the product name on the product information page.
 * It supports caching based on language and product ID, and allows configuration
 * for enabling/disabling, content width, display position, and sort order.
 *
 * Configuration options:
 * - MODULE_PRODUCTS_INFO_NAME_STATUS: Enable or disable the module.
 * - MODULE_PRODUCTS_INFO_NAME_CONTENT_WIDTH: Set the width of the module (1-12).
 * - MODULE_PRODUCTS_INFO_NAME_POSITION: Set the display position (float-end, float-start, float-none).
 * - MODULE_PRODUCTS_INFO_NAME_SORT_ORDER: Set the sort order for display.
 *
 * @package ClicShopping\Sites\Shop\Modules\ProductsInfo
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
class pi_products_info_name
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
   * @var int|null Sort order for display
   */
  public int|null $sort_order = 0;

  /**
   * @var bool Module enabled status
   */
  public bool $enabled = false;

  /**
   * @var string Cache block prefix
   */
  private mixed $cache_block;

  /**
   * @var int Language ID
   */
  private mixed $lang;

  /**
   * pi_products_info_name constructor.
   * Initializes module properties and configuration.
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);
    $this->cache_block = 'products_info_name_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_products_info_name');
    $this->description = CLICSHOPPING::getDef('module_products_info_name_description');

    if (\defined('MODULE_PRODUCTS_INFO_NAME_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_INFO_NAME_SORT_ORDER') ? (int)MODULE_PRODUCTS_INFO_NAME_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_PRODUCTS_INFO_NAME_STATUS') ? (MODULE_PRODUCTS_INFO_NAME_STATUS == 'True') : false;
    }
  }

  /**
   * Executes the module logic to display the product name.
   * Handles caching and template rendering.
   */
  public function execute()
  {
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');
    $CLICSHOPPING_Language = Registry::get('Language');

    // normalisation position module (left/right)
    $module_position = ($this->group === 'boxes_column_left') ? 'left' : 'right';

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

      $content_width = \defined('MODULE_PRODUCTS_INFO_NAME_CONTENT_WIDTH') ? (int)MODULE_PRODUCTS_INFO_NAME_CONTENT_WIDTH : 12;
      $text_position = \defined('MODULE_PRODUCTS_INFO_NAME_POSITION') ? MODULE_PRODUCTS_INFO_NAME_POSITION : 'float-none';

      $CLICSHOPPING_Template = Registry::get('Template');
      $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');

      $id = $CLICSHOPPING_ProductsCommon->getID();
      $products_name = $CLICSHOPPING_ProductsCommon->getProductsName($id);

      //trackingProduct
      ProductsTracking::insertProductTracking($id,  $this->code, $module_position, $this->sort_order, $CLICSHOPPING_Language->getId(), null, 1);

      $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($id);

      $products_name = '<a href="' . $products_name_url . '" class="productTitle">' . HTML::outputProtected($products_name) . '</a>';

      $products_name_content = '<!-- Start products_name -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_info_name'));
      $products_name_content .= ob_get_clean();

      $products_name_content .= '<!-- end products name -->' . "\n";

      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $CLICSHOPPING_TemplateCache->setCache($cache_id, $products_name_content);
      }

      $CLICSHOPPING_Template->addBlock($products_name_content, $this->group);
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
    return \defined('MODULE_PRODUCTS_INFO_NAME_STATUS');
  }

  /**
   * Installs the module configuration in the database.
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_NAME_STATUS',
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
        'configuration_key' => 'MODULE_PRODUCTS_INFO_NAME_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_PRODUCTS_INFO_NAME_POSITION',
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
        'configuration_key' => 'MODULE_PRODUCTS_INFO_NAME_SORT_ORDER',
        'configuration_value' => '10',
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
   * @return int Number of affected rows
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Returns the list of configuration keys for this module.
   *
   * @return array
   */
  public function keys()
  {
    return array(
      'MODULE_PRODUCTS_INFO_NAME_STATUS',
      'MODULE_PRODUCTS_INFO_NAME_CONTENT_WIDTH',
      'MODULE_PRODUCTS_INFO_NAME_POSITION',
      'MODULE_PRODUCTS_INFO_NAME_SORT_ORDER'
    );
  }
}
