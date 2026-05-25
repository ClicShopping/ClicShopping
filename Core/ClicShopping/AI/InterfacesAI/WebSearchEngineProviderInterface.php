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
 * WebSearchEngineProviderInterface
 *
 * Domain-agnostic contract for declaring a WebSearch engine to the Core registry.
 *
 * Each domain (Ecommerce, HR, CRM, Finance, Trading, ...) provides one or more
 * providers via its own `Apps/AI/{Domain}/Classes/ClicShoppingAdmin/WebSearch/Registration/WebSearchRegistration.php`.
 * Providers wrap an engine implementation (a class implementing {@see WebSearchInterface})
 * together with the metadata Core needs to route a query to that engine without
 * knowing anything about the domain it belongs to.
 *
 * MULTI-DOMAIN ARCHITECTURE:
 * - Core only knows about generic, brand-free engines (Google AI Overview, Google Shopping,
 *   Google Trends, RAG WebSearch — all SerpAPI public protocols, no commercial brand)
 * - Domain-specific engines (Amazon for Ecommerce, LinkedIn for HR, Salesforce for CRM, ...)
 *   are declared by the matching App through this interface
 * - Adding a new domain never requires touching Core/
 *
 * @package ClicShopping\AI\InterfacesAI
 * @since 2026-05-24
 */
interface WebSearchEngineProviderInterface
{
    /**
     * Unique mode identifier used by WebSearchExecutor and IntentRouter.
     *
     * Built-in modes are prefixed by `mode_a_`, `mode_b_`, etc. Domain-specific
     * modes follow the same pattern (e.g. `mode_d_amazon_shopping`, `mode_h_linkedin`)
     * but their meaning is opaque to Core — only the registered provider knows.
     *
     * @return string Mode identifier (e.g. 'mode_d_amazon_shopping')
     */
    public function getMode(): string;

    /**
     * Fully-qualified class name of the engine implementation.
     *
     * The class MUST implement {@see WebSearchInterface}. WebSearchExecutor
     * instantiates it on demand for the mode returned by {@see getMode()}.
     *
     * @return string FQCN of a class implementing WebSearchInterface
     */
    public function getEngineClass(): string;

    /**
     * Whether this engine expects the canonical product/keyword query (as extracted
     * by IntentRouter into RoutingDecision::getProduct()) rather than the full
     * conversational query.
     *
     * Used by WebSearchExecutor to pick between `$routing->getProduct()` and the
     * raw `$query` string before calling `engine->search()`.
     *
     * @return bool True if the engine should receive the stripped product query
     */
    public function usesProductQuery(): bool;

    /**
     * SerpAPI query-parameter key for this engine.
     *
     * Most engines use `q`. A few (e.g. Amazon) require `k`. Returning the right
     * key here lets SerpApiClient stay generic — Core does not need to know which
     * engine names require which key.
     *
     * @return string SerpAPI param key (default 'q')
     */
    public function getSerpApiQueryParam(): string;

    /**
     * Optional display name for UI badges and source labels.
     *
     * Returned by the formatter as a fallback when no i18n key is defined for the
     * mode. Engines from the agnostic Core layer may return a generic label;
     * domain-specific engines typically return their brand name.
     *
     * @return string Human-readable label (e.g. 'Amazon Shopping', 'Google Trends')
     */
    public function getDisplayName(): string;
}
