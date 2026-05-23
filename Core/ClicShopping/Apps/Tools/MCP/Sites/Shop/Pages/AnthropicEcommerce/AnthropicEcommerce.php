<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\AnthropicEcommerce;

use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Authentification;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpSecurity;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\Message;
use ClicShopping\Apps\Tools\MCP\MCP;
use ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\AnthropicEcommerce\Sub\Customers;
use ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\AnthropicEcommerce\Sub\Orders;
use ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\AnthropicEcommerce\Sub\Products;
use ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\AnthropicEcommerce\Sub\Sessions;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class AnthropicEcommerce extends \ClicShopping\OM\Domains\PagesAbstract
{
  public mixed $db;
  public mixed $app;
  public mixed $message;

  protected bool    $use_site_template = false;
  protected ?string $file = null;

  private string  $authenticatedUsername   = '';
  private ?string $authenticatedSessionId  = null;
  private ?string $resolvedUsernameFromKey = null;

  private ?Products  $products  = null;
  private ?Sessions  $sessions  = null;
  private ?Orders    $orders    = null;
  private ?Customers $customers = null;

  private AnthropicEcommercePermissions $permissions;

  /**
   * Maps every exposed action to its sub-handler group.
   * 'admin' group is handled inline in init() — no sub-class needed.
   */
  private const ACTION_MAP = [
    // Products (read)
    'products'        => 'products',
    'product'         => 'products',
    'search'          => 'products',
    'categories'      => 'products',
    'recommendations' => 'products',
    'stats'           => 'products',
    // Sessions (read + write)
    'session_create'  => 'sessions',
    'session_get'     => 'sessions',
    'session_update'  => 'sessions',
    'session_complete'=> 'sessions',
    'session_cancel'  => 'sessions',
    'session_list'    => 'sessions',
    // Orders (read + write)
    'orders'          => 'orders',
    'order'           => 'orders',
    'order_history'   => 'orders',
    'order_cancel'    => 'orders',
    'order_message'   => 'orders',
    // Customers (read + write)
    'customer'        => 'customers',
    'customer_create' => 'customers',
    'addresses'       => 'customers',
    'countries'       => 'customers',
    // Admin (read — inline handler)
    'security_report' => 'admin',
  ];

  // =========================================================================
  // Bootstrap
  // =========================================================================

  /**
   * Initializes the MCP API controller.
   *
   * Responsibilities:
   * - Bootstraps database and registry services
   * - Configures HTTP security headers
   * - Initializes core application services
   * - Validates system availability
   * - Handles CORS preflight requests
   * - Performs authentication (session token or credentials)
   * - Resolves username from API key if needed
   * - Validates session integrity
   * - Routes requests to correct domain handler
   * - Enforces authorization checks before dispatch
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

    // Permissions manager (uses isTableAllowed + generateSecurityReport)
    $this->permissions = new AnthropicEcommercePermissions();

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

    // Decode Authorization: Basic header (when Apache passes it through)
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

    // Fallback: resolve username from raw key
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
        McpSecurity::logSecurityEvent('AnthropicEcommerce - Authentication Failed', [
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
        if (empty($this->authenticatedUsername)) {
          throw new \Exception('Session token is valid but associated username could not be found.');
        }
      } catch (\Exception $e) {
        McpSecurity::logSecurityEvent('AnthropicEcommerce - Invalid Session Token', [
          'session_id' => $mcpSessionId,
          'error'      => $e->getMessage(),
        ]);
        $this->message->sendError('Unauthorized: Invalid or expired session token. ' . $e->getMessage(), 401);
        return;
      }
    }

    // =========================================================================
    // ACTION ROUTING + PERMISSION CHECK
    // =========================================================================

    $action = HTML::sanitize($_GET['action'] ?? $_POST['action'] ?? 'products');

    if (!array_key_exists($action, self::ACTION_MAP)) {
      $this->message->sendError('Invalid action: "' . $action . '"', 400);
      return;
    }

    // Permission check via AnthropicEcommercePermissions
    // Uses == 1 comparison internally (handles string/int DB values)
    if (!$this->permissions->canPerformAction($this->authenticatedUsername, $action)) {
      McpSecurity::logSecurityEvent('AnthropicEcommerce - Permission Denied', [
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

    try {
      $group = self::ACTION_MAP[$action];

      match ($group) {
        'products'  => $this->products()->dispatch($action),
        'sessions'  => $this->sessions()->dispatch($action),
        'orders'    => $this->orders()->dispatch($action),
        'customers' => $this->customers()->dispatch($action),
        'admin'     => $this->dispatchAdmin($action),
      };
    } catch (\Exception $e) {
      error_log('[AnthropicEcommerce] Routing error: ' . $e->getMessage());
      $this->message->sendError('Internal error: ' . $e->getMessage(), 500);
    }
  }

  // =========================================================================
  // Admin actions (inline — no sub-class)
  // =========================================================================

  /**
   * Dispatch admin-level actions handled directly in this class.
   * Currently: security_report
   *
   * isTableAllowed() and generateSecurityReport() are both exercised here,
   * ensuring they are actively used and not dead code.
   */
  private function dispatchAdmin(string $action): void
  {
    match ($action) {
      'security_report' => $this->handleSecurityReport(),
      default           => $this->message->sendError('Unknown admin action: ' . $action, 400),
    };
  }

  /**
   * Return the full security report for the authenticated user.
   * Also validates table access as a self-check example.
   *
   * curl -s "$BASE&action=security_report&$AUTH"
   */
  private function handleSecurityReport(): void
  {
    $report = $this->permissions->generateSecurityReport($this->authenticatedUsername);

    // Self-check: verify a sample table is accessible (exercises isTableAllowed)
    $report['table_check'] = [
      'products_allowed'       => $this->permissions->isTableAllowed('products'),
      'administrators_allowed' => $this->permissions->isTableAllowed('administrators'),
      'mcp_allowed'            => $this->permissions->isTableAllowed('mcp'),
    ];

    $this->message->sendSuccess($report);
  }

  // =========================================================================
  // Lazy sub-handler accessors
  // =========================================================================

  private function products(): Products
  {
    return $this->products ??= new Products($this->db, $this->message);
  }

  private function sessions(): Sessions
  {
    return $this->sessions ??= new Sessions($this->db, $this->message);
  }

  private function orders(): Orders
  {
    return $this->orders ??= new Orders($this->db, $this->message);
  }

  private function customers(): Customers
  {
    return $this->customers ??= new Customers($this->db, $this->message);
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
      McpSecurity::logSecurityEvent('AnthropicEcommerce - Failed to resolve username from key', [
        'error' => $e->getMessage(),
      ]);
    }
    return null;
  }
}
