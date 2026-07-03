<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment;

use ClicShopping\AI\Security\LlmResponseEvaluator;

/**
 * ReviewSentimentCritic — reliability verdict for a generated sentiment summary.
 *
 * Thin domain wrapper: delegates the actual evaluation to the agnostic
 * LlmResponseEvaluator (Core/ClicShopping/AI/Security) and maps its overall
 * score to the existing reliable|partial|unreliable verdict vocabulary.
 * No parallel critic mechanism is created.
 */
class ReviewSentimentCritic
{
  public const RELIABLE   = 'reliable';
  public const PARTIAL    = 'partial';
  public const UNRELIABLE = 'unreliable';

  /**
   * Evaluate a generated summary against the raw reviews (the ground truth).
   *
   * @return array{critic:string,verdict:string,reliable:bool}
   */
  public static function evaluate(string $summary, string $reviewsText): array
  {
    if (trim($summary) === '') {
      return ['critic' => '', 'verdict' => self::UNRELIABLE, 'reliable' => false];
    }

    // The reviews are the ground truth; the summary is the "result" to grade.
    $evaluation = LlmResponseEvaluator::evaluateLlmResponse($reviewsText, $summary);

    // Evaluator failure → fail-open (do not block), mark as partial/unknown.
    if (!empty($evaluation['error'])) {
      return ['critic' => 'VERDICT: partial | evaluator_unavailable', 'verdict' => self::PARTIAL, 'reliable' => true];
    }

    $overall = (float)($evaluation['overall_score'] ?? 0.0);
    $verdict = self::verdictFromScore($overall);

    $critic = 'VERDICT: ' . $verdict
      . ' | overall=' . number_format($overall, 2)
      . ' | ' . json_encode($evaluation['recommendations'] ?? [], JSON_UNESCAPED_UNICODE);

    return [
      'critic'   => $critic,
      'verdict'  => $verdict,
      'reliable' => $verdict !== self::UNRELIABLE,
    ];
  }

  /**
   * Map an overall score (0..1) to the verdict vocabulary.
   */
  public static function verdictFromScore(float $overall): string
  {
    if ($overall >= 0.75) return self::RELIABLE;
    if ($overall >= 0.50) return self::PARTIAL;
    return self::UNRELIABLE;
  }
}
