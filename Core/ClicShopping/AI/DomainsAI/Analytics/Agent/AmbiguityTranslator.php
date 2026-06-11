<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;

/**
 * AmbiguityTranslator - lightweight, file-cached English translation used for ambiguity detection.
 *
 * Extracted from AnalyticsAgent (god-class decomposition): self-contained concern that translates
 * a query to English (Pure LLM mode via SemanticAgent) and caches the clean result on disk. Body
 * moved verbatim, behaviour and cache path preserved.
 *
 */
class AmbiguityTranslator
{
  public function __construct(
    private ResultInterpreter $resultInterpreter,
    private bool $debug = false
  ) {}

  /**
   * Translate query to English for ambiguity detection
   * Uses a lightweight, cached translation focused on keywords
   *
   * @param string $question Original question in any language
   * @return string Translated question in English
   */
  public function translate(string $question): string
  {
    // FULL LLM MODE: Always translate using LLM, no pattern-based shortcuts
    // This ensures consistent behavior (Pure LLM mode - no pattern matching)

    // Check cache first
    $cacheKey = 'translation_ambiguity_' . md5($question);
    $cacheDir = CLICSHOPPING::BASE_DIR . 'Work/Cache/Rag/Translation/';
    $cacheFile = $cacheDir . $cacheKey . '.cache';

    // Ensure cache directory exists
    if (!is_dir($cacheDir)) {
      @mkdir($cacheDir, 0755, true);
    }

    if (file_exists($cacheFile)) {
      $cached = file_get_contents($cacheFile);
      if ($cached !== false) {
        if ($this->debug) {
          error_log("Using cached translation for ambiguity detection");
        }
        return $cached;
      }
    }

    // Use SemanticAgent::translateToEnglish for actual translation
    try {
      $translated = SemanticAgent::translateToEnglish($question, 50);

      // Extract clean translation (remove descriptive text)
      $cleanTranslation = $this->resultInterpreter->extractCleanTranslation($translated);

      // Cache the result
      @file_put_contents($cacheFile, $cleanTranslation);

      if ($this->debug) {
        error_log("Translated and cached: {$question} -> {$cleanTranslation}");
      }

      return $cleanTranslation;
    } catch (\Exception $e) {
      // If translation fails, return original query
      if ($this->debug) {
        error_log("Translation failed: " . $e->getMessage() . ", using original query");
      }
      return $question;
    }
  }
}
