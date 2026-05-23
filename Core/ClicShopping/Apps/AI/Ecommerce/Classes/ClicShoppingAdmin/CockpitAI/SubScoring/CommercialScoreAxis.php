<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring;

use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors\BooleanFactor;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors\CountFactor;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors\FactorInterface;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubFactors\RatioFactor;

/**
 * CommercialScoreAxis — Score_Y
 *
 * Measures commercial performance of a product in the catalog.
 *
 * Product data keys expected in $product array (built by DataCollector Step 1):
 *   - products_viewed      : int   — cumulative views (clic_products_description.products_viewed)
 *   - products_ordered     : int   — cumulative orders (clic_products.products_ordered)
 *   - conversion_rate      : float — [0..1] conversion rate (if available)
 *   - return_rate          : float — [0..1] return rate (if available)
 *   - specials_active      : bool  — active special price in clic_specials (status=1, not expired)
 *   - products_view        : string — 'Y'|'N' view tracking flag (clic_products.products_view)
 *   - favorites       : bool  — active row in clic_products_favorites (status=1)
 *   - feature              : bool  — active row in clic_products_featured (status=1)
 *   - review_count         : int   — COUNT(*) from :table_reviews for this product
 *   - notification_count   : int   — COUNT(*) from :table_products_notifications for this product
 *   - stock_velocity       : float|null — inventory turnover rate (sales/stock over 90 days)
 *   - stockout_probability : float|null — risk of running out of stock [0..1]
 *
 * Factors:
 *   - views         : CountFactor   (products_viewed / views_p95, log scaled)  weight: 2.0
 *   - orders        : CountFactor   (products_ordered / orders_p95, log scaled) weight: 3.0
 *   - conversion    : RatioFactor   ([0..1], sqrt transform)                   weight: 2.5
 *   - returns       : RatioFactor   (inverted: 1 - return_rate, sqrt transform) weight: 2.0
 *   - specials      : BooleanFactor (active special in clic_specials)           weight: 1.0
 *   - view_tracking : BooleanFactor (products_view = 'Y')                       weight: 1.0
 *   - favorites      : BooleanFactor (active row in clic_products_favorites)      weight: 1.5
 *   - featured      : BooleanFactor (active row in clic_products_featured)      weight: 1.5
 *   - reviews       : CountFactor   (review_count / reviews_p95, log scaled)    weight: 1.5
 *   - tracking      : CountFactor   (notification_count / tracking_p95)         weight: 1.5
 *   - velocity      : RatioFactor   (min(1.0, velocity / 2.0))                  weight: 2.0
 *   - stockout_risk : RatioFactor   (inverted: 1 - stockout_probability)        weight: 1.5
 */
class CommercialScoreAxis implements ScoringAxisInterface
{
  private array $weights = [
    'views'          => 2.0,
    'orders'         => 3.0,
    'conversion'     => 2.5,
    'returns'        => 2.0,
    'specials'       => 1.5,
    'view_tracking'  => 1.0,
    'favorites'      => 1.2,
    'featured'       => 1.2,
    'reviews'        => 1.5,
    'tracking'       => 1.5,
    'velocity'       => 2.0,
    'stockout_risk'  => 1.5,
    // Facteur tracking pondéré (Phase 1 intégration)
    // Poids 2.0 : corrige cold-start + capte tendances récentes
    // Comportement cible : ventes dominantes (70%), tracking corrige (30%)
    // Résultat effectif ≈ 20/22.6 * sales + 2/22.6 * tracking ≈ 88%/12%
    // → tracking est correctif, pas dominant (critic point 10)
    'popularity_heat' => 2.0,
  ];

  public function getCode(): string
  {
    return 'Y';
  }

  public function getWeights(): array
  {
    return $this->weights;
  }

  public function getFactors(array $product, Context $context): array
  {
    $catalog = $context->catalog;
    $factors = [];

    // views: cumulative view counter from clic_products_description.products_viewed
    $views = isset($product['products_viewed']) ? (float) $product['products_viewed'] : null;
    $factors['views'] = new CountFactor($views, $catalog->viewsMax, $catalog->viewsP95);

    // orders: cumulative total from clic_products.products_ordered
    $orders = isset($product['products_ordered']) ? (float) $product['products_ordered'] : null;
    $factors['orders'] = new CountFactor($orders, $catalog->ordersMax, $catalog->ordersP95);

    // conversion: ratio [0..1] — sqrt transform: improvement at low end is more valuable
    // (going from 0% to 1% conversion is far more impactful than 9% to 10%)
    $convRate = isset($product['conversion_rate']) ? (float) $product['conversion_rate'] : null;
    $factors['conversion'] = new RatioFactor($convRate, transform: 'sqrt');

    // returns: inverted return rate — fewer returns = higher score
    // sqrt transform applied on the inverted value for the same reason
    $returnRate = isset($product['return_rate']) ? (float) $product['return_rate'] : null;
    $invertedReturns = ($returnRate !== null) ? max(0.0, 1.0 - $returnRate) : null;
    $factors['returns'] = new RatioFactor($invertedReturns, transform: 'sqrt');

    // specials: active promotion in clic_specials (status=1, not expired)
    // Expected as 'specials_active' bool in the product array (computed by DataCollector)
    $specialsActive = isset($product['specials_active']) ? (bool) $product['specials_active'] : null;
    $factors['specials'] = new BooleanFactor($specialsActive);

    // view_tracking: products_view = 'Y' means the product is tracked for views
    $productsView = isset($product['products_view']) ? ($product['products_view'] === 'Y') : null;
    $factors['view_tracking'] = new BooleanFactor($productsView);






    // favorites: product added to clic_products_favorites (dedicated listing page)
    // Boolean — product is featured in the favorites listing (status=1)
    $favoritesActive = isset($product['favorites']) ? (bool) $product['favorites'] : null;
    $factors['favorites'] = new BooleanFactor($favoritesActive);

    // featured: active row in clic_products_featured (status=1)
    // A featured product gets a commercial boost — high visibility in the storefront
    $featuredActive = isset($product['feature']) ? (bool) $product['feature'] : null;
    $factors['featured'] = new BooleanFactor($featuredActive);

    // reviews: count from :table_reviews
    $reviewCount = isset($product['review_count']) ? (float) $product['review_count'] : null;
    $factors['reviews'] = new CountFactor($reviewCount, $catalog->reviewsMax, $catalog->reviewsP95);

    // tracking: notification count from :table_products_notifications
    $notifCount = isset($product['notification_count']) ? (float) $product['notification_count'] : null;
    $factors['tracking'] = new CountFactor($notifCount, $catalog->trackingMax, $catalog->trackingP95);

    // velocity: stock turnover rate (sales/stock over 90 days)
    // Normalisé par velocityMax du catalogue (depuis Context) :
    //   min(1.0, velocity / velocityMax)
    // velocityMax = 1.0 par défaut si non calculé (pipeline produit-par-produit)
    // velocityMax = max(velocity catalogue) si calculé par ContextBuilder::calculateVelocityMax()
    // → la normalisation s'adapte à la vélocité réelle du catalogue
    $velocity = isset($product['stock_velocity']) ? (float) $product['stock_velocity'] : null;
    $velocityMax = max(0.1, $context->velocityMax); // garde-fou division par zéro
    $normalizedVelocity = ($velocity !== null) ? min(1.0, $velocity / $velocityMax) : null;
    $factors['velocity'] = new RatioFactor($normalizedVelocity);

    // stockout_risk: inverted stockout probability (lower risk = higher score)
    $stockoutProb = isset($product['stockout_probability']) ? (float) $product['stockout_probability'] : null;
    $stockoutRisk = ($stockoutProb !== null) ? (1.0 - $stockoutProb) : null;
    $factors['stockout_risk'] = new RatioFactor($stockoutRisk);

    // ── popularity_heat : tracking pondéré anti-biais (Phase 1 intégration) ──
    //
    // Normalisation robuste (critic points 2, 5, 8) :
    //   1. Saturation : min(1, heat / (avg * 3)) → coupe les outliers extrêmes
    //      (évite qu'un produit viral domine le catalogue)
    //   2. sqrt() : accentue les petites valeurs (produits cold-start profitent plus)
    //   3. Status null si tracking_valid = false (volume insuffisant < 30 impressions)
    //      → RatioFactor retourne status='missing', exclu du calcul (critic point 9)
    //
    // Comportement global :
    //   - ventes dominantes via orders (weight=3) + conversion (weight=2.5) = 70%+
    //   - popularity_heat corrige cold-start + tendances (weight=2.0) ≈ 12%
    //   - modèle stable même avec bruit modéré (sqrt + saturation)
    $popularityHeatNorm = null;

    if (($product['tracking_valid'] ?? false) === true) {
      $heat    = $product['popularity_heat']  ?? null;
      $avgHeat = $product['avg_catalog_heat'] ?? null;

      if ($heat !== null && $avgHeat !== null && $avgHeat > 0) {
        // Saturation : coupe les outliers à 3x la moyenne catalogue
        $saturated  = min(1.0, $heat / ($avgHeat * 3.0));

        // sqrt : normalisation robuste — améliore les petites valeurs relatives
        $popularityHeatNorm = sqrt($saturated);

      } elseif ($heat !== null && $heat > 0) {
        // avg_catalog_heat indisponible : normalisation dégradée sur valeur brute
        // Borne empirique : heat > 1.0 = produit très chaud
        $popularityHeatNorm = min(1.0, sqrt($heat));
      }
    }

    $factors['popularity_heat'] = new RatioFactor($popularityHeatNorm);

    return $factors;
  }

  public function computeScore(array $factors): float
  {
    $weightedSum  = 0.0;
    $validWeights = 0.0;

    foreach ($factors as $code => $factor) {
      /** @var FactorInterface $factor */
      if ($factor->getStatus() !== 'valid') {
        continue;
      }

      $weight = $this->weights[$code] ?? 1.0;
      $weightedSum  += $weight * $factor->normalize();
      $validWeights += $weight;
    }

    if ($validWeights <= 0.0) {
      return 0.0;
    }

    return ($weightedSum / $validWeights) * 100.0;
  }
}
