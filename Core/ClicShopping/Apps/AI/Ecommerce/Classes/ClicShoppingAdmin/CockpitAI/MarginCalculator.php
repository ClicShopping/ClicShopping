<?php

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;


  class MarginCalculator {
    private float $minMarginRate;

    public function __construct() {
      // On récupère la configuration ou 15.0 par défaut si la constante n'est pas définie
      if (defined('CLICSHOPPING_APP_ECOMMERCE_CAI_MARGIN_RATE')) {
        $this->minMarginRate = (float)CLICSHOPPING_APP_ECOMMERCE_CAI_MARGIN_RATE;
      } else {
        $this->minMarginRate = 15.0;
      }
    }

    /**
     * REQ-3.2: Formule déterministe de validation de marge
     */
    public function isActionFeasible(array $productData, float $proposedPrice): array
    {
      $productsPrice    = (float)($productData['products_price'] ?? 0);
      $productsCost     = (float)($productData['products_cost'] ?? 0);
      $productsHandling = (float)($productData['products_handling'] ?? 0);

      // Garde-fou : Prix produit nul
      if ($productsPrice <= 0) {
        return ['feasible' => false, 'margin_percentage' => 0, 'reason' => 'prix produit nul'];
      }

      // Si MARGIN_RATE = 0, aucune action autorisée (règle admin)
      if ($this->minMarginRate <= 0) {
        return ['feasible' => false, 'margin_percentage' => 0, 'reason' => 'margin_rate_zero'];
      }

      // Cas particulier : cost=0 et handling=0 (valeurs par défaut BD)
      // On travaille sur le prix seul — la marge est le prix lui-même.
      // On autorise une remise jusqu'à (100 - minMarginRate)% du prix de vente.
      if ($productsCost <= 0 && $productsHandling <= 0) {
        // remise_max_pct = 100 - minMarginRate  (ex: rate=15 → remise max 85%)
        $remiseMaxPct     = 100 - $this->minMarginRate;
        $remiseMaxAbsolue = $productsPrice * ($remiseMaxPct / 100);
        $specialPriceMin  = $productsPrice - $remiseMaxAbsolue;

        // Si aucun prix proposé (= 0), on considère que c'est faisable —
        // le taux réel sera choisi par PromotionScheduler (p1..p4)
        if ($proposedPrice <= 0) {
          return [
            'feasible'          => true,
            'margin_percentage' => $remiseMaxPct,
            'special_price_min' => round($specialPriceMin, 2),
            'remise_max_pct'    => round($remiseMaxPct, 2),
            'marge_reserve'     => 0,
            'reason'            => null,
          ];
        }

        $isFeasible       = ($proposedPrice >= $specialPriceMin);
        $actualMarginPct  = (($proposedPrice - 0) / $productsPrice) * 100;

        return [
          'feasible'          => $isFeasible,
          'margin_percentage' => round($actualMarginPct, 2),
          'special_price_min' => round($specialPriceMin, 2),
          'remise_max_pct'    => round($remiseMaxPct, 2),
          'marge_reserve'     => 0,
          'reason'            => $isFeasible ? null : 'Prix proposé inférieur au seuil (cost=0)',
        ];
      }

      // --- CAS NORMAL : cost > 0 ---

      // marge_brute = price - cost - handling
      $margeBrute = $productsPrice - $productsCost - $productsHandling;

      // marge_reserve = cost × (min_margin_rate ÷ 100)
      $margeReserve = $productsCost * ($this->minMarginRate / 100);

      // remise_max_absolue = marge_brute - marge_reserve
      $remiseMaxAbsolue = $margeBrute - $margeReserve;

      // special_price_min = price - remise_max_absolue
      $specialPriceMin = $productsPrice - $remiseMaxAbsolue;

      // remise_max_pct = (remise_max_absolue ÷ price) × 100
      $remiseMaxPct = ($productsPrice > 0) ? ($remiseMaxAbsolue / $productsPrice) * 100 : 0;

      // Garde-fou 1 : remise_max_absolue ≤ 0
      if ($remiseMaxAbsolue <= 0) {
        return [
          'feasible'          => false,
          'margin_percentage' => 0,
          'reason'            => 'marge insuffisante',
          'special_price_min' => round($specialPriceMin, 2),
          'marge_reserve'     => round($margeReserve, 2),
        ];
      }

      // Garde-fou 2 : special_price_min ≥ products_price
      if ($specialPriceMin >= $productsPrice) {
        return ['feasible' => false, 'margin_percentage' => 0, 'reason' => 'prix incohérent'];
      }

      // Si aucun prix proposé, on laisse PromotionScheduler choisir le taux
      if ($proposedPrice <= 0) {
        return [
          'feasible'          => true,
          'margin_percentage' => round($remiseMaxPct, 2),
          'special_price_min' => round($specialPriceMin, 2),
          'remise_max_pct'    => round($remiseMaxPct, 2),
          'marge_reserve'     => round($margeReserve, 2),
          'reason'            => null,
        ];
      }

      $isFeasible    = ($proposedPrice >= $specialPriceMin);
      $actualMargin  = (($proposedPrice - $productsCost - $productsHandling) / $productsPrice) * 100;

      return [
        'feasible'          => $isFeasible,
        'margin_percentage' => round($actualMargin, 2),
        'special_price_min' => round($specialPriceMin, 2),
        'remise_max_pct'    => round($remiseMaxPct, 2),
        'marge_reserve'     => round($margeReserve, 2),
        'reason'            => $isFeasible ? null : 'Le prix proposé est inférieur au seuil de rentabilité calculé',
      ];
    }
  }