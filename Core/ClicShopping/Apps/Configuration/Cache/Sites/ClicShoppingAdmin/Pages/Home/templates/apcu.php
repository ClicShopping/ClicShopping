<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Cache\Classes\ClicShoppingAdmin\CacheAdmin;

$CLICSHOPPING_Cache = Registry::get('Cache');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$sapi = CacheAdmin::getApcuSapiState();
$info = CacheAdmin::getApcuInfo();

$yes = $CLICSHOPPING_Cache->getDef('text_yes');
$no = $CLICSHOPPING_Cache->getDef('text_no');
$unknown = $CLICSHOPPING_Cache->getDef('text_unknown');

$state = function (?array $s) use ($yes, $no, $unknown) {
  if ($s === null) {
    return [$unknown, $unknown, $unknown, $unknown];
  }

  $flag = fn($v) => filter_var((string)$v, FILTER_VALIDATE_BOOLEAN) ? $yes : $no;

  return [
    $s['loaded'] ? $yes : $no,
    $flag($s['enabled']),
    $flag($s['enable_cli']),
    $s['usable'] ? $yes : $no
  ];
};
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row align-items-center">
          <div class="col-md-1 logoHeading">
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/cache.gif', $CLICSHOPPING_Cache->getDef('heading_title'), '40', '40'); ?>
          </div>
          <div class="col-md-5 pageHeading">
            <?php echo '&nbsp;' . $CLICSHOPPING_Cache->getDef('heading_title'); ?>
          </div>
          <div class="col-md-6 text-end">
            <div class="btn-group float-end" role="group">
              <?php
              echo HTML::form('apcu', $CLICSHOPPING_Cache->link('Cache&ResetApcu'));
              echo HTML::button($CLICSHOPPING_Cache->getDef('text_reset_apcu'), null, null, 'danger');
              ?>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="alert alert-info"><?php echo $CLICSHOPPING_Cache->getDef('text_apcu_is_local'); ?></div>

  <?php
  if (!defined('USE_APCU') || USE_APCU != 'True') {
    ?>
    <div class="alert alert-warning"><?php echo $CLICSHOPPING_Cache->getDef('text_apcu_switch_off'); ?></div>
    <?php
  }

  if ($sapi['cli'] === null) {
    ?>
    <div class="alert alert-warning"><?php echo $CLICSHOPPING_Cache->getDef('text_apcu_cli_unknown'); ?></div>
    <?php
  }
  ?>

  <div class="row">
    <div class="col-md-12">
      <div class="card">
        <div class="card-header"><?php echo $CLICSHOPPING_Cache->getDef('heading_apcu_sapi'); ?></div>
        <div class="card-block table-responsive">
          <table class="table table-hover">
            <thead>
            <tr class="dataTableHeadingRow">
              <th><?php echo $CLICSHOPPING_Cache->getDef('text_sapi'); ?></th>
              <th><?php echo $CLICSHOPPING_Cache->getDef('text_extension_loaded'); ?></th>
              <th>apc.enabled</th>
              <th>apc.enable_cli</th>
              <th><?php echo $CLICSHOPPING_Cache->getDef('text_apcu_usable'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach (['web' => PHP_SAPI, 'cli' => 'cli'] as $k => $label) {
              $row = $state($sapi[$k]);
              ?>
              <tr>
                <td><?php echo HTML::outputProtected($label); ?></td>
                <td><?php echo $row[0]; ?></td>
                <td><?php echo $row[1]; ?></td>
                <td><?php echo $row[2]; ?></td>
                <td><?php echo $row[3]; ?></td>
              </tr>
              <?php
            }
            ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="mt-1"></div>

      <div class="card">
        <div class="card-header"><?php echo $CLICSHOPPING_Cache->getDef('heading_apcu_memory'); ?></div>
        <div class="card-block table-responsive">
          <?php
          if ($info === false) {
            ?>
            <div class="alert alert-warning"><?php echo $CLICSHOPPING_Cache->getDef('text_apcu_not_available'); ?></div>
            <?php
          } else {
            ?>
            <table class="table table-hover">
              <thead>
              <tr class="dataTableHeadingRow">
                <th><?php echo $CLICSHOPPING_Cache->getDef('text_statistic'); ?></th>
                <th><?php echo $CLICSHOPPING_Cache->getDef('text_value'); ?></th>
              </tr>
              </thead>
              <tbody>
              <tr>
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_used_memory'); ?></td>
                <td><?php echo number_format($info['used'] / 1024 / 1024, 2) . ' MB'; ?></td>
              </tr>
              <tr>
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_total_memory'); ?></td>
                <td><?php echo number_format($info['total'] / 1024 / 1024, 2) . ' MB'; ?></td>
              </tr>
              <tr>
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_apcu_entries'); ?></td>
                <td><?php echo number_format($info['entries']); ?></td>
              </tr>
              <tr>
                <td>Hits</td>
                <td><?php echo number_format($info['hits']); ?></td>
              </tr>
              <tr>
                <td>Misses</td>
                <td><?php echo number_format($info['misses']); ?></td>
              </tr>
              <tr>
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_apcu_hit_ratio'); ?></td>
                <td><?php echo round($info['hit_ratio'] * 100, 2) . ' %'; ?></td>
              </tr>
              </tbody>
            </table>
            <?php
          }
          ?>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="py-4"></div>
