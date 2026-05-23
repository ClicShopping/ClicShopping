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
 * Class responsible for checking the security status of file uploads within the PHP configuration.
 */
class securityCheck_file_uploads
{
  public string $type = 'warning';

  /**
   * Initializes the constructor for the class by loading the language definitions
   * for the 'modules/SecurityCheck/file_uploads' file in the 'Shop' directory.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $CLICSHOPPING_Language->loadDefinitions('modules/SecurityCheck/file_uploads', null, null, 'Shop');

  }

  /**
   * Checks if file uploads are enabled in the PHP configuration.
   *
   * @return bool True if file uploads are enabled, false otherwise.
   */
  public function pass()
  {
    return (bool)ini_get('file_uploads');
  }

  /**
   * Retrieves a predefined warning message indicating that file uploads are disabled.
   *
   * @return string Returns the warning message for disabled file uploads.
   */
  public function getMessage()
  {
    return CLICSHOPPING::getDef('warning_file_uploads_disabled');
  }
}

