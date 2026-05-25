<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Providers;

use ClicShopping\AI\InterfacesAI\WebSearchEngineProviderInterface;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Engines\AmazonShoppingEngine;

/**
 * Ecommerce-domain provider for Mode D — Amazon Shopping.
 *
 * Declares the AmazonShoppingEngine to the agnostic Core registry. Registered
 * by {@see \ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Registration\WebSearchRegistration}.
 *
 * The bridge between Mode D's mode identifier and the SerpAPI `engine=amazon`
 * particularities (query parameter `k` instead of `q`, Amazon-domain mapping)
 * lives here — Core remains unaware of Amazon specifics.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Providers
 * @since 2026-05-24
 */
final class AmazonShoppingProvider implements WebSearchEngineProviderInterface
{
    public const MODE = 'mode_d_amazon_shopping';

    public function getMode(): string
    {
        return self::MODE;
    }

    public function getEngineClass(): string
    {
        return AmazonShoppingEngine::class;
    }

    public function usesProductQuery(): bool
    {
        return true;
    }

    /**
     * The SerpAPI Amazon engine uses `k` (keyword) instead of `q`.
     */
    public function getSerpApiQueryParam(): string
    {
        return 'k';
    }

    public function getDisplayName(): string
    {
        return 'Amazon Shopping';
    }
}
