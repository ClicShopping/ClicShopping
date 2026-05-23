<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Storage\MariaDBVectorStore;

/**
 * PatternLearner Class
 * Responsible for finding similar historical cases and applying learned corrections
 */
class PatternLearner
{
  private MariaDBVectorStore $correctionStore;
  private SecurityLogger $logger;
  private bool $debug;
  private int $maxSimilarCases;

  /**
   * Constructor
   *
   * @param MariaDBVectorStore $correctionStore Vector store for correction patterns
   * @param SecurityLogger $logger Security logger instance
   * @param bool $debug Debug mode flag
   * @param int $maxSimilarCases Maximum number of similar cases to retrieve
   */
  public function __construct(
    MariaDBVectorStore $correctionStore,
    SecurityLogger $logger,
    bool $debug,
    int $maxSimilarCases = 5
  ) {
    $this->correctionStore = $correctionStore;
    $this->logger = $logger;
    $this->debug = $debug;
    $this->maxSimilarCases = $maxSimilarCases;
  }

  /**
   * Find similar cases in history
   *
   * @param array $errorContext Error context
   * @param array $errorAnalysis Error analysis
   * @return array Similar cases found
   */
  public function findSimilarCases(array $errorContext, array $errorAnalysis): array
  {
    try {
      $errorRepresentation = $this->createErrorRepresentation($errorContext, $errorAnalysis);

      $filter = function ($metadata) use ($errorAnalysis) {
        return isset($metadata['error_type'])
          && $metadata['error_type'] === $errorAnalysis['type']
          && isset($metadata['correction_successful'])
          && $metadata['correction_successful'] === true;
      };

      $results = $this->correctionStore->similaritySearch(
        $errorRepresentation,
        $this->maxSimilarCases,
        0.6,
        $filter
      );

      $similarCases = [];
      foreach ($results as $doc) {
        $similarCases[] = [
          'original_error' => $doc->metadata['original_error'] ?? '',
          'original_query' => $doc->metadata['original_query'] ?? '',
          'corrected_query' => $doc->metadata['corrected_query'] ?? '',
          'correction_method' => $doc->metadata['correction_method'] ?? '',
          'similarity_score' => $doc->metadata['score'] ?? 0,
          'success_rate' => $doc->metadata['success_rate'] ?? 0,
        ];
      }

      if ($this->debug && !empty($similarCases)) {
        $this->logger->logSecurityEvent(
          "PatternLearner: Found " . count($similarCases) . " similar correction cases",
          'info'
        );
      }

      return $similarCases;

    } catch (\Exception $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "PatternLearner: Error finding similar cases: " . $e->getMessage(),
          'error'
        );
      }

      return [];
    }
  }

  /**
   * Create textual representation of error for search
   * 
   * @param array $errorContext Error context
   * @param array $errorAnalysis Error analysis
   * @return string Error representation
   */
  private function createErrorRepresentation(array $errorContext, array $errorAnalysis): string
  {
    $parts = [];

    $parts[] = "Error Type: " . $errorAnalysis['type'];
    $parts[] = "Error Message: " . ($errorContext['error_message'] ?? '');

    if (!empty($errorAnalysis['details'])) {
      foreach ($errorAnalysis['details'] as $key => $value) {
        $parts[] = ucfirst($key) . ": " . $value;
      }
    }

    $parts[] = "Query Fragment: " . substr($errorContext['failed_query'] ?? '', 0, 200);

    return implode("\n", $parts);
  }

  /**
   * Apply learned correction from history
   * 
   * @param array $errorContext Error context
   * @param array $learnedCase Learned case
   * @return array Correction result
   */
  public function applyLearnedCorrection(array $errorContext, array $learnedCase): array
  {
    $originalQuery = $errorContext['failed_query'];
    $learnedOriginal = $learnedCase['original_query'];
    $learnedCorrected = $learnedCase['corrected_query'];

    $transformations = $this->identifyTransformations($learnedOriginal, $learnedCorrected);

    $correctedQuery = $this->applyTransformations($originalQuery, $transformations);

    return [
      'query' => $correctedQuery,
      'method' => 'learned_from_history',
      'confidence' => $learnedCase['similarity_score'] * $learnedCase['success_rate'],
      'source_case' => $learnedCase,
      'transformations' => $transformations,
    ];
  }

  /**
   * Identify transformations between two queries
   * 
   * @param string $original Original query
   * @param string $corrected Corrected query
   * @return array Transformations
   */
  private function identifyTransformations(string $original, string $corrected): array
  {
    $transformations = [];

    // Transformation 1: GROUP BY column additions
    if (
      preg_match_all('/GROUP BY\s+(.+?)(?:\s+ORDER BY|\s+HAVING|\s*$)/i', $original, $origMatches) &&
      preg_match_all('/GROUP BY\s+(.+?)(?:\s+ORDER BY|\s+HAVING|\s*$)/i', $corrected, $corrMatches)
    ) {

      $origCols = array_map('trim', explode(',', $origMatches[1][0] ?? ''));
      $corrCols = array_map('trim', explode(',', $corrMatches[1][0] ?? ''));

      $addedCols = array_diff($corrCols, $origCols);

      if (!empty($addedCols)) {
        $transformations[] = [
          'type' => 'add_to_group_by',
          'columns' => array_values($addedCols),
        ];
      }
    }

    // Transformation 2: Column replacements
    $origWords = explode(' ', $original);
    $corrWords = explode(' ', $corrected);

    $minCount = min(count($origWords), count($corrWords));
    for ($i = 0; $i < $minCount; $i++) {
      if (
        $origWords[$i] !== $corrWords[$i] &&
        preg_match('/^[a-z_]+$/i', $origWords[$i]) &&
        preg_match('/^[a-z_]+$/i', $corrWords[$i])
      ) {
        $transformations[] = [
          'type' => 'column_replacement',
          'from' => $origWords[$i],
          'to' => $corrWords[$i],
        ];
      }
    }

    // Transformation 3: DISTINCT addition
    if (
      stripos($corrected, 'SELECT DISTINCT') !== false &&
      stripos($original, 'SELECT DISTINCT') === false
    ) {
      $transformations[] = [
        'type' => 'add_distinct',
      ];
    }

    return $transformations;
  }

  /**
   * Apply identified transformations
   * 
   * @param string $query Original query
   * @param array $transformations Transformations to apply
   * @return string Corrected query
   */
  private function applyTransformations(string $query, array $transformations): string
  {
    $corrected = $query;

    foreach ($transformations as $transformation) {
      switch ($transformation['type']) {
        case 'add_to_group_by':
          if (preg_match('/GROUP BY\s+(.+?)(?:\s+ORDER BY|\s+HAVING|\s*$)/i', $corrected, $matches)) {
            $currentGroupBy = trim($matches[1]);
            $newColumns = implode(', ', $transformation['columns']);
            $newGroupBy = $currentGroupBy . ', ' . $newColumns;
            $corrected = preg_replace(
              '/GROUP BY\s+' . preg_quote($currentGroupBy, '/') . '/i',
              'GROUP BY ' . $newGroupBy,
              $corrected,
              1
            );
          }
          break;

        case 'column_replacement':
          $corrected = preg_replace(
            '/\b' . preg_quote($transformation['from'], '/') . '\b/i',
            $transformation['to'],
            $corrected,
            1
          );
          break;

        case 'add_distinct':
          $corrected = preg_replace(
            '/SELECT\s+/i',
            'SELECT DISTINCT ',
            $corrected,
            1
          );
          break;
      }
    }

    return $corrected;
  }
}
