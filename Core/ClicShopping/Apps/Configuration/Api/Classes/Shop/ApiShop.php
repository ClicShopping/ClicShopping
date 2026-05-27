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


// (B18) Removed ~100 lines of commented dead code that duplicated and contradicted
// the parent::checkToken implementation. Inheritance is sufficient.

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

  // (B18) Removed trivial wrappers (isAccountLocked, checkRateLimit,
  // incrementFailedAttempts, resetFailedAttempts, logSecurityEvent): they
  // just called parent:: with no added value. Direct inheritance from
  // ApiSecurity exposes the same methods.
}