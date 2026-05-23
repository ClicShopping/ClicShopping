<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Sites\Shop\Pages\Login;

use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiProducts;
use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiShop;
use ClicShopping\Apps\Configuration\Api\Classes\Shop\Authentification;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Login extends \ClicShopping\OM\Domains\PagesAbstract
{
  protected string|null $file = null;
  protected bool $use_site_template = false;
  private mixed $authentification;
  /**
   * Initializes the API request handling process. This method retrieves necessary dependencies and processes
   * incoming API requests based on the HTTP request method. It handles authentication, validates access,
   * verifies IP addresses, and generates appropriate responses for clients.
   *
   * @return bool|string Returns false if the API is disabled, or outputs the API response based on the access status.
   */
  protected function init()
  {
    if (!\defined('CLICSHOPPING_APP_API_AI_STATUS') || CLICSHOPPING_APP_API_AI_STATUS == 'False') {
      return false;
    }

    $requestMethod = ApiShop::requestMethod();

    switch ($requestMethod) {
      case 'POST':
        $key = HTML::sanitize($_POST['key'] ?? '');
        $username = HTML::sanitize($_POST['username'] ?? '');
        $ip = HTML::sanitize($_POST['ip'] ?? '');

        Registry::set('Authentification', new Authentification($username, $key, $ip));
        $this->authentification = Registry::get('Authentification');

        if ($this->authentification->checkUrl('Login') !== true) {
          echo 'bad token';
          exit;
        }

        $result = $this->authentification->checkAccess();
        if (!isset($result)) {
          exit;
        }

        $api_id = $result['api_id'];
        if ($this->authentification->getIps($api_id) !== true) {
          echo 'bad IP';
          exit;
        }

        $_SESSION['api_token'] = $this->authentification->addSession($api_id);
        echo $_SESSION['api_token'];
        break;

      default:
        echo $this->authentification?->notFoundResponse();
        break;
    }

    exit;
  }
}

