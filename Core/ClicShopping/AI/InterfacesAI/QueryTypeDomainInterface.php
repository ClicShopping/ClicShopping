<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * QueryTypeDomainInterface
 *
 * Interface for query type domains (Semantic, Analytics, Hybrid, WebSearch).
 * This interface defines the contract that all query type domains must implement.
 *
 * IMPORTANT DISTINCTION:
 * - Query Type Domains (THIS INTERFACE): Define HOW queries are processed
 *   Examples: Semantic search, SQL generation, hybrid processing, web search
 *   Location: Core/ClicShopping/AI/DomainsAI
 *
 * - Business Domains (FUTURE - Apps/): Define WHAT data is queried
 *   Examples: Ecommerce (products, orders), Finance (transactions), HR (employees)
 *   Location: Core/ClicShopping/AI/Apps/ (future spec: rag-multi-domain-evolution)
 *
 * Purpose:
 * - Standardize query type domain structure across all domains
 * - Enable consistent orchestration and routing
 * - Facilitate domain-specific capabilities and metrics
 * - Support autonomous agent architecture (future spec: agent-local-objectives-evaluation)
 *
 * Architecture Flow:
 * User Query → OrchestratorAgent → QueryTypeDomain → Agent → Processor → Executor → Result
 *
 * Domain Structure:
 * Each query type domain contains:
 * - Agent/: Entry point for domain queries
 * - Executor/: Executes domain-specific operations
 * - Processor/: Processes and transforms data
 * - Cache/: Domain-specific caching
 * - Helper/: Domain-specific utilities
 *
 * @package ClicShopping\AI\InterfacesAI
 * @version 1.0.0
 * @since 2026-01-17
 */
interface QueryTypeDomainInterface
{
  /**
   * Get the domain name
   *
   * Returns the unique identifier for this query type domain.
   * Valid domain names: 'semantic', 'analytics', 'hybrid', 'websearch', 'coreai'
   *
   * This name is used for:
   * - Routing queries to the appropriate domain
   * - Logging and monitoring
   * - Configuration lookup
   * - Metrics tracking
   *
   * Examples:
   * - Semantic domain: 'semantic'
   * - Analytics domain: 'analytics'
   * - Hybrid domain: 'hybrid'
   * - WebSearch domain: 'websearch'
   *
   * @return string Domain name (lowercase, alphanumeric)
   */
  public function getName(): string;

  /**
   * Get the domain agent
   *
   * Returns the agent responsible for processing queries in this domain.
   * The agent is the entry point for all domain operations.
   *
   * Agent responsibilities:
   * - Receive queries from OrchestratorAgent
   * - Coordinate processors and executors
   * - Return standardized AgentResponseInterface
   * - Handle domain-specific errors
   * - Manage domain-specific caching
   *
   * Examples:
   * - Semantic domain: SemanticAgent
   * - Analytics domain: AnalyticsAgent
   * - Hybrid domain: HybridAgent
   * - WebSearch domain: WebSearchAgent
   *
   * @return object Domain agent instance
   */
  public function getAgent(): object;

  /**
   * Check if domain can handle the query
   *
   * Determines whether this domain is capable of processing the given query
   * based on the query content and context.
   *
   * This method is used by OrchestratorAgent to:
   * - Route queries to the appropriate domain
   * - Determine if hybrid processing is needed
   * - Validate domain capabilities before execution
   *
   * Context may include:
   * - 'intent': Query intent classification (from UnifiedQueryAnalyzer)
   * - 'entity_type': Detected entity type (product, order, customer, etc.)
   * - 'language_id': Language ID for multilingual support
   * - 'user_id': User ID for personalization
   * - 'confidence': Classification confidence score
   * - 'requires_web_search': Whether external data is needed
   * - 'requires_analytics': Whether database queries are needed
   * - 'requires_semantic': Whether RAG knowledge base is needed
   *
   * Examples:
   * - Semantic domain: Returns true for queries requiring RAG knowledge base
   * - Analytics domain: Returns true for queries requiring SQL generation
   * - Hybrid domain: Returns true for queries requiring multiple domains
   * - WebSearch domain: Returns true for queries requiring external data
   *
   * @param string $query The user query to evaluate
   * @param array $context Additional context for evaluation
   * @return bool True if domain can handle the query, false otherwise
   */
  public function canHandle(string $query, array $context = []): bool;

  /**
   * Get domain capabilities
   *
   * Returns information about what this domain can do.
   * This is used for:
   * - Documentation and discovery
   * - Capability-based routing
   * - Feature availability checks
   * - UI feature toggles
   *
   * Capability structure:
   * [
   *     'query_types' => array,         // Supported query types: ['factual', 'analytical', 'comparative', etc.]
   *     'entity_types' => array,        // Supported entity types: ['product', 'order', 'customer', etc.]
   *     'operations' => array,          // Supported operations: ['search', 'aggregate', 'compare', etc.]
   *     'features' => array,            // Available features: ['caching', 'parallel_execution', 'fallback', etc.]
   *     'limitations' => array,         // Known limitations: ['max_results' => 100, 'timeout' => 30, etc.]
   *     'dependencies' => array,        // Required dependencies: ['database', 'embedding_model', 'web_api', etc.]
   *     'performance' => array          // Performance characteristics: ['avg_latency' => 0.5, 'cache_hit_rate' => 0.8, etc.]
   * ]
   *
   * Examples:
   * - Semantic domain: ['query_types' => ['factual', 'informational'], 'operations' => ['search', 'retrieve']]
   * - Analytics domain: ['query_types' => ['analytical', 'statistical'], 'operations' => ['aggregate', 'analyze']]
   * - Hybrid domain: ['query_types' => ['complex', 'multi-faceted'], 'operations' => ['split', 'synthesize']]
   * - WebSearch domain: ['query_types' => ['external', 'real-time'], 'operations' => ['search', 'fetch']]
   *
   * @return array Domain capabilities information
   */
  public function getCapabilities(): array;

  /**
   * Get domain metrics
   *
   * Returns performance and usage metrics for this domain.
   * This is used for:
   * - Monitoring and alerting
   * - Performance optimization
   * - Capacity planning
   * - Quality assessment
   * - Inter-agent evaluation (future spec: agent-local-objectives-evaluation)
   *
   * Metrics structure:
   * [
   *     'total_queries' => int,         // Total number of queries processed
   *     'successful_queries' => int,    // Number of successful queries
   *     'failed_queries' => int,        // Number of failed queries
   *     'avg_execution_time' => float,  // Average execution time in seconds
   *     'cache_hit_rate' => float,      // Cache hit rate (0.0-1.0)
   *     'avg_confidence' => float,      // Average confidence score (0.0-1.0)
   *     'error_rate' => float,          // Error rate (0.0-1.0)
   *     'last_execution' => string,     // ISO 8601 timestamp of last execution
   *     'uptime' => float,              // Uptime percentage (0.0-1.0)
   *     'resource_usage' => array,      // Resource usage: ['memory' => bytes, 'cpu' => percentage]
   *     'quality_metrics' => array      // Quality metrics: ['accuracy' => 0.9, 'relevance' => 0.85, etc.]
   * ]
   *
   * Examples:
   * - Semantic domain: ['total_queries' => 1000, 'avg_confidence' => 0.85, 'cache_hit_rate' => 0.7]
   * - Analytics domain: ['total_queries' => 500, 'avg_execution_time' => 0.3, 'error_rate' => 0.05]
   * - Hybrid domain: ['total_queries' => 200, 'avg_execution_time' => 1.2, 'cache_hit_rate' => 0.5]
   * - WebSearch domain: ['total_queries' => 300, 'avg_execution_time' => 2.0, 'error_rate' => 0.1]
   *
   * @return array Domain metrics
   */
  public function getMetrics(): array;
}
