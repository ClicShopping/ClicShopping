<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;

use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;

/**
 * AnalyticsQueryHeuristics - lightweight, non-LLM heuristics over an analytics query.
 *
 * Extracted from AnalyticsAgent (god-class decomposition): stateless query characterisation
 * helpers that carry no agent state. Bodies moved verbatim, behaviour preserved.
 *
 * Lives in the DomainsAI\Analytics layer (not agnostic Core) because the complexity heuristic
 * references domain entities by name.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Agent
 * @since 2026-06-11
 */
class AnalyticsQueryHeuristics
{
  /**
   * Estimate query complexity for confidence evaluation
   *
   * @param string $question The analytics question
   * @return float Complexity score (0.0-1.0)
   */
  public static function estimateQueryComplexity(string $question): float
  {
    $complexity = 0.3; // Base complexity

    // Increase for multiple questions
    $questionCount = preg_match_all('/\?/', $question);
    if ($questionCount > 1) {
      $complexity += 0.2;
    }

    // Increase for aggregation keywords
    if (preg_match('/\b(total|average|sum|count|group|aggregate)\b/i', $question)) {
      $complexity += 0.1;
    }

    // Increase for time-based queries
    if (preg_match('/\b(month|year|week|day|period|date|time)\b/i', $question)) {
      $complexity += 0.1;
    }

    // Increase for comparison queries
    if (preg_match('/\b(compare|versus|vs|difference|between)\b/i', $question)) {
      $complexity += 0.15;
    }

    // Increase for complex joins (multiple entities)
    $entityCount = 0;
    $entities = ['product', 'order', 'customer', 'category', 'manufacturer', 'supplier'];
    foreach ($entities as $entity) {
      if (preg_match('/\b' . $entity . 's?\b/i', $question)) {
        $entityCount++;
      }
    }
    if ($entityCount > 2) {
      $complexity += 0.1;
    }

    return min(1.0, $complexity);
  }

  /**
   * Identifies the analytical categories of a query
   * Matches query against predefined pattern categories
   * Supports multiple category classification
   *
   * @param string $query Query to analyze
   * @return array List of matched analytical categories
   *               Returns empty array if no categories match
   */
  public static function getAnalyticsCategories(string $query): array
  {
    $analyticsPatterns = SemanticAgent::analyticsPatterns();
    $matchedCategories = [];

    foreach ($analyticsPatterns as $category => $patterns) {
      foreach ($patterns as $pattern) {
        if (preg_match($pattern, $query)) {
          $matchedCategories[] = $category;
          break; // éviter les doublons
        }
      }
    }

    return array_unique($matchedCategories);
  }
}
