<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin;

use ClicShopping\AI\InterfacesAI\SemanticConfigInterface;

/**
 * SemanticConfig Class
 *
 * Domain-specific semantic retrieval configuration for Ecommerce.
 * Return null values to keep admin/global defaults.
 */
class SemanticConfig implements SemanticConfigInterface
{
  public static function getEmbeddingTables(): array
  {
    // Empty array = use auto-discovery of *_embedding tables.
    return [];
  }

  public static function getSimilarityThreshold(): ?float
  {
    // Return null to use admin/global default.
    return null;
  }

  public static function getMaxResultsPerStore(): ?int
  {
    // Return null to use admin/global default.
    return null;
  }
}
