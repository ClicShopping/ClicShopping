<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\AnthropicEcommerce\Sub;

use ClicShopping\Apps\Tools\MCP\Classes\Shop\EndPoint\OrdersShop;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Message;
use ClicShopping\OM\HTML;

/**
 * Orders sub-handler for the AnthropicEcommerce MCP endpoint.
 *
 * Delegates all order logic to the shared EndPoint\OrdersShop class,
 * which handles DB queries, customer ownership checks, order history
 * and customer messaging.
 *
 * Supported actions:
 *   - orders          GET  Paginated list of orders for the authenticated customer
 *   - order           GET  Single order detail + products by ?id=
 *   - order_cancel    POST Cancel an order by ?id=
 *   - order_history   GET  Status history for an order by ?id=
 *   - order_message   POST Send a message to admin for an order by ?id= + body {message}
 */
class Orders
{
  private Message    $message;
  private OrdersShop $endpoint;

  public function __construct(mixed $db, Message $message)
  {
    $this->message  = $message;
    $this->endpoint = new OrdersShop();
  }

  // =========================================================================
  // Dispatcher
  // =========================================================================

  public function dispatch(string $action): void
  {
    match ($action) {
      'orders'        => $this->endpoint->listOrders(),
      'order'         => $this->readOrder(),
      'order_cancel'  => $this->cancelOrder(),
      'order_history' => $this->orderHistory(),
      'order_message' => $this->sendMessage(),
      default         => $this->message->sendError('Unknown order action: ' . $action, 400),
    };
  }

  // =========================================================================
  // Wrappers (extract params then delegate)
  // =========================================================================

  private function readOrder(): void
  {
    $orderId = (int)HTML::sanitize($_GET['id'] ?? 0);
    if ($orderId <= 0) {
      $this->message->sendError('Missing or invalid order id', 400);
      return;
    }
    $this->endpoint->readOrder(['order_id' => $orderId]);
  }

  private function cancelOrder(): void
  {
    $orderId = (int)HTML::sanitize($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($orderId <= 0) {
      $this->message->sendError('Missing or invalid order id', 400);
      return;
    }
    $this->endpoint->cancelOrder($orderId);
  }

  private function orderHistory(): void
  {
    $orderId = (int)HTML::sanitize($_GET['id'] ?? 0);
    if ($orderId <= 0) {
      $this->message->sendError('Missing or invalid order id', 400);
      return;
    }
    $this->endpoint->getOrderHistory($orderId);
  }

  private function sendMessage(): void
  {
    $orderId = (int)HTML::sanitize($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($orderId <= 0) {
      $this->message->sendError('Missing or invalid order id', 400);
      return;
    }

    $raw = file_get_contents('php://input');
    $input = !empty($raw) ? json_decode($raw, true) : [];
    $msg = HTML::sanitize($input['message'] ?? $_POST['message'] ?? '');

    if (empty($msg)) {
      $this->message->sendError('Missing message body', 400);
      return;
    }

    $this->endpoint->sendMessageToAdmin($orderId, $msg);
  }
}
