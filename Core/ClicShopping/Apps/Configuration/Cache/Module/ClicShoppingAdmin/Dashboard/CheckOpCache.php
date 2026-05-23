<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Cache\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\Cache\Cache as CacheApp;
use ClicShopping\Apps\Configuration\Cache\Classes\ClicShoppingAdmin\CacheAdmin;

class CheckOpCache extends \ClicShopping\OM\Modules\AdminDashboardAbstract
{
  public mixed $lang;
  public mixed $app;
  public $group;

  /**
   * Initializes the module by setting up required dependencies, loading definitions,
   * and configuring properties such as title, description, sort order, and enabled status.
   *
   * @return void
   */
  protected function init(): void
  {
    if (!Registry::exists('Cache')) {
      Registry::set('Cache', new CacheApp());
    }

    $this->app = Registry::get('Cache');
    $this->lang = Registry::get('Language');

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/check_opcache');

    $this->title = $this->app->getDef('module_admin_dashboard_check_opcache_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_total_check_opcache_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_CACHE_CHECK_OPCACHE_APP_STATUS')) {
      $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_CACHE_CHECK_OPCACHE_APP_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_CACHE_CHECK_OPCACHE_APP_STATUS == 'True');
    }
  }

  /**
   * Returns the output of the module, which includes a warning message if OPcache is enabled.
   *
   * This method checks if OPcache is enabled and returns an HTML string containing a warning message
   * with a link to the OPcache configuration page.
   *
   * @return string The HTML output for the module.
   */
  public function getOutput(): string
  {
    $output = '';

    if(CacheAdmin::checkOpCache() === true) {
      $link = HTML::link( $this->app ->link('Configuration\Cache&OpCache'), $this->app->getDef('module_admin_dashboard_check_opcache_app_link'));

      $output = '<div class="col-md-' . (int)MODULE_ADMIN_DASHBOARD_CACHE_CHECK_OPCACHE_APP_CONTENT_WIDTH . '">';
      $output .= '<div class="alert alert-warning" role="alert">';
      $output .= $this->app->getDef('module_admin_dashboard_check_opcache_app_alert', ['opcache_link' => $link]);
      $output .= '</div>';
      $output .= '</div>';
   }

    return $output;
  }

  /**
   * Installs the module by adding configuration settings to the database.
   *
   * @return void
   */
  public function Install(): void
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to enable this Module ?',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_CACHE_CHECK_OPCACHE_APP_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this Module ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Select the width to display',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_CACHE_CHECK_OPCACHE_APP_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 to 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Sort Order',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_CACHE_CHECK_OPCACHE_APP_SORT_ORDER',
        'configuration_value' => '3',
        'configuration_description' => 'Sort order of display. Lowest is displayed first.',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves the configuration keys for the module.
   *
   * @return array An array containing the configuration keys used by the module.
   */
  public function keys(): array
  {
    return [
      'MODULE_ADMIN_DASHBOARD_CACHE_CHECK_OPCACHE_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_CACHE_CHECK_OPCACHE_APP_CONTENT_WIDTH',
      'MODULE_ADMIN_DASHBOARD_CACHE_CHECK_OPCACHE_APP_SORT_ORDER'
    ];
  }
}
