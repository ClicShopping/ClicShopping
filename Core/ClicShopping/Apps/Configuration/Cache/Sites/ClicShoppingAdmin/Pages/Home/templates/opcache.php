<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

//https://github.com/amnuts/opcache-gui
use ClicShopping\Apps\Configuration\Cache\Classes\ClicShoppingAdmin\CacheAdmin;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use Amnuts\Opcache\Service;

$CLICSHOPPING_Cache = Registry::get('Cache');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$CLICSHOPPING_Template = Registry::get('TemplateAdmin');
$CLICSHOPPING_MessageStack = Registry::get('MessageStack');
$cache_files = [];


// specify any options you want different from the defaults, if any
$options = [
  'allow_filelist'   => true,                // show/hide the files tab
  'allow_invalidate' => true,                // give a link to invalidate files
  'allow_reset'      => true,                // give option to reset the whole cache
  'allow_realtime'   => true,                // give option to enable/disable real-time updates
  'refresh_time'     => 15,                   // how often the data will refresh, in seconds
  'size_precision'   => 2,                   // Digits after decimal point
  'size_space'       => false,               // have '1MB' or '1 MB' when showing sizes
  'charts'           => false,                // show gauge chart or just big numbers
  'debounce_rate'    => 250,                 // milliseconds after key press to send keyup event when filtering
  'per_page'         => 200,                 // How many results per page to show in the file list, false for no pagination
  'cookie_name'      => 'opcachegui',        // name of cookie
  'cookie_ttl'       => 365,                 // days to store cookie
  'datetime_format'  => 'D, d M Y H:i:s O',  // Show datetime in this format
  'highlight'        => [
    'memory' => true,                      // show the memory chart/big number
    'hits'   => true,                      // show the hit rate chart/big number
    'keys'   => true,                      // show the keys used chart/big number
    'jit'    => true                       // show the jit buffer chart/big number
  ],
  // json structure of all text strings used, or null for default
  'language_pack'    => null
];
if ($CLICSHOPPING_MessageStack->exists('main')) {
  echo $CLICSHOPPING_MessageStack->get('main');
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
          <span class="col-md-7 text-end">
            <div class="btn-group float-end" role="group">
              <?php
                $realtime = isset($_SESSION['opcache_realtime']) ? $_SESSION['opcache_realtime'] : false;
                $button_class = $realtime ? 'btn-success' : 'btn-primary';
              ?>
              <form action="<?php echo $CLICSHOPPING_Cache->link('Cache&ToggleRealtime'); ?>" method="post" class="d-inline">
                <button type="submit" class="btn <?php echo $button_class; ?>" name="realtime_update">
                  <?php echo $CLICSHOPPING_Cache->getDef($realtime ? 'text_disable_realtime' : 'text_enable_realtime'); ?>&nbsp;&nbsp;
                </button>
              </form>&nbsp;&nbsp;

              <?php
              echo HTML::form('reset', $CLICSHOPPING_Cache->link('Cache&ResetOpCache'), 'post');
              echo HTML::button($CLICSHOPPING_Cache->getDef('text_reset_opcache'), null, null, 'danger');
              ?>
              </form>
            </div>

            <?php
            if ($realtime) {
              echo '<meta http-equiv="refresh" content="15">';
            }
            ?>
          </span>
        </div>
      </div>
    </div>
  </div>
  <div class="mt-1"></div>
    <?php
    if(CacheAdmin::checkOpCache() === false) {
      $opcache = new Service($options);
      $data = $opcache->getData();
    } else {
      $data = [
        'version' => [
          'php' => PHP_VERSION,
          'version' => phpversion('Zend OPcache')
        ],
        'memory_usage' => [
          'used_memory' => 0,
          'free_memory' => 0,
          'wasted_memory' => 0
        ],
        'opcache_statistics' => [
          'start_time' => 0,
          'oom_restarts' => 0,
          'hits' => 0,
          'misses' => 0,
          'num_cached_scripts' => 0,
          'num_cached_keys' => 0
        ],
        'directives' => []
      ];
    }
    ?>



  <!-- //################################################################################################################ -->
  <!-- //                                             LISTING                                                            -->
  <!-- //################################################################################################################ -->
  <div class="row">
    <div class="col-md-12">
      <?php
     if (is_array($data) || !empty($data)) {
       ?>
       <div class="separator"></div>
       <div class="row">
         <div class="col-md-12">
           <div class="card">
             <div class="card-header">
               <ul class="nav nav-tabs card-header-tabs" role="tablist">
                 <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab1" role="tab"><?php echo $CLICSHOPPING_Cache->getDef('heading_memory_usage'); ?></a></li>
                 <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab2" role="tab"><?php echo $CLICSHOPPING_Cache->getDef('heading_cache_statistics'); ?></a></li>
                 <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab3" role="tab"><?php echo $CLICSHOPPING_Cache->getDef('heading_configuration'); ?></a></li>
                 <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab4" role="tab"><?php echo $CLICSHOPPING_Cache->getDef('heading_installation_guide'); ?></a></li>
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
                       <tr>
                         <td><?php echo $CLICSHOPPING_Cache->getDef('text_start_time'); ?></td>
                         <td>
                           <?php
                           echo isset($data['opcache_statistics']['start_time']) ?
                             date('Y-m-d H:i:s', $data['opcache_statistics']['start_time']) : 'N/A';
                           ?>
                         </td>
                       </tr>
                       <tr>
                         <td><?php echo $CLICSHOPPING_Cache->getDef('text_restart_count'); ?></td>
                         <td>
                           <?php
                           echo isset($data['opcache_statistics']['oom_restarts']) ?
                             $data['opcache_statistics']['oom_restarts'] : 'N/A';
                           ?>
                         </td>
                       </tr>
                       <tr>
                         <td><?php echo $CLICSHOPPING_Cache->getDef('text_used_memory'); ?></td>
                         <td>
                           <?php echo isset($data['memory_usage']['used_memory']) ? number_format($data['memory_usage']['used_memory'] / 1024 / 1024, 2) . ' MB' : 'N/A'; ?>
                         </td>
                       </tr>
                       <tr>
                         <td><?php echo $CLICSHOPPING_Cache->getDef('text_free_memory'); ?></td>
                         <td>
                           <?php echo isset($data['memory_usage']['free_memory']) ?number_format($data['memory_usage']['free_memory'] / 1024 / 1024, 2) . ' MB' : 'N/A'; ?>
                         </td>
                       </tr>
                       <tr>
                         <td><?php echo $CLICSHOPPING_Cache->getDef('text_wasted_memory'); ?></td>
                         <td>
                           <?php
                           echo isset($data['memory_usage']['wasted_memory']) ? number_format($data['memory_usage']['wasted_memory'] / 1024 / 1024, 2) . ' MB' : 'N/A'; ?>
                         </td>
                       </tr>

                       <tr>
                         <td><?php echo $CLICSHOPPING_Cache->getDef('text_wasted_percentage'); ?></td>
                         <td>
                           <?php
                           if (isset($data['memory_usage']['wasted_memory']) && isset($data['memory_usage']['used_memory'])) {
                             $total = $data['memory_usage']['used_memory'] + $data['memory_usage']['free_memory'];
                             echo $total > 0 ? number_format($data['memory_usage']['wasted_memory'] * 100 / $total, 2) . '%' : 'N/A';
                           } else {
                             echo 'N/A';
                           }
                           ?>
                         </td>
                       </tr>
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
                      <tr>
                        <td><?php echo $CLICSHOPPING_Cache->getDef('text_cache_hits'); ?></td>
                        <td>
                          <?php echo isset($data['opcache_statistics']['hits']) ? number_format($data['opcache_statistics']['hits']) : 'N/A'; ?>
                        </td>
                      </tr>
                      <tr>
                        <td><?php echo $CLICSHOPPING_Cache->getDef('text_cache_misses'); ?></td>
                        <td>
                          <?php echo isset($data['opcache_statistics']['misses']) ? number_format($data['opcache_statistics']['misses']) : 'N/A'; ?>
                        </td>
                      </tr>
                      <tr>
                        <td><?php echo $CLICSHOPPING_Cache->getDef('text_cached_scripts'); ?></td>
                        <td>
                          <?php echo isset($data['opcache_statistics']['num_cached_scripts']) ? number_format($data['opcache_statistics']['num_cached_scripts']) : 'N/A'; ?>
                        </td>
                      </tr>
                      <tr>
                        <td><?php echo $CLICSHOPPING_Cache->getDef('text_cached_keys'); ?></td>
                        <td>
                          <?php echo isset($data['opcache_statistics']['num_cached_keys']) ? number_format($data['opcache_statistics']['num_cached_keys']) : 'N/A'; ?>
                        </td>
                      </tr>

                       </tbody>
                     </table>
                   </div>
                 </div>

                 <div class="tab-pane" id="tab3" role="tabpanel">
                   <div class="table-responsive">
                     <table class="table table-hover">
                       <thead>
                         <tr class="dataTableHeadingRow">
                           <th><?php echo $CLICSHOPPING_Cache->getDef('text_setting'); ?></th>
                           <th><?php echo $CLICSHOPPING_Cache->getDef('text_value'); ?></th>
                         </tr>
                       </thead>
                       <tbody>
                       <tr>
                         <td><?php echo $CLICSHOPPING_Cache->getDef('text_php_version'); ?></td>
                         <td><?php echo $data['version']['php']; ?></td>
                       </tr>
                       <tr>
                         <td><?php echo $CLICSHOPPING_Cache->getDef('text_opcache_version'); ?></td>
                         <td><?php echo $data['version']['version']; ?></td>
                       </tr>
                       <tr>
                         <td><?php echo $CLICSHOPPING_Cache->getDef('text_enabled'); ?></td>
                         <td>
                           <?php
                           $enabled = 'Unknown';
                           foreach ($data['directives'] as $item) {
                             if ($item['k'] === 'opcache.enable') {
                               $enabled = $item['v'] ? 'Yes' : 'No';
                               break;
                             }
                           }
                           echo $enabled;
                           ?>
                         </td>
                       </tr>
                       <tr>
                         <td><?php echo $CLICSHOPPING_Cache->getDef('text_memory_consumption'); ?></td>
                         <td>
                           <?php
                           foreach ($data['directives'] as $item) {
                             if ($item['k'] === 'opcache.memory_consumption' && is_numeric($item['v'])) {
                               echo number_format($item['v'] / 1024 / 1024, 2) . ' MB';
                               break;
                             }
                           }
                           ?>
                         </td>
                       </tr>

                       <tr>
                         <td><?php echo $CLICSHOPPING_Cache->getDef('text_jit_enabled'); ?></td>
                         <td>
                           <?php
                           foreach ($data['directives'] as $item) {
                             if ($item['k'] === 'opcache.jit') {
                               echo $item['v'] !== 'off' && $item['v'] !== '0' ? 'Yes' : 'No';
                               break;
                             }
                           }
                           ?>
                         </td>
                       </tr>

                       <tr>
                         <td><?php echo $CLICSHOPPING_Cache->getDef('text_jit_buffer_size'); ?></td>
                         <td>
                           <?php
                           foreach ($data['directives'] as $item) {
                             if ($item['k'] === 'opcache.jit_buffer_size') {
                               echo number_format($item['v'] / 1024 / 1024, 2) . ' MB';
                               break;
                             }
                           }
                           ?>
                         </td>
                       </tr>

                       </tbody>
                     </table>
                   </div>
                 </div>

                 <div class="tab-pane" id="tab4" role="tabpanel">
                   <div class="table-responsive">
                     <table class="table table-hover">
                       <thead>
                       <tr class="dataTableHeadingRow">
                         <th colspan="2"><?php echo $CLICSHOPPING_Cache->getDef('text_config_instructions'); ?></th>
                       </tr>
                       </thead>
                       <tbody>
                       <tr>
                         <td colspan="2">
                           <strong>1. <?php echo $CLICSHOPPING_Cache->getDef('text_edit_php_ini'); ?></strong><br><br>
                           <?php echo $CLICSHOPPING_Cache->getDef('text_config_instructions'); ?>:<br>
                             <span>
                              [opcache]<br><br>
                              opcache.enable=1              ; Enable Opcache<br>
                              opcache.enable_cli=1          ; Enable Opcache for PHP CLI<br>
                              opcache.memory_consumption=128 ; Memory size in MB for Opcache<br>
                              opcache.interned_strings_buffer=8  ; Memory size for interned strings<br>
                              opcache.max_accelerated_files=4000 ; Maximum number of files in cache<br>
                              opcache.revalidate_freq=60    ; How often to check script timestamps<br>
                              opcache.fast_shutdown=1       ; Fast shutdown for better memory management<br>
                              opcache.jit_buffer_size=100M  ; JIT buffer size<br>
                              opcache.jit=tracing           ; JIT compilation mode<br>
                             </span>
                         </td>
                       </tr>
                       <tr>
                         <td colspan="2">

                           <strong>2. <?php echo $CLICSHOPPING_Cache->getDef('text_restart_server'); ?></strong><br><br>
                           <?php echo $CLICSHOPPING_Cache->getDef('text_config_instructions'); ?>:<br>
                             <span>
                              # For Apache:<br><br>
                              sudo systemctl restart apache2<br><br>

                              # For Nginx with PHP-FPM:<br>
                              sudo systemctl restart nginx<br>
                              sudo systemctl restart php-fpm<br><br>

                              <?php echo $CLICSHOPPING_Cache->getDef('text_info'); ?><br>
                            </span>

                         </td>
                       </tr>
                       </tbody>
                     </table>
                   </div>
                 </div>

               </div>
             </div>
           </div>
         </div>
       </div>
       <?php
     } else {
       ?>
       <div class="alert alert-warning">
         <?php echo $CLICSHOPPING_Cache->getDef('text_opcache_not_available'); ?>
       </div>
       <?php
     }
     ?>
    </div>
  </div>
</div>