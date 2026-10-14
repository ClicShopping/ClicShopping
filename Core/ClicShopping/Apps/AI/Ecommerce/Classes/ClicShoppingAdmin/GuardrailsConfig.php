<?php
/**
 * Ecommerce Domain Guardrails Configuration for Admin Context
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;


use ClicShopping\OM\CLICSHOPPING;

use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns\GuardrailsPattern;
use ClicShopping\OM\Domains\GuardrailsConfigAbstract;

/**
 * GuardrailsConfig - Admin Context Security Configuration
 *
 * Implements Layer 3 security (Domain Guardrails) for the Ecommerce domain in Admin context.
 * This configuration is more permissive than Shop context, allowing Admin users to:
 * - Perform SELECT, INSERT, and UPDATE operations
 * - Access up to 10,000 results
 * - Access most e-commerce tables (except highly sensitive system tables)
 *
 * Security Architecture Integration:
 * ==================================
 *
 * This class works as Layer 3 in the three-layer security architecture:
 *
 * Layer 1: DbSecurity (Core/ClicShopping/AI/Security/DbSecurity.php)
 * - SQL injection prevention via prepared statements
 * - Rate limiting (100 queries/minute)
 * - Query pattern whitelist/blacklist
 * - Automatic LIMIT clause injection for memory protection
 *
 * Layer 2: AI Security (Core/ClicShopping/AI/Security/)
 * - SecurityOrchestrator: Coordinates security checks
 * - SemanticSecurityAnalyzer: Detects semantic threats in queries
 * - ObfuscationPreprocessor: Detects obfuscation attempts
 * - LlmGuardrails: LLM-based security validation
 *
 * Layer 3: Domain Guardrails (This Class)
 * - Domain-specific forbidden tables/columns
 * - Context-specific permissions (Admin vs Shop)
 * - Domain-specific operation restrictions
 * - Domain-specific result limits
 *
 * Admin Context Characteristics:
 * ==============================
 *
 * Permissions:
 * - Allowed Operations: SELECT, INSERT, UPDATE (no DELETE for safety)
 * - Max Results: 10,000 (higher limit for admin analysis)
 * - Context: 'admin'
 *
 * Forbidden Tables (extends common list):
 * - Common: administrators, sessions, api_keys
 * - Admin-specific: admin_sessions, admin_tokens, mcp, mcp_session
 *
 * Forbidden Columns (extends common list):
 * - Common: password, secret_key, token
 * - Admin-specific: admin_token, admin_secret, mcp_key
 *
 * @see \ClicShopping\OM\Domains\GuardrailsConfigAbstract Base class with common security rules
 * @see \ClicShopping\AI\Security\DbSecurity Layer 1 - Database Security
 * @see \ClicShopping\AI\Security\SecurityOrchestrator Layer 2 - AI Security Orchestration
 * @see \ClicShopping\Apps\AI\Ecommerce\Ecommerce Domain app that uses this configuration
 */


class GuardrailsConfig extends GuardrailsConfigAbstract
{
  /**
   * Get the complete security guardrails configuration for Admin context
   *
   * Returns an associative array with domain-specific security rules for the Admin context.
   * This configuration extends the common security rules from the base class with
   * Admin-specific restrictions and permissions.
   *
   * Configuration Structure:
   * - forbidden_tables: Array of table names that cannot be accessed
   * - forbidden_columns: Array of column names that cannot be included in results
   * - allowed_operations: Array of SQL operations allowed (SELECT, INSERT, UPDATE)
   * - max_results: Maximum number of results that can be returned (10,000)
   * - context: Context identifier ('admin')
   *
   * @return array Associative array of security guardrails configuration
   */
  public static function getConfig(): array
  {
    return [
      // Forbidden Tables: Common system tables + Admin-specific sensitive tables
      'forbidden_tables' => array_merge(
        self::getCommonForbiddenTables(), // administrators, sessions, api_keys
        [
          // Admin-specific forbidden tables
          'admin_sessions',      // Admin session data
          'admin_tokens',        // Admin authentication tokens
          'mcp',                 // MCP server configuration
          'mcp_session',         // MCP session data
          'configuration',       // System configuration (sensitive settings)
          'security_events',     // Security audit logs (should not be modified via AI)
          'rag_security_events'  // RAG security audit logs
        ]
      ),

      // Forbidden Columns: Common sensitive columns + Admin-specific sensitive columns
      'forbidden_columns' => array_merge(
        self::getCommonForbiddenColumns(), // password, secret_key, token
        [
          // Admin-specific forbidden columns
          'admin_token',         // Admin authentication tokens
          'admin_secret',        // Admin secret keys
          'mcp_key',             // MCP authentication keys
          'api_secret',          // API secret keys
          'encryption_key',      // Encryption keys
          'salt',                // Password salts
          'reset_token'          // Password reset tokens
        ]
      ),

      // Allowed Operations: Admin can SELECT, INSERT, and UPDATE (no DELETE for safety)
      // DELETE operations are intentionally excluded to prevent accidental data loss
      // Destructive operations (DROP, TRUNCATE, ALTER) are blocked by Layer 1 (DbSecurity)
      'allowed_operations' => [
        'SELECT',  // Read data
        'INSERT',  // Create new records
        'UPDATE'   // Modify existing records
        // DELETE intentionally excluded for safety
      ],

      // Max Results: Admin can retrieve up to 10,000 results for comprehensive analysis
      // This is higher than Shop context (100) but still prevents memory exhaustion
      // Layer 1 (DbSecurity) also enforces a safety limit by adding LIMIT clauses
      'max_results' => 10000,

      // Context: Identifies this configuration as Admin context
      // Used by the domain app to select the appropriate guardrails configuration
      'context' => 'admin'
    ];
  }

  /**
   * Get allowed e-commerce tables for Admin context (DYNAMIC)
   *
   * Dynamically retrieves all tables from the database and filters them based on:
   * 1. Forbidden tables blacklist (security)
   * 2. Table prefix matching (e-commerce tables only)
   * 3. Table name patterns (RAG, products, orders, customers, etc.)
   *
   * This approach is more flexible than a static whitelist because:
   * - Automatically discovers new tables added to the system
   * - No need to manually update the list when adding new features
   * - Adapts to different database configurations
   * - Respects the forbidden tables security rules
   *
   * Table Categories Discovered:
   * - Products: products, products_description, products_attributes, etc.
   * - Orders: orders, orders_products, orders_status, etc.
   * - Customers: customers (basic info only, not customers_info)
   * - Categories: categories, categories_description
   * - Manufacturers: manufacturers, manufacturers_info
   * - Reviews: reviews, reviews_description
   * - Analytics: rag_statistics, rag_query_cache, rag_chat_interactions
   * - Web Search: rag_web_search_cache, rag_web_search_results
   *
   * Note: This method queries the database using DoctrineOrm, results are cached for performance.
   *
   * @param bool $useCache Whether to use cached results (default: true)
   * @return array Array of allowed table names (without prefix)
   */
  public static function getAllowedTables(bool $useCache = true): array
  {
    static $cachedTables = null;

    // Return cached results if available and caching is enabled
    if ($useCache && $cachedTables !== null) {
      return $cachedTables;
    }

    try {
      // Use DoctrineOrm::getRelevantTables() - already implemented in AI infrastructure
      // This method queries INFORMATION_SCHEMA.TABLES and filters system tables
      $allTables = DoctrineOrm::getRelevantTables($useCache);
      
      $config = self::getConfig();
      $forbiddenTables = $config['forbidden_tables'];
      
      // Get database configuration for prefix handling
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');

      // Filter tables based on security rules and relevance
      $allowedTables = [];

      foreach ($allTables as $tableName) {
        // Remove table prefix for comparison
        $tableNameWithoutPrefix = str_replace($prefix, '', $tableName);

        // Skip if table is in forbidden list (without prefix)
        if (\in_array($tableNameWithoutPrefix, $forbiddenTables, true)) {
          continue;
        }

        // Skip if table is in forbidden list (with prefix)
        if (\in_array($tableName, $forbiddenTables, true)) {
          continue;
        }

        // Include tables that match e-commerce patterns
        if (self::isEcommerceTable($tableNameWithoutPrefix)) {
          $allowedTables[] = $tableNameWithoutPrefix;
        }
      }

      // Cache results
      $cachedTables = $allowedTables;

      return $allowedTables;

    } catch (\Exception $e) {
      // Fallback to empty array if database query fails
      // Log error for debugging
      if (defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True') {
        error_log('GuardrailsConfig::getAllowedTables() error: ' . $e->getMessage());
      }

      return [];
    }
  }

  /**
   * Check if a table is an e-commerce table based on naming patterns
   *
   * Delegates to GuardrailsPattern class for pattern matching.
   * This helps filter out system tables, temporary tables, and other non-e-commerce tables.
   *
   * E-commerce Table Patterns (defined in GuardrailsPattern):
   * - Products: products*, specials
   * - Orders: orders*
   * - Customers: customers (but NOT customers_info - sensitive)
   * - Categories: categories*
   * - Manufacturers: manufacturers*
   * - Reviews: reviews*
   * - Analytics: rag_*, web_search*
   * - Attributes: *_attributes, *_options, *_values
   * - Descriptions: *_description
   *
   * @param string $tableName Table name without prefix
   * @return bool True if table is an e-commerce table, false otherwise
   * @see \ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns\GuardrailsPattern
   */
  private static function isEcommerceTable(string $tableName): bool
  {
    return GuardrailsPattern::matches($tableName);
  }

  /**
   * Validate if a table is accessible in Admin context
   *
   * Checks if a given table name is accessible based on the guardrails configuration.
   * A table is accessible if:
   * 1. It is NOT in the forbidden tables list
   * 2. It IS in the allowed tables list (optional whitelist check)
   *
   * @param string $tableName The table name to validate (without prefix)
   * @param bool $useWhitelist Whether to enforce whitelist validation (default: false)
   * @return bool True if table is accessible, false otherwise
   */
  public static function isTableAccessible(string $tableName, bool $useWhitelist = false): bool
  {
    $config = self::getConfig();

    // Check if table is forbidden
    if (\in_array($tableName, $config['forbidden_tables'], true)) {
      return false;
    }

    // Optional: Check if table is in whitelist
    if ($useWhitelist) {
      $allowedTables = self::getAllowedTables();
      return \in_array($tableName, $allowedTables, true);
    }

    return true;
  }

  /**
   * Validate if a column is accessible in Admin context
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
   * Validate if an operation is allowed in Admin context
   *
   * Checks if a given SQL operation is allowed based on the guardrails configuration.
   * Allowed operations for Admin: SELECT, INSERT, UPDATE
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
   * Get the maximum number of results allowed in Admin context
   *
   * Returns the maximum number of results that can be returned in a single query.
   * For Admin context, this is 10,000 results.
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
   * For this class, it always returns 'admin'.
   *
   * @return string Context identifier ('admin')
   */
  public static function getContext(): string
  {
    $config = self::getConfig();
    return $config['context'];
  }

  /**
   * Get validation patterns for realistic metrics
   *
   * Returns validation rules for e-commerce metrics to detect unrealistic values.
   * Used by LlmGuardrails to validate AI-generated responses.
   *
   * Validation Rules:
   * - max_growth_percentage: Maximum realistic growth percentage (500%)
   * - max_percentage: Maximum percentage value (1000%)
   * - min_percentage: Minimum percentage value (0%)
   *
   * @return array Associative array of validation patterns
   */
  public static function getValidationPatterns(): array
  {
    return [
      'max_growth_percentage' => 500,  // Maximum realistic growth (500%)
      'max_percentage' => 1000,        // Maximum percentage value
      'min_percentage' => 0,           // Minimum percentage value
    ];
  }

  /**
   * Get business validation rules for e-commerce content
   *
   * Returns business logic validation rules specific to e-commerce domain.
   * Used by LlmGuardrails to validate business content in AI responses.
   *
   * Business Rules:
   * - validate_percentages: Whether to validate percentage ranges
   * - validate_metrics: Whether to validate metric realism
   *
   * @return array Associative array of business validation rules
   */
  public static function getBusinessRules(): array
  {
    return [
      'validate_percentages' => true,
      'validate_metrics' => true,
    ];
  }
}
