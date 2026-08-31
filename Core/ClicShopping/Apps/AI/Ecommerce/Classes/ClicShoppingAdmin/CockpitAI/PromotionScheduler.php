<?php
  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\MarginCalculator;

  /**
* PromotionScheduler v5
*
* Manages the escalation of promotional tiers P1-P4 with two levels of adaptation:
* Level 1 — Temporal Adaptive (views7d / avgViews7d):
* Compresses or expands the thresholds at J+7/14/21 depending on relative traffic.
*
* Level 2 — High-Intent Acceleration (Phase 4):
* If high_intent_ratio > threshold AND sufficient stock AND sufficient margin:
* → Skips P1, starts directly at P2 (hot iron, act now)
* → Mark trigger_strategy = 'high_intent_flash' for FeedbackCollector
*
* Mandatory Acceleration Safeguards (critical point 6):
* - margin_percentage > CLICSHOPPING_APP_ECOMMERCE_CAI_MARGIN_RATE (via MarginCalculator)
* - products_quantity above the alert threshold and safety_stock (no promotion on imminent stockout)
* - ordersDetected = 0 (already an order → STOP, no acceleration)
*/
  class PromotionScheduler
  {
    /** Seuil high_intent_ratio au-dessus duquel l'accélération P1→P2 se déclenche */
    private const HIGH_INTENT_THRESHOLD = 0.7;

    private array $config;
    private bool $debug;

    public function __construct()
    {
      $this->debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';

      // Récupération des taux depuis la configuration ou valeurs par défaut
      $this->config = [
        'p1' => (float)(defined('CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P1') ? CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P1 : 5),
        'p2' => (float)(defined('CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P2') ? CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P2 : 8),
        'p3' => (float)(defined('CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P3') ? CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P3 : 12),
        'p4' => (float)(defined('CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P4') ? CLICSHOPPING_APP_ECOMMERCE_CAI_PROMO_P4 : 15),
      ];
    }

    /**
     * Expose le taux P1 pour que ActionValidator puisse sonder la faisabilité
     * avant de lancer la promotion.
     */
    public function getP1Rate(): float
    {
      return $this->config['p1'];
    }

    /**
     * Calcule le taux de remise adaptatif pour P1 en tenant compte du trafic.
     * Si high_intent_ratio disponible et élevé → taux P1 plus agressif d'emblée.
     *
     * @param int   $views7d         Vues du produit sur 7 jours
     * @param float $avgViews7d      Moyenne catalogue sur 7 jours
     * @param float $highIntentRatio Ratio intention [0..1] (0 = non dispo)
     * @return float                 Taux P1 modulé
     */
    public function getAdaptiveP1Rate(int $views7d, float $avgViews7d, float $highIntentRatio = 0.0): float
    {
      $coefficient = $this->getTrafficCoefficient($views7d, $avgViews7d);

      // Bonus high-intent : si ratio élevé, on commence légèrement plus haut que P1
      // mais sans dépasser P2 (l'accélération franche est dans getNextStep)
      if ($highIntentRatio > self::HIGH_INTENT_THRESHOLD) {
        $intentBonus = ($highIntentRatio - self::HIGH_INTENT_THRESHOLD) * 2.0; // 0..0.6
        $coefficient = max($coefficient, 1.0 + $intentBonus);
      }

      $adaptedRate = $this->config['p1'] * $coefficient;

      // Clamp : jamais moins que p1 de base, jamais plus que p2
      // (P2 direct = réservé à getNextStep avec garde-fous complets)
      return max($this->config['p1'], min($this->config['p2'], round($adaptedRate, 2)));
    }

    /**
     * Calcule un coefficient d'adaptation basé sur le trafic du produit
     * comparé à la moyenne catalogue sur 7 jours.
     *
     * Logique :
     *   - Produit reçoit 2x+ le trafic moyen  → coefficient < 1 (ralentir les paliers, moins agressif)
     *   - Produit reçoit entre 0.5x et 2x      → coefficient = 1 (paliers normaux)
     *   - Produit reçoit moins de 0.5x du trafic → coefficient > 1 (accélérer les paliers)
     *   - Trafic moyen catalogue = 0            → coefficient = 1 (pas de données, neutre)
     *
     * @param int   $views7d     Vues du produit sur 7 jours
     * @param float $avgViews7d  Moyenne catalogue sur 7 jours
     * @return float             Coefficient [0.5 .. 2.0]
     */
    public function getTrafficCoefficient(int $views7d, float $avgViews7d): float
    {
      if ($avgViews7d <= 0) {
        return 1.0; // pas de référence catalogue → neutre
      }

      $ratio = $views7d / $avgViews7d;

      if ($ratio >= 2.0) {
        // Produit très visible → paliers ralentis (remise moins agressive)
        $coefficient = 0.5;
      } elseif ($ratio >= 1.0) {
        // Trafic normal à bon → légère modération
        $coefficient = 1.0 - (($ratio - 1.0) * 0.25); // 1.0 → 0.75
      } elseif ($ratio >= 0.5) {
        // Trafic un peu faible → paliers légèrement accélérés
        $coefficient = 1.0 + ((1.0 - $ratio) * 0.5); // 1.0 →0.9
      } else {
        // Trafic très faible → paliers fortement accélérés
        $coefficient = 2.0;
      }

      // Clamp entre 0.5 et 2.0
      return max(0.5, min(2.0, $coefficient));
    }

    /**
     * REQ-3.3 + Adaptatif + Accélération high-intent (Phase 4)
     *
     * @param int   $daysActive       Jours depuis le début de la promo
     * @param int   $ordersDetected   Commandes depuis le début de la promo
     * @param int   $views7d          Vues du produit sur 7 jours
     * @param float $avgViews7d       Moyenne catalogue sur 7 jours
     * @param float $highIntentRatio  Ratio d'intention [0..1] depuis tracking (null = non dispo)
     * @param array $productData      Données produit pour garde-fous marge + stock
     */
    public function getNextStep(
      int   $daysActive,
      int   $ordersDetected,
      int   $views7d       = 0,
      float $avgViews7d    = 0.0,
      float $highIntentRatio = 0.0,
      array $productData   = []
    ): array {
      // ── Règle d'OR : Arrêt auto dès une commande ──────────────────────────
      if ($ordersDetected > 0) {
        if ($this->debug) {
          error_log("[Info CockpitAI PromotionScheduler] - order detected - Conversion detected, removing discount");
        }

        return [
          'action'           => 'STOP',
          'rate'             => 0,
          'reason'           => 'Conversion detected, removing discount',
          'trigger_strategy' => 'standard',
        ];
      }

      // ── Alert threshold: no promotion, regardless of the path ────────
      // The P2/P3/P4 progression did not control any stock. 'STOP' is the only value that
       // the caller reads as "do not promote": it maps it to REMOVE.
      if (!empty($productData) && !$this->isStockSufficient($productData)) {
        return [
          'action'           => 'STOP',
          'rate'             => 0,
          'reason'           => 'Stock at or below the alert threshold, no promotion',
          'trigger_strategy' => 'standard',
        ];
      }

      // ── Phase 4 : Accélération high-intent ────────────────────────────────
      // Conditions :
      //   1. high_intent_ratio > HIGH_INTENT_THRESHOLD (0.7)
      //   2. Aucune commande (déjà vérifié ci-dessus)
      //   3. Marge suffisante (MarginCalculator)
      //   4. Stock déjà contrôlé plus haut, pour tous les chemins
      //   5. Promo au début du cycle (daysActive < seuil P2 normal)
      if ($highIntentRatio > self::HIGH_INTENT_THRESHOLD
        && !empty($productData)
        && $this->isMarginSufficient($productData)
        && $daysActive < 7  // seulement au début — sinon laisser la progression normale
      ) {
        if ($this->debug) {
          error_log("[Info CockpitAI PromotionScheduler] HIGH-INTENT ACCELERATION:"
            . " ratio=$highIntentRatio threshold=" . self::HIGH_INTENT_THRESHOLD
            . " → skipping P1, starting at P2");
        }

        return [
          'action'           => 'P2',
          'rate'             => $this->config['p2'],
          'reason'           => "High-intent acceleration (ratio=$highIntentRatio > " . self::HIGH_INTENT_THRESHOLD . "): starting at P2",
          'trigger_strategy' => 'high_intent_flash',  // tag pour FeedbackCollector
        ];
      }

      // ── Coefficient adaptatif temporel (niveau 1 — views7d) ───────────────
      $coefficient = ($avgViews7d > 0) ? $this->getTrafficCoefficient($views7d, $avgViews7d) : 1.0;

      $thresholdP2 = (int)round(7  * $coefficient);
      $thresholdP3 = (int)round(14 * $coefficient);
      $thresholdP4 = (int)round(21 * $coefficient);

      if ($this->debug) {
        error_log("[Info CockpitAI PromotionScheduler] Adaptive mode: views7d=$views7d avg=$avgViews7d coeff=$coefficient"
          . " thresholds: P2=J+$thresholdP2 P3=J+$thresholdP3 P4=J+$thresholdP4");
      }

      // P4 — Maximum
      if ($daysActive >= $thresholdP4) {
        if ($this->debug) error_log("[Info CockpitAI PromotionScheduler] - P4 Final step reached after {$thresholdP4} days");
        return ['action' => 'P4', 'rate' => $this->config['p4'], 'reason' => "Final step reached after {$thresholdP4} days (adaptive)", 'trigger_strategy' => 'standard'];
      }

      // P3 — Poussée
      if ($daysActive >= $thresholdP3) {
        if ($this->debug) error_log("[Info CockpitAI PromotionScheduler] - P3 Increasing discount after {$thresholdP3} days");
        return ['action' => 'P3', 'rate' => $this->config['p3'], 'reason' => "Increasing discount after {$thresholdP3} days (adaptive)", 'trigger_strategy' => 'standard'];
      }

      // P2 — Relance
      if ($daysActive >= $thresholdP2) {
        if ($this->debug) error_log("[Info CockpitAI PromotionScheduler] - P2 First escalation after {$thresholdP2} days");
        return ['action' => 'P2', 'rate' => $this->config['p2'], 'reason' => "First escalation after {$thresholdP2} days (adaptive)", 'trigger_strategy' => 'standard'];
      }

      // P1 — Initial
      if ($this->debug) error_log("[Info CockpitAI PromotionScheduler] - P1 Initial phase (coeff=$coefficient)");

      return [
        'action'           => 'P1',
        'rate'             => $this->config['p1'],
        'reason'           => 'Initial phase',
        'trigger_strategy' => 'standard',
      ];
    }

    // ── Garde-fous privés (critic point 6) ────────────────────────────────────

    /**
     * Vérifie que la marge est suffisante avant d'accélérer la promotion.
     * Utilise MarginCalculator avec proposedPrice=0 (laisse le scheduler choisir le taux).
     * Retourne false si MARGIN_RATE = 0 ou si le prix produit est nul.
     */
    private function isMarginSufficient(array $productData): bool
    {
      try {
        $calculator = new MarginCalculator();
        $margin = $calculator->isActionFeasible($productData, 0);

        if (!($margin['feasible'] ?? false)) {
          if ($this->debug) {
            error_log("[Info CockpitAI PromotionScheduler] HIGH-INTENT blocked: margin not feasible — " . ($margin['reason'] ?? 'unknown'));
          }
          return false;
        }

        $minMarginRate = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_MARGIN_RATE')
          ? (float)CLICSHOPPING_APP_ECOMMERCE_CAI_MARGIN_RATE
          : 15.0;

        $marginPct = $margin['margin_percentage'] ?? 0;

        if ($marginPct < $minMarginRate) {
          if ($this->debug) {
            error_log("[Info CockpitAI PromotionScheduler] HIGH-INTENT blocked: margin {$marginPct}% < required {$minMarginRate}%");
          }
          return false;
        }

        return true;

      } catch (\Throwable $e) {
        error_log("[Info CockpitAI PromotionScheduler] isMarginSufficient error: " . $e->getMessage());
        return false; // fail-safe : bloquer l'accélération en cas d'erreur
      }
    }

    /**
     * Vérifie que le stock est au-dessus du safety_stock avant d'accélérer.
     * Une promo sur un produit en rupture imminente est contre-productive.
     *
     * Condition : products_quantity > safety_stock (avec marge de sécurité ×1.5)
     * Si safety_stock non disponible : stock > 0 suffit.
     */
    private function isStockSufficient(array $productData): bool
    {
      $currentStock = (float)($productData['products_quantity'] ?? 0);
      $safetyStock  = (float)($productData['safety_stock'] ?? 0);
      $alertStock   = (float)($productData['alert_stock'] ?? 0);

      if ($currentStock <= 0) {
        if ($this->debug) {
          error_log("[Info CockpitAI PromotionScheduler] STOCK blocked: stock=$currentStock ≤ 0");
        }
        return false;
      }

      // The alert threshold binds on its own: safety_stock is 0 for a product with no demand
      // history, and the check below is then skipped entirely.
      if ($alertStock > 0 && $currentStock <= $alertStock) {
        if ($this->debug) {
          error_log("[Info CockpitAI PromotionScheduler] STOCK blocked: stock=$currentStock ≤ alert_stock=$alertStock");
        }
        return false;
      }

      // Si safety_stock disponible : exiger au moins 1.5× le stock de sécurité
      // (pour absorber la demande supplémentaire générée par la promo)
      if ($safetyStock > 0 && $currentStock < ($safetyStock * 1.5)) {
        if ($this->debug) {
          error_log("[Info CockpitAI PromotionScheduler] STOCK blocked: stock=$currentStock < safety_stock×1.5=" . ($safetyStock * 1.5));
        }
        return false;
      }

      return true;
    }
  }
