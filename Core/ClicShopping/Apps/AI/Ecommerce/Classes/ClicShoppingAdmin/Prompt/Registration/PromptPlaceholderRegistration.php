<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Prompt\Registration;

use ClicShopping\AI\RegistryAI\PromptPlaceholderRegistry;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Prompt\Providers\MetricCatalogProvider;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Prompt\Providers\OrderStatusMapProvider;

/**
 * PromptPlaceholderRegistration
 *
 * Opt-in of the Ecommerce domain into the agnostic dynamic-placeholder registry.
 * Discovered by path convention, exactly like WebSearchRegistration.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Prompt\Registration
 */
final class PromptPlaceholderRegistration
{
  /**
   * Register every dynamic prompt placeholder this domain provides.
   *
   * @param PromptPlaceholderRegistry $registry Agnostic registry to fill
   * @return void
   */
  public static function register(PromptPlaceholderRegistry $registry): void
  {
    $registry->register(new OrderStatusMapProvider());
    $registry->register(new MetricCatalogProvider());
  }
}
