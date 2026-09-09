<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SchemaGraph\Registration;

use ClicShopping\AI\RegistryAI\SchemaGraphRegistry;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SchemaGraph\EcommerceSchemaGraph;

/**
 * SchemaGraphRegistration
 *
 * Opt-in of the Ecommerce domain into the agnostic schema-graph registry.
 * Discovered by path convention, exactly like PromptPlaceholderRegistration.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SchemaGraph\Registration
 */
final class SchemaGraphRegistration
{
  /**
   * Register this domain's schema graph.
   *
   * @param SchemaGraphRegistry $registry Agnostic registry to fill
   * @return void
   */
  public static function register(SchemaGraphRegistry $registry): void
  {
    $registry->register(new EcommerceSchemaGraph());
  }
}
