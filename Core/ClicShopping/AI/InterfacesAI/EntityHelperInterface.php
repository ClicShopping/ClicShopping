<?php
/**
 * EntityHelperInterface - Contract for domain-specific entity helpers
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * EntityHelperInterface
 *
 * Defines the contract for domain-specific entity helpers.
 * Each domain (Ecommerce, HR, Finance, Trading) implements this interface
 * to provide entity-specific helper methods.
 *
 * This interface enables:
 * 1. Generic code in Core/ClicShopping/AI/ that works with any domain
 * 2. Domain-specific implementations in domain apps
 * 3. Easy creation of new domains by implementing this interface
 *
 * Example implementations:
 * - ProductHelper (Ecommerce domain) - queries products table
 * - EmployeeHelper (HR domain) - queries employees table
 * - AccountHelper (Finance domain) - queries accounts table
 * - AssetHelper (Trading domain) - queries assets table
 *
 */
interface EntityHelperInterface
{
  /**
   * Get entity by ID
   *
   * Retrieves entity information from database.
   * Implementation varies by domain:
   * - Ecommerce: queries products table
   * - HR: queries employees table
   * - Finance: queries accounts table
   * - Trading: queries assets table
   *
   * @param int $id Entity ID
   * @param int|null $languageId Language ID (optional, defaults to current language)
   * @return array|null Entity data or null if not found
   *
   * @example
   * ```php
   * // Ecommerce
   * $product = ProductHelper::getEntityById(123);
   * // Returns: ['product_id' => 123, 'name' => 'Product Name', 'price' => 99.99, ...]
   *
   * // HR
   * $employee = EmployeeHelper::getEntityById(456);
   * // Returns: ['employee_id' => 456, 'name' => 'John Doe', 'department_id' => 1, ...]
   * ```
   */
  public static function getEntityById(int $id, ?int $languageId = null): ?array;

  /**
   * Get entity name by ID
   *
   * Quick method to retrieve just the entity name/title.
   * Implementation varies by domain:
   * - Ecommerce: returns product name
   * - HR: returns employee name
   * - Finance: returns account name
   * - Trading: returns asset name
   *
   * @param int $id Entity ID
   * @param int|null $languageId Language ID (optional, defaults to current language)
   * @return string|null Entity name or null if not found
   *
   * @example
   * ```php
   * // Ecommerce
   * $name = ProductHelper::getEntityName(123);
   * // Returns: "Product Name"
   *
   * // HR
   * $name = EmployeeHelper::getEntityName(456);
   * // Returns: "John Doe"
   * ```
   */
  public static function getEntityName(int $id, ?int $languageId = null): ?string;

  /**
   * Get multiple entities by IDs
   *
   * Batch retrieval for multiple entities.
   * Implementation varies by domain:
   * - Ecommerce: queries products table with IN clause
   * - HR: queries employees table with IN clause
   * - Finance: queries accounts table with IN clause
   * - Trading: queries assets table with IN clause
   *
   * @param array $ids Array of entity IDs
   * @param int|null $languageId Language ID (optional, defaults to current language)
   * @return array Array of entity data indexed by ID
   *
   * @example
   * ```php
   * // Ecommerce
   * $products = ProductHelper::getEntitiesByIds([123, 456, 789]);
   * // Returns: [
   * //   123 => ['product_id' => 123, 'name' => 'Product 1', ...],
   * //   456 => ['product_id' => 456, 'name' => 'Product 2', ...],
   * //   789 => ['product_id' => 789, 'name' => 'Product 3', ...]
   * // ]
   *
   * // HR
   * $employees = EmployeeHelper::getEntitiesByIds([1, 2, 3]);
   * // Returns: [
   * //   1 => ['employee_id' => 1, 'name' => 'John', ...],
   * //   2 => ['employee_id' => 2, 'name' => 'Jane', ...],
   * //   3 => ['employee_id' => 3, 'name' => 'Bob', ...]
   * // ]
   * ```
   */
  public static function getEntitiesByIds(array $ids, ?int $languageId = null): array;

  /**
   * Check if entity exists
   *
   * Verifies whether an entity with the given ID exists in the database.
   * Implementation varies by domain:
   * - Ecommerce: checks products table
   * - HR: checks employees table
   * - Finance: checks accounts table
   * - Trading: checks assets table
   *
   * @param int $id Entity ID
   * @return bool True if entity exists, false otherwise
   *
   * @example
   * ```php
   * // Ecommerce
   * if (ProductHelper::entityExists(123)) {
   *   // Product exists
   * }
   *
   * // HR
   * if (EmployeeHelper::entityExists(456)) {
   *   // Employee exists
   * }
   * ```
   */
  public static function entityExists(int $id): bool;
}
