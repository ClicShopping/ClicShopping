<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Classes\Shop;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class ApiShop extends ApiSecurity
{
  /**
   * Returns the request method used in the HTTP request
   *
   * @return string Returns the request method (GET, POST, etc.)
   * @throws Exception If REQUEST_METHOD is not available
   */
  public static function requestMethod(): string
  {
    if (!isset($_SERVER["REQUEST_METHOD"])) {
      throw new Exception("REQUEST_METHOD not available");
    }

    $method = strtoupper($_SERVER["REQUEST_METHOD"]);
    $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];

    if (!in_array($method, $allowedMethods)) {
      throw new Exception("Invalid HTTP method: " . $method);
    }

    return $method;
  }

  /**
   * Checks if the given username and API key grant access with rate limiting
   *
   * @param string $username The username to authenticate.
   * @param string $key The API key associated with the username.
   * @return void
   * @throws Exception|\Exception If database error occurs or rate limit exceeded
   */

  public static function getAccess(string $username, string $key):void
  {
    try {
      self::performAuthentication($username, $key);
      self::authenticateCredentials($username, $key);
    } catch (\Exception $e) {
      // Les exceptions sont déjà gérées dans performAuthentication
      throw $e;
    }
  }

  /**
   * Creates a new API session and stores it in the database.
   *
   * @param int|null $api_id The API ID associated with the session.
   * @return int The ID of the newly created session entry in the database.
   * @throws Exception If session creation fails
   */
  public static function createSession(int|null $api_id): int
  {
    // Validation
    if ($api_id !== null && $api_id <= 0) {
      throw new Exception("Invalid API ID provided");
    }

    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      if (!$CLICSHOPPING_Db) {
        throw new Exception("Database connection not available");
      }

      $Ip = HTTP::getIpAddress();

      if (empty($Ip)) {
        throw new Exception("Unable to determine client IP address");
      }

      $session_id = bin2hex(random_bytes(16));

      $sql_data_array = [
        'api_id' => $api_id,
        'session_id' => $session_id,
        'ip' => $Ip,
        'date_added' => 'now()',
        'date_modified' => 'now()'
      ];

      $result = $CLICSHOPPING_Db->save('api_session', $sql_data_array);

      if (!$result) {
        throw new Exception("Failed to create session");
      }

      $sessionId = $CLICSHOPPING_Db->lastInsertId();

      if (!$sessionId) {
        throw new Exception("Failed to retrieve session ID");
      }

      self::logSecurityEvent('Session created', [
        'api_id' => $api_id,
        'session_id' => $session_id,
        'ip' => $Ip
      ]);

      return $sessionId;

    } catch (PDOException $e) {
      self::logSecurityEvent('Database error in createSession', [
        'api_id' => $api_id,
        'error' => $e->getMessage()
      ]);
      throw new Exception("Session creation failed");
    }
  }


  /**
   * Validates and regenerates the API session token if necessary
   *
   * @param string $token The current session token to check or renew.
   * @return string The valid session token, either existing or newly generated.
   * @throws Exception If token processing fails
   */
  public static function checkToken(string $token): string
  {
    return parent::checkToken($token);
/*
    // Validation du token
    if (empty($token)) {
      throw new Exception("Token cannot be empty");
    }

    if (strlen($token) !== 32 || !ctype_xdigit($token)) {
      throw new Exception("Invalid token format");
    }

    // Vérification du rate limiting pour les tokens
    $clientIp = HTTP::getIpAddress();

    if (!self::checkRateLimit($clientIp, 'token_check')) {
      throw new Exception("Rate limit exceeded for token validation");
    }

    try {
      $CLICSHOPPING_Db = Registry::get('Db');

      if (!$CLICSHOPPING_Db) {
        throw new Exception("Database connection not available");
      }

      $sql_data_array = [
        'api_id',
        'date_modified',
        'date_added'
      ];

      $Qcheck = $CLICSHOPPING_Db->get('api_session', $sql_data_array, ['session_id' => $token], 1);

      if (!empty($Qcheck->value('api_id'))) {
        $now = date('Y-m-d H:i:s');
        $date_diff = DateTime::getIntervalDate($Qcheck->value('date_modified'), $now);

        // Session expirée après le timeout configuré
        if ($date_diff > (int)CLICSHOPPING_APP_API_AI_SESSION_TIMEOUT_MINUTES) {
          // Régénérer la session
          $CLICSHOPPING_Db->delete('api_session', ['api_id' => (int)$Qcheck->valueInt('api_id')]);

          $session_id = bin2hex(random_bytes(16));
          $Ip = HTTP::getIpAddress();

          $sql_data_array = [
            'api_id' => $Qcheck->valueInt('api_id'),
            'session_id' => $session_id,
            'date_modified' => 'now()',
            'date_added' => $Qcheck->value('date_added'),
            'ip' => $Ip
          ];

          $CLICSHOPPING_Db->save('api_session', $sql_data_array);

          self::logSecurityEvent('Session regenerated', [
            'old_token' => $token,
            'new_token' => $session_id,
            'api_id' => $Qcheck->valueInt('api_id')
          ]);

          return $session_id;
        }

        return $token; // Session encore valide
      } else {
        // Token invalide - créer une nouvelle session
        $session_id = bin2hex(random_bytes(16));
        $Ip = HTTP::getIpAddress();

        $sql_data_array = [
          'api_id' => null,
          'session_id' => $session_id,
          'date_modified' => 'now()',
          'date_added' => 'now()',
          'ip' => $Ip
        ];

        $CLICSHOPPING_Db->save('api_session', $sql_data_array);

        self::logSecurityEvent('New session created for invalid token', [
          'old_token' => $token,
          'new_token' => $session_id
        ]);

        return $session_id;
      }
    } catch (PDOException $e) {
      self::logSecurityEvent('Database error in checkToken', [
        'token' => $token,
        'error' => $e->getMessage()
      ]);
      throw new Exception("Token validation failed");
    }
*/
  }

  /**
   * Clears specific cached data for categories, products also purchased, and upcoming items.
   *
   * @return void
   * @throws Exception If cache clearing fails
   */
  public static function clearCache(): void
  {
    Cache::clear('categories');
    Cache::clear('products-also_purchased');
    Cache::clear('upcoming');
  }

  /**
   * Generates a 404 Not Found HTTP response.
   *
   * @return array Returns an array containing the status code header and a JSON-encoded body with an error message.
   */
  public static function notFoundResponse(): array
  {
    return [
      'status_code_header' => 'HTTP/1.1 404 Not Found',
      'body' => json_encode([
        'error' => 'Resource not found',
        'timestamp' => date('c')
      ])
    ];
  }

  /**
   * Generates an HTTP response with a status code header of '200 OK'
   *
   * @param array $result The array of data to be included in the response body.
   * @return array An associative array containing the response header and body.
   * @throws Exception If JSON encoding fails
   */
  public static function HttpResponseOk(array $result): array
  {
    $jsonResult = json_encode($result, JSON_UNESCAPED_UNICODE);

    if ($jsonResult === false) {
      throw new Exception("Failed to encode response as JSON");
    }

    return [
      'status_code_header' => 'HTTP/1.1 200 OK',
      'body' => $jsonResult
    ];
  }

  /**
   * Checks if the account is locked due to too many failed login attempts
   *
   * @param string $username The username to check.
   * @return bool Returns true if the account is locked, otherwise false.
   */
  public static function isAccountLocked(string $username): bool
  {
    $result =  parent::isAccountLocked($username);

    return $result;
  }

  /**
   * Vérifie le rate limiting pour un identifiant et un type d'action
   */
  public static function checkRateLimit(string $identifier, string $action): bool
  {
    return parent::checkRateLimit($identifier, $action);
  }

  /**
   * Incrémente le compteur de tentatives échouées
   */
  public static function incrementFailedAttempts(string $username): void
  {
    parent::incrementFailedAttempts($username);
  }

  /**
   * Remet à zéro le compteur de tentatives échouées
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
   parent::logSecurityEvent($event, $data);
  }
}