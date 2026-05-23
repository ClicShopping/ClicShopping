<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
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
