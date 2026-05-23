<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * HybridQueryHandlerInterface
 *
 * Interface for hybrid query handling in the AI orchestration system.
 * This interface defines the contract for processing queries that require multiple
 * processing modes (semantic + analytics, semantic + web search, etc.).
 *
 * IMPORTANT DISTINCTION:
 * - HybridQueryHandlerInterface: Orchestration-level handler for hybrid queries
 *   Coordinates TaskPlanner, PlanExecutor, and result synthesis
 *   Location: Core/ClicShopping/AI/DomainsAI/Hybrid/Handler/
 *
 * - HybridQueryProcessorInterface: Component-level interface for hybrid query components
 *   Used by QueryClassifier, QuerySplitter, ResultSynthesizer, etc.
 *   Location: Core/ClicShopping/AI/InterfacesAI/
 *
 * Purpose:
 * - Standardize hybrid query handling across the orchestration system
 * - Enable proper separation of hybrid query logic from OrchestratorAgent
 * - Facilitate query decomposition via TaskPlanner
 * - Support plan execution via PlanExecutor
 * - Enable result synthesis from multiple sources
 * - Support LLM-based web search detection (domain-agnostic)
 *
 * Architecture Flow:
 * User Query → IntentAnalyzer → OrchestratorAgent → HybridQueryHandler
 *   → TaskPlanner → PlanExecutor → ResultSynthesizer → Final Result
 *
 * Hybrid Query Processing Strategy:
 * 1. Receive hybrid query with intent and context
 * 2. Use TaskPlanner to decompose query into sub-tasks
 * 3. Execute each sub-task with appropriate domain agent
 * 4. Synthesize results from all sub-tasks
 * 5. Force result type to 'hybrid' for consistency
 * 6. Store result in memory for conversation continuity
 *
 * Web Search Detection:
 * - MUST use LLM-based classification via IntentAnalyzer
 * - MUST NOT use hardcoded keyword lists in Core AI
 * - Domain-specific keywords loaded dynamically from Apps/AI/{Domain}/
 * - Supports multiple business domains (Ecommerce, HR, Finance, Trading)
 */
 
interface HybridQueryHandlerInterface
{
  /**
   * Handle hybrid query using Actor-Critic approach
   *
   * Processes queries with multiple intents by:
   * 1. Using TaskPlanner to decompose the query into sub-tasks
   * 2. Executing each sub-task with appropriate domain agent
   * 3. Synthesizing results from all sub-tasks
   * 4. Forcing result type to 'hybrid' for consistency
   * 5. Storing result in memory for conversation continuity
   *
   * This method replaces the deprecated HybridQueryProcessor and provides
   * a cleaner separation of concerns between orchestration and execution.
   *
   * Query decomposition examples:
   * - "Compare our sales with market trends" →
   *   [Analytics: Get sales data, WebSearch: Get market trends, Synthesize: Compare]
   * - "Find products similar to X and show revenue" →
   *   [Semantic: Find similar products, Analytics: Get revenue, Synthesize: Combine]
   * - "What are top products and their reviews?" →
   *   [Analytics: Get top products, Semantic: Get reviews, Synthesize: Merge]
   *
   * Intent structure:
   * [
   *     'type' => 'hybrid',                 // Intent type (always 'hybrid')
   *     'confidence' => 0.85,               // Classification confidence (0.0-1.0)
   *     'sub_types' => ['analytics', 'semantic'], // Sub-intent types
   *     'requires_web_search' => false,     // Whether external data is needed
   *     'entity_type' => 'product',         // Detected entity type
   *     'language_id' => 1                  // Language ID
   * ]
   *
   * Context structure:
   * [
   *     'user_id' => 123,                   // User ID for personalization
   *     'language_id' => 1,                 // Language ID for multilingual support
   *     'conversation_id' => 'abc123',      // Conversation ID for memory
   *     'entity_type' => 'product',         // Entity type for routing
   *     'last_entity' => ['id' => 456],     // Last referenced entity
   *     'cache_key' => 'query_hash',        // Cache key for result caching
   *     'debug' => false                    // Debug mode flag
   * ]
   *
   * Result structure:
   * [
   *     'success' => true,                  // Whether execution succeeded
   *     'type' => 'hybrid',                 // Result type (forced to 'hybrid')
   *     'intent' => [...],                  // Original intent analysis
   *     'context' => [...],                 // Query context
   *     'result' => [...],                  // Synthesized result data
   *     'execution_time' => 1.234,          // Total execution time in seconds
   *     'plan' => [...],                    // Execution plan details
   *     'steps' => [...],                   // Executed steps with results
   *     'metadata' => [...]                 // Additional metadata
   * ]
   *
   * Error handling:
   * - Throws \Exception if plan creation fails
   * - Throws \Exception if plan execution fails
   * - Logs all errors with SecurityLogger
   * - Returns error result structure on failure
   *
   * Performance considerations:
   * - Parallel execution of independent sub-tasks
   * - Result caching for repeated queries
   * - Performance tracking with markers
   * - Metrics collection for monitoring
   *
   * Examples:
   * - handleHybridQuery("Compare sales with trends",
   *   ['type' => 'hybrid', 'sub_types' => ['analytics', 'web_search']], [], 1234567890.123)
   *   → ['success' => true, 'type' => 'hybrid', 'result' => [...]]
   *
   * - handleHybridQuery("Find similar products and revenue",
   *   ['type' => 'hybrid', 'sub_types' => ['semantic', 'analytics']], [], 1234567890.123)
   *   → ['success' => true, 'type' => 'hybrid', 'result' => [...]]
   *
   * @param string $queryToProcess Original user query
   * @param array $intent Intent analysis from IntentAnalyzer
   * @param array $context Query context (user, language, conversation, etc.)
   * @param float $startTime Query start time (microtime(true))
   * @return array Synthesized result with type forced to 'hybrid'
   * @throws \Exception If plan creation or execution fails
   */
  public function handleHybridQuery(
    string $queryToProcess,
    array $intent,
    array $context,
    float $startTime
  ): array;

  /**
   * Check if query requires web search
   *
   * Determines whether a query requires external web search based on
   * LLM-based intent classification (NOT keyword matching).
   *
   * CRITICAL ARCHITECTURE RULE:
   * - Core AI MUST be domain-agnostic
   * - NO hardcoded keyword lists in Core/ClicShopping/AI/
   * - Domain-specific keywords loaded dynamically from Apps/AI/{Domain}/
   * - Use LLM classification via IntentAnalyzer for detection
   *
   * Detection strategy:
   * 1. Primary: Check intent['requires_web_search'] from IntentAnalyzer
   * 2. Secondary: Load domain-specific keywords dynamically (if configured)
   * 3. Fallback: Use LLM classification for unknown patterns
   *
   * Intent structure:
   * [
   *     'type' => 'hybrid',                 // Intent type
   *     'requires_web_search' => true,      // LLM-based detection result
   *     'confidence' => 0.85,               // Classification confidence
   *     'sub_types' => ['analytics', 'web_search'], // Sub-intent types
   *     'web_search_reason' => 'real-time data needed' // Explanation
   * ]
   *
   * Web search indicators (detected by LLM):
   * - Real-time information requests ("latest", "current", "today")
   * - External data requests ("market trends", "competitor prices")
   * - Comparison with external sources ("compare with Amazon")
   * - Information not in knowledge base ("new product X")
   *
   * Domain-specific keyword loading (optional):
   * - Ecommerce: Load from Apps/AI/Ecommerce/Classes/.../Patterns/HybridPreFilter.php
   * - HR: Load from Apps/AI/HR/Classes/.../Patterns/HybridPreFilter.php
   * - Finance: Load from Apps/AI/Finance/Classes/.../Patterns/HybridPreFilter.php
   * - Trading: Load from Apps/AI/Trading/Classes/.../Patterns/HybridPreFilter.php
   *
   * Examples:
   * - requiresWebSearch("Compare our sales with market trends",
   *   ['requires_web_search' => true]) → true
   * - requiresWebSearch("What are top products by revenue?",
   *   ['requires_web_search' => false]) → false
   * - requiresWebSearch("Find latest iPhone prices on Amazon",
   *   ['requires_web_search' => true]) → true
   *
   * @param string $query User query to evaluate
   * @param array $intent Intent analysis from IntentAnalyzer
   * @return bool True if web search is required, false otherwise
   */
  public function requiresWebSearch(string $query, array $intent): bool;

  /**
   * Get hybrid query handler statistics
   *
   * Returns performance and usage statistics for the hybrid query handler.
   * Used for monitoring, optimization, and capacity planning.
   *
   * Statistics structure:
   * [
   *     'total_queries' => int,             // Total number of hybrid queries processed
   *     'successful_queries' => int,        // Number of successful queries
   *     'failed_queries' => int,            // Number of failed queries
   *     'avg_execution_time' => float,      // Average execution time in seconds
   *     'avg_plan_steps' => float,          // Average number of plan steps
   *     'web_search_queries' => int,        // Number of queries requiring web search
   *     'cache_hit_rate' => float,          // Cache hit rate (0.0-1.0)
   *     'sub_type_distribution' => array,   // Distribution of sub-intent types
   *     'error_rate' => float,              // Error rate (0.0-1.0)
   *     'last_execution' => string          // ISO 8601 timestamp of last execution
   * ]
   *
   * Sub-type distribution structure:
   * [
   *     'semantic' => int,                  // Number of semantic sub-tasks
   *     'analytics' => int,                 // Number of analytics sub-tasks
   *     'web_search' => int,                // Number of web search sub-tasks
   *     'other' => int                      // Number of other sub-tasks
   * ]
   *
   * Use cases:
   * - Monitoring: Track hybrid query performance
   * - Optimization: Identify bottlenecks
   * - Capacity planning: Predict resource needs
   * - Debugging: Analyze failure patterns
   * - Reporting: Generate usage reports
   *
   * Examples:
   * - getStats() → ['total_queries' => 1000, 'avg_execution_time' => 1.5,
   *   'web_search_queries' => 200, ...]
   * - getStats()['error_rate'] → 0.05 (5% error rate)
   * - getStats()['sub_type_distribution'] → ['semantic' => 500, 'analytics' => 300,
   *   'web_search' => 200]
   *
   * @return array Hybrid query handler statistics
   */
  public function getStats(): array;
}
