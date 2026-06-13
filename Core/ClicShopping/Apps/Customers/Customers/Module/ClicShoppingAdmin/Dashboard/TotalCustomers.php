<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Customers\Customers\Customers as CustomersApp;

class TotalCustomers extends \ClicShopping\OM\Modules\AdminDashboardAbstract
{
  public mixed $lang;
  public mixed $app;
  public $group;

  /**
   * Initializes the module by setting up required application components, loading language definitions,
   * and configuring properties like title, description, sort order, and enabled state based on module status.
   *
   * @return void
   */
  protected function init()
  {
    if (!Registry::exists('Customers')) {
      Registry::set('Customers', new CustomersApp());
    }

    $this->app = Registry::get('Customers');
    $this->lang = Registry::get('Language');

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/total_customers');

    $this->title = $this->app->getDef('module_admin_dashboard_total_customers_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_total_customers_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_TOTAL_CUSTOMERS_APP_STATUS')) {
      $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_TOTAL_CUSTOMERS_APP_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_TOTAL_CUSTOMERS_APP_STATUS == 'True');
    }
  }

  /**
   * Generates and returns the HTML and JavaScript output needed to render a bar chart
   * displaying the number of new customer accounts created over the last 15 days.
   *
   * @return string The HTML and JavaScript code necessary for rendering the bar chart.
   */
  public function getOutput(): string
  {
    $days = [];
    for ($i = 0; $i < 15; $i++) {
      $days[date('d', strtotime('-' . $i . ' days'))] = 0;
    }

    $Qorders = $this->app->db->query('select date_format(customers_info_date_account_created, "%d") as dateday,
                                        count(customers_info_id) as total
                                        from :table_customers_info
                                        where date_sub(curdate(), interval 15 day) <= customers_info_date_account_created
                                        group by dateday
                                      ');

    while ($Qorders->fetch()) {
      $days[$Qorders->value('dateday')] = $Qorders->value('total');
    }

    $days = array_reverse($days, true);

    $data_labels = json_encode(array_keys($days));
    $data = json_encode(array_values($days));

    $chart_label_link = HTML::link('index.php?A&Customers\Customers&Customers', $this->app->getDef('module_admin_dashboard_total_customers_app_chart_link'));

    $content_width = 'col-md-' . (int)MODULE_ADMIN_DASHBOARD_TOTAL_CUSTOMERS_APP_CONTENT_WIDTH;

    $output = <<<EOD
<div class="col-12 {$content_width} d-flex" style="padding-right:0.5rem; padding-top:0.5rem">
  <div class="card flex-fill h-215">
    <div class="card-block">
      <div class="card-body">
        <h6 class="card-title"><i class="bi bi-graph-up"></i> {$chart_label_link}</h6>
        <p class="card-text">
          <div class="col-md-12">
            <canvas id="d_total_customers_app" class="col-md-12" style="display: block; width:100%; height: 215px;"></canvas>
          </div>
        </p>
      </div>
    </div>
  </div>
</div>

<script>
var ctx = document.getElementById('d_total_customers_app');
var myChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: $data_labels,
        datasets: [{
            label: 'Customers',
            data: $data,
            backgroundColor: [                
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)',
                'rgba(235, 101, 26, 0.2)'
            ],
            borderColor: [
                'rgba(54, 162, 235, 0.2)'                                            
            ],
            borderWidth: 0
        }]
    },
    options: {
        maintainAspectRatio: true,
        legend: {
          display: false
        },        
        scales: {
            y: {
                beginAtZero: true
            }
        },
        xAxes: [{
          reverse: true,
          gridLines: {
            color: "rgba(0,0,0,0.05)"
          }
        }],
        yAxes: [{
          ticks: {
            stepSize: 1
          },
          display: true,
          borderDash: [5, 5],
          gridLines: {
            color: "rgba(0,0,0,0.050)",
            fontColor: "#fff"
          }
        }]
    }
});

function beforePrintHandler () {
    for (var id in Chart.instances) {
        Chart.instances[id].resize();
    }
}
</script>
EOD;
    return $output;
  }

  /**
   * Installs the required configuration settings in the database for the module.
   *
   * @return void
   */
  public function Install()
  {
    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to enable this Module ?',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_CUSTOMERS_APP_STATUS',
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_CUSTOMERS_APP_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_CUSTOMERS_APP_INTERVAL',
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_CUSTOMERS_APP_SORT_ORDER',
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
   * Retrieves the configuration keys for the dashboard module.
   *
   * @return array The list of configuration keys used by the module.
   */
  public function keys(): array
  {
    return [
      'MODULE_ADMIN_DASHBOARD_TOTAL_CUSTOMERS_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_TOTAL_CUSTOMERS_APP_CONTENT_WIDTH',
      'MODULE_ADMIN_DASHBOARD_TOTAL_CUSTOMERS_APP_INTERVAL',
      'MODULE_ADMIN_DASHBOARD_TOTAL_CUSTOMERS_APP_SORT_ORDER'
    ];
  }
}
