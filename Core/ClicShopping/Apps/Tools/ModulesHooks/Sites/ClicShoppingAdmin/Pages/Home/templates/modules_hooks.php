<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Apps;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_ModulesHooks = Registry::get('ModulesHooks');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_Page = Registry::get('Site')->getPage();

$hooks = [];

$directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/Hooks/';

if (is_dir($directory)) {
  if ($dir = new \DirectoryIterator($directory)) {
    foreach ($dir as $file) {
      if (!$file->isDot() && $file->isDir()) {
        $site = $file->getBasename();

        if ($sitedir = new \DirectoryIterator($directory . $site)) {
          foreach ($sitedir as $groupfile) {
            if (!$groupfile->isDot() && $groupfile->isDir()) {
              $group = $groupfile->getBasename();

              if ($groupdir = new \DirectoryIterator($directory . $site . '/' . $group)) {
                foreach ($groupdir as $hookfile) {
                  if (!$hookfile->isDot() && !$hookfile->isDir() && ($hookfile->getExtension() == 'php')) {
                    $hook = $hookfile->getBasename('.php');
                    $class = 'ClicShopping\OM\Module\Hooks\\' . $site . '\\' . $group . '\\' . $hook;
                    $h = new \ReflectionClass($class);

                    foreach ($h->getMethods(\ReflectionMethod::IS_STATIC | \ReflectionMethod::IS_PUBLIC) as $method) {
                      if ($method->name != '__construct') {
                        $hooks[$site . '/' . $group . '\\' . $hook][] = ['method' => $method->name];
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }
}

foreach (Apps::getModules('Hooks') as $k => $v) {
  [$vendor, $app, $code] = explode('\\', $k, 3);

  $h = new \ReflectionClass($v);

  foreach ($h->getMethods(\ReflectionMethod::IS_STATIC | \ReflectionMethod::IS_PUBLIC) as $method) {
    if ($method->name != '__construct') {
      $hooks[$code][] = [
        'app' => $vendor . '\\' . $app,
        'method' => $method->name
      ];
    }
  }
}
?>

<style>
  .sitePill {
    color: #fff;
    background-color: #009933;
    border-radius: 20px;
    padding: 5px 10px;
  }

  .appPill {
    color: #fff;
    background-color: #0066CC;
    border-radius: 20px;
    padding: 5px 10px;
  }
</style>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row">
          <span
            class="col-md-1"><?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/hooks.png', $CLICSHOPPING_ModulesHooks->getDef('heading_title'), '40', '40'); ?></span>
          <span
            class="col-md-5 pageHeading"><?php echo '&nbsp;' . $CLICSHOPPING_ModulesHooks->getDef('heading_title'); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <div class="col-md-12 mainTitle"></div>
  <table border="0" width="100%" cellspacing="0" cellpadding="2">
    <td>
      <table class="table table-sm table-hover table-striped">

        <?php
        foreach ($hooks as $code => $data) {
          $counter = 0;

          foreach ($data as $v) {
            $counter++;

            [$site, $group] = explode('/', $code, 2);
            ?>

            <tr class="dataTableRow">

              <?php
              if ($counter === 1) {
                ?>
                <td
                  style="padding: 10px;" <?php if (\count($data) > 1) echo 'rowspan="' . \count($data) . '"'; ?>><?php echo '<span class="sitePill">' . $site . '</span> ' . $group; ?></td>
                <?php
              }
              ?>
              <td
                style="padding: 10px;"><?php echo (isset($v['app']) ? '<span class="appPill">' . $v['app'] . '</span> ' : '') . $v['method']; ?></td>
            </tr>

            <?php
          }
        }
        ?>
      </table>
    </td>
  </table>
</div>