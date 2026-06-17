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
 * QueryEnricherInterface
 *
 * Domain-agnostic contract for rewriting a web-search query BEFORE the engines
 * run, using conversational context (typically the last entity the user
 * discussed). Lets a domain App turn a follow-up like "compare with
 * competitors" into "iPhone 17 Pro compare with competitors" without Core ever
 * referencing the domain.
 *
 * Registered through
 * {@see \ClicShopping\AI\RegistryAI\WebSearchEngineRegistry::registerQueryEnricher()}
 * and invoked from the planning layer in registration order; each enricher
 * receives the (possibly already enriched) query returned by the previous one.
 *
 * Implementations MUST:
 *   - be idempotent (re-running must not further change an already-enriched query)
 *   - return the (possibly unchanged) query string
 *   - never throw — return the input untouched on any failure
 *
 * @package ClicShopping\AI\InterfacesAI
 * @since 2026-06-15
 */
interface QueryEnricherInterface
{
    /**
     * Rewrite the query using the supplied conversational context.
     *
     * @param string $query   The current web-search query (already in English)
     * @param array  $context Contextual data, e.g.:
     *                        - 'entity_name' (string): last discussed entity name
     *                        - 'entity_type' (string): its type (product, category, ...)
     *                        - 'intent_type' (string|null): detected intent
     * @return string The enriched (or untouched) query
     */
    public function enrich(string $query, array $context): string;

    /**
     * Stable identifier for this enricher (used in logs and diagnostics).
     *
     * @return string Enricher identifier (e.g. 'ecommerce-contextual-entity')
     */
    public function getEnricherId(): string;
}
