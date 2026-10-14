<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\AnthropicEcommerce\Sub;

use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\UCP\GptSessionManager;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Message;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * Sessions sub-handler for the AnthropicEcommerce MCP endpoint.
 *
 * Manages checkout sessions via GptSessionManager (file-based JSON storage).
 * Does NOT depend on ACP or UCP protocol classes.
 *
 * Supported actions:
 *   - session_create   POST  Create a new checkout session
 *   - session_get      GET   Retrieve a session by ?session_id=
 *   - session_update   POST  Update an open session
 *   - session_complete POST  Mark session completed with payment data
 *   - session_cancel   POST  Cancel an open session
 *   - session_list     GET   List all active sessions
 *
 * POST body (JSON):
 * {
 *   "items": [{"product_id": "5", "quantity": 2}],
 *   "buyer": {"email": "john@example.com", "name": "John Doe"},
 *   "delivery_address": {
 *     "line1": "12 Rue de la Paix", "city": "Paris",
 *     "postal_code": "75001", "country": "FR"
 *   },
 *   "payment": {"provider": "stripe", "payment_intent_id": "pi_xxx"}
 * }
 */
class Sessions
{
  private mixed             $db;
  private Message           $message;
  private GptSessionManager $sessionManager;

  public function __construct(mixed $db, Message $message)
  {
    $this->db             = $db;
    $this->message        = $message;
    $this->sessionManager = new GptSessionManager();
  }

  // =========================================================================
  // Dispatcher
  // =========================================================================

  public function dispatch(string $action): void
  {
    match ($action) {
      'session_create'   => $this->createSession(),
      'session_get'      => $this->getSession(),
      'session_update'   => $this->updateSession(),
      'session_complete' => $this->completeSession(),
      'session_cancel'   => $this->cancelSession(),
      'session_list'     => $this->listSessions(),
      default            => $this->message->sendError('Unknown session action: ' . $action, 400),
    };
  }

  // =========================================================================
  // Actions
  // =========================================================================

  /**
   * Create a new checkout session.
   * POST body: { items, buyer, delivery_address? }
   */
  private function createSession(): void
  {
    $input = $this->readJsonBody();
    if ($input === null) {
      return;
    }

    $errors = $this->validateSessionInput($input);
    if (!empty($errors)) {
      $this->message->sendError(['errors' => $errors], 400);
      return;
    }

    try {
      $lineItems = $this->buildLineItems($input['items'] ?? []);

      $sessionData = [
        'status'           => 'open',
        'items'            => $lineItems,
        'buyer'            => $input['buyer'] ?? [],
        'delivery_address' => $input['delivery_address'] ?? $input['shipping_address'] ?? null,
        'payment'          => null,
        'metadata'         => $input['metadata'] ?? [],
      ];

      $sessionId = $this->sessionManager->create($sessionData);
      $session   = $this->sessionManager->get($sessionId);

      $this->message->sendSuccess(['checkout_session' => $session]);
    } catch (\Exception $e) {
      error_log('[AnthropicEcommerce][Sessions] createSession: ' . $e->getMessage());
      $this->message->sendError('Failed to create session: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Retrieve a session by ID.
   * GET params: session_id
   */
  private function getSession(): void
  {
    $sessionId = HTML::sanitize($_GET['session_id'] ?? '');
    if (empty($sessionId)) {
      $this->message->sendError('Missing session_id', 400);
      return;
    }

    try {
      $session = $this->sessionManager->get($sessionId);

      if ($session === null) {
        $this->message->sendError('Session not found: ' . $sessionId, 404);
        return;
      }

      $this->message->sendSuccess(['checkout_session' => $session]);
    } catch (\Exception $e) {
      error_log('[AnthropicEcommerce][Sessions] getSession: ' . $e->getMessage());
      $this->message->sendError('Failed to retrieve session: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Update an open session (address, items, buyer).
   * GET params: session_id
   * POST body: partial fields to merge
   */
  private function updateSession(): void
  {
    $sessionId = HTML::sanitize($_GET['session_id'] ?? $_POST['session_id'] ?? '');
    if (empty($sessionId)) {
      $this->message->sendError('Missing session_id', 400);
      return;
    }

    $input = $this->readJsonBody();
    if ($input === null) {
      return;
    }

    try {
      $existing = $this->sessionManager->get($sessionId);
      if ($existing === null) {
        $this->message->sendError('Session not found: ' . $sessionId, 404);
        return;
      }

      if (($existing['status'] ?? '') !== 'open') {
        $this->message->sendError('Session is not open and cannot be updated.', 409);
        return;
      }

      $updates = $input;
      if (!empty($input['items'])) {
        $updates['items'] = $this->buildLineItems($input['items']);
      }

      $ok = $this->sessionManager->update($sessionId, $updates);
      if (!$ok) {
        $this->message->sendError('Failed to update session', 500);
        return;
      }

      $session = $this->sessionManager->get($sessionId);
      $this->message->sendSuccess(['checkout_session' => $session]);
    } catch (\Exception $e) {
      error_log('[AnthropicEcommerce][Sessions] updateSession: ' . $e->getMessage());
      $this->message->sendError('Failed to update session: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Complete a session: store payment data and mark as completed.
   * GET params: session_id
   * POST body: { payment: { provider, payment_intent_id } }
   */
  private function completeSession(): void
  {
    $sessionId = HTML::sanitize($_GET['session_id'] ?? $_POST['session_id'] ?? '');
    if (empty($sessionId)) {
      $this->message->sendError('Missing session_id', 400);
      return;
    }

    $input = $this->readJsonBody();
    if ($input === null) {
      return;
    }

    if (empty($input['payment'])) {
      $this->message->sendError('Missing payment data', 400);
      return;
    }

    try {
      $existing = $this->sessionManager->get($sessionId);
      if ($existing === null) {
        $this->message->sendError('Session not found: ' . $sessionId, 404);
        return;
      }

      if (($existing['status'] ?? '') !== 'open') {
        $this->message->sendError('Session is not open and cannot be completed.', 409);
        return;
      }

      $ok = $this->sessionManager->update($sessionId, [
        'status'  => 'completed',
        'payment' => $input['payment'],
      ]);

      if (!$ok) {
        $this->message->sendError('Failed to complete session', 500);
        return;
      }

      $session = $this->sessionManager->get($sessionId);
      $this->message->sendSuccess(['checkout_session' => $session]);
    } catch (\Exception $e) {
      error_log('[AnthropicEcommerce][Sessions] completeSession: ' . $e->getMessage());
      $this->message->sendError('Failed to complete session: ' . $e->getMessage(), 500);
    }
  }

  /**
   * Cancel an open session.
   * GET params: session_id
   */
  private function cancelSession(): void
  {
    $sessionId = HTML::sanitize($_GET['session_id'] ?? $_POST['session_id'] ?? '');
    if (empty($sessionId)) {
      $this->message->sendError('Missing session_id', 400);
      return;
    }

    try {
      $existing = $this->sessionManager->get($sessionId);
      if ($existing === null) {
        $this->message->sendError('Session not found: ' . $sessionId, 404);
        return;
      }

      $ok = $this->sessionManager->update($sessionId, ['status' => 'cancelled']);
      if (!$ok) {
        $this->message->sendError('Failed to cancel session', 500);
        return;
      }

      $this->message->sendSuccess([
        'cancelled'  => true,
        'session_id' => $sessionId,
      ]);
    } catch (\Exception $e) {
      error_log('[AnthropicEcommerce][Sessions] cancelSession: ' . $e->getMessage());
      $this->message->sendError('Failed to cancel session: ' . $e->getMessage(), 500);
    }
  }

  /**
   * List all active (non-expired) sessions from the session directory.
   */
  private function listSessions(): void
  {
    try {
      $dirSession = \ClicShopping\OM\CLICSHOPPING::BASE_DIR . 'Work/Session/Shop/UCP';
      $sessions   = [];

      if (is_dir($dirSession)) {
        foreach (glob($dirSession . '/cs_*.json') as $file) {
          $payload = json_decode(file_get_contents($file), true);
          if (is_array($payload) && isset($payload['checkout_session'])) {
            $s = $payload['checkout_session'];
            if (!empty($s['expires_at']) && strtotime($s['expires_at']) < time()) {
              continue;
            }
            $sessions[] = [
              'id'         => $s['id'] ?? basename($file, '.json'),
              'status'     => $s['status'] ?? 'unknown',
              'created_at' => $s['created_at'] ?? null,
              'expires_at' => $s['expires_at'] ?? null,
              'buyer'      => $s['buyer'] ?? [],
            ];
          }
        }
      }

      $this->message->sendSuccess([
        'sessions' => $sessions,
        'count'    => count($sessions),
      ]);
    } catch (\Exception $e) {
      error_log('[AnthropicEcommerce][Sessions] listSessions: ' . $e->getMessage());
      $this->message->sendError('Failed to list sessions: ' . $e->getMessage(), 500);
    }
  }

  // =========================================================================
  // Private helpers
  // =========================================================================

  /**
   * Validate minimum fields required to create a session.
   */
  private function validateSessionInput(array $input): array
  {
    $errors = [];

    if (empty($input['items'])) {
      $errors[] = ['code' => 'missing', 'field' => '$.items', 'message' => 'items is required.'];
    }

    if (empty($input['buyer']['email'])) {
      $errors[] = ['code' => 'missing', 'field' => '$.buyer.email', 'message' => 'buyer.email is required.'];
    }

    return $errors;
  }

  /**
   * Hydrate line items from product IDs via DB lookup.
   *
   * Input:  [['product_id' => '5', 'quantity' => 2], ...]
   * Output: enriched array with name, price, sku, etc.
   */
  private function buildLineItems(array $items): array
  {
    $lineItems  = [];
    $languageId = Registry::get('Language')->getId();

    foreach ($items as $item) {
      $productId = (int)($item['product_id'] ?? $item['id'] ?? 0);
      $quantity  = max(1, (int)($item['quantity'] ?? 1));

      if ($productId <= 0) {
        continue;
      }

      try {
        $Qproduct = $this->db->prepare('
          SELECT p.products_id,
                 pd.products_name,
                 p.products_price,
                 p.products_quantity AS stock,
                 p.products_ean,
                 p.products_sku,
                 p.products_model,
                 p.products_image,
                 p.products_weight
            FROM :table_products p
       LEFT JOIN :table_products_description pd
              ON pd.products_id = p.products_id
             AND pd.language_id = :language_id
           WHERE p.products_id     = :products_id
             AND p.products_status = 1
           LIMIT 1
        ');
        $Qproduct->bindInt(':language_id', $languageId);
        $Qproduct->bindInt(':products_id', $productId);
        $Qproduct->execute();

        if (!$Qproduct->fetch()) {
          continue;
        }

        $unitPrice = (float)$Qproduct->value('products_price');

        $lineItems[] = [
          'product_id'  => $productId,
          'name'        => $Qproduct->value('products_name'),
          'quantity'    => $quantity,
          'unit_price'  => $unitPrice,
          'total_price' => round($unitPrice * $quantity, 2),
          'ean'         => $Qproduct->value('products_ean'),
          'sku'         => $Qproduct->value('products_sku'),
          'model'       => $Qproduct->value('products_model'),
          'image'       => $Qproduct->value('products_image'),
          'weight'      => (float)$Qproduct->value('products_weight'),
        ];
      } catch (\Exception $e) {
        error_log('[AnthropicEcommerce][Sessions] buildLineItems product ' . $productId . ': ' . $e->getMessage());
      }
    }

    return $lineItems;
  }

  /**
   * Read and decode the JSON request body.
   * Returns null and sends an error on failure.
   */
  private function readJsonBody(): ?array
  {
    $raw = file_get_contents('php://input');

    if (empty($raw)) {
      $this->message->sendError('Empty request body', 400);
      return null;
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
      $this->message->sendError('Invalid JSON body', 400);
      return null;
    }

    return $data;
  }
}
