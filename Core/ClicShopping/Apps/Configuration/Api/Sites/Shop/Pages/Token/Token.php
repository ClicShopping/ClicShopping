<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Sites\Shop\Pages\Token;

use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiSecurity;
use ClicShopping\Apps\Configuration\Api\Classes\Shop\Login;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Token extends \ClicShopping\OM\Domains\PagesAbstract
{
  protected ?string $file = null;
  protected bool $use_site_template = false;

  /**
   * Initializes the API AI module by sanitizing input data, creating a login instance,
   * and retrieving the authentication token for the session.
   *
   * @return mixed Returns false if the API AI module is not defined or disabled.
   *               Outputs the token and terminates execution upon successful initialization.
   */
  public function init()
  {
    header('Content-Type: application/json');

    if (!\defined('CLICSHOPPING_APP_API_AI_STATUS') || CLICSHOPPING_APP_API_AI_STATUS == 'False') {
      http_response_code(503);
      echo json_encode(['error' => 'API is disabled']);
      exit;
    }

    if (!isset($_POST['username']) || !isset($_POST['key'])) {
      http_response_code(400);
      echo json_encode(['error' => 'Missing required parameters: username and key']);
      exit;
    }

    $username = HTML::sanitize($_POST['username']);
    $key      = HTML::sanitize($_POST['key']);

    if (empty($username) || empty($key)) {
      http_response_code(400);
      echo json_encode(['error' => 'Username and key cannot be empty']);
      exit;
    }

    Registry::set('Login', new Login($username, $key, ''));
    $CLICSHOPPING_login = Registry::get('Login');

    $token = $CLICSHOPPING_login->getLogin();

    // getLogin() returns either the 32-hex session token or a sentinel string
    // ('account locked' | 'no access' | 'Bad IP') or false when API is disabled.
    if ($token === false) {
      http_response_code(503);
      echo json_encode(['error' => 'API is disabled']);
      exit;
    }

    if (!is_string($token) || strlen($token) !== 32 || !ctype_xdigit($token)) {
      http_response_code(401);
      echo json_encode(['error' => $token ?: 'Authentication failed']);
      exit;
    }

    // Also expose the token via headers so modern clients can pick it up
    // without parsing the body (mirror of MCP B2).
    ApiSecurity::emitSessionHeaders($token);

    echo json_encode(['token' => $token]);
    exit;
  }
}