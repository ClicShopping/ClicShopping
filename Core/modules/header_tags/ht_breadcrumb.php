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
 * Class representing the ht_breadcrumb module.
 *
 * The ht_breadcrumb class is responsible for managing the breadcrumb trail functionality
 * within a web application. It extends the behavior to include JSON breadcrumbs in
 * templates and integrates with application services like templates and breadcrumbs management.
 */
class ht_breadcrumb
{
  public string $code;
  public $group;
  public $title;
  public $description;
  public int|null $sort_order = 0;
  public bool $enabled = false;

  /**
   * Constructor method for initializing the class properties.
   *
   * @return void
   */
  public function __construct()
  {
    $this->code = get_class($this);
    $this->group = basename(__DIR__);

    $this->title = CLICSHOPPING::getDef('module_header_tags_breadcrumb_title');
    $this->description = CLICSHOPPING::getDef('module_header_tags_breadcrump_description');

    if (\defined('MODULE_HEADER_TAGS_BREADCRUMB_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_BREADCRUMB_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_HEADER_TAGS_BREADCRUMB_STATUS == 'True');
    }
  }

  /**
   * Executes the process of adding a footer breadcrumb script block if the Breadcrumb service is started.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_Template = Registry::get('Template');
    $CLICSHOPPING_Breadcrumb = Registry::get('Breadcrumb');
    $CLICSHOPPING_Service = Registry::get('Service');

    if ($CLICSHOPPING_Service->isStarted('Breadcrumb')) {
      $footer = $CLICSHOPPING_Breadcrumb->getJsonBreadcrumb();
    }

    $CLICSHOPPING_Template->addBlock($footer, 'footer_scripts');
  }

  /**
   * Checks if the current instance is enabled.
   *
   * @return bool Returns true if the instance is enabled, otherwise false.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Checks if the constant 'MODULE_HEADER_TAGS_BREADCRUMB_STATUS' is defined.
   *
   * @return bool Returns true if the constant is defined, otherwise false.
   */
  public function check()
  {
    return \defined('MODULE_HEADER_TAGS_BREADCRUMB_STATUS');
  }

  /**
   * Installs the configuration settings for the module into the database.
   *
   * @return void
   */
  public function install()
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Do you want to display this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_BREADCRUMB_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to display this module ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $CLICSHOPPING_Db->save('configuration', [
        'configuration_title' => 'Sort Order',
        'configuration_key' => 'MODULE_HEADER_TAGS_BREADCRUMB_SORT_ORDER',
        'configuration_value' => '555',
        'configuration_description' => 'Sort order. Lowest is displayed in first',
        'configuration_group_id' => '6',
        'sort_order' => '10',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Removes configuration entries from the database that match the keys provided by the keys() method.
   *
   * Executes a delete query on the configuration table for the specified configuration keys.
   *
   * @return int The number of rows affected by the delete query.
   */
  public function remove()
  {
    return Registry::get('Db')->exec('delete from :table_configuration where configuration_key in ("' . implode('", "', $this->keys()) . '")');
  }

  /**
   * Retrieves the configuration keys used by the module.
   *
   * @return array An array of configuration keys.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_BREADCRUMB_STATUS',
      'MODULE_HEADER_TAGS_BREADCRUMB_SORT_ORDER'
    ];
  }
}
