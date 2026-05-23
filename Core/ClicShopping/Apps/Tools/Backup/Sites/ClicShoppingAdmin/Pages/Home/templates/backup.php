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
use ClicShopping\OM\ObjectInfo;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Backup = Registry::get('Backup');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');

$backup_directory = CLICSHOPPING::BASE_DIR . 'Work/Backups/';
// check if the backup directory exists
$dir_ok = false;

if (is_dir($backup_directory)) {
  if (FileSystem::isWritable($backup_directory)) {
    $dir_ok = true;
  } else {
    $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Backup->getDef('error_backup_directory_not_writeable'), 'error');
  }
} else {
  $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Backup->getDef('error_backup_directory_does_not_exist'), 'error');
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
          <span class="col-md-6 text-end">
            <?php
            echo HTML::button($CLICSHOPPING_Backup->getDef('button_backup'), null, $CLICSHOPPING_Backup->link('BackupDb'), 'info') . ' ';

            if (MODE_DEMO == 'False') {
              echo HTML::button($CLICSHOPPING_Backup->getDef('button_restore_file'), null, $CLICSHOPPING_Backup->link('RestoreLocal'), 'warning');
            }
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <table border="0" width="100%" cellspacing="0" cellpadding="2">
    <td>
      <table class="table table-sm  table-hover table-striped">
        <thead>
        <tr class="dataTableHeadingRow">
          <th class="dataTableHeadingContent"><?php echo $CLICSHOPPING_Backup->getDef('table_heading_title'); ?></th>
          <th
            class="dataTableHeadingContent text-center"><?php echo $CLICSHOPPING_Backup->getDef('table_heading_file_date'); ?></th>
          <th
            class="dataTableHeadingContent text-end"><?php echo $CLICSHOPPING_Backup->getDef('table_heading_file_size'); ?></th>
          <th
            class="dataTableHeadingContent text-center"><?php echo $CLICSHOPPING_Backup->getDef('table_heading_statistics'); ?></th>
          <th
            class="dataTableHeadingContent text-end"><?php echo $CLICSHOPPING_Backup->getDef('table_heading_info_compression'); ?></th>
          <th
            class="dataTableHeadingContent text-end"><?php echo $CLICSHOPPING_Backup->getDef('table_heading_action'); ?>
            &nbsp;
          </th>
        </tr>
        </thead>
        <tbody>
        <?php

        if ($dir_ok === true) {

        $dir = dir($backup_directory);
        $contents = [];

        while ($file = $dir->read()) {
          if (!is_dir($backup_directory . $file) && \in_array(substr($file, -3), array('zip', 'sql', '.gz'))) {
            $contents[] = $file;
          }
        }

        sort($contents);

        for ($i = 0, $n = \count($contents); $i < $n; $i++) {
          $entry = $contents[$i];

          if ((!isset($_GET['file']) || (isset($_GET['file']) && ($_GET['file'] == $entry)))) {
            if (is_file($backup_directory . $file)) {
              $info = [
                'file' => $file,
                'date' => date($CLICSHOPPING_Backup->getDef('php_date_time_format'), filemtime($backup_directory . $file)),
                'size' => number_format(filesize($backup_directory . $entry)) . ' file',
              ];

              switch (substr(file, -3)) {
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

              $compression = $buInfo->compression;
            } else {
              $compression = '';
            }
            ?>
            <th scope="row"><?php echo $entry; ?></th>
            <td class="text-center"><?php echo date("m/d/Y", filemtime($backup_directory . $entry)); ?></td>
            <td class="text-end"><?php echo number_format(filesize($backup_directory . $entry)); ?>
              bytes
            </td>
            <td class="text-center">
              <?php
              // Get backup statistics
              $stats = \ClicShopping\Apps\Tools\Backup\Classes\ClicShoppingAdmin\Backup::getBackupStatistics($backup_directory . $entry);
              if ($stats && $stats['tables_backed_up'] > 0) {
                echo '<small>';
                echo 'Tables: ' . $stats['tables_backed_up'];
                if ($stats['tables_with_comments'] > 0) {
                  echo ' (' . $stats['tables_with_comments'] . ' with comments)';
                }
                echo '<br>';
                if ($stats['columns_with_comments'] > 0) {
                  echo 'Columns with comments: ' . $stats['columns_with_comments'] . '<br>';
                }
                if ($stats['vector_columns'] > 0) {
                  echo 'VECTOR columns: ' . $stats['vector_columns'] . '<br>';
                }
                echo 'Rows: ' . number_format($stats['total_rows']);
                echo '</small>';
              } else {
                echo '<small>No stats</small>';
              }
              ?>
            </td>
            <td class="text-center" onclick="document.location.href='<?php $compression; ?>'"></td>
            <td class="text-end">
              <div class="btn-group d-flex justify-content-end" role="group" aria-label="buttonGroup">
                <?php
                echo '<a href="' . $CLICSHOPPING_Backup->link('Backup&Download&file=' . $entry) . '"><h4><i class="bi bi-cloud-arrow-down" title="' . $CLICSHOPPING_Backup->getDef('icon_file_download') . '"></i></h4></a>';
                echo '&nbsp;';
                echo '<a href="' . $CLICSHOPPING_Backup->link('Restore&file=' . $entry) . '"><h4><i class="bi bi-cloud-arrow-up" title="' . $CLICSHOPPING_Backup->getDef('icon_restore') . '"></i></h4></a>';
                echo '&nbsp;';
                echo '<a href="' . $CLICSHOPPING_Backup->link('Delete&file=' . $entry) . '"><h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Backup->getDef('icon_delete') . '"></i></h4></a>';
                echo '&nbsp;';
                ?>
              </div>
            </td>
            </tr>
            <?php
          }
        }

        $dir->close();
        ?>
        </tbody>
      </table>
    </td>
  </table>
  <?php
  if (\defined('DB_LAST_RESTORE')) {
    ?>
    <div><?php echo $CLICSHOPPING_Backup->getDef('text_last_restoration') . ' ' . DB_LAST_RESTORE . ' <a href="' . $CLICSHOPPING_Backup->link('Backup&Forget') . '">' . $CLICSHOPPING_Backup->getDef('text_forget') . '</a>'; ?></div>
    <?php
  }
  }
  ?>
</div>


