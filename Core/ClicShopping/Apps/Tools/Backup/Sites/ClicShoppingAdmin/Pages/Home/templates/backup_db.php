<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Backup = Registry::get('Backup');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$backup_directory = CLICSHOPPING::BASE_DIR . 'Work/Backups/';

$dir_ok = false;

if (is_dir($backup_directory)) {
  if (FileSystem::isWritable($backup_directory)) {
    $dir_ok = true;
  } else {
    $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_backup_directory_not_writeable'), 'error');
  }
} else {
  $CLICSHOPPING_MessageStack->add(CLICSHOPPING::getDef('error_backup_directory_does_not_exist'), 'error');
}
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
  <div class="mt-1"></div>


  <div class="col-md-12 mainTitle">
    <strong><?php echo $CLICSHOPPING_Backup->getDef('text_info_heading_new_backup'); ?></strong></div>
  <?php echo HTML::form('backup', $CLICSHOPPING_Backup->link('Backup&BackupNow')); ?>
  <div class="adminformTitle">
    <div class="row">
      <div class="mt-1"></div>
      <div class="col-md-12"><?php echo $CLICSHOPPING_Backup->getDef('text_info_new_backup'); ?></div>
      <div class="mt-1"></div>
      <div>
        <?php
        if (is_file(LOCAL_EXE_GZIP)) {
          $contents[] = ['text' => HTML::radioField('compress', 'gzip') . ' ' . $CLICSHOPPING_Backup->getDef('text_info_use_gzip')];
        }
        ?>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12">
        <ul class="list-group-slider list-group-flush">
          <li class="list-group-item-slider">
            <label class="switch">
              <?php echo HTML::checkboxField('compress', 'no', true, 'class="success"'); ?>
              <span class="slider"></span>
            </label>
          </li>
          <span class="text-slider"><?php echo $CLICSHOPPING_Backup->getDef('text_info_use_no_compression'); ?></span>
        </ul>
      </div>
      <div class="mt-1"></div>
      <div class="col-md-12">
        <ul class="list-group-slider list-group-flush">
          <li class="list-group-item-slider">
            <label class="switch">
              <?php echo HTML::checkboxField('compress', 'zip', null, 'class="success"'); ?>
              <span class="slider"></span>
            </label>
          </li>
          <span class="text-slider"><?php echo $CLICSHOPPING_Backup->getDef('text_info_use_zip'); ?></span>
        </ul>
      </div>
      <?php
      if ($dir_ok === true) {
        ?>
        <div class="mt-1"></div>
        <div class="col-md-12">
          <ul class="list-group-slider list-group-flush">
            <li class="list-group-item-slider">
              <label class="switch">
                <?php echo HTML::checkboxField('download', 'yes', null, 'class="success"'); ?>
                <span class="slider"></span>
              </label>
            </li>
            <span
              class="text-slider"><?php echo $CLICSHOPPING_Backup->getDef('text_info_download_only') . '<br />*' . $CLICSHOPPING_Backup->getDef('text_info_best_through_https'); ?></span>
          </ul>
        </div>
        <?php
      } else {
        ?>
        <div class="mt-1"></div>
        <div class="col-md-12">
          <ul class="list-group-slider list-group-flush">
            <li class="list-group-item-slider">
              <label class="switch">
                <?php echo HTML::checkboxField('download', 'yes', true, 'class="success"'); ?>
                <span class="slider"></span>
              </label>
            </li>
            <span
              class="text-slider"><?php echo $CLICSHOPPING_Backup->getDef('text_info_download_only') . '<br />*' . $CLICSHOPPING_Backup->getDef('text_info_best_through_https'); ?></span>
          </ul>
        </div>
        <?php
      }
      ?>
      <div class="col-md-12 text-center">
        <span><br/><?php echo HTML::button($CLICSHOPPING_Backup->getDef('button_backup'), null, null, 'primary', null, 'sm') . ' </span><span>' . HTML::button($CLICSHOPPING_Backup->getDef('button_cancel'), null, $CLICSHOPPING_Backup->link('Backup'), 'warning', null, 'sm'); ?></span>
      </div>
    </div>
  </div>
  </form>
</div>


