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
 * SiteRouterInterface
 *
 * Domain-agnostic contract for mapping a target site (as extracted by the
 * Pure-LLM-Mode IntentRouter into the `target_site` field) to a list of
 * recommended WebSearch modes for a given user intent.
 *
 * ROLE WITHIN PURE LLM MODE:
 * - The primary detection of `target_site` is performed by the LLM upstream
 *   (IntentRouter::route()), never by pattern matching.
 * - SiteRouter implementations are consulted *after* the LLM has decided which
 *   site the user is targeting. They translate that site into the recommended
 *   mode pipeline (single mode or hybrid) for the matching engine.
 * - This is therefore NOT a fallback pattern detector — it is the downstream
 *   domain-knowledge layer that knows "<site>.fr means Mode D" because the
 *   Ecommerce domain owns that engine.
 *
 * MULTI-DOMAIN ARCHITECTURE:
 * - Each App registers its own SiteRouters via WebSearchRegistration.
 *   {Domain} app → its own SiteRouter (the sites that domain owns)
 *   another domain → its SiteRouter for the sites it owns       [future]
 *   ...                                                         [future]
 * - Core consults all registered routers via WebSearchEngineRegistry::findSiteRouter().
 *
 * @package ClicShopping\AI\InterfacesAI
 * @since 2026-05-24
 */
interface SiteRouterInterface
{
    /**
     * Whether this router recognises the given target site as one it owns.
     *
     * The site string comes from IntentRouter's LLM-driven extraction. It is
     * normalised to lowercase before being passed in (callers should also
     * normalise). Matching strategy is left to each implementation (exact host,
     * substring, regex, TLD list, ...).
     *
     * @param string $targetSite Lower-cased site identifier (e.g. '<site>.fr')
     * @return bool True if this router owns the site and can recommend modes
     */
    public function matches(string $targetSite): bool;

    /**
     * Recommended WebSearch modes for the given intent on this site.
     *
     * Returned mode identifiers MUST match modes registered via
     * {@see WebSearchEngineProviderInterface::getMode()}. Order matters: the
     * first entry is the primary engine, subsequent entries are added in hybrid
     * mode by the ModeSelector.
     *
     * Returning an empty array means "no recommendation" — ModeSelector will
     * fall back to its default mode for the intent.
     *
     * @param string $intentType Intent classification from IntentRouter
     *                           (e.g. 'price_comparison', 'market_research', 'product_discovery')
     * @return array<string> Ordered list of mode identifiers
     */
    public function getRecommendedModes(string $intentType): array;

    /**
     * Stable identifier for this router (used in logs and diagnostics).
     *
     * @return string Router identifier (e.g. '<site-id>', one per domain-owned site)
     */
    public function getRouterId(): string;
}
