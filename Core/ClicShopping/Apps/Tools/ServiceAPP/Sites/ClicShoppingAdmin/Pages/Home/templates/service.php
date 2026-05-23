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

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_ServiceAPP = Registry::get('ServiceAPP');

$directory = CLICSHOPPING::BASE_DIR . 'Service/Shop/';
$exclude = ['.', '..', '_htaccess', '.htaccess'];
$files = array_diff(scandir($directory), $exclude);

$result['entries'] = [];
$result['file'] = [];
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/service.png', $CLICSHOPPING_ServiceAPP->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ServiceAPP->getDef('heading_title'); ?></span>
          <span
            class="col-md-6 text-end"><?php echo '<span>' . HTML::button($CLICSHOPPING_ServiceAPP->getDef('button_service_admin'), null, $CLICSHOPPING_ServiceAPP->link('ServiceAPPAdmin'), 'success') . '</span>'; ?>

        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <table border="0" width="100%" cellspacing="0" cellpadding="2">
    <td>
      <table class="table table-sm table-hover table-striped">
        <thead>
        <tr class="dataTableHeadingRow">
          <th><?php echo $CLICSHOPPING_ServiceAPP->getDef('table_heading_services'); ?></th>
          <th><?php echo $CLICSHOPPING_ServiceAPP->getDef('table_heading_class'); ?></th>
        </tr>
        </thead>
        <tbody>
        <?php
        foreach ($files as $sm) {
          $result['file'][] = ['files_name' => $sm];
        }
        sort($result['file']);

        $i = 0;

        foreach ($result['file'] as &$module) {

          $class = substr($module['files_name'], 0, strrpos($module['files_name'], '.'));
          $class1[] = $class;

          if (class_exists($class)) {
            $module = new $class;
          }

          if (isset($module)) {
            ?>
            <td><?php echo $module['files_name']; ?></td>
            <td><?php echo $class; ?></td>
            </tr>
            <?php
          }
        }
        ?>
        </tbody>
      </table>
    </td>
  </table>
  <!-- body_eof //-->
</div>