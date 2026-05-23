<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * AgentResponseInterface
 *
 * Standardized interface for all agent responses in the RAG system.
 * This interface ensures consistent response format across all agents
 * (AnalyticsAgent, SemanticAgent, WebSearchAgent, HybridQueryProcessor, etc.)
 *
 * Purpose:
 * - Standardize response structure for all agents
 * - Enable consistent handling in ResultFormatter
 * - Facilitate source attribution tracking
 * - Support metadata propagation through the pipeline
 *
 * Response Flow:
 * Query → Agent → AgentResponseInterface → ResultFormatter → Display
 *
 * @package ClicShopping\AI\InterfacesAI
 * @version 1.0.0
 * @since 2025-11-14
 */
interface AgentResponseInterface
{
  /**
   * Convert the response to an array format
   *
   * Returns a standardized array structure that can be consumed by
   * ResultFormatter and other downstream components.
   *
   * Standard format:
   * [
   *     'success' => bool,              // Whether the query was successful
   *     'type' => string,               // Query type: 'analytics', 'semantic', 'web_search', 'hybrid'
   *     'query' => string,              // Original query text
   *     'result' => array,              // Type-specific result structure (see below)
   *     'source_attribution' => array,  // Source information for display
   *     'metadata' => array,            // Additional metadata (entity_id, execution_time, etc.)
   *     'error' => string|null          // Error message if success is false
   * ]
   *
   * Type-specific result structures:
   *
   * Analytics ('analytics'):
   * [
   *     'sql_query' => string,          // Generated SQL query
   *     'columns' => array,             // Column names
   *     'rows' => array,                // Result rows
   *     'row_count' => int,             // Number of rows
   *     'interpretation' => string      // Human-readable interpretation
   * ]
   *
   * Semantic ('semantic'):
   * [
   *     'documents' => array,           // Retrieved documents with content and scores
   *     'summary' => string,            // LLM-generated summary
   *     'document_count' => int,        // Number of documents retrieved
   *     'similarity_threshold' => float // Threshold used for retrieval
   * ]
   *
   * Web Search ('web_search'):
   * [
   *     'product_info' => array,        // Internal product information
   *     'external_results' => array,    // SERAPI results
   *     'price_comparison' => array,    // Price comparison data
   *     'urls' => array                 // External URLs
   * ]
   *
   * Hybrid ('hybrid'):
   * [
   *     'sub_queries' => array,         // Array of sub-query results
   *     'synthesis' => string,          // Combined synthesis text
   *     'sources_used' => array         // List of sources used
   * ]
   *
   * @return array Standardized response array
   */
  public function toArray(): array;

  /**
   * Get the query type
   *
   * Returns the type of query that was processed.
   * Valid types: 'analytics', 'semantic', 'web_search', 'hybrid'
   *
   * @return string Query type
   */
  public function getType(): string;

  /**
   * Get the result data
   *
   * Returns the type-specific result structure.
   * The structure varies based on query type (see toArray() documentation).
   *
   * @return array Result data
   */
  public function getResult(): array;

  /**
   * Get source attribution information
   *
   * Returns information about the data sources used to generate the response.
   * This is displayed in the UI to show users where the information came from.
   *
   * Format:
   * [
   *     'primary_source' => string,     // Main source: 'RAG Knowledge Base', 'Analytics Database', 'Web Search', 'LLM', 'Mixed'
   *     'icon' => string,               // Icon for display: '📚', '📊', '🌐', '🤖', '🔀'
   *     'details' => array,             // Additional details (table names, document count, URLs, etc.)
   *     'confidence' => float           // Confidence score (0.0-1.0)
   * ]
   *
   * Examples:
   * - Analytics: ['primary_source' => 'Analytics Database', 'icon' => '📊', 'details' => ['table' => 'clic_products'], 'confidence' => 0.9]
   * - Semantic: ['primary_source' => 'RAG Knowledge Base', 'icon' => '📚', 'details' => ['document_count' => 3], 'confidence' => 0.85]
   * - Web Search: ['primary_source' => 'Web Search', 'icon' => '🌐', 'details' => ['url_count' => 5], 'confidence' => 0.7]
   * - LLM: ['primary_source' => 'LLM', 'icon' => '🤖', 'details' => ['fallback' => true], 'confidence' => 0.5]
   * - Hybrid: ['primary_source' => 'Mixed', 'icon' => '🔀', 'details' => ['sources' => ['analytics', 'semantic']], 'confidence' => 0.8]
   *
   * @return array Source attribution information
   */
  public function getSourceAttribution(): array;

  /**
   * Get metadata
   *
   * Returns additional metadata about the query execution.
   * This information is used for logging, debugging, and analytics.
   *
   * Common metadata fields:
   * [
   *     'entity_id' => int|null,        // Entity ID if applicable (product_id, category_id, etc.)
   *     'entity_type' => string|null,   // Entity type (product, category, order, etc.)
   *     'execution_time' => float,      // Execution time in seconds
   *     'timestamp' => string,          // ISO 8601 timestamp
   *     'language_id' => int,           // Language ID used
   *     'user_id' => string,            // User ID who made the query
   *     'confidence_score' => float,    // Overall confidence score
   *     'classification_reasoning' => array, // Why this type was chosen
   *     'cache_hit' => bool,            // Whether result was from cache
   *     'feedback_influenced' => bool,  // Whether feedback influenced the result
   *     'memory_context_used' => bool   // Whether conversation memory was used
   * ]
   *
   * @return array Metadata
   */
  public function getMetadata(): array;

  /**
   * Check if the response was successful
   *
   * Returns true if the query was processed successfully,
   * false if there was an error.
   *
   * @return bool Success status
   */
  public function isSuccess(): bool;

  /**
   * Get error message if any
   *
   * Returns the error message if the query failed,
   * or null if the query was successful.
   *
   * @return string|null Error message or null
   */
  public function getError(): ?string;

  /**
   * Get the original query text
   *
   * Returns the original query string that was processed.
   *
   * @return string Query text
   */
  public function getQuery(): string;
}
