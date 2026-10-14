<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\OM\Domains\AdminDashboardAbstract;

class CostEstimation extends AdminDashboardAbstract
{
  public mixed $app;
  public $group;

  /**
   * Initializes the module by setting up the required application instance,
   * language configurations, and loading module definitions. It also determines
   * the title, description, sort order, and enabled status of the module based on defined constants.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('ChatGpt')) {
      Registry::set('ChatGpt', new ChatGptApp());
    }

    $this->app = Registry::get('ChatGpt');

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/cost_estimation');

    $this->title = $this->app->getDef('module_admin_dashboard_total_cost_estimation_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_total_cost_estimation_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_TOTAL_COST_ESTIMATION_APP_STATUS')) {
      $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_TOTAL_COST_ESTIMATION_APP_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_TOTAL_COST_ESTIMATION_APP_STATUS == 'True');
    }
  }

  /**
   * Generates and returns the HTML and JavaScript output for a dashboard widget that displays
   * a bar chart of GPT token usage and costs over the past 12 months.
   *
   * The method retrieves token usage data from the database, calculates costs based on the
   * application price configuration, structures the data into JSON format for use in a JavaScript chart,
   * and generates the necessary HTML and chart configuration.
   *
   * @return string The complete HTML and JavaScript content for rendering the dashboard widget.
   */
  public function getOutput(): string
  {
    $charts = TokenChartDataProvider::getChartsData();

    $chart_label_link = $this->app->getDef('module_admin_dashboard_total_cost_estimation_app_chart_link');
    $content_width = 'col-md-' . (int)MODULE_ADMIN_DASHBOARD_TOTAL_COST_ESTIMATION_APP_CONTENT_WIDTH;

    $chartConfig = htmlspecialchars(json_encode($charts['cost_estimation']['chart'], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

    $output = <<<EOD
<div class="col-12 {$content_width} d-flex" style="padding-right:0.5rem; padding-top:0.5rem">
  <div class="card flex-fill h-215">
    <div class="card-block">
      <div class="card-body">
        <h6 class="card-title"><i class="bi bi-graph-up"></i> {$chart_label_link}</h6>
        <p class="card-text">
          <div class="col-md-12">
            <canvas id="d_total_cost_estimation_app" class="col-md-12 chatgpt-token-chart" data-chart-config="{$chartConfig}" style="display: block; width:100%; height: 215px;"></canvas>
          </div>
        </p>
      </div>
    </div>
  </div>
</div>

EOD;

    if (!defined('CHATGPT_TOKEN_CHARTS_JS')) {
      define('CHATGPT_TOKEN_CHARTS_JS', true);
      $output .= '<script defer src="' . htmlspecialchars($charts['assets']['script'], ENT_QUOTES, 'UTF-8') . '"></script>';
    }

    return $output;
  }


  /**
   * Installs the module by saving necessary configuration settings to the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to enable this Module ?',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_COST_ESTIMATION_APP_STATUS',
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_COST_ESTIMATION_APP_CONTENT_WIDTH',
        'configuration_value' => '6',
        'configuration_description' => 'Select a number between 1 to 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Sort Order',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_COST_ESTIMATION_APP_SORT_ORDER',
        'configuration_value' => '50',
        'configuration_description' => 'Sort order of display. Lowest is displayed first.',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  /**
   * Retrieves a list of configuration keys related to the dashboard total cost estimation module.
   *
   * @return array An array of configuration keys used by the total cost estimation module.
   */
  public function keys(): array
  {
    return [
      'MODULE_ADMIN_DASHBOARD_TOTAL_COST_ESTIMATION_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_TOTAL_COST_ESTIMATION_APP_CONTENT_WIDTH',
      'MODULE_ADMIN_DASHBOARD_TOTAL_COST_ESTIMATION_APP_SORT_ORDER'
    ];
  }
}
