<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * SemanticConfigInterface
 *
 * Contract for domain-specific semantic/embedding configuration.
 * Implementations should provide overrides for semantic retrieval behavior.
 *
 */
 
interface SemanticConfigInterface
{
  /**
   * Get embedding table names to search (full table names).
   * Return empty array to use auto-discovery.
   *
   * @return array
   */
  public static function getEmbeddingTables(): array;

  /**
   * Get minimum similarity score (0.0 - 1.0).
   * Return null to use global/admin default.
   *
   * @return float|null
   */
  public static function getSimilarityThreshold(): ?float;

  /**
   * Get maximum results per store.
   * Return null to use global/admin default.
   *
   * @return int|null
   */
  public static function getMaxResultsPerStore(): ?int;
}
