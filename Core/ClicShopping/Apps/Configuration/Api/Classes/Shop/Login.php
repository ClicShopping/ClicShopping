<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Api\Classes\Shop;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiSecurity;

class Login
{
  private mixed $lang;
  private string $username;
  private string $key;
  private ?string $ip;
  private mixed $authentification;
  /**
   * Constructor method.
   *
   * @param string $username The username of the user.
   * @param string $key The key associated with the user.
   * @param string|null $ip The IP address of the user (optional).
   * @return void
   */
  public function __construct(string $username, string $key, ?string $ip)
  {
    if (trim($username) === '' || trim($key) === '') {
      throw new \InvalidArgumentException('Username and key are required');
    }

    $this->username = $username;
    $this->key = $key;
    $this->ip = $ip;
    $this->lang = Registry::get('Language');
  }

  /**
   * Get the username of the user.
   *
   * @return string Returns the username.
   */
  public function getUsername(): string
  {
    return $this->username;
  }

  /**
   * Get the IP address of the user.
   *
   * @return string|null Returns the IP address if set, otherwise null.
   */
  public function getIp(): ?string
  {
    return $this->ip;
  }

  /**
   * Get the key associated with the user.
   *
   * @return string|null Returns the key if set, otherwise null.
   */
  public function getKey(): string
  {
    return $this->key;
  }
  
  /**
   * Handles user login by authenticating username, key, and IP, then generating a session token.
   *
   * This method checks if the API module is active and processes login credentials.
   * If valid credentials are provided, it checks IP restrictions and generates a session token.
   * If invalid credentials are detected or the IP check fails, it returns appropriate error messages.
   *
   * @return string|false Returns a session token string upon successful login,
   *                      'bad IP' if the IP is not allowed, 'no access' for invalid credentials,
   *                      or false if the API module is inactive.
   */
  public function getLogin(): string|false
  {
    if (!\defined('CLICSHOPPING_APP_API_AI_STATUS') || CLICSHOPPING_APP_API_AI_STATUS == 'False') {
        return false;
    }

    $key = HTML::sanitize($this->getKey());
    $username = HTML::sanitize($this->getUsername());
    $ip = HTML::sanitize($this->getIp() ?? '');

    if ($username === '' || $key === '') {
      throw new \InvalidArgumentException('Username and key must not be empty');
    }

    if (ApiSecurity::isAccountLocked($username)) {
      return 'account locked';
    }

    Registry::set('Authentification', new Authentification($username, $key, $ip));
    $this->authentification = Registry::get('Authentification');

    $result = ApiSecurity::authenticateCredentials($username, $key);

    if (!is_array($result) || !isset($result['api_id'])) {
      ApiSecurity::incrementFailedAttempts($username);
      return 'no access';
    }

    $api_id = $result['api_id'];

    if (!$this->authentification->getIps($api_id)) {
      ApiSecurity::incrementFailedAttempts($username);
      return 'Bad IP';
    }

    ApiSecurity::resetFailedAttempts($username);

    return $this->authentification->addSession($api_id);
  }
}