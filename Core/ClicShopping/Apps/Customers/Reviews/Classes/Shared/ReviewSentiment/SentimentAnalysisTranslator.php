<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Customers\Reviews\Reviews as ReviewsApp;
use ClicShopping\OM\Registry;

/**
 * SentimentAnalysisTranslator — structure-preserving translation of the canonical
 * (English) sentiment analysis JSON into another interface language.
 *
 * The analysis is generated ONCE in English (single source of truth) so the
 * classification is identical across languages; only the human-readable string
 * values are translated here — dominant_sentiment, theme sentiment and frequency
 * stay canonical. On any failure the canonical JSON is returned unchanged (never
 * a broken render, never per-language classification drift).
 */
class SentimentAnalysisTranslator
{
  /**
   * @param string $canonicalJson The canonical English analysis JSON.
   * @param string $languageName  Target language display name (e.g. "French").
   * @param string $languageCode  Target language code (e.g. "fr"); "en*" is a no-op.
   * @return string Translated JSON, or the canonical JSON on no-op/failure.
   */
  public static function translate(string $canonicalJson, string $languageName, string $languageCode): string
  {
    if (str_starts_with(strtolower($languageCode), 'en') || trim($canonicalJson) === '' || trim($languageName) === '') {
      return $canonicalJson;
    }

    if (!Registry::exists('ReviewsApp')) {
      Registry::set('ReviewsApp', new ReviewsApp());
    }
    $app = Registry::get('ReviewsApp');
    $app->loadDefinitions('Sites/ClicShoppingAdmin/review_sentiment_prompts', 'en');

    try {
      $prompt = $app->getDef('text_translate_analysis', [
        'language'      => $languageName,
        'analysis_json' => $canonicalJson,
      ]);

      if ($prompt === '' || $prompt === 'text_translate_analysis') {
        return $canonicalJson;
      }

      $raw = (string)Gpt::getGptResponse($prompt, 1500, 0.0, Gpt::defaultModel());

      // Accept only a well-formed translated analysis; otherwise keep canonical.
      $decoded = json_decode($raw, true);
      if (is_array($decoded) && isset($decoded['summary'])) {
        return $raw;
      }

      return $canonicalJson;
    } catch (\Throwable $e) {
      return $canonicalJson;
    }
  }
}
