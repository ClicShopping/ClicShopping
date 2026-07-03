<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment;

use ClicShopping\OM\Registry;
use ClicShopping\Apps\Customers\Reviews\Reviews as ReviewsApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

/**
 * ReviewSentimentFidelityChecker — LLM fact-check against the source reviews.
 *
 * Anti-hallucination gate: the customer reviews are the only ground truth; the
 * LLM lists any claim in the generated analysis NOT supported by them. Unlike
 * cosine grounding (inert when review documents have no embeddings), this is an
 * explicit fact-check. Mirrors the SeoFidelityChecker pattern.
 *
 * Fails open (available=false → fidelity_ok=true) so an LLM outage never blocks.
 */
class ReviewSentimentFidelityChecker
{
  /** Minimum fraction of analysis claims that must be supported by the reviews. */
  public const MIN_SUPPORTED = 0.90;

  /**
   * @param array<int,string> $claims Analysis claims to fact-check (strengths, issues, summary).
   * @return array{available:bool,fidelity_ok:bool,supported_fraction:float,unsupported_claims:list<string>}
   */
  public static function check(string $reviewsText, array $claims, string $languageCode = 'en'): array
  {
    $claims = array_values(array_filter(array_map('trim', $claims), static fn($c) => $c !== ''));

    if (trim($reviewsText) === '' || $claims === []) {
      return ['available' => true, 'fidelity_ok' => true, 'supported_fraction' => 1.0, 'unsupported_claims' => []];
    }

    if (!Registry::exists('ReviewsApp')) {
      Registry::set('ReviewsApp', new ReviewsApp());
    }
    $app = Registry::get('ReviewsApp');
    $app->loadDefinitions('Sites/ClicShoppingAdmin/review_sentiment_prompts', $languageCode);

    try {
      $prompt = $app->getDef('text_fidelity_check', [
        'text_reviews' => $reviewsText,
        'claims'       => "- " . implode("\n- ", $claims),
      ]);

      $raw  = (string)Gpt::getGptResponse($prompt, 500, 0.0, Gpt::defaultModel());
      $json = json_decode($raw, true);

      if (!is_array($json)) {
        // Not parseable → cannot verify; fail open.
        return ['available' => false, 'fidelity_ok' => true, 'supported_fraction' => 1.0, 'unsupported_claims' => []];
      }

      $unsupported = [];
      foreach ((array)($json['unsupported_claims'] ?? []) as $claim) {
        $claim = trim((string)$claim);
        if ($claim !== '') {
          $unsupported[] = $claim;
        }
      }

      if (isset($json['supported_fraction'])) {
        $fraction = (float)$json['supported_fraction'];
      } else {
        // Derive from unsupported count against total claims.
        $fraction = 1.0 - (count($unsupported) / max(1, count($claims)));
      }
      $fraction = max(0.0, min(1.0, $fraction));

      return [
        'available'          => true,
        'fidelity_ok'        => self::gateFromFraction($fraction),
        'supported_fraction' => $fraction,
        'unsupported_claims' => $unsupported,
      ];
    } catch (\Throwable $e) {
      return ['available' => false, 'fidelity_ok' => true, 'supported_fraction' => 1.0, 'unsupported_claims' => []];
    }
  }

  /**
   * Gate: the supported fraction must be at or above the threshold.
   */
  public static function gateFromFraction(float $fraction): bool
  {
    return $fraction >= self::MIN_SUPPORTED;
  }
}
