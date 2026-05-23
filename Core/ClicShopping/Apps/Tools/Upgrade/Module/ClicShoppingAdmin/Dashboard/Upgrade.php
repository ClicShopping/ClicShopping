<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Tools\Upgrade\Classes\ClicShoppingAdmin\Github;
use ClicShopping\Apps\Tools\Upgrade\Upgrade as UpgradeApp;

class Upgrade extends \ClicShopping\OM\Modules\AdminDashboardAbstract
{
  public mixed $lang;
  public mixed $app;
  public $group;

  /**
   * Initializes the module by registering necessary resources, loading definitions, and setting up properties.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('Upgrade')) {
      Registry::set('Upgrade', new UpgradeApp());
    }

    $this->app = Registry::get('Upgrade');
    $this->lang = Registry::get('Language');

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/upgrade');

    $this->title = $this->app->getDef('module_admin_dashboard_clicshopping_update_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_clicshopping_update_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_CLICSHOPPING_UPDATE_APP_STATUS')) {
      $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_CLICSHOPPING_UPDATE_APP_SORT_ORDER;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_CLICSHOPPING_UPDATE_APP_STATUS == 'True');
    }
  }

  /**
   * Generates and returns the HTML output for displaying an update notification.
   *
   * The method checks the current version against the latest version available
   * from the GitHub repository and, if an update is available, it generates an
   * alert box containing information about the new version and a download button.
   *
   * @return string The HTML output for the update notification if a new version is available, or nothing if there is no update.
   */
  public function getOutput()
  {

    Registry::set('Github', new Github());
    $CLICSHOPPING_Github = Registry::get('Github');

    $current_version = CLICSHOPPING::getVersion();
    preg_match('/^(\d+\.)?(\d+\.)?(\d+)$/', $current_version, $version);

    $new_version = false;

    $core_info = $CLICSHOPPING_Github->getJsonCoreInformation();

    if (\is_object($core_info) && $core_info->version) {
      if ($current_version < $core_info->version) {
        $new_version = true;
      }
    }

    if ($new_version === true) {
      $content_width = (int)MODULE_ADMIN_DASHBOARD_CLICSHOPPING_UPDATE_APP_CONTENT_WIDTH;

      $output = '<div class="col-md-' . $content_width . '">';
      $output .= '<div class="row">';
      $output .= '<div class="alert alert-warning" role="alert">';
      $output .= '<div class="row">';
      $output .= '<span class="col-md-10"><strong>' . $this->app->getDef('module_admin_dashboard_clicshopping_update_app_text_warning_upgrade') . ' : ' . $current_version . '  => ' . $core_info->version . ' - ' . $core_info->date . '<br />' . $core_info->description . '  </strong></span>';
      $output .= '<span class="col-md-2 text-end"><a href="https://github.com/ClicShopping/ClicShopping_V3/archive/master.zip" target="_blank" rel="noreferrer">' . HTML::button($this->app->getDef('module_admin_dashboard_clicshopping_update_app_button'), null, null, 'primary', null, 'sm') . '</a></span>';
      $output .= '</div>';
      $output .= '</div>';
      $output .= '</div>';
      $output .= '</div>';
      $output .= '<div class="mt-1"></div>';

      return $output;
    }
  }

  /**
   * Installs the configuration settings for the module in the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to enable this module ?',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_CLICSHOPPING_UPDATE_APP_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to display the latest update ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Select the width to display',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_CLICSHOPPING_UPDATE_APP_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_CLICSHOPPING_UPDATE_APP_SORT_ORDER',
        'configuration_value' => '1',
        'configuration_description' => 'Sort order of display. Lowest is displayed first',
        'configuration_group_id' => '6',
        'sort_order' => '60',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves the configuration keys related to the ClicShopping update module.
   *
   * @return array An array of configuration keys for the module.
   */
  public function keys()
  {
    return ['MODULE_ADMIN_DASHBOARD_CLICSHOPPING_UPDATE_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_CLICSHOPPING_UPDATE_APP_CONTENT_WIDTH',
      'MODULE_ADMIN_DASHBOARD_CLICSHOPPING_UPDATE_APP_SORT_ORDER'
    ];
  }
}
