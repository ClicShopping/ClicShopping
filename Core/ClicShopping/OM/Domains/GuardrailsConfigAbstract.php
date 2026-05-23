<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Domains;

/**
 * Abstract base class for Domain-specific Security Guardrails Configuration
 * 
 * This class provides a framework-level abstraction for defining security guardrails
 * that are reusable across all domains (Ecommerce, HR, Finance, Trading, etc.).
 * 
 * Security Architecture - Three-Layer Defense:
 * ============================================
 * 
 * Layer 1: Database Security (DbSecurity)
 * - Located in: Core/ClicShopping/AI/Security/DbSecurity.php
 * - Purpose: Low-level SQL validation and execution security
 * - Functions:
 *   * Rate limiting (100 queries/minute default)
 *   * SQL injection prevention via prepared statements
 *   * Query pattern whitelist/blacklist (SELECT allowed, DROP forbidden)
 *   * Automatic LIMIT clause injection for memory protection
 *   * Query execution monitoring and logging
 * - Scope: Global, applies to ALL database operations
 * 
 * Layer 2: AI Security Components (SecurityOrchestrator, SemanticSecurityAnalyzer)
 * - Located in: Core/ClicShopping/AI/Security/
 * - Purpose: AI-powered threat detection and semantic analysis
 * - Components:
 *   * SecurityOrchestrator: Coordinates security checks across components
 *   * SemanticSecurityAnalyzer: Detects semantic threats in natural language queries
 *   * ObfuscationPreprocessor: Detects obfuscation attempts (base64, hex encoding)
 *   * LlmGuardrails: LLM-based security validation with confidence scoring
 * - Scope: AI/RAG-specific, analyzes user queries before processing
 * 
 * Layer 3: Domain Guardrails (This Class)
 * - Located in: Core/ClicShopping/OM/Domains/GuardrailsConfigAbstract.php
 * - Purpose: Domain and context-specific security rules
 * - Functions:
 *   * Domain-specific forbidden tables/columns (e.g., HR salary data)
 *   * Context-specific permissions (Admin vs Shop)
 *   * Domain-specific operation restrictions (read-only for Shop)
 *   * Domain-specific result limits (100 for Shop, 10000 for Admin)
 * - Scope: Domain-specific, enforced by domain apps
 * 
 * Integration Flow:
 * =================
 * 
 * User Query → Layer 2 (AI Security) → Layer 3 (Domain Guardrails) → Layer 1 (DbSecurity) → Database
 *              ↓                        ↓                              ↓
 *              Semantic Analysis        Domain Rules Check            SQL Validation
 *              Obfuscation Detection    Context Permissions           Rate Limiting
 *              LLM Guardrails           Entity Restrictions           Query Execution
 * 
 * Example Security Flow:
 * =====================
 * 
 * 1. User Query: "Show me all customer passwords"
 *    - Layer 2: SemanticSecurityAnalyzer detects sensitive data request → BLOCKED
 * 
 * 2. User Query: "List products" (Shop context)
 *    - Layer 2: Query passes semantic analysis
 *    - Layer 3: Domain Guardrails check:
 *      * Context: Shop (read-only)
 *      * Allowed operations: ['SELECT'] ✓
 *      * Max results: 100 ✓
 *      * Forbidden tables: None in query ✓
 *    - Layer 1: DbSecurity validates SQL syntax and executes
 * 
 * 3. User Query: "Update product prices" (Admin context)
 *    - Layer 2: Query passes semantic analysis
 *    - Layer 3: Domain Guardrails check:
 *      * Context: Admin (read-write)
 *      * Allowed operations: ['SELECT', 'INSERT', 'UPDATE'] ✓
 *      * Max results: 10000 ✓
 *    - Layer 1: DbSecurity validates and executes UPDATE
 * 
 * 4. User Query: "DROP TABLE administrators"
 *    - Layer 2: SemanticSecurityAnalyzer detects destructive operation → BLOCKED
 *    - Layer 3: Would block 'administrators' as forbidden table
 *    - Layer 1: Would block DROP operation via query blacklist
 * 
 * Implementation Pattern:
 * ======================
 * 
 * Child classes MUST extend this abstract class and implement getConfig() to provide
 * domain-specific security rules. Common security rules are provided by this base class
 * and can be extended or overridden by child implementations.
 * 
 * Example - Ecommerce Admin Guardrails:
 * ```php
 * namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;
 * 
 * use ClicShopping\OM\Domains\GuardrailsConfigAbstract;
 * 
 * class GuardrailsConfig extends GuardrailsConfigAbstract
 * {
 *     public static function getConfig(): array
 *     {
 *         return [
 *             'forbidden_tables' => array_merge(
 *                 self::getCommonForbiddenTables(),
 *                 ['admin_sessions', 'admin_tokens'] // Admin-specific additions
 *             ),
 *             'forbidden_columns' => array_merge(
 *                 self::getCommonForbiddenColumns(),
 *                 ['admin_token', 'admin_secret'] // Admin-specific additions
 *             ),
 *             'allowed_operations' => ['SELECT', 'INSERT', 'UPDATE'], // Admin can modify
 *             'max_results' => 10000, // Admin can see more results
 *             'context' => 'admin'
 *         ];
 *     }
 * }
 * ```
 * 
 * Example - Ecommerce Shop Guardrails (More Restrictive):
 * ```php
 * namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop;
 * 
 * use ClicShopping\OM\Domains\GuardrailsConfigAbstract;
 * 
 * class GuardrailsConfig extends GuardrailsConfigAbstract
 * {
 *     public static function getConfig(): array
 *     {
 *         return [
 *             'forbidden_tables' => array_merge(
 *                 self::getCommonForbiddenTables(),
 *                 ['customers_info', 'orders_total', 'admin_*'] // Shop restrictions
 *             ),
 *             'forbidden_columns' => array_merge(
 *                 self::getCommonForbiddenColumns(),
 *                 ['customer_email', 'customer_phone', 'credit_card'] // Shop restrictions
 *             ),
 *             'allowed_operations' => ['SELECT'], // Shop is read-only
 *             'max_results' => 100, // Shop is more restrictive
 *             'context' => 'shop'
 *         ];
 *     }
 * }
 * ```
 * 
 * Benefits of This Architecture:
 * ==============================
 * 
 * 1. Defense in Depth: Multiple security layers provide redundancy
 * 2. Separation of Concerns: Each layer handles specific security aspects
 * 3. Context Awareness: Domain guardrails adapt to Admin vs Shop contexts
 * 4. Reusability: Common rules defined once, extended by domains
 * 5. Flexibility: Domains can override or extend base security rules
 * 6. Maintainability: Security logic centralized and well-documented
 * 7. Auditability: Each layer logs security events for compliance
 * 
 * @see \ClicShopping\AI\Security\DbSecurity Layer 1 - Database Security
 * @see \ClicShopping\AI\Security\SecurityOrchestrator Layer 2 - AI Security Orchestration
 * @see \ClicShopping\AI\Security\SemanticSecurityAnalyzer Layer 2 - Semantic Threat Detection
 * @see \ClicShopping\AI\Security\ObfuscationPreprocessor Layer 2 - Obfuscation Detection
 * @see \ClicShopping\AI\Security\LlmGuardrails Layer 2 - LLM-based Security Validation
 * @see \ClicShopping\OM\Domains\AbstractDomainApp Domain App Base Class
 */
abstract class GuardrailsConfigAbstract
{
  /**
   * Get the complete security guardrails configuration for this domain
   * 
   * Child classes MUST implement this method to return domain-specific security rules.
   * The configuration should include:
   * - forbidden_tables: Array of table names that cannot be accessed
   * - forbidden_columns: Array of column names that cannot be accessed
   * - allowed_operations: Array of SQL operations allowed (SELECT, INSERT, UPDATE, DELETE)
   * - max_results: Maximum number of results that can be returned
   * - context: Context identifier (e.g., 'admin', 'shop', 'api')
   * 
   * Use the helper methods provided by this base class to build the configuration:
   * - getCommonForbiddenTables(): Returns common forbidden tables
   * - getCommonForbiddenColumns(): Returns common forbidden columns
   * - getCommonAllowedOperations(): Returns common allowed operations
   * - getDefaultMaxResults(): Returns default maximum results
   * 
   * @return array Associative array of security guardrails configuration
   */
  abstract public static function getConfig(): array;

  /**
   * Get common forbidden tables that should be restricted across all domains
   * 
   * These tables contain sensitive system data and should never be directly accessible
   * via AI-generated queries. Domain-specific implementations can extend this list
   * with additional forbidden tables.
   * 
   * Common Forbidden Tables:
   * - administrators: Admin user accounts and credentials
   * - sessions: Active user sessions and session data
   * - api_keys: API authentication keys and secrets
   * 
   * Usage:
   * ```php
   * $forbiddenTables = array_merge(
   *     self::getCommonForbiddenTables(),
   *     ['domain_specific_table1', 'domain_specific_table2']
   * );
   * ```
   * 
   * @return array Array of common forbidden table names
   */
  protected static function getCommonForbiddenTables(): array
  {
    return [
      'administrators',
      'sessions',
      'api_keys'
    ];
  }

  /**
   * Get common forbidden columns that should be restricted across all domains
   * 
   * These columns contain sensitive data (passwords, secrets, tokens) and should
   * never be included in query results. Domain-specific implementations can extend
   * this list with additional forbidden columns.
   * 
   * Common Forbidden Columns:
   * - password: User passwords (hashed or encrypted)
   * - secret_key: Secret keys for encryption/signing
   * - token: Authentication or session tokens
   * 
   * Usage:
   * ```php
   * $forbiddenColumns = array_merge(
   *     self::getCommonForbiddenColumns(),
   *     ['credit_card', 'ssn', 'bank_account']
   * );
   * ```
   * 
   * @return array Array of common forbidden column names
   */
  protected static function getCommonForbiddenColumns(): array
  {
    return [
      'password',
      'secret_key',
      'token'
    ];
  }

  /**
   * Get common allowed SQL operations for read-only contexts
   * 
   * By default, only SELECT operations are allowed. This is the most restrictive
   * setting and is appropriate for public-facing contexts (Shop, API).
   * 
   * Domain-specific implementations can extend this list for contexts that require
   * write access (Admin, Internal Tools):
   * - Admin context: ['SELECT', 'INSERT', 'UPDATE']
   * - Internal tools: ['SELECT', 'INSERT', 'UPDATE', 'DELETE']
   * 
   * Note: Destructive operations (DROP, TRUNCATE, ALTER) are NEVER allowed and
   * are blocked by Layer 1 (DbSecurity) regardless of this configuration.
   * 
   * Usage:
   * ```php
   * // Read-only context (Shop)
   * $allowedOps = self::getCommonAllowedOperations(); // ['SELECT']
   * 
   * // Read-write context (Admin)
   * $allowedOps = ['SELECT', 'INSERT', 'UPDATE'];
   * ```
   * 
   * @return array Array of allowed SQL operation names
   */
  protected static function getCommonAllowedOperations(): array
  {
    return ['SELECT'];
  }

  /**
   * Get default maximum number of results that can be returned
   * 
   * This limit prevents memory exhaustion and performance degradation from
   * queries that return too many results. The default is 1000 results.
   * 
   * Domain-specific implementations should adjust this based on context:
   * - Shop context: 100 (more restrictive for public access)
   * - Admin context: 10000 (less restrictive for internal use)
   * - API context: 1000 (balanced for programmatic access)
   * 
   * Note: Layer 1 (DbSecurity) also enforces a safety limit (default 10000)
   * by automatically adding LIMIT clauses to queries without explicit limits.
   * The domain guardrail limit should be equal to or less than the DbSecurity
   * safety limit.
   * 
   * Usage:
   * ```php
   * // Shop context (more restrictive)
   * $maxResults = 100;
   * 
   * // Admin context (less restrictive)
   * $maxResults = 10000;
   * 
   * // Default context
   * $maxResults = self::getDefaultMaxResults(); // 1000
   * ```
   * 
   * @return int Default maximum number of results
   */
  protected static function getDefaultMaxResults(): int
  {
    return 1000;
  }
}
