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

/**
 * Provides a security check for session storage configuration in the shop context.
 * This class ensures that session storage is properly configured and verified to prevent potential security issues.
 */
class securityCheck_session_storage
{
  public string $type = 'warning';

  /**
   * Initializes the constructor and loads the necessary language definitions
   * for the session storage security check module in the shop context.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $CLICSHOPPING_Language->loadDefinitions('modules/SecurityCheck/session_storage', null, null, 'Shop');

  }

  /**
   * Checks if the session storage configuration is set or if the session save path is writable.
   *
   * @return bool Returns true if the store_sessions configuration is not empty or the session save path is writable, false otherwise.
   */
  public function pass()
  {
    return ((CLICSHOPPING::getConfig('store_sessions') != '') || FileSystem::isWritable(session_save_path()));
  }

  /**
   * Retrieves a warning message related to the session storage directory if the directory
   * does not exist or is not writable and the store_sessions configuration is empty.
   *
   * @return string|null Returns a warning message if the session directory is non-existent or not writable,
   *                     otherwise returns null.
   */
  public function getMessage()
  {
    if (CLICSHOPPING::getConfig('store_sessions') == '') {
      if (!is_dir(session_save_path())) {
        return CLICSHOPPING::getDef('warning_session_directory_non_existent', [
          'session_path' => session_save_path()
        ]);
      } elseif (!FileSystem::isWritable(session_save_path())) {
        return CLICSHOPPING::getDef('warning_session_directory_not_writeable', [
          'session_path' => session_save_path()
        ]);
      }
    }
  }
}