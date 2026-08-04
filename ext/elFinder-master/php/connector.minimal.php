<?php
//error_reporting(E_ALL | E_STRICT); // Set E_ALL for debuging
error_reporting(0); // Set E_ALL for debuging

// ===============================================
// PATCHES DE SÉCURITÉ ELFINDER
// ===============================================

// Configuration sécurisée simplifiée
$secure_upload_allow = ['image', 'text/plain'];
$secure_upload_deny = ['all'];

// Vérification de sécurité - Plus permissive pour elFinder
// Vérifier si on est dans le contexte ClicShopping
$is_clicshopping_context = (
    (isset($_GET['Admin']) && $_GET['Admin'] === 'ClicShoppingAdmin') ||
    (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'ClicShoppingAdmin') !== false) ||
    (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'ClicShoppingAdmin') !== false)
);

if (!$is_clicshopping_context) {
    // Log de sécurité mais ne pas bloquer complètement
    error_log('elFinder: Tentative d\'accès non autorisée depuis: ' . ($_SERVER['HTTP_REFERER'] ?? 'Direct'));
}

// Validation des paramètres de requête - Temporairement désactivée pour debug
// Log des commandes pour voir ce qu'elFinder utilise
if (isset($_GET['cmd'])) {
    error_log('elFinder command: ' . $_GET['cmd']);
}

// Validation basique - seulement bloquer les commandes vraiment dangereuses
$dangerous_commands = ['exec', 'system', 'shell_exec', 'eval', 'passthru'];
if (isset($_GET['cmd']) && in_array($_GET['cmd'], $dangerous_commands)) {
    http_response_code(400);
    exit('Dangerous command blocked');
}

// Headers de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// // Optional exec path settings (Default is called with command name only)
// define('ELFINDER_TAR_PATH',      '/PATH/TO/tar');
// define('ELFINDER_GZIP_PATH',     '/PATH/TO/gzip');
// define('ELFINDER_BZIP2_PATH',    '/PATH/TO/bzip2');
// define('ELFINDER_XZ_PATH',       '/PATH/TO/xz');
// define('ELFINDER_ZIP_PATH',      '/PATH/TO/zip');
// define('ELFINDER_UNZIP_PATH',    '/PATH/TO/unzip');
// define('ELFINDER_RAR_PATH',      '/PATH/TO/rar');
// define('ELFINDER_UNRAR_PATH',    '/PATH/TO/unrar');
// define('ELFINDER_7Z_PATH',       '/PATH/TO/7za');
// define('ELFINDER_CONVERT_PATH',  '/PATH/TO/convert');
// define('ELFINDER_IDENTIFY_PATH', '/PATH/TO/identify');
// define('ELFINDER_EXIFTRAN_PATH', '/PATH/TO/exiftran');
// define('ELFINDER_JPEGTRAN_PATH', '/PATH/TO/jpegtran');
// define('ELFINDER_FFMPEG_PATH',   '/PATH/TO/ffmpeg');

// define('ELFINDER_CONNECTOR_URL', 'URL to this connector script');  // see elFinder::getConnectorUrl()

// define('ELFINDER_COOKIE_SAMESITE', 'None'); // It require when configuring with CORS.

// define('ELFINDER_DEBUG_ERRORLEVEL', -1); // Error reporting level of debug mode

// // To Enable(true) handling of PostScript files by ImageMagick
// // It is disabled by default as a countermeasure 
// // of Ghostscript multiple -dSAFER sandbox bypass vulnerabilities
// // see https://www.kb.cert.org/vuls/id/332928
// define('ELFINDER_IMAGEMAGICK_PS', true);
// ===============================================

// // load composer autoload before load elFinder autoload If you need composer
// // You need to run the composer command in the php directory.
//is_readable('./vendor/autoload.php') && require './vendor/autoload.php';

// // elFinder autoload
require './autoload.php';
// ===============================================

// // Enable FTP connector netmount
elFinder::$netDrivers['ftp'] = 'FTP';
// ===============================================

// // Required for Dropbox network mount
// // Installation by composer
// // `composer require kunalvarma05/dropbox-php-sdk` on php directory
// // Enable network mount
// elFinder::$netDrivers['dropbox2'] = 'Dropbox2';
// // Dropbox2 Netmount driver need next two settings. You can get at https://www.dropbox.com/developers/apps
// // AND require register redirect url to "YOUR_CONNECTOR_URL?cmd=netmount&protocol=dropbox2&host=1"
// // If the elFinder HTML element ID is not "elfinder", you need to change "host=1" to "host=ElementID"
// define('ELFINDER_DROPBOX_APPKEY',    '');
// define('ELFINDER_DROPBOX_APPSECRET', '');
// ===============================================

// // Required for Google Drive network mount
// // Installation by composer
// // `composer require google/apiclient:^2.0` on php directory
// // Enable network mount
// elFinder::$netDrivers['googledrive'] = 'GoogleDrive';
// // GoogleDrive Netmount driver need next two settings. You can get at https://console.developers.google.com
// // AND require register redirect url to "YOUR_CONNECTOR_URL?cmd=netmount&protocol=googledrive&host=1"
// // If the elFinder HTML element ID is not "elfinder", you need to change "host=1" to "host=ElementID"
// define('ELFINDER_GOOGLEDRIVE_CLIENTID',     '');
// define('ELFINDER_GOOGLEDRIVE_CLIENTSECRET', '');
// // Required case when Google API is NOT added via composer
// define('ELFINDER_GOOGLEDRIVE_GOOGLEAPICLIENT', '/path/to/google-api-php-client/vendor/autoload.php');
// ===============================================

// // Required for Google Drive network mount with Flysystem
// // Installation by composer
// // `composer require nao-pon/flysystem-google-drive:~1.1 nao-pon/elfinder-flysystem-driver-ext` on php directory
// // Enable network mount
// elFinder::$netDrivers['googledrive'] = 'FlysystemGoogleDriveNetmount';
// // GoogleDrive Netmount driver need next two settings. You can get at https://console.developers.google.com
// // AND require register redirect url to "YOUR_CONNECTOR_URL?cmd=netmount&protocol=googledrive&host=1"
// // If the elFinder HTML element ID is not "elfinder", you need to change "host=1" to "host=ElementID"
// define('ELFINDER_GOOGLEDRIVE_CLIENTID',     '');
// define('ELFINDER_GOOGLEDRIVE_CLIENTSECRET', '');
// // And "php/.tmp" directory must exist and be writable by PHP.
// ===============================================

// // Required for One Drive network mount
// //  * cURL PHP extension required
// //  * HTTP server PATH_INFO supports required
// // Enable network mount
// elFinder::$netDrivers['onedrive'] = 'OneDrive';
// // OneDrive Netmount driver need next two settings. You can get at
// // https://portal.azure.com/#blade/Microsoft_AAD_IAM/ActiveDirectoryMenuBlade/RegisteredApps
// // AND require register redirect url to "YOUR_CONNECTOR_URL/netmount/onedrive/1"
// // If the elFinder HTML element ID is not "elfinder", you need to change "/1" to "/ElementID"
// define('ELFINDER_ONEDRIVE_CLIENTID',     '');
// define('ELFINDER_ONEDRIVE_CLIENTSECRET', '');
// ===============================================

// // Required for Box network mount
// //  * cURL PHP extension required
// // Enable network mount
// elFinder::$netDrivers['box'] = 'Box';
// // Box Netmount driver need next two settings. You can get at https://developer.box.com
// // AND require register redirect url to "YOUR_CONNECTOR_URL?cmd=netmount&protocol=box&host=1"
// // If the elFinder HTML element ID is not "elfinder", you need to change "host=1" to "host=ElementID"
// define('ELFINDER_BOX_CLIENTID',     '');
// define('ELFINDER_BOX_CLIENTSECRET', '');
// ===============================================


// // Zoho Office Editor APIKey
// // https://www.zoho.com/docs/help/office-apis.html
// define('ELFINDER_ZOHO_OFFICE_APIKEY', '');
// Zoho Office save callback state TTL in seconds
// Default: 3600 (1 hour), Max: 86400 (24 hours)
// define('ELFINDER_ZOHO_OFFICE_CALLBACK_TTL', 3600);
// ===============================================

// // Online converter (online-convert.com) APIKey
// // https://apiv2.online-convert.com/docs/getting_started/api_key.html
// define('ELFINDER_ONLINE_CONVERT_APIKEY', '');
// ===============================================

// // Zip Archive editor
// // Installation by composer
// // `composer require nao-pon/elfinder-flysystem-ziparchive-netmount` on php directory
// define('ELFINDER_DISABLE_ZIPEDITOR', false); // set `true` to disable zip editor
// ===============================================

/**
 * Simple function to demonstrate how to control file access using "accessControl" callback.
 * This method will disable accessing files/folders starting from '.' (dot)
 *
 * @param  string    $attr    attribute name (read|write|locked|hidden)
 * @param  string    $path    absolute file path
 * @param  string    $data    value of volume option `accessControlData`
 * @param  object    $volume  elFinder volume driver object
 * @param  bool|null $isDir   path is directory (true: directory, false: file, null: unknown)
 * @param  string    $relpath file path relative to volume root directory started with directory separator
 * @return bool|null
 **/
function access($attr, $path, $data, $volume, $isDir, $relpath) {
  $basename = basename($path);
  return $basename[0] === '.'                  // if file/folder begins with '.' (dot)
    && strlen($relpath) !== 1           // but with out volume root
    ? !($attr == 'read' || $attr == 'write') // set read+write to false, other (locked+hidden) set to true
    :  null;                                 // else elFinder decide it itself
}


//*****************************************************************************

  require('../../../Core/ClicShopping/Conf/ElFinderConfig.php');

// Documentation for connector options:
// https://github.com/Studio-42/elFinder/wiki/Connector-configuration-options

// Configuration sécurisée d'elFinder
$opts = array(
  'debug' => false,  //see a new menu in filefinder with all informations
  'roots' => array(
  array(
     'driver'        => 'LocalFileSystem',           // driver for accessing file system (REQUIRED)
//                    'path'          => '../files/',                 // path to files (REQUIRED)
      'path'          =>  DIR_FS_CATALOG_IMAGES,         // path to files (REQUIRED)
      'URL'           =>  DIR_WS_CATALOG_IMAGES, // URL to files (REQUIRED)
      'trashHash'     => 't1_Lw',                     // elFinder's hash of trash folder
      'winHashFix'    => DIRECTORY_SEPARATOR !== '/', // to make hash same to Linux one on windows too
      
      // CONFIGURATION SÉCURISÉE
      'uploadDeny'    => $secure_upload_deny,         // Types MIME interdits
      'uploadAllow'   => $secure_upload_allow,        // Types MIME autorisés
      'uploadOrder'   => array('deny', 'allow'),      // D'abord interdire, puis autoriser
      'uploadMaxSize' => '10M',                       // Taille maximale 10MB
      'acceptedName' => '/^[a-zA-Z0-9._-]+$/',       // Noms de fichiers stricts
      
      'accessControl' => 'access',             // disable and hide dot starting files (OPTIONAL)
      
      // Désactiver les fonctions dangereuses
      'disabled' => ['mkfile', 'edit', 'rename', 'resize', 'archive', 'extract'],

//hide some extensions

      'attributes' => array(
                            array( // hide readmes
                                    'pattern' => '/README/',
                                    'read' => false,
                                    'write' => false,
                                    'hidden' => true,
                                    'locked' => false
                                  ),
                            array( // restrict access to htaccess$ files
                                    'pattern' => '/\.htaccess$/',
                                    'write' => false,
                                    'read' => false,
                                    'hidden' => true,
                                  ),
                            array( // restrict access to htaccess$ files
                              'pattern' => '/\.index/',
                              'write' => false,
                              'read' => false,
                              'hidden' => true,
                            ),
                             array( // restrict access to php files
                                     'pattern' => '/\.php$/',
                                     'write' => false,
                                     'read' => false,
                                     'hidden' => true,
                                   ),
                             array( // restrict access to html files
                                     'pattern' => '/\.html$/',
                                     'write' => false,
                                     'read' => false,
                                     'hidden' => true,
                                   ),
                             array( // restrict access to htm files
                                     'pattern' => '/\.htm$/',
                                     'write' => false,
                                     'read' => false,
                                     'hidden' => true,
                                   ),
                              array( // restrict access to txt files
                                    'pattern' => '/\.txt$/',
                                    'write' => false,
                                    'read' => false,
                                    'hidden' => true,
                                  ),
                              array( // restrict access to txt files
                                'pattern' => '/\.zip$/',
                                'write' => false,
                                'read' => false,
                                'hidden' => true,
                              )
                            ),
    ),

// Trash volume
    array(
     'id'            => '1',
     'driver'        => 'Trash',
     'path'          => DIR_FS_CATALOG_IMAGES . '.trash/',
//                     'tmbURL'        => dirname($_SERVER['PHP_SELF']) . '/../files/.trash/.tmb/',
     'tmbURL'        => DIR_WS_CATALOG_IMAGES . '.tmb/',
     'winHashFix'    => DIRECTORY_SEPARATOR !== '/', // to make hash same to Linux one on windows too
     'uploadDeny'    => array('all'),                // Recomend the same settings as the original volume that uses the trash
     'uploadAllow'   => array('image', 'text/plain'),// Same as above
     'uploadOrder'   => array('deny', 'allow'),      // Same as above
     'accessControl' => 'access',                    // Same as above
    ),

    array(
      'dirMode'       => 0755,
      'fileMode'      => 0644
    )
  )
);

// run elFinder
$connector = new elFinderConnector(new elFinder($opts));
$connector->run();
