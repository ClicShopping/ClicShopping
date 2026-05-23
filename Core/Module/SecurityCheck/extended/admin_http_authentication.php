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
 * This class is part of the security system within the application.
 * It verifies whether HTTP Authentication for the admin interface is active.
 */
class securityCheckExtended_admin_http_authentication
{
  /**
   * Represents a type of message or notification, typically used to categorize
   * or indicate the severity or context, such as 'warning'.
   */
  public string $type = 'warning';
  public string $title;


  /**
   * Initializes the module by loading language definitions and setting the module title.
   *
   * @return void
   */
  public function __construct()
  {
    $CLICSHOPPING_Language = Registry::get('Language');

    $CLICSHOPPING_Language->loadDefinitions('modules/security_check/extended/admin_http_authentication', null, null, 'Shop');

    $this->title = CLICSHOPPING::getDef('module_security_check_extended_admin_http_authentication_title');
  }

  /**
   * Checks if HTTP authentication credentials are provided.
   *
   * @return bool Returns true if both PHP_AUTH_USER and PHP_AUTH_PW are set, otherwise false.
   */
  public function pass()
  {

    return isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']);
  }

  /**
   * Retrieves the error message specific to the admin HTTP authentication security check.
   *
   * @return string The localized error message.
   */
  public function getMessage()
  {
    return CLICSHOPPING::getDef('module_security_check_extended_admin_http_authentication_error');
  }
}