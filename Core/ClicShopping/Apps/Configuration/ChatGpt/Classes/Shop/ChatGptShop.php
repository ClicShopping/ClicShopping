<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

class ChatGptShop
{
  /**
   * Checks the operational status of the GPT system.
   *
   * @return bool Returns true if the GPT system is operational, otherwise false.
   */
  public static function checkGptStatus(): bool
  {
   return Gpt::checkGptStatus();
  }

  /**
   * Converts a sentiment label string into a numeric score.
   *
   * @param array $sentimentLabel Array whose first element is 'positive', 'neutral', or 'negative'.
   * @return float|null 1.0 / 0.0 / -1.0, or null for unrecognised labels.
   */
  protected static function extractSentimentScore(array $sentimentLabel): ?float
  {
    // Intentionally not calling checkGptStatus() here: the GPT status
    // has already been verified by the caller (performSentimentPrediction).
    $label = $sentimentLabel[0] ?? null;

    if ($label === null) {
      return null;
    }

    $label = strtolower(trim($label));

    return match ($label) {
      'positive' => 1.0,
      'neutral'  => 0.0,
      'negative' => -1.0,
      default    => null,
    };
  }

  /**
   * Performs sentiment prediction on an array of user comments.
   *
   * Each comment is analysed independently. The method returns the sentiment
   * score of the LAST comment in the array (consistent with the original
   * behaviour), or null when no valid sentiment could be extracted.
   *
   * Fixes applied vs. the original version:
   *   - $sentimentLabel is now reset at the start of every iteration, which
   *     prevents a stale label from a previous comment contaminating the next.
   *   - The GPT availability check uses `!== false` instead of isset(), because
   *     GptShop::getGptResponse() returns (bool) false — not null — when the
   *     service is unavailable, and isset(false) === true.
   *
   * @param array $userComments Comments to analyse.
   * @param int   $max_token    Max tokens for the GPT response (default 5).
   * @param float $temperature  Sampling temperature (default 0.2).
   * @return float|null Sentiment score, or null when unavailable.
   */
  public static function performSentimentPrediction(
    array $userComments,
    int   $max_token   = 5,
    float $temperature = 0.2
  ): ?float {
    // Bail out early if GPT is down
    if (self::checkGptStatus() === false) {
      return null;
    }

    $sentimentScore = null; // will hold the result of the last processed comment

    foreach ($userComments as $comment) {
      // Reset per-iteration to avoid cross-comment contamination
      $sentimentLabel = [];
      $prompt = "Task : Analyze the sentiment of the following customer review.
      - Constraint 1: If the content is not related to an ecommerce review, return NONE.
      - Constraint 2: No introductory text, no explanations, no quotes.
      - Return ONLY one of these words: POSITIVE, NEGATIVE, or NEUTRAL. 
      - Do not provide any explanation. Review: " . $comment;

      $apiResponse = GptShop::getGptResponse($prompt, $max_token, $temperature);

      // getGptResponse() returns (bool) false when GPT is unavailable.
      // isset() cannot distinguish false from a real string, so we test
      // strictly with !== false.
      if ($apiResponse !== false && $apiResponse !== null && $apiResponse !== '') {
        $sentimentLabel[] = str_replace(' ', '', $apiResponse);
        $sentimentScore   = self::extractSentimentScore($sentimentLabel);
      } else {
        // GPT unavailable or empty response for this comment
        $sentimentScore = 0.0;
      }
    }

    return $sentimentScore;
  }
}