<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

use ClicShopping\AI\InterfacesAI\SchemaConfigInterface;

/**
 * SchemaConfig Class
 *
 * Provides schema-specific rules and guidance for the Ecommerce domain.
 * These rules help the LLM understand domain-specific schema patterns
 * and avoid common mistakes when generating SQL queries.
 *
 * DESIGN PRINCIPLES:
 * - Domain-specific: Contains e-commerce schema knowledge
 * - LLM-friendly: Provides clear guidance for SQL generation
 * - Extensible: Easy to add new rules as needed
 *
 * Usage:
 * ```php
 * $rules = SchemaConfig::getSchemaRules();
 * ```
 */
class SchemaConfig implements SchemaConfigInterface
{
  /**
   * Get schema rules for the Ecommerce domain
   *
   * Returns an array of schema rules that help the LLM understand
   * domain-specific patterns and avoid common mistakes.
   *
   * @return array Array of schema rule strings
   */
  public static function getSchemaRules(): array
  {
    return [
      "IMPORTANT: Regarding the structure of the e-commerce tables, please note the following details:\n",
      "PRIORITY RULES:",
      "- For product weight queries: Use clic_products.products_weight (contains actual weight VALUES)",
      "- clic_weight_classes is a REFERENCE table for weight UNITS (kg, g, lbs, oz) NOT weight values",
      "- When asked about a product's weight, ALWAYS query clic_products, NOT clic_weight_classes\n"
    ];
  }

  /**
   * Get schema rules as a formatted string
   *
   * @return string Formatted schema rules
   */
  public static function getSchemaRulesString(): string
  {
    return implode("\n", self::getSchemaRules());
  }
}
