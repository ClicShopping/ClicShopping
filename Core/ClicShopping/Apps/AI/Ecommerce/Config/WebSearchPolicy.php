<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Config;

use ClicShopping\AI\DomainsAI\WebSearch\Providers\GoogleAIOverviewProvider;
use ClicShopping\AI\DomainsAI\WebSearch\Providers\GoogleShoppingProvider;
use ClicShopping\AI\DomainsAI\WebSearch\Providers\GoogleTrendsProvider;
use ClicShopping\AI\DomainsAI\WebSearch\Providers\RagWebSearchProvider;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Providers\AmazonShoppingProvider;

/**
 * WebSearchPolicy
 *
 * Static allow-list of web-search modes the Ecommerce domain enables. Declared
 * to the agnostic registry at boot via WebSearchRegistration. Non-commerce
 * domains ship their own (narrower) policy or fall back to the socle default.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Config
 * @since 2026-07-22
 */
final class WebSearchPolicy
{
    /** @var array<string> Modes enabled for the Ecommerce domain (all commerce modes). */
    public const ALLOWED_MODES = [
        GoogleAIOverviewProvider::MODE,
        GoogleShoppingProvider::MODE,
        RagWebSearchProvider::MODE,
        GoogleTrendsProvider::MODE,
        AmazonShoppingProvider::MODE,
    ];
}
