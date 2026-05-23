<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Module\HeaderTags;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\Catalog\Products\Products as ProductsApp;

class ProductsConditions extends \ClicShopping\OM\Modules\HeaderTagsAbstract
{
  public mixed $app;
  public mixed $group;
  /**
   * Initializes the module by setting up dependencies, language definitions, and module properties.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('Products')) {
      Registry::set('Products', new ProductsApp());
    }

    $this->app = Registry::get('Products');
    $this->group = 'footer_scripts'; // could be header_tags or footer_scripts

    $this->app->loadDefinitions('Module/HeaderTags/products_conditions');

    $this->title = $this->app->getDef('module_header_tags_product_condition_title');
    $this->description = $this->app->getDef('module_header_tags_product_condition_description');

    if (\defined('MODULE_HEADER_TAGS_PRODUCT_CONDITION_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_PRODUCT_CONDITION_SORT_ORDER;
      $this->enabled = (MODULE_HEADER_TAGS_PRODUCT_CONDITION_STATUS == 'True');
    }
  }

  /**
   * Checks whether the module or functionality is enabled.
   *
   * @return bool Returns true if the module or functionality is enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates and returns the output of the product's JSON-LD metadata for inclusion in the HTML footer or header group.
   * This method retrieves necessary dependencies, checks required conditions, and constructs the JSON-LD metadata.
   *
   * @return string|false The generated block to be added to the template group or false if the conditions are not met.
   */
  public function getOutput()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');
    $CLICSHOPPING_ProductsFunctionTemplate = Registry::get('ProductsFunctionTemplate');

    if (!\defined('CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS') || CLICSHOPPING_APP_CATALOG_PRODUCTS_PD_STATUS == 'False') {
      return false;
    }

    $products_id = $CLICSHOPPING_ProductsCommon->getId();

    if (isset($_GET['Id']) || isset($_GET['products_id'])) {
      $jsonLtd = $CLICSHOPPING_ProductsFunctionTemplate->getProductJsonLd($products_id);

      $footer_tag = '<!-- products condition json_ltd -->' . "\n";
      $footer_tag .= $jsonLtd . "\n";
      $footer_tag .= '<!-- end products condition json_ltd -->' . "\n";

      $display_result = $CLICSHOPPING_Template->addBlock($footer_tag, $this->group);

      $output =
        <<<EOD
        {$display_result}
        EOD;

      return $output;
    }
  }

  /**
   * Installs the module by saving its configuration into the database.
   * Two configuration entries are created: one for enabling/disabling the module,
   * and another for defining the sort order in which the module will display.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_CONDITION_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to install this module ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Display sort order',
        'configuration_key' => 'MODULE_HEADER_TAGS_PRODUCT_CONDITION_SORT_ORDER',
        'configuration_value' => '162',
        'configuration_description' => 'Display sort order (The lower is displayed in first)',
        'configuration_group_id' => '6',
        'sort_order' => '215',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves the keys associated with the module's configuration settings.
   *
   * @return array The array of configuration keys used by the module.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_PRODUCT_CONDITION_STATUS',
      'MODULE_HEADER_TAGS_PRODUCT_CONDITION_SORT_ORDER'
    ];
  }
}
