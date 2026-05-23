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
 * Represents a security check to verify whether the installation directory exists in the application's root directory.
 */
class securityCheck_install_directory
{
  public string $type = 'warning';

  /**
   * Constructor method that initializes the language registry and loads the necessary language definitions for the security check module.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $CLICSHOPPING_Language->loadDefinitions('modules/security_check/install_directory', null, null, 'Shop');
  }

  /**
   * Checks if the install directory does not exist in the specified root directory.
   *
   * @return bool Returns true if the install directory does not exist, false otherwise.
   */
  public function pass()
  {
    return !is_dir(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'install');
  }

  /**
   * Retrieves a predefined warning message indicating that the install directory exists,
   * including the installation path dynamically configured for the shop.
   *
   * @return string The warning message with the appropriate installation path.
   */
  public function getMessage()
  {
    return CLICSHOPPING::getDef('warning_install_directory_exists', [
      'install_path' => CLICSHOPPING::getConfig('dir_root', 'Shop') . 'install'
    ]);
  }
}
