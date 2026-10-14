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

$CLICSHOPPING_SecDirPermissions = Registry::get('SecDirPermissions');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Language = Registry::get('Language');

$CLICSHOPPING_Language->loadDefinitions('sec_dir_permissions');

function getOpenDir($path)
{
  $path = rtrim($path, '/') . '/';

  $exclude_array = ['.', '..', '.DS_Store', 'Thumbs.db', '.htaccess', '_htaccess'];

  $result = [];

  if ($handle = opendir($path)) {

    while (false !== ($filename = readdir($handle))) {

      if (!\in_array($filename, $exclude_array)) {
        $file = ['name' => $path . $filename,
          'is_dir' => is_dir($path . $filename),
          'writable' => FileSystem::isWritable($path . $filename)
        ];

        $result[] = $file;

        if ($file['is_dir'] === true) {
          $result = array_merge($result, getOpenDir($path . $filename));
        }
      }
    }

    closedir($handle);
  }

  return $result;
}

$whitelist_array = [];

$Qwhitelist = $CLICSHOPPING_SecDirPermissions->db->get('sec_directory_whitelist', 'directory');

while ($Qwhitelist->fetch()) {
  $whitelist_array[] = $Qwhitelist->value('directory');
}

$admin_dir = basename(CLICSHOPPING::getConfig('dir_root'));

if ($admin_dir != 'ClicShoppingAdmin') {
  for ($i = 0, $n = \count($whitelist_array); $i < $n; $i++) {
    if (substr($whitelist_array[$i], 0, 6) == 'ClicShoppingAdmin/') {
      $whitelist_array[$i] = $admin_dir . substr($whitelist_array[$i], 5);
    }
  }
}

?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/file_manager.gif', $CLICSHOPPING_SecDirPermissions->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-7 pageHeading"><?php echo $CLICSHOPPING_SecDirPermissions->getDef('heading_title'); ?></span>
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
          <th><?php echo $CLICSHOPPING_SecDirPermissions->getDef('table_heading_directories'); ?></th>
          <th
            class="text-center"><?php echo $CLICSHOPPING_SecDirPermissions->getDef('table_heading_writable'); ?></th>
          <th
            class="text-center"><?php echo $CLICSHOPPING_SecDirPermissions->getDef('table_heading_recommended'); ?></th>
        </tr>
        <thead>
        <tbody>
        <?php
        foreach (getOpenDir(CLICSHOPPING::getConfig('dir_root', 'Shop')) as $file) {
          if ($file['is_dir']) {
            ?>
            <tr>
              <th
                scope="row"><?php echo substr($file['name'], \strlen(CLICSHOPPING::getConfig('dir_root', 'Shop'))); ?></th>
              <td
                class="text-center"><?php echo $file['writable'] === true ? '<i class="bi-check text-success"></i>' : '<i class="bi bi-x text-danger"></i>'; ?></td>
              <td
                class="text-center"><?php echo(\in_array(substr($file['name'], \strlen(CLICSHOPPING::getConfig('dir_root', 'Shop'))), $whitelist_array) ? '<i class="bi-check text-success"></i>' : '<i class="bi bi-x text-danger"></i>'); ?></td>
            </tr>
            <?php
          }
        }
        ?>
        </tbody>
      </table>
    </td>
    </tr>
  </table>
</div>