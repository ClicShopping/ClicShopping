<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Planner;

/**
 * WebSearchPlan - Value object representing a decomposed search plan
 *
 * Holds the result of compound query analysis. A compound query contains
 * multiple independent search intents (e.g. "market trends AND price comparison")
 * that must be executed as separate tasks and then merged.
 *
 * Each task in the plan has the structure:
 * [
 *   'query'       => string  — sub-query text
 *   'intent'      => string  — price_comparison|product_discovery|market_research
 *   'product'     => string  — product or category name
 *   'target_site' => string|null — specific site domain or null
 * ]
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Planner
 */
class WebSearchPlan
{
  private bool $isCompound;
  private array $tasks;

  public function __construct(bool $isCompound, array $tasks = [])
  {
    $this->isCompound = $isCompound;
    $this->tasks = $tasks;
  }

  public function isCompound(): bool
  {
    return $this->isCompound;
  }

  public function getTasks(): array
  {
    return $this->tasks;
  }

  public static function single(): self
  {
    return new self(false, []);
  }

  public static function compound(array $tasks): self
  {
    return new self(true, $tasks);
  }
}
