<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\SiteRouters;

use ClicShopping\AI\InterfacesAI\SiteRouterInterface;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Providers\AmazonShoppingProvider;
use ClicShopping\AI\DomainsAI\WebSearch\Providers\GoogleShoppingProvider;
use ClicShopping\AI\DomainsAI\WebSearch\Providers\GoogleAIOverviewProvider;

/**
 * AmazonSiteRouter
 *
 * Maps any Amazon marketplace TLD detected by the upstream Pure-LLM IntentRouter
 * (target_site = "amazon.fr", "amazon.com", ...) to the Ecommerce-owned Mode D
 * (AmazonShoppingEngine via SerpAPI `engine=amazon`) and the appropriate hybrid
 * partners (Mode B / Mode A) depending on the user intent.
 *
 * ROLE WITHIN PURE LLM MODE:
 * - This is NOT a pattern-based intent classifier — the intent classification
 *   itself is done by the LLM in IntentRouter. This router is consulted
 *   *after* the LLM has decided the user is interested in a particular site.
 * - Holding the list of Amazon TLDs here (rather than in Core/) is the
 *   AGENTS.md-mandated location for domain-specific routing knowledge.
 *
 * MIGRATION NOTE (2026-05-24):
 *   Previously the Amazon domain list lived in
 *   `Core/AI/DomainsAI/WebSearch/Patterns/LocationPatterns::amazonDomainName()`
 *   and was duplicated in `AmazonShoppingEngine::isAmazonSite()`. Both copies
 *   were removed and consolidated here as part of VIOLATION-002 resolution
 *   (cf. AUDIT_REPORT_AI_2026_05_18.md).
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\SiteRouters
 * @since 2026-05-24
 */
final class AmazonSiteRouter implements SiteRouterInterface
{
    /**
     * Canonical Amazon marketplace TLDs supported by the SerpAPI `engine=amazon`.
     * The bare `amazon` keyword catches free-form mentions the LLM extracts
     * before it can pick a specific TLD.
     *
     * Source: SerpAPI Amazon engine documentation (amazon_domain parameter).
     */
    public const SUPPORTED_AMAZON_DOMAINS = [
        'amazon',
        'amazon.com',
        'amazon.fr',
        'amazon.co.uk',
        'amazon.de',
        'amazon.es',
        'amazon.it',
        'amazon.ca',
        'amazon.com.mx',
        'amazon.co.jp',
        'amazon.in',
        'amazon.com.br',
        'amazon.com.au',
        'amazon.nl',
        'amazon.se',
        'amazon.pl',
        'amazon.sg',
        'amazon.ae',
        'amazon.sa',
    ];

    public function matches(string $targetSite): bool
    {
        if ($targetSite === '') {
            return false;
        }

        foreach (self::SUPPORTED_AMAZON_DOMAINS as $domain) {
            if ($targetSite === $domain || \str_contains($targetSite, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Preferred hybrid pipelines per intent. Preserves the exact behaviour
     * previously hard-coded in `ModeSelector::isAmazonSite()` callers.
     */
    public function getRecommendedModes(string $intentType): array
    {
        return match ($intentType) {
            'price_comparison'  => [AmazonShoppingProvider::MODE, GoogleShoppingProvider::MODE],
            'market_research'   => [GoogleAIOverviewProvider::MODE, AmazonShoppingProvider::MODE],
            'product_discovery' => [AmazonShoppingProvider::MODE],
            default             => [AmazonShoppingProvider::MODE],
        };
    }

    public function getRouterId(): string
    {
        return 'amazon';
    }
}
