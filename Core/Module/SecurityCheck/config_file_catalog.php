<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\Registry;

class securityCheck_config_file_catalog
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

    $CLICSHOPPING_Language->loadDefinitions('Shop', 'modules/SecurityCheck/config_file_catalog', null, null, 'Shop');

  }

  /**
   * Checks if the configuration file is not writable.
   *
   * @return bool Returns true if the configuration file is not writable, otherwise false.
   */
  public function pass()
  {
    return !FileSystem::isWritable(CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/configure.php');
  }

  /**
   * Retrieves the warning message related to the writable configuration file.
   *
   * @return string The warning message with the configured file path.
   */
  public function getMessage()
  {
    return CLICSHOPPING::getDef('warning_config_file_writeable', [
      'configure_file_path' => CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/configure.php'
    ]);
  }
}