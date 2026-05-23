<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Cache;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Db;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Language;
use ClicShopping\OM\Registry;

Cache::clearAll();

$CLICSHOPPING_Db = Db::initialize($_POST['DB_SERVER'], $_POST['DB_SERVER_USERNAME'], $_POST['DB_SERVER_PASSWORD'], $_POST['DB_DATABASE']);
Registry::set('Db', $CLICSHOPPING_Db);

$CLICSHOPPING_Db->setTablePrefix($_POST['DB_TABLE_PREFIX']);

$Qcfg = $CLICSHOPPING_Db->get('configuration', [
    'configuration_key as k',
    'configuration_value as v'
  ]
);

while ($Qcfg->fetch()) {
  define($Qcfg->value('k'), $Qcfg->value('v'));
}

$CLICSHOPPING_Language = new Language();
Registry::set('Language', $CLICSHOPPING_Language);

$CLICSHOPPING_Db->save('configuration', ['configuration_value' => HTML::sanitize($_POST['CFG_STORE_NAME'])], ['configuration_key' => 'STORE_NAME']);
$CLICSHOPPING_Db->save('configuration', ['configuration_value' => HTML::sanitize($_POST['CFG_STORE_OWNER_NAME'])], ['configuration_key' => 'STORE_OWNER']);
$CLICSHOPPING_Db->save('configuration', ['configuration_value' => $_POST['CFG_STORE_OWNER_EMAIL_ADDRESS']], ['configuration_key' => 'STORE_OWNER_EMAIL_ADDRESS']);

if (!empty($_POST['CFG_STORE_OWNER_NAME']) && !empty($_POST['CFG_STORE_OWNER_EMAIL_ADDRESS'])) {
  $CLICSHOPPING_Db->save('configuration', ['configuration_value' => '"' . trim($_POST['CFG_STORE_OWNER_NAME']) . '" <' . trim($_POST['CFG_STORE_OWNER_EMAIL_ADDRESS']) . '>'], ['configuration_key' => 'EMAIL_FROM']);
} else {
  $CLICSHOPPING_Db->save('configuration', ['configuration_value' => $_POST['CFG_STORE_OWNER_EMAIL_ADDRESS']], ['configuration_key' => 'EMAIL_FROM']);
}

$CLICSHOPPING_Db->save('configuration', ['configuration_value' => '"' . trim($_POST['CFG_STORE_OWNER_EMAIL_ADDRESS']) . '" <' . trim($_POST['CFG_STORE_OWNER_EMAIL_ADDRESS']) . '>'], ['configuration_key' => 'SEND_EXTRA_ORDER_EMAILS_TO']);

if (!empty($_POST['CFG_ADMINISTRATOR_USERNAME'])) {
  $Qcheck = $CLICSHOPPING_Db->prepare('select user_name
                                         from :table_administrators
                                         where user_name = :user_name
                                      ');

  $Qcheck->bindValue(':user_name', $_POST['CFG_ADMINISTRATOR_USERNAME']);
  $Qcheck->execute();

  if ($Qcheck->fetch() !== false) {
    $sql_array = [
      'user_password' => Hash::encrypt(trim($_POST['CFG_ADMINISTRATOR_PASSWORD'])),
      'date_added' => 'now()'
    ];

    $CLICSHOPPING_Db->save('administrators', $sql_array, ['user_name' => $_POST['CFG_ADMINISTRATOR_USERNAME']]);
  } else {
    $update_array = [
      'user_name' => $_POST['CFG_ADMINISTRATOR_USERNAME'],
      'user_password' => Hash::encrypt(trim($_POST['CFG_ADMINISTRATOR_PASSWORD'])),
      'name' => HTML::sanitize($_POST['CFG_ADMINISTRATOR_NAME']),
      'first_name' => HTML::sanitize($_POST['CFG_ADMINISTRATOR_FIRSTNAME']),
      'access' => '1',
      'date_added' => 'now()'
    ];

    $CLICSHOPPING_Db->save('administrators', $update_array);
  }
}

if (!empty($_POST['CFG_SMTP_HOST']) && !empty($_POST['CFG_SMTP_USER_NAME']) && !empty($_POST['CFG_SMTP_PASSWORD'])) {
  $smtp_port = HTML::sanitize($_POST['CFG_SMTP_PORT']);

  if ($smtp_port == '25') {
    $CLICSHOPPING_Db->save('configuration', ['configuration_value' => 'no'], ['configuration_key' => 'EMAIL_SMTP_SECURE']);
  } elseif ($smtp_port == '465') {
    $CLICSHOPPING_Db->save('configuration', ['configuration_value' => 'ssl'], ['configuration_key' => 'EMAIL_SMTP_SECURE']);
  } else {
    $CLICSHOPPING_Db->save('configuration', ['configuration_value' => 'tls'], ['configuration_key' => 'EMAIL_SMTP_SECURE']);
  }

  $CLICSHOPPING_Db->save('configuration', ['configuration_value' => $smtp_port], ['configuration_key' => 'EMAIL_SMTP_PORT']);

  $email_transport = HTML::sanitize($_POST['CFG_SMTP_EMAIL_TRANSORT']);


  if ($smtp_port == 'smtp') {
    $CLICSHOPPING_Db->save('configuration', ['configuration_value' => 'smtp'], ['configuration_key' => 'EMAIL_TRANSPORT']);
  } elseif ($smtp_port == 'gmail') {
    $CLICSHOPPING_Db->save('configuration', ['configuration_value' => 'gmail'], ['configuration_key' => 'EMAIL_TRANSPORT']);
  } else {
    $CLICSHOPPING_Db->save('configuration', ['configuration_value' => 'sendmail'], ['configuration_key' => 'EMAIL_TRANSPORT']);
  }

  $CLICSHOPPING_Db->save('configuration', ['configuration_value' => HTML::sanitize($_POST['CFG_SMTP_HOST'])], ['configuration_key' => 'EMAIL_SMTP_HOSTS']);
  $CLICSHOPPING_Db->save('configuration', ['configuration_value' => trim($_POST['CFG_SMTP_USER_NAME'])], ['configuration_key' => 'EMAIL_SMTP_USER']);
  $CLICSHOPPING_Db->save('configuration', ['configuration_value' => trim($_POST['CFG_SMTP_PASSWORD'])], ['configuration_key' => 'EMAIL_SMTP_PASSWORD']);
}


if (FileSystem::isWritable(CLICSHOPPING::BASE_DIR . 'Work')) {
  if (!is_dir(Cache::getPath())) {
    if (!mkdir($concurrentDirectory = Cache::getPath(), 0777) && !is_dir($concurrentDirectory)) {
      throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
    }
  }
}

if (FileSystem::isWritable(CLICSHOPPING::BASE_DIR . 'Work/Temp')) {
  if (!is_dir(CLICSHOPPING::BASE_DIR . 'Work/Temp')) {
    if (!mkdir($concurrentDirectory = CLICSHOPPING::BASE_DIR . 'Work/Temp', 0777) && !is_dir($concurrentDirectory)) {
      throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
    }
  }
}

if (FileSystem::isWritable(CLICSHOPPING::BASE_DIR . 'Work/Log')) {
  if (!is_dir(CLICSHOPPING::BASE_DIR . 'Work/Log')) {
    if (!mkdir($concurrentDirectory = CLICSHOPPING::BASE_DIR . 'Work/Log', 0777) && !is_dir($concurrentDirectory)) {
      throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
    }
  }
}

if (FileSystem::isWritable(CLICSHOPPING::BASE_DIR . 'Work/Session')) {
  if (!is_dir(CLICSHOPPING::BASE_DIR . 'Work/Session')) {
    if (!mkdir($concurrentDirectory = CLICSHOPPING::BASE_DIR . 'Work/Session', 0777) && !is_dir($concurrentDirectory)) {
      throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
    }
  }
}

foreach (glob(Cache::getPath() . '*.cache') as $c) {
  unlink($c);
}

$dir_fs_document_root = $_POST['DIR_FS_DOCUMENT_ROOT'];
if ((substr($dir_fs_document_root, -1) != '\\') && (substr($dir_fs_document_root, -1) != '/')) {
  if (strrpos($dir_fs_document_root, '\\') !== false) {
    $dir_fs_document_root .= '\\';
  } else {
    $dir_fs_document_root .= '/';
  }
}

$http_url = parse_url($_POST['HTTP_WWW_ADDRESS']);
$http_server = $http_url['scheme'] . '://' . $http_url['host'];
$http_catalog = $http_url['path'];

if (isset($http_url['port']) && !empty($http_url['port'])) {
  $http_server .= ':' . $http_url['port'];
}

if (substr($http_catalog, -1) != '/') {
  $http_catalog .= '/';
}

$admin_folder = 'ClicShoppingAdmin';

if (isset($_POST['CFG_ADMIN_DIRECTORY']) && !empty($_POST['CFG_ADMIN_DIRECTORY']) && FileSystem::isWritable($dir_fs_document_root) && FileSystem::isWritable($dir_fs_document_root . 'ClicShoppingAdmin')) {
  $admin_folder = preg_replace('/[^a-zA-Z0-9]/', '', trim($_POST['CFG_ADMIN_DIRECTORY']));

  if (empty($admin_folder)) {
    $admin_folder = 'ClicShoppingAdmin';
  }
}

if ($admin_folder != 'ClicShoppingAdmin') {
  @rename($dir_fs_document_root . 'ClicShoppingAdmin', $dir_fs_document_root . $admin_folder);
}

$dbServer = trim($_POST['DB_SERVER']);
$dbUsername = trim($_POST['DB_SERVER_USERNAME']);
$dbPassword = trim($_POST['DB_SERVER_PASSWORD']);
$dbDatabase = trim($_POST['DB_DATABASE']);
$dbTablePrefix = trim($_POST['DB_TABLE_PREFIX']);
$timezone = trim($_POST['TIME_ZONE']);

$data_encryption = bin2hex(random_bytes(16));

$file_contents = <<<ENDCFG
<?php
\$ini = <<<EOD
bootstrap_file = "index.php"
db_server = "{$dbServer}"
db_server_username = "{$dbUsername}"
db_server_password = "{$dbPassword}"
db_database = "{$dbDatabase}"
db_table_prefix = "{$dbTablePrefix}"
store_sessions = "MySQL"
time_zone = "{$timezone}"
db_server_persistent_connections = "false"
data_encryption = "{$data_encryption}"
dir_core = "Core"
EOD;

ENDCFG;

// last empty line needed
file_put_contents(CLICSHOPPING::BASE_DIR . 'Conf/global.php', $file_contents, LOCK_EX);

@chmod(CLICSHOPPING::BASE_DIR . 'Conf/global.php', 0444);

$file_contents = <<<ENDCFG
<?php
\$ini = <<<EOD
dir_root = "{$dir_fs_document_root}"
http_server = "{$http_server}"
http_path = "{$http_catalog}"
http_images_path = "images/"
http_cookie_domain = ""
http_cookie_path = "{$http_catalog}"
dir_core = "Core"
EOD;

ENDCFG;
// last empty line needed

file_put_contents(CLICSHOPPING::BASE_DIR . 'Sites/Shop/site_conf.php', $file_contents, LOCK_EX);

@chmod(CLICSHOPPING::BASE_DIR . 'Sites/Shop/site_conf.php', 0444);

// Elfinder configuration
$file_contents = <<<ENDCFG
<?php
define('DIR_FS_CATALOG_IMAGES', '{$dir_fs_document_root}sources/images/');  // path to files (REQUIRED)
define('DIR_WS_CATALOG_IMAGES', '{$http_catalog}sources/images/'); // URL to files (REQUIRED)

ENDCFG;
// last empty line needed

file_put_contents(CLICSHOPPING::BASE_DIR . 'Conf/ElFinderConfig.php', $file_contents, LOCK_EX);

@chmod(CLICSHOPPING::BASE_DIR . 'Conf/ElFinderConfig.php', 0444);

$admin_dir_fs_document_root = $dir_fs_document_root . $admin_folder . '/';

$admin_http_path = $http_catalog . $admin_folder . '/';

$file_contents = <<<ENDCFG
<?php
\$ini = <<<EOD
dir_root = "{$admin_dir_fs_document_root}"
http_server = "{$http_server}"
http_path = "{$admin_http_path}"
http_images_path = "images/"
http_cookie_domain = ""
http_cookie_path = "{$admin_http_path}"
dir_core = "Core"
EOD;

ENDCFG;
// last empty line needed

file_put_contents(CLICSHOPPING::BASE_DIR . 'Sites/ClicShoppingAdmin/site_conf.php', $file_contents, LOCK_EX);

@chmod(CLICSHOPPING::BASE_DIR . 'Sites/ClicShoppingAdmin/site_conf.php', 0444);
$modules = ''; // must be under array

if (!isset($_POST['DB_SKIP_IMPORT'])) {
  if (\is_array($modules)) {
    foreach ($modules as $m) {
      $m_installed = [];

      foreach ($m['modules'] as $module) {
        $file = $module['file'];
        $class = $module['class'] ?? basename($file, '.php');
        $code = $module['code'] ?? $file;

        include_once($m['dir'] . $file);

        $mo = new $class();
        $mo->install();

        $m_installed[] = $code;

        if (isset($module['params'])) {
          foreach ($module['params'] as $key => $value) {
            $CLICSHOPPING_Db->save('configuration', ['configuration_value' => $value], ['configuration_key' => $key]);
          }
        }
      }

      $CLICSHOPPING_Db->save('configuration', ['configuration_value' => implode(';', $m_installed)], ['configuration_key' => $m['key']]);
    }
  }
}
?>

<div class="row">
  <div class="col-sm-9">
    <div class="alert alert-info" role="alert">
      <h2><?php echo TEXT_END_INSTALLATION; ?></h2>
      <p><?php echo TEXT_END_INSTALLATION_1; ?></p>
    </div>
  </div>

  <div class="col-sm-3">
    <div class="card">
      <div class="card-header">
        <p>Step 4/4</p>
        <ol>
          <li>Database Server</li>
          <li>Web Server</li>
          <li>Online Store Settings</li>
          <li><strong>&gt; Finished!</strong></li>
        </ol>
      </div>
    </div>
    <br/>
    <div class="progress">
      <div class="progress-bar progress-bar-primary progress-bar-striped" role="progressbar" aria-valuenow="100"
           aria-valuemin="0" aria-valuemax="100" style="width: 100%">100%
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-xs-12 col-sm-push-3 col-sm-9">
    <h1></h1>

    <div class="alert alert-info" role="alert">
      <p>
        <?php echo TEXT_END_INSTALLATION_2; ?><br/>
        <?php echo TEXT_END_INSTALLATION_3; ?><br/>
        <?php echo TEXT_END_INSTALLATION_4; ?><br/>
        <?php echo TEXT_END_INSTALLATION_5; ?><br/>
        <?php echo TEXT_END_INSTALLATION_6; ?><br/>
        <?php echo TEXT_END_INSTALLATION_7; ?><br/>
        <?php echo TEXT_END_INSTALLATION_8; ?><br/>
      </p>
    </div>
    <br/>
    <div class="alert alert-success" role="alert"><?php echo TEXT_END_INSTALLATION_SUCCESS; ?></div>
    <br/>

    <div class="row">
      <div class="col-sm-6 text-start">
        <?php echo HTML::button(TEXT_END_ACCESS_CATALOG, 'bi bi-cart-fill', $http_server . $http_catalog . 'index.php', 'success', ['newwindow' => 1]); ?>
      </div>
      <div class="col-sm-6 text-end">
        <!-- Button to Open the Modal -->
        <?php echo HTML::button(TEXT_END_ACCESS_ADMIN, 'bi bi-lock-fill', $http_server . $http_catalog . $admin_folder . '/index.php', 'primary', ['newwindow' => 1]); ?>
      </div>
    </div>
  </div>
</div>
