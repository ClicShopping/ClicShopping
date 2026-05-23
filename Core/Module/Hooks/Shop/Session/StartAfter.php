<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\OM\Module\Hooks\Shop\Session;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Hash;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class StartAfter
{
  /**
   * Executes the session initialization and validation processes.
   *
   * This method performs the following:
   * - Initializes a session token if it does not exist.
   * - Verifies the SSL session ID if SSL is enabled and the feature is configured.
   * - Verifies the browser user agent if the feature is enabled.
   * - Verifies the IP address if the feature is enabled.
   *
   * If validation fails, the session is killed and the user is redirected.
   *
   * @return void
   */
  public function execute()
  {

    $CLICSHOPPING_Session = Registry::get('Session');

// initialize a session token
    if (!isset($_SESSION['sessiontoken'])) {
      $_SESSION['sessiontoken'] = md5(Hash::getRandomInt() . Hash::getRandomInt() . Hash::getRandomInt() . Hash::getRandomInt());
    }

// verify the ssl_session_id if the feature is enabled
    if ((HTTP::getRequestType() === 'SSL') && (SESSION_CHECK_SSL_SESSION_ID == 'True') && $CLICSHOPPING_Session->hasStarted()) {
      if (!isset($_SESSION['SSL_SESSION_ID'])) {
        $_SESSION['SESSION_SSL_ID'] = $_SERVER['SSL_SESSION_ID'];
      }

      if ($_SESSION['SESSION_SSL_ID'] != $_SERVER['SSL_SESSION_ID']) {
        $CLICSHOPPING_Session->kill();

        CLICSHOPPING::redirect(null, 'Info&SSLcheck');
      }
    }

// verify the browser user agent if the feature is enabled
    if (SESSION_CHECK_USER_AGENT == 'True') {
      $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

      if (!isset($_SESSION['SESSION_USER_AGENT'])) {
        $_SESSION['SESSION_USER_AGENT'] = $userAgent;
      }

      if ($_SESSION['SESSION_USER_AGENT'] !== $userAgent) {
        $CLICSHOPPING_Session->kill();
        CLICSHOPPING::redirect(null, 'Account&LogIn');
      }
    }

// verify the IP address if the feature is enabled
    if (SESSION_CHECK_IP_ADDRESS == 'True') {
      if (!isset($_SESSION['SESSION_IP_ADDRESS'])) {
        $_SESSION['SESSION_IP_ADDRESS'] = HTTP::getIpAddress();
      }

      if ($_SESSION['SESSION_IP_ADDRESS'] != HTTP::getIpAddress()) {
        $CLICSHOPPING_Session->kill();

        CLICSHOPPING::redirect(null, 'Account&LogIn');
      }
    }
  }
}
