<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Registration;

use ClicShopping\AI\RegistryAI\WebSearchEngineRegistry;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Enhancers\MarketAnalysisEnhancer;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Providers\AmazonShoppingProvider;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\SiteRouters\AmazonSiteRouter;

/**
 * WebSearchRegistration
 *
 * Single entry point through which the Ecommerce domain declares its WebSearch
 * engines and site routers to the agnostic Core registry.
 *
 * Auto-discovered by {@see WebSearchEngineRegistry::bootstrapDomains()} on
 * first registry instantiation. The discovery mechanism is path-based:
 *   `Apps/AI/{Domain}/Classes/ClicShoppingAdmin/WebSearch/Registration/WebSearchRegistration.php`
 *   class `ClicShopping\Apps\AI\{Domain}\Classes\ClicShoppingAdmin\WebSearch\Registration\WebSearchRegistration`
 *   public static function register(WebSearchEngineRegistry $registry): void
 *
 * Other domains (HR, CRM, Finance, Trading, ...) follow the exact same
 * pattern — no Core change is needed to add a new domain.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Registration
 * @since 2026-05-24
 */
final class WebSearchRegistration
{
    /**
     * Register every Ecommerce-owned WebSearch component with the Core registry.
     */
    public static function register(WebSearchEngineRegistry $registry): void
    {
        $registry->registerProvider(new AmazonShoppingProvider());
        $registry->registerSiteRouter(new AmazonSiteRouter());

        // Post-search enhancers: add the "is my price aligned with the
        // market?" LLM synthesis at the top of price_comparison responses.
        $registry->registerResultEnhancer(new MarketAnalysisEnhancer());
    }
}
