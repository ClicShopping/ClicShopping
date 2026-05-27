<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Classes\Shop;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\DateTime;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class Authentification extends ApiSecurity
{
  private string $username;
  public string $key;
  private ?string $ip;

  /**
   * Constructor for initializing the class with user credentials and optional IP address.
   *
   * @param string $username The username for authentication.
   * @param string $key The API key or password associated with the username.
   * @param string|null $ip Optional IP address for further security or identification purposes.
   * @throws Exception If invalid parameters are provided
   */
  public function __construct(string $username, string $key, ?string $ip = null)
  {
    // Validation des paramètres d'entrée
    if (empty($username) || empty($key)) {
      throw new \Exception("Username and key cannot be empty");
    }

    if (strlen($username) > 100) {
      throw new \Exception("Username too long (max 100 characters)");
    }

    if (is_null($key) || !is_string($key) || strlen($key) > 256) {
      throw new \Exception("API key too long or invalid type (max 255 characters)");
    }

    if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
      throw new \Exception("Invalid IP address format");
    }

    $this->username = HTML::sanitize($username);
    $this->key = HTML::sanitize($key);
    $this->ip = $ip ? HTML::sanitize($ip) : HTTP::getIpAddress();

    self::logSecurityEvent('Authentication object created', [
      'username' => $this->getUsername(),
      'ip' => $this->getIp()
    ]);
  }

  /**
   * Getters sécurisés pour accéder aux propriétés
   */
  public function getUsername(): string
  {
    return $this->username;
  }

  /**
   * @return string|null Returns the API key, or null if not set
   */
  public function getIp(): ?string
  {
    return $this->ip;
  }

  /**
   * Adds a new API session or returns existing session with proper validation
   *
   * @param int $api_id The unique identifier of the API
   * @return int|string Returns the session ID or database insert ID
   * @throws Exception If session creation fails or invalid API ID
   */
  public static function addSession(int $api_id): int|string
  {
    // Validation de l'API ID
    if ($api_id <= 0) {
      throw new \Exception("Invalid API ID provided");
    }

    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      if (!$CLICSHOPPING_Db) {
        throw new \Exception("Database connection not available");
      }

      $Qcheck = $CLICSHOPPING_Db->get('api_session', [
        'session_id',
        'date_modified'
      ], [
        'api_id' => $api_id
      ]);

      if (!empty($Qcheck->value('session_id'))) {
        $now = date('Y-m-d H:i:s');
        $date_diff = DateTime::getIntervalDate($Qcheck->value('date_modified'), $now);

        if ($date_diff <= (int)CLICSHOPPING_APP_API_AI_SESSION_TIMEOUT_MINUTES) {
          self::logSecurityEvent('Existing session returned', [
            'api_id' => $api_id,
            'session_id' => $Qcheck->value('session_id')
          ]);
          return $Qcheck->value('session_id');
        } else {
          // Session expirée, la supprimer
          $CLICSHOPPING_Db->delete('api_session', ['api_id' => $api_id]);
        }
      }

      $session_id = bin2hex(random_bytes(16));
      $ip = HTTP::getIpAddress();

      if (empty($ip)) {
        throw new \Exception("Unable to determine client IP address");
      }

      $sql_data_array = [
        'api_id' => $api_id,
        'session_id' => $session_id,
        'ip' => $ip,
        'date_added' => 'now()',
        'date_modified' => 'now()'
      ];

      $result = $CLICSHOPPING_Db->save('api_session', $sql_data_array);

      if (!$result) {
        throw new \Exception("Failed to create session");
      }

      self::logSecurityEvent('New session created', [
        'api_id'     => $api_id,
        'session_id' => $session_id,
        'ip'         => $ip,
        'insert_id'  => $CLICSHOPPING_Db->lastInsertId(),
      ]);

      // Return the 32-hex session token (what checkToken/clients use),
      // NOT the auto-increment PK (insert_id) which is useless to the client.
      return $session_id;

    } catch (PDOException $e) {
      self::logSecurityEvent('Database error in addSession', [
        'api_id' => $api_id,
        'error' => $e->getMessage()
      ]);
      throw new \Exception("Session creation failed");
    }
  }

  /**
   * Enhanced URL validation with proper security checks
   *
   * @param string $requiredParam The parameter that should NOT be present in the request
   * @return bool Returns true if validation passes
   * @throws Exception If URL validation fails
   */
  public function checkUrl(string $requiredParam): bool
  {
    try {
      if (empty($requiredParam)) {
        throw new \Exception("Required parameter name cannot be empty");
      }

      $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
      if ($uri === false) {
        self::logSecurityEvent('Invalid URI in request', [
          'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
          'username' => $this->getUsername()
        ]);
        $this->send404Response();
        return false;
      }

      $uri = explode('/', trim($uri, '/'));
      $path = CLICSHOPPING::getConfig('http_path', 'Shop');
      $path = trim(str_replace('/', '', $path));

      // Vérifier que le chemin commence par le bon préfixe
      if (empty($uri[0]) || $uri[0] !== $path) {
        self::logSecurityEvent('Invalid path in URL', [
          'expected_path' => $path,
          'actual_path' => $uri[0] ?? 'empty',
          'username' => $this->getUsername()
        ]);
        $this->send404Response();
        return false;
      }

      if (!isset($_REQUEST['api'])) {
        self::logSecurityEvent('Missing api parameter', [
          'username' => $this->getUsername(),
          'uri' => $_SERVER['REQUEST_URI']
        ]);
        $this->send404Response();
        return false;
      }

      if (isset($_REQUEST[$requiredParam])) {
        self::logSecurityEvent('Forbidden parameter present', [
          'parameter' => $requiredParam,
          'username' => $this->getUsername()
        ]);
        $this->send404Response();
        return false;
      }

      return true;

    } catch (Exception $e) {
      self::logSecurityEvent('Error in URL validation', [
        'error' => $e->getMessage(),
        'username' => $this->getUsername()
      ]);
      $this->send404Response();
      return false;
    }
  }

  /**
   * Sends a 404 Not Found response with structured JSON data
   */
  private function send404Response(): void
  {
    header("HTTP/1.1 404 Not Found");
    header("Content-Type: application/json");

    echo json_encode([
      'error' => 'Not Found',
      'timestamp' => date('c')
    ]);

    exit();
  }

  /**
   * Checks if the provided credentials are valid
   *
   * @return bool Returns true if credentials are valid, false otherwise
   */
  public static function checkRateLimit(string $identifier, string $action): bool
  {
    return parent::checkRateLimit($identifier, $action);
  }

  /**
   * @param string $username
   * @return bool
   */
  public static function isAccountLocked(string $username): bool
  {
    $result = parent::isAccountLocked($username);

    return $result;
  }

  /**
   * Increments the failed attempts counter for a given username
   *
   * @param string $username The username to increment failed attempts for
   */
  public static function incrementFailedAttempts(string $username): void
  {
    parent::incrementFailedAttempts($username);
  }

  /**
   * Resets the failed attempts counter for a given username
   *
   * @param string $username The username to reset failed attempts for
   * @return bool Returns true if reset was successful, false otherwise
   */
  public static function resetFailedAttempts(string $username)
  {
    return parent::resetFailedAttempts($username);
  }

  /**
   * Logs security events to a file with structured data
   *
   * @param string $event The event type (e.g., 'Authentication', 'RateLimitExceeded')
   * @param array $data Additional data to log (optional)
   */
  public static function logSecurityEvent(string $event, array $data = [])
  {
    return parent::logSecurityEvent($event, $data);
  }

  /**
   * Validates the IP address for a given API ID
   *
   * @param int $api_id The unique identifier of the API
   * @return bool Returns true if the IP is valid, false otherwise
   */
  public static function getIps(int $api_id): bool
  {
    return parent::validateIp($api_id);
  }

  /**
   * @param string $ip
   * @param string $range
   * @return bool
   */
  public static function ipInRange(string $ip, string $range): bool
  {
    return parent::ipInRange($ip, $range);
  }
}