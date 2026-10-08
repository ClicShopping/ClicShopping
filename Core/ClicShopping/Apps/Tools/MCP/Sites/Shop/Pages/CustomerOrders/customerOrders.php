<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/*
List orders:    index.php?mcp&customerOrders&action=list_orders&customer_id={CID}&limit=10&offset=0
Read one:       index.php?mcp&customerOrders&action=read_order&order_id={OID}&customer_id={CID}
Cancel (POST):  index.php?mcp&customerOrders&action=cancel_order   body: order_id, customer_id
Message (POST): index.php?mcp&customerOrders&action=send_message  body: order_id, customer_id, message
History:        index.php?mcp&customerOrders&action=history&order_id={OID}&customer_id={CID}

Auth: Basic header or X-MCP-USER + X-MCP-KEY headers, then re-use returned X-MCP-TOKEN.
*/

namespace ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\CustomerOrders;

use ClicShopping\Apps\Tools\MCP\Classes\Shop\EndPoint\CustomerOrdersPermissions;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\EndPoint\OrdersShop;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Authentification;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpSecurity;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Message;
use ClicShopping\Apps\Tools\MCP\MCP;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * MCP customer-orders endpoint.
 *
 * Authentication identical to the other MCP Pages (AnthropicEcommerce, ChatRagBI,
 * CustomersProducts): credentials (username + key) or X-MCP-TOKEN, then McpPermissions
 * gates each action against the customer_orders context.
 */
class customerOrders extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $db;
  public mixed $app;
  public mixed $message;

  protected bool    $use_site_template = false;
  protected ?string $file              = null;

  protected OrdersShop                $orders;
  private CustomerOrdersPermissions   $permissions;

  private string  $authenticatedUsername   = '';
  private ?string $authenticatedSessionId  = null;
  private ?string $resolvedUsernameFromKey = null;

  private ?int $getOrderId  = null;
  private ?int $postOrderId = null;

  /**
   * Bootstrap: services, headers, auth, permission gate, dispatch.
   */
  protected function init(): void
  {
    $this->db = Registry::get('Db');

    // ----- HTTP headers -----
    header('Content-Type: application/json');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Key, X-Session-Token, X-MCP-USER, X-MCP-KEY, X-MCP-TOKEN');
    header('Access-Control-Allow-Credentials: true');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');

    // ----- Registry bootstrapping -----
    if (!Registry::exists('MCP')) {
      Registry::set('MCP', new MCP());
    }
    $this->app = Registry::get('MCP');

    if (!Registry::exists('Message')) {
      Registry::set('Message', new Message());
    }
    $this->message = Registry::get('Message');

    if (!Registry::exists('OrdersShop')) {
      Registry::set('OrdersShop', new OrdersShop());
    }
    $this->orders = Registry::get('OrdersShop');

    // Endpoint-owned permission manager (its own action whitelist + table whitelist).
    $this->permissions = new CustomerOrdersPermissions();

    // ----- Application status check -----
    if (!\defined('CLICSHOPPING_APP_MCP_MC_STATUS') || CLICSHOPPING_APP_MCP_MC_STATUS === 'False') {
      $this->message->sendError('API is disabled', 503);
      return;
    }

    // ----- CORS preflight -----
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
      http_response_code(200);
      exit;
    }

    // =========================================================================
    // AUTHENTICATION
    // =========================================================================

    $username     = $_GET['user_name'] ?? $_POST['user_name'] ?? $_SERVER['HTTP_X_MCP_USER']  ?? $_SERVER['HTTP_MCP_USER']  ?? null;
    $key          = $_GET['key']       ?? $_POST['key']       ?? $_SERVER['HTTP_X_MCP_KEY']   ?? $_SERVER['HTTP_MCP_KEY']   ?? null;
    $mcpSessionId = $_GET['token']     ?? $_POST['token']     ?? $_SERVER['HTTP_X_MCP_TOKEN'] ?? $_SERVER['HTTP_MCP_TOKEN'] ?? null;

    $this->authenticatedSessionId = $mcpSessionId;

    // Decode Authorization: Basic header (when Apache passes it through).
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (empty($username) && empty($key) && !empty($authHeader)) {
      if (preg_match('/Basic\s+(.*)/i', $authHeader, $matches)) {
        $decoded = base64_decode(trim($matches[1]), true);
        if ($decoded !== false && str_contains($decoded, ':')) {
          [$username, $key] = explode(':', $decoded, 2);
        } else {
          $key = trim($matches[1]);
        }
      }
    }

    // Fallback: resolve username from a raw API key when only the key was given.
    if (empty($username) && !empty($key)) {
      $this->resolvedUsernameFromKey = $this->findUsernameByKey($key);
      if (!empty($this->resolvedUsernameFromKey)) {
        $username = $this->resolvedUsernameFromKey;
      }
    }

    if (empty($mcpSessionId)) {
      if (empty($username) || empty($key)) {
        $this->message->sendError('Unauthorized: Missing session token or credentials.', 401);
        return;
      }
      try {
        $auth                         = new Authentification($username, $key);
        $mcpSessionId                 = $auth->authenticateAndCreateSession();
        $this->authenticatedUsername  = $username;
        $this->authenticatedSessionId = $mcpSessionId;
      } catch (\Exception $e) {
        McpSecurity::logSecurityEvent('customerOrders - Authentication Failed', [
          'username' => $username,
          'error'    => $e->getMessage(),
        ]);
        $this->message->sendError('Unauthorized: ' . $e->getMessage(), 401);
        return;
      }
    } else {
      try {
        $validSessionId              = McpSecurity::checkToken($mcpSessionId);
        $this->authenticatedUsername = McpSecurity::getUsernameFromSession($validSessionId);
        $this->authenticatedSessionId = $validSessionId;
        if (empty($this->authenticatedUsername)) {
          throw new \Exception('Session token is valid but associated username could not be found.');
        }
      } catch (\Exception $e) {
        McpSecurity::logSecurityEvent('customerOrders - Invalid Session Token', [
          'session_id' => $mcpSessionId,
          'error'      => $e->getMessage(),
        ]);
        $this->message->sendError('Unauthorized: Invalid or expired session token. ' . $e->getMessage(), 401);
        return;
      }
    }

    // Expose the current session token so clients can reuse it instead of
    // re-authenticating on every call (covers silent renewal too).
    McpSecurity::emitSessionHeaders($this->authenticatedSessionId);

    // =========================================================================
    // ACTION ROUTING + PERMISSION CHECK
    // =========================================================================

    $action = HTML::sanitize($_POST['action'] ?? $_GET['action'] ?? '');

    if ($action === '') {
      $this->message->sendError('Missing action.', 400);
      return;
    }

    if (!$this->permissions->canPerformAction($this->authenticatedUsername, $action)) {
      McpSecurity::logSecurityEvent('customerOrders - Permission Denied', [
        'username' => $this->authenticatedUsername,
        'action'   => $action,
      ]);
      $this->message->sendError(
        'Forbidden: User "' . $this->authenticatedUsername . '" does not have permission for action "' . $action . '".',
        403
      );
      return;
    }

    // =========================================================================
    // DISPATCH
    // =========================================================================

    $this->getOrderId  = isset($_GET['order_id'])  ? (int)HTML::sanitize($_GET['order_id'])  : null;
    $this->postOrderId = isset($_POST['order_id']) ? (int)HTML::sanitize($_POST['order_id']) : null;

    try {
      match ($action) {
        'list_orders'  => $this->handleListOrders(),
        'read_order'   => $this->handleReadOrder(),
        'cancel_order' => $this->handleCancelOrder(),
        'send_message' => $this->handleSendMessage(),
        'history'      => $this->handleGetHistory(),
        default        => $this->message->sendError('Invalid action: "' . $action . '"', 400),
      };
    } catch (\Exception $e) {
      error_log('[customerOrders] Dispatch error: ' . $e->getMessage());
      $this->message->sendError('Internal error: ' . $e->getMessage(), 500);
    }
  }

  // =========================================================================
  // Handlers
  // =========================================================================

  private function handleListOrders(): void
  {
    $this->orders->listOrders();
  }

  private function handleReadOrder(): void
  {
    $orderId = filter_var($this->getOrderId ?? $this->postOrderId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($orderId === false) {
      $this->message->sendError('A valid order ID is required.', 400);
      return;
    }
    $this->orders->readOrder(['order_id' => $orderId]);
  }

  private function handleCancelOrder(): void
  {
    $orderId = filter_var($this->postOrderId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($orderId === false) {
      $this->message->sendError('A valid order ID is required.', 400);
      return;
    }
    $this->orders->cancelOrder($orderId);
  }

  private function handleSendMessage(): void
  {
    $orderId        = filter_var($this->postOrderId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $messageContent = HTML::sanitize($_POST['message'] ?? '');

    if ($orderId === false) {
      $this->message->sendError('A valid order ID is required.', 400);
      return;
    }
    if ($messageContent === '') {
      $this->message->sendError('Message content cannot be empty.', 400);
      return;
    }

    $this->orders->sendMessageToAdmin($orderId, $messageContent);
  }

  private function handleGetHistory(): void
  {
    $orderId = filter_var($this->getOrderId ?? $this->postOrderId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($orderId === false) {
      $this->message->sendError('A valid order ID is required.', 400);
      return;
    }
    $this->orders->getOrderHistory($orderId);
  }

  // =========================================================================
  // Helpers
  // =========================================================================

  /**
   * Resolve a MCP username from a raw API key (fallback when no user_name given).
   */
  private function findUsernameByKey(string $key): ?string
  {
    try {
      $Quser = $this->db->prepare('SELECT username
                                     FROM :table_mcp
                                    WHERE mcp_key = :mcp_key
                                      AND status = 1
                                    LIMIT 1');
      $Quser->bindValue(':mcp_key', $key);
      $Quser->execute();
      if ($Quser->fetch()) {
        return (string)$Quser->value('username');
      }
    } catch (\Exception $e) {
      McpSecurity::logSecurityEvent('customerOrders - Failed to resolve username from key', [
        'error' => $e->getMessage(),
      ]);
    }
    return null;
  }
}
