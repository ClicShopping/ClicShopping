<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Api\Sites\Shop\Pages\Category;

use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiShop;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Category extends \ClicShopping\OM\Domains\PagesAbstract
{
  protected string|null $file = null;
  protected bool $use_site_template = false;
  private mixed $Db;

  /**
    * Initializes the Categories API page, handling authentication, request method routing,
    * and permission checks for category-related API actions (GET, POST, DELETE).
    */
  protected function init()
  {
    $this->Db = Registry::get('Db');

    if (!\defined('CLICSHOPPING_APP_API_AI_STATUS') || CLICSHOPPING_APP_API_AI_STATUS == 'False') {
      return $this->sendErrorResponse('API is disabled');
    }

    $requestMethod = ApiShop::requestMethod();
    $token = HTML::sanitize($_GET['token'] ?? null);

    if (!$token || !ApiShop::checkToken($token)) {
      return $this->sendErrorResponse('Invalid or missing token');
    }

    // Handle request method logic
    $statusCheck = $this->getStatusCheck($token);

    switch ($requestMethod) {
      case 'GET':
        return $this->handleGetRequest($statusCheck);
      case 'DELETE':
        return $this->handleDeleteRequest($statusCheck);
      case 'POST':
        return $this->handlePostRequest($statusCheck);
      case 'PUT':
        return $this->handlePutRequest($statusCheck);
      default:
        return $this->sendErrorResponse('Unsupported request method');
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
      'get' => $this->statusCheck('get_categories_status', $token),
      'delete' => $this->statusCheck('delete_categories_status', $token),
      'update' => $this->statusCheck('update_categories_status', $token),
      'insert' => $this->statusCheck('insert_categories_status', $token)
    ];
  }

  /**
   * Handle GET request
   */
  private function handleGetRequest(array $statusCheck)
  {
    if ($statusCheck['get'] == 0) {
      return $this->sendErrorResponse('Category fetch not allowed');
    }

    return $this->sendSuccessResponse(static::getCategories());
  }

  /**
   * Handle PUT request
   */
  private function handlePutRequest(array $statusCheck)
  {
    if (!$statusCheck['update'] == 0) {
      return $this->sendErrorResponse('Update not allowed');
    }

    return $this->sendSuccessResponse('Category updated successfully');
  }

  /**
   * Handle DELETE request
   */
  private function handleDeleteRequest(array $statusCheck)
  {
    if ($statusCheck['delete'] == 0) {
      return $this->sendErrorResponse('Category deletion not allowed');
    }

    return $this->sendSuccessResponse(static::deleteCategories());
  }

  /**
   * Handle POST request
   */
  private function handlePostRequest(array $statusCheck)
  {
    if (isset($_GET['update']) && $statusCheck['update'] == 0) {
      return $this->sendErrorResponse('Category update not allowed');
    }

    if (isset($_GET['insert']) && $statusCheck['insert'] == 0) {
      return $this->sendErrorResponse('Category insertion not allowed');
    }

    return $this->sendSuccessResponse(self::saveCategories());
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
  private function sendErrorResponse(string $message): array
  {
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit;
  }

  /**
   * Retrieves a list of categories through the API.
   *
   * @return array The API response containing the categories or an error response.
   */
  private static function getCategories(): array
  {
    return self::handleCategoryAction('ApiGetCategories');
  }

  /**
   * Deletes categories by invoking the 'ApiDeleteCategories' hook.
   * Clears the API cache after the operation is completed.
   *
   * @return array The HTTP response indicating the success or failure of the operation.
   */
  private static function deleteCategories(): array
  {
    return self::handleCategoryAction('ApiDeleteCategories');
  }

  /**
   * Saves the provided category data through the API call and handles the response.
   *
   * @return array The API response, either an HTTP OK response with the results or a not found response if the operation fails.
   */
  private static function saveCategories(): array
  {
    return self::handleCategoryAction('ApiPutCategories');
  }

  /**
   * Handles the category action by invoking the appropriate hook and clearing the cache.
   *
   * @param string $action The action to be performed (e.g., 'ApiGetCategories', 'ApiDeleteCategories', etc.).
   * @return array The HTTP response indicating the success or failure of the operation.
   */
  private static function handleCategoryAction(string $action): array
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
