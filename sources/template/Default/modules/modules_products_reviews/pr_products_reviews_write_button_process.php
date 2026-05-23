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

/**
 * Class pr_products_reviews_write_button_process
 *
 * Handles the display and processing of the "write review" button for products.
 * This module manages configuration, caching, and rendering of the button block
 * in the product reviews write context.
 *
 * @package ClicShopping\Modules\ProductsReviews
 */
class pr_products_reviews_write_button_process
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
    $this->cache_block = 'products_reviews_write_button_process_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('modules_products_reviews_write_button_process_title');
    $this->description = CLICSHOPPING::getDef('modules_products_reviews_write_button_process_description');

    if (\defined('MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_STATUS')) {
      $this->sort_order = \defined('MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_SORT_ORDER') ? (int)MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_SORT_ORDER : 0;
      $this->enabled = \defined('MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_STATUS') ? (MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_STATUS == 'True') : false;
    }
  }

  /**
   * Executes the module logic: displays the review write buttons and handles caching.
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');

    if (isset($_GET['Products'], $_GET['ReviewsWrite'])) {
      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        // Cache based on language and product ID as button URLs contain the product ID
        $cache_id = $this->cache_block . $this->lang . '_' . (int)$_GET['products_id'];
        $cache_output = $CLICSHOPPING_TemplateCache->getCache($cache_id);

        if ($cache_output !== false) {
          $CLICSHOPPING_Template->addBlock($cache_output, $this->group);
          return;
        }
      }

      $content_width = \defined('MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_CONTENT_WIDTH') ? (int)MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_CONTENT_WIDTH : 0;
      $text_position = \defined('MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_POSITION') ? MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_POSITION : 'float-none';

      if (isset($_GET['Products'], $_GET['ReviewsWrite']) && !isset($_GET['Success'])) {
      $products_name_url = $CLICSHOPPING_ProductsFunctionTemplate->getProductsUrlRewrited()->getProductNameUrl($CLICSHOPPING_ProductsCommon->getID());

      $button_back = HTML::button(CLICSHOPPING::getDef('button_back'), null, $products_name_url, 'primary');
      $button_process = HTML::button(CLICSHOPPING::getDef('button_continue'), null, null, 'success');

      $reviews_button = '<!-- Start products_reviews_write_button_process -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_reviews_write_button_process'));
      $reviews_button .= ob_get_clean();

      $reviews_button .= '<!-- end products_reviews_write_button_process -->' . "\n";

      if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
        $CLICSHOPPING_TemplateCache->setCache($cache_id, $reviews_button);
      }

      $CLICSHOPPING_Template->addBlock($reviews_button, $this->group);
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
    return \defined('MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_STATUS');
  }

  /**
   * Installs the module configuration into the database.
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_STATUS',
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
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_POSITION',
        'configuration_value' => 'float-none',
        'configuration_description' => 'Select where you want display the module',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'float-end\', \'float-start\', \'float-none\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_SORT_ORDER',
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
    return array('MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_STATUS',
      'MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_CONTENT_WIDTH',
      'MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_POSITION',
      'MODULES_PRODUCTS_REVIEWS_WRITE_BUTTON_PROCESS_SORT_ORDER'
    );
  }
}
