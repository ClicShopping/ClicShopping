<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\DateTime;
use ClicShopping\OM\ErrorHandler;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');
$CLICSHOPPING_EditLogError = Registry::get('EditLogError');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$files = [];

foreach (glob(ErrorHandler::getDirectory() . 'errors-*.txt') as $f) {
  $key = basename($f, '.txt');

  if (preg_match('/^errors-([0-9]{4})([0-9]{2})([0-9]{2})$/', $key, $matches)) {
    $files[$key] = [
      'path' => $f,
      'key' => $key,
      'date' => DateTime::toShort($matches[1] . '-' . $matches[2] . '-' . $matches[3]),
      'size' => filesize($f)
    ];
  }
}
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/log.png', $CLICSHOPPING_EditLogError->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_EditLogError->getDef('heading_title'); ?></span>
          <span class="col-md-6 text-end">
              <?php echo HTML::button($CLICSHOPPING_EditLogError->getDef('button_delete_all'), null, $CLICSHOPPING_EditLogError->link('LogError&DeleteAll'), 'danger'); ?>
           </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <table class="table table-sm table-hover table-striped">
    <thead>
    <tr class="dataTableHeadingRow">
      <th><?php echo $CLICSHOPPING_EditLogError->getDef('table_heading_filename'); ?></th>
      <th class="text-end"><?php echo $CLICSHOPPING_EditLogError->getDef('table_heading_filesize'); ?></th>
      <th class="action"></th>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach ($files as $f) {
      ?>
      <tr>
        <td><?php echo $f['date']; ?></td>
        <td class="text-end"><?php echo $f['size']; ?></td>
        <td
          class="text-end"><?php echo HTML::link($CLICSHOPPING_EditLogError->link('Edit&View&log=' . $f['key']), '<h4><i class="bi bi-pencil" title="' . $CLICSHOPPING_EditLogError->getDef('icon_edit') . '"></i></h4>'); ?></td>
      </tr>
      <?php
    }
    ?>
    </tbody>
  </table>
</div>
