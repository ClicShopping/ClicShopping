<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\RegistryAI;

use ClicShopping\AI\Config\WebSearchRegistryConfig;
use ClicShopping\AI\DomainsAI\WebSearch\Providers\GoogleAIOverviewProvider;
use ClicShopping\AI\DomainsAI\WebSearch\Providers\GoogleShoppingProvider;
use ClicShopping\AI\DomainsAI\WebSearch\Providers\GoogleTrendsProvider;
use ClicShopping\AI\DomainsAI\WebSearch\Providers\RagWebSearchProvider;
use ClicShopping\AI\InterfacesAI\SiteRouterInterface;
use ClicShopping\AI\InterfacesAI\WebSearchEngineProviderInterface;
use ClicShopping\AI\InterfacesAI\WebSearchResultEnhancerInterface;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * WebSearchEngineRegistry
 *
 * Domain-agnostic, in-memory, process-scoped registry for WebSearch engines.
 *
 * Lives alongside {@see ActorRegistry} and {@see CriticRegistry} in the
 * shared RegistryAI namespace, following the convention established for
 * AI-wide registries.
 *
 * RESPONSIBILITIES:
 * 1. Hold the agnostic, brand-free Core engines built into Mode A/B/C/E
 *    (Google AI Overview, Google Shopping, RAG WebSearch, Google Trends —
 *    Google here refers to public SerpAPI protocols, not a merchant brand).
 * 2. Let any `Apps/AI/{Domain}/` register additional domain-specific engines
 *    (Mode D Amazon for Ecommerce, future Modes for HR / CRM / Finance / ...).
 * 3. Map a target site, as detected upstream by the LLM in IntentRouter, to
 *    the recommended modes of the matching SiteRouter — without Core ever
 *    knowing about any specific brand.
 * 4. Expose the engine-specific SerpAPI query-param key so SerpApiClient
 *    never special-cases an engine name.
 *
 * BOOTSTRAP STRATEGY:
 * - Built-in Core providers are registered eagerly in the constructor.
 * - Each domain App opts in by shipping
 *   `Apps/AI/{Domain}/Classes/ClicShoppingAdmin/WebSearch/Registration/WebSearchRegistration.php`
 *   exposing a public static `register(WebSearchEngineRegistry $r): void`.
 * - On first instantiation the registry scans `Apps/AI/*` (see
 *   {@see WebSearchRegistryConfig}) and invokes each domain's registration.
 *
 * EXTENSIBILITY (multi-domain):
 *   Apps/AI/HR/Classes/ClicShoppingAdmin/WebSearch/Registration/WebSearchRegistration.php
 *     final class WebSearchRegistration {
 *       public static function register(WebSearchEngineRegistry $r): void {
 *         $r->registerProvider(new LinkedInProvider());
 *         $r->registerSiteRouter(new LinkedInSiteRouter());
 *       }
 *     }
 *   No Core change is needed to add HR, CRM, Finance, Trading, ... domains.
 *
 * DIFFERENCE FROM ActorRegistry / CriticRegistry:
 *   ActorRegistry and CriticRegistry persist agent capabilities to the database
 *   because cross-request state tracking and performance metrics are needed.
 *   WebSearchEngineRegistry holds only deterministic, side-effect-free
 *   configuration — providers are stateless, so an in-memory per-request
 *   rebuild is faster and safer than DB round-trips on every search.
 *
 * @package ClicShopping\AI\RegistryAI
 * @since 2026-05-24
 */
final class WebSearchEngineRegistry
{
    private static ?self $instance = null;

    /** @var array<string, WebSearchEngineProviderInterface> */
    private array $providersByMode = [];

    /** @var array<string, SiteRouterInterface> */
    private array $siteRoutersById = [];

    /** @var array<string, WebSearchResultEnhancerInterface> */
    private array $resultEnhancersById = [];

    private SecurityLogger $logger;
    private bool $debug;
    private bool $domainsBootstrapped = false;

    /**
     * Returns the shared registry instance, bootstrapping built-in providers
     * and (once) every available domain on first call.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->bootstrapDomains();
        }

        return self::$instance;
    }

    /**
     * Reset the singleton — test-only helper. Production code MUST NOT call this.
     */
    public static function resetForTesting(): void
    {
        self::$instance = null;
    }

    private function __construct()
    {
        $this->logger = new SecurityLogger();
        $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
            && \CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

        $this->registerCoreProviders();
    }

    /**
     * Register a WebSearch engine provider.
     *
     * @throws \InvalidArgumentException If a provider for the same mode is already registered
     */
    public function registerProvider(WebSearchEngineProviderInterface $provider): void
    {
        $mode = $provider->getMode();

        if (isset($this->providersByMode[$mode])) {
            throw new \InvalidArgumentException(
                "WebSearch provider for mode '{$mode}' is already registered "
                . "(existing: " . $this->providersByMode[$mode]::class . ", "
                . "incoming: " . $provider::class . ")"
            );
        }

        $this->providersByMode[$mode] = $provider;

        if ($this->debug) {
            $this->logger->logStructured('info', 'WebSearchEngineRegistry', 'provider_registered', [
                'mode' => $mode,
                'engine_class' => $provider->getEngineClass(),
                'provider_class' => $provider::class,
            ]);
        }
    }

    /**
     * Register a SiteRouter for downstream target_site → mode mapping.
     *
     * @throws \InvalidArgumentException If a router with the same ID is already registered
     */
    public function registerSiteRouter(SiteRouterInterface $router): void
    {
        $routerId = $router->getRouterId();

        if (isset($this->siteRoutersById[$routerId])) {
            throw new \InvalidArgumentException(
                "SiteRouter with id '{$routerId}' is already registered"
            );
        }

        $this->siteRoutersById[$routerId] = $router;

        if ($this->debug) {
            $this->logger->logStructured('info', 'WebSearchEngineRegistry', 'site_router_registered', [
                'router_id' => $routerId,
                'router_class' => $router::class,
            ]);
        }
    }

    /**
     * Register a result enhancer (post-processor invoked after every search).
     *
     * Enhancers fire in registration order. The first registered wins on
     * conflicting writes — later enhancers see the modified result.
     *
     * @throws \InvalidArgumentException If an enhancer with the same id is already registered
     */
    public function registerResultEnhancer(WebSearchResultEnhancerInterface $enhancer): void
    {
        $enhancerId = $enhancer->getEnhancerId();

        if (isset($this->resultEnhancersById[$enhancerId])) {
            throw new \InvalidArgumentException(
                "WebSearch result enhancer with id '{$enhancerId}' is already registered"
            );
        }

        $this->resultEnhancersById[$enhancerId] = $enhancer;

        if ($this->debug) {
            $this->logger->logStructured('info', 'WebSearchEngineRegistry', 'result_enhancer_registered', [
                'enhancer_id' => $enhancerId,
                'enhancer_class' => $enhancer::class,
            ]);
        }
    }

    /**
     * @return array<WebSearchResultEnhancerInterface> All registered enhancers in registration order
     */
    public function getResultEnhancers(): array
    {
        return \array_values($this->resultEnhancersById);
    }

    /**
     * Retrieve the provider for a given mode identifier.
     */
    public function getProvider(string $mode): ?WebSearchEngineProviderInterface
    {
        return $this->providersByMode[$mode] ?? null;
    }

    /**
     * Whether a provider is registered for the given mode.
     */
    public function hasProvider(string $mode): bool
    {
        return isset($this->providersByMode[$mode]);
    }

    /**
     * @return array<string> All registered mode identifiers
     */
    public function getRegisteredModes(): array
    {
        return \array_keys($this->providersByMode);
    }

    /**
     * Find the SiteRouter that owns the given target site (LLM-extracted upstream).
     *
     * The lookup is order-stable: routers are consulted in their registration
     * order and the first match wins. Built-in Core registers no SiteRouter —
     * matches only come from registered domain Apps.
     */
    public function findSiteRouter(?string $targetSite): ?SiteRouterInterface
    {
        if ($targetSite === null || $targetSite === '') {
            return null;
        }

        $normalised = \strtolower(\trim($targetSite));

        foreach ($this->siteRoutersById as $router) {
            if ($router->matches($normalised)) {
                return $router;
            }
        }

        return null;
    }

    /**
     * SerpAPI query-parameter key for the given engine name.
     *
     * Delegates to {@see findProviderByEngineName()} then reads the value via
     * {@see WebSearchEngineProviderInterface::getSerpApiQueryParam()}. Returns
     * the registry default (currently 'q') if the engine is unknown, so
     * SerpApiClient stays safe for legacy / unregistered engines.
     */
    public function getSerpApiQueryParam(string $engineName): string
    {
        $provider = $this->findProviderByEngineName($engineName);

        return $provider !== null
            ? $provider->getSerpApiQueryParam()
            : WebSearchRegistryConfig::DEFAULT_SERPAPI_QUERY_PARAM;
    }

    /**
     * Resolve a registered provider from the engine name returned by
     * {@see \ClicShopping\AI\InterfacesAI\WebSearchInterface::getName()}.
     *
     * Iterates every registered provider, instantiates its engine class once
     * and compares the engine name. The lookup is O(n) on the number of
     * registered providers — n is small (typically 5-10) so a cached map is
     * not yet needed; callers that need it per-request may memoise locally.
     */
    public function findProviderByEngineName(string $engineName): ?WebSearchEngineProviderInterface
    {
        foreach ($this->providersByMode as $provider) {
            $engineClass = $provider->getEngineClass();
            if (!\class_exists($engineClass)) {
                continue;
            }
            try {
                $engineInstance = new $engineClass();
                if (\method_exists($engineInstance, 'getName')
                    && $engineInstance->getName() === $engineName) {
                    return $provider;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Register the four built-in agnostic Core providers (Mode A/B/C/E).
     * No commercial brand here — every Mode is a public SerpAPI protocol.
     */
    private function registerCoreProviders(): void
    {
        $this->providersByMode[GoogleAIOverviewProvider::MODE] = new GoogleAIOverviewProvider();
        $this->providersByMode[GoogleShoppingProvider::MODE]   = new GoogleShoppingProvider();
        $this->providersByMode[RagWebSearchProvider::MODE]     = new RagWebSearchProvider();
        $this->providersByMode[GoogleTrendsProvider::MODE]     = new GoogleTrendsProvider();
    }

    /**
     * Scan `Apps/AI/*` and invoke each domain's WebSearchRegistration class.
     *
     * Each domain is loaded at most once per process. Missing or malformed
     * registration files are silently skipped (with a debug log) so a broken
     * domain never prevents the rest of the system from booting.
     */
    private function bootstrapDomains(): void
    {
        if ($this->domainsBootstrapped || !WebSearchRegistryConfig::isAutoScanEnabled()) {
            return;
        }

        $this->domainsBootstrapped = true;

        if (!\defined('CLICSHOPPING_BASE_DIR')) {
            return;
        }

        $basePath = \CLICSHOPPING_BASE_DIR . WebSearchRegistryConfig::getDomainBasePath();

        if (!\is_dir($basePath)) {
            return;
        }

        $entries = \scandir($basePath);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $domainPath = $basePath . $entry;
            if (!\is_dir($domainPath)) {
                continue;
            }

            $registrationFile = $domainPath . '/'
                . WebSearchRegistryConfig::REGISTRATION_CLASS_RELATIVE_PATH;

            if (!\file_exists($registrationFile)) {
                continue;
            }

            $fqcn = WebSearchRegistryConfig::getRegistrationClassFqcn($entry);

            if (!\class_exists($fqcn)) {
                if ($this->debug) {
                    $this->logger->logStructured('warning', 'WebSearchEngineRegistry', 'registration_class_missing', [
                        'domain' => $entry,
                        'expected_class' => $fqcn,
                        'file' => $registrationFile,
                    ]);
                }
                continue;
            }

            if (!\method_exists($fqcn, 'register')) {
                if ($this->debug) {
                    $this->logger->logStructured('warning', 'WebSearchEngineRegistry', 'register_method_missing', [
                        'domain' => $entry,
                        'class' => $fqcn,
                    ]);
                }
                continue;
            }

            try {
                $fqcn::register($this);

                if ($this->debug) {
                    $this->logger->logStructured('info', 'WebSearchEngineRegistry', 'domain_registered', [
                        'domain' => $entry,
                        'class' => $fqcn,
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logger->logStructured('error', 'WebSearchEngineRegistry', 'domain_registration_failed', [
                    'domain' => $entry,
                    'class' => $fqcn,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
