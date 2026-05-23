<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring;

/**
 * Context
 *
 * Transports cross-cutting data between pipeline steps.
 * Immutable (readonly) - assembled once by the Orchestrator and passed downstream.
 *
 * seoStatus values: 'NOT_ANALYZED' | 'ANALYZED'
 */
readonly class Context
{
  public const SEO_NOT_ANALYZED = 'NOT_ANALYZED';
  public const SEO_ANALYZED     = 'ANALYZED';

  public function __construct(
    public array                $history,              // Top-3 historical embeddings (content strings)
    public array                $strategyPreferences,  // ['axis_x' => 'visibility', 'axis_y' => 'conversion']
    public string               $seoStatus,            // 'NOT_ANALYZED' | 'ANALYZED'
    public ?float               $seoScore,             // SEO score [0..100] if ANALYZED, null otherwise
    public array                $thresholds,           // ['T_high' => 70.0, 'T_low' => 30.0]
    public CatalogNormalization $catalog,              // Catalog-wide max values
    public int                  $languageId,           // Current language ID
    public int                  $userId,               // Requesting user ID
    public float                $velocityMax = 1.0,    // Maximum stock velocity across catalog
    // ── SEO Quality Benchmark signals (Phase 2 algorithmic guard) ────────
    // Populated by the DataCollector from clic_seo_quality_benchmark_log.
    // Independent from seoScore (which measures the public-front crawler
    // score) — the benchmark measures content quality vs source description
    // (entity coverage, vocabulary diversity, repetition, entropy).
    public ?float               $seoBenchmarkScore   = null,   // composite [0..1] | null when no benchmark yet
    public ?string              $seoBenchmarkVerdict = null,   // improvement | parity | regression | null
    public ?string              $seoBenchmarkReason  = null,   // low_coverage | repetition | … | null
    public ?float               $seoBenchmarkCoverage   = null, // source-entity coverage [0..1]
    public ?float               $seoBenchmarkDiversity  = null, // type-token ratio [0..1]
    public ?float               $seoBenchmarkRepetition = null,  // repetition penalty [0..1]
    // ── Thin-content signal (Phase 1) ────────────────────────────────────
    // Populated by the DataCollector from the latest seo_serp_reports
    // entry.  When the product page body is too short for meaningful SEO
    // analysis, this carries the level ('warning' or 'critical') and the
    // associated message so the CockpitAI UI can explain WHY the score
    // is degraded rather than just showing low numbers.
    public ?string              $seoThinContentLevel = null,   // 'critical' | 'warning' | 'ok' | null
    public ?int                 $seoWordcountBody    = null    // body word count when known
  ) {
  }

  /**
   * Build a default Context for pipeline execution from module constants.
   */
  public static function fromDefaults(int $languageId, int $userId): self
  {
    return new self(
      history: [],
      strategyPreferences: [
        'axis_x' => \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_X') ? CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_X : 'quality',
        'axis_y' => \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_Y') ? CLICSHOPPING_APP_ECOMMERCE_CAI_STRATEGY_Y : 'conversion',
      ],
      seoStatus: self::SEO_NOT_ANALYZED,
      seoScore: null,
      thresholds: [
        'T_high' => \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_T_HIGH') ? (float) CLICSHOPPING_APP_ECOMMERCE_CAI_T_HIGH : 70.0,
        'T_low'  => \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_T_LOW')  ? (float) CLICSHOPPING_APP_ECOMMERCE_CAI_T_LOW  : 30.0,
      ],
      catalog: CatalogNormalization::defaults(),
      languageId: $languageId,
      userId: $userId,
      velocityMax: 1.0
    );
  }

  public function isSeoAnalyzed(): bool
  {
    return $this->seoStatus === self::SEO_ANALYZED;
  }

  public function getThresholdHigh(): float
  {
    return (float) ($this->thresholds['T_high'] ?? 70.0);
  }

  public function getThresholdLow(): float
  {
    return (float) ($this->thresholds['T_low'] ?? 30.0);
  }
}
