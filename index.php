<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\OM\HTTP;

// start the timer for the page parse time log
define('PAGE_PARSE_START_TIME', microtime());
define('CLICSHOPPING_BASE_DIR', __DIR__ . '/Core/ClicShopping/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();

//check configuration
if (!CLICSHOPPING::configExists('db_server') || (\strlen(CLICSHOPPING::getConfig('db_server')) < 1)) {
  if (realpath(__DIR__ . '/install/')) {
    $realDocRoot = realpath($_SERVER['DOCUMENT_ROOT']);
    $realDirPath = realpath(__DIR__);
    $suffix = str_replace($realDocRoot, '', $realDirPath);

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);

    $prefix = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $folderUrl = $prefix . $host . $suffix . '/install/index.php';

    header('Location:' . $folderUrl);
    exit;
  } else {
    echo 'Please look your install directory to begin your new installation like https://wwww.mydomain.com/MyDirectory/install';
    exit;
  }
}

CLICSHOPPING::loadSite('Shop');

if (CLICSHOPPING::hasSitePage()) {
  if (CLICSHOPPING::isRPC() === false) {
    $page_file = CLICSHOPPING::getSitePageFile();

    if (empty($page_file) || !is_file($page_file)) {
      HTTP::redirect(CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop') . 'error_documents/404.php');
    }

    if (CLICSHOPPING::useSiteTemplateWithPageFile()) {
      $headerFile = Registry::get('Template')->getFile('header.php', 'Default');
      if (is_file($headerFile)) {
        include_once($headerFile);
      }
    }

    include_once($page_file);

    if (CLICSHOPPING::useSiteTemplateWithPageFile()) {
      $footerFile = Registry::get('Template')->getFile('footer.php', 'Default');
      if (is_file($footerFile)) {
        include_once($footerFile);
      }
    }
  }

    goto main_sub3;
  }

main_sub3: // Sites and Apps skip to here

  $footerFile = CLICSHOPPING::BASE_DIR . '/Sites/Shop/Templates/Default/footer.php';
  if (is_file($footerFile)) {
    require_once($footerFile);
  }