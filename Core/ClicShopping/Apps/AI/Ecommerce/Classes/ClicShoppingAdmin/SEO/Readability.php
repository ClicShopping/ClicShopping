<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

/**
 * Readability — Kandel-Moles score (French adaptation of Flesch Reading Ease).
 *
 * Deterministic, language-agnostic enough for product copy, no LLM. Higher is
 * easier to read. Syllables are approximated by vowel groups (accents included).
 */
class Readability
{
  /** Kandel-Moles reading-ease score in [0,100]; 0.0 for empty text. */
  public function score(string $text): float
  {
    $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags($text)));
    if ($text === '') {
      return 0.0;
    }

    $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $wordCount = count($words);
    if ($wordCount === 0) {
      return 0.0;
    }

    $sentenceCount = max(1, (int)preg_match_all('/[.!?…]+/u', $text));

    $syllables = 0;
    foreach ($words as $w) {
      $syllables += $this->countSyllables($w);
    }

    $asl = $wordCount / $sentenceCount;     // average sentence length
    $asw = $syllables / $wordCount;         // average syllables per word
    $score = 207.0 - 1.015 * $asl - 73.6 * $asw;

    return round(max(0.0, min(100.0, $score)), 1);
  }

  /** Vowel-group syllable approximation (>= 1), multibyte/accents-safe. */
  private function countSyllables(string $word): int
  {
    $word = mb_strtolower($word, 'UTF-8');
    preg_match_all('/[aeiouyàâäéèêëîïôöùûüœ]+/u', $word, $m);
    return max(1, count($m[0]));
  }
}
