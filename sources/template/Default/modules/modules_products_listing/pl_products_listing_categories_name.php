<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class pl_products_listing_categories_name
{

  public string $code;
  public string $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;

  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_products_listing_categories_name_title');
    $this->description = CLICSHOPPING::getDef('module_products_listing_categories_name_description');

    if (\defined('MODULE_PRODUCTS_LISTING_CATEGORIES_NAME_STATUS')) {
      $this->sort_order = \defined('MODULE_PRODUCTS_LISTING_CATEGORIES_NAME_SORT_ORDER') ? (int)MODULE_PRODUCTS_LISTING_CATEGORIES_NAME_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_PRODUCTS_LISTING_CATEGORIES_NAME_STATUS') ? (MODULE_PRODUCTS_LISTING_CATEGORIES_NAME_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Category = Registry::get('Category');

    if (!empty($CLICSHOPPING_Category->getPath())) {
      if ($CLICSHOPPING_Category->getID()) {
        if ($CLICSHOPPING_Category->getDepth() == 'nested' || $CLICSHOPPING_Category->getDepth() == 'products') {
          $bootstrap_column = \defined('MODULE_PRODUCTS_LISTING_CATEGORIES_COLUMNS') ? (int)MODULE_PRODUCTS_LISTING_CATEGORIES_COLUMNS : 0;

          $products_listing_categories_name = $CLICSHOPPING_Category->getTitle();

          $products_listing = '<!-- product_listing_description start -->' . "\n";

          ob_start();
          require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/products_listing_categories_name'));

          $products_listing .= ob_get_clean();

          $products_listing .= '<!-- product_listing_description end -->' . "\n";

          $CLICSHOPPING_Template->addBlock($products_listing, $this->group);
        }
      }
    }
  }

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_PRODUCTS_LISTING_CATEGORIES_NAME_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_CATEGORIES_NAME_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please indicate the number of column that you want to display ?',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_CATEGORIES_COLUMNS',
        'configuration_value' => '6',
        'configuration_description' => 'Choose a number between 1 and 12',
        'configuration_group_id' => '6',
        'sort_order' => '3',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_PRODUCTS_LISTING_CATEGORIES_NAME_SORT_ORDER',
        'configuration_value' => '20',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '4',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  public function keys()
  {
    return array('MODULE_PRODUCTS_LISTING_CATEGORIES_NAME_STATUS',
      'MODULE_PRODUCTS_LISTING_CATEGORIES_COLUMNS',
      'MODULE_PRODUCTS_LISTING_CATEGORIES_NAME_SORT_ORDER'
    );
  }
}
