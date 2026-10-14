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

  $CLICSHOPPING_Memcached = CacheAdmin::getMemcached();

  if ($CLICSHOPPING_Memcached && method_exists($CLICSHOPPING_Memcached, 'getStats')) {
    $stats = $CLICSHOPPING_Memcached->getStats();
    $memcache_available = is_array($stats) && count($stats) > 0;

    if (isset($_POST['reset_memcache'])) {
      $CLICSHOPPING_Memcached->flush();
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Cache->getDef('text_memcache_flushed'), 'success');
    }
  } else {
    $stats = [];
    $memcache_available = false;
    $CLICSHOPPING_MessageStack->add('main', $CLICSHOPPING_Cache->getDef('text_memcache_error'), 'error');
  }
?>
<div class="contentBody">
  <div class="row">
    <div class="col-md-12">
      <div class="card card-block headerCard">
        <div class="row align-items-center">
          <div class="col-md-1 logoHeading">
            <?php echo HTML::image($CLICSHOPPING_Template->getImageDirectory() . 'categories/cache.gif', $CLICSHOPPING_Cache->getDef('heading_title'), '40', '40'); ?>
          </div>
          <div class="col-md-2 pageHeading">
            <?php echo '&nbsp;' . $CLICSHOPPING_Cache->getDef('heading_title'); ?>
          </div>
          <div class="col-md-6 text-center">
            <?php
            if ($memcache_available) {
              foreach ($stats as $server => $value) {
                $hits = $value['get_hits'];
                $misses = $value['get_misses'];
                $used_memory_mb = $value['bytes'] / 1024 / 1024;
                $total_memory_mb = $value['limit_maxbytes'] / 1024 / 1024;

                $hit_ratio = $hits + $misses > 0 ? $hits / ($hits + $misses) : 0;
                $hit_status = 'Mauvais';
                if ($hit_ratio >= 0.95) {
                  $hit_status = 'Très bon';
                } elseif ($hit_ratio >= 0.85) {
                  $hit_status = 'Bon';
                }

                $memory_status = ($used_memory_mb >= $total_memory_mb * 0.95) ? 'Saturée' : 'OK';

                echo '<span class="badge bg-info">Serveur : ' . $server . '</span> ';
                echo '<span class="badge bg-secondary">Taux : ' . round($hit_ratio * 100, 2) . '% - ' . $hit_status . '</span> ';
                echo '<span class="badge bg-secondary">Mémoire : ' . round($used_memory_mb, 2) . 'MB / ' . round($total_memory_mb, 2) . 'MB - ' . $memory_status . '</span>';
              }
            }
            ?>
          </div>
          <div class="col-md-3 text-end">
            <div class="btn-group float-end" role="group">
              <?php
              echo HTML::form('memcached', $CLICSHOPPING_Cache->link('Cache&ResetMemcached'));
              echo HTML::button($CLICSHOPPING_Cache->getDef('text_reset_memcached'), null, null, 'danger');
              ?>
              </form>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
  <div class="mt-1"></div>
  <?php
  if (defined('USE_MEMCACHED') && USE_MEMCACHED === 'False') {
    ?>
      <div class="alert alert-warning">
        <?php echo $CLICSHOPPING_Cache->getDef('text_memcache_not_available'); ?>
      </div>
    <?php
  }
  ?>
  <div class="row">
    <div class="col-md-12">
      <?php
      if ($memcache_available): ?>
        <div class="card">
          <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
              <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab1" role="tab"><?php echo $CLICSHOPPING_Cache->getDef('heading_memory_usage'); ?></a></li>
              <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab2" role="tab"><?php echo $CLICSHOPPING_Cache->getDef('heading_cache_statistics'); ?></a></li>
            </ul>
          </div>

          <div class="card-block">
            <div class="tab-content">
              <div class="tab-pane active" id="tab1" role="tabpanel">
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                    <tr class="dataTableHeadingRow">
                      <th><?php echo $CLICSHOPPING_Cache->getDef('text_memory_type'); ?></th>
                      <th><?php echo $CLICSHOPPING_Cache->getDef('text_value'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                      foreach ($stats as $server => $data) {
                      ?>
                      <tr>
                        <td>Server</td>
                        <td><?php echo $server; ?></td>
                      </tr>
                      <tr>
                        <td><?php echo $CLICSHOPPING_Cache->getDef('text_used_memory'); ?></td>
                        <td><?php echo number_format($data['bytes'] / 1024 / 1024, 2) . ' MB'; ?></td>
                      </tr>
                      <tr>
                        <td><?php echo $CLICSHOPPING_Cache->getDef('text_total_memory'); ?></td>
                        <td><?php echo number_format($data['limit_maxbytes'] / 1024 / 1024, 2) . ' MB'; ?></td>
                      </tr>
                      <?php
                      }
                      ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <div class="tab-pane" id="tab2" role="tabpanel">
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                    <tr class="dataTableHeadingRow">
                      <th><?php echo $CLICSHOPPING_Cache->getDef('text_statistic'); ?></th>
                      <th><?php echo $CLICSHOPPING_Cache->getDef('text_value'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    foreach ($stats as $server => $value) {
                      ?>
                      <tr>
                        <td>Hits</td>
                        <td><?php echo number_format($value['get_hits']); ?></td>
                      </tr>
                      <tr>
                        <td>Misses</td>
                        <td><?php echo number_format($value['get_misses']); ?></td>
                      </tr>
                      <tr>
                        <td>Evictions</td>
                        <td><?php echo number_format($value['evictions']); ?></td>
                      </tr>
                      <tr>
                        <td>Total Items</td>
                        <td><?php echo number_format($value['total_items']); ?></td>
                      </tr>
                    <?php
                    }
                    ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="alert alert-warning">
          <?php echo $CLICSHOPPING_Cache->getDef('text_memcache_not_available'); ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
