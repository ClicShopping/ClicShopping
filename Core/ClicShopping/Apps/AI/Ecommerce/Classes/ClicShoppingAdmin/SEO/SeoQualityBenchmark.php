<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

/**
 * SeoQualityBenchmark
 *
 * Algorithmic regression guard for the Phase 2 optimization output.
 *
 * Replaces subjective "looks better / looks worse" judgements by reproducible,
 * embedding-free metrics that can be computed on any source / generated pair:
 *
 *   - Shannon lexical entropy        — diversity of vocabulary in the text
 *   - Unique-word ratio (TTR)        — proportion of distinct word forms
 *   - Source entity coverage         — how much of the source's content vocabulary
 *                                       survived in the generated text (paraphrased
 *                                       or kept verbatim — substring match)
 *   - Repetition penalty             — penalises over-used non-stopwords (e.g. the
 *                                       LLM saying "coffret" 12 times)
 *
 * A composite score is derived using the weights proposed in the human-coder
 * brief: 30 % entity coverage, 25 % normalised entropy, 20 % diversity,
 * 25 % (1 − repetition).  The class also exposes compare(source, generated)
 * so the pipeline can decide whether the generated content is a regression
 * relative to the source and should be rejected.
 *
 * This benchmark is intentionally pure PHP, language-agnostic and embedding-free:
 *  - cheap enough to run on every attempt without inflating LLM cost,
 *  - reproducible across runs (deterministic),
 *  - usable both as a critic during retry and as a hard gate before apply.
 *
 * A future enhancement can layer a SERP-similarity check (cosine on top-10
 * embeddings) on top of this — see AnswerGroundingVerifier for the embedding
 * helper.  Out of scope here to avoid one extra OpenAI call per attempt.
 */
class SeoQualityBenchmark
{
  /**
   * Words at least this long are considered "content words".  Below this
   * length, words are too likely to be stopwords or grammatical tokens
   * to carry SEO signal.
   */
  private const CONTENT_WORD_MIN_LEN = 5;

  /**
   * A bilingual (FR + EN) stopword list.  Kept short on purpose: the
   * length filter above already removes most function words.  Adding a
   * dedicated stopword library is not worth the dependency given the
   * complementary length filter.
   */
  private const STOPWORDS = [
    // FR
    'avec', 'pour', 'sans', 'dans', 'cette', 'cettes', 'leur', 'leurs',
    'votre', 'votres', 'plus', 'mais', 'cela', 'ceci', 'donc', 'aussi',
    'comme', 'tout', 'tous', 'toute', 'toutes', 'sont', 'sera', 'fait',
    'faire', 'etre', 'avoir', 'quand', 'alors', 'apres', 'avant', 'parce',
    'aussi', 'encore', 'jamais', 'meme', 'sous', 'entre', 'beaucoup',
    'peuvent', 'doivent', 'cependant', 'depuis', 'pendant',
    // EN
    'with', 'from', 'that', 'this', 'they', 'them', 'have', 'will', 'been',
    'were', 'their', 'about', 'into', 'than', 'each', 'which', 'when',
    'where', 'what', 'your', 'these', 'those', 'over', 'under', 'such',
    'while', 'after', 'before', 'because', 'still', 'never', 'between',
    'among', 'during', 'should', 'could', 'would', 'might',
  ];

  /**
   * Regression tolerance for compare().  A negative delta worse than this
   * threshold is flagged as a regression.  Empirically a 10 % drop on the
   * composite score is the point where the SEO degradation becomes
   * perceptible (e.g. lost entities, excessive repetition).
   */
  private const REGRESSION_DELTA_THRESHOLD = -0.10;

  /**
   * Score one piece of text in isolation.  When $source is provided, the
   * entity_coverage component measures how much of the source vocabulary
   * survived in $text; otherwise it is set to 1.0 (no penalty).
   *
   * @return array{
   *   score:float,
   *   breakdown:array{entropy:float,normalized_entropy:float,diversity:float,entity_coverage:float,repetition:float,word_count:int}
   * }
   */
  public function scoreText(string $text, ?string $source = null): array
  {
    $words = $this->tokenize($text);
    if (empty($words)) {
      return [
        'score'     => 0.0,
        'breakdown' => [
          'entropy'            => 0.0,
          'normalized_entropy' => 0.0,
          'diversity'          => 0.0,
          'entity_coverage'    => 0.0,
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
    $entityCoverage = $source !== null ? $this->entityCoverage($source, $text) : 1.0;

    // Weighted composite from the algorithmic brief.  Kept identical across
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

  /**
   * Compare a generated text against the source description.  The verdict
   * tells the caller whether the generated content is a regression that
   * should not be applied:
   *
   *   - verdict = 'regression' → caller should reject (revert / retry)
   *   - verdict = 'parity'     → no clear win, caller decides
   *   - verdict = 'improvement'→ keep
   *
   * The diagnostics block is normalised so analytics queries can drill into
   * the exact failure mode without parsing free-text messages.
   *
   * @return array{
   *   source_score:array,
   *   generated_score:array,
   *   delta:float,
   *   is_regression:bool,
   *   verdict:string,
   *   regression_reason:string,
   *   diagnostics:array{
   *     critical:bool,
   *     coverage:float,
   *     diversity_drop:float,
   *     entropy_drop:float,
   *     repetition:float,
   *     messages:array<int,string>
   *   }
   * }
   */
  public function compare(string $source, string $generated): array
  {
    $sourceScore    = $this->scoreText($source);
    $generatedScore = $this->scoreText($generated, $source);
    $delta          = $generatedScore['score'] - $sourceScore['score'];

    $srcBreak = $sourceScore['breakdown'];
    $genBreak = $generatedScore['breakdown'];

    $coverage      = (float)$genBreak['entity_coverage'];
    $diversityDrop = max(0.0, (float)$srcBreak['diversity']          - (float)$genBreak['diversity']);
    $entropyDrop   = max(0.0, (float)$srcBreak['normalized_entropy'] - (float)$genBreak['normalized_entropy']);
    $repetition    = (float)$genBreak['repetition'];
    $sourceWords   = (int)($srcBreak['word_count']  ?? 0);
    $srcDiversity  = (float)($srcBreak['diversity'] ?? 0);

    // ---------------------------------------------------------------------- //
    // Regression policy (rebalanced after real-world tests)
    //
    // Two kinds of failure modes worth blocking on:
    //   1. Critical fidelity issues: coverage drop, repetition spam.
    //   2. Material global decline: composite delta below threshold.
    //
    // The previous "diversity_drop > 0.10 is critical" rule fired on every
    // short / dense source — expanding a 50-word source to 150-200 words
    // mechanically lowers the type-token ratio, even when the output is
    // factually correct and entity_coverage is high.  We now treat
    // diversity_drop / entropy_drop as INFORMATIONAL warnings that surface
    // in diagnostics.messages but DO NOT, on their own, block the apply.
    // They become blocking only when the source was already vocabulary-poor
    // (diversity < 0.6) AND we made it worse, which would be a true
    // regression.
    // ---------------------------------------------------------------------- //
    $reasons  = [];   // critical reasons that block apply
    $warnings = [];   // informational drops, surfaced but non-blocking
    $messages = [];

    if ($coverage < 0.6) {
      $reasons[] = 'low_coverage';
      $messages[] = sprintf(
        'Low source-entity coverage: %.2f (target ≥ 0.60). Add the missing source attributes (e.g. material details, base / accessory mentions, usage variants) as semantic paraphrases.',
        $coverage
      );
    }
    if ($repetition > 0.20) {
      $reasons[] = 'repetition';
      $messages[] = sprintf(
        'High repetition penalty: %.2f. Identify the over-used token(s) and replace some occurrences with semantic synonyms.',
        $repetition
      );
    }

    // Adaptive diversity threshold: a short/dense source (< 80 words AND
    // diversity ≥ 0.80) cannot be expanded 2x-3x without arithmetic drop in
    // type-token ratio — we tolerate up to 0.25 in that case, otherwise
    // keep the original 0.10 threshold.  Either way, it remains advisory
    // unless the OUTPUT itself drops below 0.6 (vocabulary-poor result).
    $isShortDenseSource = $sourceWords > 0 && $sourceWords < 80 && $srcDiversity >= 0.80;
    $diversityCeiling   = $isShortDenseSource ? 0.25 : 0.10;
    if ($diversityDrop > $diversityCeiling) {
      $msg = sprintf(
        'Vocabulary diversity dropped: %.2f vs source %.2f%s.',
        $genBreak['diversity'],
        $srcBreak['diversity'],
        $isShortDenseSource ? ' (source is short / dense → expansion mechanically lowers TTR)' : ''
      );
      // Block only if the output's absolute diversity is poor (< 0.6).
      // Otherwise just surface as a warning so the retry can address it
      // without trapping the pipeline in an unwinnable loop.
      if ((float)$genBreak['diversity'] < 0.60) {
        $reasons[]  = 'diversity_drop';
        $messages[] = $msg . ' Output diversity below 0.60 — diversify with synonyms of source attributes.';
      } else {
        $warnings[] = 'diversity_drop';
        $messages[] = $msg;
      }
    }

    if ($entropyDrop > 0.10) {
      // Always advisory: entropy is correlated with diversity, same caveat.
      $warnings[] = 'entropy_drop';
      $messages[] = sprintf(
        'Normalised entropy dropped: %.2f vs source %.2f (informational).',
        $genBreak['normalized_entropy'], $srcBreak['normalized_entropy']
      );
    }

    $isRegression     = $delta < self::REGRESSION_DELTA_THRESHOLD || !empty($reasons);
    $verdict          = $isRegression
      ? 'regression'
      : ($delta > 0.05 ? 'improvement' : 'parity');
    $regressionReason = match (true) {
      count($reasons) > 1 => 'multiple',
      count($reasons) === 1 => $reasons[0],
      $isRegression        => 'delta_drop',   // delta below threshold but no individual breach
      default              => 'none',
    };

    $diagnostics = [
      'critical'       => $isRegression,
      'coverage'       => round($coverage, 3),
      'diversity_drop' => round($diversityDrop, 3),
      'entropy_drop'   => round($entropyDrop, 3),
      'repetition'     => round($repetition, 3),
      'warnings'       => $warnings,   // non-blocking drops, surfaced for traceability
      'messages'       => $messages,
    ];

    return [
      'source_score'      => $sourceScore,
      'generated_score'   => $generatedScore,
      'delta'             => round($delta, 3),
      'is_regression'     => $isRegression,
      'verdict'           => $verdict,
      'regression_reason' => $regressionReason,
      'diagnostics'       => $diagnostics,
    ];
  }

  // ---------------------------------------------------------------------- //
  // Helpers
  // ---------------------------------------------------------------------- //

  /**
   * Lowercase, strip HTML, split into a word list.  Multibyte-safe so
   * French accented forms are not lost.
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
   * Same as tokenize() but with stopwords and very short tokens removed.
   * Used by metrics that focus on "content words" (entropy, repetition).
   *
   * @return list<string>
   */
  private function tokenizeContent(string $text): array
  {
    $out = [];
    foreach ($this->tokenize($text) as $word) {
      if (mb_strlen($word, 'UTF-8') < self::CONTENT_WORD_MIN_LEN) {
        continue;
      }
      if (in_array($word, self::STOPWORDS, true)) {
        continue;
      }
      $out[] = $word;
    }
    return $out;
  }

  /**
   * Shannon entropy over the content-word distribution.  Higher means a
   * richer, more varied vocabulary.
   *
   * @param list<string> $words All tokens (we recompute content-only inside).
   */
  private function shannonEntropy(array $words): float
  {
    if (empty($words)) {
      return 0.0;
    }
    // Use full word stream so the score is comparable across texts of
    // different length (content-only filtering would over-weight rare
    // technical terms in short texts).
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
   * Type-token ratio: number of distinct words divided by total words.
   * Sensitive to text length, but used here only relative to source.
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
   * Penalty for over-repetition of content words.  A word that exceeds the
   * "natural" frequency budget (5 % of content tokens) contributes the
   * excess to the penalty.  Capped at 1.0.
   *
   * @param list<string> $words
   */
  private function repetitionPenalty(array $allWords): float
  {
    $contentOnly = array_values(array_filter(
      $allWords,
      fn(string $w): bool => mb_strlen($w, 'UTF-8') >= self::CONTENT_WORD_MIN_LEN
        && !in_array($w, self::STOPWORDS, true)
    ));
    if (empty($contentOnly)) {
      return 0.0;
    }
    $freq    = array_count_values($contentOnly);
    $total   = count($contentOnly);
    $excess  = 0.0;
    foreach ($freq as $count) {
      $share = $count / $total;
      if ($share > 0.05) {
        $excess += ($share - 0.05);
      }
    }
    return min(1.0, $excess);
  }

  /**
   * Fraction of the source's content vocabulary that is preserved in the
   * output, either verbatim or as a substring.  Captures both the "kept
   * the same word" case ("borosilicate") and the "kept the root with a
   * paraphrase" case ("isolation" → "isolant" still matches the stem if
   * the LLM keeps any 5-char prefix).
   */
  private function entityCoverage(string $source, string $output): float
  {
    $sourceTerms = array_values(array_unique($this->tokenizeContent($source)));
    if (empty($sourceTerms)) {
      return 1.0;
    }
    $outputLower = mb_strtolower(strip_tags($output), 'UTF-8');
    $covered = 0;
    foreach ($sourceTerms as $term) {
      $stem = mb_substr($term, 0, max(5, (int) floor(mb_strlen($term, 'UTF-8') * 0.7)), 'UTF-8');
      if ($stem !== '' && mb_strpos($outputLower, $stem) !== false) {
        $covered++;
      }
    }
    return $covered / count($sourceTerms);
  }
}
