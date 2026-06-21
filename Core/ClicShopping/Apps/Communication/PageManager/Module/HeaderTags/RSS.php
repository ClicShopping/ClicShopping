<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Communication\PageManager\Module\HeaderTags;

use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Communication\PageManager\PageManager as PageManagerApp;
use ClicShopping\OM\Domains\HeaderTagsAbstract;

class RSS extends HeaderTagsAbstract
{
  public mixed $lang;
  public mixed $app;

  /**
   * Initializes the module by setting up required registry objects, loading definitions,
   * and configuring properties such as title, description, sort order, and enabled status.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('PageManager')) {
      Registry::set('PageManager', new PageManagerApp());
    }

    $this->app = Registry::get('PageManager');
    $this->lang = Registry::get('Language');
    $this->group = 'header_tags'; // could be header_tags or footer_scripts

    $this->app->loadDefinitions('Module/HeaderTags/rss');

    $this->title = $this->app->getDef('module_header_tags_rss_title');
    $this->description = $this->app->getDef('module_header_tags_rss_description');

    if (\defined('MODULE_HEADER_TAGS_RSS_STATUS')) {
      $this->sort_order = (int)MODULE_HEADER_TAGS_RSS_SORT_ORDER;
      $this->enabled = (MODULE_HEADER_TAGS_RSS_STATUS == 'True');
    }
  }

  /**
   * Checks whether the current instance is enabled.
   *
   * @return bool Returns true if the instance is enabled, false otherwise.
   */
  public function isEnabled()
  {
    return $this->enabled;
  }

  /**
   * Generates and returns the RSS feed link block for inclusion in the template.
   *
   * @return string|bool Returns the formatted RSS feed link block as a string if the RSS functionality is enabled, or false otherwise.
   */
  public function getOutput()
  {
    $CLICSHOPPING_Template = Registry::get('Template');

    if (!\defined('CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS') || CLICSHOPPING_APP_PAGE_MANAGER_PM_STATUS == 'False') {
      return false;
    }

    $xml = $CLICSHOPPING_Template->addBlock('<link rel="alternate" type="application/rss+xml" title="' . HTML::outputProtected(STORE_NAME) . '" href="' . HTTP::getShopUrlDomain() . 'index.php?Info&RSS' . '">', $this->group);

    $output =
      <<<EOD
{$xml}
EOD;

    return $output;
  }

  /**
   * Installs the configuration settings required for the module.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to install this module ?',
        'configuration_key' => 'MODULE_HEADER_TAGS_RSS_STATUS',
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
        'configuration_key' => 'MODULE_HEADER_TAGS_RSS_SORT_ORDER',
        'configuration_value' => '210',
        'configuration_description' => 'Display sort order (The lower is displayed in first)',
        'configuration_group_id' => '6',
        'sort_order' => '215',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves the configuration keys required for the module.
   *
   * @return array An array of configuration key names.
   */
  public function keys()
  {
    return ['MODULE_HEADER_TAGS_RSS_STATUS',
      'MODULE_HEADER_TAGS_RSS_SORT_ORDER'
    ];
  }
}
