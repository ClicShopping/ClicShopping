<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

use ClicShopping\Apps\Tools\MCP\Classes\ClicShoppingAdmin\MCPConnector;

$CLICSHOPPING_MCP = Registry::get('MCP');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/migration.png', $CLICSHOPPING_MCP->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_MCP->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
          <?php echo HTML::button($CLICSHOPPING_MCP->getDef('button_back'), null, $CLICSHOPPING_MCP->link('MCP'), 'primary'); ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="mt-1"></div>

  <div class="mt-1"></div>
  <div class="mt-1"></div>
  <style>
      #performanceChart {
          display: block;
          width: 100% !important;
          height: 200px !important;
          background-color: rgba(0, 0, 0, 0.05); /* test visuel */
      }
  </style>
  <div class="card mb-3">
    <div class="card-header">
      <i class="bi bi-graph-up-arrow"></i> MCP Performance Metrics
      <div class="float-right">
        <select id="mcpServer" class="form-select form-select-sm d-inline-block w-auto me-2">
          <option value="all"><?php echo $CLICSHOPPING_MCP->getDef('text_all_servers'); ?></option>
          <?php
            // Load all active MCP servers
            $db = Registry::get('Db');
            $Qservers = $db->prepare('SELECT mcp_id, 
                                             username, 
                                             server_host, 
                                             server_port 
                                       FROM :table_mcp 
                                       WHERE status = 1 
                                       ORDER BY mcp_id
                                       ');
            $Qservers->execute();
            
            while ($Qservers->fetch()) {
              $mcpId = $Qservers->valueInt('mcp_id');
              $username = $Qservers->value('username');
              $serverHost = $Qservers->value('server_host');
              $serverPort = $Qservers->valueInt('server_port');
              $serverLabel = $username . ' (' . $serverHost . ':' . $serverPort . ')';
              echo '<option value="' . $mcpId . '">' . HTML::outputProtected($serverLabel) . '</option>';
            }
          ?>
        </select>
        <select id="timeRange" class="form-select form-select-sm d-inline-block w-auto">
          <option value="hour"><?php echo $CLICSHOPPING_MCP->getDef('text_last_hour'); ?></option>
          <option value="day" selected><?php echo $CLICSHOPPING_MCP->getDef('text_last_24:hour'); ?></option>
          <option value="week"><?php echo $CLICSHOPPING_MCP->getDef('text_last_week'); ?></option>
          <option value="month"><?php echo $CLICSHOPPING_MCP->getDef('text_last_month'); ?></option>
        </select>
        <div class="d-inline-block ms-2 align-middle">
          <input id="thrError" type="number" min="0" max="100" step="1" value="20" class="form-control form-control-sm d-inline-block w-auto" placeholder="<?php echo $CLICSHOPPING_MCP->getDef('text_error_rate'); ?>" title="<?php echo $CLICSHOPPING_MCP->getDef('text_error_rate'); ?>" />
          <input id="thrLatency" type="number" min="0" step="10" value="1000" class="form-control form-control-sm d-inline-block w-auto" placeholder="<?php echo $CLICSHOPPING_MCP->getDef('text_error_latency'); ?>" title="<?php echo $CLICSHOPPING_MCP->getDef('text_error_latency'); ?>" />
          <input id="thrDowntime" type="number" min="0" step="10" value="300" class="form-control form-control-sm d-inline-block w-auto" placeholder="<?php echo $CLICSHOPPING_MCP->getDef('text_error_downtime'); ?>" title="<?php echo $CLICSHOPPING_MCP->getDef('text_error_downtime'); ?>" />
          <button id="applyThresholds" class="btn btn-sm btn-outline-primary"><?php echo $CLICSHOPPING_MCP->getDef('text_apply'); ?></button>
        </div>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-4">
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title"><?php echo $CLICSHOPPING_MCP->getDef('text_current_performance'); ?></h5>
              <div id="performanceMetrics">
                <div class="d-flex justify-content-between mb-2">
                  <span><?php echo $CLICSHOPPING_MCP->getDef('text_request_rate'); ?></span>
                  <span id="requestRate"><?php echo $CLICSHOPPING_MCP->getDef('text_loading'); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span><?php echo $CLICSHOPPING_MCP->getDef('text_average_latency'); ?></span>
                  <span id="avgLatency"><?php echo $CLICSHOPPING_MCP->getDef('text_loading'); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span><?php echo $CLICSHOPPING_MCP->getDef('text_error_rate'); ?></span>
                  <span id="errorRate"><?php echo $CLICSHOPPING_MCP->getDef('text_loading'); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                  <span><?php echo $CLICSHOPPING_MCP->getDef('text_uptime'); ?></span>
                  <span id="uptime"><?php echo $CLICSHOPPING_MCP->getDef('text_loading'); ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body">
              <h5 class="card-title"><?php echo $CLICSHOPPING_MCP->getDef('text_performance_trends'); ?></h5>
              <div id="performanceTrends">
                <div class="trend-item mb-2">
                  <label><?php echo $CLICSHOPPING_MCP->getDef('text_latency_trend'); ?></label>
                  <div class="progress">
                    <div id="latencyTrend" class="progress-bar" role="progressbar" style="width: 0%"></div>
                  </div>
                </div>
                <div class="trend-item mb-2">
                  <label><?php echo $CLICSHOPPING_MCP->getDef('text_error_rate_trend'); ?></label>
                  <div class="progress">
                    <div id="errorTrend" class="progress-bar" role="progressbar" style="width: 0%"></div>
                  </div>
                </div>
                <div class="trend-item">
                  <label><?php echo $CLICSHOPPING_MCP->getDef('text_request_rate_trend'); ?></label>
                  <div class="progress">
                    <div id="requestTrend" class="progress-bar" role="progressbar" style="width: 0%"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Statistics Section -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title"><?php echo $CLICSHOPPING_MCP->getDef('text_performance_statistics'); ?></h5>
              <div id="performanceStats" class="row">
                <div class="col-md-3">
                  <div class="text-center">
                    <h6><?php echo $CLICSHOPPING_MCP->getDef('text_avg_latency'); ?></h6>
                    <span id="avgLatencyStat" class="h4 text-primary">-</span>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="text-center">
                    <h6><?php echo $CLICSHOPPING_MCP->getDef('text_max_latency'); ?></h6>
                    <span id="maxLatencyStat" class="h4 text-warning">-</span>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="text-center">
                    <h6><?php echo $CLICSHOPPING_MCP->getDef('text_total_requests'); ?></h6>
                    <span id="totalRequestsStat" class="h4 text-info">-</span>
                  </div>
                </div>
                <div class="col-md-3">
                  <div class="text-center">
                    <h6><?php echo $CLICSHOPPING_MCP->getDef('text_data_points'); ?>Data Points</h6>
                    <span id="dataPointsStat" class="h4 text-secondary">-</span>
                  </div>
                </div>
              </div>
              <div class="mt-3 text-end">
                <button id="exportData" class="btn btn-sm btn-outline-success">
                  <i class="bi bi-download"></i> <?php echo $CLICSHOPPING_MCP->getDef('text_export_data'); ?>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Chart Section -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <canvas id="performanceChart" height="200"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Recommendations -->
      <div class="row">
        <div class="col-12">
          <div class="card">
            <div class="card-body">
              <h5 class="card-title"><?php echo $CLICSHOPPING_MCP->getDef('text_performance_recommendations'); ?></h5>
              <div id="recommendations" class="list-group">
                <div class="text-center"><?php echo $CLICSHOPPING_MCP->getDef('text_loading_recommendations'); ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="py-3"></div>

<?php
  $ajax_get_performance_data_url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/MCP/GetPerformanceData.php';
  $ajax_export_url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path','ClicShoppingAdmin') . 'ajax/MCP/ExportPerformanceData.php';
  $mcp_token = MCPConnector::getInstance()->getSessionToken();
?>
<script defer>
  var GetPerformanceData = "<?php echo $ajax_get_performance_data_url; ?>";
  var ExportPerformanceData = "<?php echo $ajax_export_url; ?>";
  var mcpToken = <?php echo json_encode($mcp_token); ?>;
</script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/MCP/performance_monitor.js'); ?>"></script>
