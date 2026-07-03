<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring;

  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors\CreationDateFactor;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors\FactorInterface;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors\RatioFactor;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors\ScoreFactor;

  /**
   * ProductScoreAxis — Score_X
   *
   * Measures intrinsic quality of a product sheet.
   *
   * Product data keys expected in $product array (built by DataCollector Step 1):
   *
   *   From clic_products:
   *     - products_image          : string  — main image filename (non-empty = has image)
   *     - products_image_medium   : string  — medium image filename
   *     - products_image_zoom     : string  — zoom image filename
   *     - products_image_small    : string  — small image filename
   *     - products_date_added     : string|\DateTimeImmutable — catalog creation date
   *     - products_model          : string  — model/reference
   *     - products_sku            : string  — SKU
   *     - products_ean            : string  — EAN barcode
   *     - manufacturers_id        : int     — manufacturer link
   *     - products_weight         : float   — weight
   *     - products_quantity       : int     — stock level
   *
   *   From clic_products_description (language-specific):
   *     - products_description         : string — full description
   *     - products_description_summary : string — short summary
   *     - products_head_title_tag      : string — SEO title
   *     - products_head_desc_tag       : string — SEO meta description
   *     - products_head_keywords_tag   : string — SEO meta keywords
   *
   *   From Context (SEO analysis):
   *     - context->seoScore  (float|null) — SEO score if ANALYZED
   *
   * Factors:
   *   - description   : ScoreFactor  (computed from description text)       weight: 2.0
   *   - images        : RatioFactor  (filled image slots / MAX_IMAGE_SLOTS) weight: 1.5
   *   - keywords      : RatioFactor  (SEO keywords/tags completeness)       weight: 1.5
   *   - creation_date : RatioFactor  (freshness over 2-year reference)      weight: 1.0
   *   - completeness  : RatioFactor  (filled identifier/attr fields ratio)  weight: 1.5
   *   - seo_score     : ScoreFactor  (seo_score / 100, conditional)         weight: 2.5
   *
   * Description score formula:
   *   - 0.6 * description_length_ratio (capped at TARGET_DESC_LENGTH chars)
   *   - 0.2 * (summary present ? 1 : 0)
   *   - 0.2 * (meta description present ? 1 : 0)
   *   Result [0..1] → used as score/100
   *
   * Completeness score: count non-empty identifier/attribute fields / TOTAL_COMPLETENESS_FIELDS
   *   Fields: model, sku, ean, manufacturers_id, weight, description_summary, head_title_tag
   */
  class ProductScoreAxis implements ScoringAxisInterface
  {
    private const TARGET_DESC_LENGTH         = 500;  // chars for "complete" description
    private const FRESHNESS_DAYS_REF         = 730;  // 2 years reference for freshness
    private const MAX_IMAGE_SLOTS            = 2;    // products_image + products_image_zoom (only 2 exist in DB)
    private const TOTAL_COMPLETENESS_FIELDS  = 7;    // fields counted for completeness score

    // Weights re-balanced to accommodate the SEO Quality Benchmark factor.
    // seo_score is the crawler-side score (HTML output); seo_quality is the
    // content-vs-source benchmark (entropy, diversity, entity coverage,
    // repetition) produced by SeoObservability.  The two cover
    // complementary aspects of SEO health and are weighted accordingly:
    // crawler score keeps the higher weight as it reflects what Google sees.
    private array $weights = [
      'description'   => 2.0,
      'images'        => 1.5,
      'keywords'      => 1.5,
      'creation_date' => 1.0,
      'completeness'  => 1.5,
      'seo_score'     => 2.0,  // ↓ from 2.5 to leave room for seo_quality
      'seo_quality'   => 1.5,  // new — SEO benchmark composite (Phase 2 guard)
    ];

    public function getCode(): string
    {
      return 'X';
    }

    public function getWeights(): array
    {
      return $this->weights;
    }

    public function getFactors(array $product, Context $context): array
    {
      $factors = [];

      // description: computed quality score from products_description content
      $descScore = $this->computeDescriptionScore($product);
      $factors['description'] = new ScoreFactor($descScore, 1.0);

      // images: ratio of filled image fields / MAX_IMAGE_SLOTS (only 2 fields exist in DB)
      $imageSlots = 0;
      foreach (['products_image', 'products_image_zoom'] as $field) {
        if (!empty($product[$field])) {
          $imageSlots++;
        }
      }
      $imageRatio = ($imageSlots > 0) ? (float) $imageSlots / self::MAX_IMAGE_SLOTS : null;
      $factors['images'] = new RatioFactor($imageRatio);

      // keywords: ratio based on whether SEO keyword/meta fields are filled
      $keywordsRatio = $this->computeKeywordsRatio($product);
      $factors['keywords'] = new RatioFactor($keywordsRatio);

      // creation_date: freshness — newer = higher
      $createdAt = $product['products_date_added'] ?? null;
      $freshnessRatio = null;
      if (!empty($createdAt)) {
        $createdDate = ($createdAt instanceof \DateTimeInterface)
          ? $createdAt
          : new \DateTimeImmutable($createdAt);
        $daysSince = (int) (new \DateTimeImmutable('now'))->diff($createdDate)->days;
        $freshnessRatio = max(0.0, 1.0 - ($daysSince / self::FRESHNESS_DAYS_REF));
      }
      $factors['creation_date'] = new RatioFactor($freshnessRatio);

      // completeness: ratio of filled identifier/attribute fields
      $completenessRatio = $this->computeCompletenessRatio($product);
      $factors['completeness'] = new RatioFactor($completenessRatio);


      // seo_score: resolved from clic_seo_serp_reports via Context (injected by DataCollector Step 1).
      //
      // SQL query used by the DataCollector/Orchestrator to populate context->seoScore:
      //
      //   SELECT seo_score_after
      //   FROM :table_seo_serp_reports
      //   WHERE entity_type = 'product'
      //     AND entity_id   = :product_id
      //     AND language_id = :language_id
      //     AND seo_score_after > 0
      //   ORDER BY created_at DESC
      //   LIMIT 1
      //
      // seo_score_after [0..100] is the score produced by the SEO agent after analysis.
      // If no row is found, seoStatus stays 'NOT_ANALYZED' and seoScore stays null.
      // The Context is populated in CockpitAIOrchestrator::collectProductData() (Step 1).


      // seo_score: conditional on SEO status in context
      if ($context->isSeoAnalyzed()) {
        $factors['seo_score'] = new ScoreFactor($context->seoScore, 100.0);
      } else {
        $factors['seo_score'] = new ScoreFactor(null, 100.0, notAnalyzed: true);
      }

      // seo_quality: composite benchmark from clic_seo_quality_benchmark_log
      // (range 0..1 — already normalized).  When a regression verdict is
      // present, hard-cap the contribution to 0.5 so a regressed product
      // cannot mask its issue behind a misleadingly high seo_score.  When
      // no benchmark exists for this product/language, mark as notAnalyzed.
      if ($context->seoBenchmarkScore !== null) {
        $benchmarkValue = $context->seoBenchmarkScore;
        if ($context->seoBenchmarkVerdict === 'regression') {
          $benchmarkValue = min($benchmarkValue, 0.5);
        }
        $factors['seo_quality'] = new ScoreFactor($benchmarkValue, 1.0);
      } else {
        $factors['seo_quality'] = new ScoreFactor(null, 1.0, notAnalyzed: true);
      }

      // REQ-SC-01 : Récupération de l'âge du produit et du max catalogue
      $dateAdded = new \DateTimeImmutable($product['products_date_added'] ?? 'now');
      $ageDays = (int) $dateAdded->diff(new \DateTimeImmutable())->format('%a');

      $maxDays = $context->catalog->ageMax ?? 365;

      // Ajout du facteur de fraîcheur
      $factors['creation_date'] = new CreationDateFactor($ageDays, $maxDays);

      return $factors;
    }

    /**
     * Compute description quality score [0..1] from raw text fields.
     *   - 60% from description length (capped at TARGET_DESC_LENGTH chars)
     *   - 20% from products_description_summary present
     *   - 20% from products_head_desc_tag present
     *
     * Returns null if products_description is missing entirely.
     */
    private function computeDescriptionScore(array $product): ?float
    {
      $desc = $product['products_description'] ?? null;

      if (empty($desc)) {
        return null;
      }

      $descLength = mb_strlen(strip_tags((string) $desc));
      $lengthRatio = min(1.0, $descLength / self::TARGET_DESC_LENGTH);

      $hasSummary = !empty($product['products_description_summary']) ? 1.0 : 0.0;
      $hasMetaDesc = !empty($product['products_head_desc_tag']) ? 1.0 : 0.0;

      return (0.6 * $lengthRatio) + (0.2 * $hasSummary) + (0.2 * $hasMetaDesc);
    }

    /**
     * Compute keywords completeness ratio [0..1] with quality scoring.
     *
     * Scoring breakdown:
     *   - products_head_keywords_tag: 0.5 total
     *     • 0.25 for presence
     *     • 0.25 for quality (5+ keywords, properly formatted)
     *   - products_head_title_tag: 0.5 total
     *     • 0.25 for presence
     *     • 0.25 for quality (50-70 chars optimal length)
     *
     * Returns null if both fields are missing from the product array.
     */
    private function computeKeywordsRatio(array $product): ?float
    {
      if (!array_key_exists('products_head_keywords_tag', $product) &&
        !array_key_exists('products_head_title_tag', $product)) {
        return null;
      }

      $score = 0.0;
      $debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';

      // ── Keywords scoring (0.5 total) ──────────────────────────────────
      $keywords = $product['products_head_keywords_tag'] ?? '';
      if (!empty($keywords)) {
        // Presence: 0.25
        $score += 0.25;

        // Quality: 0.25 based on keyword count and formatting
        // Split by comma, filter empty values, count
        $keywordArray = array_filter(
          array_map('trim', explode(',', $keywords)),
          fn($k) => !empty($k)
        );
        $keywordCount = count($keywordArray);

        if ($keywordCount >= 5) {
          // Optimal: 5+ keywords
          $score += 0.25;
        } elseif ($keywordCount > 0) {
          // Partial: proportional to 5 keywords target
          $score += ($keywordCount / 5.0) * 0.25;
        }

        if ($debug) {
          error_log(sprintf(
            '[CockpitAI] Keywords factor: "%s" → %d keywords → score %.3f',
            mb_substr($keywords, 0, 50),
            $keywordCount,
            $score
          ));
        }
      } else {
        if ($debug) {
          error_log('[CockpitAI] Keywords factor: EMPTY');
        }
      }

      // ── Title tag scoring (0.5 total) ─────────────────────────────────
      $title = $product['products_head_title_tag'] ?? '';
      if (!empty($title)) {
        // Presence: 0.25
        $score += 0.25;

        // Quality: 0.25 based on optimal length (50-70 chars for SEO)
        $titleLength = mb_strlen($title);
        $optimalMin = 50;
        $optimalMax = 70;

        if ($titleLength >= $optimalMin && $titleLength <= $optimalMax) {
          // Optimal length
          $score += 0.25;
        } elseif ($titleLength > 0) {
          // Penalize deviation from optimal range
          if ($titleLength < $optimalMin) {
            // Too short: proportional to optimal min
            $score += ($titleLength / $optimalMin) * 0.25;
          } else {
            // Too long: penalize excess beyond optimal max
            $excess = $titleLength - $optimalMax;
            $penalty = min(1.0, $excess / 30.0); // Full penalty at +30 chars
            $score += (1.0 - $penalty) * 0.25;
          }
        }

        if ($debug) {
          error_log(sprintf(
            '[CockpitAI] Title tag factor: "%s" → %d chars → score %.3f',
            mb_substr($title, 0, 50),
            $titleLength,
            $score
          ));
        }
      } else {
        if ($debug) {
          error_log('[CockpitAI] Title tag factor: EMPTY');
        }
      }

      if ($debug) {
        error_log(sprintf('[CockpitAI] Total keywords ratio: %.3f', $score));
      }

      return $score;
    }

    /**
     * Compute product completeness ratio [0..1].
     * Counts non-empty values across TOTAL_COMPLETENESS_FIELDS key fields:
     *   products_model, products_sku, products_ean,
     *   manufacturers_id, products_weight,
     *   products_description_summary, products_head_title_tag
     *
     * Returns null if no completeness fields are present in the product array.
     */
    private function computeCompletenessRatio(array $product): ?float
    {
      $fields = [
        'products_model',
        'products_sku',
        'products_ean',
        'manufacturers_id',
        'products_weight',
        'products_description_summary',
        'products_head_title_tag',
      ];

      // If none of these keys exist, treat as missing
      $anyPresent = false;
      foreach ($fields as $field) {
        if (array_key_exists($field, $product)) {
          $anyPresent = true;
          break;
        }
      }

      if (!$anyPresent) {
        return null;
      }

      $filled = 0;
      foreach ($fields as $field) {
        if (!empty($product[$field]) && $product[$field] !== '0') {
          $filled++;
        }
      }

      return (float) $filled / self::TOTAL_COMPLETENESS_FIELDS;
    }

    /**
     * Compute Axis Score based on Global Deterministic Formula (REQ-SC-03).
     * * Formula: ∑(normalized_value_i * weight_i) / ∑(all_declared_weights) * 100
     * * @param array<string, FactorInterface> $factors
     * @return float Score [0..100]
     */
    public function computeScore(array $factors): float
    {
      $weightedSum = 0.0;
      $totalDeclaredWeights = 0.0;

      // 1. Calcul du dénominateur fixe (Somme de TOUS les poids déclarés)
      foreach ($this->weights as $weight) {
        $totalDeclaredWeights += $weight;
      }

      if ($totalDeclaredWeights === 0.0) {
        return 0.0;
      }

      // 2. Calcul du numérateur
      foreach ($factors as $code => $factor) {
        /** @var FactorInterface $factor */
        $weight = $this->weights[$code] ?? 0.0;

        // Gestion spécifique du SEO (REQ-SC-02)
        if ($code === 'seo' && $factor->getStatus() === 'not_analyzed') {
          $weightedSum += ($weight * 0.0);
          continue;
        }

        // Pour les autres facteurs, on n'ajoute au numérateur que s'ils sont valides
        if ($factor->getStatus() === 'valid') {
          $weightedSum += ($weight * $factor->normalize());
        } else {
          // Si invalide ou manquant, la valeur est implicitement 0
          // Le poids reste présent au dénominateur via l'étape 1
          $weightedSum += ($weight * 0.0);
        }
      }

      // Résultat final sur 100
      return ($weightedSum / $totalDeclaredWeights) * 100.0;
    }
  }
