<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\Shop\Security;


use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use Exception;
use PDOException;


/**
 * Handles MCP security functions for the Shop side.
 */
class McpShop extends McpSecurity
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
   * Checks if the given username and MCP key grant access with rate limiting
   *
   * @param string $username The username to authenticate.
   * @param string $key The MCP key associated with the username.
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
   * Creates a new MCP session and stores it in the database.
   *
   * @param int|null $mcp_id The MCP ID associated with the session.
   * @return int The ID of the newly created session entry in the database.
   * @throws Exception If session creation fails
   */
  public static function createSession(int|null $mcp_id): int
  {
    // Validation
    if ($mcp_id !== null && $mcp_id <= 0) {
      throw new Exception("Invalid MCP ID provided");
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
        'mcp_id' => $mcp_id,
        'session_id' => $session_id,
        'ip' => $Ip,
        'date_added' => 'now()',
        'date_modified' => 'now()'
      ];

      $result = $CLICSHOPPING_Db->save('mcp_session', $sql_data_array);

      if (!$result) {
        throw new Exception("Failed to create session");
      }

      $sessionId = $CLICSHOPPING_Db->lastInsertId();

      if (!$sessionId) {
        throw new Exception("Failed to retrieve session ID");
      }

      self::logSecurityEvent('Session created', [
        'mcp_id' => $mcp_id,
        'session_id' => $session_id,
        'ip' => $Ip
      ]);

      return $sessionId;

    } catch (PDOException $e) {
      self::logSecurityEvent('Database error in createSession', [
        'mcp_id' => $mcp_id,
        'error' => $e->getMessage()
      ]);
      throw new Exception("Session creation failed");
    }
}

  /**
   * Validates and regenerates the MCP session token if necessary
   *
   * @param string $token The current session token to check or renew.
   * @return string The valid session token, either existing or newly generated.
   * @throws Exception If token processing fails
   */
  public static function checkToken(string $token): string
  {
    return parent::checkToken($token);
  }

  /**
   * Clears specific cached data for categories, products also purchased, and upcoming items.
   *
   * @return void
   * @throws Exception If cache clearing fails
   */
  public static function clearCache(): void
  {

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