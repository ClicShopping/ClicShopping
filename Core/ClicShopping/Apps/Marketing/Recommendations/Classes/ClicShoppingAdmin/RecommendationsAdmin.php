<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Classes\ClicShoppingAdmin;

use ClicShopping\OM\Registry;
use function is_null;
/**
 * RecommendationsAdmin — product recommendation scoring engine.
 *
 * ════════════════════════════════════════════════════════════════
 * GLOBAL MATHEMATICAL CONTRACT
 * ════════════════════════════════════════════════════════════════
 *
 * Unified score space: [-1, 1]
 *   All sources are mapped into this space BEFORE aggregation.
 *   The normalization layer is handled by SentimentScorer (static methods).
 *
 * Sources and their normalization:
 *   ┌─────────────────────────────┬─────────────┬────────────────────────────────────┐
 *   │ Source                      │ Raw         │ Normalized → [-1, 1]               │
 *   ├─────────────────────────────┼─────────────┼────────────────────────────────────┤
 *   │ reviewRate (star rating)    │ [0, 5]      │ SentimentScorer::normalizeRating() │
 *   │ userFeedback (user vote)    │ [-1, 1]     │ SentimentScorer::clamp()           │
 *   │ sentimentScore (GPT review) │ [-1, 1]     │ SentimentScorer::clamp()           │
 *   │ votesSentiment (history)    │ raw sum     │ SentimentScorer::compute()         │
 *   │ salesDataScore              │ [0, 1]      │ SentimentScorer::normalize01To11() │
 *   │ externalRecommendation      │ [0, 1]      │ SentimentScorer::normalize01To11() │
 *   │ popularityScore             │ [0, 1]      │ SentimentScorer::normalize01To11() │
 *   └─────────────────────────────┴─────────────┴────────────────────────────────────┘
 *
 * Weight sum: always normalized to 1.0 in both strategies.
 *
 * Minimum review threshold: configurable via CLICSHOPPING_APP_RECOMMENDATIONS_PR_MIN_REVIEWS
 *   Below this threshold, the sentiment score weight is reduced
 *   (progressive degradation via SentimentScorer::getSignificanceWeight()).
 */
class RecommendationsAdmin
{
  private mixed $db;
  private SentimentScorer $scorer;

  public function __construct()
  {
    $this->db     = Registry::get('Db');
    $this->scorer = new SentimentScorer(k: 5.0, globalMean: 0.0);
  }

  // ════════════════════════════════════════════════════════════════
  // Public entry point — strategy dispatcher
  // ════════════════════════════════════════════════════════════════

  /**
   * Computes the recommendation score for a product.
   *
   * @param int         $productsId         Product identifier
   * @param float       $productsRateWeight Product rating weight (0–1, e.g. 0.8)
   * @param float|null  $reviewRate         Raw rating [0–5]
   * @param float|null  $userFeedback       Current user feedback [-1, 1]
   * @param string|null $strategy           'Range' or 'MultipleSources'
   * @param float|null  $sentimentScore     GPT score of current review [-1, 1]
   *                                        (DO NOT confuse with historical vote score)
   *
   * @return float Final score in [-1, 1]
   */
  public function calculateRecommendationScore(
    int         $productsId,
    float       $productsRateWeight = 0.8,
    float|null  $reviewRate = 0,
    float|null  $userFeedback = 0,
    string|null $strategy = 'Range',
    float|null  $sentimentScore = null
  ): float {
    // — 1. Normalize inputs into [-1, 1] —

    // Rating [0–5] → [-1, 1]
    $reviewRateNorm = SentimentScorer::normalizeRating((float)$reviewRate);

    // Current feedback → [-1, 1]
    $userFeedbackNorm = SentimentScorer::clamp((float)$userFeedback);

    // GPT score of current review → [-1, 1] (or null if absent)
    $sentimentScoreNorm = is_null($sentimentScore) ? null : SentimentScorer::clamp($sentimentScore);

    // — 2. Historical vote score (distinct from current GPT score) —
    // Returns [normalized score, review count]
    [$voteSentimentScore, $reviewCount] = $this->getVoteSentimentScore($productsId);

    // — 3. Minimum review threshold (config or default 3) —
    $minReviews = \defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_MIN_REVIEWS') ? (int)CLICSHOPPING_APP_RECOMMENDATIONS_PR_MIN_REVIEWS : 3;

    // Significance weight: reduced if too few reviews
    $significanceWeight = $this->scorer->getSignificanceWeight($reviewCount, $minReviews);

    // — 4. Dispatch to selected strategy —
    if ($strategy === 'Range') {
      return $this->calculateScoreBasedOnRange(
        $productsRateWeight,
        $reviewRateNorm,
        $userFeedbackNorm,
        $sentimentScoreNorm,
        $voteSentimentScore,
        $significanceWeight
      );
    }

    return $this->calculateScoreWithMultipleSources(
      $productsId,
      $productsRateWeight,
      $reviewRateNorm,
      $userFeedbackNorm,
      $sentimentScoreNorm,
      $voteSentimentScore,
      $significanceWeight
    );
  }

  // ════════════════════════════════════════════════════════════════
  // Strategy 1: Range
  // ════════════════════════════════════════════════════════════════

  /**
   * Range strategy — weights normalized to 1.0.
   *
   * Sources used:
   *   - reviewRate       (normalized star rating)
   *   - userFeedback     (current vote)
   *   - sentimentScore   (current GPT, optional)
   *   - voteSentiment    (historical aggregate, weighted by significance)
   *
   * All sources are in [-1, 1].
   * Weight sum always normalized to 1.0.
   *
   * @return float Score in [-1, 1]
   */
  private function calculateScoreBasedOnRange(
    float  $productsRateWeight,
    float  $reviewRateNorm,
    float  $userFeedbackNorm,
    ?float $sentimentScoreNorm,
    float  $voteSentimentScore,
    float  $significanceWeight
  ): float {
    // Base weights
    $wReview    = max(0.0, min(1.0, $productsRateWeight)); // star rating
    $wFeedback  = 0.2;                                     // current feedback
    $wVote      = 0.3 * $significanceWeight;               // historical votes

    // Current GPT sentiment weight (config or default)
    $wSentiment = !is_null($sentimentScoreNorm) ? (float)(\defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_WEIGHTING_SENTIMENT') ? CLICSHOPPING_APP_RECOMMENDATIONS_PR_WEIGHTING_SENTIMENT : 0.5) : 0.0;

    // Total effective weight
    $totalWeight = $wReview + $wFeedback + $wVote + $wSentiment;

    if ($totalWeight <= 0.0) {
      return 0.0;
    }

    // Weighted raw score
    $rawScore = ($reviewRateNorm     * $wReview)
      + ($userFeedbackNorm   * $wFeedback)
      + ($voteSentimentScore * $wVote)
      + (($sentimentScoreNorm ?? 0.0) * $wSentiment);

    // Normalize → [-1, 1]
    $score = $rawScore / $totalWeight;

    return SentimentScorer::clamp($score);
  }

  // ════════════════════════════════════════════════════════════════
  // Strategy 2: MultipleSources
  // ════════════════════════════════════════════════════════════════

  /**
   * MultipleSources strategy — all available signals.
   *
   * Sources used:
   *   - reviewRate         (normalized star rating)
   *   - userFeedback       (current vote)
   *   - sentimentScore     (current GPT, optional)
   *   - voteSentiment      (historical aggregate)
   *   - salesDataScore     (sales → [-1, 1])
   *   - externalScore      (external recommendations → [-1, 1])
   *   - popularityScore    (historical frequency → [-1, 1])
   *
   * All sources are in [-1, 1].
   * Weight sum always normalized to 1.0.
   *
   * @return float Score in [-1, 1]
   */
  private function calculateScoreWithMultipleSources(
    int    $productsId,
    float  $productsRateWeight,
    float  $reviewRateNorm,
    float  $userFeedbackNorm,
    ?float $sentimentScoreNorm,
    float  $voteSentimentScore,
    float  $significanceWeight
  ): float {
    // External sources [0,1] → [-1, 1]
    $salesScore    = SentimentScorer::normalize01To11($this->getSalesDataScore($productsId));
    $externalScore = SentimentScorer::normalize01To11($this->getExternalRecommendationScore($productsId));
    $popularScore  = SentimentScorer::normalize01To11($this->getPopularityScore($productsId));

    // Weights (config or defaults)
    $wReview    = max(0.0, min(1.0, $productsRateWeight));
    $wFeedback  = 0.15;
    $wVote      = 0.2 * $significanceWeight;
    $wSales     = (float)(\defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_WEIGHT_SALES') ? CLICSHOPPING_APP_RECOMMENDATIONS_PR_WEIGHT_SALES : 0.2);
    $wExternal  = (float)(\defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_WEIGHT_EXTERNAL') ? CLICSHOPPING_APP_RECOMMENDATIONS_PR_WEIGHT_EXTERNAL : 0.15);
    $wPopular   = 0.1;

    $wSentiment = !is_null($sentimentScoreNorm) ? (float)(\defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_WEIGHTING_SENTIMENT') ? CLICSHOPPING_APP_RECOMMENDATIONS_PR_WEIGHTING_SENTIMENT : 0.4) : 0.0;

    // Normalize weights → sum = 1.0
    $totalWeight = $wReview + $wFeedback + $wVote + $wSales + $wExternal + $wPopular + $wSentiment;

    if ($totalWeight <= 0.0) {
      return 0.0;
    }

    // Weighted score
    $rawScore = ($reviewRateNorm         * $wReview)
      + ($userFeedbackNorm       * $wFeedback)
      + ($voteSentimentScore     * $wVote)
      + ($salesScore             * $wSales)
      + ($externalScore          * $wExternal)
      + ($popularScore           * $wPopular)
      + (($sentimentScoreNorm ?? 0.0) * $wSentiment);

    $score = $rawScore / $totalWeight;

    return SentimentScorer::clamp($score);
  }
// ════════════════════════════════════════════════════════════════
// Data sources — all return [0, 1] (except voteSentiment)
// ════════════════════════════════════════════════════════════════

  /**
   * Historical sentiment score based on customer votes.
   *
   * Returns a pair [score [-1,1], review count].
   * The review count is required to compute significance.
   *
   * DISTINCT from the GPT score of the current review ($sentimentScore parameter).
   * These two signals are complementary:
   *   - voteSentiment = historical community trend
   *   - sentimentScore GPT = analysis of the current submitted review
   *
   * @return array{float, int}  [normalized score in [-1,1], review count]
   */
  private function getVoteSentimentScore(int $productsId): array
  {
    $Q = $this->db->prepare('SELECT COUNT(*) as n, SUM(rv.sentiment) as s
                            FROM :table_reviews_vote rv
                            JOIN :table_reviews r ON r.reviews_id = rv.reviews_id
                            WHERE r.products_id = :pid
                          ');
    $Q->bindInt(':pid', $productsId);
    $Q->execute();

    $n = (int)$Q->value('n');
    $s = (float)$Q->value('s');

    if ($n === 0) {
      return [0.0, 0];
    }

    $score = $this->scorer->compute($s, $n);

    return [$score, $n];
  }

  /**
   * Popularity score based on getMostRecommendedProducts().
   *
   * Measures how frequently this product appears in historical
   * positive recommendations, normalized by the maximum observed
   * across the catalog → returns [0, 1].
   *
   * Useful signal to give a favorable prior to products
   * consistently well-rated over time.
   *
   * @return float Score in [0, 1]
   */
  private function getPopularityScore(int $productsId): float
  {
    // Retrieve top recommended products (all categories, no date filter)
    $topProducts = $this->getMostRecommendedProducts(limit: 50, customers_group_id: 99, date: null);

    if (empty($topProducts)) {
      return 0.0;
    }

    // Maximum recommendation_count observed
    $maxCount = max(array_column($topProducts, 'recommendation_count'));

    if ($maxCount <= 0) {
      return 0.0;
    }

    // Find current product in the list
    foreach ($topProducts as $product) {
      if ((int)$product['products_id'] === $productsId) {
        return max(0.0, min(1.0, (float)$product['recommendation_count'] / $maxCount));
      }
    }

    // Product not in top → minimal score
    return 0.0;
  }

  /**
   * Sales score normalized by catalog maximum.
   *
   * @return float Score in [0, 1]
   */
  private function getSalesDataScore(int $productsId): float
  {
    $Qcur = $this->db->prepare('SELECT products_ordered as po
                              FROM :table_products
                              WHERE products_id = :pid
                             ');
    $Qcur->bindInt(':pid', $productsId);
    $Qcur->execute();
    $current = (float)$Qcur->valueDecimal('po');

    $Qmax = $this->db->prepare('SELECT max(products_ordered) as max_po
                              FROM :table_products
                             ');
    $Qmax->execute();
    $max = (float)$Qmax->valueDecimal('max_po');

    if ($max <= 0) {
      return 0.0;
    }

    return max(0.0, min(1.0, $current / $max));
  }

  /**
   * External recommendation score normalized.
   * Uses min/max bounds from configuration.
   *
   * @return float Score in [0, 1]
   */
  private function getExternalRecommendationScore(int $productsId): float
  {
    $Qavg = $this->db->prepare('SELECT avg(score) as avg_score
                              FROM :table_products_recommendations
                              WHERE products_id = :pid
                             ');
    $Qavg->bindInt(':pid', $productsId);
    $Qavg->execute();
    $avg = (float)$Qavg->valueDecimal('avg_score');

    $min = \defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_MIN_SCORE') ? (float)CLICSHOPPING_APP_RECOMMENDATIONS_PR_MIN_SCORE : 0.0;
    $max = \defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_MAX_SCORE') ? (float)CLICSHOPPING_APP_RECOMMENDATIONS_PR_MAX_SCORE : 5.0;

    if ($max <= $min) {
      return 0.0;
    }

    $normalized = ($avg - $min) / ($max - $min);
    return max(0.0, min(1.0, $normalized));
  }

// ════════════════════════════════════════════════════════════════
// Product average rating computation
// ════════════════════════════════════════════════════════════════

  /**
   * Returns the raw average rating [0–5] of a product.
   * This value is normalized in calculateRecommendationScore()
   * via SentimentScorer::normalizeRating() before usage.
   *
   * @param int $products_id Product identifier
   * @return float Raw average rating, or 0 if no reviews
   */
  public function calculateProductsRateWeight(int $products_id): float
  {
    $Qcheck = $this->db->prepare('SELECT avg(reviews_rating) as average
                                FROM :table_reviews
                                WHERE products_id = :products_id
                               ');
    $Qcheck->bindInt(':products_id', $products_id);
    $Qcheck->execute();

    $review = $Qcheck->fetch();

    if (!$review || $review['average'] === null) {
      return 0.0;
    }

    return (float)$review['average'];
  }

// ════════════════════════════════════════════════════════════════
// Analysis of recommended / rejected products
// ════════════════════════════════════════════════════════════════

  /**
   * Returns the most frequently recommended products.
   *
   * Used by:
   *   - getPopularityScore(): historical popularity signal in scoring
   *   - Admin dashboard: display of featured products
   *
   * @param int         $limit              Maximum number of products
   * @param string|int  $customers_group_id Customer group (99 = all)
   * @param string|null $date               Start date in 'Y-m-d' format, or null
   *
   * @return array Array of products with products_id, recommendation_count, score
   */
  public function getMostRecommendedProducts(
    int        $limit = 10,
    string|int $customers_group_id = 99,
    string|null $date = null
  ): array {
    if ($customers_group_id == 99) {
      $customers_group = 'AND customers_group_id >= 0';
    } elseif ($customers_group_id > 0) {
      $customers_group = 'AND customers_group_id = ' . (int)$customers_group_id;
    } else {
      $customers_group = 'AND customers_group_id = 0';
    }

    $minRecommendedScore = \defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_MIN_SCORE') ? (float)CLICSHOPPING_APP_RECOMMENDATIONS_PR_MIN_SCORE : 0.0;

    $date_analyse = '';
    if (!empty($date)) {
      $currentDate  = date('Y-m-d');
      $date_analyse = ' AND recommendation_date BETWEEN :date_start AND :date_end ';
    }

    $QmostRecommended = $this->db->prepare('SELECT products_id,
                                                   COUNT(*) as recommendation_count,
                                                   recommendation_date,
                                                   MAX(score) as score
                                            FROM :table_products_recommendations
                                            WHERE score >= :minRecommendedScore
                                                  ' . $customers_group . '
                                                  ' . $date_analyse . '
                                            GROUP BY products_id
                                            ORDER BY recommendation_count DESC
                                            LIMIT :limit
                                           ');

    $QmostRecommended->bindInt(':limit', $limit);
    $QmostRecommended->bindDecimal(':minRecommendedScore', $minRecommendedScore);

    if (!empty($date)) {
      $QmostRecommended->bindValue(':date_start', $date);
      $QmostRecommended->bindValue(':date_end', date('Y-m-d'));
    }

    $QmostRecommended->execute();

    return $QmostRecommended->fetchAll();
  }

  /**
   * Returns the most frequently rejected products.
   *
   * @param int         $limit              Maximum number of products
   * @param int         $customers_group_id Customer group (99 = all)
   * @param string|null $date               Start date in 'Y-m-d' format, or null
   *
   * @return array Array of products with products_id, rejection_count, score
   */
  public function getRejectedProducts(
    int        $limit = 10,
    int        $customers_group_id = 99,
    string|null $date = null
  ): array {
    if ($customers_group_id == 99) {
      $customers_group = '';
    } elseif ($customers_group_id > 0) {
      $customers_group = ' AND customers_group_id = ' . (int)$customers_group_id;
    } else {
      $customers_group = ' AND customers_group_id = 0';
    }

    $maxRejectedScore = \defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_MAX_SCORE') ? (float)CLICSHOPPING_APP_RECOMMENDATIONS_PR_MAX_SCORE : 0.0;

    $date_analyse = '';
    if (!empty($date)) {
      $date_analyse = ' AND recommendation_date BETWEEN :date_start AND :date_end ';
    }

    $QrejectedProducts = $this->db->prepare('SELECT products_id,
                                                    COUNT(*) as rejection_count,
                                                    recommendation_date,
                                                    MAX(score) as score
                                             FROM :table_products_recommendations
                                             WHERE score < :maxRejectedScore
                                                   ' . $customers_group . '
                                                   ' . $date_analyse . '
                                             GROUP BY products_id
                                             ORDER BY rejection_count DESC
                                             LIMIT :limit
                                            ');

    $QrejectedProducts->bindInt(':limit', $limit);
    $QrejectedProducts->bindDecimal(':maxRejectedScore', $maxRejectedScore);

    if (!empty($date)) {
      $QrejectedProducts->bindValue(':date_start', $date);
      $QrejectedProducts->bindValue(':date_end', date('Y-m-d'));
    }

    $QrejectedProducts->execute();

    return $QrejectedProducts->fetchAll();
  }
}
