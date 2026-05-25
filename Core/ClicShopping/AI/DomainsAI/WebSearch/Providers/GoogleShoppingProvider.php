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
use ClicShopping\AI\DomainsAI\WebSearch\Tools\GoogleShoppingEngine;
use ClicShopping\AI\InterfacesAI\WebSearchEngineProviderInterface;

/**
 * Built-in agnostic provider for Mode B — Google Shopping.
 *
 * Wraps the public SerpAPI `google_shopping` protocol. Default mode for
 * price_comparison intents with no target site.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Providers
 * @since 2026-05-24
 */
final class GoogleShoppingProvider implements WebSearchEngineProviderInterface
{
    public const MODE = 'mode_b_google_shopping';

    public function getMode(): string
    {
        return self::MODE;
    }

    public function getEngineClass(): string
    {
        return GoogleShoppingEngine::class;
    }

    public function usesProductQuery(): bool
    {
        return true;
    }

    public function getSerpApiQueryParam(): string
    {
        return WebSearchRegistryConfig::DEFAULT_SERPAPI_QUERY_PARAM;
    }

    public function getDisplayName(): string
    {
        return 'Google Shopping';
    }
}
