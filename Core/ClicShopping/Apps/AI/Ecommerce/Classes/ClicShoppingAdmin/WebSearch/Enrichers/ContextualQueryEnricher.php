<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Enrichers;

use ClicShopping\AI\InterfacesAI\QueryEnricherInterface;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns\WebSearchPatterns;

/**
 * ContextualQueryEnricher — Ecommerce query enricher
 *
 * Turns a contextual follow-up web search ("compare with competitors",
 * "price reviews") into a self-contained query by prepending the last entity
 * the user discussed (e.g. "iPhone 17 Pro compare with competitors"). The
 * heavy lifting — detecting contextual keywords and avoiding duplicate
 * prepends — stays in {@see WebSearchPatterns::enrichWebSearchQuery()}, which
 * also owns the rest of the Ecommerce price/web logic.
 *
 * Registered with the agnostic Core registry through
 * {@see \ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Registration\WebSearchRegistration}.
 * Core (the planning layer) invokes it via
 * {@see \ClicShopping\AI\RegistryAI\WebSearchEngineRegistry::getQueryEnrichers()}
 * without ever referencing this class — that is what keeps Core domain-agnostic.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\WebSearch\Enrichers
 * @since 2026-06-15
 */
final class ContextualQueryEnricher implements QueryEnricherInterface
{
    private const ENRICHER_ID = 'ecommerce-contextual-entity';

    public function getEnricherId(): string
    {
        return self::ENRICHER_ID;
    }

    public function enrich(string $query, array $context): string
    {
        $entityName = (string) ($context['entity_name'] ?? '');
        $entityType = (string) ($context['entity_type'] ?? 'entity');

        // No entity to inject → leave the query untouched.
        if (trim($entityName) === '') {
            return $query;
        }

        return WebSearchPatterns::enrichWebSearchQuery($query, $entityName, $entityType);
    }
}
