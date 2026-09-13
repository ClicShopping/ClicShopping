<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\InterfacesAI;

/**
 * AnalyticsResultEnricherInterface
 *
 * Domain-side enrichment of analytics RESULT ROWS, after SQL execution and before
 * interpretation. Distinct from {@see QueryEnricherInterface}, which enriches the QUESTION.
 *
 * Core never knows what an enricher recognises: the enricher inspects the row shape it
 * produced and returns the rows untouched when they are not its business. Added columns
 * reach the LLM because ResultInterpreter serialises every column of every row.
 *
 * An enricher is exposed by the active domain App through
 * `getAnalyticsResultEnrichers(): array`, like `getMetricCatalog()`.
 *
 * @package ClicShopping\AI\InterfacesAI
 */
interface AnalyticsResultEnricherInterface
{
  /**
   * Add columns to the result rows, or return them unchanged.
   *
   * MUST be side-effect free and MUST NOT drop, reorder or overwrite existing columns.
   *
   * @param array<int|string, mixed> $rows Executed query rows
   * @return array<int|string, mixed> Same rows, possibly with extra columns
   */
  public function enrich(array $rows): array;
}
