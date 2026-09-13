<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Prompt\Providers;

use ClicShopping\AI\InterfacesAI\PromptPlaceholderProviderInterface;

/**
 * StockReorderLevelProvider
 *
 * Renders `{{stock_reorder_level}}`: the shop-wide default reorder threshold
 * (STOCK_REORDER_LEVEL). A product's own products_quantity_alert overrides it when
 * set; 0 here means there is no shop-wide fallback. The value is a config constant,
 * not a table, so a prompt cannot spell it and no source table backs it.
 *
 * Emitted as a bare numeric literal for a SQL CASE — the effective reorder threshold
 * is products_quantity_alert when > 0, else this value.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Prompt\Providers
 */
class StockReorderLevelProvider implements PromptPlaceholderProviderInterface
{
  public const TOKEN = '{{stock_reorder_level}}';

  /**
   * @return string The token this provider answers for
   */
  public function getToken(): string
  {
    return self::TOKEN;
  }

  /**
   * Config constant, no table backs it.
   *
   * @return array<int, string> Empty
   */
  public function getSourceTables(): array
  {
    return [];
  }

  /**
   * Numeric literal for the SQL. Read under defined() — the constant is gated; absent it
   * renders 0, which the CASE guard (`> 0`) treats as "no shop-wide threshold".
   *
   * @param int $languageId Unused: the value is language-independent
   * @return string
   */
  public function render(int $languageId): string
  {
    return \defined('STOCK_REORDER_LEVEL') ? (string)(float)\constant('STOCK_REORDER_LEVEL') : '0';
  }
}
