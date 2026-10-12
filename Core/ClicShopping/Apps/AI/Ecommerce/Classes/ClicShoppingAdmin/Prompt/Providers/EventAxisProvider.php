<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Prompt\Providers;

use ClicShopping\AI\DomainsAI\Analytics\Planning\EventAxisSql;
use ClicShopping\AI\InterfacesAI\PromptPlaceholderProviderInterface;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\EventAxis;

/**
 * EventAxisProvider
 *
 * Renders `{{event_axis_<projection>}}`: the signed event axis declared by EventAxis, so every
 * example of the prompt reads the same axis instead of a hand-written copy.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Prompt\Providers
 */
class EventAxisProvider implements PromptPlaceholderProviderInterface
{
  /**
   * @param string $projection Key of EventAxis::projections()
   */
  public function __construct(private readonly string $projection)
  {
  }

  /**
   * @return string The token this provider answers for
   */
  public function getToken(): string
  {
    return '{{event_axis_' . $this->projection . '}}';
  }

  /**
   * The axis is code, not data: no table content is rendered, so no freshness rule applies.
   *
   * @return array<int, string> Always empty
   */
  public function getSourceTables(): array
  {
    return [];
  }

  /**
   * @param int $languageId Unused: {{language_id}} is resolved by the chokepoint
   * @return string Body of the derived table, placeholders left for the chokepoint
   */
  public function render(int $languageId, ?array $resultRows = null): string
  {
    return EventAxisSql::render(EventAxis::axis(), EventAxis::projections()[$this->projection]);
  }
}
