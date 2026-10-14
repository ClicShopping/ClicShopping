<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator;

  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring\CatalogNormalization;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring\Context;
  use ClicShopping\OM\Registry;

  /**
   * ContextBuilder
   *
   * Assembles the immutable Context object passed through the scoring pipeline.
   * Reads module constants (T_high, T_low, strategy_x, strategy_y) and combines
   * them with runtime data (product SEO state, catalog normalization, RAG history).
   *
   * Extracted from CockpitAIOrchestrator to keep the orchestrator thin and to
   * make Context construction independently testable.
   *
   * Constants consumed (defined in CockpitAIModule / CAI configuration):
   *  - CLICSHOPPING_APP_ECOMMERCE_CAI_T_HIGH       float  default 70.0
   *  - CLICSHOPPING_APP_ECOMMERCE_CAI_T_LOW        float  default 30.0
   *  - CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_X   string default 'quality'
   *  - CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_Y   string default 'performance'
   *
   * Note on velocityMax (Requirement 13):
   *   CockpitAI analyses one product at a time. The current orchestrator pipeline does
   *   not provide a pre-computed catalog-wide velocity array at build() time, so
   *   velocityMax defaults to 1.0 for single-product analyses.
   *   Use calculateVelocityMax(array $allProducts) when a batch context is available
   *   (e.g. a future bulk-analysis feature) and pass the result to build() explicitly.
   */
  class ContextBuilder
  {
    private mixed $db;

    public function __construct()
    {
      $this->db = Registry::get('Db');
    }

    /**
     * Build a full Context from collected product data and catalog normalization.
     *
     * Called by the Orchestrator after Step 2 (catalog normalization) and
     * before Step 3 (scoring calculation).
     *
     * velocityMax defaults to 1.0 for single-product pipeline runs.
     * Pass a pre-computed value via $velocityMax when a catalog-wide array
     * is available (Requirement 13.3 default).
     *
     * @param array                $productData  Array returned by DataCollector::collect()
     * @param CatalogNormalization $catalog      Computed (or default) catalog max values
     * @param array                $history      Top-3 RAG embeddings (content strings)
     * @param int                  $languageId   Current language ID
     * @param int                  $userId       Requesting user ID
     * @param float                $velocityMax  Catalog-wide max velocity (default 1.0)
     * @return Context
     */
    public function build(
      array                $productData,
      CatalogNormalization $catalog,
      array                $history,
      int                  $languageId,
      int                  $userId,
      float                $velocityMax = 1.0,
    ): Context {

      if (!self::validateThresholds()) {
        error_log("[ContextBuilder] Warning: T_high/T_low thresholds invalid or missing — using defaults 70.0/30.0");
      }

      return new Context(
        history:             $history,
        strategyPreferences: $this->getStrategyPreferences(),
        seoStatus:           (string) ($productData['seo_status'] ?? Context::SEO_NOT_ANALYZED),
        seoScore:            isset($productData['seo_score']) ? (float) $productData['seo_score'] : null,
        thresholds:          $this->getThresholds(),
        catalog:             $catalog,
        languageId:          $languageId,
        userId:              $userId,
        velocityMax:         $velocityMax,
        // ── SEO Quality Benchmark signals — populated by DataCollector
        // from clic_seo_quality_benchmark_log; null when the table is empty
        // for this (product, language) pair.
        seoBenchmarkScore:      isset($productData['seo_benchmark_score'])
          ? (float)$productData['seo_benchmark_score'] : null,
        seoBenchmarkVerdict:    $productData['seo_benchmark_verdict']     ?? null,
        seoBenchmarkReason:     $productData['seo_benchmark_reason']      ?? null,
        seoBenchmarkCoverage:   isset($productData['seo_benchmark_coverage'])
          ? (float)$productData['seo_benchmark_coverage'] : null,
        seoBenchmarkDiversity:  isset($productData['seo_benchmark_diversity'])
          ? (float)$productData['seo_benchmark_diversity'] : null,
        seoBenchmarkRepetition: isset($productData['seo_benchmark_repetition'])
          ? (float)$productData['seo_benchmark_repetition'] : null,
        // Thin-content signal — null when SEO has never been analysed.
        seoThinContentLevel: isset($productData['seo_thin_content_level'])
          ? (string)$productData['seo_thin_content_level'] : null,
        seoWordcountBody: isset($productData['seo_wordcount_body'])
          ? (int)$productData['seo_wordcount_body'] : null,
      );
    }



    /**
     * Validate threshold values used by ScoringEngine::classifyQuadrant()
     * and Context::fromDefaults() for Q1/Q2/Q3/Q4 classification.
     *
     * Constants used by Context.php :
     *   CLICSHOPPING_APP_ECOMMERCE_CAI_T_HIGH  (default 70.0)
     *   CLICSHOPPING_APP_ECOMMERCE_CAI_T_LOW   (default 30.0)
     *
     * This method is available for admin configuration validation panels.
     * It is NOT called in checkStatus() — Context::fromDefaults() already
     * falls back to 70/30 if the constants are undefined, so the module
     * runs correctly without them being explicitly set.
     *
     * @param float|null $tHigh High threshold (null = read from constant or use 70.0)
     * @param float|null $tLow  Low threshold  (null = read from constant or use 30.0)
     * @return bool Validation result
     */
    public static function validateThresholds(?float $tHigh = null, ?float $tLow = null): bool
    {
      if ($tHigh === null) {
        $tHigh = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_T_HIGH') ? (float)CLICSHOPPING_APP_ECOMMERCE_CAI_T_HIGH : 70.0;
      }

      if ($tLow === null) {
        $tLow = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_T_LOW') ? (float)CLICSHOPPING_APP_ECOMMERCE_CAI_T_LOW : 30.0;
      }

      if ($tHigh <= $tLow)               return false;
      if ($tHigh < 0 || $tHigh > 100)    return false;
      if ($tLow  < 0 || $tLow  > 100)    return false;
      if (($tHigh - $tLow) < 10)         return false;

      return true;
    }

    /**
     * Return strategy preferences per axis from module constants.
     *
     * @return array{axis_x: string, axis_y: string}
     */
    public function getStrategyPreferences(): array
    {
      return [
        'axis_x' => \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_X')
          ? CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_X
          : 'quality',
        'axis_y' => \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_Y')
          ? CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_Y
          : 'performance',
      ];
    }

    /**
     * Return quadrant thresholds from module constants.
     *
     * @return array{T_high: float, T_low: float}
     */
    public function getThresholds(): array
    {
      return [
        'T_high' => \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_T_HIGH')
          ? (float) CLICSHOPPING_APP_ECOMMERCE_CAI_T_HIGH
          : 70.0,
        'T_low'  => \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_T_LOW')
          ? (float) CLICSHOPPING_APP_ECOMMERCE_CAI_T_LOW
          : 30.0,
      ];
    }

    /**
     * Calculate catalog-wide velocity maximum from a products array.
     *
     * Extracts all non-null stock_velocity values from the products array
     * and returns the maximum value. If no products have calculable velocity,
     * returns 1.0 as the default (Requirement 13.3).
     *
     * This method is public so callers with access to a batch of products
     * (e.g. a bulk analysis controller) can pre-compute velocityMax and
     * pass it to build() directly.
     *
     * Requirements: 13.2, 13.3
     *
     * @param array $allProducts Array of product data arrays, each potentially containing 'stock_velocity'
     * @return float Maximum velocity across all products, or 1.0 if none is calculable
     */
    public function calculateVelocityMax(array $allProducts): float
    {
      $velocities = array_filter(
        array_column($allProducts, 'stock_velocity'),
        fn($v) => $v !== null && is_numeric($v)
      );

      return !empty($velocities) ? (float) max($velocities) : 1.0;
    }
  }
