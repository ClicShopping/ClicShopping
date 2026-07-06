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

// Requested log key may be missing from $_GET or point to a rotated/deleted file
// (e.g. an old errors-YYYYMMDD that no longer exists) -> degrade gracefully, no warning.
$log = $files[$_GET['log'] ?? ''] ?? null;
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1 logoHeading"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/log.png', $CLICSHOPPING_EditLogError->getDef('heading_title'), '40', '40'); ?></span>
          <span class="col-md-5 pageHeading">
            <?php
            echo '&nbsp;' . $CLICSHOPPING_EditLogError->getDef('heading_title') . ' -  ';
            echo HTML::outputProtected($log['date'] ?? '');
            ?>
          </span>
          <span class="col-md-6 text-end">
            <?php
            echo HTML::button($CLICSHOPPING_EditLogError->getDef('button_back'), null, $CLICSHOPPING_EditLogError->link('LogError'), 'primary') . ' ';

            echo HTML::form('delete', $CLICSHOPPING_EditLogError->link('LogError&Delete&log=' . ($log['key'] ?? '')));
            echo HTML::button($CLICSHOPPING_EditLogError->getDef('button_delete'), null, null, 'danger');
            ?>
            </form>
           </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div>
    <?php
    if (isset($log['path'])) {
      echo HTML::textAreaField('code', file_get_contents($log['path']), '', '', 'id="code"');
    }
    ?>
  </div>
</div>