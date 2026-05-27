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
 * Permission manager for the customerOrders MCP endpoint.
 *
 * Self-contained: the endpoint owns its action whitelist and table whitelist.
 * Delegates the actual permission evaluation to McpPermissions::evaluateEndpointAction(),
 * so the Core class never has to be edited when a new endpoint is added.
 *
 * Mirror of the AnthropicEcommercePermissions pattern.
 */
class CustomerOrdersPermissions
{
  private McpPermissions $mcpPermissions;
  private string         $prefix;

  /**
   * Read-only actions (require select_data only).
   */
  private const READ_ACTIONS = [
    'list_orders',
    'read_order',
    'history',
  ];

  /**
   * Write actions — map of action => required permission flag(s) (any-of).
   * select_data is always required in addition to one of the listed flags
   * (enforced by McpPermissions::evaluateEndpointAction).
   */
  private const WRITE_ACTIONS = [
    'cancel_order' => ['update_data'],
    'send_message' => ['create_data'],
  ];

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

  /**
   * Check whether a user is allowed to execute a given action on this endpoint.
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
   * Returns the union of all allowed actions for this endpoint.
   *
   * @return string[]
   */
  public function getAllowedActions(): array
  {
    return array_values(array_unique(array_merge(
      self::READ_ACTIONS,
      array_keys(self::WRITE_ACTIONS)
    )));
  }

  /**
   * Validate that a referenced table is on this endpoint's whitelist.
   * Called by EndPoint classes before any DB query.
   */
  public function isTableAllowed(string $tableName): bool
  {
    $bare  = str_replace($this->prefix, '', strtolower($tableName));
    $clean = $this->prefix . $bare;

    if (in_array($clean, $this->getForbiddenTables(), true)) {
      McpSecurity::logSecurityEvent('customerOrders - forbidden table access attempt', [
        'table' => $clean,
      ]);
      return false;
    }

    return in_array($clean, $this->getAllowedTables(), true);
  }

  /**
   * Full security report for the authenticated user, mirroring the
   * AnthropicEcommerce pattern. Exposed via a 'security_report' action
   * if the Page chooses to wire it.
   */
  public function generateSecurityReport(string $username): array
  {
    return [
      'username'         => $username,
      'security_level'   => 'CUSTOMER_ORDERS_READ_WRITE',
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
   * Allowed tables (prefix applied at runtime).
   *
   * @return string[]
   */
  public function getAllowedTables(): array
  {
    return [
      $this->prefix . 'orders',
      $this->prefix . 'orders_products',
      $this->prefix . 'orders_status',
      $this->prefix . 'orders_status_history',
      $this->prefix . 'orders_status_invoice',
      $this->prefix . 'customers',
    ];
  }

  /**
   * Tables that must never be accessed from this endpoint.
   *
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
    ];
  }
}
