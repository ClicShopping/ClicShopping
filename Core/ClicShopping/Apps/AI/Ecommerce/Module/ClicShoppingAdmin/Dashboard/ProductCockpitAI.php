<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
  namespace ClicShopping\Apps\AI\Ecommerce\Module\ClicShoppingAdmin\Dashboard;

  use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\DashboardData;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTML;
  use ClicShopping\OM\Registry;

  class ProductCockpitAI extends \ClicShopping\OM\Modules\AdminDashboardAbstract
  {
  public mixed $lang;
  public mixed $app;
  public $group;

  public function getOutput()
  {
    $dash     = new DashboardData();
    $kpis     = $dash->getKpis($this->lang->getId());
    $quads    = $dash->getQuadrantDistribution($this->lang->getId());
    $velocity = $dash->getVelocitySummary($this->lang->getId());
    $stockout = $dash->getStockoutRiskProducts($this->lang->getId(), 0.7, 5);

    $totalP    = max(1, array_sum($quads));
    $totalV    = max(1, array_sum($velocity));
    $avgX      = (float) ($kpis['avg_score_x'] ?? 0);
    $avgY      = (float) ($kpis['avg_score_y'] ?? 0);
    $stockoutN = count($stockout);

    // JSON arrays for Chart.js
    $quadLabels = json_encode(['Q1 Scaling', 'Q2 Acquisition', 'Q3 Rework/Kill', 'Q4 Optimization', 'Monitoring']);
    $quadValues = json_encode([$quads['Q1'], $quads['Q2'], $quads['Q3'], $quads['Q4'], $quads['Q_intermediate']]);
    $quadColors = json_encode(['#16a34a', '#2563eb', '#dc2626', '#d97706', '#9ca3af']);

    $velLabels  = json_encode(['Fast-moving ≥2.0', 'Slow-moving', 'No sales', 'No data']);
    $velValues  = json_encode([$velocity['fast'], $velocity['slow'], $velocity['none'], $velocity['no_data']]);
    $velColors  = json_encode(['#16a34a', '#d97706', '#dc2626', '#9ca3af']);

    // Score color helper (PHP side for KPIs)
    $xColor = $avgX >= 70 ? '#16a34a' : ($avgX >= 30 ? '#d97706' : '#dc2626');
    $yColor = $avgY >= 70 ? '#16a34a' : ($avgY >= 30 ? '#d97706' : '#dc2626');

    $content_width = 'col-md-' . (int)MODULE_ADMIN_DASHBOARD_PRODUCT_COCKPIT_AI_CONTENT_WIDTH;

    $dashUrl = HTML::sanitize(CLICSHOPPING::link('ClicShoppingAdmin/index.php?A&AI\Ecommerce&DashboardProductCockpitAI'));

    $stockColor = $stockoutN > 0 ? '#dc2626' : '#16a34a';

    ob_start(); ?>
<span class="<?= $content_width ?>">
<div class="card border-0 shadow-sm" style="font-family:'Segoe UI',sans-serif;color:#1a1f2e;">

  <!-- Header -->
  <div class="card-header text-white d-flex flex-wrap justify-content-between align-items-center gap-2"
       style="background:#1a1f2e;">
    <strong>Product CockpitAI — Overview</strong>
    <a href="<?= $dashUrl ?>" class="small text-decoration-none" style="color:#93c5fd;">
      Full dashboard &rsaquo;
    </a>
  </div>

  <div class="card-body">

    <!-- KPI row -->
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <div class="card h-100 text-center border-0" style="background:#f8f9fc;border-top:3px solid #2563eb !important;">
          <div class="card-body p-2">
            <div class="text-uppercase fw-bold text-muted" style="font-size:9px;letter-spacing:.5px;">Products</div>
            <div class="fw-bolder lh-1 my-1" style="font-size:22px;"><?= number_format($kpis['total_products']) ?></div>
            <div class="text-muted" style="font-size:9px;"><?= number_format($kpis['total_analyses']) ?> analyses</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card h-100 text-center border-0" style="background:#f8f9fc;border-top:3px solid <?= $xColor ?> !important;">
          <div class="card-body p-2">
            <div class="text-uppercase fw-bold text-muted" style="font-size:9px;letter-spacing:.5px;">Avg Score X</div>
            <div class="fw-bolder lh-1 my-1" style="font-size:22px;color:<?= $xColor ?>;"><?= $avgX ?></div>
            <div class="text-muted" style="font-size:9px;">Quality</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card h-100 text-center border-0" style="background:#f8f9fc;border-top:3px solid <?= $yColor ?> !important;">
          <div class="card-body p-2">
            <div class="text-uppercase fw-bold text-muted" style="font-size:9px;letter-spacing:.5px;">Avg Score Y</div>
            <div class="fw-bolder lh-1 my-1" style="font-size:22px;color:<?= $yColor ?>;"><?= $avgY ?></div>
            <div class="text-muted" style="font-size:9px;">Commercial</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card h-100 text-center border-0" style="background:#f8f9fc;border-top:3px solid <?= $stockColor ?> !important;">
          <div class="card-body p-2">
            <div class="text-uppercase fw-bold text-muted" style="font-size:9px;letter-spacing:.5px;">Stockout &gt;70%</div>
            <div class="fw-bolder lh-1 my-1" style="font-size:22px;color:<?= $stockColor ?>;"><?= $stockoutN ?></div>
            <div class="text-muted" style="font-size:9px;">urgent reorder</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Two charts side by side (stack on mobile/tablet) -->
    <div class="row g-3">
      <div class="col-12 col-lg-6">
        <div class="card h-100 border-0" style="background:#f8f9fc;">
          <div class="card-body p-2">
            <div class="text-uppercase fw-bold text-muted mb-2" style="font-size:10px;letter-spacing:.5px;">
              Quadrant distribution — <?= $totalP ?> products
            </div>
            <div style="position:relative;height:180px;">
              <canvas id="cai-dash-quads"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card h-100 border-0" style="background:#f8f9fc;">
          <div class="card-body p-2">
            <div class="text-uppercase fw-bold text-muted mb-2" style="font-size:10px;letter-spacing:.5px;">
              Velocity distribution — <?= $totalV ?> products
            </div>
            <div style="position:relative;height:180px;">
              <canvas id="cai-dash-vel"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 small text-muted">
    Last analysis: <?= $kpis['last_analysis'] ? htmlspecialchars(substr($kpis['last_analysis'], 0, 10)) : 'never' ?>
  </div>
</div>

<script>
(function() {
  'use strict';

  // Chart.js must already be loaded by the admin page
  if (typeof Chart === 'undefined') {
    console.warn('CockpitAI dashboard: Chart.js not loaded');
    return;
  }

  // Shared doughnut options
  var doughnutOpts = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '60%',
    plugins: {
      legend: {
        position: 'right',
        labels: { font: { size: 10 }, boxWidth: 12, padding: 8 }
      },
      tooltip: {
        callbacks: {
          label: function(ctx) {
            var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
            var pct   = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
            return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
          }
        }
      }
    }
  };

  // Quadrant doughnut
  new Chart(document.getElementById('cai-dash-quads'), {
    type: 'doughnut',
    data: {
      labels:   <?= $quadLabels ?>,
      datasets: [{
        data:            <?= $quadValues ?>,
        backgroundColor: <?= $quadColors ?>,
        borderWidth: 2,
        borderColor: '#fff',
        hoverOffset: 4
      }]
    },
    options: doughnutOpts
  });

  // Velocity doughnut
  new Chart(document.getElementById('cai-dash-vel'), {
    type: 'doughnut',
    data: {
      labels:   <?= $velLabels ?>,
      datasets: [{
        data:            <?= $velValues ?>,
        backgroundColor: <?= $velColors ?>,
        borderWidth: 2,
        borderColor: '#fff',
        hoverOffset: 4
      }]
    },
    options: doughnutOpts
  });

})();
</script>
</span>
<?php
    return ob_get_clean();
  }

    public function install()
    {
      $this->app->db->save('configuration', [
        'configuration_title' => 'Enable CockpitAI module',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_PRODUCT_COCKPIT_AI_STATUS',
        'configuration_value' => 'True',
          'configuration_description' => 'Do you want to enable this Module ?',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_boolean_value(array(\'True\', \'False\'))',
        'date_added' => 'now()'
      ]);

      $this->app->db->save('configuration', [
        'configuration_title' => 'Display width',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_PRODUCT_COCKPIT_AI_CONTENT_WIDTH',
        'configuration_value' => '12',
          'configuration_description' => 'Select a number between 1 to 12',
        'configuration_group_id' => '6',
        'sort_order' => '1',
        'set_function' => 'clic_cfg_set_content_module_width_pull_down',
        'date_added' => 'now()'
      ]);

      $this->app->db->save('configuration', [
        'configuration_title' => 'Sort order',
        'configuration_key' => 'MODULE_ADMIN_DASHBOARD_PRODUCT_COCKPIT_AI_SORT_ORDER',
        'configuration_value' => '600',
          'configuration_description' => 'Sort order of display. Lowest is displayed first.',
        'configuration_group_id' => '6',
        'sort_order' => '2',
          'set_function' => '',
        'date_added' => 'now()'
      ]);
    }

    public function keys()
    {
      return [
        'MODULE_ADMIN_DASHBOARD_PRODUCT_COCKPIT_AI_STATUS',
        'MODULE_ADMIN_DASHBOARD_PRODUCT_COCKPIT_AI_CONTENT_WIDTH',
        'MODULE_ADMIN_DASHBOARD_PRODUCT_COCKPIT_AI_SORT_ORDER'
      ];
    }

    protected function init()
    {
      if (!Registry::exists('Ecommerce')) {
        Registry::set('Ecommerce', new EcommerceApp());
      }

      $this->app = Registry::get('Ecommerce');
      $this->lang = Registry::get('Language');

      $this->app->loadDefinitions('Module/ClicShoppingAdmin/Dashboard/product_cockpit_ai');

      $this->title = 'CockpitAI';
      $this->description = 'AI product intelligence dashboard';

      if (\defined('MODULE_ADMIN_DASHBOARD_PRODUCT_COCKPIT_AI_STATUS')) {
        $this->sort_order = (int)MODULE_ADMIN_DASHBOARD_PRODUCT_COCKPIT_AI_SORT_ORDER;
        $this->enabled = (MODULE_ADMIN_DASHBOARD_PRODUCT_COCKPIT_AI_STATUS == 'True');
      }
    }
}
