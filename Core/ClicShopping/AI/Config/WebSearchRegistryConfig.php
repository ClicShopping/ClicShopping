<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\Config;

/**
 * WebSearchRegistryConfig
 *
 * Default configuration for the agnostic WebSearch engine registry.
 *
 * Provides fallback values that callers can override through ClicShopping
 * configuration constants. Values defined here are used by
 * {@see \ClicShopping\AI\DomainsAI\WebSearch\Registry\WebSearchEngineRegistry}
 * during its auto-scan bootstrap and should rarely need to be changed.
 *
 * NAMING (per AGENTS.md): all overrides use the constant pattern
 * `CLICSHOPPING_APP_API_AI_WEBSEARCH_*`. Verify the constant exists in the
 * Api/Ai App configuration before adding a new one — never invent constant names.
 */
class WebSearchRegistryConfig
{
    /**
     * Default sub-path (inside CLICSHOPPING_BASE_DIR) where domain Apps live.
     *
     * The registry scans every immediate child of this directory for a
     * registration class matching {@see REGISTRATION_CLASS_RELATIVE_PATH}.
     */
    public const DEFAULT_DOMAIN_BASE_PATH = 'Apps/AI/';

    /**
     * Relative path (under each Apps/AI/{Domain}/) of the per-domain
     * registration class file. Domains opt-in by placing a file here that
     * exposes the registration class returned by {@see REGISTRATION_CLASS_FQCN_TEMPLATE}.
     */
    public const REGISTRATION_CLASS_RELATIVE_PATH =
        'Classes/ClicShoppingAdmin/WebSearch/Registration/WebSearchRegistration.php';

    /**
     * Fully qualified class name template for the per-domain registration class.
     * `{Domain}` is replaced with the domain directory name (e.g. 'Ecommerce').
     *
     * The class MUST expose a public static `register(WebSearchEngineRegistry $r): void`
     * method that calls `$r->registerProvider()` and/or `$r->registerSiteRouter()`.
     */
    public const REGISTRATION_CLASS_FQCN_TEMPLATE =
        'ClicShopping\\Apps\\AI\\{Domain}\\Classes\\ClicShoppingAdmin\\WebSearch\\Registration\\WebSearchRegistration';

    /**
     * Default SerpAPI query-parameter key used by engines that do not declare
     * their own override via WebSearchEngineProviderInterface::getSerpApiQueryParam().
     */
    public const DEFAULT_SERPAPI_QUERY_PARAM = 'q';

    /**
     * Returns the resolved base path for the domain-scan, honouring any future
     * `CLICSHOPPING_APP_API_AI_WEBSEARCH_BASE_PATH` override (constant currently
     * not defined — defaults always apply).
     *
     * @return string Absolute or relative base path ending with a trailing slash
     */
    public static function getDomainBasePath(): string
    {
        if (\defined('CLICSHOPPING_APP_API_AI_WEBSEARCH_BASE_PATH')) {
            $override = \trim((string) \constant('CLICSHOPPING_APP_API_AI_WEBSEARCH_BASE_PATH'));
            if ($override !== '') {
                return \rtrim($override, '/') . '/';
            }
        }

        return self::DEFAULT_DOMAIN_BASE_PATH;
    }

    /**
     * Whether the auto-scan bootstrap should run. Defaults to enabled; can be
     * disabled in test environments via `CLICSHOPPING_APP_API_AI_WEBSEARCH_AUTOSCAN`.
     *
     * @return bool True if the registry should scan Apps/AI/* on bootstrap
     */
    public static function isAutoScanEnabled(): bool
    {
        if (\defined('CLICSHOPPING_APP_API_AI_WEBSEARCH_AUTOSCAN')) {
            return \constant('CLICSHOPPING_APP_API_AI_WEBSEARCH_AUTOSCAN') === 'True';
        }

        return true;
    }

    /**
     * Resolve the fully qualified registration class name for a given domain.
     *
     * @param string $domain Domain directory name (e.g. 'Ecommerce')
     * @return string FQCN such as `ClicShopping\Apps\AI\Ecommerce\Classes\...\WebSearchRegistration`
     */
    public static function getRegistrationClassFqcn(string $domain): string
    {
        return \str_replace('{Domain}', $domain, self::REGISTRATION_CLASS_FQCN_TEMPLATE);
    }
}
