<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * PriceBoundFilter
 *
 * Bounds external price listings (Google Shopping and registered competitor sites) to a band around a
 * reference price, so that accessories (cases, chargers, screen protectors…) — which are far
 * cheaper or pricier than the device itself — do not skew the price analysis/average.
 *
 * The reference price is the catalog (internal) price when available; otherwise the median of the
 * external prices. The band half-width is BOUND_PERCENT (a fixed constant for now — easily moved
 * to configuration or database later).
 */

namespace ClicShopping\AI\DomainsAI\WebSearch\Helper;

class PriceBoundFilter
{
  /**
   * Half-width of the price band, in percent of the reference price.
   * Fixed constant for a first iteration; change here, or later wire it to config/DB.
   */
  public const BOUND_PERCENT = 50;

  /**
   * Keep only external listings whose price falls within ±BOUND_PERCENT of the reference price.
   *
   * @param float|null $referencePrice Catalog/internal price, or null for a pure web-search query.
   * @param array      $externalPrices List of listings; each is expected to expose a numeric price.
   * @param string     $priceKey       Key holding the numeric price on each listing (e.g. 'price'
   *                                    for competitor rows, 'extracted_price' for shopping results).
   * @return array{kept: array, excluded: int, reference: float|null, low: float|null, high: float|null, bound_percent: int}
   */
  public static function bound(?float $referencePrice, array $externalPrices, string $priceKey = 'price'): array
  {
    $boundPercent = self::BOUND_PERCENT;
    $unfiltered = [
      'kept' => $externalPrices,
      'excluded' => 0,
      'reference' => $referencePrice,
      'low' => null,
      'high' => null,
      'bound_percent' => $boundPercent,
    ];

    // Collect numeric prices to determine a reference when the catalog price is unknown.
    $prices = [];
    foreach ($externalPrices as $listing) {
      if (isset($listing[$priceKey]) && is_numeric($listing[$priceKey])) {
        $prices[] = (float)$listing[$priceKey];
      }
    }

    $reference = $referencePrice;
    if (($reference === null || $reference <= 0) && $prices !== []) {
      // No catalog reference (pure web-search, product not in DB): use the HIGHEST listing as the
      // reference. The product itself is typically the priciest item; accessories (cases, chargers)
      // are cheaper, so the ±band drops them. The median is unsuitable here — listings are bimodal
      // (cheap accessories vs the device) and the median lands in the empty gap between the two.
      $reference = max($prices);
    }

    // No usable reference → cannot bound; return everything unchanged.
    if ($reference === null || $reference <= 0) {
      return $unfiltered;
    }

    $low = $reference * (1 - $boundPercent / 100.0);
    $high = $reference * (1 + $boundPercent / 100.0);

    $kept = [];
    $excluded = 0;
    foreach ($externalPrices as $listing) {
      $price = (isset($listing[$priceKey]) && is_numeric($listing[$priceKey])) ? (float)$listing[$priceKey] : null;
      if ($price === null) {
        // Keep listings without a parseable price (nothing to bound).
        $kept[] = $listing;
        continue;
      }
      if ($price >= $low && $price <= $high) {
        $kept[] = $listing;
      } else {
        $excluded++;
      }
    }

    // Safety: never bound everything away (e.g. a catalog reference far from every listing) —
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
