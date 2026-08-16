<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubCorrectionAgent;

/**
 * QueryEquivalence Class
 * Tells whether a proposed correction actually changed the failed query.
 * A self-heal whose output is the input repairs nothing and must never be accepted or memorized.
 */
class QueryEquivalence
{
  /**
   * Check if a correction left the query untouched
   *
   * Comparison is whitespace- and trailing-semicolon-insensitive but CASE-SENSITIVE:
   * on Linux a table-name case fix is a real correction.
   *
   * @param string $failedQuery The query that failed
   * @param string $correctedQuery The query proposed by the correction
   * @return bool True when the correction is a no-op
   */
  public static function isUnchanged(string $failedQuery, string $correctedQuery): bool
  {
    return self::normalize($failedQuery) === self::normalize($correctedQuery);
  }

  /**
   * Normalize a query for structural comparison
   *
   * @param string $query Query to normalize
   * @return string Normalized query
   */
  private static function normalize(string $query): string
  {
    $normalized = preg_replace('/\s+/', ' ', $query) ?? $query;

    return rtrim(trim($normalized), "; \t\n\r");
  }
}
