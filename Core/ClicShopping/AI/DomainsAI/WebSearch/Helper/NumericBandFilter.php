<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * NumericBandFilter
 *
 * Agnostic numeric helper: bounds a set of external listings to a band around a
 * reference value, dropping outliers that would skew an average or comparison.
 * The logic is pure arithmetic and carries no domain concept — callers decide
 * what the numeric value means (e.g. a catalog price vs. competitor listings in
 * the Ecommerce domain, a salary band elsewhere).
 *
 * The reference value is the caller-supplied one when available; otherwise the
 * highest listing value (outliers are typically cheaper accessories around a
 * dominant item). The band half-width is BOUND_PERCENT.
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Helper;

class NumericBandFilter
{
  /**
   * Half-width of the band, in percent of the reference value.
   * Fixed constant for a first iteration; change here, or later wire it to config/DB.
   */
  public const BOUND_PERCENT = 50;

  /**
   * Keep only listings whose value falls within ±BOUND_PERCENT of the reference value.
   *
   * @param float|null $referenceValue Caller-supplied reference, or null to derive one.
   * @param array      $listings       List of listings; each is expected to expose a numeric value.
   * @param string     $valueKey       Key holding the numeric value on each listing (e.g. 'price'
   *                                    for competitor rows, 'extracted_price' for shopping results).
   * @return array{kept: array, excluded: int, reference: float|null, low: float|null, high: float|null, bound_percent: int}
   */
  public static function bound(?float $referenceValue, array $listings, string $valueKey = 'price'): array
  {
    $boundPercent = self::BOUND_PERCENT;
    $unfiltered = [
      'kept' => $listings,
      'excluded' => 0,
      'reference' => $referenceValue,
      'low' => null,
      'high' => null,
      'bound_percent' => $boundPercent,
    ];

    // Collect numeric values to determine a reference when the caller supplied none.
    $values = [];
    foreach ($listings as $listing) {
      if (isset($listing[$valueKey]) && is_numeric($listing[$valueKey])) {
        $values[] = (float)$listing[$valueKey];
      }
    }

    $reference = $referenceValue;
    if (($reference === null || $reference <= 0) && $values !== []) {
      // No supplied reference: use the HIGHEST listing as the reference. The dominant item is
      // typically the priciest; outliers (cheaper accessories) sit below it, so the ±band drops
      // them. The median is unsuitable here — listings are bimodal and the median lands in the
      // empty gap between the two clusters.
      $reference = max($values);
    }

    // No usable reference → cannot bound; return everything unchanged.
    if ($reference === null || $reference <= 0) {
      return $unfiltered;
    }

    $low = $reference * (1 - $boundPercent / 100.0);
    $high = $reference * (1 + $boundPercent / 100.0);

    $kept = [];
    $excluded = 0;
    foreach ($listings as $listing) {
      $value = (isset($listing[$valueKey]) && is_numeric($listing[$valueKey])) ? (float)$listing[$valueKey] : null;
      if ($value === null) {
        // Keep listings without a parseable value (nothing to bound).
        $kept[] = $listing;
        continue;
      }
      if ($value >= $low && $value <= $high) {
        $kept[] = $listing;
      } else {
        $excluded++;
      }
    }

    // Safety: never bound everything away (e.g. a reference far from every listing) —
    // returning an empty set would be worse than not filtering.
    if ($kept === []) {
      return $unfiltered;
    }

    return [
      'kept' => $kept,
      'excluded' => $excluded,
      'reference' => $reference,
      'low' => $low,
      'high' => $high,
      'bound_percent' => $boundPercent,
    ];
  }
}
