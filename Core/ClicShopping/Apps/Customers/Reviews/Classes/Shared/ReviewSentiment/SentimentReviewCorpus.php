<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment;

use function count;

/**
 * SentimentReviewCorpus — builds the helpfulness-weighted review text sent to the LLM.
 *
 * More-helpful reviews are ordered first and annotated so the model can weight
 * them; truncation keeps the most-helpful reviews. This influences ONLY the
 * qualitative synthesis — the numeric distribution is computed separately.
 */
class SentimentReviewCorpus
{
  /**
   * @param array<int,array{text:string,helpful_yes:int,helpful_no:int}> $reviews
   * @param int $maxWords Safety cap on total words in the LLM input.
   * @return string
   */
  public static function buildWeightedText(array $reviews, int $maxWords = 2250): string
  {
    if (count($reviews) === 0) {
      return '';
    }

    // Order by helpfulness (most-helpful first); stable for equal votes.
    usort($reviews, static fn($a, $b) => ((int)($b['helpful_yes'] ?? 0)) <=> ((int)($a['helpful_yes'] ?? 0)));

    $blocks = [];
    foreach ($reviews as $r) {
      $yes = (int)($r['helpful_yes'] ?? 0);
      $no  = (int)($r['helpful_no'] ?? 0);
      $blocks[] = '[helpful: ' . $yes . ' yes / ' . $no . ' no] ' . trim((string)($r['text'] ?? ''));
    }

    $joined = implode("\n- ", $blocks);

    // Truncate by words, most-helpful preserved (they come first).
    $words = preg_split('/\s+/', $joined, -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) > $maxWords) {
      $joined = implode(' ', array_slice($words, 0, $maxWords));
    }

    return $joined;
  }
}
