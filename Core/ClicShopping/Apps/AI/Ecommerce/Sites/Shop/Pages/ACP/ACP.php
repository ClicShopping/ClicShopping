<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/*
 * GptRetailers API Endpoint Documentation:
 *
 * This class acts as the API gateway for the OpenAI Retailers Agent Controlled Purchase (ACP)
 * system, handling routing and serving data/actions over HTTP requests.
 *
 * Endpoints:
 * ----------------------------------------------------------------------------------------------------------------------------------------
 * | Method | Path/Query Parameter                                        | Function in GptRetailers.php        | Description                                                                 |
 * ----------------------------------------------------------------------------------------------------------------------------------------
 * | GET    | ...?OpenAI&ACP&retailers/products                            | getProducts()                         | Retrieves the full product catalog.                                        |
 * | GET    | ...?OpenAI&ACP&retailers/checkout_sessions                   | listSessions()                        | Lists all existing checkout session IDs or full sessions.                  |
 * | GET    | ...?OpenAI&ACP&retailers/checkout_sessions/{id}              | getSessionById()                      | Retrieves a specific checkout session by ID.                               |
 * | POST   | ...?OpenAI&ACP&retailers/checkout_sessions                   | createSession()                       | Creates a new checkout session (items or line_items required).             |
 * | POST   | ...?OpenAI&ACP&retailers/checkout_sessions/{id}              | updateSession()                       | Updates fields of an existing session.                                     |
 * | POST   | ...?OpenAI&ACP&retailers/checkout_sessions/{id}/complete     | completeSessionAndCreateOrder()       | Completes checkout and creates the order.                                  |
 * | POST   | ...?OpenAI&ACP&retailers/checkout_sessions/{id}/cancel       | cancelSession()                       | Cancels a checkout session.                                                |
 * | PATCH  | ...?OpenAI&ACP&retailers/checkout_sessions/{id}              | updateSession()                       | Updates fields (items, addresses, metadata) of an existing session.        |
 * | DELETE | ...?OpenAI&ACP&retailers/checkout_sessions/{id}              | deleteSession()                       | Deletes a checkout session.                                                |
 * | POST   | ...?OpenAI&ACP&retailers/stripe_webhook                      | handleStripeWebhook()                 | Handles Stripe webhook events.                                             |
 * | POST   | ...?OpenAI&ACP&retailers/create_order&session_id={id}        | createOrderFromSession()              | Creates a ClicShopping order from a completed session.                     |
 * | POST   | ...?OpenAI&ACP&retailers/complete_and_order&session_id={id}  | completeSessionAndCreateOrder()       | Completes the session then creates the order (legacy).                     |
 * | POST   | ...?OpenAI&ACP&agentic_commerce/delegate_payment             | handleDelegatePayment()               | Vaults a delegated payment token.                                          |
 * ----------------------------------------------------------------------------------------------------------------------------------------
 */
namespace ClicShopping\Apps\AI\Ecommerce\Sites\Shop\Pages\ACP;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Content-Type: application/json');

use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\ACP\GptRetailers as retails;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Class GptRetailers
 *
 * Implements the API routing logic for the OpenAI Retailers Agent Controlled Purchase (ACP)
 * system. It acts as the HTTP entry point, handling request methods (GET, POST, PATCH, DELETE)
 * and URI parameters to dispatch calls to the ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop\Retails\GptRetailers class.
 * All responses are formatted as JSON.
 */
class ACP extends \ClicShopping\OM\Domains\PagesAbstract
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
    if (!Registry::exists('gptRetailers')) {
      Registry::set('gptRetailers', new retails());
    }

    $CLICSHOPPING_getRetailers = Registry::get('gptRetailers');

    header('Content-Type: application/json; charset=UTF-8');
    if (!empty($_SERVER['HTTP_IDEMPOTENCY_KEY'])) {
      header('Idempotency-Key: ' . $_SERVER['HTTP_IDEMPOTENCY_KEY']);
    }

    if (!empty($_SERVER['HTTP_REQUEST_ID'])) {
      header('Request-Id: ' . $_SERVER['HTTP_REQUEST_ID']);
    }

// Get the request method and path
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = $_SERVER['REQUEST_URI'] ?? '';
    $hasProductsParam = isset($_GET['retailers/products']) || isset($_GET['products']);
    $hasCheckoutParam = isset($_GET['retailers/checkout_sessions']) || isset($_GET['checkout_sessions']);
    $checkoutIdParam = isset($_GET['id']) ? (string)$_GET['id'] : null;
    $hasStripeWebhookParam = isset($_GET['stripe_webhook']);
    $hasCompleteParam = isset($_GET['complete']);
    $hasCancelParam = isset($_GET['cancel']);
    $hasDelegatePaymentParam = isset($_GET['agentic_commerce/delegate_payment']) || isset($_GET['delegate_payment']);

// Handle CORS preflight
    if ($method === 'OPTIONS') {
      header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
      http_response_code(204);
      exit;
    }
//security
    try {
      if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
        $expectedSecret = \defined('CLICSHOPPING_APP_ECOMMERCE_ACP_API_KEY_OPENAI_RETAIL') ? CLICSHOPPING_APP_ECOMMERCE_ACP_API_KEY_OPENAI_RETAIL : '';

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
      http_response_code(500);
      echo json_encode(['error' => 'Authorization error'], JSON_UNESCAPED_SLASHES);
      exit;
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

// Simple routing

// --------------------------------------------------------------------------------
// POST /agentic_commerce/delegate_payment - Handle delegated payment vaulting
// --------------------------------------------------------------------------------
    if ($method === 'POST' && ($hasDelegatePaymentParam || str_contains($path, '/agentic_commerce/delegate_payment'))) {
      $headers = [
        'idempotency_key' => $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null,
        'request_id' => $_SERVER['HTTP_REQUEST_ID'] ?? null
      ];
      $result = $CLICSHOPPING_getRetailers->handleDelegatePayment($input, $headers);
      http_response_code($result['status']);
      echo json_encode($result['body'], JSON_UNESCAPED_SLASHES);
      exit;
    }

// --------------------------------------------------------------------------------
// GET /products - Returns product catalog
// --------------------------------------------------------------------------------

    if ($method === 'GET' && ($hasProductsParam || str_contains($path, '/products'))) {
      // Return full product catalog
      $products = $CLICSHOPPING_getRetailers->getProducts();
      echo json_encode(['products' => $products], JSON_UNESCAPED_SLASHES);

      exit;
    }
// --------------------------------------------------------------------------------
// GET /checkout_sessions/{id} - Retrieve session by ID
// --------------------------------------------------------------------------------
    if ($method === 'GET' && (($hasCheckoutParam && !empty($checkoutIdParam)) || preg_match('#/checkout_sessions/([^/]+)#', $path, $matches))) {
      $sessionId = $checkoutIdParam ?? $matches[1] ?? null;
      $session = $CLICSHOPPING_getRetailers->getSessionById($sessionId);
      if ($session === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
      } else {
        echo json_encode($session, JSON_UNESCAPED_SLASHES);
      }

      exit;
    }
// --------------------------------------------------------------------------------
// GET /checkout_sessions - List sessions
// --------------------------------------------------------------------------------
    if ($method === 'GET' && ($hasCheckoutParam || preg_match('#/checkout_sessions(\?|$)#', $path))) {
      $idsOnly = isset($_GET['ids_only']) ? (bool)$_GET['ids_only'] : false;
      $sessions = $CLICSHOPPING_getRetailers->listSessions(!$idsOnly);
      $key = $idsOnly ? 'session_ids' : 'checkout_sessions';

      echo json_encode([$key => $sessions], JSON_UNESCAPED_SLASHES);
      exit;
    }
// --------------------------------------------------------------------------------
// POST /checkout_sessions - Create session
// POST /checkout_sessions/{id} - Complete session
// --------------------------------------------------------------------------------

    $sessionId = null;

    if (preg_match('#/checkout_sessions/([^/]+)#', $path, $matches)) {
      $sessionId = $matches[1];
    } elseif (!empty($checkoutIdParam)) {
      // Fallback ou prise en compte du paramètre 'id' si l'ID n'est pas dans le chemin
      $sessionId = $checkoutIdParam;
    }

    if ($method === 'POST' && ($hasCheckoutParam || str_contains($path, '/checkout_sessions'))) {
      if ($sessionId) {
        if ($hasCompleteParam || str_contains($path, '/complete')) {
          $validation = $CLICSHOPPING_getRetailers->validateAcpInput($input, 'complete', $input['items'] ?? []);
          if (!empty($validation)) {
            http_response_code(400);
            echo json_encode(['messages' => $validation], JSON_UNESCAPED_SLASHES);
            exit;
          }
          $result = $CLICSHOPPING_getRetailers->completeSessionAndCreateOrder($sessionId, $input);
          if ($result === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
          } else {
            if (!empty($result['checkout_session'])) {
              echo json_encode($result['checkout_session'], JSON_UNESCAPED_SLASHES);
            } else {
              echo json_encode($result, JSON_UNESCAPED_SLASHES);
            }
          }
        } elseif ($hasCancelParam || str_contains($path, '/cancel')) {
          $result = $CLICSHOPPING_getRetailers->cancelSession($sessionId);
          if ($result === null) {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
          } else {
            echo json_encode($result, JSON_UNESCAPED_SLASHES);
          }
        } else {
          // Update session: POST /checkout_sessions/{id}
          $validation = $CLICSHOPPING_getRetailers->validateAcpInput($input, 'update', $input['items'] ?? []);
          if (!empty($validation)) {
            http_response_code(400);
            echo json_encode(['messages' => $validation], JSON_UNESCAPED_SLASHES);
            exit;
          }
          $session = $CLICSHOPPING_getRetailers->updateSession($sessionId, $input);
          if ($session) {
            echo json_encode($session, JSON_UNESCAPED_SLASHES);
          } else {
            http_response_code(404);
            echo json_encode(['error' => 'Session not found']);
          }
        }
      } else {
        // Creation: POST /checkout_sessions
        $itemsInput = $input['items'] ?? [];
        if (empty($itemsInput) && !empty($input['line_items']) && is_array($input['line_items'])) {
          $itemsInput = [];
          foreach ($input['line_items'] as $lineItem) {
            if (!empty($lineItem['item']['id']) && !empty($lineItem['item']['quantity'])) {
              $itemsInput[$lineItem['item']['id']] = $lineItem['item']['quantity'];
            }
          }
        } elseif (!empty($itemsInput) && is_array($itemsInput)) {
          $isList = array_keys($itemsInput) === range(0, count($itemsInput) - 1);
          if ($isList) {
            $itemsMap = [];
            foreach ($itemsInput as $item) {
              if (is_array($item) && !empty($item['id']) && !empty($item['quantity'])) {
                $itemsMap[$item['id']] = $item['quantity'];
              }
            }
            if (!empty($itemsMap)) {
              $itemsInput = $itemsMap;
            }
          }
        }

        if (empty($itemsInput) || !is_array($itemsInput)) {
          http_response_code(400);
          echo json_encode(['error' => 'Invalid payload: items or line_items required']);
          exit;
        }

        $items = $CLICSHOPPING_getRetailers->buildItemsFromIds($itemsInput);
        $validation = $CLICSHOPPING_getRetailers->validateAcpInput($input, 'create', $itemsInput);
        if (!empty($validation)) {
          http_response_code(400);
          echo json_encode(['messages' => $validation], JSON_UNESCAPED_SLASHES);
          exit;
        }

        $sessionData = [
          'items' => $items,
          'fulfillment_address' => $input['fulfillment_address'] ?? [],
          'shipping_address' => $input['shipping_address'] ?? [],
          'billing_address' => $input['billing_address'] ?? [],
          'buyer' => $input['buyer'] ?? [],
          'metadata' => $input['metadata'] ?? [],
          'messages' => $input['messages'] ?? [],
          'links' => $input['links'] ?? []
        ];
        $session = $CLICSHOPPING_getRetailers->createSession($sessionData);
        http_response_code(201);
        echo json_encode($session, JSON_UNESCAPED_SLASHES);
      }

      exit;
    }
// --------------------------------------------------------------------------------
// PATCH /checkout_sessions/{id} - Update session
// --------------------------------------------------------------------------------
    if ($method === 'PATCH' && (($hasCheckoutParam && !empty($checkoutIdParam)) || preg_match('#/checkout_sessions/([^/]+)#', $path, $matches))) {
      $sessionId = $checkoutIdParam ?? $matches[1] ?? null;
      // Optional: validate keys
      if (!empty($input)) {
        $allowed = ['items','line_items','shipping_address','fulfillment_address','billing_address','buyer','messages','links','metadata','fulfillment_option_id'];
        foreach (array_keys($input) as $k) {
          if (!in_array($k, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid field: ' . $k]);
            exit;
          }
        }
      }
      $validation = $CLICSHOPPING_getRetailers->validateAcpInput($input, 'update', $input['items'] ?? []);
      if (!empty($validation)) {
        http_response_code(400);
        echo json_encode(['messages' => $validation], JSON_UNESCAPED_SLASHES);
        exit;
      }
      $session = $CLICSHOPPING_getRetailers->updateSession($sessionId, $input);
      if ($session) {
        echo json_encode($session, JSON_UNESCAPED_SLASHES);
      } else {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
      }

      exit;
    }
// --------------------------------------------------------------------------------
// DELETE /checkout_sessions/{id} - Delete session
// --------------------------------------------------------------------------------
    if ($method === 'DELETE' && (($hasCheckoutParam && !empty($checkoutIdParam)) || preg_match('#/checkout_sessions/([^/]+)#', $path, $matches))) {
      $sessionId = $checkoutIdParam ?? $matches[1] ?? null;
      $deleted = $CLICSHOPPING_getRetailers->deleteSession($sessionId);
      if ($deleted) {
        echo json_encode(['deleted' => true, 'id' => $sessionId]);
      } else {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
      }
      exit;
    }
// --------------------------------------------------------------------------------
// POST /stripe_webhook - Handle Stripe webhooks
// --------------------------------------------------------------------------------
    if ($method === 'POST' && ($hasStripeWebhookParam || str_contains($path, '/stripe_webhook'))) {
      $CLICSHOPPING_getRetailers->handleStripeWebhook();

      exit;
    }
// --------------------------------------------------------------------------------
// POST /create_order - Create ClicShopping order from session
// --------------------------------------------------------------------------------
    $hasCreateOrderParam = isset($_GET['create_order']);
    $orderSessionIdParam = isset($_GET['session_id']) ? (string)$_GET['session_id'] : null;

    if ($method === 'POST' && ($hasCreateOrderParam || str_contains($path, '/create_order'))) {
      if (empty($orderSessionIdParam)) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID required']);
        exit;
      }

      $customerData = $input['customer_data'] ?? [];
      $paymentData = $input['payment_data'] ?? [];

      $result = $CLICSHOPPING_getRetailers->createOrderFromSession($orderSessionIdParam, $customerData, $paymentData);
      echo json_encode($result, JSON_UNESCAPED_SLASHES);
      exit;
    }
// --------------------------------------------------------------------------------
// POST /complete_and_order - Complete session then create order
// --------------------------------------------------------------------------------
    $hasCompleteAndOrderParam = isset($_GET['complete_and_order']);
    $completeSessionIdParam = isset($_GET['session_id']) ? (string)$_GET['session_id'] : null;

    if ($method === 'POST' && ($hasCompleteAndOrderParam || str_contains($path, '/complete_and_order'))) {
      if (empty($completeSessionIdParam)) {
        http_response_code(400);
        echo json_encode(['error' => 'Session ID required']);
        exit;
      }

      $validation = $CLICSHOPPING_getRetailers->validateAcpInput($input, 'complete', $input['items'] ?? []);
      if (!empty($validation)) {
        http_response_code(400);
        echo json_encode(['messages' => $validation], JSON_UNESCAPED_SLASHES);
        exit;
      }

      $result = $CLICSHOPPING_getRetailers->completeSessionAndCreateOrder($completeSessionIdParam, $input);
      echo json_encode($result, JSON_UNESCAPED_SLASHES);
      exit;
    }

// Fallback
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
  }
}
