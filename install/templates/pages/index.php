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

$configfile_array = [
  CLICSHOPPING::BASE_DIR . 'Conf/global.php',
  CLICSHOPPING::BASE_DIR . 'Conf/ElFinderConfig.php',
  CLICSHOPPING::BASE_DIR . 'Sites/Shop/site_conf.php',
  CLICSHOPPING::BASE_DIR . 'Sites/ClicShoppingAdmin/site_conf.php'
];

foreach ($configfile_array as $key => $f) {
  if (!is_file($f)) {
    continue;
  } elseif (!FileSystem::isWritable($f)) {
    // try to chmod and try again
    @chmod($f, 0777);

    if (!FileSystem::isWritable($f)) {
      continue;
    }
  }

// file exists and is writable
  unset($configfile_array[$key]);
}

$directory_array = [CLICSHOPPING::BASE_DIR . 'Work'];

foreach ($directory_array as $key => $d) {
  if (!is_dir($d)) {
    continue;
  } elseif (!is_writable($d)) {
    continue;
  }

  // Directory exists and is writable
  unset($directory_array[$key]);
}

$warning_array = [];

if (version_compare(phpversion(), '8.4', '<')) {
  $warning_array[] = 'The minimum required PHP version is v8.4+ and Mariadb 11.7 at least. Please ask your host or server administrator to upgrade the PHP version to continue installation.';
}

if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
  $warning_array[] = 'The PDO MySQL driver extension (pdo_mysql) is not installed or enabled in PHP. Please enable it in the PHP configuration to continue installation.';
}

if (!extension_loaded('curl')) {
  $warning_array[] = 'The cURL extension (curl) is not installed or enabled in PHP. Please enable it in the PHP configuration to continue installation.<br />
    You can bypass this process (not recommended) but you can have error more later if you don\'t install Curl. <a href="install.php">Continue the process</a>';
}

if (!extension_loaded('zip')) {
  $warning_array[] = 'The Zip extension (zip) is not installed or enabled in PHP. Please enable it in the PHP configuration to continue installation.<br />
    You can bypass this process (not recommended) but you can have error more later if you don\'t install Zip. <a href="install.php">Continue the process</a>';
}

if (!extension_loaded('soap')) {
  $warning_array[] = 'The soap extension (soap) is not installed or enabled in PHP. Please enable it in the PHP configuration to continue installation.<br />
    You can bypass this process (not recommended) but you can have error more later if you don\'t install soap. <a href="install.php">Continue the process</a>';
}

if (!extension_loaded('xml')) {
  $warning_array[] = 'The xml extension (xml) is not installed or enabled in PHP. Please enable it in the PHP configuration to continue installation.<br />
    You can bypass this process (not recommended) but you can have error more later if you don\'t install soap. <a href="install.php">Continue the process</a>';
}

if (!extension_loaded('json')) {
  $warning_array[] = 'The json extension (json) is not installed or enabled in PHP. Please enable it in the PHP configuration to continue installation.<br />
    You can bypass this process (not recommended) but you can have error more later if you don\'t install soap. <a href="install.php">Continue the process</a>';
}

if (!function_exists('pathinfo')) {
  $warning_array[] = 'The pathinfo function is not enabled in PHP. Please enable it in the PHP configuration to continue installation.<br />';
}

$https_url = 'https://' . $_SERVER['HTTP_HOST'];

if (isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_URI'])) {
  $https_url .= $_SERVER['REQUEST_URI'];
} else {
  $https_url .= $_SERVER['SCRIPT_FILENAME'];
}
?>

<br id="content">
<div class="page-header">
  <div class="container">
    <div class="col-md-1 float-right">
      <form action="index.php" method="get">
        <?php echo HTML::selectMenu('language', $languages_array, $language, 'onChange="this.form.submit();"'); ?>
      </form>
    </div>
    <br /><br />
    <h2><?php echo TEXT_TITLE_WELCOME; ?> <small>v<?php echo CLICSHOPPING::getVersion(); ?></small></h2>
    <p><?php echo TEXT_INTRO_WELCOME; ?></p>
  </div>
</div>

<div class="container">
  <div class="card">
    <div class="card-header"><i class="bi bi-sliders"></i>Please configure your PHP settings to match requirements
      listed below.
    </div>
    <div class="card-body">
      <br>
      <h5><?php echo TEXT_PHP_SETTINGS; ?></h5>
      <table class="table table-bordered">
        <thead>
          <tr>
            <td width="35%"><b>PHP Settings</b></td>
            <td width="25%"><b>Current Settings</b></td>
            <td width="25%"><b>Required Settings</b></td>
            <td width="15%" class="text-center"><b>Status</b></td>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?php echo PHP_VERSION; ?></td>
            <td class="text-center"><?php echo phpversion(); ?></td>
            <td class="text-center">PHP 8.4+</td>
            <td class="text-end">
              <?php echo version_compare(phpversion(), '8.4', '>=') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-danger"></i>'; ?>
            </td>
          </tr>
          <tr>
          <td>File Upload</td>
          <td class="text-center"><?php echo(((int)ini_get('file_uploads') === 0) ? 'Off' : 'On'); ?></td>
          </td>
          <td class="text-center">On</td>
          <td
            class="text-end"><?php echo(((int)ini_get('file_uploads') === 1) ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-danger"></i>'); ?></td>
        </tr>
        </tbody>
      </table>

      <br>
      <div><h5><?php echo TEXT_PHP_EXTENSION; ?></h5></div>
      <table class="table table-bordered">
        <thead>
          <tr>
            <td width="35%"><b>Extension Settingss</b></td>
            <td width="25%"><b>Current Settings</b></td>
            <td width="25%"><b>Required Settings</b></td>
            <td width="15%" class="text-center"><b>Status</b></td>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Mysql / Maria-Db database</td>
            <td class="text-center">
              <?php echo extension_loaded('pdo') && extension_loaded('pdo_mysql') ? 'On' : 'Off'; ?>
            </td>
            <td class="text-center">On</td>
            <td class="text-end">
              <?php echo extension_loaded('pdo') && extension_loaded('pdo_mysql') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-danger"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td>cURL</td>
            <td class="text-center"><?php echo extension_loaded('curl') ? 'On' : 'Off'; ?></td>
            <td class="text-center">On</td>
            <td class="text-end">
              <?php echo extension_loaded('curl') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td>zip</td>
            <td class="text-center"><?php echo extension_loaded('zip') ? 'On' : 'Off'; ?></td>
            <td class="text-center">On</td>
            <td class="text-end">
              <?php echo extension_loaded('zip') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td>Gd</td>
            <td class="text-center"><?php echo extension_loaded('gd') ? 'On' : 'Off'; ?></td>
            <td class="text-center">On</td>
            <td class="text-end">
              <?php echo extension_loaded('gd') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td>OpenSSL</td>
            <td class="text-center"><?php echo extension_loaded('openssl') ? 'On' : 'Off'; ?></td>
            <td class="text-center">On</td>
            <td class="text-end">
              <?php echo extension_loaded('openssl') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td>Soap</td>
            <td class="text-center"><?php echo extension_loaded('soap') ? 'On' : 'Off'; ?></td>
            <td class="text-center">On</td>
            <td class="text-end">
              <?php echo extension_loaded('soap') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td>XML</td>
            <td class="text-center"><?php echo extension_loaded('xml') ? 'On' : 'Off'; ?></td>
            <td class="text-center">On</td>
            <td class="text-end">
              <?php echo extension_loaded('xml') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td>Json</td>
            <td class="text-center"><?php echo extension_loaded('json') ? 'On' : 'Off'; ?></td>
            <td class="text-center">On</td>
            <td class="text-end">
              <?php echo extension_loaded('json') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td>Memcached</td>
            <td class="text-center"><?php echo extension_loaded('memcached') ? 'On' : 'Off'; ?></td>
            <td class="text-center">Optionnal</td>
            <td class="text-end">
              <?php echo extension_loaded('memcached') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td>OPcache</td>
            <td class="text-center"><?php echo extension_loaded('opcache') ? 'On' : 'Off'; ?></td>
            <td class="text-center">Optionnal</td>
            <td class="text-end">
              <?php echo extension_loaded('opcache') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td>Pathinfo</td>
            <td class="text-center"><?php echo function_exists('pathinfo') ? 'On' : 'Off'; ?></td>
            <td class="text-center">On</td>
            <td class="text-end">
              <?php echo function_exists('pathinfo') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>

          <tr>
            <td>exec</td>
            <td class="text-center"><?php echo function_exists('exec') ? 'On' : 'Off'; ?></td>
            <td class="text-center">Optionnal</td>
            <td class="text-end">
              <?php echo function_exists('exec') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>

        </tbody>
      </table>
      <br>
      <div>
        <h5><?php echo TEXT_CHECK_RIGHT; ?></h5>
      </div>
      <table class="table table-bordered">
        <thead>
          <tr>
            <td width="70%"><b>Files</b></td>
            <td width="25%"><b>Status</b></td>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?php echo CLICSHOPPING::BASE_DIR . 'Work'; ?></td>
            <td class="text-end">
              <?php echo is_writable(CLICSHOPPING::BASE_DIR . 'Work') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td><?php echo CLICSHOPPING::BASE_DIR . 'Conf/ElFinderConfig.php'; ?></td>
            <td class="text-end">
              <?php echo is_writable(CLICSHOPPING::BASE_DIR . 'Conf/ElFinderConfig.php') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td><?php echo CLICSHOPPING::BASE_DIR . 'Conf/global.php'; ?></td>
            <td class="text-end">
              <?php echo is_writable(CLICSHOPPING::BASE_DIR . 'Conf/global.php') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td><?php echo CLICSHOPPING::BASE_DIR . 'Sites/ClicShoppingAdmin/conf.php'; ?></td>
            <td class="text-end">
              <?php echo is_writable(CLICSHOPPING::BASE_DIR . 'Sites/ClicShoppingAdmin/site_conf.php') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
          <tr>
            <td><?php echo CLICSHOPPING::BASE_DIR . 'Sites/Shop/conf.php'; ?></td>
            <td class="text-end">
              <?php echo is_writable(CLICSHOPPING::BASE_DIR . 'Sites/Shop/site_conf.php') ? '<i class="bi bi-hand-thumbs-up text-success"></i>' : '<i class="bi bi-exclamation-circle-fill text-warning"></i>'; ?>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>


  <br />
  <div class="mt-1"></div>


  <div class="row">
    <div class="col-md-12">
      <?php
      if (!empty($warning_array)) {
        ?>
        <div class="alert alert-danger" role="alert">
          <p><?php echo TEXT_NOTICE; ?></p>
          <ul style="margin-top: 20px; margin-bottom: 20px;">
            <?php
            foreach ($warning_array as $key => $value) {
              echo '<li>' . $value . '</li>';
            }
            ?>
          </ul>
          <p><i>Changing webserver configuration parameters may require the webserver service to be restarted before the
              changes take affect.</i></p>
        </div>

        <?php
      }

      if (!empty($configfile_array) || !empty($directory_array)) {
        ?>

        <div class="alert alert-danger" role="alert">
          <p><?php echo TEXT_NOT_SAVE_PARAMETERS; ?></p>

          <ul style="margin-top: 20px;">
            <?php
            foreach ($configfile_array as $file) {
              echo '<li>' . FileSystem::displayPath($file) . '</li>';
            }

            foreach ($directory_array as $dir) {
              echo '<li>' . $dir . '</li>';
            }
            ?>
          </ul>
        </div>

        <?php
      }

      if (!empty($configfile_array) || !empty($warning_array) || !empty($directory_array)) {
        ?>

        <p class="text-end"><a href="index.php" class="btn btn-danger" role="button">Retry Installation</a></p>

        <?php
      } else {
        ?>

        <div id="detectHttps" class="alert alert-info" role="alert">
          <p><i class="bi bi-arrow-repeat"></i> Please wait, detecting web server environment..</p>
        </div>

        <div id="jsOn" style="display: none;">
          <p>The web server environment has been verified to proceed with a successful installation and configuration of
            your online store.</p>

          <div id="httpsNotice" style="display: none;">
            <div class="alert alert-warning" role="alert">
              <p><strong>HTTPS Server Detected</strong></p>
              <p>A HTTPS configured web server has been detected. It is recommended to install your online store in a
                secure environment. Please click the following <span class="label label-warning">Reload in HTTPS</span>
                button to reload this installation procedure in HTTPS. If you receive an error, please use your browsers
                back button to return to this page and continue the installation using the <span
                  class="label label-success">Start the Installation Procedure</span> button below.</p>
              <p class="text-end"><a href="<?= $https_url; ?>" class="btn btn-warning btn-sm" role="button">Reload in
                  HTTPS</a></p>
            </div>
          </div>

          <p class="text-end"><a href="install.php" class="btn btn-primary" role="button">Start the Installation
              Procedure</a></p>
        </div>

        <div id="jsOff">
          <p class="text-danger">Please enable Javascript in your browser to be able to start the installation
            procedure.</p>
          <p class="text-end"><a href="index.php" class="btn btn-danger" role="button">Retry Installation</a></p>
        </div>

        <script>
          $(function () {
            $('#jsOff').hide();

            if (document.location.protocol == 'https:') {
              $('#detectHttps').hide();
              $('#jsOn').show();
            } else {
              var httpsCheckUrl = 'rpc.php?action=httpsCheck';

              $.post(httpsCheckUrl, null, function (response) {
                if (('status' in response) && ('message' in response)) {
                  if ((response.status == '1') && (response.message == 'success')) {
                    $('#detectHttps').hide();
                    $('#httpsNotice').show();
                    $('#jsOn').show();
                  } else {
                    $('#detectHttps').hide();
                    $('#jsOn').show();
                  }
                } else {
                  $('#detectHttps').hide();
                  $('#jsOn').show();
                }
              }, 'json').fail(function () {
                $('#detectHttps').hide();
                $('#jsOn').show();
              });
            }
          });
        </script>

        <?php
      }
      ?>
    </div>
  </div>
</div>
</div>