<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer;


use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Sites\Common\HTMLOverrideCommon;

/**
 * TranslationService
 *
 * Handles query translation and language detection.
 * Extracted from IntentAnalyzer to follow Single Responsibility Principle.
 *
 * PHASE 8.4 CLEANUP: Removed deprecated LanguagePattern dependency.
 * Pure LLM mode: Always translates through LLM for consistent multilingual processing.
 *
 * Responsibilities:
 * - Force translation through LLM (Pure LLM mode)
 * - Translate all queries to English for internal processing
 * - Clean translation output (remove GPT prefixes)
 * - Handle translation errors gracefully
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubIntentAnalyzer
 * @since 2025-12-14
 * @updated 2026-01-03 Phase 8.4 cleanup - Pure LLM mode
 */

class TranslationService
{
  private SecurityLogger $logger;
  private bool $debug;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->logger = new SecurityLogger();
    $this->debug = $debug;
  }

  /**
   * Translate query to English if needed
   *
   * @param string $query Query to translate
   * @return array Translation result with:
   *   - 'translated_query' (string): Translated query (or original if already English)
   *   - 'original_query' (string): Original query
   *   - 'was_translated' (bool): Whether translation was performed
   *   - 'is_english' (bool): Whether query was detected as English
   *   - 'translation_time_ms' (float): Translation time in milliseconds
   */
  public function translateIfNeeded(string $query): array
  {
    $startTime = microtime(true);
    $isEnglish = $this->isEnglish($query);

    if ($isEnglish) {
      // Query is already in English, skip translation
      $translationTime = (microtime(true) - $startTime) * 1000;

      $this->logger->logStructured(
        'info',
        'TranslationService',
        'translation_skipped',
        [
          'original_query' => $query,
          'reason' => 'Query detected as English',
          'translation_time_ms' => round($translationTime, 2)
        ]
      );

      if ($this->debug) {
        error_log("[info] Translation SKIPPED - Query is already in English");
        error_log("Using original query: '{$query}'");
      }

      return [
        'translated_query' => $query,
        'original_query' => $query,
        'was_translated' => false,
        'is_english' => true,
        'translation_time_ms' => $translationTime,
      ];
    }

    // Query is not in English, translate it
    $translatedQuery = SemanticAgent::translateToEnglish($query, 80);
    $translationTime = (microtime(true) - $startTime) * 1000;

    $this->logger->logStructured(
      'info',
      'TranslationService',
      'translation_performed',
      [
        'original_query' => $query,
        'translated_query' => $translatedQuery,
        'translation_time_ms' => round($translationTime, 2),
        'translation_empty' => empty(trim($translatedQuery))
      ]
    );

    if ($this->debug) {
      error_log("🌐 Translation PERFORMED - Query translated to English");
      error_log("Original query: '{$query}'");
      error_log("Translated query: '{$translatedQuery}'");
    }

    // If translation is empty, use original query
    if (empty(trim($translatedQuery))) {
      $translatedQuery = $query;

      $this->logger->logStructured(
        'warning',
        'TranslationService',
        'translation_fallback',
        [
          'original_query' => $query,
          'reason' => 'Translation returned empty string',
          'fallback_action' => 'Using original query'
        ]
      );

      if ($this->debug) {
        error_log("⚠️ Translation empty, using original query");
      }
    }

    // Clean translation
    $cleanTranslatedQuery = $this->extractCleanTranslation($translatedQuery);

    return [
      'translated_query' => $cleanTranslatedQuery,
      'original_query' => $query,
      'was_translated' => true,
      'is_english' => false,
      'translation_time_ms' => $translationTime,
    ];
  }

  /**
   * Detect if query is in English
   *
   * PHASE 8.4: Pure LLM Mode - Always return false to force translation
   * 
   * In Pure LLM mode, we ALWAYS pass queries through the LLM translation service.
   * The LLM will:
   * - Detect if the query is already in English
   * - Return it unchanged if it's English
   * - Translate it if it's in another language
   * 
   * This approach:
   * - Simplifies the code (no complex pattern matching)
   * - Ensures consistent behavior across all languages (FR, ES, DE, EN, IT, PT, etc.)
   * - Leverages LLM's superior language detection capabilities
   * - Handles edge cases and mixed-language queries automatically
   * - Supports the multilingual UI with English processing architecture
   *
   * @param string $query Query to analyze
   * @return bool Always returns false to force LLM translation
   */
  public function isEnglish(string $query): bool
  {
    // Pure LLM mode: Always return false to force translation
    // The LLM will detect if the query is already in English and return it unchanged
    // This ensures consistent multilingual processing with English as the internal language
    return false;
  }

  /**
   * Extract clean translation by removing GPT prefixes
   *
   * Removes common GPT response prefixes like:
   * - "Translation: ..."
   * - "The translation is: ..."
   * - "English: ..."
   *
   * PHASE 8.4: Patterns inlined from LanguagePattern (removed dependency)
   *
   * @param string $translatedQuery Raw translation from GPT
   * @return string Clean translation
   */
  public function extractCleanTranslation(string $translatedQuery): string
  {
    // PHASE 8.4: Patterns inlined below (no longer calling LanguagePattern)
    $patterns = [
      'quoted_after_is' => '/is:\s*"([^"]+)"|is:\s*\'([^\']+)\'/',
      'full_quoted_prefix' => '/^(Translation:|Translated:|English:|Result:|Answer:|Response:)\s*"([^"]+)"$/i',
      'simple_prefix_1' => '/^(Translation:|Translated:|English:|Result:|Answer:|Response:)\s*/i',
      'simple_prefix_2' => '/^(The translation is|This translates to|In English):\s*/i',
      'simple_prefix_3' => '/^(Here is the translation|The English version is):\s*/i',
    ];
    
    // Pattern 1: Extract text between quotes after "is:"
    if (preg_match($patterns['quoted_after_is'], $translatedQuery, $matches)) {
      return $matches[1] ?? $matches[2];
    }

    // Pattern 2: Only if entire sentence is quoted AND starts with GPT prefix
    if (preg_match($patterns['full_quoted_prefix'], $translatedQuery, $matches)) {
      return $matches[2];
    }

    // Pattern 3: Remove common GPT prefixes only
    $clean = $translatedQuery;
    $prefixPatterns = [
      $patterns['simple_prefix_1'],
      $patterns['simple_prefix_2'],
      $patterns['simple_prefix_3']
    ];

    foreach ($prefixPatterns as $prefix) {
      $clean = preg_replace($prefix, '', $clean);
    }

    $clean = trim($clean, " \t\n\r\0\x0B");
    $clean = HTMLOverrideCommon::cleanHtmlForEmbedding($clean);

    // Remove extra whitespace only (not internal quotes)
    return $clean;
  }

  /**
   * Check if a keyword exists in text regardless of language
   *
   * This method uses LLM translation to detect if an English keyword
   * has an equivalent in the original text (any language).
   * 
   * This is language-agnostic and respects the AI layer architecture:
   * - No hardcoded language patterns
   * - Works for all languages automatically
   * - Uses existing LLM translation infrastructure
   *
   * @param string $text Text to search (any language)
   * @param string $englishKeyword English keyword to search for
   * @return bool True if keyword or its translation exists in text
   */
  public function containsKeywordInAnyLanguage(string $text, string $englishKeyword): bool
  {
    $textLower = strtolower($text);
    $keywordLower = strtolower($englishKeyword);

    // First check if English keyword exists directly
    if (str_contains($textLower, $keywordLower)) {
      return true;
    }

    // If not found, translate the text to English and check again
    // This handles cases where the keyword exists in another language
    $translationResult = $this->translateIfNeeded($text);
    $translatedTextLower = strtolower($translationResult['translated_query']);

    return str_contains($translatedTextLower, $keywordLower);
  }
}
