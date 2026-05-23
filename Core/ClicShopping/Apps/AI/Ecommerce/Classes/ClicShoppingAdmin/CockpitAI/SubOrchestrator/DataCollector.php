<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator;

  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\ProductStock;

  /**
   * DataCollector
   *
   * Pipeline Step 1 — Read-only MariaDB data collection.
   * (Requirements 10.1, 21.2, 22.2)
   *
   * Collects all data needed by the scoring axes (Score_X and Score_Y) and the
   * RecommendationEngine for a single product in a single pipeline run.
   *
   * Timeout: 2 s (enforced by the caller — PipelineRunner).
   * On failure: throw exception → pipeline aborts (Step 1 is critical).
   *
   * ── Data collected ───────────────────────────────────────────────────────────
   *
   * Score_X (product quality) sources:
   *   clic_products              : image, zoom image, date_added, model, sku, ean,
   *                                manufacturers_id, weight, quantity
   *   clic_products_description  : name, description, summary, SEO title/desc/keywords,
   *                                products_viewed
   *
   * Score_Y (commercial performance) sources:
   *   clic_products_viewed       : views last 30 days
   *   clic_orders_products       : order count, total quantity
   *   clic_orders_products_download + clic_orders : return count
   *   clic_specials              : active promotion
   *   clic_products              : products_view flag
   *   clic_products_favorites    : favorites count
   *   clic_products_featured     : featured status (status = 1 = active)
   *   clic_reviews               : approved review count
   *   clic_products_xsell        : cross-recommendation count
   *   clic_products_notifications: tracking count
   *
   * SEO source:
   *   clic_seo_serp_reports      : latest seo_score_after for this product/language
   *                                (see Apps/AI/Ecommerce/Sql/MariaDb/MariaDb.php line 319)
   */
  class DataCollector
  {
    private const TIMEOUT_SECONDS = 2.0;

    private mixed $db;
    private mixed $debug;

    public function __construct()
    {
      $this->db = Registry::get('Db');
      $this->debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';
    }

    /**
     * Collect all product data required for a full CockpitAI analysis.
     *
     * @param int $productId   Product ID
     * @param int $languageId  Language ID
     * @return array           Product data array (keys documented below)
     * @throws \Exception      If product not found, DB error, or timeout
     */
    public function collect(int $productId, int $languageId): array
    {
      $startTime = microtime(true);

      try {
        // ── Core product + description ──────────────────────────────────────
        // Note: products_image_medium / products_image_small do not exist in
        //       clic_products — only products_image and products_image_zoom.
        // Note: products_featured_status does not exist - using products_view instead
        $Qproduct = $this->db->prepare(' SELECT p.products_id,
                                                 p.products_model,
                                                 p.products_sku,
                                                 p.products_ean,
                                                 p.products_image,
                                                 p.products_image_zoom,
                                                 p.products_date_added,
                                                 p.products_status,
                                                 p.products_weight,
                                                 p.products_quantity,
                                                 p.products_price,
                                                 p.products_cost,
                                                 p.products_handling,
                                                 p.manufacturers_id,
                                                 p.products_ordered,
                                                 p.products_view,
                                                 p.products_date_available,
                                                 pd.products_name,
                                                 pd.products_description,
                                                 pd.products_description_summary,
                                                 pd.products_head_title_tag,
                                                 pd.products_head_desc_tag,
                                                 pd.products_head_keywords_tag,
                                                 pd.products_viewed
                                          FROM :table_products p
                                          LEFT JOIN :table_products_description pd
                                            ON p.products_id = pd.products_id
                                           AND pd.language_id = :language_id
                                          WHERE p.products_id = :product_id
                                          LIMIT 1
                                        ');
        $Qproduct->bindInt(':product_id', $productId);
        $Qproduct->bindInt(':language_id', $languageId);
        $Qproduct->execute();

        if ($Qproduct->rowCount() === 0) {
          throw new \Exception("Product {$productId} not found");
        }

        $product = $Qproduct->fetch();
        $this->checkTimeout($startTime, 'product query');

        // ── Additional image count (clic_products_images) ──────────────────
        $Qimages = $this->db->prepare(' SELECT COUNT(*) AS image_count
                                        FROM :table_products_images
                                        WHERE products_id = :product_id
                                      ');
        $Qimages->bindInt(':product_id', $productId);
        $Qimages->execute();
        $imageCount = $Qimages->valueInt('image_count');

        // ── Attribute count ────────────────────────────────────────────────
        $Qattr = $this->db->prepare('SELECT COUNT(DISTINCT options_id) AS attribute_count
                                      FROM :table_products_attributes
                                      WHERE products_id = :product_id
                                    ');
        $Qattr->bindInt(':product_id', $productId);
        $Qattr->execute();
        $attributeCount = $Qattr->valueInt('attribute_count');

        // ── Views last 30 days ─────────────────────────────────────────────
        // date_viewed n'existe pas dans products_description.
        // Stratégie :
        //   1. Si CLICSHOPPING_APP_ECOMMERCE_CAI_PRODUCT_TRACKING = True → lire depuis tracking (données précises)
        //   2. Sinon → utiliser products_viewed (cumulatif, proxy imparfait mais disponible)
        // Dans les deux cas, on respecte products_date_available : un produit disponible
        // depuis moins de 30 jours ne peut pas avoir 30 jours de vues.
        $views30d = 0;

        if (\defined('CLICSHOPPING_APP_ECOMMERCE_CAI_PRODUCT_TRACKING') && CLICSHOPPING_APP_ECOMMERCE_CAI_PRODUCT_TRACKING === 'True') {
          // Source précise : tracking pondéré sur 30 jours
          $Qviews30d = $this->db->prepare('SELECT COUNT(*) AS views_30d
                                           FROM :table_products_cockpit_ai_tracking_impressions
                                           WHERE products_id = :product_id
                                           AND language_id = :language_id
                                           AND displayed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)');
          $Qviews30d->bindInt(':product_id', $productId);
          $Qviews30d->bindInt(':language_id', $languageId);
          $Qviews30d->execute();
          $views30d = (int)$Qviews30d->valueInt('views_30d');
        } else {
          // Proxy : products_viewed cumulatif (pas de fenêtre temporelle)
          // Limité par la date de disponibilité du produit
          $views30d = (int)($product['products_viewed'] ?? 0);
        }

        // Respect de products_date_available : si le produit est disponible depuis
        // moins de 30 jours, les vues sont forcément <= jours_disponibles
        // (ne pas surestimer le trafic d'un produit récent)
        $dateAvailable = $product['products_date_available'] ?? $product['products_date_added'] ?? null;

        if ($dateAvailable && $dateAvailable !== '0000-00-00 00:00:00') {
          $daysAvailable = max(1, (int)((time() - strtotime($dateAvailable)) / 86400));
          if ($daysAvailable < 30) {
            // Pro-rata : views × (jours_disponibles / 30)
            $views30d = (int)round($views30d * ($daysAvailable / 30));
          }
        }

        // ── Views last 7 days — produit + moyenne catalogue ────────────────
        // Utilisés par PromotionScheduler adaptatif pour moduler les paliers P1..P4
        $Qviews7d = $this->db->prepare('SELECT COALESCE(SUM(products_viewed), 0) AS views_7d
                                        FROM :table_products_description
                                        WHERE products_id = :product_id
                                        AND language_id = :language_id');
        $Qviews7d->bindInt(':product_id', $productId);
        $Qviews7d->bindInt(':language_id', $languageId);
        $Qviews7d->execute();
        $views7d = (int)$Qviews7d->valueInt('views_7d');

        // Moyenne catalogue sur 7 jours (tous produits actifs, même langue)
        $Qavg7d = $this->db->prepare('SELECT COALESCE(AVG(pd.products_viewed), 0) AS avg_views_7d
                                      FROM :table_products_description pd
                                      INNER JOIN :table_products p ON pd.products_id = p.products_id
                                      WHERE pd.language_id = :language_id
                                      AND p.products_status = 1');
        $Qavg7d->bindInt(':language_id', $languageId);
        $Qavg7d->execute();
        $avgViews7d = (float)$Qavg7d->valueDecimal('avg_views_7d');

        // ── Orders ─────────────────────────────────────────────────────────
        $Qorders = $this->db->prepare(' SELECT COUNT(DISTINCT op.orders_id) AS order_count,
                                               COALESCE(SUM(op.products_quantity), 0) AS total_quantity
                                        FROM :table_orders_products op
                                        INNER JOIN :table_orders o ON op.orders_id = o.orders_id
                                        WHERE op.products_id = :product_id
                                        AND o.orders_status >= 3
                                      ');
        $Qorders->bindInt(':product_id', $productId);
        $Qorders->execute();
        $orderCount    = $Qorders->valueInt('order_count');
        $totalQuantity = $Qorders->valueInt('total_quantity');

        $this->checkTimeout($startTime, 'orders query');

        // ── Returns ────────────────────────────────────────────────────────
        /*
        $Qreturns = $this->db->prepare('
        SELECT COUNT(*) AS return_count
        FROM :table_orders_products_download opd
        INNER JOIN :table_orders o ON opd.orders_id = o.orders_id
        WHERE opd.products_id = :product_id
          AND o.orders_status = 4
      ');
        $Qreturns->bindInt(':product_id', $productId);
        $Qreturns->execute();
        $returnCount = $Qreturns->value('return_count');
        */
        $returnCount = 0;









          // ── Active special / promotion ─────────────────────────────────────
        $Qspecial = $this->db->prepare('SELECT COUNT(*) AS has_special
                                        FROM :table_specials
                                        WHERE products_id = :product_id
                                          AND status = 1
                                          AND (expires_date IS NULL OR expires_date > NOW())
                                      ');
        $Qspecial->bindInt(':product_id', $productId);
        $Qspecial->execute();
        $specialActive = $Qspecial->valueInt('has_special') > 0;


 // ── Featured (products_favorites) ──────────────────────────────────
        // A product is favorites when it has an active row in clic_products_favorites.
        // Table: clic_products_favorites (products_id, status, ...) — status=1 = active.
        $Qfavorites = $this->db->prepare(' SELECT COUNT(*) AS favorites_count
                                          FROM :table_products_favorites
                                          WHERE products_id = :product_id
                                          AND status = 1
                                        ');
        $Qfavorites->bindInt(':product_id', $productId);
        $Qfavorites->execute();
        $favorites = $Qfavorites->valueInt('favorites_count') > 0;

        // ── Reviews (approved) ─────────────────────────────────────────────
        $Qreviews = $this->db->prepare('SELECT COUNT(*) AS review_count
                                        FROM :table_reviews
                                        WHERE products_id = :product_id
                                        AND status = 1
                                      ');
        $Qreviews->bindInt(':product_id', $productId);
        $Qreviews->execute();
        $reviewCount = $Qreviews->valueInt('review_count');

        // ── Cross-recommendations ──────────────────────────────────────────
        /*
        $Qrec = $this->db->prepare('SELECT COUNT(*) AS recommendation_count
                                    FROM :table_products_xsell
                                    WHERE products_id = :product_id
                                  ');
        $Qrec->bindInt(':product_id', $productId);
        $Qrec->execute();
        $recommendationCount = $Qrec->valueInt('recommendation_count');
        */
        $recommendationCount = 0;

        // ── Product tracking (notifications) ──────────────────────────────
        $Qtrack = $this->db->prepare('
        SELECT COUNT(DISTINCT customers_id) AS tracking_count
        FROM :table_products_notifications
        WHERE products_id = :product_id
      ');
        $Qtrack->bindInt(':product_id', $productId);
        $Qtrack->execute();
        $trackingCount = $Qtrack->valueInt('tracking_count');

        $this->checkTimeout($startTime, 'feature flags queries');

        // ── SEO: latest score from clic_seo_serp_reports ───────────────────
        // seo_score_after > 0 means at least one completed SEO analysis exists.
        $Qseo = $this->db->prepare('
                                    SELECT seo_score_after
                                    FROM :table_seo_serp_reports
                                    WHERE entity_type = :entity_type
                                      AND entity_id   = :product_id
                                      AND language_id = :language_id
                                      AND seo_score_after > 0
                                    ORDER BY created_at DESC
                                    LIMIT 1
                                  ');
        $Qseo->bindValue(':entity_type', 'product');
        $Qseo->bindInt(':product_id', $productId);
        $Qseo->bindInt(':language_id', $languageId);
        $Qseo->execute();

        if ($this->debug) {
          error_log('[Info CockpitAI] seo score :' . $Qseo->valueDecimal('seo_score_after') . '-' . $Qseo->rowCount());
        }

        $seoStatus           = 'NOT_ANALYZED';
        $seoScore            = null;
        $seoThinContentLevel = null;
        $seoWordcountBody    = null;

        if ($Qseo->rowCount() > 0) {
          $rawScore = $Qseo->valueDecimal('seo_score_after') ?? 0;

          if ($rawScore > 0) {
            $seoStatus = 'ANALYZED';
            $seoScore  = $rawScore; // [0..100] — normalized ÷100 in ScoreFactor
          }

          // Try to extract the thin-content flag from the seo_after JSON
          // payload stored alongside the score.  Defensive: malformed JSON
          // or missing keys silently fall back to null so the analysis is
          // never blocked on the freshness probe.
          try {
            $Qafter = $this->db->prepare('
              SELECT seo_after
              FROM   :table_seo_serp_reports
              WHERE  entity_type = :entity_type
                AND  entity_id   = :entity_id
                AND  language_id = :language_id
                AND  seo_score_after > 0
              ORDER BY created_at DESC
              LIMIT 1
            ');
            $Qafter->bindValue(':entity_type', 'product');
            $Qafter->bindInt(':entity_id',     $productId);
            $Qafter->bindInt(':language_id',   $languageId);
            $Qafter->execute();
            if ($Qafter->rowCount() > 0) {
              $seoAfterJson = (string)$Qafter->value('seo_after');
              $seoAfterArr  = json_decode($seoAfterJson, true);
              if (is_array($seoAfterArr)) {
                if (isset($seoAfterArr['thin_content_level'])) {
                  $seoThinContentLevel = (string)$seoAfterArr['thin_content_level'];
                }
                if (isset($seoAfterArr['wordcount_body'])) {
                  $seoWordcountBody = (int)$seoAfterArr['wordcount_body'];
                }
              }
            }
          } catch (\Throwable $e) {
            if ($this->debug) {
              error_log('[Info CockpitAI] thin-content probe failed: ' . $e->getMessage());
            }
          }
        }

        // ── SEO Quality Benchmark — latest entry per (product, language) ───
        // The new Phase 2 anti-regression guard (SeoQualityBenchmark) writes
        // one row per optimization attempt in clic_seo_quality_benchmark_log.
        // We read the most recent non-unknown verdict so CockpitAI can:
        //   - factor benchmark composite into Score_X (semantic quality),
        //   - flag regressions in rules / autopilot decisions,
        //   - surface verdict trends in the dashboard.
        $seoBenchmarkScore      = null;
        $seoBenchmarkVerdict    = null;
        $seoBenchmarkReason     = null;
        $seoBenchmarkCoverage   = null;
        $seoBenchmarkDiversity  = null;
        $seoBenchmarkRepetition = null;
        try {
          $Qbm = $this->db->prepare('
            SELECT generated_score, verdict, regression_reason,
                   entity_coverage, generated_diversity, repetition
            FROM   :table_seo_quality_benchmark_log
            WHERE  entity_type = :entity_type
              AND  entity_id   = :entity_id
              AND  language_id = :language_id
              AND  verdict    != :unknown_verdict
            ORDER BY date_modified DESC
            LIMIT 1
          ');
          $Qbm->bindValue(':entity_type',     'product');
          $Qbm->bindInt(':entity_id',         $productId);
          $Qbm->bindInt(':language_id',       $languageId);
          $Qbm->bindValue(':unknown_verdict', 'unknown');
          $Qbm->execute();
          if ($Qbm->rowCount() > 0) {
            $seoBenchmarkScore      = (float)($Qbm->valueDecimal('generated_score')     ?? 0);
            $seoBenchmarkVerdict    = (string)$Qbm->value('verdict');
            $seoBenchmarkReason     = (string)$Qbm->value('regression_reason');
            $seoBenchmarkCoverage   = (float)($Qbm->valueDecimal('entity_coverage')     ?? 0);
            $seoBenchmarkDiversity  = (float)($Qbm->valueDecimal('generated_diversity') ?? 0);
            $seoBenchmarkRepetition = (float)($Qbm->valueDecimal('repetition')          ?? 0);
          }
        } catch (\Throwable $e) {
          // The table is created lazily by Apps/AI/Ecommerce/Sql/MariaDb.
          // Until the migration has run on this environment, silently fall
          // back to null values — never block the analysis pipeline.
          if ($this->debug) {
            error_log('[Info CockpitAI] benchmark query failed: ' . $e->getMessage());
          }
        }

        // ── Derived metrics ────────────────────────────────────────────────
        $conversionRate = $views30d > 0 ? ($orderCount / $views30d) : 0.0;
        $returnRate     = $orderCount > 0 ? ($returnCount / $orderCount) : 0.0;

        // ── Featured (products_featured) ──────────────────────────────────
        // Table: clic_products_featured (products_id, status, ...) — status=1 = active.
        $Qfeatured = $this->db->prepare('SELECT COUNT(*) AS featured_count
                                          FROM :table_products_featured
                                          WHERE products_id = :product_id
                                        AND status = 1
                                        ');
        $Qfeatured->bindInt(':product_id', $productId);
        $Qfeatured->execute();
        $featured = $Qfeatured->valueInt('featured_count') > 0;

        $result_array = [
          // Identity
          'product_id'  => $productId,
          'language_id' => $languageId,
          'name'        => $product['products_name'] ?? '',
          'status'      => (int) ($product['products_status'] ?? 0),

          // ── Score_X fields (ProductScoreAxis) ──────────────────────────
          'products_image'               => $product['products_image'] ?? '',
          'products_image_zoom'          => $product['products_image_zoom'] ?? '',
          'products_date_added'          => $product['products_date_added'] ?? '',
          'products_model'               => $product['products_model'] ?? '',
          'products_sku'                 => $product['products_sku'] ?? '',
          'products_ean'                 => $product['products_ean'] ?? '',
          'manufacturers_id'             => $product['manufacturers_id'] ?? null,
          'products_weight'              => $product['products_weight'] ?? null,
          'products_quantity'            => $product['products_quantity'] ?? null,
          'products_description'         => $product['products_description'] ?? '',
          'products_description_summary' => $product['products_description_summary'] ?? '',
          'products_head_title_tag'      => $product['products_head_title_tag'] ?? '',
          'products_head_desc_tag'       => $product['products_head_desc_tag'] ?? '',
          'products_head_keywords_tag'   => $product['products_head_keywords_tag'] ?? '',
          'image_count'                  => $imageCount,
          'attribute_count'              => $attributeCount,

          // ── Pricing fields (MarginCalculator) ──────────────────────────
          'products_price'    => (float)($product['products_price']    ?? 0),
          'products_cost'     => (float)($product['products_cost']     ?? 0),
          'products_handling' => (float)($product['products_handling'] ?? 0),

          // ── Adaptive scheduler metrics ──────────────────────────────────
          'views_7d'      => $views7d,
          'avg_views_7d'  => $avgViews7d,

          // ── SEO (injected into Context → ScoreFactor) ──────────────────
          'seo_status' => $seoStatus,   // 'NOT_ANALYZED' | 'ANALYZED'
          'seo_score'  => $seoScore,    // float [0..100] | null

          // ── Thin-content signal (Phase 1 verdict, surfaced for CockpitAI UI)
          'seo_thin_content_level' => $seoThinContentLevel, // 'critical' | 'warning' | 'ok' | null
          'seo_wordcount_body'     => $seoWordcountBody,    // int|null — body word count when known

          // ── SEO Quality Benchmark (injected into Context, scored by ProductScoreAxis)
          'seo_benchmark_score'      => $seoBenchmarkScore,      // composite [0..1] | null
          'seo_benchmark_verdict'    => $seoBenchmarkVerdict,    // improvement|parity|regression | null
          'seo_benchmark_reason'     => $seoBenchmarkReason,     // low_coverage|repetition|… | null
          'seo_benchmark_coverage'   => $seoBenchmarkCoverage,   // source-entity coverage [0..1]
          'seo_benchmark_diversity'  => $seoBenchmarkDiversity,  // type-token ratio [0..1]
          'seo_benchmark_repetition' => $seoBenchmarkRepetition, // repetition penalty [0..1]

          // ── Score_Y fields (CommercialScoreAxis) ───────────────────────
          'products_viewed'      => (int) ($product['products_viewed'] ?? 0),
          'products_ordered'     => (int) ($product['products_ordered'] ?? 0),
          'products_view'        => $product['products_view'] ?? 'N',
          'views_30d'            => $views30d,
          'orders'               => $orderCount,
          'order_count'          => $orderCount,
          'total_quantity'       => $totalQuantity,
          'conversion_rate'      => $conversionRate,
          'return_count'         => $returnCount,
          'return_rate'          => $returnRate,
          'specials_active'      => $specialActive,
          'feature'              => $featured,
          'favorites'             => $favorites,
          'review_count'         => $reviewCount,
          'notification_count'   => $trackingCount,
          'recommendation_count' => $recommendationCount,

          // ── RecommendationEngine context keys ──────────────────────────
          'promo_active'    => $specialActive,
          'recommendations' => $recommendationCount,
        ];

        // ── Velocity metrics integration ───────────────────────────────────
        $velocityMetrics = $this->collectVelocityMetrics($productId, $product);
        $result_array = array_merge($result_array, $velocityMetrics);
        $this->checkTimeout($startTime, 'velocity metrics collection');

        // ── Tracking metrics integration (Phase 1) ─────────────────────────
        // avg_catalog_heat calculé une seule fois ici pour éviter un recalcul
        // par produit dans ScoringEngine (optimisation critique sur gros catalogue).
        // Seuil dynamique selon usage (critic point 9) :
        //   ranking/affichage  → 20
        //   optimisation IA    → 30 (équilibre minimal acceptable)
        //   décision business  → 100
        $trackingMetrics = $this->collectTrackingMetrics($productId, $languageId);
        $result_array = array_merge($result_array, $trackingMetrics);
        $this->checkTimeout($startTime, 'tracking metrics collection');

        return $result_array;
      } catch (\Throwable $e) {
        throw new \Exception(
          "DataCollector: collection failed for product {$productId}: " . $e->getMessage(),
          0,
          $e
        );
      }
    }

    /**
     * Throw if elapsed time exceeds the 2 s Step-1 timeout.
     */
    private function checkTimeout(float $startTime, string $phase): void
    {
      if ((microtime(true) - $startTime) > self::TIMEOUT_SECONDS) {
        throw new \Exception("Data collection timeout (2 s) exceeded at: {$phase}");
      }
    }

    /**
     * Collect velocity metrics for a product.
     *
     * Retrieves 90-day daily demand series and calculates:
     * - Stock velocity (sales/stock ratio)
     * - Demand statistics (mean, stddev)
     * - 30-day demand forecast
     * - Stockout probability
     * - Safety stock (7-day lead time, 95% service level)
     *
     * @param int $productId Product ID
     * @param array $product Product data array (must contain products_quantity)
     * @return array Velocity metrics with keys: daily_demand_series, stock_velocity,
     *               demand_stats, demand_forecast_30d, stockout_probability, safety_stock
     */
    private function collectVelocityMetrics(int $productId, array $product): array
    {
      $result = [
        'daily_demand_series' => [],
        'stock_velocity' => null,
        'demand_stats' => [
          'count' => 0,
          'mean' => 0.0,
          'stddev' => 0.0,
        ],
        'demand_forecast_30d' => [
          'mean_daily' => 0.0,
          'stddev_daily' => 0.0,
          'mean_total' => 0.0,
          'stddev_total' => 0.0,
          'count_days' => 0,
        ],
        'stockout_probability' => null,
        'safety_stock' => null,
      ];

      try {
        // Retrieve 90-day daily demand series
        $dailyDemandSeries = ProductStock::getDailyDemandSeriesByProducts($productId, 90);
        $result['daily_demand_series'] = $dailyDemandSeries;

        // Calculate stock velocity (total_sold_90d / current_stock)
        $currentStock = $product['products_quantity'] ?? 0;
        if (!empty($dailyDemandSeries) && $currentStock > 0) {
          $totalSold90d = array_sum($dailyDemandSeries);
          $result['stock_velocity'] = round($totalSold90d / $currentStock, 2);
        } else {
          $result['stock_velocity'] = null;
        }

        // Calculate demand statistics
        if (!empty($dailyDemandSeries)) {
          $demandStats = ProductStock::calculateDemandStats($dailyDemandSeries);
          $result['demand_stats'] = [
            'count' => $demandStats['count'],
            'mean' => round($demandStats['mean'], 2),
            'stddev' => round($demandStats['stddev'], 2),
          ];

          // Calculate 30-day demand forecast
          $demandForecast = ProductStock::calculateDemandForecast($dailyDemandSeries, 30);
          $result['demand_forecast_30d'] = [
            'mean_daily' => round($demandForecast['mean_daily'], 2),
            'stddev_daily' => round($demandForecast['stddev_daily'], 2),
            'mean_total' => round($demandForecast['mean_total'], 2),
            'stddev_total' => round($demandForecast['stddev_total'], 2),
            'count_days' => $demandForecast['count_days'],
          ];

          // Calculate stockout probability
          if ($currentStock <= 0) {
            $result['stockout_probability'] = 1.0;
          } else {
            $stockoutProb = ProductStock::calculateStockoutProbability(
              (float)$currentStock,
              $demandStats['mean'],
              $demandStats['stddev']
            );
            $result['stockout_probability'] = round($stockoutProb, 4);
          }

          // Calculate safety stock (7-day lead time, 95% service level)
          $safetyStock = ProductStock::calculateSafetyStockFromDailyDemand(
            $dailyDemandSeries,
            7,
            0.95
          );
          $result['safety_stock'] = round($safetyStock, 2);
        } else {
          // No historical data - set stockout probability to 1.0 if out of stock
          if ($currentStock <= 0) {
            $result['stockout_probability'] = 1.0;
          }
        }

        if ($this->debug) {
          error_log(sprintf(
            '[Info CockpitAI] Velocity metrics for product %d: velocity=%.2f, stockout_prob=%.4f, safety_stock=%.2f',
            $productId,
            $result['stock_velocity'] ?? 0.0,
            $result['stockout_probability'] ?? 0.0,
            $result['safety_stock'] ?? 0.0
          ));
        }

      } catch (\Throwable $e) {
        // Log error and continue with default values
        error_log(sprintf(
          '[Error CockpitAI] Failed to collect velocity metrics for product %d: %s',
          $productId,
          $e->getMessage()
        ));

        // Return default values on error
        return $result;
      }

      return $result;
    }

    /**
     * Collect weighted tracking metrics from products_cockpit_ai_tracking_impressions.
     *
     * Seuils de volume minimum (critic point 9) :
     *   < MIN_IMPRESSIONS_THRESHOLD → toutes les métriques tracking = null
     *   Le ScoringEngine ignorera le facteur (status = 'missing')
     *
     *   Seuil choisi : 30 (point d'équilibre minimal acceptable)
     *   - ranking/affichage  : 20 suffisent
     *   - optimisation IA    : 30 recommandé
     *   - décision business  : 100 requis
     *
     * Formule popularity_heat (critic point 2 — anti-biais exposition) :
     *   SUM(weight * EXP(-TIMESTAMPDIFF(HOUR, displayed_at, NOW()) / 48))
     *   / (1 + LOG(COUNT(*) + 1))
     *
     *   → décroissance temporelle (récent > ancien)
     *   → log-dampening du dénominateur (anti-amplification exposition)
     *
     * avg_catalog_heat calculé une seule fois sur l'ensemble du catalogue actif
     * pour la même langue, puis passé au ScoringEngine pour normalisation.
     *
     * @param int $productId
     * @param int $languageId
     * @return array
     */
    private function collectTrackingMetrics(int $productId, int $languageId): array
    {
      // Valeurs par défaut — retournées si tracking désactivé ou volume insuffisant
      $defaults = [
        'popularity_heat'       => null,
        'total_impressions_7d'  => 0,
        'module_spread'         => 0,
        'high_intent_ratio'     => null,
        'avg_catalog_heat'      => null,
        'catalog_heat_stddev'   => null,
        'tracking_valid'        => false,  // flag pour ScoringEngine
      ];

      // Respect du flag de désactivation du tracking
      if (\defined('CLICSHOPPING_APP_ECOMMERCE_CAI_PRODUCT_TRACKING') && CLICSHOPPING_APP_ECOMMERCE_CAI_PRODUCT_TRACKING === 'False') {
        return $defaults;
      }

      // Seuil de volume minimum
      $minImpressions = 30;

      try {
        // ── Métriques produit ────────────────────────────────────────────────
        $Qtrack = $this->db->prepare('
          SELECT
            COUNT(*)                                                    AS total_impressions,
            COUNT(DISTINCT module_code)                                 AS module_spread,
            SUM(
              weight * EXP(-TIMESTAMPDIFF(HOUR, displayed_at, NOW()) / 48)
            ) / (1 + LOG(COUNT(*) + 1))                               AS popularity_heat,
            SUM(CASE WHEN weight >= 0.5 THEN 1 ELSE 0 END)
              / NULLIF(COUNT(*), 0)                                    AS high_intent_ratio
          FROM :table_products_cockpit_ai_tracking_impressions
          WHERE products_id = :product_id
            AND language_id = :language_id
            AND displayed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ');
        $Qtrack->bindInt(':product_id', $productId);
        $Qtrack->bindInt(':language_id', $languageId);
        $Qtrack->execute();

        $row = $Qtrack->fetch();

        $totalImpressions = (int)($row['total_impressions'] ?? 0);

        // Garde-fou volume minimum (critic point 9)
        if ($totalImpressions < $minImpressions) {
          if ($this->debug) {
            error_log("[CockpitAI Tracking] product=$productId: impressions=$totalImpressions < min=$minImpressions → tracking ignored");
          }
          return array_merge($defaults, ['total_impressions_7d' => $totalImpressions]);
        }

        $popularityHeat  = $row['popularity_heat']  !== null ? (float)$row['popularity_heat']  : null;
        $highIntentRatio = $row['high_intent_ratio'] !== null ? (float)$row['high_intent_ratio'] : null;
        $moduleSpread    = (int)($row['module_spread'] ?? 0);

        // ── Moyenne + stddev catalogue (calculé une seule fois) ──────────────
        // Évite le recalcul par produit (optimisation recommandée)
        $Qcatalog = $this->db->prepare('
          SELECT
            AVG(product_heat)   AS avg_heat,
            STDDEV(product_heat) AS stddev_heat
          FROM (
            SELECT
              products_id,
              SUM(
                weight * EXP(-TIMESTAMPDIFF(HOUR, displayed_at, NOW()) / 48)
              ) / (1 + LOG(COUNT(*) + 1)) AS product_heat
            FROM :table_products_cockpit_ai_tracking_impressions
            WHERE language_id = :language_id
              AND displayed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY products_id
            HAVING COUNT(*) >= :min_impressions
          ) AS catalog_heats
        ');
        $Qcatalog->bindInt(':language_id', $languageId);
        $Qcatalog->bindInt(':min_impressions', $minImpressions);
        $Qcatalog->execute();

        $catalogRow    = $Qcatalog->fetch();
        $avgCatalogHeat  = $catalogRow['avg_heat']     !== null ? (float)$catalogRow['avg_heat']     : null;
        $catalogStddev   = $catalogRow['stddev_heat']  !== null ? (float)$catalogRow['stddev_heat']  : null;

        if ($this->debug) {
          error_log(sprintf(
            "[CockpitAI Tracking] product=%d impressions=%d heat=%.4f high_intent=%.2f avg_catalog=%.4f stddev=%.4f",
            $productId,
            $totalImpressions,
            $popularityHeat ?? 0,
            $highIntentRatio ?? 0,
            $avgCatalogHeat ?? 0,
            $catalogStddev ?? 0
          ));
        }

        return [
          'popularity_heat'      => $popularityHeat,
          'total_impressions_7d' => $totalImpressions,
          'module_spread'        => $moduleSpread,
          'high_intent_ratio'    => $highIntentRatio,
          'avg_catalog_heat'     => $avgCatalogHeat,
          'catalog_heat_stddev'  => $catalogStddev,
          'tracking_valid'       => true,
        ];

      } catch (\Throwable $e) {
        error_log("[CockpitAI Tracking] collectTrackingMetrics failed for product=$productId: " . $e->getMessage());
        return $defaults;
      }
    }
  }