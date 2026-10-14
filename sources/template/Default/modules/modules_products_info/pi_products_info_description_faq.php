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
 * Class pi_products_info_description_faq
 *
 * Module to display the product FAQ on the product info page.
 * FAQ content is retrieved from the products_description_faq table.
 *
 * Configuration options:
 * - MODULE_PRODUCTS_INFO_FAQ_STATUS: Enable or disable the module.
 * - MODULE_PRODUCTS_INFO_FAQ_CONTENT_WIDTH: Set the width (1-12).
 * - MODULE_PRODUCTS_INFO_FAQ_SORT_ORDER: Set the display sort order.
 *
 * Methods:
 * - __construct(): Initializes module properties and configuration.
 * - execute(): Renders the product FAQ block if enabled and FAQ exists.
 * - isEnabled(): Returns if the module is enabled.
 * - check(): Checks if the module is installed.
 * - install(): Installs configuration settings in the database.
 * - remove(): Removes configuration settings from the database.
 * - keys(): Returns the configuration keys used by this module.
 *
 * @package ClicShopping\Modules\ProductsInfo
 */
class pi_products_info_description_faq
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
    $this->cache_block = 'products_info_description_faq_';
    $this->lang = Registry::get('Language')->getId();

    $this->title = CLICSHOPPING::getDef('module_products_info_faq_title');
    $this->description = CLICSHOPPING::getDef('module_products_info_faq_description');

    if (\defined('MODULE_PRODUCTS_INFO_FAQ_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_INFO_FAQ_SORT_ORDER') ? (int)MODULE_PRODUCTS_INFO_FAQ_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_PRODUCTS_INFO_FAQ_STATUS') ? (MODULE_PRODUCTS_INFO_FAQ_STATUS == 'True') : false;
    }
  }

  /**
   * Executes the module: displays the product FAQ if applicable.
   * Only displays if 2 or more FAQ items exist.
   */
  public function execute()
  {
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_TemplateCache = Registry::get('TemplateCache');
    $CLICSHOPPING_Db = Registry::get('Db');

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

      // Retrieve FAQ from products_description_faq table
      $Qfaq = $CLICSHOPPING_Db->prepare('SELECT faq_content, faq_description
                                          FROM :table_products_description_faq
                                          WHERE products_id = :products_id
                                            AND language_id = :language_id
                                          LIMIT 1');
      $Qfaq->bindInt(':products_id', $CLICSHOPPING_ProductsCommon->getID());
      $Qfaq->bindInt(':language_id', $this->lang);
      $Qfaq->execute();

      $faq_row = $Qfaq->fetch();

      // Only display if FAQ exists and has content
      if ($faq_row && !empty($faq_row['faq_content'])) {
        // Parse FAQ JSON content
        $faq_data = json_decode($faq_row['faq_content'], true);

        // Only display if 2 or more FAQ items exist
        if (is_array($faq_data) && count($faq_data) >= 2) {
          $content_width = \defined('MODULE_PRODUCTS_INFO_FAQ_CONTENT_WIDTH') ? (int)MODULE_PRODUCTS_INFO_FAQ_CONTENT_WIDTH : 12;

          $faq_title = CLICSHOPPING::getDef('module_products_info_faq_section_title');

          $products_faq_content = '<!-- Start products FAQ -->' . "\n";

          ob_start();
          require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_info_description_faq'));
          $products_faq_content .= ob_get_clean();

          $products_faq_content .= '<!-- end products FAQ -->' . "\n";

          if ($CLICSHOPPING_TemplateCache->isCacheEnabled()) {
            $CLICSHOPPING_TemplateCache->setCache($cache_id, $products_faq_content);
          }

          $CLICSHOPPING_Template->addBlock($products_faq_content, $this->group);
        }
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
    return \defined('MODULE_PRODUCTS_INFO_FAQ_STATUS');
  }

  /**
   * Installs the module configuration in the database.
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_FAQ_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop (AI must be activated to use this module)?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the module',
        'configuration_key' => 'MODULE_PRODUCTS_INFO_FAQ_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_PRODUCTS_INFO_FAQ_SORT_ORDER',
        'configuration_value' => '35',
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
      'MODULE_PRODUCTS_INFO_FAQ_STATUS',
      'MODULE_PRODUCTS_INFO_FAQ_CONTENT_WIDTH',
      'MODULE_PRODUCTS_INFO_FAQ_SORT_ORDER'
    );
  }
}
