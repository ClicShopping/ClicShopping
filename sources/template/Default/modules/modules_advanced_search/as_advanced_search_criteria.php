<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class as_advanced_search_criteria
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

    $this->title = CLICSHOPPING::getDef('module_advanced_search_criteria_title');
    $this->description = CLICSHOPPING::getDef('module_advanced_search_criteria_description');

    if (\defined('MODULE_ADVANCED_SEARCH_CRITERIA_STATUS')) {
      $this->sort_order = (int)MODULE_ADVANCED_SEARCH_CRITERIA_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_ADVANCED_SEARCH_CRITERIA_STATUS == 'True');
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (isset($_GET['AdvancedSearch'])) {

      $content_width = (int)MODULE_ADVANCED_SEARCH_CRITERIA_CONTENT_WIDTH;

      $advanced_search_criteria = '<!-- Start advanced search criteria -->' . "\n";

      ob_start();
      require_once($CLICSHOPPING_Template->getTemplateModules($this->group . '/content/advanced_search_criteria'));

      $advanced_search_criteria .= ob_get_clean();

      $advanced_search_criteria .= '<!-- end advanced search criteria -->' . "\n";

      $CLICSHOPPING_Template->addBlock($advanced_search_criteria, $this->group);
    }
  } // function execute

  public function isEnabled()
  {
    return $this->enabled;
  }

  public function check()
  {
    return \defined('MODULE_ADVANCED_SEARCH_CRITERIA_STATUS');
  }

  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_ADVANCED_SEARCH_CRITERIA_STATUS',
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
        'configuration_key' => 'MODULE_ADVANCED_SEARCH_CRITERIA_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_ADVANCED_SEARCH_CRITERIA_SORT_ORDER',
        'configuration_value' => '10',
        'configuration_description' => 'Sort order of display. Lowest is displayed first. The sort order must be different on every module',
        'configuration_group_id' => '6',
        'sort_order' => '10',
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
      'MODULE_ADVANCED_SEARCH_CRITERIA_STATUS',
      'MODULE_ADVANCED_SEARCH_CRITERIA_CONTENT_WIDTH',
      'MODULE_ADVANCED_SEARCH_CRITERIA_SORT_ORDER'
    );
  }
}
