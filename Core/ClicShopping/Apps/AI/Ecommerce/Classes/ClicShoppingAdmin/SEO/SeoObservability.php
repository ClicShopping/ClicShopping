<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

/**
 * SeoObservability
 *
 * Language-agnostic observability metrics for the Phase 2 optimization output.
 *
 * This class is NOT a gate. Source-fact fidelity is decided by the agentic,
 * language-agnostic {@see SeoFidelityChecker} (Pure LLM Mode, per AGENTS.md).
 * SeoObservability only produces cheap, reproducible numbers surfaced in the
 * report / dashboard so an admin can see how an attempt evolved versus its
 * source — it never accepts or rejects content.
 *
 * Every metric is computed on the raw token stream with no per-language word
 * list (no stopwords, no stem/prefix heuristic), so it behaves identically for
 * EN / FR / DE / IT / … :
 *
 *   - Shannon lexical entropy   — vocabulary richness of the text
 *   - Normalised entropy        — entropy scaled to [0,1] for cross-text comparison
 *   - Unique-word ratio (TTR)   — proportion of distinct word forms (diversity)
 *   - Repetition penalty        — penalises over-used tokens (keyword stuffing)
 *   - Word count                — raw length signal
 *
 * Source-entity coverage is NOT derived here from a keyword heuristic; the
 * caller passes the agentic {@see SeoFidelityChecker} `coverage_estimate`
 * (LLM-judged, multilingual) into {@see scoreText()} so the composite reflects
 * a semantic, model-robust coverage rather than substring matching.
 *
 * The composite score uses the weights from the algorithmic brief:
 * 30 % coverage, 25 % normalised entropy, 20 % diversity, 25 % (1 − repetition).
 */
class SeoObservability
{
  /**
   * Tokens at least this long feed the repetition penalty. Below this length a
   * token is too likely to be a grammatical/function word to carry SEO signal.
   * This is a length heuristic only — NOT a language-specific word list — so it
   * stays agnostic across languages.
   */
  private const CONTENT_WORD_MIN_LEN = 5;

  /**
   * Score one piece of text in isolation. When $coverageEstimate is provided
   * (the agentic SeoFidelityChecker `coverage_estimate` for the generated text),
   * it feeds the entity_coverage component; otherwise coverage defaults to 1.0
   * (e.g. when scoring the source itself, which is its own reference).
   *
   * @return array{
   *   score:float,
   *   breakdown:array{entropy:float,normalized_entropy:float,diversity:float,entity_coverage:float,repetition:float,word_count:int}
   * }
   */
  public function scoreText(string $text, ?float $coverageEstimate = null): array
  {
    $words = $this->tokenize($text);
    if (empty($words)) {
      return [
        'score'     => 0.0,
        'breakdown' => [
          'entropy'            => 0.0,
          'normalized_entropy' => 0.0,
          'diversity'          => 0.0,
          'entity_coverage'    => $coverageEstimate ?? 0.0,
          'repetition'         => 1.0,
          'word_count'         => 0,
        ],
      ];
    }

    $entropy        = $this->shannonEntropy($words);
    $maxEntropy     = log(max(count($words), 2), 2);
    $normalised     = $maxEntropy > 0 ? min(1.0, $entropy / $maxEntropy) : 0.0;
    $diversity      = $this->uniqueWordRatio($words);
    $repetition     = $this->repetitionPenalty($words);
    $entityCoverage = $coverageEstimate !== null ? max(0.0, min(1.0, $coverageEstimate)) : 1.0;

    // Weighted composite from the algorithmic brief. Identical weights across
    // source and generated so the comparison is meaningful.
    $score = 0.30 * $entityCoverage
           + 0.25 * $normalised
           + 0.20 * $diversity
           + 0.25 * (1.0 - $repetition);

    return [
      'score' => round($score, 3),
      'breakdown' => [
        'entropy'            => round($entropy, 3),
        'normalized_entropy' => round($normalised, 3),
        'diversity'          => round($diversity, 3),
        'entity_coverage'    => round($entityCoverage, 3),
        'repetition'         => round($repetition, 3),
        'word_count'         => count($words),
      ],
    ];
  }

  // ---------------------------------------------------------------------- //
  // Helpers
  // ---------------------------------------------------------------------- //

  /**
   * Lowercase, strip HTML, split into a word list. Multibyte-safe so accented
   * forms are preserved. No language-specific filtering.
   *
   * @return list<string>
   */
  private function tokenize(string $text): array
  {
    $text = strip_tags($text);
    $text = preg_replace('/\s+/u', ' ', mb_strtolower($text, 'UTF-8'));
    $parts = preg_split('/[^\p{L}\p{N}_-]+/u', (string)$text, -1, PREG_SPLIT_NO_EMPTY);
    return $parts ?: [];
  }

  /**
   * Shannon entropy over the full token distribution. Higher means a richer,
   * more varied vocabulary.
   *
   * @param list<string> $words
   */
  private function shannonEntropy(array $words): float
  {
    if (empty($words)) {
      return 0.0;
    }
    $freq  = array_count_values($words);
    $total = count($words);
    $H     = 0.0;
    foreach ($freq as $count) {
      $p = $count / $total;
      if ($p > 0) {
        $H -= $p * log($p, 2);
      }
    }
    return $H;
  }

  /**
   * Type-token ratio: distinct words divided by total words.
   *
   * @param list<string> $words
   */
  private function uniqueWordRatio(array $words): float
  {
    if (empty($words)) {
      return 0.0;
    }
    return count(array_unique($words)) / count($words);
  }

  /**
   * Penalty for over-repetition of content-length tokens. A token exceeding the
   * "natural" frequency budget (5 % of tokens) contributes the excess to the
   * penalty. Capped at 1.0. Uses a length filter only (no stopword list) so it
   * stays language-agnostic.
   *
   * @param list<string> $allWords
   */
  private function repetitionPenalty(array $allWords): float
  {
    $contentOnly = array_values(array_filter(
      $allWords,
      fn(string $w): bool => mb_strlen($w, 'UTF-8') >= self::CONTENT_WORD_MIN_LEN
    ));
    if (empty($contentOnly)) {
      return 0.0;
    }
    $freq   = array_count_values($contentOnly);
    $total  = count($contentOnly);
    $excess = 0.0;
    foreach ($freq as $count) {
      $share = $count / $total;
      if ($share > 0.05) {
        $excess += ($share - 0.05);
      }
    }
    return min(1.0, $excess);
  }
}
