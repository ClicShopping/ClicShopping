<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\Apps\Configuration\ChatGpt\ChatGpt as ChatGptApp;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Domains\AdminDashboardAbstract;

class TotalToken extends AdminDashboardAbstract
{
  public mixed $lang;
  public mixed $app;
  public $group;

  /**
   * Initializes the application instance and its related properties for the dashboard module.
   *
   * This method checks and sets the registry for the ChatGpt application instance,
   * retrieves the corresponding language and definitions, and sets the title and
   * description properties for the dashboard module. Additionally, it configures
   * the module's enabled status and sort order if the necessary constants are defined.
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

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/total_token');

    $this->title = $this->app->getDef('module_admin_dashboard_total_gpt_token_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_total_gpt_token_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_TOTAL_GPT_TOKEN_APP_STATUS')) {
      $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_TOTAL_GPT_TOKEN_APP_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_TOTAL_GPT_TOKEN_APP_STATUS == 'True');
    }
  }

  /**
   * Generates and returns an HTML output containing a bar chart that displays GPT token usage
   * data for the last 30 days. This method retrieves data from the database, formats it into
   * a JSON structure and embeds the corresponding chart within a defined HTML structure.
   *
   * @return string The generated HTML output containing the chart for GPT token usage data.
   */
  public function getOutput(): string
  {
    $charts = TokenChartDataProvider::getChartsData();

    $chart_label_link = HTML::link('index.php?A&Configuration\ChatGpt&ChatGpt', $charts['daily_total_tokens']['title']);

    $content_width = 'col-md-' . (int)MODULE_ADMIN_DASHBOARD_TOTAL_GPT_TOKEN_APP_CONTENT_WIDTH;

    $chartConfig = htmlspecialchars(json_encode($charts['daily_total_tokens']['chart'], JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

    $output = <<<EOD
<div class="col-12 {$content_width} d-flex" style="padding-right:0.5rem; padding-top:0.5rem">
  <div class="card flex-fill h-215">
    <div class="card-block">
      <div class="card-body">
        <h6 class="card-title"><i class="bi bi-graph-up"></i> {$chart_label_link}</h6>
        <p class="card-text">
          <div class="col-md-12">
            <canvas id="d_total_gpt_token_app" class="col-md-12 chatgpt-token-chart" data-chart-config="{$chartConfig}" style="display: block; width:100%; height: 215px;"></canvas>
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
   * Installs the necessary configuration settings for the module in the database.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to enable this Module ?',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_GPT_TOKEN_APP_STATUS',
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_GPT_TOKEN_APP_CONTENT_WIDTH',
        'configuration_value' => '6',
        'configuration_description' => 'Select a number between 1 to 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Choose your analyse interval ?',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_GPT_TOKEN_APP_INTERVAL',
        'configuration_value' => '30 Day',
        'configuration_description' => 'Analyse interval',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'7 Day\', \'14 Day\', \'30 Day\', \'90 Day\', \'182 Day\', \'365 Day\'))',
        'date_added' => 'now()'
      ]
    );

    $this->app->db->save('configuration', [
        'configuration_title' => 'Sort Order',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_GPT_TOKEN_APP_SORT_ORDER',
        'configuration_value' => '45',
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
   * @return array An array of configuration keys used by the module.
   */
  public function keys(): array
  {
    return [
      'MODULE_ADMIN_DASHBOARD_TOTAL_GPT_TOKEN_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_TOTAL_GPT_TOKEN_APP_CONTENT_WIDTH',
      'MODULE_ADMIN_DASHBOARD_TOTAL_GPT_TOKEN_APP_INTERVAL',
      'MODULE_ADMIN_DASHBOARD_TOTAL_GPT_TOKEN_APP_SORT_ORDER'
    ];
  }
}
