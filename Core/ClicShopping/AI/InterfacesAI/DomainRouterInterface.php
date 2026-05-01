<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * DomainRouterInterface
 *
 * Interface for domain routing functionality in the AI orchestration system.
 * This interface defines the contract for routing queries to appropriate query type domains
 * based on intent classification and domain capabilities.
 *
 * IMPORTANT DISTINCTION:
 * - Query Type Domains: Define HOW queries are processed
 *   Examples: Semantic search, SQL generation, hybrid processing, web search
 *   Location: Core/ClicShopping/AI/DomainsAI
 *
 * - Business Domains (FUTURE): Define WHAT data is queried
 *   Examples: Ecommerce (products, orders), Finance (transactions), HR (employees)
 *   Location: Core/ClicShopping/Apps/AI/{Domain}/ (future spec: rag-multi-domain-evolution)
 *
 * Purpose:
 * - Standardize domain routing across the orchestration system
 * - Enable proper QueryTypeDomainInterface implementation
 * - Facilitate domain capability checking and metrics collection
 * - Support dynamic domain registration for extensibility
 * - Enable domain-agnostic query processing
 *
 * Architecture Flow:
 * User Query → IntentAnalyzer → DomainRouter → QueryTypeDomain → Agent → Result
 *
 * Routing Strategy:
 * 1. Intent classification determines initial domain selection
 * 2. Domain capability checking validates routing decision
 * 3. Fallback to semantic domain if primary domain unavailable
 * 4. Hybrid domain for queries requiring multiple processing modes
  */
  
interface DomainRouterInterface
{
    /**
     * Get domain for intent type
     *
     * Routes queries to the appropriate query type domain based on intent classification.
     * Returns QueryTypeDomainInterface instance or class name (transitional)
     * for standardized access.
     *
     * Intent types:
     * - 'semantic': Queries requiring RAG knowledge base and vector search
     * - 'analytics': Queries requiring SQL generation and database analysis
     * - 'hybrid': Queries requiring multiple processing modes (semantic + analytics)
     * - 'web_search': Queries requiring external web search and real-time data
     *
     * Context may include:
     * - 'confidence': Classification confidence score (0.0-1.0)
     * - 'entity_type': Detected entity type (product, order, customer, etc.)
     * - 'language_id': Language ID for multilingual support
     * - 'user_id': User ID for personalization
     * - 'requires_web_search': Whether external data is needed
     * - 'requires_analytics': Whether database queries are needed
     * - 'requires_semantic': Whether RAG knowledge base is needed
     *
     * Fallback behavior:
     * - Unknown intent types default to semantic domain (safer than analytics)
     * - Logs warning when fallback occurs for monitoring
     *
     * Examples:
     * - Intent 'semantic' → SemanticAgent
     * - Intent 'analytics' → AnalyticsAgent
     * - Intent 'hybrid' → ActorCriticCoordinator
     * - Intent 'web_search' → WebSearchTool
     * - Intent 'unknown' → SemanticAgent (fallback)
     *
     * @param string $intentType Intent type from IntentAnalyzer
     * @param array $context Additional routing context
     * @return QueryTypeDomainInterface|string Domain instance or class name
     */
    public function getDomainForIntent(
        string $intentType,
        array $context = []
    ): QueryTypeDomainInterface|string;

    /**
     * Check if domain can handle query
     *
     * Determines whether a domain is capable of processing the given query
     * based on domain capabilities and query characteristics.
     *
     * This method is used by OrchestratorAgent to:
     * - Validate routing decisions before execution
     * - Determine if hybrid processing is needed
     * - Enable capability-based routing
     * - Prevent routing to unavailable domains
     *
     * Capability checks include:
     * - Domain availability (registered and initialized)
     * - Query type support (factual, analytical, comparative, etc.)
     * - Entity type support (product, order, customer, etc.)
     * - Operation support (search, aggregate, compare, etc.)
     * - Feature availability (caching, parallel execution, etc.)
     * - Resource availability (database, API, embeddings, etc.)
     *
     * Context may include:
     * - 'query_type': Classified query type
     * - 'entity_type': Detected entity type
     * - 'operations': Required operations
     * - 'features': Required features
     * - 'confidence': Classification confidence
     *
     * Examples:
     * - Semantic domain can handle: factual queries, product searches,
     *   similarity comparisons
     * - Analytics domain can handle: statistical queries, aggregations,
     *   revenue analysis
     * - Hybrid domain can handle: complex queries requiring multiple domains
     * - WebSearch domain can handle: external data queries, real-time information
     *
     * @param string $intentType Intent type to check
     * @param string $query User query to evaluate
     * @param array $context Query context for evaluation
     * @return bool True if domain can handle query, false otherwise
     */
    public function canDomainHandleQuery(
        string $intentType,
        string $query,
        array $context = []
    ): bool;

    /**
     * Get domain capabilities
     *
     * Returns information about what a domain can do.
     * Used for capability-based routing and feature availability checks.
     *
     * Capability structure:
     * [
     *     'available' => bool,            // Whether domain is available
     *     'domain_class' => string,       // Domain class name
     *     'query_types' => array,         // Supported query types
     *     'operations' => array,          // Supported operations
     *     'features' => array,            // Available features
     *     'entity_types' => array,        // Supported entity types
     *     'limitations' => array,         // Known limitations (optional)
     *     'dependencies' => array         // Required dependencies (optional)
     * ]
     *
     * Query types by domain:
     * - Semantic: ['factual', 'informational', 'similarity']
     * - Analytics: ['analytical', 'statistical', 'aggregation']
     * - Hybrid: ['complex', 'multi-faceted', 'comparative']
     * - WebSearch: ['external', 'real-time', 'market_data']
     *
     * Operations by domain:
     * - Semantic: ['search', 'retrieve', 'compare']
     * - Analytics: ['aggregate', 'analyze', 'calculate', 'filter']
     * - Hybrid: ['split', 'synthesize', 'coordinate']
     * - WebSearch: ['search', 'fetch', 'compare']
     *
     * Features by domain:
     * - Semantic: ['caching', 'embeddings', 'vector_search']
     * - Analytics: ['sql_generation', 'caching', 'validation']
     * - Hybrid: ['task_planning', 'parallel_execution', 'result_synthesis']
     * - WebSearch: ['external_api', 'caching', 'rate_limiting']
     *
     * Examples:
     * - getDomainCapabilities('semantic') → ['available' => true,
     *   'query_types' => ['factual', 'informational'], ...]
     * - getDomainCapabilities('analytics') → ['available' => true,
     *   'operations' => ['aggregate', 'analyze'], ...]
     * - getDomainCapabilities('unknown') → ['available' => false,
     *   'error' => 'Domain not found']
     *
     * @param string $intentType Intent type to get capabilities for
     * @return array Domain capabilities information
     */
    public function getDomainCapabilities(string $intentType): array;

    /**
     * Get domain metrics
     *
     * Returns performance and usage metrics for a domain.
     * Used for monitoring, optimization, and capacity planning.
     *
     * Metrics structure:
     * [
     *     'intent_type' => string,        // Intent type identifier
     *     'total_queries' => int,         // Total number of queries processed
     *     'successful_queries' => int,    // Number of successful queries
     *     'failed_queries' => int,        // Number of failed queries
     *     'avg_execution_time' => float,  // Average execution time in seconds
     *     'cache_hit_rate' => float,      // Cache hit rate (0.0-1.0)
     *     'note' => string                // Implementation status note (optional)
     * ]
     *
     * Future metrics (when implemented):
     * - 'avg_confidence' => float         // Average confidence score (0.0-1.0)
     * - 'error_rate' => float             // Error rate (0.0-1.0)
     * - 'last_execution' => string        // ISO 8601 timestamp of last execution
     * - 'uptime' => float                 // Uptime percentage (0.0-1.0)
     * - 'resource_usage' => array         // Resource usage metrics
     * - 'quality_metrics' => array        // Quality metrics (accuracy, relevance)
     *
     * Examples:
     * - getDomainMetrics('semantic') → ['total_queries' => 1000,
     *   'avg_execution_time' => 0.5, ...]
     * - getDomainMetrics('analytics') → ['total_queries' => 500,
     *   'cache_hit_rate' => 0.7, ...]
     * - getDomainMetrics('hybrid') → ['total_queries' => 200,
     *   'avg_execution_time' => 1.2, ...]
     *
     * @param string $intentType Intent type to get metrics for
     * @return array Domain metrics
     */
    public function getDomainMetrics(string $intentType): array;

    /**
     * Register custom domain
     *
     * Allows registration of custom domains at runtime.
     * Useful for testing, domain extensions, and dynamic domain loading.
     *
     * Registration requirements:
     * - Domain class must exist and be autoloadable
     * - Domain class should implement QueryTypeDomainInterface (recommended)
     * - Intent type must be unique (overwrites existing if duplicate)
     * - Domain class must be instantiable
     *
     * Use cases:
     * - Testing: Register mock domains for unit tests
     * - Extensions: Add custom domain types without modifying core
     * - Dynamic loading: Load domains from configuration
     * - Multi-tenant: Register tenant-specific domains
     *
     * Examples:
     * - registerDomain('custom_analytics', CustomAnalyticsAgent::class)
     * - registerDomain('finance', FinanceAgent::class)
     * - registerDomain('hr', HRAgent::class)
     * - registerDomain('test_semantic', MockSemanticAgent::class)
     *
     * @param string $intentType Intent type identifier (unique)
     * @param string $domainClass Domain class name (fully qualified)
     * @return void
     */
    public function registerDomain(string $intentType, string $domainClass): void;

    /**
     * Get all registered domains
     *
     * Returns list of all registered domains with their intent types.
     * Used for discovery, debugging, and monitoring.
     *
     * Return structure:
     * [
     *     'semantic' => SemanticAgent::class,
     *     'analytics' => AnalyticsAgent::class,
     *     'hybrid' => ActorCriticCoordinator::class,
     *     'web_search' => WebSearchTool::class,
     *     'custom_domain' => CustomAgent::class  // If registered
     * ]
     *
     * Use cases:
     * - Discovery: List available domains for UI display
     * - Debugging: Verify domain registration
     * - Monitoring: Track registered domains
     * - Documentation: Generate domain documentation
     * - Testing: Verify test domain registration
     *
     * Examples:
     * - getRegisteredDomains() → ['semantic' => SemanticAgent::class,
     *   'analytics' => AnalyticsAgent::class, ...]
     * - count(getRegisteredDomains()) → 4 (default domains)
     * - array_keys(getRegisteredDomains()) → ['semantic', 'analytics',
     *   'hybrid', 'web_search']
     *
     * @return array Domain map (intent_type => domain_class)
     */
    public function getRegisteredDomains(): array;
}
