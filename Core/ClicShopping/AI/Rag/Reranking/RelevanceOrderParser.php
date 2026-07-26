<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Rag\Reranking;

/**
 * RelevanceOrderParser Class
 *
 * Tolerant reader of the reranker LLM answer ("Relevance order: 3, 1, 2").
 * A ranking model routinely returns a PARTIAL order — it drops the documents it
 * judges irrelevant — or uses another separator. Rather than abort the reranking,
 * this parser keeps the ranked positions and appends the omitted ones, so the
 * worst case degrades to the incoming similarity order.
 *
 * @package ClicShopping\AI\Rag\Reranking
 */
class RelevanceOrderParser
{
  /**
   * Parse an LLM relevance answer into a complete list of 0-based document positions.
   *
   * @param string $answer Raw LLM answer
   * @param int $documentCount Number of documents submitted for reranking
   * @return array<int, int> Every position exactly once, ranked first, omitted ones appended
   */
  public static function parse(string $answer, int $documentCount): array
  {
    if ($documentCount < 1) {
      return [];
    }

    $ranked = self::extractPositions($answer, $documentCount);

    // Positions the model skipped stay available, behind the ones it ranked.
    foreach (range(0, $documentCount - 1) as $position) {
      if (!in_array($position, $ranked, true)) {
        $ranked[] = $position;
      }
    }

    return $ranked;
  }

  /**
   * Extract the ranked 0-based positions, deduplicated and bounded to the input size.
   *
   * @param string $answer Raw LLM answer
   * @param int $documentCount Number of documents submitted for reranking
   * @return array<int, int>
   */
  private static function extractPositions(string $answer, int $documentCount): array
  {
    // Prefer the text after the expected marker; scan the whole answer when it is missing.
    if (preg_match('/relevance\s+order\s*:(.*)/is', $answer, $marker) === 1) {
      $answer = $marker[1];
    }

    if (preg_match_all('/\d+/', $answer, $numbers) === 0) {
      return [];
    }

    $positions = [];

    foreach ($numbers[0] as $number) {
      $position = (int)$number - 1;

      if ($position >= 0 && $position < $documentCount && !in_array($position, $positions, true)) {
        $positions[] = $position;
      }
    }

    return $positions;
  }
}
