<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Interfaces;

/**
 * Interface for Domain-specific ClicShopping AI applications
 * 
 * Extends ConfigurableAppInterface to provide domain-specific functionality for AI-powered
 * applications. This interface defines the contract for multi-domain RAG (Retrieval-Augmented
 * Generation) systems where each domain (e.g., Ecommerce, HR, Finance) is implemented as a
 * standard ClicShopping App with domain-specific entities, guardrails, LLM prompts, and helpers.
 * 
 * Domain apps are discovered automatically via Apps::getAll() and registered with the DomainRegistry.
 * They support Pure LLM Mode for entity detection and query classification, eliminating the need
 * for pattern-based matching.
 * 
 * Key Features:
 * - Domain identification and metadata
 * - Entity configuration for domain-specific data models
 * - Security guardrails for LLM-generated queries
 * - LLM prompts for entity detection, query classification, and SQL generation
 * - Domain-specific helper classes for business logic
 * - Pure LLM Mode support (no pattern matching)
 * - Backward compatibility with pattern-based systems (optional)
 * 
 * @see ConfigurableAppInterface
 * @see \ClicShopping\OM\Apps
 * @see \ClicShopping\AI\DomainRegistry
 */
interface DomainAppInterface extends ConfigurableAppInterface
{
  /**
   * Get the unique domain identifier
   * 
   * Returns a unique string identifier for this domain (e.g., 'ecommerce', 'hr', 'finance').
   * This identifier is used for domain registration, routing, and cache keys.
   * 
   * The domain ID should be:
   * - Lowercase
   * - Alphanumeric with underscores only
   * - Unique across all domain apps
   * - Stable (should not change after deployment)
   * 
   * @return string The unique domain identifier (e.g., 'ecommerce', 'hr', 'finance')
   */
  public function getDomainId(): string;

  /**
   * Get the human-readable domain name
   * 
   * Returns a user-friendly name for this domain that can be displayed in the UI.
   * This name is typically used in admin interfaces, dashboards, and user-facing
   * domain selection controls.
   * 
   * The domain name should be:
   * - Human-readable and descriptive
   * - Properly capitalized
   * - Localized if multilingual support is needed
   * 
   * @return string The human-readable domain name (e.g., 'E-Commerce', 'Human Resources', 'Finance')
   */
  public function getDomainName(): string;

  /**
   * Get the entity configuration for this domain
   * 
   * Returns an array defining all entities (data models) available in this domain.
   * Each entity represents a database table or logical data structure that can be
   * queried via the RAG system.
   * 
   * Entity configuration structure:
   * ```php
   * [
   *   'entity_name' => [
   *     'table' => 'database_table_name',
   *     'id_column' => 'primary_key_column',
   *     'embedding_table' => 'embeddings_table_name',
   *     'description_fields' => ['field1', 'field2'],
   *     'searchable_fields' => ['field1', 'field2', 'field3'],
   *     'metadata' => ['additional' => 'info']
   *   ],
   *   // ... more entities
   * ]
   * ```
   * 
   * This configuration is used by:
   * - Entity detection (LLM identifies which entities are relevant to a query)
   * - SQL generation (LLM uses table/column names to generate queries)
   * - Semantic search (embedding tables for vector similarity)
   * - Result interpretation (description fields for human-readable output)
   * 
   * @return array Associative array of entity configurations keyed by entity name
   */
  public function getEntityConfig(): array;

  /**
   * Get the helper classes for this domain
   * 
   * Returns an array of instantiated helper objects that provide domain-specific
   * business logic, data transformations, and utility functions. Helpers encapsulate
   * complex operations that are specific to the domain.
   * 
   * Helpers structure:
   * ```php
   * [
   *   'product' => new ProductHelper(),
   *   'order' => new OrderHelper(),
   *   'customer' => new CustomerHelper(),
   *   // ... additional domain-specific helpers
   * ]
   * ```
   * 
   * Helpers are used for:
   * - Data formatting and transformation
   * - Business rule validation
   * - Complex calculations
   * - Domain-specific operations
   * 
   * @return array Associative array of helper instances keyed by helper name
   */
  public function getHelpers(): array;

  /**
   * Check if Pure LLM Mode is enabled for this domain
   * 
   * Returns true if the domain uses Pure LLM Mode for entity detection and query
   * classification, eliminating pattern-based matching. In Pure LLM Mode, all
   * detection and classification is performed by the LLM using the prompts defined
   * in getLlmPrompts().
   * 
   * Pure LLM Mode benefits:
   * - More accurate entity detection across languages
   * - Better handling of ambiguous queries
   * - No need to maintain pattern libraries
   * - Easier to extend with new entities
   * - Consistent behavior across domains
   * 
   * When Pure LLM Mode is disabled, the system may fall back to pattern-based
   * matching for backward compatibility.
   * 
   * @return bool True if Pure LLM Mode is enabled, false for pattern-based mode
   */
  public function isPureLlmMode(): bool;

  /**
   * Get the pattern classes for backward compatibility (optional)
   * 
   * Returns an array of pattern class names used for entity detection and query
   * classification in pattern-based mode. This method is optional and primarily
   * used for backward compatibility with legacy systems.
   * 
   * Pattern classes structure:
   * ```php
   * [
   *   'entity_detection' => 'ClicShopping\Apps\AI\Ecommerce\Classes\Patterns\EntityDetection',
   *   'query_classification' => 'ClicShopping\Apps\AI\Ecommerce\Classes\Patterns\QueryClassification',
   *   // ... additional pattern classes
   * ]
   * ```
   * 
   * Note: Pattern classes are NOT used when isPureLlmMode() returns true.
   * They are maintained for:
   * - Backward compatibility with existing implementations
   * - Fallback when LLM is unavailable
   * - Testing and comparison purposes
   * 
   * @return array Associative array of pattern class names keyed by pattern type
   */
  public function getPatternClasses(): array;
}
