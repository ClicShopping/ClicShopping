<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\DashboardData;
  use ClicShopping\OM\HTML;
  use ClicShopping\OM\Registry;

  $CLICSHOPPING_Ecommerce = Registry::get('Ecommerce');
  $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

  // ── Bootstrap ─────────────────────────────────────────────────────────────────
  $CLICSHOPPING_Language = Registry::get('Language');
  $languageId            = (int) $CLICSHOPPING_Language->getId();

  $dashboard    = new DashboardData();
  $kpis         = $dashboard->getKpis($languageId);
  $quadrants    = $dashboard->getQuadrantDistribution($languageId);
  $topProducts  = $dashboard->getTopProductsByScoreY($languageId, 10);
  $stockoutRisk = $dashboard->getStockoutRiskProducts($languageId, 0.7, 15);
  $velocity     = $dashboard->getVelocitySummary($languageId);

  // ── Helpers ────────────────────────────────────────────────────────────────────
  $totalProducts = max(1, array_sum($quadrants));
  $totalVelocity = max(1, array_sum($velocity));

  function scoreBadgeClass(float $s): string {
    if ($s >= 70) return 'bg-success';
    if ($s >= 30) return 'bg-warning text-dark';
    return 'bg-danger';
  }

  function quadrantBadgeClass(string $q): string {
    return match ($q) {
      'Q1'    => 'bg-success',
      'Q2'    => 'bg-primary',
      'Q3'    => 'bg-danger',
      'Q4'    => 'bg-warning text-dark',
      default => 'bg-secondary',
    };
  }

  $quadrantMeta = [
    'Q1'             => ['label' => 'Q1 Scaling',       'desc' => 'High quality · High commercial', 'bg' => 'bg-success'],
    'Q2'             => ['label' => 'Q2 Acquisition',   'desc' => 'High quality · Low commercial',  'bg' => 'bg-primary'],
    'Q3'             => ['label' => 'Q3 Rework/Kill',   'desc' => 'Low quality · Low commercial',   'bg' => 'bg-danger'],
    'Q4'             => ['label' => 'Q4 Optimization',  'desc' => 'Low quality · High commercial',  'bg' => 'bg-warning'],
    'Q_intermediate' => ['label' => 'Monitoring',       'desc' => 'Transition zone',                'bg' => 'bg-secondary'],
  ];
?>

  <style>
      .kpi-card { border-left: 4px solid; }
      .table-middle td { vertical-align: middle; }
      .text-tiny { font-size: 0.7rem; }
  </style>

<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span class="col-md-1 logoHeading">
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/categorie.gif', $CLICSHOPPING_Ecommerce->getDef('heading_configuration'), '40', '40'); ?>
          </span>
          <span class="col-md-3 pageHeading">
            <?php //echo '&nbsp;' . $CLICSHOPPING_Ecommerce->getDef('heading_configuration'); ?> CockpitAI — Strategic Dashboard
          </span>
          <span class="col-md-8 text-end">
                <?php echo number_format($kpis['total_analyses']); ?> analyses &nbsp;·&nbsp;
                Last update: <?php echo $kpis['last_analysis'] ? htmlspecialchars(substr($kpis['last_analysis'], 0, 16)) : 'Never'; ?>
                &nbsp;·&nbsp; Language # <?php echo $languageId; ?>
          </span>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3"></div>

  <div class="row">
    <div class="container-fluid px-4">

      <div class="row mb-4">
        <div class="col-6 col-md-4 col-lg-2 mb-3">
          <div class="card h-100 kpi-card border-primary shadow-sm">
            <div class="card-body p-3">
              <h6 class="text-muted text-uppercase small fw-bold">Analyzed</h6>
              <h3 class="fw-bold mb-0"><?= number_format($kpis['total_products']) ?></h3>
              <small class="text-muted"><?= number_format($kpis['total_analyses']) ?> runs</small>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2 mb-3">
          <div class="card h-100 kpi-card border-success shadow-sm">
            <div class="card-body p-3">
              <h6 class="text-muted text-uppercase small fw-bold">Avg Score X</h6>
              <h3 class="fw-bold mb-0 text-success"><?= $kpis['avg_score_x'] ?></h3>
              <small class="text-muted">Quality</small>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2 mb-3">
          <div class="card h-100 kpi-card border-warning shadow-sm">
            <div class="card-body p-3">
              <h6 class="text-muted text-uppercase small fw-bold">Avg Score Y</h6>
              <h3 class="fw-bold mb-0 text-warning"><?= $kpis['avg_score_y'] ?></h3>
              <small class="text-muted">Commercial</small>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2 mb-3">
          <div class="card h-100 kpi-card border-success shadow-sm">
            <div class="card-body p-3">
              <h6 class="text-muted text-uppercase small fw-bold">Stars (Q1)</h6>
              <h3 class="fw-bold mb-0"><?= $quadrants['Q1'] ?></h3>
              <small class="text-muted">High X + High Y</small>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2 mb-3">
          <div class="card h-100 kpi-card border-danger shadow-sm">
            <div class="card-body p-3">
              <h6 class="text-muted text-uppercase small fw-bold">At Risk (Q3)</h6>
              <h3 class="fw-bold mb-0 text-danger"><?= $quadrants['Q3'] ?></h3>
              <small class="text-muted">Rework/Kill</small>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2 mb-3">
          <div class="card h-100 kpi-card border-danger shadow-sm">
            <div class="card-body p-3">
              <h6 class="text-muted text-uppercase small fw-bold">Stockout Risk</h6>
              <h3 class="fw-bold mb-0 text-danger"><?= count($stockoutRisk) ?></h3>
              <small class="text-muted">&gt;70% Prob.</small>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-6 mb-4">
          <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
              <h6 class="m-0 fw-bold text-uppercase small">Quadrant Distribution</h6>
              <span class="badge rounded-pill bg-light text-dark border">
        <?= $totalProducts ?> products
      </span>
            </div>

            <div class="card-body">
              <?php foreach ($quadrantMeta as $code => $meta):
                $count = $quadrants[$code] ?? 0;
                $pct   = $totalProducts > 0 ? round($count / $totalProducts * 100) : 0;
                ?>
                <div class="mb-3">
                  <div class="d-flex justify-content-between mb-1">
                    <div>
                      <span class="fw-bold small d-block"><?= $meta['label'] ?></span>
                      <span class="text-muted small"><?= $meta['desc'] ?></span>
                    </div>
                    <span class="small fw-bold">
                      <?= $pct ?>% (<?= $count ?>)
                    </span>
                  </div>
                  <div class="progress" style="height: 12px;">
                    <div class="progress-bar <?= $meta['bg'] ?>"
                         role="progressbar"
                         style="width: <?= max($pct, $count > 0 ? 5 : 0) ?>%">
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>


        <div class="col-lg-6 mb-4">
          <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
              <h6 class="m-0 fw-bold text-uppercase small">Velocity Distribution</h6>
              <span class="badge rounded-pill bg-light text-dark border">
        <?= $totalVelocity ?> products
      </span>
            </div>

            <div class="card-body">
              <div class="row">
                <?php
                  $velDefs = [
                    'fast'    => ['Fast-moving', 'bg-success'],
                    'slow'    => ['Slow-moving', 'bg-warning'],
                    'none'    => ['No sales',    'bg-danger'],
                    'no_data' => ['No data',     'bg-secondary'],
                  ];

                  foreach ($velDefs as $key => [$label, $bgCls]):
                    $count = $velocity[$key] ?? 0;
                    $pct   = $totalVelocity > 0 ? round($count / $totalVelocity * 100) : 0;

                    $borderColor = match ($key) {
                      'fast'    => '#28a745',
                      'slow'    => '#ffc107',
                      'none'    => '#dc3545',
                      default   => '#6c757d',
                    };
                    ?>
                    <div class="col-6 mb-3">
                      <div class="p-2 border rounded"
                           style="border-left: 4px solid <?= $borderColor ?>;">

                        <div class="text-muted small fw-bold"><?= $label ?></div>
                        <div class="h4 fw-bold mb-0"><?= $count ?></div>
                        <div class="small text-muted mb-2"><?= $pct ?>%</div>

                        <div class="progress" style="height: 4px;">
                          <div class="progress-bar <?= $bgCls ?>"
                               role="progressbar"
                               style="width: <?= $pct ?>%">
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <h6 class="m-0 fw-bold text-uppercase small text-primary">
            Top 10 Products — Commercial Performance (Y)
          </h6>
        </div>

        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="bg-light">
            <tr class="small text-muted text-uppercase">
              <th class="border-0">#</th>
              <th class="border-0">Product</th>
              <th class="border-0 text-center">Score X</th>
              <th class="border-0 text-center">Score Y</th>
              <th class="border-0">Quadrant</th>
              <th class="border-0">Last analysis</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($topProducts)): ?>
              <tr>
                <td colspan="6" class="text-center py-4 text-muted">
                  No analyses yet.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($topProducts as $i => $p): ?>
                <tr>
                  <td>
                <span class="badge bg-light text-dark border rounded-circle d-inline-flex align-items-center justify-content-center"
                      style="width:25px;height:25px;">
                  <?= $i + 1 ?>
                </span>
                  </td>

                  <td>
                    <a href="?page=products&pID=<?= (int)$p['product_id'] ?>"
                       class="fw-bold text-dark text-decoration-none">
                      <?= HTML::outputProtected($p['product_name']) ?>
                    </a>
                    <div class="small text-muted">
                      ID: #<?= (int)$p['product_id'] ?>
                    </div>
                  </td>

                  <td class="text-center">
                  <span class="badge rounded-pill <?= scoreBadgeClass($p['score_x']) ?> px-3">
                    <?= $p['score_x'] ?>
                  </span>
                  </td>

                  <td class="text-center">
                <span class="badge rounded-pill <?= scoreBadgeClass($p['score_y']) ?> px-3">
                  <?= $p['score_y'] ?>
                </span>
                  </td>

                  <td>
                <span class="badge <?= quadrantBadgeClass($p['quadrant']) ?>">
                  <?= htmlspecialchars($quadrantMeta[$p['quadrant']]['label'] ?? $p['quadrant']) ?>
                </span>
                  </td>

                  <td class="small text-muted">
                    <?= htmlspecialchars(substr($p['analysis_date'], 0, 10)) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card shadow-sm mb-5 border-danger">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <h6 class="m-0 font-weight-bold text-uppercase small text-danger">Stockout Risk — Probability > 70%</h6>
          <span class="badge badge-danger"><?= count($stockoutRisk) ?> Urgent</span>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0 table-middle">
            <thead class="bg-light">
            <tr class="small text-muted text-uppercase">
              <th class="border-0">Product</th>
              <th class="border-0" style="width: 30%;">Stockout Risk</th>
              <th class="border-0">Velocity</th>
              <th class="border-0">Safety Stock</th>
              <th class="border-0">Score Y</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($stockoutRisk)): ?>
              <tr><td colspan="5" class="text-center py-4 text-success font-weight-bold">✓ No high-risk products — good inventory health.</td></tr>
            <?php else: ?>
              <?php foreach ($stockoutRisk as $p):
                $prob    = (float) $p['stockout_probability'];
                $probPct = round($prob * 100, 1);
                $bgBar   = $prob >= 0.9 ? 'bg-danger' : 'bg-warning';
                $vel     = $p['stock_velocity'];
                $velStr  = $vel === null ? '—' : ($vel >= 2.0 ? '⚡ ' . $vel : (string)$vel);
                ?>
                <tr>
                  <td>
                    <a href="?page=products&pID=<?= (int)$p['product_id'] ?>" class="text-dark font-weight-bold"><?= HTML::outputProtected($p['product_name']) ?></a>
                    <div class="text-tiny text-muted">ID: #<?= (int)$p['product_id'] ?></div>
                  </td>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="progress flex-grow-1 mr-2" style="height: 8px;">
                        <div class="progress-bar <?= $bgBar ?>" style="width: <?= $probPct ?>%"></div>
                      </div>
                      <span class="small font-weight-bold <?= $prob >= 0.9 ? 'text-danger' : 'text-warning' ?>"><?= $probPct ?>%</span>
                    </div>
                  </td>
                  <td class="font-weight-bold small"><?= htmlspecialchars($velStr) ?></td>
                  <td><?= $p['safety_stock'] !== null ? $p['safety_stock'] . ' u' : '—' ?></td>
                  <td><span class="badge badge-pill <?= scoreBadgeClass($p['score_y']) ?>"><?= $p['score_y'] ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="card-footer bg-light small text-muted">
          ⚡ Fast-moving products with high stockout risk should be replenished immediately. Safety stock based on 90-day demand.
        </div>
      </div>
    </div>
  </div>
  <div class="py-4"></div>
