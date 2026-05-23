<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_MCP = Registry::get('MCP');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span class="col-md-1 logoHeading">
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/migration.png', $CLICSHOPPING_MCP->getDef('heading_title'), '40', '40'); ?>
          </span>
          <span class="col-md-4 pageHeading">
            <?php echo '&nbsp;' . $CLICSHOPPING_MCP->getDef('heading_title'); ?>
          </span>
          <span class="col-md-7 text-end">
            <?php echo HTML::button($CLICSHOPPING_MCP->getDef('button_back'), null, $CLICSHOPPING_MCP->link('MCP'), 'primary'); ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="py-3"></div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <span id="connectionStatus" class="badge bg-secondary"><?php echo $CLICSHOPPING_MCP->getDef('text_disconnected'); ?></span>
        <span id="healthStatus" class="badge bg-info"><?php echo $CLICSHOPPING_MCP->getDef('text_unknown'); ?></span>
        <span id="serverInfo" class="badge bg-light text-dark ms-2" style="display:none;"></span>
      </div>
      <div>
        <select id="mcpServer" class="form-select form-select-sm d-inline-block w-auto">
          <option value="all"><?php echo $CLICSHOPPING_MCP->getDef('text_all_servers'); ?></option>
          <?php
            // Load all active MCP servers
            $db = Registry::get('Db');
            $Qservers = $db->prepare('SELECT mcp_id, username, server_host, server_port 
                                       FROM :table_mcp 
                                       WHERE status = 1 
                                       ORDER BY mcp_id');
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
      </div>
    </div>
    <div class="card-body">
      <!-- Server Information Row -->
      <div class="row mb-3">
        <div class="col-12">
          <div class="alert alert-info mb-0" id="serverInfoCard" style="display:none;">
            <div class="row">
              <div class="col-md-3">
                <strong>Server:</strong> <span id="serverName">-</span>
              </div>
              <div class="col-md-3">
                <strong>Host:</strong> <span id="serverHost">-</span>
              </div>
              <div class="col-md-3">
                <strong>Port:</strong> <span id="serverPort">-</span>
              </div>
              <div class="col-md-3">
                <strong>SSL:</strong> <span id="serverSSL">-</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="row">
        <div class="col-md-4">
          <div class="card border-primary">
            <div class="card-body">
              <h6 class="card-title"><?php echo $CLICSHOPPING_MCP->getDef('text_configuration'); ?></h6>
              <div id="configStatus">
                <div class="text-muted"><?php echo $CLICSHOPPING_MCP->getDef('text_waiting_data'); ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-success">
            <div class="card-body">
              <h6 class="card-title"><?php echo $CLICSHOPPING_MCP->getDef('text_connectivity'); ?></h6>
              <div id="connectivityStatus">
                <div class="text-muted"><?php echo $CLICSHOPPING_MCP->getDef('text_waiting_data'); ?></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-warning">
            <div class="card-body">
              <!--<h6 class="card-title">Performance</h6>-->
              <div id="performanceStatus">
                <div class="text-muted"><?php echo $CLICSHOPPING_MCP->getDef('text_waiting_data'); ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row mt-3">
        <div class="col-12">
          <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted"><?php echo $CLICSHOPPING_MCP->getDef('text_last_update'); ?> <span id="lastUpdate"><?php echo $CLICSHOPPING_MCP->getDef('text_never'); ?></span></small>
            <div>
              <button id="startMonitoring" class="btn btn-success btn-sm">
                <i class="bi bi-play-circle"></i> <?php echo $CLICSHOPPING_MCP->getDef('text_start_monitoring'); ?>
              </button>
              <button id="stopMonitoring" class="btn btn-danger btn-sm" style="display:none;">
                <i class="bi bi-stop-circle"></i> <?php echo $CLICSHOPPING_MCP->getDef('text_stop_monitoring'); ?>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Event Log -->
      <div class="card mb-3">
        <div class="card-header">
          <i class="bi bi-list-ul"></i> <?php echo $CLICSHOPPING_MCP->getDef('text_event_log'); ?>
          <button id="clearLog" class="btn btn-sm btn-outline-secondary float-end"><?php echo $CLICSHOPPING_MCP->getDef('text_clear'); ?></button>
        </div>
        <div class="card-body">
          <div id="eventLog" style="max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 0.8em;">
            <div class="text-muted"><?php echo $CLICSHOPPING_MCP->getDef('text_no_event'); ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$event_url = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/MCP/Event/McpHealthStream.php';
?>

<script defer>
  var eventUrl = "<?php echo $event_url; ?>";
</script>
<script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/MCP/health_monitor.js'); ?>"></script>

