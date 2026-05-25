<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\DomainsAI\WebSearch\Providers;

use ClicShopping\AI\Config\WebSearchRegistryConfig;
use ClicShopping\AI\DomainsAI\WebSearch\Tools\GoogleAIOverviewEngine;
use ClicShopping\AI\InterfacesAI\WebSearchEngineProviderInterface;

/**
 * Built-in agnostic provider for Mode A — Google AI Overview.
 *
 * Wraps the public SerpAPI `google_ai_overview` protocol. Used as the default
 * mode for market_research and product_discovery intents without a target site.
 *
 * Not a commercial brand — `google_ai_overview` is a SerpAPI protocol name.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Providers
 * @since 2026-05-24
 */
final class GoogleAIOverviewProvider implements WebSearchEngineProviderInterface
{
    public const MODE = 'mode_a_ai_overview';

    public function getMode(): string
    {
        return self::MODE;
    }

    public function getEngineClass(): string
    {
        return GoogleAIOverviewEngine::class;
    }

    public function usesProductQuery(): bool
    {
        return false;
    }

    public function getSerpApiQueryParam(): string
    {
        return WebSearchRegistryConfig::DEFAULT_SERPAPI_QUERY_PARAM;
    }

    public function getDisplayName(): string
    {
        return 'AI Overview';
    }
}
