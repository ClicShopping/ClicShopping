<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Mcp = Registry::get('MCP');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Hooks = Registry::get('Hooks');

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int) $_GET['page'] : 1;
if (isset($_GET['cID'])) {
  $cId = HTML::sanitize($_GET['cID']);
} else {
  $cId = '';
}

// Optimized SQL query with proper formatting
$Qmcp = $CLICSHOPPING_Mcp->db->prepare('SELECT mcp_id,
                                                username,
                                                mcp_key,
                                                status,
                                                select_data,
                                                update_data,
                                                create_data,
                                                delete_data,
                                                create_db,
                                                server_host,
                                                server_port,
                                                ssl_enabled,
                                                alert_threshold,
                                                latency_threshold,
                                                downtime_threshold,
                                                data_retention,
                                                alert_notification
                                          FROM :table_mcp
                                          WHERE mcp_id = :mcp_id
                                        ');
$Qmcp->bindInt(':mcp_id', $cId);
$Qmcp->execute();

if (!empty($cId)) {
  $form_action = 'Update';
} else {
  $form_action = 'Insert';
}
?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/mcp.png', $CLICSHOPPING_Mcp->getDef('heading_title'), '40', '40'); ?></span>
          <span class="col-md-7 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Mcp->getDef('heading_title'); ?></span>
          <span class="col-md-4 text-end">
            <?php
            echo HTML::button($CLICSHOPPING_Mcp->getDef('button_configure'), null, $CLICSHOPPING_Mcp->link('Configure'), 'primary') . ' ';
            echo HTML::form('form_mcp', $CLICSHOPPING_Mcp->link('MCP&' . $form_action . '&page=' . $page . '&' . (isset($_GET['cID']) ? '&cID=' . $_GET['cID'] : '')));
            echo HTML::button($CLICSHOPPING_Mcp->getDef('button_cancel'), null, $CLICSHOPPING_Mcp->link('MCP&page=' . $page . '&cID=' . $Qmcp->valueInt('mcp_id')), 'warning') . ' ';

            if (!empty($cId)) {
              echo HTML::button($CLICSHOPPING_Mcp->getDef('button_save'), null, null, 'success') . ' ';
            } else {
              echo HTML::button($CLICSHOPPING_Mcp->getDef('button_insert'), null, null, 'success') . ' ';
            }
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div id="McpTabs" style="overflow: auto;">
    <ul class="nav nav-tabs flex-column flex-sm-row" role="tablist" id="myTab">
      <li class="nav-item">
        <?php echo '<a href="#tab1" role="tab" data-bs-toggle="tab" class="nav-link active">' . $CLICSHOPPING_Mcp->getDef('tab_general') . '</a>'; ?>
      </li>
      <li class="nav-item">
        <?php echo '<a href="#tab2" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Mcp->getDef('tab_ip_address') . '</a>'; ?>
      </li>
      <li class="nav-item">
        <?php echo '<a href="#tab3" role="tab" data-bs-toggle="tab" class="nav-link">' . $CLICSHOPPING_Mcp->getDef('tab_session') . '</a>'; ?>
      </li>
    </ul>
    <div class="tabsClicShopping">
      <div class="tab-content">
        <?php
        // -------------------------------------------------------------------
        //          ONGLET General sur la description de la Apli
        // -------------------------------------------------------------------
        
        ?>
        <div class="tab-pane active" id="tab1">
          <div class="mt-1"></div>
          <div class="row" id="mcp_username">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Mcp->getDef('text_mcp_username'); ?>"
                  class="col-3 col-form-label"><?php echo $CLICSHOPPING_Mcp->getDef('text_mcp_username'); ?></label>
                <div class="col-md-5">
                  <?php echo HTML::inputField('username', $Qmcp->value('username'), 'required aria-required="true" '); ?>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>
          <div class="row" id="mcp_key">
            <div class="col-md-12">
              <div class="form-group row">
                <label for="<?php echo $CLICSHOPPING_Mcp->getDef('text_mcp_key'); ?>"
                  class="col-3 col-form-label"><?php echo $CLICSHOPPING_Mcp->getDef('text_mcp_key'); ?></label>
                <div class="col-md-7">
                  <?php echo HTML::textAreaField('mcp_key', $Qmcp->value('mcp_key'), null, 4, 'id="input-key" class="form-control required aria-required="true" "'); ?>
                  <div class="mt-1"></div>
                  <button type="button" id="button-generate" class="btn btn-primary"><i
                      class="bi bi-arrow-clockwise"></i>
                    <?php echo $CLICSHOPPING_Mcp->getDef('text_mcp_generate_key'); ?>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="mt-1"></div>

             <!-- Server Configuration -->
          <div class="card mt-3">
            <div class="card-header">
              <h6 class="mb-0"><?php echo $CLICSHOPPING_Mcp->getDef('text_server_configuration'); ?></h6>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label for="server_host"
                      class="form-label"><?php echo $CLICSHOPPING_Mcp->getDef('text_server_host'); ?></label>
                    <?php echo HTML::inputField('server_host', $Qmcp->value('server_host'), 'class="form-control" placeholder="localhost"'); ?>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label for="server_port"
                      class="form-label"><?php echo $CLICSHOPPING_Mcp->getDef('text_server_port'); ?></label>
                    <?php echo HTML::inputField('server_port', $Qmcp->value('server_port'), 'class="form-control" type="number" placeholder="3000"'); ?>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <div class="form-check form-switch">
                      <?php echo HTML::checkboxField('ssl_enabled', '1', $Qmcp->valueInt('ssl_enabled'), 'class="form-check-input" id="ssl_enabled"'); ?>
                      <label class="form-check-label" for="ssl_enabled">
                        <?php echo $CLICSHOPPING_Mcp->getDef('text_ssl_enabled'); ?>
                      </label>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                </div>
              </div>
            </div>
          </div>
          
          <!-- Data Permissions Table -->
          <div class="card mt-3">
            <div class="card-header">
              <h6 class="mb-0"><?php echo $CLICSHOPPING_Mcp->getDef('text_data_permissions'); ?></h6>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead class="table-light">
                    <tr>
                      <th><?php echo $CLICSHOPPING_Mcp->getDef('text_permission_type'); ?></th>
                      <th class="text-center"><?php echo $CLICSHOPPING_Mcp->getDef('text_status'); ?></th>
                      <th><?php echo $CLICSHOPPING_Mcp->getDef('text_description'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_Mcp->getDef('text_select_data'); ?></strong></td>
                      <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                          <?php echo HTML::checkboxField('select_data', '1', $Qmcp->valueInt('select_data'), 'class="form-check-input"'); ?>
                        </div>
                      </td>
                      <td><small
                          class="text-muted"><?php echo $CLICSHOPPING_Mcp->getDef('text_select_data_desc'); ?></small>
                      </td>
                    </tr>
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_Mcp->getDef('text_update_data'); ?></strong></td>
                      <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                          <?php echo HTML::checkboxField('update_data', '1', $Qmcp->valueInt('update_data'), 'class="form-check-input"'); ?>
                        </div>
                      </td>
                      <td><small
                          class="text-muted"><?php echo $CLICSHOPPING_Mcp->getDef('text_update_data_desc'); ?></small>
                      </td>
                    </tr>
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_Mcp->getDef('text_create_data'); ?></strong></td>
                      <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                          <?php echo HTML::checkboxField('create_data', '1', $Qmcp->valueInt('create_data'), 'class="form-check-input"'); ?>
                        </div>
                      </td>
                      <td><small
                          class="text-muted"><?php echo $CLICSHOPPING_Mcp->getDef('text_create_data_desc'); ?></small>
                      </td>
                    </tr>
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_Mcp->getDef('text_delete_data'); ?></strong></td>
                      <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                          <?php echo HTML::checkboxField('delete_data', '1', $Qmcp->valueInt('delete_data'), 'class="form-check-input"'); ?>
                        </div>
                      </td>
                      <td><small
                          class="text-muted"><?php echo $CLICSHOPPING_Mcp->getDef('text_delete_data_desc'); ?></small>
                      </td>
                    </tr>
                    <tr>
                      <td><strong><?php echo $CLICSHOPPING_Mcp->getDef('text_create_db'); ?></strong></td>
                      <td class="text-center">
                        <div class="form-check form-switch d-inline-block">
                          <?php echo HTML::checkboxField('create_db', '1', $Qmcp->valueInt('create_db'), 'class="form-check-input"'); ?>
                        </div>
                      </td>
                      <td><small
                          class="text-muted"><?php echo $CLICSHOPPING_Mcp->getDef('text_create_db_desc'); ?></small>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>










          <!-- Monitoring & Alerts -->
          <div class="card mt-3">
            <div class="card-header">
              <h6 class="mb-0"><?php echo $CLICSHOPPING_Mcp->getDef('text_monitoring_alerts'); ?></h6>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-4">
                  <div class="form-group mb-3">
                    <label for="alert_threshold"
                      class="form-label"><?php echo $CLICSHOPPING_Mcp->getDef('text_alert_threshold'); ?></label>
                    <div class="input-group">
                      <?php echo HTML::inputField('alert_threshold', $Qmcp->value('alert_threshold'), 'class="form-control" type="number" min="0" max="100"'); ?>
                      <span class="input-group-text">%</span>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group mb-3">
                    <label for="latency_threshold"
                      class="form-label"><?php echo $CLICSHOPPING_Mcp->getDef('text_latency_threshold'); ?></label>
                    <div class="input-group">
                      <?php echo HTML::inputField('latency_threshold', $Qmcp->value('latency_threshold'), 'class="form-control" type="number" min="0"'); ?>
                      <span class="input-group-text">ms</span>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group mb-3">
                    <label for="downtime_threshold"
                      class="form-label"><?php echo $CLICSHOPPING_Mcp->getDef('text_downtime_threshold'); ?></label>
                    <div class="input-group">
                      <?php echo HTML::inputField('downtime_threshold', $Qmcp->value('downtime_threshold'), 'class="form-control" type="number" min="0"'); ?>
                      <span class="input-group-text">min</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <label for="data_retention"
                      class="form-label"><?php echo $CLICSHOPPING_Mcp->getDef('text_data_retention'); ?></label>
                    <div class="input-group">
                      <?php echo HTML::inputField('data_retention', $Qmcp->value('data_retention'), 'class="form-control" type="number" min="1"'); ?>
                      <span class="input-group-text">days</span>
                    </div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-3">
                    <div class="form-check form-switch mt-4">
                      <?php echo HTML::checkboxField('alert_notification', '1', $Qmcp->valueInt('alert_notification'), 'class="form-check-input" id="alert_notification"'); ?>
                      <label class="form-check-label" for="alert_notification">
                        <?php echo $CLICSHOPPING_Mcp->getDef('text_alert_notification'); ?>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-1"></div>
          <div class="mt-1"></div>
          <?php echo $CLICSHOPPING_Hooks->output('Mcp', 'McpiContentTab1', null, 'display'); ?>
          </form>
        </div>
        <?php
        // -------------------------------------------------------------------
        //          Ip address Tab
        // -------------------------------------------------------------------
        ?>
        <div class="tab-pane" id="tab2">
          <div class="mt-1"></div>
          <div class="row" id="text_alert">
            <div class="col-md-12">
              <?php
              if (!empty($cId)) {
                ?>
                <div class="alert alert-info" role="alert">
                  <?php echo $CLICSHOPPING_Mcp->getDef('text_info_message', ['ip_address' => HTTP::getIpAddress()]); ?>
                </div>
                <?php
              } else {
                ?>
                <div class="alert alert-warning" role="alert">
                  <?php echo $CLICSHOPPING_Mcp->getDef('text_info_warning'); ?></div>
                <?php
              }
              ?>
            </div>
          </div>
          <div class="mt-1"></div>
          <?php

          $Qip = $CLICSHOPPING_Mcp->db->prepare('select mcp_ip_id,
                                                         mcp_id,
                                                         ip,
                                                         comment
                                                  from :table_mcp_ip
                                                  where mcp_id = :mcp_id
                                                ');
          $Qip->bindInt(':mcp_id', $cId);
          $Qip->execute();

          $result = $Qip->fetchAll();

          if (!empty($cId)) {
            ?>
            <div class="col-md-12">
              <div class="row">
                <div class="text-end">
                  <div class="col-md-12">
                    <div class="form-group row">
                      <label for="<?php echo $CLICSHOPPING_Mcp->getDef('text_add_ip'); ?>"
                        class="col-11 col-form-label"></label>
                      <div class="col-md-1">
                        <?php echo HTML::button($CLICSHOPPING_Mcp->getDef('text_add_ip'), null, null, 'primary', ['params' => 'data-bs-toggle="modal" data-refresh="true" data-bs-target="#myModal"']); ?>
                        <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel"
                          aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <div class="modal-header">
                                <?php echo $CLICSHOPPING_Mcp->getDef('text_add_ip'); ?>
                              </div>
                              <div class="modal-body">
                                <?php echo HTML::form('add_mcp', $CLICSHOPPING_Mcp->link('MCP&AddIp&cID=' . $Qmcp->valueInt('mcp_id') . '&page=' . $page)); ?>
                                <div class="row" id="mcp_ip">
                                  <div class="col-md-12">
                                    <div class="form-group row">
                                      <label for="<?php echo $CLICSHOPPING_Mcp->getDef('text_ip'); ?>"
                                        class="col-3 col-form-label"><?php echo $CLICSHOPPING_Mcp->getDef('text_ip'); ?></label>
                                      <div class="col-md-7">
                                        <?php echo HTML::inputField('ip'); ?>
                                      </div>
                                    </div>
                                    <div class="form-group row">
                                      <label for="<?php echo $CLICSHOPPING_Mcp->getDef('text_comment'); ?>"
                                        class="col-3 col-form-label"><?php echo $CLICSHOPPING_Mcp->getDef('text_comment'); ?></label>
                                      <div class="col-md-7">
                                        <?php echo HTML::inputField('comment'); ?>
                                      </div>
                                    </div>

                                  </div>
                                  <div class="mt-1"></div>
                                  <div class="text-center">
                                    <?php echo HTML::button($CLICSHOPPING_Mcp->getDef('text_add'), null, null, 'success'); ?>
                                  </div>
                                </div>
                                </form>
                              </div> <!-- /.modal-content -->
                            </div><!-- /.modal-dialog -->
                          </div><!-- /.modal -->
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }
          ?>
          <table class="table table-striped">
            <thead class="dataTableHeadingRow">
              <tr>
                <td><?php echo $CLICSHOPPING_Mcp->getDef('text_ip'); ?></td>
                <td><?php echo $CLICSHOPPING_Mcp->getDef('text_heading_comment'); ?></td>
                <td class="text-end"><?php echo $CLICSHOPPING_Mcp->getDef('text_action'); ?></td>
              </tr>
            </thead>
            <tbody>
              <?php
              foreach ($result as $value) {
                ?>
                <tr>
                  <td><?php echo $value['ip']; ?></td>
                  <td><?php echo $value['comment']; ?></td>
                  <td class="text-end">
                    <?php
                    echo HTML::link($CLICSHOPPING_Mcp->link('MCP&DeleteIP&dID=' . $value['mcp_ip_id'] . '&cID=' . $cId), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Mcp->getDef('icon_delete') . '"></i></h4>');
                    echo '&nbsp;';
                    ?>
                  </td>
                </tr>
                <?php
              }
              ?>
            </tbody>
          </table>
          <div class="mt-1"></div>
          <?php echo $CLICSHOPPING_Hooks->output('Mcp', 'MCPContentTab2', null, 'display'); ?>
        </div>
        <?php

        // -------------------------------------------------------------------
        //          Session tab 2
        // -------------------------------------------------------------------
        


        $Qsession = $CLICSHOPPING_Mcp->db->prepare('select mcp_session_id,
                                                           mcp_id,
                                                           session_id,
                                                           ip,
                                                           date_added,
                                                           date_modified
                                                    from :table_mcp_session
                                                    where mcp_id = :mcp_id
                                                  ');
        $Qsession->bindInt(':mcp_id', $cId);
        $Qsession->execute();

        $result_array = $Qsession->fetchAll();
        ?>
        <div class="tab-pane" id="tab3">
          <div class="mt-1"></div>
          <table class="table table-striped">
            <thead class="dataTableHeadingRow">
              <tr>
                <td><?php echo $CLICSHOPPING_Mcp->getDef('text_token'); ?></td>
                <td><?php echo $CLICSHOPPING_Mcp->getDef('text_ip'); ?></td>
                <td class="text-center"><?php echo $CLICSHOPPING_Mcp->getDef('text_date_added'); ?></td>
                <td class="text-center"><?php echo $CLICSHOPPING_Mcp->getDef('text_date_modified'); ?></td>
                <td class="text-end"><?php echo $CLICSHOPPING_Mcp->getDef('text_action'); ?></td>
              </tr>
            </thead>
            <tbody>
              <?php
              foreach ($result_array as $value) {
                ?>
                <tr>
                  <td><?php echo $value['session_id']; ?></td>
                  <td><?php echo $value['ip']; ?></td>
                  <td><?php echo $value['date_added']; ?></td>
                  <td><?php echo $value['date_modified']; ?></td>
                  <td class="text-end">
                    <?php
                    echo HTML::link($CLICSHOPPING_Mcp->link('MCP&DeleteSessionIp&page=' . $page . '&cID=' . $value['mcp_session_id']), '<h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Mcp->getDef('icon_delete') . '"></i></h4>');
                    echo '&nbsp;';
                    ?>
                  </td>
                </tr>
                <?php
              }
              ?>
            </tbody>
          </table>
          <div class="mt-1"></div>
          <?php echo $CLICSHOPPING_Hooks->output('Mcp', 'MCPContentTab3', null, 'display'); ?>
        </div>
      </div>
    </div>
    </form>
  </div>

  <script
    src="<?php echo CLICSHOPPING::link('Shop/ext/javascript/clicshopping/ClicShoppingAdmin/generate_api.js'); ?>"></script>