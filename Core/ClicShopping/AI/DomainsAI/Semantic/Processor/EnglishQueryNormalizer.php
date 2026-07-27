<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Semantic\Processor;

use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\OM\Registry;

/**
 * EnglishQueryNormalizer - single chokepoint producing the English form of a user query.
 *
 * The AI process runs in English (AGENTS.md); every stage that needs the English form must read
 * the SAME string, or stages silently disagree about what the user asked. Before this class the
 * analytics path translated the query at four sites and post-processed the result three different
 * ways, so ambiguity detection, classification, abstention and SQL generation could each work on a
 * different string. Translation itself is cached by TranslationHandler; this class adds the
 * request-scoped memo and, above all, the single post-processing rule.
 *
 * Fail-safe: never throws and never returns empty — the original query drives the pipeline when
 * translation is unavailable.
 *
 * @package ClicShopping\AI\DomainsAI\Semantic\Processor
 * @since 2026-07-27
 */
class EnglishQueryNormalizer
{
  /**
   * Request-scoped memo: [memoKey => normalized query].
   *
   * @var array<string, string>
   */
  private static array $memo = [];

  /**
   * Normalise a user query to its canonical English form.
   *
   * @param string $query User query, in any interface language
   * @return string English query, or the original one when translation is unavailable
   */
  public static function normalize(string $query): string
  {
    if (trim($query) === '') {
      return $query;
    }

    $memoKey = self::memoKey($query);

    if (isset(self::$memo[$memoKey])) {
      return self::$memo[$memoKey];
    }

    $normalized = $query;

    try {
      // TranslationHandler already strips the LLM's wrapper (prefixes, <think>, enclosing
      // quotes). No second cleaning pass here: that is what made the four sites disagree.
      $translated = SemanticAgent::translateToEnglish($query);

      if (trim($translated) !== '') {
        $normalized = trim($translated);
      }
    } catch (\Throwable $e) {
      // Fail-safe: the original query still drives the pipeline.
    }

    self::$memo[$memoKey] = $normalized;

    return $normalized;
  }

  /**
   * Clear the request-scoped memo. Test harnesses use it to measure a cold path.
   *
   * @return void
   */
  public static function reset(): void
  {
    self::$memo = [];
  }

  /**
   * Build the memo key. The interface language is part of it: the same string may translate
   * differently depending on the source language.
   *
   * @param string $query User query
   * @return string Memo key
   */
  private static function memoKey(string $query): string
  {
    $languageId = 0;

    if (Registry::exists('Language')) {
      $languageId = (int)Registry::get('Language')->getId();
    }

    return $languageId . '_' . md5($query);
  }
}
