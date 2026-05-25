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
use ClicShopping\AI\DomainsAI\WebSearch\Tools\RagWebSearchEngine;
use ClicShopping\AI\InterfacesAI\WebSearchEngineProviderInterface;

/**
 * Built-in agnostic provider for Mode C — RAG WebSearch.
 *
 * Performs targeted scraping of admin-configured competitor sites stored in
 * `clic_rag_websearch`. The engine is agnostic — the sites it scrapes are
 * domain-supplied data, not Core knowledge.
 *
 * @package ClicShopping\AI\DomainsAI\WebSearch\Providers
 * @since 2026-05-24
 */
final class RagWebSearchProvider implements WebSearchEngineProviderInterface
{
    public const MODE = 'mode_c_rag_websearch';

    public function getMode(): string
    {
        return self::MODE;
    }

    public function getEngineClass(): string
    {
        return RagWebSearchEngine::class;
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
        return 'RAG WebSearch';
    }
}
