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
use ClicShopping\Apps\Configuration\Cache\Classes\ClicShoppingAdmin\CacheAdmin;

$CLICSHOPPING_Cache = Registry::get('Cache');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');

$info = CacheAdmin::getRedisInfo();
$store_sessions = CLICSHOPPING::configExists('store_sessions') ? CLICSHOPPING::getConfig('store_sessions') : '';
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
              echo HTML::form('redis', $CLICSHOPPING_Cache->link('Cache&ResetRedis'));
              echo HTML::button($CLICSHOPPING_Cache->getDef('text_reset_redis'), null, null, 'danger');
              ?>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>

  <div class="alert alert-danger"><?php echo $CLICSHOPPING_Cache->getDef('text_reset_redis_warning'); ?></div>

  <?php
  if (!defined('USE_REDIS') || USE_REDIS != 'True') {
    ?>
    <div class="alert alert-warning"><?php echo $CLICSHOPPING_Cache->getDef('text_redis_switch_off'); ?></div>
    <?php
  }

  if ($info === false) {
    ?>
    <div class="alert alert-warning"><?php echo $CLICSHOPPING_Cache->getDef('text_redis_not_available'); ?></div>
    <?php
  } else {
    if ($info['maxmemory'] === 0 || $info['maxmemory_policy'] === 'noeviction') {
      ?>
      <div class="alert alert-warning"><?php echo $CLICSHOPPING_Cache->getDef('text_redis_no_eviction'); ?></div>
      <?php
    }
    ?>
    <div class="row">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header"><?php echo $CLICSHOPPING_Cache->getDef('heading_redis_server'); ?></div>
          <div class="card-block table-responsive">
            <table class="table table-hover">
              <thead>
              <tr class="dataTableHeadingRow">
                <th><?php echo $CLICSHOPPING_Cache->getDef('text_statistic'); ?></th>
                <th><?php echo $CLICSHOPPING_Cache->getDef('text_value'); ?></th>
              </tr>
              </thead>
              <tbody>
              <tr>
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_redis_version'); ?></td>
                <td><?php echo HTML::outputProtected($info['version']); ?></td>
              </tr>
              <tr>
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_used_memory'); ?></td>
                <td><?php echo number_format($info['used_memory'] / 1024 / 1024, 2) . ' MB'; ?></td>
              </tr>
              <tr>
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_redis_maxmemory'); ?></td>
                <td><?php echo $info['maxmemory'] === 0 ? $CLICSHOPPING_Cache->getDef('text_redis_unlimited') : number_format($info['maxmemory'] / 1024 / 1024, 2) . ' MB'; ?></td>
              </tr>
              <tr>
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_redis_policy'); ?></td>
                <td><?php echo HTML::outputProtected($info['maxmemory_policy']); ?></td>
              </tr>
              <tr>
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_redis_keys'); ?></td>
                <td><?php echo number_format($info['keys']); ?></td>
              </tr>
              <tr>
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_redis_clients'); ?></td>
                <td><?php echo number_format($info['clients']); ?></td>
              </tr>
              <tr>
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_redis_uptime'); ?></td>
                <td><?php echo number_format($info['uptime'] / 86400, 1) . ' ' . $CLICSHOPPING_Cache->getDef('text_redis_days'); ?></td>
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
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_redis_hit_ratio'); ?></td>
                <td><?php echo round($info['hit_ratio'] * 100, 2) . ' %'; ?></td>
              </tr>
              <tr>
                <td><?php echo $CLICSHOPPING_Cache->getDef('text_redis_store_sessions'); ?></td>
                <td><?php echo HTML::outputProtected($store_sessions); ?></td>
              </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <?php
  }
  ?>
</div>
<div class="py-4"></div>
