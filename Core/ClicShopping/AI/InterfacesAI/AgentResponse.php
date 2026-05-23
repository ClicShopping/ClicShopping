<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * AgentResponse
 *
 * Concrete implementation of AgentResponseInterface.
 * This class provides a standardized way to build and return agent responses.
 *
 * Usage Example:
 * ```php
 * use ClicShopping\AI\InterfacesAI\AgentResponse;
 *
 * // Create a successful analytics response
 * $response = AgentResponse::success('analytics', $query)
 *     ->setResult([
 *         'sql_query' => $sql,
 *         'columns' => $columns,
 *         'rows' => $rows,
 *         'row_count' => count($rows),
 *         'interpretation' => $interpretation
 *     ])
 *     ->setSourceAttribution([
 *         'primary_source' => 'Analytics Database',
 *         'icon' => '📊',
 *         'details' => ['table' => 'clic_products'],
 *         'confidence' => 0.9
 *     ])
 *     ->setMetadata([
 *         'entity_id' => $productId,
 *         'entity_type' => 'product',
 *         'execution_time' => $executionTime
 *     ]);
 *
 * // Create an error response
 * $response = AgentResponse::error('analytics', $query, 'SQL execution failed');
 *
 * // Convert to array for downstream processing
 * $responseArray = $response->toArray();
 * ```
 *
 * @package ClicShopping\AI\InterfacesAI
 * @version 1.0.0
 * @since 2025-11-14
 */
class AgentResponse implements AgentResponseInterface
{
  private bool $success;
  private string $type;
  private string $query;
  private array $result;
  private array $sourceAttribution;
  private array $metadata;
  private ?string $error;

  /**
   * Constructor
   *
   * @param bool $success Success status
   * @param string $type Query type ('analytics', 'semantic', 'web_search', 'hybrid')
   * @param string $query Original query text
   * @param array $result Result data
   * @param array $sourceAttribution Source attribution information
   * @param array $metadata Metadata
   * @param string|null $error Error message if any
   */
  public function __construct(
    bool $success,
    string $type,
    string $query,
    array $result = [],
    array $sourceAttribution = [],
    array $metadata = [],
    ?string $error = null
  ) {
    $this->success = $success;
    $this->type = $type;
    $this->query = $query;
    $this->result = $result;
    $this->sourceAttribution = $sourceAttribution;
    $this->metadata = $metadata;
    $this->error = $error;

    // Add timestamp to metadata if not present
    if (!isset($this->metadata['timestamp'])) {
      $this->metadata['timestamp'] = date('c'); // ISO 8601 format
    }
  }

  /**
   * Create a successful response
   *
   * @param string $type Query type
   * @param string $query Original query
   * @return self
   */
  public static function success(string $type, string $query): self
  {
    return new self(true, $type, $query);
  }

  /**
   * Create an error response
   *
   * @param string $type Query type
   * @param string $query Original query
   * @param string $error Error message
   * @return self
   */
  public static function error(string $type, string $query, string $error): self
  {
    return new self(false, $type, $query, [], [], [], $error);
  }

  /**
   * Set the result data
   *
   * @param array $result Result data
   * @return self
   */
  public function setResult(array $result): self
  {
    $this->result = $result;
    return $this;
  }

  /**
   * Set source attribution
   *
   * @param array $sourceAttribution Source attribution information
   * @return self
   */
  public function setSourceAttribution(array $sourceAttribution): self
  {
    $this->sourceAttribution = $sourceAttribution;
    return $this;
  }

  /**
   * Set metadata
   *
   * @param array $metadata Metadata
   * @return self
   */
  public function setMetadata(array $metadata): self
  {
    $this->metadata = array_merge($this->metadata, $metadata);
    return $this;
  }

  /**
   * Add a single metadata field
   *
   * @param string $key Metadata key
   * @param mixed $value Metadata value
   * @return self
   */
  public function addMetadata(string $key, $value): self
  {
    $this->metadata[$key] = $value;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function toArray(): array
  {
    return [
      'success' => $this->success,
      'type' => $this->type,
      'query' => $this->query,
      'result' => $this->result,
      'source_attribution' => $this->sourceAttribution,
      'metadata' => $this->metadata,
      'error' => $this->error
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getType(): string
  {
    return $this->type;
  }

  /**
   * {@inheritDoc}
   */
  public function getResult(): array
  {
    return $this->result;
  }

  /**
   * {@inheritDoc}
   */
  public function getSourceAttribution(): array
  {
    return $this->sourceAttribution;
  }

  /**
   * {@inheritDoc}
   */
  public function getMetadata(): array
  {
    return $this->metadata;
  }

  /**
   * {@inheritDoc}
   */
  public function isSuccess(): bool
  {
    return $this->success;
  }

  /**
   * {@inheritDoc}
   */
  public function getError(): ?string
  {
    return $this->error;
  }

  /**
   * {@inheritDoc}
   */
  public function getQuery(): string
  {
    return $this->query;
  }

  /**
   * Create source attribution for analytics queries
   *
   * Helper method to create standardized source attribution for analytics queries.
   *
   * @param string $tableName Database table name
   * @param float $confidence Confidence score (0.0-1.0)
   * @return array Source attribution array
   */
  public static function createAnalyticsSourceAttribution(string $tableName, float $confidence = 0.9): array
  {
    return [
      'primary_source' => 'Analytics Database',
      'icon' => '📊',
      'details' => ['table' => $tableName],
      'confidence' => $confidence
    ];
  }

  /**
   * Create source attribution for semantic queries
   *
   * Helper method to create standardized source attribution for semantic queries.
   *
   * @param int $documentCount Number of documents retrieved
   * @param float $confidence Confidence score (0.0-1.0)
   * @return array Source attribution array
   */
  public static function createSemanticSourceAttribution(int $documentCount, float $confidence = 0.85): array
  {
    return [
      'primary_source' => 'RAG Knowledge Base',
      'icon' => '📚',
      'details' => ['document_count' => $documentCount],
      'confidence' => $confidence
    ];
  }

  /**
   * Create source attribution for web search queries
   *
   * Helper method to create standardized source attribution for web search queries.
   *
   * @param int $urlCount Number of URLs retrieved
   * @param float $confidence Confidence score (0.0-1.0)
   * @return array Source attribution array
   */
  public static function createWebSearchSourceAttribution(int $urlCount, float $confidence = 0.7): array
  {
    return [
      'primary_source' => 'Web Search',
      'icon' => '🌐',
      'details' => ['url_count' => $urlCount],
      'confidence' => $confidence
    ];
  }

  /**
   * Create source attribution for LLM fallback
   *
   * Helper method to create standardized source attribution for LLM fallback.
   *
   * @param float $confidence Confidence score (0.0-1.0)
   * @return array Source attribution array
   */
  public static function createLLMSourceAttribution(float $confidence = 0.5): array
  {
    return [
      'primary_source' => 'LLM',
      'icon' => '🤖',
      'details' => ['fallback' => true],
      'confidence' => $confidence
    ];
  }

  /**
   * Create source attribution for hybrid queries
   *
   * Helper method to create standardized source attribution for hybrid queries.
   *
   * @param array $sources List of sources used (e.g., ['analytics', 'semantic'])
   * @param float $confidence Confidence score (0.0-1.0)
   * @return array Source attribution array
   */
  public static function createHybridSourceAttribution(array $sources, float $confidence = 0.8): array
  {
    return [
      'primary_source' => 'Mixed',
      'icon' => '🔀',
      'details' => ['sources' => $sources],
      'confidence' => $confidence
    ];
  }
}
