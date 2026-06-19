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
use ClicShopping\OM\Domains\AdminDashboardAbstract;

class TotalRevenue extends AdminDashboardAbstract
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

    $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/total_revenue');

    $this->title = $this->app->getDef('module_admin_dashboard_total_revenue_app_title');
    $this->description = $this->app->getDef('module_admin_dashboard_total_revenue_app_description');

    if (\defined('MODULE_ADMIN_DASHBOARD_TOTAL_REVENUE_APP_STATUS')) {
      $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_TOTAL_REVENUE_APP_SORT_ORDER ?? 0;
      $this->enabled = (MODULE_ADMIN_DASHBOARD_TOTAL_REVENUE_APP_STATUS == 'True');
    }
  }

  public function getOutput()
  {
    $month = [];
    $days = [];
    $plot_days = [];
    $plot_revenue = [];

    for ($i = 0; $i <= 12; $i++) {
      $month[date('M')] = 0;
    }

    $Qorder = $this->app->db->prepare("select date_format(o.date_purchased, '%b-%Y') as dateday,
                                                sum(ot.value) as total
                                        from :table_orders o,
                                              :table_orders_total ot
                                        where date_sub(curdate(), interval 11 month) <= o.date_purchased
                                        and (o.orders_status > 0 and o.orders_status <> 4)
                                        and o.orders_id = ot.orders_id
                                        and ot.class = 'ST'
                                        group by dateday
                                        order by date_purchased desc
                                        ");


    $Qorder->execute();

    while ($Qorder->fetch()) {
      $days[$Qorder->value('dateday')] = $Qorder->valueDecimal('total');
    }

    $days = array_reverse($days, true);

    foreach ($days as $d => $r) {
      $plot_days[] = $d;
      $plot_revenue[] = $r;
    }

    $data_labels = json_encode($plot_days);
    $data = json_encode($plot_revenue);

    $chart_label_link = HTML::link('index.php?A&Orders\Orders&Orders', $this->app->getDef('module_admin_dashboard_total_revenue_app_chart_link'));

    $content_width = 'col-md-' . (int)MODULE_ADMIN_DASHBOARD_TOTAL_REVENUE_APP_CONTENT_WIDTH;

    $output = <<<EOD
<div class="col-12 {$content_width} d-flex" style="padding-right:0.5rem; padding-top:0.5rem">
  <div class="card flex-fill h-215">
      <div class="card-block">
        <div class="card-body">
          <h6 class="card-title"><i class="bi bi-graph-up"></i> {$chart_label_link}</h6>
          <p class="card-text">
            <div class="col-md-12">
              <canvas id="TotalRevenue" class="col-md-12" style="display: block; min-width:200px; height: 215px;"></canvas>
            </div>
          </p>
        </div>
      </div>
  </div>
</div>

<script>
var ctx = document.getElementById('TotalRevenue');
var myChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: $data_labels,
        datasets: [{
            label: 'Turnover',
            data: $data,
            backgroundColor: [                
                'rgba(153, 102, 255, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(153, 102, 255, 0.2)',
                'rgba(153, 102, 255, 0.2)',                              
            ],
            borderColor: [
                'rgba(153, 102, 255, 1)'
            ],
            borderWidth: 0
        }]
    },
    options: {
      maintainAspectRatio: true,
      responsive: true,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          intersect: false
        },
        filler: {
          propagate: true
        }
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_REVENUE_APP_STATUS',
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_REVENUE_APP_CONTENT_WIDTH',
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
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_TOTAL_REVENUE_APP_SORT_ORDER',
        'configuration_value' => '40',
        'configuration_description' => 'Sort order of display. Lowest is displayed first.',
        'configuration_group_id' => '6',
        'sort_order' => '2',
        'set_function' => '',
        'date_added' => 'now()'
      ]
    );
  }

  public function keys()
  {
    return ['MODULE_ADMIN_DASHBOARD_TOTAL_REVENUE_APP_STATUS',
      'MODULE_ADMIN_DASHBOARD_TOTAL_REVENUE_APP_CONTENT_WIDTH',
      'MODULE_ADMIN_DASHBOARD_TOTAL_REVENUE_APP_SORT_ORDER'
    ];
  }
}
