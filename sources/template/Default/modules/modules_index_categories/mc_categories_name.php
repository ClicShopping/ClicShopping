<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class mc_categories_name
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

    $this->title = CLICSHOPPING::getDef('module_index_categories_name_title');
    $this->description = CLICSHOPPING::getDef('module_index_categories_name_description');

    if (\defined('MODULE_INDEX_CATEGORIES_NAME_STATUS')) {
      $this->sort_order = \defined('MODULE_INDEX_CATEGORIES_NAME_SORT_ORDER') ? (int)MODULE_INDEX_CATEGORIES_NAME_SORT_ORDER : 0;
      $this->enabled = \defined('MODULE_INDEX_CATEGORIES_NAME_STATUS') ? (MODULE_INDEX_CATEGORIES_NAME_STATUS == 'True') : false;
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Category = Registry::get('Category');

    $content_width = \defined('MODULE_INDEX_CATEGORIES_NAME_CONTENT_WIDTH') ? (int)MODULE_INDEX_CATEGORIES_NAME_CONTENT_WIDTH : 12;

    if (CLICSHOPPING::getBaseNameIndex() && $CLICSHOPPING_Category->getPath()) {

      if ($CLICSHOPPING_Category->getDepth() == 'nested' || $CLICSHOPPING_Category->getDepth() == 'products') {

        $CLICSHOPPING_Template = Registry::get('Template');

        $categorie_name = $CLICSHOPPING_Category->getTitle();

        $categories_content = '<!-- Index Categories name start -->' . "\n";

        ob_start();
        require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/categories_name'));
        $categories_content .= ob_get_clean();

        $categories_content .= '<!-- Index Categories name end -->' . "\n";

        $CLICSHOPPING_Template->addBlock($categories_content, $this->group);
      }
    }
  } // public function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_INDEX_CATEGORIES_NAME_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_INDEX_CATEGORIES_NAME_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module in your shop ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Please select the width of the module ?',
        'configuration_key' => 'MODULE_INDEX_CATEGORIES_NAME_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_INDEX_CATEGORIES_NAME_SORT_ORDER',
        'configuration_value' => '10',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '2',
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
    return array(
      'MODULE_INDEX_CATEGORIES_NAME_STATUS',
      'MODULE_INDEX_CATEGORIES_NAME_CONTENT_WIDTH',
      'MODULE_INDEX_CATEGORIES_NAME_SORT_ORDER'
    );
  }
}
