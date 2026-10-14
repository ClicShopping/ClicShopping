<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\OM\Domains\AdminDashboardAbstract;

class CheckAPI extends AdminDashboardAbstract
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
  protected function init()
  {
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGptApp());
    }

    $this->app = Registry::get('ChatGpt');
    $this->lang = Registry::get('Language');

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/check_api');

    $this->title = $this->app->getDef('module_admin_dashboard_check_api_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_total_check_api_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_GPT_CHECK_API_APP_STATUS')) {
      $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_GPT_CHECK_API_APP_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_GPT_CHECK_API_APP_STATUS == 'True');
    }
  }

  /**
   * Generates and returns the dashboard output for the module.
   * If the API key for the ChatGPT application is not set, returns an alert message.
   *
   * @return string The generated output, including alert information if the API key is missing.
   */
  public function getOutput(): string
  {
    $output = '';

    $apiKey = Gpt::getProviderApiKey('openai')['api_key'];

    if (empty($apiKey)) {
      try {
        $link = HTML::link($this->app->link('Configuration\ChatGpt&Configure'), $this->app->getDef('module_admin_dashboard_check_api_app_link'));

        $contentWidth = defined('MODULE_ADMIN_DASHBOARD_GPT_CHECK_API_APP_CONTENT_WIDTH') ? (int) MODULE_ADMIN_DASHBOARD_GPT_CHECK_API_APP_CONTENT_WIDTH : 12;

        $output = '<div class="col-md-' . $contentWidth . '">';
        $output .= '<div class="alert alert-warning" role="alert">';
        $output .= $this->app->getDef('module_admin_dashboard_check_api_app_alert', ['gpt_link' => $link]);
        $output .= '</div>';
        $output .= '</div>';
      } catch (Exception $e) {
        error_log("Dashboard CheckAPI error: " . $e->getMessage());
        $output = '<div class="col-md-12"><div class="alert alert-danger">Erreur de configuration ChatGPT</div></div>';
      }
   }

    return $output;
  }

  /**
   * Installs the module by adding configuration settings to the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save(
      'configuration',
      [
        'configuration_title' => 'Do you want to enable this Module ?',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_GPT_CHECK_API_APP_STATUS',
        'configuration_value' => 'True',
        'configuration_description' => 'Do you want to enable this Module ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save(
      'configuration',
      [
        'configuration_title' => 'Select the width to display',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_GPT_CHECK_API_APP_CONTENT_WIDTH',
        'configuration_value' => '12',
        'configuration_description' => 'Select a number between 1 to 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save(
      'configuration',
      [
        'configuration_title' => 'Sort Order',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_GPT_CHECK_API_APP_SORT_ORDER',
        'configuration_value' => '2',
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
      'MODULE_ADMIN_DASHBOARD_GPT_CHECK_API_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_GPT_CHECK_API_APP_CONTENT_WIDTH',
      'MODULE_ADMIN_DASHBOARD_GPT_CHECK_API_APP_SORT_ORDER'
    ];
  }
}
