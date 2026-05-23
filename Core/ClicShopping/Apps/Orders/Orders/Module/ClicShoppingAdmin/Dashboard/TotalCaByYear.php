<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Module\ClicShoppingAdmin\Dashboard;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Orders\Orders\Orders as OrdersApp;

class TotalCaByYear extends \ClicShopping\OM\Modules\AdminDashboardAbstract
{
  public mixed $lang;
  public mixed $app;
  public $group;

  protected function init()
  {
    if (!Registry::exists('Orders')) {
      Registry::set('Orders', new OrdersApp());
    }

    $this->app = Registry::get('Orders');
    $this->lang = Registry::get('Language');

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/total_ca_by_year');

    $this->title = $this->app->getDef('module_admin_dashboard_total_ca_by_year_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_total_ca_by_year_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_TOTAL_CA_BY_YEAR_APP_STATUS')) {
      $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_TOTAL_CA_BY_YEAR_APP_SORT_ORDER;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_TOTAL_CA_BY_YEAR_APP_STATUS == 'True');
    }
  }

  public function getOutput(): string
  {
    $year = [];

    for ($i = 0; $i <= 4; $i++) {
      $year[date('Y', strtotime('-' . $i . ' year'))] = 0;
    }

    $Qorder = $this->app->db->prepare('select date_format(o.date_purchased, "%Y") as year,
                                                 sum(ot.value) as total
                                        from :table_orders  o,
                                             :table_orders_total ot
                                        where  o.orders_id = ot.orders_id
                                        and o.orders_status = 3
                                        and ot.class = :class
                                        group by year
                                       ');
    $Qorder->bindValue(':class', 'ST');
    $Qorder->execute();

    while ($Qorder->fetch()) {
      $year[$Qorder->value('year')] = $Qorder->value('total');
    }

    $days = array_reverse($year, true);

    $data_labels = json_encode(array_keys($days));
    $data = json_encode(array_values($days));

    $chart_label_link = HTML::link('index.php?A&Orders\Orders&Orders', $this->app->getDef('module_admin_dashboard_total_ca_by_year_app_chart_link'));

    $content_width = 'col-md-' . (int)MODULE_ADMIN_DASHBOARD_TOTAL_CA_BY_YEAR_APP_CONTENT_WIDTH;

    $output = <<<EOD
<div class="col-12 {$content_width} d-flex" style="padding-right:0.5rem; padding-top:0.5rem">
  <div class="card flex-fill h-215">
      <div class="card-block">
        <div class="card-body">
          <h6 class="card-title"><i class="bi bi-graph-up"></i></i> {$chart_label_link}</h6>
          <p class="card-text">
            <div class="col-md-12">
              <canvas id="d_total_ca_by_year" class="col-md-12" style="display: block; min-width:200px; height: 215px;"></canvas>
            </div>
          </p>
        </div>
      </div>
  </div>
</div>

<script>
var ctx = document.getElementById('d_total_ca_by_year');
var myChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: $data_labels,
        datasets: [{
            label: 'Turnover on 1 year',
              data: $data,
              backgroundColor: [                
                 'rgba(180, 180, 180, 0.2)',
                 'rgba(180, 180, 180, 0.2)',
                 'rgba(140, 140, 140, 0.2)',
                 'rgba(100, 100, 100, 0.2)',
                 'rgba(54, 162, 235, 0.2)'                                  
              ],
              borderWidth: 0
          }]
      },
      options: {
        maintainAspectRatio: true,
        responsive: true,
        legend: {
          display: false
        },
        scales: {
          x: {
            reverse: false,
            grid: {
              color: "rgba(0,0,0,0.05)"
            }
          },
          y: {
            ticks: {
              stepSize: 100
            },
            display: true,
            borderDash: [5, 5],
            grid: {
              color: "rgba(0,0,0,0.050)",
              borderColor: "#fff"
            },
            beginAtZero: true
          }
        }
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

  public function Install()
  {

    $this->app->db->save('configuration', [
        'configuration_title' => 'Do you want to enable this Module ?',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_CA_BY_YEAR_APP_STATUS',
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_CA_BY_YEAR_APP_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_CA_BY_YEAR_APP_SORT_ORDER',
        'configuration_value' => '30',
        'configuration_description' => 'Sort order of display. Lowest is displayed first.',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  public function keys(): array
  {
    return [
      'MODULE_ADMIN_DASHBOARD_TOTAL_CA_BY_YEAR_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_TOTAL_CA_BY_YEAR_APP_CONTENT_WIDTH',
      'MODULE_ADMIN_DASHBOARD_TOTAL_CA_BY_YEAR_APP_SORT_ORDER'
    ];
  }
}
