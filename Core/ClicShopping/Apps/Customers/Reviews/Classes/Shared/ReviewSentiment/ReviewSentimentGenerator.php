<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment;

use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\ProductsAdmin;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment\SentimentAnalysisData;
use ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment\SentimentMetrics;
use ClicShopping\Apps\Customers\Reviews\Classes\Shared\ReviewSentiment\SentimentReviewCorpus;
use ClicShopping\OM\Registry;
use function count;

/**
 * ReviewSentimentGenerator — shared product-level sentiment generation + persistence.
 *
 * Single source of truth for the whole pipeline, called both by the manual admin
 * hook (ReviewsSentiment/Update) and by the cron runner (ReviewSentimentCronRunner):
 *   1. computed metrics from star ratings (SentimentMetrics)
 *   2. one canonical English analysis (JSON) — vote-aware corpus
 *   3. reliability critic + anti-hallucination fidelity fact-check
 *   4. structure-preserving translation per language (SentimentAnalysisTranslator)
 *   5. upsert reviews_sentiment(+_description)
 *
 * Embeddings are intentionally NOT handled here — the manual hook adds them; the
 * cron path skips them (they can be rebuilt on a manual trigger).
 */
class ReviewSentimentGenerator
{
  /** Minimum approved reviews required to analyse a product. */
  public const MIN_REVIEWS = 3;

  /** GPT temperature for the main analysis (stable, factual). */
  private const GPT_TEMPERATURE = 0.5;

  private mixed $app;
  private mixed $db;
  private mixed $lang;
  private mixed $productsAdmin;

  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }
    $this->app  = Registry::get('Ecommerce');
    $this->db   = $this->app->db;
    $this->lang = Registry::get('Language');

    if (!Registry::exists('ProductsAdmin')) {
      Registry::set('ProductsAdmin', new ProductsAdmin());
    }
    $this->productsAdmin = Registry::get('ProductsAdmin');
  }

  /**
   * Generate + persist the sentiment analysis for one product.
   *
   * @param int         $productId       Product to analyse.
   * @param int         $reviewsIdAnchor Review id stored on the parent row (the trigger).
   * @param string|null $userAdmin       Actor recorded on the row; defaults to 'cron'.
   * @return array{sentiment_id:int,verdict:string,reliable:bool,review_count:int}|null
   *         null when the product has fewer than MIN_REVIEWS approved reviews.
   */
  public function generateForProduct(int $productId, int $reviewsIdAnchor, ?string $userAdmin = null): ?array
  {
    $ratings = $this->getProductRatings($productId);

    if (count($ratings) < self::MIN_REVIEWS) {
      return null;
    }

    $metrics   = SentimentMetrics::compute($ratings);
    $languages = $this->lang->getLanguages();

    // ── Parent row (metrics, language-agnostic) ──
    $parent_data = [
      'reviews_id'    => $reviewsIdAnchor,
      'user_admin'    => $userAdmin ?? 'cron',
      'review_count'  => count($ratings),
      'positive_pct'  => $metrics['positive_pct'],
      'neutral_pct'   => $metrics['neutral_pct'],
      'negative_pct'  => $metrics['negative_pct'],
      'rating_stddev' => $metrics['rating_stddev'],
    ];

    $Qcheck = $this->db->get('reviews_sentiment', 'id', ['reviews_id' => $reviewsIdAnchor]);
    if (!empty($Qcheck->valueInt('id'))) {
      $parent_data['date_modified'] = 'now()';
      $this->db->save('reviews_sentiment', $parent_data, ['id' => (int)$Qcheck->valueInt('id')]);
      $sentimentId = (int)$Qcheck->valueInt('id');
      $isUpdate = true;
    } else {
      $parent_data['date_added'] = 'now()';
      $parent_data['products_id'] = $productId;
      $parent_data['sentiment_status'] = 1;
      $this->db->save('reviews_sentiment', $parent_data);
      $sentimentId = (int)$this->db->lastInsertId();
      $isUpdate = false;
    }

    // ── Canonical analysis (once, English) ──
    $canonicalLangId = (int)$languages[0]['id'];
    $productsName    = $this->productsAdmin->getProductsName($productId, $canonicalLangId);
    $corpus          = $this->getProductReviewCorpus($productId, $canonicalLangId);
    $weightedText    = SentimentReviewCorpus::buildWeightedText($corpus);

    $canonicalJson  = $this->generate('en', $productsName, $weightedText);
    $canonicalData  = SentimentAnalysisData::fromJson($canonicalJson, $canonicalJson);
    $canonicalProse = $canonicalData->getSummary();

    $criticResult = ReviewSentimentCritic::evaluate($canonicalProse, $weightedText);
    $verdict    = $criticResult['verdict'];
    $criticText = $criticResult['critic'];

    $claims   = array_merge($canonicalData->getStrengths(), $canonicalData->getIssues(), [$canonicalProse]);
    $fidelity = ReviewSentimentFidelityChecker::check($weightedText, $claims, 'en');
    if ($fidelity['available'] && !$fidelity['fidelity_ok']) {
      $verdict = ReviewSentimentCritic::UNRELIABLE;
      $criticText .= ' | FIDELITY: unsupported (' . number_format($fidelity['supported_fraction'], 2) . ') → ' . implode('; ', $fidelity['unsupported_claims']);
    }

    // ── Persist per language (English = canonical, others = translation) ──
    for ($i = 0, $n = count($languages); $i < $n; $i++) {
      $languageId   = (int)$languages[$i]['id'];
      $languageCode = $this->lang->getLanguageCodeById($languageId) ?? 'en';
      $languageName = (string)($languages[$i]['name'] ?? '');

      if (str_starts_with(strtolower($languageCode), 'en')) {
        $analysisJson = $canonicalJson;
        $prose        = $canonicalProse;
      } else {
        $analysisJson = SentimentAnalysisTranslator::translate($canonicalJson, $languageName, $languageCode);
        $prose        = SentimentAnalysisData::fromJson($analysisJson, $canonicalProse)->getSummary();
      }

      $desc_data = [
        'description'    => $prose,
        'analysis_json'  => $analysisJson,
        'critic'         => $criticText,
        'critic_verdict' => $verdict,
      ];

      if ($isUpdate) {
        $this->db->save('reviews_sentiment_description', $desc_data, ['id' => $sentimentId, 'language_id' => $languageId]);
      } else {
        $desc_data['id'] = $sentimentId;
        $desc_data['language_id'] = $languageId;
        $this->db->save('reviews_sentiment_description', $desc_data);
      }
    }

    return [
      'sentiment_id' => $sentimentId,
      'verdict'      => $verdict,
      'reliable'     => $verdict !== ReviewSentimentCritic::UNRELIABLE,
      'review_count' => count($ratings),
    ];
  }

  /**
   * @return array<int,int> Approved star ratings (1..5) for the product.
   */
  private function getProductRatings(int $productId): array
  {
    $Q = $this->db->prepare('SELECT reviews_rating FROM :table_reviews
                             WHERE products_id = :products_id AND status = 1');
    $Q->bindInt(':products_id', $productId);
    $Q->execute();

    $ratings = [];
    foreach ($Q->fetchAll() as $row) {
      $ratings[] = (int)$row['reviews_rating'];
    }

    return $ratings;
  }

  /**
   * @return array<int,array{text:string,rating:int,helpful_yes:int,helpful_no:int}>
   */
  private function getProductReviewCorpus(int $productId, int $languagesId): array
  {
    $Qrows = $this->db->prepare('SELECT r.reviews_id, r.reviews_rating, rd.reviews_text
                                 FROM :table_reviews r, :table_reviews_description rd
                                 WHERE r.products_id = :products_id AND r.status = 1
                                   AND r.reviews_id = rd.reviews_id AND rd.languages_id = :languages_id');
    $Qrows->bindInt(':products_id', $productId);
    $Qrows->bindInt(':languages_id', $languagesId);
    $Qrows->execute();

    $rows = [];
    foreach ($Qrows->fetchAll() as $row) {
      $reviewsId = (int)$row['reviews_id'];

      $Qy = $this->db->prepare('SELECT count(*) AS c FROM :table_reviews_vote WHERE reviews_id = :rid AND vote = 1');
      $Qy->bindInt(':rid', $reviewsId);
      $Qy->execute();

      $Qn = $this->db->prepare('SELECT count(*) AS c FROM :table_reviews_vote WHERE reviews_id = :rid AND vote = 0');
      $Qn->bindInt(':rid', $reviewsId);
      $Qn->execute();

      $rows[] = [
        'text'        => (string)$row['reviews_text'],
        'rating'      => (int)$row['reviews_rating'],
        'helpful_yes' => $Qy->valueInt('c'),
        'helpful_no'  => $Qn->valueInt('c'),
      ];
    }

    return $rows;
  }

  /**
   * Calls the LLM (JSON) with the domain prompt for the given language.
   */
  private function generate(string $languageCode, string $productsName, string $weightedText): string
  {
    $this->app->loadDefinitions('Module/Hooks/ClicShoppingAdmin/ReviewsSentiment/review_sentiment', $languageCode);

    $prompt = $this->app->getDef('text_sentiment', [
      'products_name' => $productsName,
      'text_reviews'  => $weightedText,
    ]);

    return (string)Gpt::getGptResponse($prompt, 2300, self::GPT_TEMPERATURE, Gpt::defaultModel());
  }
}
