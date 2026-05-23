<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Sites\Shop\Pages\Token;

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
    if (!\defined('CLICSHOPPING_APP_API_AI_STATUS') && CLICSHOPPING_APP_API_AI_STATUS == 'False') {
      return false;
    }

    if (!isset($_POST['username']) || !isset($_POST['key'])) {
      echo json_encode(['error' => 'Missing required parameters: username and key']);
      exit;
    }

    $username = HTML::sanitize($_POST['username']);
    $key = HTML::sanitize($_POST['key']);

    if (empty($username) || empty($key)) {
      echo json_encode(['error' => 'Username and key cannot be empty']);
      exit;
    }

    Registry::set('Login', new Login($username, $key, ''));
    $CLICSHOPPING_login = Registry::get('Login');

    $token = $CLICSHOPPING_login->getLogin();

    echo $token;

    exit;
  }
}