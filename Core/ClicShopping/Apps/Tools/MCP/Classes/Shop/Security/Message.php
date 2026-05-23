<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\Shop\Security;

/**
 * Handles all API responses for the Multi-Channel Products (MCP) API.
 * This class ensures a consistent JSON format for both success and error messages.
 */
class Message
{
  /**
   * Sends a successful API response with the provided data.
   *
   * The method formats the data into a standardized JSON structure
   * and terminates the script. It sets the HTTP status code to 200.
   *
   * @param array|object $data The data payload to be included in the response.
   */
  public function sendSuccess(array|object $data): void
  {
    http_response_code(200);

    echo json_encode([
      'status' => 'success',
      'data' => $data,
      'timestamp' => date('c'),
      'source' => 'clicshopping_mcp_api'
    ]);
    exit;
  }

  /**
   * Sends an error API response with a specified message and status code.
   *
   * The method formats the error message into a standardized JSON structure
   * and terminates the script. It sets the appropriate HTTP status code.
   *
   * @param string $message The error message to be sent.
   * @param int $code The HTTP status code for the error (default is 400).
   */
  public function sendError(string $message, int $code = 400): void
  {
    http_response_code($code);

    echo json_encode([
      'status' => 'error',
      'message' => $message,
      'timestamp' => date('c'),
      'source' => 'clicshopping_mcp_api'
    ]);
    exit;
  }
}