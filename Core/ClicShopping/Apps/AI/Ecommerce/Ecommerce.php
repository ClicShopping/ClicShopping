<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce;

use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\EntityConfig;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns\HybridPreFilter;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\ProductHelper;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns\AnalyticsPatterns;
use ClicShopping\OM\Domains\AbstractDomainApp;

/**
 * Ecommerce Domain App for ClicShopping AI
 * 
 * This class implements the E-Commerce domain for the multi-domain RAG (Retrieval-Augmented
 * Generation) system. It provides domain-specific configuration for e-commerce entities
 * (products, orders, customers, categories), security guardrails, LLM prompts, and helper
 * classes.
 * 
 * The Ecommerce domain app is automatically discovered via Apps::getAll() and registered
 * with the DomainRegistry. It operates in Pure LLM Mode by default, using LLM-based entity
 * detection and query classification instead of pattern matching.
 * 
 * Key Features:
 * - Pure LLM Mode for entity detection and query classification
 * - E-commerce specific entities: products, orders, customers, categories, manufacturers, suppliers
 * - 3-layer security architecture (Core, AI/LLM, Domain Guardrails)
 * - Context-aware guardrails (Admin vs Shop with different permissions)
 * - Domain-specific LLM prompts for accurate query processing
 * - Helper classes for product, order, and customer operations
 * - Multilingual support with English processing (translation at boundaries)
 * - Backward compatibility with existing RAG BI system
 * 
 * Security Architecture (3 Layers):
 * 
 * Layer 1: Core Application Security (DbSecurity)
 * - Rate limiting, SQL validation, prepared statements, automatic LIMIT
 * - Location: Core/ClicShopping/AI/Security/DbSecurity.php
 * 
 * Layer 2: AI/LLM Security (SecurityOrchestrator + Components)
 * - Obfuscation detection, semantic threat analysis, response validation
 * - Location: Core/ClicShopping/AI/Security/
 * - Components: SecurityOrchestrator, ObfuscationPreprocessor, SemanticSecurityAnalyzer,
 *   LlmGuardrails, AnswerGroundingVerifier, HallucinationDetector
 * 
 * Layer 3: Domain Guardrails (This Class)
 * - Context-aware security rules (Admin vs Shop)
 * - Abstract: Core/ClicShopping/OM/DomainsAIGuardrailsConfigAbstract.php
 * - Admin: Classes/ClicShoppingAdmin/GuardrailsConfig.php (permissive)
 * - Shop: Classes/Shop/GuardrailsConfig.php (restrictive - future)
 * 
 * Directory Structure:
 * - Classes/ClicShoppingAdmin/: Configuration classes (EntityConfig, GuardrailsConfig, Helpers)
 * - Module/Hooks/ClicShoppingAdmin/: Agent hooks (SEOAgent, AnalyticsAgent, EntityDetection, etc.)
 * - Module/ClicShoppingAdmin/Config/: Configuration modules for admin settings
 * - Module/ClicShoppingAdmin/Dashboard/: Dashboard widgets for admin interface
 * - Sites/ClicShoppingAdmin/Pages/: Admin pages for domain management
 * - Sql/: Database schema and migration scripts
 * - languages/: Language files for multilingual support
 * 
 * Future Extensions (Shop):
 * - Classes/Shop/: Automation classes (DynamicPricing, ProductRecommendations, PersonalizationEngine)
 * - Module/Hooks/Shop/: Shop-side hooks for catalog automations
 * - Module/HeaderTags/: AI-generated meta tags and SEO
 * - Sites/Shop/Pages/: AI-powered catalog pages
 * 
 * Usage:
 * ```php
 * // Domain is automatically discovered and registered
 * $registry = \ClicShopping\AI\DomainRegistry::getInstance();
 * $ecommerce = $registry->getApp('ecommerce');
 * 
 * // Get domain configuration
 * $entities = $ecommerce->getEntityConfig();
 * $guardrails = $ecommerce->getGuardrailsConfig(); // Context-aware (Admin vs Shop)
 * $helpers = $ecommerce->getHelpers();
 * 
 * // Check Pure LLM Mode
 * if ($ecommerce->isPureLlmMode()) {
 *     // Use LLM-based entity detection
 * }
 * ```
 * 
 * @see AbstractDomainApp
 * @see \ClicShopping\AI\DomainsAI\Registry
 * @see \ClicShopping\OM\Interfaces\DomainAppInterface
 * @see \ClicShopping\OM\Domains\GuardrailsConfigAbstract
 * @see \ClicShopping\AI\Security\SecurityOrchestrator
 */


class Ecommerce extends AbstractDomainApp
{
  /**
   * API version for this domain app
   * 
   * @var int
   */
  protected $api_version = 1;

  /**
   * Unique identifier for this domain app
   * 
   * Format: ClicShopping_{Vendor}_{AppName}_V{Version}
   * 
   * @var string
   */
  protected string $identifier = 'ClicShopping_AI_Ecommerce_V1';

  /**
   * Pure LLM Mode flag
   * 
   * When true, all entity detection and query classification is performed by the LLM
   * using prompts loaded directly by AI components via DomainConfig. Pattern-based 
   * matching is not used.
   * 
   * @var bool
   */
  protected bool $pureLlmMode = true;

  /**
   * Initialize the Ecommerce domain app
   *
   * This method is called by the parent constructor after basic initialization.
   * It calls the parent init() method to register the domain app with the DomainRegistry.
   *
   * @return void
   */
  protected function init(): void
  {
    // Call parent init to register with DomainRegistry
    parent::init();

    // Additional Ecommerce-specific initialization can be added here
    // For example: loading configuration, initializing caches, etc.
  }

  /**
   * Get the unique domain identifier
   *
   * Returns 'ecommerce' as the unique identifier for this domain.
   * This identifier is used for domain registration, routing, and cache keys.
   *
   * @return string The unique domain identifier 'ecommerce'
   */
  public function getDomainId(): string
  {
    return 'ecommerce';
  }

  /**
   * Get the human-readable domain name
   *
   * Returns 'E-Commerce' as the user-friendly name for this domain.
   * This name is displayed in admin interfaces, dashboards, and domain selection controls.
   *
   * @return string The human-readable domain name 'E-Commerce'
   */
  public function getDomainName(): string
  {
    return 'Ecommerce';
  }

  /**
   * Get the entity configuration for this domain
   *
   * Returns domain-specific entity definitions for e-commerce entities.
   * Uses DYNAMIC discovery via MultiDBRAGManager and EntityRegistry.
   *
   * Entity configuration includes:
   * - products: Product catalog with descriptions, prices, inventory
   * - orders: Customer orders with items, totals, status
   * - customers: Customer accounts with contact info, addresses
   * - categories: Product categories with hierarchy
   * - manufacturers: Product manufacturers/brands
   * - suppliers: Product suppliers
   *
   * @return array Associative array of entity configurations keyed by entity name
   */
  public function getEntityConfig(): array
  {
    return EntityConfig::getConfig();
  }

  /**
   * Get tool registry class for domain-specific tool resolution.
   *
   * @return string|null Fully qualified class name or null if not supported
   */
  public function getToolRegistryClass(): ?string
  {
    return \ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Tools\ToolRegistry::class;
  }

  /**
   * Get the helper classes for this domain
   *
   * Returns instantiated helper objects for e-commerce business logic.
   * Currently provides ProductHelper for WebSearch price comparison queries.
   *
   * Helpers include:
   * - product: ProductHelper for product-specific operations (used by WebSearch)
   *
   * Future helpers (to be added when needed):
   * - order: OrderHelper for order-specific operations (when OrderAnalyticsAgent is implemented)
   * - customer: CustomerHelper for customer-specific operations (when CustomerSegmentationAgent is implemented)
   *
   * @return array Associative array of helper instances keyed by helper name
   */
  public function getHelpers(): array
  {
    return [
      'product' => new ProductHelper(),
    ];
  }

  /**
   * Get the HybridPreFilter pattern class for this domain
   *
   * Returns the fully qualified class name for the domain-specific HybridPreFilter pattern.
   * This allows the framework to load patterns dynamically from the active domain without
   * hardcoding Ecommerce-specific imports.
   *
   * TASK 2026-01-23: Added for domain-agnostic pattern loading
   *
   * @return string|null Fully qualified class name or null if not available
   */
  public function getHybridPreFilterClass(): ?string
  {
    return HybridPreFilter::class;
  }

  /**
   * Get the AnalyticsPatterns class for this domain
   *
   * Returns the fully qualified class name for the domain-specific AnalyticsPatterns.
   * This allows the framework to load patterns dynamically from the active domain without
   * hardcoding Ecommerce-specific imports.
   *
   * TASK 2026-01-23: Added for domain-agnostic pattern loading
   *
   * @return string|null Fully qualified class name or null if not available
   */
  public function getAnalyticsPatternsClass(): ?string
  {
    return AnalyticsPatterns::class;
  }
}
