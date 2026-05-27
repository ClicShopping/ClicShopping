<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\Shop\EndPoint;

use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpPermissions;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpSecurity;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Permission manager for the CustomersProducts MCP endpoint.
 *
 * Self-contained: the endpoint owns its action whitelist and table whitelist.
 * Delegates the actual permission evaluation to McpPermissions::evaluateEndpointAction(),
 * so the Core class never has to be edited when a new endpoint is added.
 *
 * Mirror of CustomerOrdersPermissions / AnthropicEcommercePermissions.
 */
class CustomersProductsPermissions
{
  private McpPermissions $mcpPermissions;
  private string         $prefix;

  /**
   * Read-only actions (require select_data only).
   * Matches the dispatch table in CustomersProducts.php.
   */
  private const READ_ACTIONS = [
    'products',
    'product',
    'categories',
    'search',
    'stats',
    'recommendations',
  ];

  /**
   * Write actions are intentionally empty: the current endpoint dispatches
   * only read operations. Add entries here together with the corresponding
   * match() arms in CustomersProducts.php when write flows are introduced.
   *
   * @var array<string, string[]>
   */
  private const WRITE_ACTIONS = [];

  public function __construct()
  {
    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');

    if (!Registry::exists('McpPermissions')) {
      Registry::set('McpPermissions', new McpPermissions());
    }

    $this->mcpPermissions = Registry::get('McpPermissions');
  }

  // =========================================================================
  // Public API
  // =========================================================================

  public function canPerformAction(string $username, string $action): bool
  {
    return $this->mcpPermissions->evaluateEndpointAction(
      $username,
      $action,
      self::READ_ACTIONS,
      self::WRITE_ACTIONS
    );
  }

  /**
   * @return string[]
   */
  public function getAllowedActions(): array
  {
    return array_values(array_unique(array_merge(
      self::READ_ACTIONS,
      array_keys(self::WRITE_ACTIONS)
    )));
  }

  public function isTableAllowed(string $tableName): bool
  {
    $bare  = str_replace($this->prefix, '', strtolower($tableName));
    $clean = $this->prefix . $bare;

    if (in_array($clean, $this->getForbiddenTables(), true)) {
      McpSecurity::logSecurityEvent('CustomersProducts - forbidden table access attempt', [
        'table' => $clean,
      ]);
      return false;
    }

    return in_array($clean, $this->getAllowedTables(), true);
  }

  public function generateSecurityReport(string $username): array
  {
    return [
      'username'         => $username,
      'security_level'   => 'CUSTOMER_PRODUCTS_READ_ONLY',
      'allowed_actions'  => $this->getAllowedActions(),
      'read_actions'     => self::READ_ACTIONS,
      'write_actions'    => self::WRITE_ACTIONS,
      'allowed_tables'   => $this->getAllowedTables(),
      'forbidden_tables' => $this->getForbiddenTables(),
      'restrictions'     => [
        'table_whitelist_enforced'           => true,
        'forbidden_table_access_blocked'     => true,
        'write_requires_explicit_permission' => true,
        'delete_never_allowed'               => true,
      ],
    ];
  }

  /**
   * @return string[]
   */
  public function getAllowedTables(): array
  {
    return [
      $this->prefix . 'products',
      $this->prefix . 'products_description',
      $this->prefix . 'products_to_categories',
      $this->prefix . 'categories',
      $this->prefix . 'categories_description',
      $this->prefix . 'manufacturers',
      $this->prefix . 'specials',
      $this->prefix . 'products_attributes',
      $this->prefix . 'products_options',
      $this->prefix . 'products_options_values',
    ];
  }

  /**
   * @return string[]
   */
  private function getForbiddenTables(): array
  {
    return [
      $this->prefix . 'administrators',
      $this->prefix . 'mcp',
      $this->prefix . 'mcp_session',
      $this->prefix . 'mcp_failed_attempts',
      $this->prefix . 'mcp_rate_limit',
      $this->prefix . 'sessions',
      $this->prefix . 'configuration',
      $this->prefix . 'customers_info',
      $this->prefix . 'address_book',
      $this->prefix . 'orders',
    ];
  }
}
