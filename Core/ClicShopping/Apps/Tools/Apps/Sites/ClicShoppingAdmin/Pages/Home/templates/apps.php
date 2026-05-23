<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Apps = Registry::get('Apps');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/apps.png', $CLICSHOPPING_Apps->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Apps->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="mt-1"></div>
  <div class="spaceRow"></div>
  <table id="appsInstalledTable" class="table table-sm table-hover">
    <thead>
    <tr class="dataTableHeadingRow">
      <th><?php echo $CLICSHOPPING_Apps->getDef('table_heading_apps'); ?></th>
      <th><?php echo $CLICSHOPPING_Apps->getDef('table_heading_vendor'); ?></th>
      <th class="text-end"><?php echo $CLICSHOPPING_Apps->getDef('table_heading_version'); ?></th>
      <th class="action"></th>
    </tr>
    </thead>
    <tbody></tbody>
  </table>

  <script id="appInstalledTableEntry" type="x-tmpl-mustache">
    <tr>
      <td>{{title}}</td>
      <td>{{vendor}}</td>
      <td class="text-end">{{version}}</td>
    </tr>

  </script>
</div>
<script>
  $(function () {
    function rpcGetInstalledApps() {
      $('#appsInstalledTable tbody').empty();
      fas$('#appsInstalledTable tbody').append('<tr><td colspan="' + $('#appsInstalledTable thead th').length + '"><i class="bi bi-arrow-repeat"></i></td></tr>');

      $.get('<?= addslashes($CLICSHOPPING_Apps->link('Apps&getInstalledApps&action=1')); ?>', function (response) {
        $('#appsInstalledTable tbody').empty();

        if ((typeof response == 'object') && ('result' in response) && (response.result === 1)) {
          var appInstalledTableEntry = $('#appInstalledTableEntry').html();
          Mustache.parse(appInstalledTableEntry);

          $(response.apps).each(function (k, v) {
            var entry = $.parseHTML(Mustache.render(appInstalledTableEntry, {
              title: v.title,
              vendor: v.vendor,
              version: v.version
            }));

            $(entry).appendTo('#appsInstalledTable tbody');
          });

          if ($('#appsInstalledTable tbody tr').length < 1) {
            $('#appsInstalledTable tbody').append('<tr><td colspan="' + $('#appsInstalledTable thead th').length + '">There are currently no Apps installed.</td></tr>');
          }
        } else {
          errorRpcGetInstalledApps();
        }
      }, 'json').fail(function () {
        errorRpcGetInstalledApps();
      });
    };

    $('#appsInstalledTable tbody').on('click', 'tr[data-row="rpcError"] td a[data-action="doRpcGetInstalledApps"]', function () {
      rpcGetInstalledApps();
    });

    function errorRpcGetInstalledApps() {
      $('#appsInstalledTable tbody').empty().append('<tr data-row="rpcError"><td colspan="' + $('#appsInstalledTable thead th').length + '">There was a problem retrieving the list of installed Apps. <a data-action="doRpcGetInstalledApps">Try again.</a></td></tr>');
    };

    rpcGetInstalledApps();
    rpcGetShowcase();
  });
</script>