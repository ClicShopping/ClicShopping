<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_MCP = Registry::get('MCP');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
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
      	  <?php
	            echo HTML::button($CLICSHOPPING_MCP->getDef('button_configure'), null, $CLICSHOPPING_MCP->link('Configure'), 'primary');
              echo HTML::button($CLICSHOPPING_MCP->getDef('button_insert'), null, $CLICSHOPPING_MCP->link('Edit'), 'success') . ' ';
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-2"></div>

  <div class="col-md-12 mainTitle"><strong><?php echo $CLICSHOPPING_MCP->getDef('text_mcp_data'); ?></strong></div>
  <div class="adminformTitle">
     <div class="row">
      <div class="card headerCard">
      <div class="card-body">
        <div class="row">
          <div class="flex-fill">
            <?php echo HTML::button($CLICSHOPPING_MCP->getDef('button_health_monitor'), null, $CLICSHOPPING_MCP->link('HealthMonitor'), 'success'); ?>
            <?php echo HTML::button($CLICSHOPPING_MCP->getDef('button_performance_monitor'), null, $CLICSHOPPING_MCP->link('PerformanceMonitor'), 'danger'); ?>
            <?php echo HTML::button($CLICSHOPPING_MCP->getDef('button_mcp_alert'), null, $CLICSHOPPING_MCP->link('AlertViewer'), 'warning'); ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING DES                                                        -->
  <!-- //################################################################################################################ -->

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="value"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="id" data-sortable="true"><?php echo $CLICSHOPPING_MCP->getDef('table_heading_mcp_id'); ?></th>
      <th data-field="username"
          data-sortable="true"><?php echo $CLICSHOPPING_MCP->getDef('table_heading_mcp_username'); ?></th>
      <th data-field="server"
          data-sortable="true"><?php echo $CLICSHOPPING_MCP->getDef('table_heading_mcp_server'); ?></th>
      <th data-field="key"
          class="text-center"><?php echo $CLICSHOPPING_MCP->getDef('table_heading_mcp_key_text'); ?></th>
      <th data-field="status" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_MCP->getDef('table_heading_mcp_status'); ?></th>
      <th data-field="date_added" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_MCP->getDef('table_heading_mcp_date_added'); ?></th>
      <th data-field="date_modified" data-sortable="true"
          class="text-center"><?php echo $CLICSHOPPING_MCP->getDef('table_heading_mcp_date_modified'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_MCP->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>
    <?php
    $Qmcp = $CLICSHOPPING_MCP->db->prepare('select SQL_CALC_FOUND_ROWS mcp_id,
                                                                        username,
                                                                        mcp_key,
                                                                        server_host,
                                                                        server_port,
                                                                        ssl_enabled,
                                                                        status,
                                                                        date_added,
                                                                        date_modified
                                                        from :table_mcp
                                                        order by mcp_id
                                                        limit :page_set_offset, :page_set_max_results
                                                      ');

    $Qmcp->setPageSet((int)MAX_DISPLAY_SEARCH_RESULTS_ADMIN);
    $Qmcp->execute();

    $listingTotalRow = $Qmcp->getPageSetTotalRows();

    if ($listingTotalRow > 0) {
      while ($Qmcp->fetch()) {
        ?>
        <td><?php echo $Qmcp->valueInt('mcp_id'); ?></td>
        <td><?php echo $Qmcp->valueProtected('username'); ?></td>
        <td>
          <?php
          $protocol = $Qmcp->valueInt('ssl_enabled') ? 'https' : 'http';
          $serverInfo = $protocol . '://' . $Qmcp->valueProtected('server_host') . ':' . $Qmcp->valueInt('server_port');
          echo '<span class="badge bg-info">' . HTML::outputProtected($serverInfo) . '</span>';
          ?>
        </td>
        <td><?php echo substr($Qmcp->valueProtected('mcp_key'), -40, 40) . '...'; ?></td>
        <td class="text-center">
          <?php
          if ($Qmcp->valueInt('status') == 1) {
            echo HTML::link($CLICSHOPPING_MCP->link('MCP&SetFlag&flag=0&cID=' . $Qmcp->valueInt('mcp_id') . '&page=' . $page), '<i class="bi-check text-success"></i>');
          } else {
            echo HTML::link($CLICSHOPPING_MCP->link('MCP&SetFlag&flag=1&cID=' . $Qmcp->valueInt('mcp_id') . '&page=' . $page), '<i class="bi bi-x text-danger"></i>');
          }
          ?>
        </td>
        <td><?php echo DateTime::toShort($Qmcp->valueProtected('date_added')); ?></td>
        <td><?php echo DateTime::toShort($Qmcp->valueProtected('date_modified')); ?></td>
        <td class="text-end">
          <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
            <?php
            echo HTML::link($CLICSHOPPING_MCP->link('Edit&page=' . $page . '&cID=' . $Qmcp->valueInt('mcp_id')), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_MCP->getDef('icon_edit') . '"></i></h4>');
            echo '&nbsp;';
            echo HTML::link($CLICSHOPPING_MCP->link('MCP&Delete&page=' . $page . '&cID=' . $Qmcp->valueInt('mcp_id')), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_MCP->getDef('icon_delete') . '"></i></h4>');
            echo '&nbsp;';
            ?>
          </div>
        </td>
        </tr>
        <?php
      }
    }
    ?>
    </tbody>
  </table>
</div>
<div class="py-4"></div>