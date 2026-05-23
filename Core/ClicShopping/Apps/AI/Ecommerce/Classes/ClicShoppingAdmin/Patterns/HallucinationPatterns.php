<?php
/**
 * HallucinationPatterns - Ecommerce-specific hallucination detection patterns
 *
 * This class provides patterns for detecting hallucinations in LLM responses
 * specific to the e-commerce domain. These patterns are used by LlmGuardrails
 * to validate AI-generated responses and detect suspicious or impossible values.
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Patterns;

/**
 * HallucinationPatterns
 *
 * Provides e-commerce-specific patterns for detecting hallucinations in LLM responses.
 * These patterns identify suspicious values, impossible metrics, and unrealistic claims
 * that may indicate the LLM is generating false information.
 *
 * Pattern Categories:
 * - Impossible growth rates (>500%)
 * - Impossible revenue figures (>100M)
 * - Future dates
 * - Impossible percentages (>1000%)
 * - Hallucinated products
 * - Impossible e-commerce metrics
 * - Temporal hallucinations
 * - Fictitious data
 * - Impossible claims
 */
class HallucinationPatterns
{
  /**
   * Returns all suspicious patterns for hallucination detection
   *
   * These patterns are used to detect hallucinations in LLM responses.
   * Each pattern is a regex that matches suspicious or impossible values
   * in the e-commerce domain.
   *
   * @return array Array of regex patterns for hallucination detection
   */
  public static function getSuspiciousPatterns(): array
  {
    return [
      // Impossible growth rates
      '/growth\s+(?:rate\s+)?of\s+[5-9]\d{2,}\s*%/i', // >500%
      '/increase\s+of\s+[1-9]\d{3,}\s*%/i', // >1000%
      '/sales\s+increased\s+by\s+[1-9]\d{3,}\s*%/i',

      // Impossible revenue figures
      '/revenue\s+of\s+[1-9]\d{8,}/i', // >100M
      '/turnover\s+of\s+[1-9]\d{8,}/i',
      '/sales\s+of\s+[1-9]\d{7,}\s*(?:\$|€|dollars?|euros?)/i',

      // Future dates
      '/in\s+202[5-9]/i', // Future years
      '/for\s+(?:the\s+)?year\s+202[5-9]/i',
      '/by\s+202[5-9]/i',

      // Impossible percentages
      '/[1-9]\d{3,}\s*%/', // >1000%
      '/conversion\s+rate\s+of\s+[5-9]\d\s*%/i', // >50%
      '/profit\s+margin\s+of\s+[1-9]\d{2,}\s*%/i', // >100%

      // Hallucinated products
      '/product\s+(?:non-existent|fake|fictitious|imaginary)/i',
      '/reference\s+(?:fake|fictitious|imaginary)/i',
      '/model\s+(?:fake|fictitious|imaginary)/i',

      // Impossible e-commerce metrics
      '/average\s+(?:cart|basket)\s+(?:value\s+)?of\s+[1-9]\d{4,}/i', // >10k
      '/margin\s+of\s+[1-9]\d{2,}\s*%/i', // >100%
      '/roi\s+of\s+[1-9]\d{3,}\s*%/i', // >1000%

      // Temporal hallucinations
      '/yesterday\s+we\s+sold/i',
      '/last\s+week\s+(?:the\s+)?sales/i',
      '/this\s+morning\s+(?:we\s+)?received/i',

      // Fictitious data
      '/customer\s+(?:fictitious|fake|imaginary)/i',
      '/order\s+(?:fictitious|fake|imaginary)/i',
      '/transaction\s+(?:fictitious|fake|imaginary)/i',

      // Impossible claims
      '/sold\s+out\s+in\s+\d+\s+seconds/i',
      '/\d+\s+million\s+customers\s+bought/i',
      '/never\s+been\s+returned/i'
    ];
  }

  /**
   * Returns patterns for detecting impossible growth rates
   *
   * Growth rates above 500% are considered suspicious in most e-commerce contexts.
   *
   * @return array Array of regex patterns for growth rate detection
   */
  public static function getGrowthRatePatterns(): array
  {
    return [
      '/growth\s+(?:rate\s+)?of\s+[5-9]\d{2,}\s*%/i', // >500%
      '/increase\s+of\s+[1-9]\d{3,}\s*%/i', // >1000%
      '/sales\s+increased\s+by\s+[1-9]\d{3,}\s*%/i',
    ];
  }

  /**
   * Returns patterns for detecting impossible revenue figures
   *
   * Revenue figures above 100M are considered suspicious for most e-commerce businesses.
   *
   * @return array Array of regex patterns for revenue detection
   */
  public static function getRevenueFigurePatterns(): array
  {
    return [
      '/revenue\s+of\s+[1-9]\d{8,}/i', // >100M
      '/turnover\s+of\s+[1-9]\d{8,}/i',
      '/sales\s+of\s+[1-9]\d{7,}\s*(?:\$|€|dollars?|euros?)/i',
    ];
  }

  /**
   * Returns patterns for detecting future dates
   *
   * References to future years (2025+) are considered suspicious.
   *
   * @return array Array of regex patterns for future date detection
   */
  public static function getFutureDatePatterns(): array
  {
    return [
      '/in\s+202[5-9]/i', // Future years
      '/for\s+(?:the\s+)?year\s+202[5-9]/i',
      '/by\s+202[5-9]/i',
    ];
  }

  /**
   * Returns patterns for detecting impossible percentages
   *
   * Percentages above 1000% or conversion rates above 50% are suspicious.
   *
   * @return array Array of regex patterns for percentage detection
   */
  public static function getImpossiblePercentagePatterns(): array
  {
    return [
      '/[1-9]\d{3,}\s*%/', // >1000%
      '/conversion\s+rate\s+of\s+[5-9]\d\s*%/i', // >50%
      '/profit\s+margin\s+of\s+[1-9]\d{2,}\s*%/i', // >100%
    ];
  }

  /**
   * Returns patterns for detecting hallucinated products
   *
   * Explicit mentions of fake, fictitious, or imaginary products.
   *
   * @return array Array of regex patterns for hallucinated product detection
   */
  public static function getHallucinatedProductPatterns(): array
  {
    return [
      '/product\s+(?:non-existent|fake|fictitious|imaginary)/i',
      '/reference\s+(?:fake|fictitious|imaginary)/i',
      '/model\s+(?:fake|fictitious|imaginary)/i',
    ];
  }

  /**
   * Returns patterns for detecting impossible e-commerce metrics
   *
   * Metrics like average cart value >10k, margin >100%, or ROI >1000% are suspicious.
   *
   * @return array Array of regex patterns for impossible metric detection
   */
  public static function getImpossibleMetricPatterns(): array
  {
    return [
      '/average\s+(?:cart|basket)\s+(?:value\s+)?of\s+[1-9]\d{4,}/i', // >10k
      '/margin\s+of\s+[1-9]\d{2,}\s*%/i', // >100%
      '/roi\s+of\s+[1-9]\d{3,}\s*%/i', // >1000%
    ];
  }

  /**
   * Returns patterns for detecting temporal hallucinations
   *
   * References to specific recent events (yesterday, last week, this morning) are suspicious
   * because the LLM doesn't have access to real-time data.
   *
   * @return array Array of regex patterns for temporal hallucination detection
   */
  public static function getTemporalHallucinationPatterns(): array
  {
    return [
      '/yesterday\s+we\s+sold/i',
      '/last\s+week\s+(?:the\s+)?sales/i',
      '/this\s+morning\s+(?:we\s+)?received/i',
    ];
  }

  /**
   * Returns patterns for detecting fictitious data
   *
   * Explicit mentions of fictitious, fake, or imaginary data.
   *
   * @return array Array of regex patterns for fictitious data detection
   */
  public static function getFictitiousDataPatterns(): array
  {
    return [
      '/customer\s+(?:fictitious|fake|imaginary)/i',
      '/order\s+(?:fictitious|fake|imaginary)/i',
      '/transaction\s+(?:fictitious|fake|imaginary)/i',
    ];
  }

  /**
   * Returns patterns for detecting impossible claims
   *
   * Claims like "sold out in seconds" or "never been returned" are suspicious.
   *
   * @return array Array of regex patterns for impossible claim detection
   */
  public static function getImpossibleClaimPatterns(): array
  {
    return [
      '/sold\s+out\s+in\s+\d+\s+seconds/i',
      '/\d+\s+million\s+customers\s+bought/i',
      '/never\s+been\s+returned/i',
    ];
  }

  /**
   * Returns the maximum acceptable growth rate percentage
   *
   * @return int Maximum acceptable growth rate (500%)
   */
  public static function getMaxGrowthRate(): int
  {
    return 500;
  }

  /**
   * Returns the maximum acceptable revenue figure
   *
   * @return int Maximum acceptable revenue (100,000,000)
   */
  public static function getMaxRevenue(): int
  {
    return 100000000;
  }

  /**
   * Returns the maximum acceptable conversion rate percentage
   *
   * @return int Maximum acceptable conversion rate (50%)
   */
  public static function getMaxConversionRate(): int
  {
    return 50;
  }

  /**
   * Returns the maximum acceptable profit margin percentage
   *
   * @return int Maximum acceptable profit margin (100%)
   */
  public static function getMaxProfitMargin(): int
  {
    return 100;
  }

  /**
   * Returns the maximum acceptable ROI percentage
   *
   * @return int Maximum acceptable ROI (1000%)
   */
  public static function getMaxROI(): int
  {
    return 1000;
  }

  /**
   * Returns the maximum acceptable average cart value
   *
   * @return int Maximum acceptable average cart value (10,000)
   */
  public static function getMaxAverageCartValue(): int
  {
    return 10000;
  }
}
