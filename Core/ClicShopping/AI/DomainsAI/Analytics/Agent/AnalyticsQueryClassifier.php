<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;

use ClicShopping\AI\CoreAI\Query\QueryClassifier;
use ClicShopping\AI\DomainsAI\Shared\Patterns\Common\ModificationKeywordsPattern;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;

/**
 * AnalyticsQueryClassifier - characterises an incoming query for the analytics pipeline.
 *
 * Extracted from AnalyticsAgent (god-class decomposition): groups the "what kind of query is
 * this?" checks (modification request? analytics intent?). Bodies moved verbatim.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Agent
 * @since 2026-06-11
 */
class AnalyticsQueryClassifier
{
  public function __construct(
    private ResultInterpreter $resultInterpreter,
    private bool $debug = false
  ) {}

  /**
   * Detects whether the question asks to modify the previous query/result.
   *
   * @param string $question The user's question
   * @return bool True when a modification keyword is present
   */
  public function isModificationRequest(string $question): bool
  {
    // Use centralized pattern class (uses getAllKeywords() internally)
    $isModification = ModificationKeywordsPattern::isModificationRequest($question);

    if ($isModification && $this->debug) {
      $keyword = ModificationKeywordsPattern::getModificationKeyword($question);
      error_log("🔄 Modification request detected with keyword: {$keyword}");
    }

    return $isModification;
  }

  /**
   * Classifies whether the query is an analytics query (translates first, then classifies).
   *
   * @param string $query The user's query (any language)
   * @return bool True when the classifier returns the 'analytics' type
   */
  public function isAnalyticsQuery(string $query): bool
  {
    if ($this->debug) {
      error_log("\n=== ANALYTICS AGENT: isAnalyticsQuery() ===");
      error_log("Input query: '{$query}'");
    }

    $translatedQuery = SemanticAgent::translateToEnglish($query, 80);
    if ($this->debug) {
      error_log("Translated query: '{$translatedQuery}'");
    }

    // Extract only the clean translation (not the descriptive text)
    $cleanTranslation = $this->resultInterpreter->extractCleanTranslation($translatedQuery);
    if ($this->debug) {
      error_log("Clean translation: '{$cleanTranslation}'");
    }

    // NOW classify the translated query using centralized QueryClassifier
    $classifier = new QueryClassifier($this->debug);
    $classificationResult = $classifier->classify($cleanTranslation, $cleanTranslation);

    if ($this->debug) {
      error_log("Classification result: '{$classificationResult['type']}' (confidence: {$classificationResult['confidence']})");
      if (!empty($classificationResult['reasoning'])) {
        error_log("Reasoning: " . implode('; ', $classificationResult['reasoning']));
      }
    }

    // REVERTED: Only accept 'analytics' type, NOT 'hybrid'
    // Hybrid queries should be handled by HybridQueryProcessor, not AnalyticsAgent
    // The CompoundQueryHandler in AnalyticsAgent produces incorrect output format
    // HybridQueryProcessor has proper handling for multi-intent queries
    $isAnalytics = $classificationResult['type'] === 'analytics';
    if ($this->debug) {
      error_log("Is analytics? " . ($isAnalytics ? 'YES' : 'NO'));
      error_log("=== END isAnalyticsQuery() ===\n");
    }

    return $isAnalytics;
  }
}
