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
    public float                $velocityMax = 1.0     // Maximum stock velocity across catalog
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
