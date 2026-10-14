<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/*
 * GptRetailers UCP API Endpoint Documentation:
 *
 * This class acts as the API gateway for the Universal Commerce Protocol (UCP)
 * system, handling routing and serving data/actions over HTTP requests.
 *
 * Endpoints:
 * ----------------------------------------------------------------------------------------------------------------------------------------
 * | Method | Path/Query Parameter                                        | Function in GptRetailersUCP.php       | Description                                                                 |
 * ----------------------------------------------------------------------------------------------------------------------------------------
 * | GET    | ...?Google&UCP&retailers/products                                  | getProducts()                         | Retrieves the product catalog.                                             |
 * | POST   | ...?Google&UCP&retailers/checkout_sessions                         | createSession()                       | Creates a new checkout session.                                            |
 * | PATCH  | ...?Google&UCP&retailers/checkout_sessions/{id}                    | updateSession()                       | Updates an existing checkout session.                                      |
 * | POST   | ...?Google&UCP&retailers/checkout_sessions/{id}/complete           | completeSession()                     | Completes checkout and creates the order.                                  |
 * | POST   | ...?Google&UCP&retailers/webhook                                   | handleWebhook()                       | Handles payment webhooks.                                                  |
 * ----------------------------------------------------------------------------------------------------------------------------------------
 */
namespace ClicShopping\Apps\AI\Ecommerce\Sites\Shop\Pages\UCP;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
header('Content-Type: application/json; charset=UTF-8');

use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\UCP\GptRetailersUCP as RetailersUcp;
use ClicShopping\AI\Security\RateLimit;
use ClicShopping\OM\Registry;
use ClicShopping\OM\SimpleLogger;

class UCP extends \ClicShopping\OM\Domains\PagesAbstract
{
  /**
   * @var string|null Stores the file path, inherited from PagesAbstract but not directly used for this API endpoint.
   */
  protected string|null $file = null;

  /**
   * @var bool Disables the standard site template since this class serves a JSON API.
   */
  protected bool $use_site_template = false;

  /**
   * Initializes the GptRetailers API endpoint.
   *
   * 1. Sets up necessary CORS headers.
   * 2. Retrieves or registers the core `GptRetailers` business logic class.
   * 3. Reads the request method, path, and body.
   * 4. Implements the routing logic to call the appropriate method in the business class
   * based on the endpoint and HTTP method, and sends the JSON response.
   * 5. Handles CORS preflight (OPTIONS) requests.
   */
  public function init()
  {
    if (!Registry::exists('gptRetailersUcp')) {
      Registry::set('gptRetailersUcp', new RetailersUcp());
    }

    $ucp = Registry::get('gptRetailersUcp');
    $logger = Registry::exists('SimpleLogger') ? Registry::get('SimpleLogger') : new SimpleLogger('UCP_API');

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = $_SERVER['REQUEST_URI'] ?? '';
    $requestId = uniqid('req_');

    if (!empty($_SERVER['HTTP_IDEMPOTENCY_KEY'])) {
      header('Idempotency-Key: ' . $_SERVER['HTTP_IDEMPOTENCY_KEY']);
    }

    if (!empty($_SERVER['HTTP_REQUEST_ID'])) {
      header('Request-Id: ' . $_SERVER['HTTP_REQUEST_ID']);
    }

    if ($method === 'OPTIONS') {
      header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
      http_response_code(204);
      exit;
    }

    $errorResponse = function (string $code, string $message, array $details = [], int $status = 400) use ($requestId, $logger, $method, $path) {
      $logger->error('UCP request error', [
        'request_id' => $requestId,
        'method' => $method,
        'path' => $path,
        'code' => $code,
        'status' => $status
      ]);
      http_response_code($status);
      echo json_encode([
        'error' => [
          'code' => $code,
          'message' => $message,
          'details' => $details,
          'request_id' => $requestId
        ]
      ], JSON_UNESCAPED_SLASHES);
      exit;
    };

    $logger->info('UCP request received', [
      'request_id' => $requestId,
      'method' => $method,
      'path' => $path,
      'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    // The read-only product catalog can be browsed without a Bearer key
    // (config-gated, default ON) so AI agents discovering llms.txt can read it.
    // Transactional endpoints (checkout / webhook) always keep the Bearer auth.
    $publicCatalogEnabled = !\defined('CLICSHOPPING_APP_ECOMMERCE_UCP_PUBLIC_CATALOG')
      || CLICSHOPPING_APP_ECOMMERCE_UCP_PUBLIC_CATALOG === 'True';
    $isCatalogRequest = ($method === 'GET')
      && (isset($_GET['products']) || isset($_GET['retailers/products']) || str_contains($path, '/products'));
    $skipAuth = $publicCatalogEnabled && $isCatalogRequest;

    $expectedSecret = '';

    // Simple auth (shared key) — skipped for the public read-only catalog
    if (!$skipAuth) {
      try {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
          $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
          $expectedSecret = \defined('CLICSHOPPING_APP_ECOMMERCE_UCP_API_KEY_GOOGLE_RETAIL') ? CLICSHOPPING_APP_ECOMMERCE_UCP_API_KEY_GOOGLE_RETAIL : '';

          $expectedHeader = $expectedSecret !== '' ? 'Bearer ' . $expectedSecret : '';

          if ($expectedHeader === '' || $authHeader !== $expectedHeader) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES);
            exit;
          }
        } else {
          http_response_code(401);
          echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_SLASHES);
          exit;
        }
      } catch (\Throwable $e) {
        $errorResponse('INTERNAL_ERROR', 'Authorization error', [], 500);
      }
    }

    // Rate limiting (per API key or IP — applies to the public catalog too)
    $identifier = $expectedSecret !== '' ? $expectedSecret : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $maxRequests = \defined('CLICSHOPPING_APP_ECOMMERCE_UCP_RATE_LIMIT') ? (int)CLICSHOPPING_APP_ECOMMERCE_UCP_RATE_LIMIT : 100;
    $rateLimiter = new RateLimit('ucp', $maxRequests, 60);
    if (!$rateLimiter->checkLimit($identifier)) {
      $errorResponse('RATE_LIMIT_EXCEEDED', 'Too many requests', [], 429);
    }

// Read JSON input if POST or PATCH
    $rawBody = file_get_contents('php://input');
    $input = [];
    if ($method === 'POST' || $method === 'PATCH') {
      if ($rawBody !== '' && $rawBody !== false) {
        $input = json_decode($rawBody, true);
        if (!is_array($input)) {
          http_response_code(400);
          echo json_encode(['error' => 'Invalid JSON body']);
          exit;
        }
      } else {
        $input = [];
      }
    }

    $logger->debug('UCP request payload', [
      'request_id' => $requestId,
      'payload' => $this->sanitizeLogData($input)
    ]);

    $hasProductsParam = isset($_GET['products']) || isset($_GET['retailers/products']);
    $hasCheckoutParam = isset($_GET['checkout_sessions']) || isset($_GET['retailers/checkout_sessions']);
    $checkoutIdParam = isset($_GET['id']) ? (string)$_GET['id'] : null;
    $hasCompleteParam = isset($_GET['complete']);
    $hasWebhookParam = isset($_GET['webhook']);

    if ($method === 'GET' && ($hasProductsParam || str_contains($path, '/products'))) {
      $filters = [
        'page' => $_GET['page'] ?? 1,
        'limit' => $_GET['limit'] ?? 100,
        'category' => $_GET['category'] ?? null,
        'min_price' => $_GET['min_price'] ?? null,
        'max_price' => $_GET['max_price'] ?? null,
        'in_stock' => isset($_GET['in_stock']) ? (bool)$_GET['in_stock'] : null
      ];

      echo json_encode($ucp->getProducts($filters), JSON_UNESCAPED_SLASHES);
      $logger->info('UCP response', [
        'request_id' => $requestId,
        'status' => 200,
        'action' => 'products'
      ]);
      exit;
    }

    if ($method === 'POST' && ($hasCheckoutParam || str_contains($path, '/checkout_sessions'))) {
      if (($hasCompleteParam || str_contains($path, '/complete')) && (($checkoutIdParam !== null) || preg_match('#/checkout_sessions/([^/]+)/complete#', $path, $matches))) {
        $sessionId = $checkoutIdParam ?? $matches[1] ?? null;
        if (!$sessionId) {
          $errorResponse('VALIDATION_ERROR', 'Missing session id');
        }
        $result = $ucp->completeSession($sessionId, $input);
        if (isset($result['error'])) {
          $errorResponse($result['error']['code'] ?? 'PAYMENT_FAILED', $result['error']['message'] ?? 'Payment failed', $result['error']['details'] ?? []);
        }
        echo json_encode($result, JSON_UNESCAPED_SLASHES);
        $logger->info('UCP response', [
          'request_id' => $requestId,
          'status' => 200,
          'action' => 'checkout_sessions.complete'
        ]);
        exit;
      }

      $errors = $ucp->validateUcpInput($input, false);
      if (!empty($errors)) {
        $errorResponse('VALIDATION_ERROR', 'Validation failed', $errors);
      }

      $session = $ucp->createSession($input);
      http_response_code(201);
      echo json_encode(['checkout_session' => $session], JSON_UNESCAPED_SLASHES);
      $logger->info('UCP response', [
        'request_id' => $requestId,
        'status' => 201,
        'action' => 'checkout_sessions.create'
      ]);
      exit;
    }

    if ($method === 'PATCH' && (($hasCheckoutParam && !empty($checkoutIdParam)) || preg_match('#/checkout_sessions/([^/]+)#', $path, $matches))) {
      $sessionId = $checkoutIdParam ?? $matches[1] ?? null;
      if (!$sessionId) {
        $errorResponse('VALIDATION_ERROR', 'Missing session id');
      }

      $errors = $ucp->validateUcpInput($input, true);
      if (!empty($errors)) {
        $errorResponse('VALIDATION_ERROR', 'Validation failed', $errors);
      }

      $session = $ucp->updateSession($sessionId, $input);
      if ($session === null) {
        $errorResponse('SESSION_NOT_FOUND', 'Session not found', [], 404);
      }

      echo json_encode(['checkout_session' => $session], JSON_UNESCAPED_SLASHES);
      $logger->info('UCP response', [
        'request_id' => $requestId,
        'status' => 200,
        'action' => 'checkout_sessions.update'
      ]);
      exit;
    }

    if ($method === 'POST' && ($hasWebhookParam || str_contains($path, '/webhook'))) {
      $result = $ucp->handleWebhook($input);
      echo json_encode($result, JSON_UNESCAPED_SLASHES);
      $logger->info('UCP response', [
        'request_id' => $requestId,
        'status' => 200,
        'action' => 'webhook'
      ]);
      exit;
    }

    $errorResponse('NOT_FOUND', 'Unknown UCP action', [], 404);
  }

  /**
   * Sanitize sensitive data before logging.
   *
   * @param array $data
   * @return array
   */
  private function sanitizeLogData(array $data): array
  {
    $sensitiveKeys = ['payment_data', 'api_key', 'authorization', 'card', 'token'];
    foreach ($sensitiveKeys as $key) {
      if (isset($data[$key])) {
        $data[$key] = '[REDACTED]';
      }
    }

    return $data;
  }
}
