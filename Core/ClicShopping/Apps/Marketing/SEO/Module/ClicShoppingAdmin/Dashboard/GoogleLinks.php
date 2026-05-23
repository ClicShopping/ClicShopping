<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\SEO\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\SEO\SEO as SEOApp;

class GoogleLinks extends \ClicShopping\OM\Modules\AdminDashboardAbstract
{
  public mixed $lang;
  public mixed $app;
  public $group;

  /**
   * Initializes the SEO application module for the admin dashboard.
   *
   * Sets up the necessary registry entries, loads language definitions,
   * and configures the module's properties such as title, description,
   * sort order, and enabled status based on defined constants.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('SEO')) {
      Registry::set('SEO', new SEOApp());
    }

    $this->app = Registry::get('SEO');
    $this->lang = Registry::get('Language');

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/google_links');

    $this->title = $this->app->getDef('module_admin_dashboard_googlelinks_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_googlelinks_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_GOOGLELINKS_APP_STATUS')) {
      $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_GOOGLELINKS_APP_SORT_ORDER;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_GOOGLELINKS_APP_STATUS == 'True');
    }
  }

  /**
   * Generates and returns the HTML output containing links to various Google services.
   *
   * @return string The HTML output encapsulating the Google services links.
   */
  public function getOutput()
  {
    $url_adsense = 'https://google.com/adsense';
    $url_analytics = 'https://www.google.com/analytics/';
    $url_adwords = 'https://adwords.google.com';
    $url_webmastertools = 'https://search.google.com/search-console';
    $url_alerts = 'https://www.google.com/alerts';
    $url_places = 'https://accounts.google.com/ServiceLogin?service=lbc&continue=https://www.google.com/local/add%3Fservice%3Dlbc';
    $url_merchant = 'https://www.google.com/merchants/default';
    $content_width = (int)MODULE_ADMIN_DASHBOARD_GOOGLELINKS_APP_CONTENT_WIDTH;

    $output = '<div class="col-md-' . $content_width . '">';
    $output .= '<div class="mt-1"></div>';
    $output .= '<table class="table table-sm table-hover">' .
      '<thead>' .
      '  <tr class="dataTableHeadingRow">' .
      '    <th width="20">&nbsp;</th>' .
      '    <th>' . $this->app->getDef('module_admin_dashboard_googlelinks_app_seo_title') . '</th>' .
      '  </tr>' .
      '</thead>' .
      '<tbody>';

    $output .= '<tr class="dataTableRow backgroundBlank">' .
      '    <td colspan="2"><a href="' . $url_analytics . '" target="_blank" rel="noreferrer">' . $this->app->getDef('module_admin_dashboard_googlelinks_app_analytics') . '</a></td>' .
      '  </tr>' .
      '  <tr class="dataTableRow backgroundBlank">' .
      '    <td colspan="2"><a href="' . $url_webmastertools . '" target="_blank" rel="noreferrer">' . $this->app->getDef('module_admin_dashboard_googlelinks_app_webmastertools') . '</a></td>' .
      '  </tr>' .
      '  <tr class="dataTableRow backgroundBlank">' .
      '    <td colspan="2"><a href="' . $url_places . '" target="_blank" rel="noreferrer">' . $this->app->getDef('module_admin_dashboard_googlelinks_app_places') . '</a></td>' .
      '  </tr>' .
      '  <tr class="dataTableRow backgroundBlank">' .
      '    <td colspan="2"><a href="' . $url_alerts . '" target="_blank" rel="noreferrer">' . $this->app->getDef('module_admin_dashboard_googlelinks_app_alerts') . '</a></td>' .
      '  </tr>' .
      '  <tr class="dataTableRow backgroundBlank">' .
      '    <td colspan="2"><a href="' . $url_adsense . '" target="_blank" rel="noreferrer">' . $this->app->getDef('module_admin_dashboard_googlelinks_app_adsense') . '</a></td>' .
      '  </tr>' .
      '  <tr class="dataTableRow backgroundBlank">' .
      '    <td colspan="2"><a href="' . $url_adwords . '" target="_blank" rel="noreferrer">' . $this->app->getDef('module_admin_dashboard_googlelinks_app_adwords') . '</a></td>' .
      '  </tr>' .
      '  <tr class="dataTableRow backgroundBlank">' .
      '    <td colspan="2"><a href="' . $url_merchant . '" target="_blank" rel="noreferrer">' . $this->app->getDef('module_admin_dashboard_googlelinks_app_merchant') . '</a></td>' .
      '  </tr>';

    $output .= '</tbody>';
    $output .= '</table>';
    $output .= '</div>';
    $output .= '<div class="mt-1"></div>';

    return $output;
  }

  /**
   * Installs the required configuration settings for the module in the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Enable Summary google Links SEO',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_GOOGLELINKS_APP_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this module ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Select the width to display',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_GOOGLELINKS_APP_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_GOOGLELINKS_APP_SORT_ORDER',
        'configuration_value' => '400',
        'configuration_description' => 'Sort order of display. Lowest is displayed first.',
        'configuration_group_id' => '6',
        'sort_order' => '90',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves an array of configuration keys used by the module.
   *
   * @return array An array of strings representing the configuration keys.
   */
  public function keys()
  {
    return ['MODULE_ADMIN_DASHBOARD_GOOGLELINKS_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_GOOGLELINKS_APP_CONTENT_WIDTH',
      'MODULE_ADMIN_DASHBOARD_GOOGLELINKS_APP_SORT_ORDER'
    ];
  }
}

