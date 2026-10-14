<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

use ClicShopping\AI\Infrastructure\Storage\MariaDBVectorStore;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * MemorySimilarityRetriever Class
 *
 * Candidate retrieval + ranking for the long-term memory vector store. Extracted
 * verbatim from LongTermMemoryManager::searchSimilar (2026-06-23) to drain the
 * cyclo-69 (benchmark D2) hotspot: this is the MemoryRetrieval/MemoryRanking
 * concern (vector-store query with a permissive threshold, a graceful fallback
 * cascade when a per-user filter matches nothing — unfiltered → manual filter →
 * ultra-low threshold — then score-sort and slice to the requested limit).
 *
 * Entity-aware filtering (EntityMatcher) stays in LongTermMemoryManager around
 * this call: this class is purely "fetch the best N candidate documents".
 *
 * Responsibilities:
 * - Initial similarity search (permissive threshold) honouring an optional filter
 * - Fallback cascade when the filtered search returns nothing
 * - Rank candidates by score and limit to the requested count
 */
class MemorySimilarityRetriever
{
  private MariaDBVectorStore $vectorStore;
  private SecurityLogger $logger;
  private bool $debug;

  /**
   * Constructor
   *
   * @param MariaDBVectorStore $vectorStore Vector store instance
   * @param bool $debug Enable debug logging
   */
  public function __construct(MariaDBVectorStore $vectorStore, bool $debug = false)
  {
    $this->vectorStore = $vectorStore;
    $this->debug = $debug;
    $this->logger = new SecurityLogger();
  }

  /**
   * Build the metadata filter callable for a per-user / per-language similarity
   * search. Returns null when neither constraint is supplied (no filtering).
   *
   * @param string|null $userId User id constraint (null = any)
   * @param int|null $languageId Language id constraint (null = any)
   * @return callable|null Metadata filter for the vector store, or null
   */
  public function buildMetadataFilter(?string $userId, ?int $languageId): ?callable
  {
    // 🔧 FIX: Create filter for user_id and language_id if provided
    if ($userId === null && $languageId === null) {
      return null;
    }

    $debug = $this->debug; // Capture debug flag for closure
    return function(array $metadata) use ($userId, $languageId, $debug) {
      // Check user_id filter - handle both string and int types
      if ($userId !== null) {
        $docUserId = $metadata['user_id'] ?? null;
        // Normalize both to string for comparison
        $docUserIdStr = (string)$docUserId;
        $userIdStr = (string)$userId;

        if ($docUserIdStr !== $userIdStr && $docUserId != $userId) {
          if ($debug) {
            error_log("[INFO] FILTER: user_id mismatch - doc: {$docUserIdStr} (type: " . gettype($docUserId) . "), filter: {$userIdStr} (type: " . gettype($userId) . ")");
          }
          return false;
        }
      }

      // Check language_id filter - handle both int and string types
      if ($languageId !== null) {
        $docLanguageId = $metadata['language_id'] ?? null;
        // Normalize both to int for comparison
        $docLanguageIdInt = (int)$docLanguageId;
        $languageIdInt = (int)$languageId;

        if ($docLanguageIdInt !== $languageIdInt) {
          if ($debug) {
            error_log("[INFO] FILTER: language_id mismatch - doc: {$docLanguageIdInt}, filter: {$languageIdInt}");
          }
          return false;
        }
      }

      if ($debug) {
        $userMatch = $userId === null ? 'N/A' : ($metadata['user_id'] ?? 'missing');
        $langMatch = $languageId === null ? 'N/A' : ($metadata['language_id'] ?? 'missing');
        error_log("[INFO] FILTER: Document passed filter (user_id: {$userMatch}, language_id: {$langMatch})");
      }

      return true;
    };
  }

  /**
   * Fetch the top candidate documents for a query, ranked by score and limited.
   *
   * @param string $query Query text
   * @param int $limit Maximum number of results
   * @param callable|null $filter Optional metadata filter passed to the vector store
   * @param string|null $userId User id (used by the manual fallback filter)
   * @param int|null $languageId Language id (used by the manual fallback filter)
   * @return array Ranked, limited candidate documents (entity filtering applied by caller)
   */
  public function fetchRanked(string $query, int $limit, ?callable $filter, ?string $userId, ?int $languageId): array
  {
    // 🔧 FIX: Start with a very low threshold to get maximum results, then filter
    // Use much lower initial threshold to ensure we get results
    $initialThreshold = 0.1; // Very permissive
    $results = $this->vectorStore->similaritySearch($query, $limit * 10, $initialThreshold, $filter);

    // Convert results to array if it's an iterable
    $resultsArray = is_array($results) ? $results : iterator_to_array($results);

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Initial search with threshold {$initialThreshold}: found " . count($resultsArray) . " results",
        'info'
      );
    }

    // If no results with filter, try without filter to see if filter is blocking everything
    if (empty($resultsArray) && $filter !== null) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "No per-user history matched the filter yet; checking unfiltered availability",
          'info'
        );
      }

      // Try without filter to see if there are ANY results
      $resultsNoFilter = $this->vectorStore->similaritySearch($query, $limit * 10, $initialThreshold, null);
      $resultsNoFilterArray = is_array($resultsNoFilter) ? $resultsNoFilter : iterator_to_array($resultsNoFilter);

      if (!empty($resultsNoFilterArray)) {
        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "No matching per-user history yet for this user - using unfiltered fallback (" . count($resultsNoFilterArray) . " candidates available)",
            'info'
          );
        }

        // Apply manual filtering on unfiltered results (less strict)
        $manuallyFiltered = [];
        foreach ($resultsNoFilterArray as $doc) {
          $docMeta = isset($doc->metadata) ? $doc->metadata : [];
          $docUserId = (string)($docMeta['user_id'] ?? $docMeta['sourceName'] ?? '');
          $docLangId = (int)($docMeta['language_id'] ?? 0);

          $userIdMatch = $userId === null || $docUserId === (string)$userId || empty($docUserId);
          $langIdMatch = $languageId === null || $docLangId === (int)$languageId;

          if ($userIdMatch && $langIdMatch) {
            $manuallyFiltered[] = $doc;
            if (count($manuallyFiltered) >= $limit) break;
          }
        }

        // Use manually filtered if we have results, otherwise use all unfiltered
        $resultsArray = !empty($manuallyFiltered) ? $manuallyFiltered : array_slice($resultsNoFilterArray, 0, $limit);
      } else {
        // No results even without filter - try with even lower threshold
        $ultraLowThreshold = 0.05;
        $resultsUltra = $this->vectorStore->similaritySearch($query, $limit * 20, $ultraLowThreshold, null);
        $resultsUltraArray = is_array($resultsUltra) ? $resultsUltra : iterator_to_array($resultsUltra);
        $resultsArray = array_slice($resultsUltraArray, 0, $limit);

        if ($this->debug) {
          $this->logger->logSecurityEvent(
            "Tried ultra-low threshold {$ultraLowThreshold}: found " . count($resultsArray) . " results",
            'info'
          );
        }
      }
    }

    // Filter by similarity score if we have many results
    if (count($resultsArray) > $limit) {
      // Sort by score (higher is better) and keep best matches
      usort($resultsArray, function($a, $b) {
        $scoreA = (isset($a->metadata) && isset($a->metadata['score'])) ? $a->metadata['score'] : 0;
        $scoreB = (isset($b->metadata) && isset($b->metadata['score'])) ? $b->metadata['score'] : 0;
        return $scoreB <=> $scoreA;
      });
    }

    // Limit to requested number of results
    $resultsArray = array_slice($resultsArray, 0, $limit);

    return $resultsArray;
  }
}
