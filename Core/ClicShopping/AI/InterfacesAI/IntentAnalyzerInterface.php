<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * IntentAnalyzerInterface
 *
 * Common interface for all specialized intent analyzers.
 * Each analyzer is responsible for detecting and analyzing a specific type of query intent.
 *
 * @package ClicShopping\AI\InterfacesAI
 * @since 2025-12-14
 */
interface IntentAnalyzerInterface
{
  /**
   * Analyze a query to determine if it matches this analyzer's intent type
   *
   * @param string $query Query to analyze (translated to English)
   * @param string $originalQuery Original query in user's language
   * @return array Analysis result with:
   *   - 'matches' (bool): Whether this analyzer can handle the query
   *   - 'confidence' (float): Confidence score (0.0 to 1.0)
   *   - 'type' (string): Intent type (semantic, analytics, web_search, hybrid)
   *   - 'metadata' (array): Type-specific metadata
   *   - 'reasoning' (array): Detection reasoning/evidence
   */
  public function analyze(string $query, string $originalQuery): array;

  /**
   * Get the intent type this analyzer handles
   *
   * @return string Intent type (semantic, analytics, web_search, hybrid)
   */
  public function getType(): string;

  /**
   * Calculate confidence score for this intent type
   *
   * @param string $query Query to analyze
   * @param array $detectionData Detection data from pattern matching
   * @return float Confidence score (0.0 to 1.0)
   */
  public function calculateConfidence(string $query, array $detectionData): float;

  /**
   * Extract metadata specific to this intent type
   *
   * @param string $query Query to analyze
   * @return array Type-specific metadata
   */
  public function extractMetadata(string $query): array;
}
