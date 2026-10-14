<?php
/**
 * Ecommerce Domain Guardrails Configuration for Shop Context
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop;


use ClicShopping\OM\Domains\GuardrailsConfigAbstract;
use ClicShopping\Apps\AI\Ecommerce\Classes\Shop\Patterns\GuardrailsPattern;

/**
 * GuardrailsConfig - Shop Context Security Configuration
 *
 * Implements Layer 3 security (Domain Guardrails) for the Ecommerce domain in Shop context.
 * This configuration is more restrictive than Admin context, allowing Shop users to:
 * - Perform SELECT operations only (no INSERT, UPDATE, DELETE)
 * - Access up to 100 results
 * - Access only public e-commerce tables (products, categories, reviews)
 *
 * Security Architecture (3 Layers):
 * 
 * Layer 1: Core Application Security (DbSecurity)
 * - Rate limiting, SQL validation, prepared statements, automatic LIMIT
 * - Location: Core/ClicShopping/AI/Security/DbSecurity.php
 * 
 * Layer 2: AI/LLM Security (SecurityOrchestrator + Components)
 * - Obfuscation detection, semantic threat analysis, response validation
 * - Location: Core/ClicShopping/AI/Security/
 * 
 * Layer 3: Domain Guardrails (This Class)
 * - Context-aware security rules (Admin vs Shop)
 * - Shop context is more restrictive than Admin context
 *
 * @see \ClicShopping\OM\Domains\GuardrailsConfigAbstract Base class with common security rules
 */

class GuardrailsConfig extends GuardrailsConfigAbstract
{
  /**
   * Get the complete security guardrails configuration for Shop context
   *
   * Returns an associative array with domain-specific security rules for the Shop context.
   * This configuration extends the common security rules from the base class with
   * Shop-specific restrictions (more restrictive than Admin).
   *
   * Shop Context Restrictions:
   * - SELECT only (no INSERT, UPDATE, DELETE)
   * - Max 100 results (vs 10,000 for Admin)
   * - No access to customer, order, or admin tables
   * - No access to sensitive columns (emails, passwords, tokens)
   *
   * @return array Associative array of security guardrails configuration
   */
  public static function getConfig(): array
  {
    return [
      // Forbidden Tables: All sensitive tables (shop users can only see public data)
      'forbidden_tables' => array_merge(
        self::getCommonForbiddenTables(),
        [
          // All customer-related tables (sensitive)
          'customers',
          'customers_info',
          'customers_basket',
          'customers_basket_attributes',
          
          // All order-related tables (sensitive)
          'orders',
          'orders_products',
          'orders_status',
          'orders_status_history',
          'orders_total',
          
          // All admin-related tables
          'administrators',
          'admin_sessions',
          'admin_tokens',
          
          // All system tables
          'mcp',
          'mcp_session',
          'configuration',
          'security_events',
          'rag_security_events',
          
          // All analytics tables
          'rag_statistics',
          'rag_query_cache',
          'rag_chat_interactions',
        ]
      ),

      // Forbidden Columns: All sensitive columns
      'forbidden_columns' => array_merge(
        self::getCommonForbiddenColumns(),
        [
          // Customer sensitive columns
          'customers_email_address',
          'customers_telephone',
          'customers_password',
          
          // Order sensitive columns
          'orders_total',
          'orders_status',
          
          // Admin sensitive columns
          'admin_token',
          'admin_secret',
          'mcp_key',
          'api_secret',
          'encryption_key',
          'salt',
          'reset_token'
        ]
      ),

      // Allowed Operations: Shop users can only READ (SELECT)
      // No INSERT, UPDATE, DELETE for safety
      'allowed_operations' => [
        'SELECT'  // Read-only access
      ],

      // Max Results: Shop users can retrieve up to 100 results
      // Lower limit than Admin (10,000) to prevent data extraction
      'max_results' => 100,

      // Context: Identifies this configuration as Shop context
      'context' => 'shop'
    ];
  }

  /**
   * Validate if a table is accessible in Shop context
   *
   * Checks if a given table name is accessible based on the guardrails configuration.
   * Uses GuardrailsPattern to validate against Shop-specific patterns.
   *
   * A table is accessible if:
   * 1. It is NOT in the forbidden tables list
   * 2. It MATCHES Shop-specific patterns (public data only)
   * 3. It is NOT explicitly excluded (sensitive data)
   *
   * @param string $tableName The table name to validate (without prefix)
   * @return bool True if table is accessible, false otherwise
   */
  public static function isTableAccessible(string $tableName): bool
  {
    $config = self::getConfig();

    // Check if table is forbidden
    if (\in_array($tableName, $config['forbidden_tables'], true)) {
      return false;
    }

    // Check if table is explicitly excluded (sensitive data)
    if (GuardrailsPattern::isExcluded($tableName)) {
      return false;
    }

    // Check if table matches Shop patterns (public data only)
    return GuardrailsPattern::matches($tableName);
  }

  /**
   * Validate if a column is accessible in Shop context
   *
   * Checks if a given column name is accessible based on the guardrails configuration.
   * A column is accessible if it is NOT in the forbidden columns list.
   *
   * @param string $columnName The column name to validate
   * @return bool True if column is accessible, false otherwise
   */
  public static function isColumnAccessible(string $columnName): bool
  {
    $config = self::getConfig();

    // Check if column is forbidden
    return !\in_array($columnName, $config['forbidden_columns'], true);
  }

  /**
   * Validate if an operation is allowed in Shop context
   *
   * Checks if a given SQL operation is allowed based on the guardrails configuration.
   * Allowed operations for Shop: SELECT ONLY (read-only)
   *
   * @param string $operation The SQL operation to validate (e.g., 'SELECT', 'INSERT', 'UPDATE')
   * @return bool True if operation is allowed, false otherwise
   */
  public static function isOperationAllowed(string $operation): bool
  {
    $config = self::getConfig();

    // Normalize operation to uppercase
    $operation = strtoupper(trim($operation));

    return \in_array($operation, $config['allowed_operations'], true);
  }

  /**
   * Get the maximum number of results allowed in Shop context
   *
   * Returns the maximum number of results that can be returned in a single query.
   * For Shop context, this is 100 results (lower than Admin for security).
   *
   * @return int Maximum number of results allowed
   */
  public static function getMaxResults(): int
  {
    $config = self::getConfig();
    return $config['max_results'];
  }

  /**
   * Get the context identifier
   *
   * Returns the context identifier for this guardrails configuration.
   * For this class, it always returns 'shop'.
   *
   * @return string Context identifier ('shop')
   */
  public static function getContext(): string
  {
    $config = self::getConfig();
    return $config['context'];
  }
}
