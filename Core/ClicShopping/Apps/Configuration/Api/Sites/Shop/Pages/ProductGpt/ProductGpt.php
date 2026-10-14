<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Sites\Shop\Pages\ProductGpt;

use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiSecurity;
use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiShop;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class ProductGpt extends \ClicShopping\OM\Domains\PagesAbstract
{
  protected string|null $file = null;
  protected bool $use_site_template = false;
  private mixed $Db;

  /**
    * Initializes the ProductsGpt API page, handling authentication, request method routing,
    * and permission checks for productGpt-related API actions (GET, POST, DELETE).
    */
  protected function init()
  {
    $this->Db = Registry::get('Db');

    if (!\defined('CLICSHOPPING_APP_API_AI_STATUS') || CLICSHOPPING_APP_API_AI_STATUS == 'False') {
      return $this->sendErrorResponse('API is disabled', 503);
    }

    try {
      $requestMethod = ApiShop::requestMethod();
    } catch (\Exception $e) {
      return $this->sendErrorResponse('Unsupported request method', 405);
    }

    $token = HTML::sanitize(ApiSecurity::extractToken());

    if ($token === '') {
      return $this->sendErrorResponse('Invalid or missing token', 401);
    }

    try {
      $validToken = ApiShop::checkToken($token);
    } catch (\Exception $e) {
      return $this->sendErrorResponse('Invalid or missing token', 401);
    }

    ApiSecurity::emitSessionHeaders($validToken);

    // Handle request method logic
    $statusCheck = $this->getStatusCheck($validToken);

    switch ($requestMethod) {
      case 'GET':
        return $this->handleGetRequest($statusCheck);
      default:
        return $this->sendErrorResponse('Unsupported request method', 405);
    }
  }

  /**
   * Get status check for various actions
   *
   * @param string $token The session token used for identifying the API session.
   * @return array An associative array containing the status checks for various actions.
   */
  private function getStatusCheck(string $token): array
  {
    return [
      'get' => $this->statusCheck('get_product_status', $token),
    ];
  }

  /**
   * Handle GET request
   */
  private function handleGetRequest(array $statusCheck)
  {
    if ($statusCheck['get'] == 0) {
      return $this->sendErrorResponse('Product fetch not allowed');
    }

    return $this->sendSuccessResponse(static::getProductsGpt());
  }


  /**
   * Sends a success response with the provided data.
   *
   * @param mixed $data The data to be included in the success response.
   * @return array The HTTP response indicating success.
   */
  private function sendSuccessResponse(mixed $data): array
  {
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
  }

  /**
   * Sends an error response with the provided message.
   *
   * @param string $message The error message to be included in the response.
   * @return array The HTTP response indicating an error.
   */
  private function sendErrorResponse(string $message, int $code = 400): array
  {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
  }

  /**
   * Retrieves a list of saveProducts through the API.
   *
   * @return array The API response containing the saveProducts or an error response.
   */
  private static function getProductsGpt(): array
  {
    return self::handleProductGptAction('ApiGetProductGpt');
  }

  /**
   * Handles the productGpt action by invoking the appropriate hook and clearing the cache.
   *
   * @param string $action The action to be performed (e.g., 'ApiGetProducts', 'ApiDeleteProducts', etc.).
   * @return array The HTTP response indicating the success or failure of the operation.
   */
  private static function handleProductGptAction(string $action): array
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $result = $CLICSHOPPING_Hooks->call('Api', $action);

    if (empty($result)) {
      return ApiShop::notFoundResponse();
    }

    ApiShop::clearCache();
    return ApiShop::HttpResponseOk($result);
  }

  /**
   * Checks the status based on the provided string and token.
   *
   * @param string $string The column name to be selected from the database.
   * @param string $token The session token used for identifying the API session.
   * @return int The integer value associated with the specified column.
   */
  private function statusCheck(string $string, string $token): int
  {
    $QstatusCheck = $this->Db->prepare('select a.' . $string . '
                                          from :table_api a,
                                               :table_api_session ase
                                          where a.api_id = ase.api_id
                                          and ase.session_id = :session_id  
                                        ');
    $QstatusCheck->bindValue('session_id', $token);
    $QstatusCheck->execute();

    return $QstatusCheck->valueInt($string);
  }
}
