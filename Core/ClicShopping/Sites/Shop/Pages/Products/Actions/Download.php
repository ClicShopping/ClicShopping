<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Products\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\Registry;
use ClicShopping\Sites\Shop\Pages\Account\Classes\HistoryInfo;

class Download extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

// Unlinks all subdirectories and files in $dir
// Works only on one subdir level, will not recurse
  protected function unlinkTempDir($dir)
  {
    $h1 = opendir($dir);
    while ($subdir = readdir($h1)) {
// Ignore non directories
      if (!is_dir($dir . $subdir)) {
        continue;
      }
// Ignore . and .. and CVS
      if ($subdir == '.' || $subdir == '..' || $subdir == 'CVS') {
        continue;
      }
// Loop and unlink files in subdirectory
      $h2 = opendir($dir . $subdir);

      while ($file = readdir($h2)) {
        if ($file == '.' || $file == '..') {
          continue;
        }

        if (file_exists($dir . $subdir . DIRECTORY_SEPARATOR . $file)) {
          unlink($dir . $subdir . DIRECTORY_SEPARATOR . $file);
        }
      }

      closedir($h2);

      if (is_dir($dir . $subdir)) {
        rmdir($dir . $subdir);
      }
    }
    closedir($h1);
  }

  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Template = Registry::get('Template');

    if (!$CLICSHOPPING_Customer->isLoggedOn()) {
      CLICSHOPPING::redirect('account', 'login');
    }

// Check download.php was called with proper GET parameters
    if ((isset($_GET['order']) && !is_numeric($_GET['order'])) || (isset($_GET['id']) && !is_numeric($_GET['id']))) {
      CLICSHOPPING::redirect();
    }

// Check that order_id, customer_id and filename match
    $Qdownload = HistoryInfo::getDownloadFilesPurchased();

    if ($Qdownload->fetch() === false) {
      CLICSHOPPING::redirect(null, null);
    }

// MySQL 3.22 does not have INTERVAL
    list($dt_year, $dt_month, $dt_day) = explode('-', $Qdownload->value('date_purchased_day'));
    $download_timestamp = mktime(23, 59, 59, $dt_month, $dt_day + $Qdownload->valueInt('download_maxdays'), $dt_year);

// Die if time expired (maxdays = 0 means no time limit)
    if (($Qdownload->valueInt('download_maxdays') != 0) && ($download_timestamp <= time())) die;

// Die if remaining count is <=0
    if ($Qdownload->valueInt('download_count') <= 0) die;

// Die if file is not there
    if (!is_file($CLICSHOPPING_Template->getPathDownloadShopDirectory('Private') . $Qdownload->value('orders_products_filename'))) die;

// Now decrement counter
    $Qupdate = $CLICSHOPPING_Db->prepare('update :table_orders_products_download
                                            set download_count = download_count-1
                                            where orders_products_download_id = :orders_products_download_id
                                            ');

    $Qupdate->bindInt(':orders_products_download_id', $_GET['id']);
    $Qupdate->execute();

// Now send the file with header() magic
    header('Expires: Mon, 26 Nov 1962 00:00:00 GMT');
    header('Last-Modified: ' . gmdate('D,d M Y H:i:s') . ' GMT');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Type: Application/octet-stream');
    header('Content-disposition: attachment; filename="' . basename($Qdownload->value('orders_products_filename')) . '"');

    if (DOWNLOAD_BY_REDIRECT == 'true') {
// This will work only on Unix/Linux hosts
      $this->unlinkTempDir($CLICSHOPPING_Template->getPathDownloadShopDirectory('Private'));
      $tempdir = Hash::getRandomString(20);

      umask(0000);
      mkdir($CLICSHOPPING_Template->getPathDownloadShopDirectory('Private') . $tempdir, 0777);
      symlink($CLICSHOPPING_Template->getPathDownloadShopDirectory('Private') . $Qdownload->value('orders_products_filename'), $CLICSHOPPING_Template->getPathDownloadShopDirectory('Private') . $tempdir . DIRECTORY_SEPARATOR . $Qdownload->value('orders_products_filename'));

      if (is_file($CLICSHOPPING_Template->getPathDownloadShopDirectory('Private') . $tempdir . DIRECTORY_SEPARATOR . $Qdownload->value('orders_products_filename'))) {
        CLICSHOPPING::redirect($CLICSHOPPING_Template->getTemplateSource() . '/Download/Private/' . $tempdir . DIRECTORY_SEPARATOR . $Qdownload->value('orders_products_filename'));
      }
    }

// Fallback to readfile() delivery method. This will work on all systems, but will need considerable resources
    readfile($CLICSHOPPING_Template->getPathDownloadShopDirectory('Private') . $Qdownload->value('orders_products_filename'));

    exit;
  }
}