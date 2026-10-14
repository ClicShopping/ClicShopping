<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Sites\Shop\Pages\AnthropicEcommerce;

use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpPermissions;
use ClicShopping\Apps\Tools\MCP\Classes\Shop\Security\McpSecurity;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * Permission manager specific to the AnthropicEcommerce MCP endpoint.
 *
 * Mirrors the pattern of RagBIPermissions: whitelist of allowed actions,
 * blacklist of forbidden tables, and a security report generator.
 *
 * Read-only actions require only select_data.
 * Write actions additionally require create_data or update_data.
 *
 * isTableAllowed() is called by sub-handlers before any DB query.
 * generateSecurityReport() is exposed via the 'security_report' action
 * in AnthropicEcommerce.php (authenticated users only).
 */
class AnthropicEcommercePermissions
{
  private mixed $db;
  private McpPermissions $mcpPermissions;
  private string $prefix;

  // Actions that only require read (select_data) permission
  private const READ_ACTIONS = [
    'products',
    'product',
    'search',
    'categories',
    'recommendations',
    'stats',
    'orders',
    'order',
    'order_history',
    'session_get',
    'session_list',
    'customer',
    'addresses',
    'countries',
    'security_report',
  ];

  /**
   * Write actions — map of action => required permission flag(s) (any-of).
   * select_data is always required in addition, enforced by
   * McpPermissions::evaluateEndpointAction.
   *
   * @var array<string, string[]>
   */
  private const WRITE_ACTIONS = [
    'session_create'   => ['create_data', 'update_data'],
    'session_update'   => ['create_data', 'update_data'],
    'session_complete' => ['create_data', 'update_data'],
    'session_cancel'   => ['create_data', 'update_data'],
    'order_cancel'     => ['create_data', 'update_data'],
    'order_message'    => ['create_data', 'update_data'],
    'customer_create'  => ['create_data', 'update_data'],
  ];

  public function __construct()
  {
    $this->db     = Registry::get('Db');
    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');

    if (!Registry::exists('McpPermissions')) {
      Registry::set('McpPermissions', new McpPermissions());
    }

    $this->mcpPermissions = Registry::get('McpPermissions');
  }

  // =========================================================================
  // Public API
  // =========================================================================

  /**
   * Check whether a user is allowed to execute a given action.
   *
   * Rules:
   *   - Action must be in the whitelist
   *   - Read actions  : select_data == 1
   *   - Write actions : select_data == 1 AND (create_data == 1 OR update_data == 1)
   *
   * Uses == 1 (not strict cast) to handle both integer and string DB values
   * safely, consistent with the rest of ClicShopping.
   */
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
   * Validate that a table referenced by a sub-handler is on the whitelist.
   *
   * Called by Sessions::buildLineItems(), Products, Orders, Customers
   * before executing any DB query, to prevent accidental access to
   * sensitive tables.
   *
   * @param string $tableName  Raw table name (with or without prefix)
   * @return bool
   */
  public function isTableAllowed(string $tableName): bool
  {
    // Normalise: strip any existing prefix then re-apply to avoid double-prefix
    $bare  = str_replace($this->prefix, '', strtolower($tableName));
    $clean = $this->prefix . $bare;

    if (in_array($clean, $this->getForbiddenTables(), true)) {
      McpSecurity::logSecurityEvent('AnthropicEcommerce - forbidden table access attempt', [
        'table' => $clean,
      ]);
      return false;
    }

    return in_array($clean, $this->getAllowedTables(), true);
  }

  /**
   * Generate a full security report for the authenticated user.
   * Exposed via the 'security_report' action in AnthropicEcommerce.php.
   *
   * @param string $username
   * @return array
   */
  public function generateSecurityReport(string $username): array
  {
    $permissions = $this->mcpPermissions->getUserPermissions($username);

    return [
      'username'         => $username,
      'security_level'   => 'ECOMMERCE_READ_WRITE',
      'allowed_actions'  => $this->getAllowedActions(),
      'read_actions'     => self::READ_ACTIONS,
      'write_actions'    => self::WRITE_ACTIONS,
      'allowed_tables'   => $this->getAllowedTables(),
      'forbidden_tables' => $this->getForbiddenTables(),
      'permissions'      => [
        'select_data' => $permissions['select_data'] == 1,
        'create_data' => $permissions['create_data'] == 1,
        'update_data' => $permissions['update_data'] == 1,
        'delete_data' => $permissions['delete_data'] == 1,
        'create_db'   => $permissions['create_db']   == 1,
      ],
      'restrictions' => [
        'table_whitelist_enforced'           => true,
        'forbidden_table_access_blocked'     => true,
        'write_requires_explicit_permission' => true,
        'delete_never_allowed'               => true,
      ],
    ];
  }

  /**
   * Returns the full list of allowed actions.
   */
  public function getAllowedActions(): array
  {
    return array_values(array_unique(array_merge(
      self::READ_ACTIONS,
      array_keys(self::WRITE_ACTIONS)
    )));
  }

  /**
   * Returns the list of allowed DB tables (prefix applied at runtime).
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
      $this->prefix . 'orders',
      $this->prefix . 'orders_products',
      $this->prefix . 'orders_status',
      $this->prefix . 'orders_status_history',
      $this->prefix . 'customers',
      $this->prefix . 'address_book',
      $this->prefix . 'countries',
      $this->prefix . 'zones',
    ];
  }

  // =========================================================================
  // Private helpers
  // =========================================================================

  /**
   * Returns the list of tables that must never be accessed.
   */
  private function getForbiddenTables(): array
  {
    return [
      $this->prefix . 'administrators',
      $this->prefix . 'mcp',
      $this->prefix . 'mcp_session',
      $this->prefix . 'sessions',
      $this->prefix . 'configuration',
      $this->prefix . 'customers_info',
    ];
  }
}
