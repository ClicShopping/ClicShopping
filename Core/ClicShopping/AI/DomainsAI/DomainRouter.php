<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\DomainsAI;

use ClicShopping\AI\InterfacesAI\DomainRouterInterface;
use ClicShopping\AI\InterfacesAI\QueryTypeDomainInterface;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\DomainsAI\Analytics\Agent\AnalyticsAgent;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCriticCoordinator;
use ClicShopping\AI\DomainsAI\WebSearch\Tool\WebSearchTool;

/**
 * DomainRouter Class
 *
 * Routes queries to appropriate query type domains based on intent classification.
 * Implements DomainRouterInterface for standardized domain access.
 *
 * Query Type Domains define HOW queries are processed:
 * - Semantic: Vector embeddings, similarity search
 * - Analytics: SQL generation, BI queries
 * - Hybrid: Combined semantic + analytics
 * - WebSearch: External web search
 *
 * Future: Will also coordinate with Business Domains (WHAT data is queried)
 * when rag-multi-domain-evolution spec is implemented.
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
 
class DomainRouter implements DomainRouterInterface
{
  private SecurityLogger $securityLogger;
  private bool $debug;
  private array $domainMap;
  private array $domainInstances;

  /**
   * Constructor
   *
   * Initializes the domain router with debug mode and domain mapping.
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
    $this->securityLogger = new SecurityLogger();
    $this->domainInstances = [];

    // Initialize domain mapping
    // Maps intent types to domain class names
    $this->domainMap = [
      'semantic' => SemanticAgent::class,
      'analytics' => AnalyticsAgent::class,
      'hybrid' => ActorCriticCoordinator::class,
      'web_search' => WebSearchTool::class,
    ];

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'DomainRouter', 'initialized', [
        'registered_domains' => array_keys($this->domainMap),
        'domain_count' => count($this->domainMap)
      ]);
    }
  }

  /**
   * Get domain for intent type
   *
   * Routes queries to the appropriate query type domain based on intent.
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
   * @return QueryTypeDomainInterface|string Domain instance or class name (transitional)
   */
  public function getDomainForIntent(string $intentType, array $context = []): QueryTypeDomainInterface|string
  {
    // Log routing request
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'DomainRouter', 'routing_request', [
        'intent_type' => $intentType,
        'context_provided' => !empty($context),
        'context_keys' => array_keys($context),
        'confidence' => $context['confidence'] ?? null,
        'entity_type' => $context['entity_type'] ?? null
      ]);
    }

    // Get domain class for intent type
    $domainClass = $this->domainMap[$intentType] ?? null;

    if ($domainClass === null) {
      // Log fallback decision with warning level
      $this->securityLogger->logStructured('warning', 'DomainRouter', 'domain_not_found', [
        'intent_type' => $intentType,
        'available_domains' => array_keys($this->domainMap),
        'fallback' => 'semantic',
        'reason' => 'Unknown intent type - using semantic domain as safe fallback',
        'context_keys' => array_keys($context)
      ]);

      // Fallback to semantic domain (safer than analytics)
      $domainClass = $this->domainMap['semantic'];
      $intentType = 'semantic'; // Update intent type for logging
    }

    // Log successful routing decision
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'DomainRouter', 'domain_routing', [
        'intent_type' => $intentType,
        'domain_class' => $domainClass,
        'routing_method' => 'domain_based',
        'context_keys' => array_keys($context),
        'is_fallback' => isset($context['is_fallback']) ? $context['is_fallback'] : false
      ]);
    }

    // NOTE: Current implementation returns class name for backward compatibility
    // Future implementation will return QueryTypeDomainInterface instance
    // when all domains implement the interface

    // TODO: Implement QueryTypeDomainInterface for all domains
    // TODO: Return domain instance instead of class name
    // Example future implementation:
    // return $this->getDomainInstance($domainClass, $context);

    return $domainClass;
  }

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
  public function canDomainHandleQuery(string $intentType, string $query, array $context = []): bool
  {
    $domainClass = $this->domainMap[$intentType] ?? null;

    if ($domainClass === null) {
      // Log domain not found
      $this->securityLogger->logStructured('warning', 'DomainRouter', 'capability_check_failed', [
        'intent_type' => $intentType,
        'reason' => 'domain_not_registered',
        'query_length' => strlen($query),
        'available_domains' => array_keys($this->domainMap)
      ]);
      
      return false;
    }

    // TODO: Implement canHandle() check when domains implement QueryTypeDomainInterface
    // For now, assume domain can handle if it exists in map

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'DomainRouter', 'capability_check', [
        'intent_type' => $intentType,
        'domain_class' => $domainClass,
        'can_handle' => true,
        'query_length' => strlen($query),
        'context_provided' => !empty($context),
        'context_keys' => array_keys($context)
      ]);
    }

    return true;
  }

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
  public function getDomainCapabilities(string $intentType): array
  {
    $domainClass = $this->domainMap[$intentType] ?? null;

    if ($domainClass === null) {
      // Log domain not found
      $this->securityLogger->logStructured('warning', 'DomainRouter', 'capabilities_request_failed', [
        'intent_type' => $intentType,
        'reason' => 'domain_not_registered',
        'available_domains' => array_keys($this->domainMap)
      ]);
      
      return [
        'available' => false,
        'error' => 'Domain not found'
      ];
    }

    // TODO: Implement getCapabilities() when domains implement QueryTypeDomainInterface
    // For now, return basic capabilities based on domain type

    $capabilities = match ($intentType) {
      'semantic' => [
        'query_types' => ['factual', 'informational', 'similarity'],
        'operations' => ['search', 'retrieve', 'compare'],
        'features' => ['caching', 'embeddings', 'vector_search'],
        'entity_types' => ['product', 'category', 'content']
      ],
      'analytics' => [
        'query_types' => ['analytical', 'statistical', 'aggregation'],
        'operations' => ['aggregate', 'analyze', 'calculate', 'filter'],
        'features' => ['sql_generation', 'caching', 'validation'],
        'entity_types' => ['product', 'order', 'customer', 'revenue']
      ],
      'hybrid' => [
        'query_types' => ['complex', 'multi-faceted', 'comparative'],
        'operations' => ['split', 'synthesize', 'coordinate'],
        'features' => ['task_planning', 'parallel_execution', 'result_synthesis'],
        'entity_types' => ['all']
      ],
      'web_search' => [
        'query_types' => ['external', 'real-time', 'market_data'],
        'operations' => ['search', 'fetch', 'compare'],
        'features' => ['external_api', 'caching', 'rate_limiting'],
        'entity_types' => ['external_data']
      ],
      default => []
    };

    $capabilities['available'] = true;
    $capabilities['domain_class'] = $domainClass;

    // Log capabilities retrieval
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'DomainRouter', 'capabilities_retrieved', [
        'intent_type' => $intentType,
        'domain_class' => $domainClass,
        'query_types_count' => count($capabilities['query_types'] ?? []),
        'operations_count' => count($capabilities['operations'] ?? []),
        'features_count' => count($capabilities['features'] ?? []),
        'entity_types_count' => count($capabilities['entity_types'] ?? [])
      ]);
    }

    return $capabilities;
  }

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
  public function getDomainMetrics(string $intentType): array
  {
    // Log metrics request
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'DomainRouter', 'metrics_requested', [
        'intent_type' => $intentType,
        'implementation_status' => 'placeholder'
      ]);
    }

    // TODO: Implement getMetrics() when domains implement QueryTypeDomainInterface
    // For now, return placeholder metrics

    return [
      'intent_type' => $intentType,
      'total_queries' => 0,
      'successful_queries' => 0,
      'failed_queries' => 0,
      'avg_execution_time' => 0.0,
      'cache_hit_rate' => 0.0,
      'note' => 'Metrics collection not yet implemented'
    ];
  }

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
  public function registerDomain(string $intentType, string $domainClass): void
  {
    $this->domainMap[$intentType] = $domainClass;

    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'DomainRouter', 'domain_registered', [
        'intent_type' => $intentType,
        'domain_class' => $domainClass
      ]);
    }
  }

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
  public function getRegisteredDomains(): array
  {
    // Log domain list request
    if ($this->debug) {
      $this->securityLogger->logStructured('info', 'DomainRouter', 'domains_list_requested', [
        'total_domains' => count($this->domainMap),
        'domain_types' => array_keys($this->domainMap)
      ]);
    }

    return $this->domainMap;
  }

  /**
   * Get domain instance (future implementation)
   *
   * Creates or retrieves cached domain instance.
   * Will be used when domains implement QueryTypeDomainInterface.
   *
   * @param string $domainClass Domain class name
   * @param array $context Initialization context
   * @return QueryTypeDomainInterface Domain instance
   * @throws \Exception When domain instance creation is not yet implemented
   */
  private function getDomainInstance(string $domainClass, array $context = []): QueryTypeDomainInterface
  {
    // Log attempt to get domain instance
    $this->securityLogger->logStructured('error', 'DomainRouter', 'domain_instance_not_implemented', [
      'domain_class' => $domainClass,
      'context_keys' => array_keys($context),
      'message' => 'Domain instance creation not yet implemented - returning class name instead'
    ]);

    // TODO: Implement domain instance caching
    // TODO: Implement domain initialization with context

    throw new \Exception('Domain instance creation not yet implemented');
  }
}
