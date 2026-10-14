<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;

// set the level of error reporting
error_reporting(E_ALL & ~E_DEPRECATED);

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../Core/') . '/ClicShopping/');

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

if (isset($_GET['language'])) {
  setcookie('Lor_Language', HTML::sanitize($_GET['language']), ini_get('session.cookie_lifetime'), ini_get('session.cookie_path'), ini_get('session.cookie_domain'), ini_get('session.cookie_secure'), ini_get('session.cookie_httponly'));

  $language = HTML::sanitize($_GET['language']);
} elseif (isset($_COOKIE['Lor_Language'])) {
  $language = $_COOKIE['Lor_Language'];
} else {
  $language = 'english';
}

CLICSHOPPING::initialize();
