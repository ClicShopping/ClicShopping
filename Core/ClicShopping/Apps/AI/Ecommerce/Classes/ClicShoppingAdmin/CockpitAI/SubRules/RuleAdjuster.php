<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
   * @Licence GPL 2 & MIT
   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

  use ClicShopping\OM\Registry;

  /**
   * RuleAdjuster v5
   *
   * Lit les statistiques de FeedbackCollector et ajuste les seuils dans
   * clic_cockpit_ai_rule_thresholds. RuleRegistry::loadThresholds() relit
   * cette table à chaque instanciation — les closures utilisent automatiquement
   * les nouveaux seuils sans redéploiement.
   *
   * Seuils gérés :
   *   seo_low_score, description_poor, return_threshold, recommendations_min
   *   promo_p1, promo_p2, promo_p3, promo_p4, margin_rate
   *
   * Garde-fous :
   *   - MIN_SAMPLES cycles minimum avant ajustement (défaut 30)
   *   - Variation ≤ MAX_ADJUSTMENT_PCT du seuil actuel (défaut 20%)
   *   - Bornes absolues par seuil (BOUNDS)
   *   - Cooldown 7 jours par règle
   *   - Audit dans cockpit_ai_rule_adjustments
   */
  class RuleAdjuster
  {
    private mixed $db;
    private bool $debug;
    private FeedbackCollector $feedbackCollector;

    private const MIN_SAMPLES        = 30;
    private const MAX_ADJUSTMENT_PCT = 0.20;

    private const BOUNDS = [
      'seo_low_score'        => ['min' => 20.0,  'max' => 80.0],
      'description_poor'     => ['min' => 0.2,   'max' => 0.8],
      'return_threshold'     => ['min' => 0.02,  'max' => 0.5],
      'recommendations_min'  => ['min' => 1.0,   'max' => 10.0],
      'promo_p1'             => ['min' => 2.0,   'max' => 15.0],
      'promo_p2'             => ['min' => 4.0,   'max' => 20.0],
      'promo_p3'             => ['min' => 6.0,   'max' => 30.0],
      'promo_p4'             => ['min' => 8.0,   'max' => 40.0],
      'margin_rate'          => ['min' => 5.0,   'max' => 50.0],
      // Seuils tracking (Phase 6)
      'high_intent_threshold' => ['min' => 0.4,  'max' => 0.95],
      'trending_multiplier'   => ['min' => 1.2,  'max' => 5.0],
    ];

    public function __construct()
    {
      $this->db                = Registry::get('Db');
      $this->debug             = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';
      $this->feedbackCollector = new FeedbackCollector();
    }

    /**
     * Point d'entrée principal.
     * Analyse les stats de feedback et ajuste les règles si justifié.
     *
     * @return array Résumé des ajustements effectués
     */
    public function run(): array
    {
      $adjustments = [];

      if ($this->debug) {
        error_log("[RuleAdjuster] Run started");
      }

      // ── Ajustement promos (taux P1..P4, margin_rate) ──────────────────────
      $specialsStats = $this->feedbackCollector->getActionStats('specials', self::MIN_SAMPLES);
      if ($specialsStats !== null) {
        $result = $this->adjustPromotionThresholds($specialsStats);
        if ($result) $adjustments[] = $result;
      }

      // ── Ajustement high_intent_threshold (Phase 6) ────────────────────────
      // Segmente flash vs standard et ajuste le seuil en fonction de l'efficacité
      // observée du groupe flash — sans toucher aux seuils si volume insuffisant.
      $strategyStats = $this->feedbackCollector->getActionStatsByStrategy('specials', 20);
      $highIntentResult = $this->adjustHighIntentThreshold($strategyStats);
      if ($highIntentResult) $adjustments[] = $highIntentResult;

      // ── Featured / favorites : log uniquement pour Actor-Critic futur ─────
      foreach (['featured', 'favorites'] as $type) {
        $stats = $this->feedbackCollector->getActionStats($type, self::MIN_SAMPLES);
        if ($stats !== null) {
          $this->saveAdjustmentLog("{$type}_effectiveness", $stats['positive_rate'], $stats['positive_rate'], $stats['sample_size'], $stats['avg_delta_y']);
        }
      }

      if ($this->debug) error_log("[RuleAdjuster] Run done | adjustments=" . count($adjustments));

      return $adjustments;
    }

    /**
     * Ajuste high_intent_threshold selon l'efficacité observée des promos flash.
     *
     * Logique (symétrique à computeDirection mais sur le groupe flash uniquement) :
     *   flash.positive_rate < 40%  → augmenter le seuil (être plus sélectif)
     *   flash.positive_rate > 70%  → baisser le seuil (l'accélération fonctionne bien)
     *   entre 40-70%               → zone neutre, pas d'ajustement
     *
     * Comparaison flash vs standard :
     *   Si flash fait pire que standard → augmenter seuil même si > 40%
     *   (évite d'amplifier une stratégie moins bonne que la baseline)
     */
    private function adjustHighIntentThreshold(array $strategyStats): ?array
    {
      $flashStats    = $strategyStats['flash']    ?? null;
      $standardStats = $strategyStats['standard'] ?? null;

      // Données insuffisantes — pas d'ajustement
      if ($flashStats === null) {
        if ($this->debug) {
          error_log("[RuleAdjuster] high_intent_threshold: insufficient flash data, skipping");
        }
        return null;
      }

      if (!$this->canAdjust('high_intent_threshold')) {
        if ($this->debug) {
          error_log("[RuleAdjuster] high_intent_threshold: cooldown active, skipping");
        }
        return null;
      }

      $currentThreshold = $this->readThreshold('high_intent_threshold', 0.7);
      $flashPositiveRate = $flashStats['positive_rate'];
      $flashAvgDelta     = $flashStats['avg_delta_y'];

      // Déterminer si flash est moins bon que standard (si dispo)
      $flashWorseThanStandard = false;
      if ($standardStats !== null) {
        $flashWorseThanStandard = ($flashPositiveRate < $standardStats['positive_rate'] - 10);
      }

      $newThreshold = null;
      $reason       = '';

      if ($flashPositiveRate < 40 || $flashWorseThanStandard) {
        // Flash inefficace ou moins bon que standard → être plus sélectif
        $delta        = min(0.05, ($currentThreshold * 0.1)); // +5% max par ajustement
        $newThreshold = $this->clamp('high_intent_threshold', $currentThreshold + $delta);
        $reason       = "Flash positive_rate={$flashPositiveRate}% < 40% or worse than standard → raising threshold";

      } elseif ($flashPositiveRate > 70 && $flashAvgDelta > 0) {
        // Flash très efficace → baisser le seuil (déclencher plus souvent)
        $delta        = min(0.03, ($currentThreshold * 0.05)); // -3% max par ajustement
        $newThreshold = $this->clamp('high_intent_threshold', $currentThreshold - $delta);
        $reason       = "Flash positive_rate={$flashPositiveRate}% > 70% → lowering threshold";
      }

      if ($newThreshold === null || abs($newThreshold - $currentThreshold) < 0.01) {
        if ($this->debug) {
          error_log("[RuleAdjuster] high_intent_threshold: neutral zone ($flashPositiveRate%), no adjustment");
        }
        return null;
      }

      $this->writeThreshold('high_intent_threshold', $newThreshold, $currentThreshold, $flashStats);

      if ($this->debug) {
        error_log("[RuleAdjuster] high_intent_threshold: $currentThreshold → $newThreshold | $reason");
      }

      return [
        'rule_key'      => 'high_intent_threshold',
        'from'          => $currentThreshold,
        'to'            => $newThreshold,
        'reason'        => $reason,
        'flash_stats'   => $flashStats,
        'standard_stats'=> $standardStats,
      ];
    }

    private function adjustPromotionThresholds(array $stats): ?array
    {
      $direction = $this->computeDirection($stats['avg_delta_y'], $stats['positive_rate']);
      if ($direction === 0 || !$this->canAdjust('promo_p1')) return null;

      $factor  = min(abs($stats['avg_delta_y']) / 10, self::MAX_ADJUSTMENT_PCT);
      $results = [];

      $currentP1     = $this->readThreshold('promo_p1',    5.0);
      $currentP2     = $this->readThreshold('promo_p2',    8.0);
      $currentMargin = $this->readThreshold('margin_rate', 15.0);

      if ($direction > 0) {
        $candidates = [
          'promo_p1'    => $currentP1     * (1 - $factor),
          'margin_rate' => $currentMargin * (1 - $factor * 0.5),
        ];
      } else {
        $candidates = [
          'promo_p1'    => $currentP1     * (1 + $factor),
          'promo_p2'    => $currentP2     * (1 + $factor),
          'margin_rate' => $currentMargin * (1 + $factor * 0.5),
        ];
      }

      $currentValues = ['promo_p1' => $currentP1, 'promo_p2' => $currentP2, 'margin_rate' => $currentMargin];

      foreach ($candidates as $key => $newRaw) {
        $newVal = $this->clamp($key, $newRaw);
        $oldVal = $currentValues[$key] ?? $newVal;

        if (abs($newVal - $oldVal) >= 0.1) {
          $this->writeThreshold($key, $newVal, $oldVal, $stats);
          $results[$key] = ['from' => $oldVal, 'to' => $newVal];
        }
      }

      return empty($results) ? null : [
        'action_type'   => 'specials',
        'direction'     => $direction > 0 ? 'effective' : 'ineffective',
        'sample_size'   => $stats['sample_size'],
        'avg_delta_y'   => $stats['avg_delta_y'],
        'positive_rate' => $stats['positive_rate'],
        'adjustments'   => $results,
      ];
    }

    private function readThreshold(string $ruleKey, float $default): float
    {
      try {
        $Q = $this->db->prepare('SELECT threshold FROM :table_products_cockpit_ai_rule_thresholds WHERE rule_key = :key LIMIT 1');
        $Q->bindValue(':key', $ruleKey);
        $Q->execute();
        if ($Q->fetch()) return (float)$Q->valueDecimal('threshold');
      } catch (\Throwable) {}
      return $default;
    }

    private function writeThreshold(string $ruleKey, float $newValue, float $oldValue, array $stats): void
    {
      try {
        $Q = $this->db->prepare('SELECT rule_key FROM :table_products_cockpit_ai_rule_thresholds WHERE rule_key = :key LIMIT 1');
        $Q->bindValue(':key', $ruleKey);
        $Q->execute();

        if ($Q->fetch()) {
          $this->db->save(':table_products_cockpit_ai_rule_thresholds', ['threshold' => round($newValue, 4), 'date_modified' => 'now()'], ['rule_key' => $ruleKey]);
        } else {
          $this->db->save(':table_products_cockpit_ai_rule_thresholds', ['rule_key' => $ruleKey, 'threshold' => round($newValue, 4), 'default_val' => round($newValue, 4), 'date_modified' => 'now()']);
        }

        $this->saveAdjustmentLog($ruleKey, $oldValue, $newValue, $stats['sample_size'], $stats['avg_delta_y']);

        if ($this->debug) {
          error_log("[RuleAdjuster] $ruleKey: $oldValue → $newValue");
        }

      } catch (\Throwable $e) {
        error_log("[RuleAdjuster] writeThreshold error $ruleKey: " . $e->getMessage());
      }
    }

    private function computeDirection(float $avgDeltaY, float $positiveRate): int
    {
      if ($positiveRate >= 60 && $avgDeltaY > 0) return 1;
      if ($positiveRate < 40 || $avgDeltaY <= 0) return -1;
      return 0;
    }

    private function canAdjust(string $ruleKey): bool
    {
      try {
        $Q = $this->db->prepare('SELECT date_created FROM :table_products_cockpit_ai_rule_adjustments WHERE rule_key = :key ORDER BY date_created DESC LIMIT 1');
        $Q->bindValue(':key', $ruleKey);
        $Q->execute();
        if (!$Q->fetch()) return true;
        return (new \DateTimeImmutable())->diff(new \DateTimeImmutable($Q->value('date_created')))->days >= 7;
      } catch (\Throwable) {
        return true;
      }
    }

    private function clamp(string $ruleKey, float $value): float
    {
      $b = self::BOUNDS[$ruleKey] ?? ['min' => 0.0, 'max' => 9999.0];
      return round(max($b['min'], min($b['max'], $value)), 4);
    }

    private function saveAdjustmentLog(string $ruleKey, float $oldValue, float $newValue, int $sampleSize, float $avgDeltaY): void
    {
      try {
        $this->db->save(':table_products_cockpit_ai_rule_adjustments', [
          'rule_key'          => $ruleKey,
          'old_value'         => round($oldValue, 4),
          'new_value'         => round($newValue, 4),
          'delta'             => round($newValue - $oldValue, 4),
          'sample_size'       => $sampleSize,
          'avg_delta_score_y' => round($avgDeltaY, 2),
          'date_created'      => 'now()'
        ]);
      } catch (\Throwable $e) {
        error_log("[RuleAdjuster] saveAdjustmentLog error: " . $e->getMessage());
      }
    }

    public function getAdjustmentHistory(int $limit = 50): array
    {
      $Q = $this->db->prepare('SELECT rule_key, old_value, new_value, delta, sample_size, avg_delta_score_y, date_created FROM :table_products_cockpit_ai_rule_adjustments ORDER BY date_created DESC LIMIT :limit');
      $Q->bindInt(':limit', $limit);
      $Q->execute();
      $history = [];
      while ($row = $Q->fetch()) $history[] = $row;
      return $history;
    }

    public function getCurrentThresholds(): array
    {
      $thresholds = [];
      try {
        $Q = $this->db->prepare('SELECT rule_key, 
                                        threshold, 
                                        default_val, 
                                        date_modified 
                                  FROM :table_products_cockpit_ai_rule_thresholds 
                                  ORDER BY rule_key
                                  ');
        $Q->execute();
        while ($row = $Q->fetch()) {
          $thresholds[$row['rule_key']] = [
            'current'            => (float)$row['threshold'],
            'default'            => (float)$row['default_val'],
            'delta_from_default' => round((float)$row['threshold'] - (float)$row['default_val'], 4),
            'date_modified'      => $row['date_modified'],
          ];
        }
      } catch (\Throwable $e) {
        error_log("[RuleAdjuster] getCurrentThresholds error: " . $e->getMessage());
      }
      return $thresholds;
    }
  }
