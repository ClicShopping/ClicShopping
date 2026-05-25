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
 * WebSearchResultEnhancerInterface
 *
 * Domain-agnostic contract for post-processing the raw result returned by
 * {@see \ClicShopping\AI\DomainsAI\WebSearch\WebSearchFacade::search()}.
 *
 * Use case:
 *   A domain App (Ecommerce, HR, CRM, ...) registers one or more enhancers
 *   that look at the WebSearch payload after the engines have run and
 *   inject domain-specific data (HTML synthesis, summary table, KPI
 *   markers, etc.) into the result array — without Core ever needing to
 *   know about that domain.
 *
 * Example (Ecommerce):
 *   - `MarketAnalysisEnhancer`: when the intent is `price_comparison` and
 *     the response carries shopping_results, generates an LLM-written
 *     synthesis of "is your price aligned with the market" and adds it as
 *     `$results['market_analysis']`. WebSearchFormatter renders it at the
 *     top of the response.
 *
 * Enhancers are invoked from {@see WebSearchFacade::search()} after engine
 * execution, in their registration order. Each enhancer should:
 *   - be idempotent (re-running it must not change the outcome)
 *   - return the (possibly modified) results array
 *   - never throw — log and return the input untouched on failure
 *
 * @package ClicShopping\AI\InterfacesAI
 * @since 2026-05-25
 */
interface WebSearchResultEnhancerInterface
{
    /**
     * Whether this enhancer wants to process the given results.
     *
     * Called before {@see enhance()}. Implementations should keep this
     * predicate cheap (no DB or LLM calls), since it gates the heavier
     * processing in `enhance()`.
     *
     * @param array $results The raw WebSearch result array
     * @param array $context Optional context (intent type, language, product
     *                       query, user id, ...) forwarded by the facade
     * @return bool True if {@see enhance()} should be invoked
     */
    public function shouldEnhance(array $results, array $context): bool;

    /**
     * Enhance the result array with domain-specific additions.
     *
     * Implementations MUST return the input array (modified or not). They
     * MUST NOT throw — any failure should be caught internally and the
     * pristine input returned.
     *
     * @param array $results The raw WebSearch result array
     * @param array $context Optional context (intent type, language, ...)
     * @return array The enhanced (or untouched) result array
     */
    public function enhance(array $results, array $context): array;

    /**
     * Stable identifier for this enhancer (used in logs and diagnostics).
     *
     * @return string Enhancer identifier (e.g. 'market-analysis-synthesis')
     */
    public function getEnhancerId(): string;
}
