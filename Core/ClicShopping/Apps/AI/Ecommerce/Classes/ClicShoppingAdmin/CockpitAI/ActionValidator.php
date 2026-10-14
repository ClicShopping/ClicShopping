<?php
  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\MarginCalculator;

  class ActionValidator
  {
    private mixed $db;
    private bool $debug;

    public function __construct() {
      $this->db = Registry::get('Db');
      $this->debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';
    }

    public function validateAction(string $actionCode, int $productId, array $productData, array $params = []): array
    {
      $actionCode = strtoupper($actionCode);

      $flags = $productData['metadata']['feature_flags'] ?? [];

      $quadrant = $productData['metadata']['scores']['quadrant'] ?? ($productData['quadrant'] ?? 'Q4');

      If ($this->debug) {
        error_log("[CockpitAI Debug] Product: " . $productId . " | Action: " . $actionCode . " | Quadrant: " . $quadrant);
      }

      $table = $this->mapTable($actionCode);
      $exists = $this->checkExists($table, $productId);

      // --- RÈGLE 1 : FAVORITES ---
      if ($actionCode === 'ADD_FAVORITES' || $actionCode === 'ADD_FAVORITES_STATUS') {
        if ($exists || ($flags['favorites'] ?? false) === true) {
          return ['status' => 'SKIP', 'reason' => 'Already exists'];
        }

        // Conflit Specials/Favorites : si une promo est active, on ne met pas en favoris.
        // SKIP (pas REMOVE) — on ne touche pas aux favoris existants, on ignore juste l'action.
        // Le ConflictResolver (Phase 7) gère l'arbitrage avant cette étape.
        if ($this->checkExists(':table_specials', $productId)) {
          if ($this->debug) {
            error_log("[CockpitAI Debug] -> SKIP: Conflict with Specials (promo active, favorites untouched)");
          }
          return ['status' => 'SKIP', 'reason' => 'Conflict: Special active'];
        }

        if ($this->debug) {
          error_log("[CockpitAI Debug] -> ADD to Favorites");
        }

        return ['status' => 'ADD', 'reason' => 'New favorite'];
      }

      // --- RÈGLE 2 : FEATURED ---
      if ($actionCode === 'MARK_FEATURED' || $actionCode === 'ADD_FEATURED_STATUS') {
        if ($exists || ($flags['feature'] ?? false) === true) {
          return ['status' => 'SKIP', 'reason' => 'Already exists'];
        }

        if ($quadrant !== 'Q2') {
          if ($exists) {
            If ($this->debug) {
              error_log("[CockpitAI Debug] -> REMOVE: Not Q2 anymore");
            }

            return ['status' => 'REMOVE', 'reason' => 'Exit Q2'];
          }

          If ($this->debug) {
          error_log("[CockpitAI Debug] -> SKIP: Not Q2");
          }

          return ['status' => 'SKIP'];
        }
        if ($exists) {
          If ($this->debug) {
            error_log("[CockpitAI Debug] -> SKIP: Already Featured");
          }

          return ['status' => 'SKIP'];
        }

        If ($this->debug) {
          error_log("[CockpitAI Debug] -> ADD to Featured");
        }

        return ['status' => 'ADD'];
      }

      // --- RÈGLE 3 : SPECIALS ---
      if ($actionCode === 'CREATE_PROMOTION' || $actionCode === 'APPLY_FLASH_DISCOUNT') {
        $minMarginConfig = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_MARGIN_RATE') ? (float)CLICSHOPPING_APP_ECOMMERCE_CAI_MARGIN_RATE : 15;

        // Si MARGIN_RATE = 0, aucune action autorisée
        if ($minMarginConfig <= 0) {
          return ['status' => 'SKIP', 'reason' => 'margin_rate_zero'];
        }

        // On utilise le taux P1 adaptatif comme prix de sonde si aucun prix proposé
        // pour que MarginCalculator puisse évaluer la faisabilité du premier palier
        $scheduler  = new PromotionScheduler();
        $views7d    = (int)($productData['views_7d'] ?? 0);
        $avgViews7d = (float)($productData['avg_views_7d'] ?? 0.0);
        $p1Rate     = $scheduler->getAdaptiveP1Rate($views7d, $avgViews7d, (float)($productData['high_intent_ratio'] ?? 0.0));
        $basePrice  = (float)($productData['products_price'] ?? 0);
        $probePrice = $params['new_price'] ?? ($basePrice > 0 ? $basePrice * (1 - $p1Rate / 100) : 0);

        $calculator = new MarginCalculator();
        $margin     = $calculator->isActionFeasible($productData, (float)$probePrice);

        If ($this->debug) {
          error_log("[CockpitAI Debug] -> MARGIN check: feasible=" . ($margin['feasible'] ? 'true' : 'false')
            . " margin_pct=" . ($margin['margin_percentage'] ?? 0)
            . "% required=" . $minMarginConfig . "%"
            . " probe_price=" . $probePrice);
        }

        if (!$margin['feasible'] || ($margin['margin_percentage'] ?? 0) < $minMarginConfig) {
          If ($this->debug) {
            error_log("[CockpitAI Debug] -> SKIP: Margin too low. Real: " . ($margin['margin_percentage'] ?? 0) . "% | Required: " . $minMarginConfig . "%");
          }

          return [
            'status'  => 'SKIP',
            'reason'  => 'insufficient_margin',
            'details' => 'Below required ' . $minMarginConfig . '%'
          ];
        }

        $res = $this->specialLimitedNumber($productId, $productData);

        If ($this->debug) {
          error_log("[CockpitAI Debug] -> SPECIAL Result: " . $res['status']);
        }

        return $res;
      }

      return ['status' => 'SKIP'];
    }

    private function mapTable(string $code): string {
      $code = strtoupper($code);
      if (str_contains($code, 'FEATURED')) return ':table_products_featured';
      if (str_contains($code, 'FAVORITE')) return ':table_products_favorites';
      return ':table_specials';
    }

    private function checkExists(string $table, int $productId): bool {
      $Q = $this->db->prepare("SELECT count(*) as total
                               FROM " . $table . "
                               WHERE products_id = :id 
                               AND status = 1");
      $Q->bindInt(':id', $productId);
      $Q->execute();
      return ($Q->valueInt('total') > 0);
    }

    public function specialLimitedNumber(int $productId, array $productData = []): array {
      $scheduler       = new PromotionScheduler();
      $views7d         = (int)($productData['views_7d'] ?? 0);
      $avgViews7d      = (float)($productData['avg_views_7d'] ?? 0.0);
      $highIntentRatio = (float)($productData['high_intent_ratio'] ?? 0.0);

      // Récupérer les infos de la promo actuelle
      $Qcheck = $this->db->prepare('SELECT specials_date_added, DATEDIFF(NOW(), specials_date_added) as days_active 
                                    FROM :table_specials 
                                    WHERE products_id = :pId 
                                    AND status = 1
                                    ');
      $Qcheck->bindInt(':pId', $productId);
      $Qcheck->execute();
      $res = $Qcheck->fetch();

      if ($res === false) return ['status' => 'ADD'];

      // Compter les ventes depuis le début de cette promo
      $Qorder = $this->db->prepare('SELECT count(*) as total 
                                    FROM :table_orders_products op 
                                    JOIN :table_orders o ON op.orders_id = o.orders_id
                                    WHERE op.products_id = :pId 
                                    AND o.date_purchased >= :date_start');
      $Qorder->bindInt(':pId', $productId);
      $Qorder->bindValue(':date_start', $res['specials_date_added']);
      $Qorder->execute();

      // Appel adaptatif + high-intent (Phase 4)
      // productData transmis pour les garde-fous marge + stock dans PromotionScheduler
      $nextStep = $scheduler->getNextStep(
        (int)$res['days_active'],
        (int)$Qorder->valueInt('total'),
        $views7d,
        $avgViews7d,
        $highIntentRatio,
        $productData
      );

      if ($this->debug()) {
        error_log(['CockpitAI specialLimitedNumber]  $nextStep' . $nextStep['action']. ' ' . $nextStep['reason']]);
      }


      return [
        'status'           => ($nextStep['action'] === 'STOP') ? 'REMOVE' : 'UPDATE',
        'new_rate'         => $nextStep['rate'],
        'reason'           => $nextStep['reason'],
        'trigger_strategy' => $nextStep['trigger_strategy'] ?? 'standard',
      ];
    }
  }