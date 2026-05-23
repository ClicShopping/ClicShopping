<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Config\DomainConfig;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * EntityMatcher Class
 *
 * Responsible for extracting and matching entities in queries and content using LLM.
 * This helps prevent context pollution by ensuring retrieved content matches
 * the specific entities mentioned in the query.
 *
 * PURE LLM MODE: Uses LLM for entity extraction and matching, not pattern matching.
 *
 * Example: When querying "article 4 des cgv", this class ensures we don't
 * return content about "article 3 des cgv" even if they're semantically similar.
 */
class EntityMatcher
{
  private SecurityLogger $logger;
  private bool $debug;
  private mixed $chat;
  private $language;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
    $this->logger = new SecurityLogger();
    
    $this->chat = Gpt::getChatForModel();
    
    // Load language definitions
    $this->language = Registry::get('Language');
    DomainConfig::loadLanguageFile('rag_entity_matcher');

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "EntityMatcher initialized (Pure LLM Mode)",
        'info'
      );
    }
  }

  /**
   * Extract entities from a query using LLM
   *
   * Uses LLM to identify specific entities mentioned in the query.
   * PURE LLM MODE: No pattern matching, only LLM-based extraction.
   *
   * @param string $query Query text
   * @param string $domain Domain context (optional, for future use)
   * @return array Array of extracted entities with type and value
   */
  public function extractEntities(string $query, string $domain = 'Ecommerce'): array
  {
    try {
      // Get prompt from language file
      $prompt = CLICSHOPPING::getDef('prompt_extract_entities', [
        'query' => $query
      ]);

      $response = $this->chat->generateText($prompt);

      // Parse JSON response
      $entities = json_decode($response, true);

      if (!is_array($entities)) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "LLM entity extraction returned invalid JSON: " . substr($response, 0, 200),
            'warning'
          );
        }
        return [];
      }

      if ($this->debug && !empty($entities)) {
        $this->logger->logSecurityEvent(
          "LLM extracted " . count($entities) . " entities from query: " . json_encode($entities),
          'info'
        );
      }

      return $entities;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error extracting entities with LLM: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }

  /**
   * Check if content matches the extracted entities using LLM
   *
   * Uses LLM to determine if the content contains references to the same entities
   * mentioned in the query. This prevents returning content about different
   * entities even if they're semantically similar.
   *
   * PURE LLM MODE: No pattern matching, only LLM-based matching.
   *
   * @param string $content Content to check
   * @param array $queryEntities Entities extracted from the query
   * @param string $domain Domain context (optional, for future use)
   * @return bool True if content matches entities, false otherwise
   */
  public function contentMatchesEntities(string $content, array $queryEntities, string $domain = 'Ecommerce'): bool
  {
    // If no entities were extracted, don't filter (allow all content)
    if (empty($queryEntities)) {
      return true;
    }

    try {
      $entitiesJson = json_encode($queryEntities);
      $contentPreview = mb_substr($content, 0, 1000); // Limit content length for LLM

      // Get prompt from language file
      $prompt = CLICSHOPPING::getDef('prompt_match_entities', [
        'entities' => $entitiesJson,
        'content' => $contentPreview
      ]);

      $response = $this->chat->generateText($prompt);

      $matches = (stripos(trim($response), 'YES') === 0);

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "LLM entity matching: " . ($matches ? "MATCH" : "NO MATCH") . " (response: {$response})",
          'info'
        );
      }

      return $matches;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error matching entities with LLM: " . $e->getMessage(),
        'error'
      );
      // On error, allow the content (fail open to avoid blocking valid results)
      return true;
    }
  }

  /**
   * Filter documents by entity matching
   *
   * Filters an array of documents to only include those that match
   * the entities extracted from the query.
   *
   * @param array $documents Array of Document objects
   * @param array $queryEntities Entities extracted from the query
   * @param string $domain Domain context (optional, for future use)
   * @return array Filtered array of documents
   */
  public function filterDocumentsByEntities(array $documents, array $queryEntities, string $domain = 'Ecommerce'): array
  {
    // If no entities, return all documents
    if (empty($queryEntities)) {
      return $documents;
    }

    $filtered = [];
    $filteredCount = 0;

    foreach ($documents as $doc) {
      $content = $doc->content ?? '';

      if ($this->contentMatchesEntities($content, $queryEntities, $domain)) {
        $filtered[] = $doc;
      } else {
        $filteredCount++;
      }
    }

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Entity filtering: " . count($documents) . " documents -> " . count($filtered) . " documents (filtered out: {$filteredCount})",
        'info'
      );
    }

    return $filtered;
  }
}
