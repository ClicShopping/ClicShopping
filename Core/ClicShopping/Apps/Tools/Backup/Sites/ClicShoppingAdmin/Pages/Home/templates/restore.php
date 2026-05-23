<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Backup = Registry::get('Backup');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

// check if the backup directory exists
$dir_ok = false;
$backup_directory = CLICSHOPPING::BASE_DIR . 'Work/Backups/';

$restore_file = $backup_directory . $_GET['file'];

$file = basename($_GET['file']);

if (file_exists($backup_directory . $file)) {
  $info = [
    'file' => $file,
    'date' => date(CLICSHOPPING::getDef('php_date_time_format'), filemtime($backup_directory . $file)),
    'size' => number_format(filesize($backup_directory . $file)) . ' bytes'
  ];
}

switch (substr($info['file'], -3)) {
  case 'zip':
    $info['compression'] = 'ZIP';
    break;
  case '.gz':
    $info['compression'] = 'GZIP';
    break;
  default:
    $info['compression'] = $CLICSHOPPING_Backup->getDef('text_no_extension');
    break;
}

$buInfo = new ObjectInfo($info);
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/backup.gif', $CLICSHOPPING_Backup->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Backup->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <?php
  if (is_file($backup_directory . $_GET['file'])) {
    ?>
    <div class="mt-1"></div>
    <div class="col-md-12 mainTitle"><strong><?php echo $buInfo->date; ?></strong></div>
    <div class="adminformTitle">
      <div class="row">
        <div class="mt-1"></div>
        <div
          class="col-md-12"><?php echo HTML::breakString($CLICSHOPPING_Backup->getDef('text_info_restore', ['restore' => $backup_directory . (($buInfo->compression != $CLICSHOPPING_Backup->getDef('text_no_extension')) ? substr($buInfo->file, 0, strrpos($buInfo->file, '.')) : $buInfo->file), ($buInfo->compression != $CLICSHOPPING_Backup->getDef('text_info_unpack')) ? $CLICSHOPPING_Backup->getDef('text_no_extension') : '']), 35, ' '); ?>
          <br/><br/></div>
        <div class="col-md-12 text-center">
          <span><br/><?php echo HTML::button($CLICSHOPPING_Backup->getDef('button_restore'), null, $CLICSHOPPING_Backup->link('Backup&RestoreNow&file=' . $buInfo->file), 'primary', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_Backup->getDef('button_cancel'), null, $CLICSHOPPING_Backup->link('Backup'), 'warning', null, 'sm'); ?></span>
        </div>
      </div>
    </div>
    <?php
  }
  ?>
</div>


