<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Detectors;

use ClicShopping\AI\InterfacesAI\QueryIntentDetectorInterface;
use ClicShopping\AI\RegistryAI\WebSearchEngineRegistry;

/**
 * PriceComparisonIntentDetector
 *
 * Ecommerce-owned FALLBACK detector for price-comparison queries, consulted by
 * the planning layer only when the LLM intent signal (rag_intent_router) is
 * absent. Price comparison is a commerce concept, so its keywords live here in
 * the Ecommerce domain — never in agnostic Core.
 *
 * Registered through
 * {@see \ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Registration\WebSearchRegistration::register()}.
 *
 * Detection logic:
 *   1. a price keyword (price, cost), AND
 *   2. either a competitor/comparison keyword, OR an explicit target site
 *      ("price on <site>") resolved against the registered SiteRouters — so a
 *      new competitor becomes detectable simply by registering its router, with
 *      no retailer brand ever hard-coded.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Detectors
 * @since 2026-07-20
 */
final class PriceComparisonIntentDetector implements QueryIntentDetectorInterface
{
    /** Intent identifier emitted on a positive match. */
    public const INTENT = 'price_comparison';

    public function detectIntent(string $query): ?string
    {
        $queryLower = mb_strtolower($query, 'UTF-8');

        // Check for price keywords.
        $hasPriceKeyword = (
            mb_strpos($queryLower, 'price') !== false ||
            mb_strpos($queryLower, 'cost') !== false
        );

        if (!$hasPriceKeyword) {
            return null;
        }

        // Check for competitor/comparison keywords.
        $hasCompetitorKeyword = (
            mb_strpos($queryLower, 'competitor') !== false ||
            mb_strpos($queryLower, 'compar') !== false ||  // compare, comparison
            mb_strpos($queryLower, 'rival') !== false ||
            mb_strpos($queryLower, 'versus') !== false ||
            mb_strpos($queryLower, 'vs') !== false
        );

        // Check for an explicit "on/at/from <site>" target — domain-agnostic.
        // The candidate token is resolved against the registered SiteRouters; a
        // match means a domain owns that site. No brand list here: new
        // competitors become detectable simply by registering a router.
        $hasTargetSitePattern = false;
        if (preg_match('/\b(?:on|at|from)\s+([a-z0-9][a-z0-9.\-]+)/i', $queryLower, $m) === 1) {
            $hasTargetSitePattern = WebSearchEngineRegistry::getInstance()->findSiteRouter($m[1]) !== null;
        }

        return ($hasCompetitorKeyword || $hasTargetSitePattern) ? self::INTENT : null;
    }

    public function getDetectorId(): string
    {
        return 'ecommerce-price-comparison';
    }
}
