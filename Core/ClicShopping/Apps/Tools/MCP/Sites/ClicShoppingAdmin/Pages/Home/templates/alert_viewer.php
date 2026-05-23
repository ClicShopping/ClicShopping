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

$CLICSHOPPING_Mcp = Registry::get('MCP');
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
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/migration.png', $CLICSHOPPING_Mcp->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Mcp->getDef('heading_title'); ?></span>
          <span class="col-md-7 text-end">
          <?php echo HTML::button($CLICSHOPPING_Mcp->getDef('button_back'), null, $CLICSHOPPING_Mcp->link('MCP'), 'primary'); ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="py-3"></div>

  <div class="card mb-3">
    <div class="card-header">
      <i class="bi bi-bell"></i> <?php echo $CLICSHOPPING_Mcp->getDef('text_alert_history'); ?>
      <div class="float-end">
        <select id="alertFilter" class="form-select form-select-sm d-inline-block w-auto">
          <option value="all"><?php echo $CLICSHOPPING_Mcp->getDef('text_all_alert'); ?></option>
          <option value="critical"><?php echo $CLICSHOPPING_Mcp->getDef('text_errors_critical_only'); ?></option>
          <option value="error"><?php echo $CLICSHOPPING_Mcp->getDef('text_errors_only'); ?></option>
          <option value="warning"><?php echo $CLICSHOPPING_Mcp->getDef('text_only_warning'); ?></option>
          <option value="info"><?php echo $CLICSHOPPING_Mcp->getDef('text_info_only'); ?></option>
        </select>
      </div>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table id="mcpAlerts" class="table table-hover">
          <thead>
          <tr>
            <th><?php echo $CLICSHOPPING_Mcp->getDef('text_date_time'); ?></th>
            <th><?php echo $CLICSHOPPING_Mcp->getDef('text_server'); ?></th>
            <th><?php echo $CLICSHOPPING_Mcp->getDef('text_type'); ?></th>
            <th><?php echo $CLICSHOPPING_Mcp->getDef('text_message'); ?></th>
          </tr>
          </thead>
          <tbody id="mcpAlertsList">
          <tr>
            <td colspan="4" class="text-center"><?php echo $CLICSHOPPING_Mcp->getDef('text_loading_alert'); ?></td>
          </tr>
          </tbody>
        </table>
      </div>
      <div class="row mt-3">
        <div class="col-md-6">
          <div id="mcpAlertsPagination" class="float-start"></div>
        </div>
        <div class="col-md-6">
          <button id="clearAlerts" class="btn btn-sm btn-danger float-end">
            <i class="bi bi-trash"></i> <?php echo $CLICSHOPPING_Mcp->getDef('text_clear_alert'); ?>
          </button>
        </div>
      </div>
    </div>
  </div>
  <div class="py-3"></div>

  <?php
  $ajax_gpt_alerts = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/MCP/GetAlerts.php';
  $ajax_clear_alert = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin') . 'ajax/MCP/ClearAlerts.php';
  ?>

  <script defer>
    var gptAlerts = "<?php echo $ajax_gpt_alerts; ?>";
    var clearAlerts = "<?php echo $ajax_clear_alert; ?>";
    var text_no_alert = "<?php echo addslashes($CLICSHOPPING_Mcp->getDef('text_no_alert')); ?>";
  </script>
  <script defer src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/MCP/alert_viewer.js'); ?>"></script>
