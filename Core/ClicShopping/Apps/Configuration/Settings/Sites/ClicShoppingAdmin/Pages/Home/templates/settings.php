<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Settings = Registry::get('Settings');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$gID = (isset($_GET['gID'])) ? $_GET['gID'] : 1;

$QcfgGroup = $CLICSHOPPING_Settings->db->get('configuration_group', 'configuration_group_title', ['configuration_group_id' => (int)$gID]);

?>
<!-- body //-->
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/configuration_' . $gID . '.gif', $CLICSHOPPING_Settings->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-11 pageHeading"><?php echo '&nbsp;' . $QcfgGroup->value('configuration_group_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true"
    data-search="true">

  <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="title"><?php echo $CLICSHOPPING_Settings->getDef('table_heading_configuration_title'); ?></th>
      <th data-field="value"><?php echo $CLICSHOPPING_Settings->getDef('table_heading_configuration_value'); ?></th>
      <th data-field="key"><?php echo $CLICSHOPPING_Settings->getDef('table_heading_configuration_key'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Settings->getDef('table_heading_action'); ?>&nbsp;
      </th>
    </tr>
    </thead>
    <tbody>

    <?php
    $Qconfiguration = $CLICSHOPPING_Settings->db->get('configuration', [
      'configuration_id',
      'configuration_title',
      'configuration_value',
      'configuration_key',
      'use_function'
    ], [
      'configuration_group_id' => (int)$gID
    ],
      'sort_order'
    );

    while ($Qconfiguration->fetch()) {
      $cfgValue = $Qconfiguration->value('configuration_value');
      ?>
      <tr>
        <td><?php echo $Qconfiguration->value('configuration_title'); ?></td>
        <td><?php echo htmlspecialchars($cfgValue, ENT_QUOTES | ENT_HTML5); ?></td>
        <td><span class="badge text-bg-light"><?php echo $Qconfiguration->value('configuration_key'); ?></span></td>
        <td>
          <script>
            $(document).ready(function () {
              $("#myModal_<?php echo $Qconfiguration->valueInt('configuration_id'); ?>").on("show.bs.modal", function (e) {
                var link = $(e.relatedTarget);
                $(this).find(".modal-body").load(link.attr("href"));
              });
            });
          </script>
          <a
            href="<?php echo $CLICSHOPPING_Settings->link('SettingsPopUp&Save&gID=' . HTML::sanitize($_GET['gID']) . '&cID=' . HTML::sanitize($Qconfiguration->valueInt('configuration_id'))); ?>"
            data-bs-toggle="modal" data-refresh="true"
            data-bs-target="#myModal_<?php echo $Qconfiguration->valueInt('configuration_id'); ?>"><h4><i
                class="bi bi-pencil" title="<?php echo $CLICSHOPPING_Settings->getDef('icon_edit'); ?>"></i></h4></a>
          <div class="modal fade" id="myModal_<?php echo $Qconfiguration->valueInt('configuration_id'); ?>"
               tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-body">
                  <div class="te"></div>
                </div>
              </div> <!-- /.modal-content -->
            </div><!-- /.modal-dialog -->
          </div><!-- /.modal -->
        </td>
      </tr>
      <?php
    }
    ?>
    </tbody>
  </table>
</div>
<div class="py-4"></div>
<div class="py-2"></div>