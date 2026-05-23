<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * This class is responsible for performing a security check on the download directory
 * used in the shop system. It ensures compliance with the required settings for downloads.
 */
class securityCheck_download_directory
{
  public string $type = 'warning';

  /**
   * Constructor method.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $CLICSHOPPING_Language->loadDefinitions('modules/SecurityCheck/download_directory', null, null, 'Shop');
  }

  /**
   * Checks if the download functionality is enabled and verifies the existence
   * of the specified download directory in the shop.
   *
   * @return bool Returns true if downloads are disabled or if the directory exists; otherwise, false.
   */
  public function pass()
  {
    $CLICSHOPPING_Template = Registry::get('TemplateAdmin');

    if (DOWNLOAD_ENABLED != 'true') {
      return true;
    }

    return is_dir($CLICSHOPPING_Template->getPathDownloadShopDirectory('Private'));
  }

  /**
   * Retrieves a warning message indicating that the download directory does not exist.
   *
   * @return string The formatted warning message including the download path.
   */
  public function getMessage()
  {
    return CLICSHOPPING::getDef('warning_download_directory_non_existent', [
      'download_path' => CLICSHOPPING::getConfig('dir_root', 'Shop') . 'sources/Download/Private/'
    ]);
  }
}