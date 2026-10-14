<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Cache;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Cache = Registry::get('Cache');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

// check if the cache directory exists
if (is_dir(Cache::getPath())) {
  if (!FileSystem::isWritable(Cache::getPath())) $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Cache->getDef('error_cache_directory_not_writeable'), 'error');
} else {
  $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Cache->getDef('error_cache_directory_does_not_exist'), 'error');
}

$cache_files = [];

foreach (glob(Cache::getPath() . '*.cache') as $c) {
  $key = basename($c, '.cache');

  if (($pos = strpos($key, '-')) !== false) {
    $cache_files[substr($key, 0, $pos)][] = $key;
  } else {
    $cache_files[$key][] = $key;
  }
}

?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/cache.gif', $CLICSHOPPING_Cache->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-4 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_Cache->getDef('heading_title'); ?></span>
          <span
            class="col-md-7 text-end"><?php echo HTML::button($CLICSHOPPING_Cache->getDef('button_reset'), null, $CLICSHOPPING_Cache->link('Cache&ResetAll'), 'danger'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING                                                            -->
  <!-- //################################################################################################################ -->

  <table
    id="table"
    data-toggle="table"
    data-icons-prefix="bi"
    data-icons="icons"
    data-sort-name="number"
    data-sort-order="asc"
    data-toolbar="#toolbar"
    data-buttons-class="primary"
    data-show-toggle="true"
    data-show-columns="true"
    data-mobile-responsive="true"
    data-check-on-init="true">

    <thead class="dataTableHeadingRow">
    <tr>
      <th data-field="cache"><?php echo $CLICSHOPPING_Cache->getDef('table_heading_cache'); ?></th>
      <th data-field="number" data-sortable="true"
          class="text-end"><?php echo $CLICSHOPPING_Cache->getDef('table_heading_cache_number_of_files'); ?></th>
      <th data-field="action" data-switchable="false"
          class="text-end"><?php echo $CLICSHOPPING_Cache->getDef('table_heading_action'); ?></th>
    </tr>
    </thead>
    <tbody>
    <?php
    foreach (array_keys($cache_files) as $key) {
      ?>
      <tr>
        <th scope="row"><?php echo $key; ?></th>
        <td class="text-end"><?php echo \count($cache_files[$key]); ?></td>
        <td
          class="text-end"><?php echo '<a href="' . $CLICSHOPPING_Cache->link('Cache&Reset&block=' . $key) . '"><h4><i class="bi bi-trash2" title="' . $CLICSHOPPING_Cache->getDef('image_reset') . '"></i></h4></a>'; ?></td>
      </tr>
      <?php
    }
    ?>
    </tbody>
  </table>
</div>
<div class="py-4"></div>
