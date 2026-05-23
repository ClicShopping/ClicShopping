<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\ActionsRecorder\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Tools\ActionsRecorder\ActionsRecorder as ActionsRecorderApp;

class AdminLogins extends \ClicShopping\OM\Modules\AdminDashboardAbstract
{
  public mixed $lang;
  public mixed $app;
  public $group;

  /**
   * Initializes the module by setting up required dependencies, loading language definitions,
   * and defining the module's title, description, sort order, and enabled status.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('ActionsRecorder')) {
      Registry::set('ActionsRecorder', new ActionsRecorderApp());
    }

    $this->app = Registry::get('ActionsRecorder');
    $this->lang = Registry::get('Language');

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/admin_logins');

    $this->title = $this->app->getDef('module_admin_dashboard_admin_logins_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_admin_logins_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_ADMIN_LOGINS_APP_STATUS')) {
      $this->sort_order = MODULE_ADMIN_DASHBOARD_ADMIN_LOGINS_APP_SORT_ORDER;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_ADMIN_LOGINS_APP_STATUS == 'True');
    }
  }

  /**
   * Generates and returns the HTML output for the admin logins dashboard module.
   *
   * @return string The generated HTML output for displaying admin logins data.
   */
  public function getOutput()
  {
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    $content_width = (int)MODULE_ADMIN_DASHBOARD_ADMIN_LOGINS_APP_CONTENT_WIDTH;

    $output = '<div class="col-md-' . $content_width . '">';
    $output .= '<div class="mt-1"></div>';
    $output .= '<table
        id="table"
        data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
        data-sort-name="status"
        data-sort-order="asc"
        data-toolbar="#toolbar"
        data-buttons-class="primary"
        data-show-toggle="true"
        data-show-columns="true"
        data-mobile-responsive="true"
        data-check-on-init="true">' .
      '<thead class="dataTableHeadingRow">' .
      '  <tr>' .
      '    <th data-field="status" data-switchable="false" data-sortable="true" width="20">&nbsp;</th>' .
      '    <th data-field="title">' . $this->app->getDef('module_admin_dashboard_admin_heading_title') . '</th>' .
      '    <th data-field="date" class="text-center">' . $this->app->getDef('module_admin_dashboard_admin_logins_app_date') . '</th>' .
      '    <th data-field="action" data-switchable="false" class="text-end">' . $this->app->getDef('module_admin_dashboard_admin_logins_action') . '</th>' .
      '  </tr>' .
      '</thead>';
    '<tbody>';

    $Qlogins = $this->app->db->get('action_recorder', [
      'id',
      'user_name',
      'success',
      'date_added'
    ], [
      'module' => 'ar_admin_login'
    ],
      'date_added desc',
      6
    );

    while ($Qlogins->fetch()) {
      $output .= '  <tr class="dataTableRow backgroundBlank">' .
        '    <td class="text-center"><i class="bi bi-' . (($Qlogins->valueInt('success') === 1) ? 'check' : 'bi-x') . '"></i>&nbsp;' .
        '    <td><a href="' . CLICSHOPPING::link(null, 'A&Tools\ActionsRecorder&ActionsRecorder&module=ar_admin_login&aID=' . $Qlogins->valueInt('id')) . '">' . $Qlogins->valueProtected('user_name') . '</a></td>' .
        '    <td class="text-center">' . DateTime::toShort($Qlogins->value('date_added')) . '</td>' .
        '    <td class="text-end"><a href="' . CLICSHOPPING::link(null, 'A&Tools\ActionsRecorder&ActionsRecorder&module=ar_admin_login&aID=' . $Qlogins->valueInt('id')) . '"><h4><i class="bi bi-pencil" title="' . $this->app->getDef('module_admin_dashboard_admin_logins_icon_edit') . '"></i></h4></a>&nbsp;' .
        '  </tr>';
    }

    $output .= '<tbody>';
    $output .= '</table>';
    $output .= '</div>';
    $output .= '<div class="mt-1"></div>';

    return $output;
  }

  /**
   * Installs the configuration settings for the Administrator Logins Module.
   *
   * This method adds the necessary configuration entries into the database
   * that are required to enable and customize the module's functionality.
   * It includes options for enabling the module, selecting content width,
   * and determining the sort order of display.
   *
   * @return void
   */
  public function Install()
  {

    $this->app->db->save('configuration', [
        'configuration_title' => 'Enable Administrator Logins Module',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_ADMIN_LOGINS_APP_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to display the latest administrator logins on the dashboard?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Select the width to display',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_ADMIN_LOGINS_APP_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_ADMIN_LOGINS_APP_SORT_ORDER',
        'configuration_value' => '400',
        'configuration_description' => 'Sort order of display. Lowest is displayed first.',
        'configuration_group_id' => '6',
        'sort_order' => '0',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves the keys associated with the application's configuration.
   *
   * @return array An array of configuration keys used by the application.
   */
  public function keys()
  {
    return ['MODULE_ADMIN_DASHBOARD_ADMIN_LOGINS_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_ADMIN_LOGINS_APP_SORT_ORDER'
    ];
  }
}
