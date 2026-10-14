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
 * | Method | Path/Query Parameter                               | Function in GptRetailers.php | Description                                                                                 |
 * ----------------------------------------------------------------------------------------------------------------------------------------
 * | GET    | ...?openai&retailers/products                      | getProducts()                  | Retrieves the full product catalog.                                                           |
 * | GET    | ...?openai&retailers/checkout_sessions             | listSessions()                 | Lists all existing checkout session IDs or full sessions.                                     |
 * | GET    | ...?openai&retailers/checkout_sessions/{id}        | getSessionById()               | Retrieves a specific checkout session by ID.                                                  |
 * | POST   | ...?openai&retailers/checkout_sessions             | createSession()                | Creates a new checkout session with initial items and addresses (requires 'items' in body).   |
 * | POST   | ...?openai&retailers/checkout_sessions/{id}        | completeSessionWithStripe() /  | Completes a session, either with a Stripe PaymentIntent or a delegated payment reference.   |
 * |        |                                                    | completeSessionWithDelegatedPayment()|                                                                                           |
 * | PATCH  | ...?openai&retailers/checkout_sessions/{id}        | updateSession()                | Updates fields (items, addresses, metadata) of an existing session.                           |
 * | DELETE | ...?openai&retailers/checkout_sessions/{id}        | deleteSession()                | Deletes a checkout session.                                                                   |
 * | POST   | ...?openai&retailers/stripe_webhook                | handleStripeWebhook()          | Handles asynchronous events from the Stripe payment gateway.                                  |
 * | POST   | ...?openai&retailers/create_order&session_id={id}  | createOrderFromSession()       | Creates a ClicShopping order from an existing, completed session.                             |
 * | POST   | ...?openai&retailers/complete_and_order&session_id={id}| completeSessionAndCreateOrder()| Completes the session with payment data AND creates the ClicShopping order.                 |
 * ----------------------------------------------------------------------------------------------------------------------------------------
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Sites\Shop\Pages\GptRetailers;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Content-Type: application/json');

use ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop\Retails\GptRetailers as retails;
use ClicShopping\OM\Registry;

/**
 * Class GptRetailers
 *
 * Implements the API routing logic for the OpenAI Retailers Agent Controlled Purchase (ACP)
 * system. It acts as the HTTP entry point, handling request methods (GET, POST, PATCH, DELETE)
 * and URI parameters to dispatch calls to the ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop\Retails\GptRetailers class.
 * All responses are formatted as JSON.
 */
class GptRetailers extends \ClicShopping\OM\Domains\PagesAbstract
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

// Get the request method and path
    $method = $_SERVER['REQUEST_METHOD'] ? $_POST : $_GET;;
    $path = $_SERVER['REQUEST_URI'];
    $hasProductsParam = isset($_GET['retailers/products']) || isset($_GET['products']);
    $hasCheckoutParam = isset($_GET['checkout_sessions']);
    $checkoutIdParam = isset($_GET['id']) ? (string)$_GET['id'] : null;
    $hasStripeWebhookParam = isset($_GET['stripe_webhook']);

// Handle CORS preflight
    if ($method === 'OPTIONS') {
      header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
      http_response_code(204);
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
    if ($method === 'GET' && ($hasCheckoutParam && !empty($checkoutIdParam) || preg_match('#/checkout_sessions/([^/]+)#', $path, $matches))) {
      $sessionId = $checkoutIdParam ?? $matches[1] ?? null;
      $session = $CLICSHOPPING_getRetailers->getSessionById($sessionId);
      if ($session === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Session not found']);
      } else {
        echo json_encode(['checkout_session' => $session], JSON_UNESCAPED_SLASHES);
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
        // Completion: POST /checkout_sessions/{id}
        // Choose Delegated Payment or Stripe based on payload
        if (!empty($input['delegated_payment'])) {
          $session = $CLICSHOPPING_getRetailers->completeSessionWithDelegatedPayment($sessionId, $input);
        } else {
          $session = $CLICSHOPPING_getRetailers->completeSessionWithStripe($sessionId, $input);
        }

        if ($session === null) {
          http_response_code(404);
          echo json_encode(['error' => 'Session not found']);
        } else {
          echo json_encode(['checkout_session' => $session], JSON_UNESCAPED_SLASHES);
        }
} else {
        // Creation: POST /checkout_sessions
        // Build items from input IDs/quantities
        if (empty($input['items']) || !is_array($input['items'])) {
          http_response_code(400);
          echo json_encode(['error' => 'Invalid payload: items required']);
          exit;
        }

        $items = $CLICSHOPPING_getRetailers->buildItemsFromIds($input['items']);

        $sessionData = [
          'items' => $items,
          'shipping_address' => $input['shipping_address'] ?? [],
          'billing_address' => $input['billing_address'] ?? [],
          'metadata' => $input['metadata'] ?? []
        ];
        $session = $CLICSHOPPING_getRetailers->createSession($sessionData);
        echo json_encode(['checkout_session' => $session], JSON_UNESCAPED_SLASHES);
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
        $allowed = ['items','shipping_address','billing_address','metadata'];
        foreach (array_keys($input) as $k) {
          if (!in_array($k, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid field: ' . $k]);
            exit;
          }
}
      }
      $session = $CLICSHOPPING_getRetailers->updateSession($sessionId, $input);
      if ($session) {
        echo json_encode(['checkout_session' => $session], JSON_UNESCAPED_SLASHES);
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

      $customerData = $input['customer_data'] ?? [];
      $paymentData = $input['payment_data'] ?? [];

      $result = $CLICSHOPPING_getRetailers->completeSessionAndCreateOrder($completeSessionIdParam, $customerData, $paymentData);
      echo json_encode($result, JSON_UNESCAPED_SLASHES);
      exit;
    }

// Fallback
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
  }
}

