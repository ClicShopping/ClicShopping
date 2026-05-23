<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubRules;

  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubRules\{Action, ActionPriority};
  use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
  use ClicShopping\OM\Registry;

  /**
   * RuleRegistry
   *
   * Central registry for all CockpitAI recommendation rules.
   *
   * This registry stores the complete catalog of rules that map product state
   * (scores, quadrant, SEO status, commercial metrics) to recommended actions.
   *
   * Standard v4.23 Rules Catalog:
   * ────────────────────────────────────────────────────────────────────────
   * 1. seo_not_analyzed      : seo_status = NOT_ANALYZED
   *                            → Launch SEO Analysis (high)
   *
   * 2. seo_low_score         : seo_score < 50 AND seo_status = ANALYZED
   *                            → Optimize SEO (high)
   *
   * 3. description_poor      : score_description < 0.5
   *                            → Optimize Description (high)
   *
   * 4. special_needed        : quadrant IN (Q2, Q3) AND !promo_active
   *                            → Create Promotion (medium)
   *
   * 5. feature_needed        : quadrant = Q2 AND !feature
   *                            → Add Featured Status (medium)
   *
   * 6. recommendations_low   : recommendations < 2 AND score_y > 50
   *                            → Add Cross-Recommendations (low)
   *
   * 7. category_reposition   : quadrant = Q3 AND return_rate <= threshold
   *                            → Reposition Category (medium)
   *
   * 8. consider_removal      : quadrant = Q3 AND return_rate > threshold
   *                            → Consider Removal (critical, exclusive)
   * ────────────────────────────────────────────────────────────────────────
   *
   * Usage:
   * ──────
   * $registry = RuleRegistry->standard();
   * $allRules = $registry->all();
   * $rule = $registry->get('seo_not_analyzed');
   *
   * RuleContext keys expected by standard rules:
   *  - quadrant          : string   — Q1|Q2|Q3|Q4|Q_intermediate
   *  - score_x           : float    — [0..100]
   *  - score_y           : float    — [0..100]
   *  - seo_status        : string   — NOT_ANALYZED|ANALYZED
   *  - seo_score         : float|null
   *  - score_description : float    — normalized [0..1] from factors_x['description']
   *  - promo_active      : bool
   *  - feature           : bool
   *  - recommendations   : int      — number of active cross-recommendations
   *  - return_rate       : float    — [0..1]
   *  - return_threshold  : float    — configurable, default 0.1
   * ────────────────────────────────────────────────────────────────────────
   *
   * Thresholds loaded from clic_products_cockpit_ai_rule_thresholds (BD) instead of
   * hardcoded in closures. RuleAdjuster writes to this table;
   * RuleRegistry rereads it at each instantiation.
   *
   * Threshold keys managed:
   * seo_low_score: SEO score below which the rule is triggered (default 50)
   * description_poor: description score below which the rule is triggered (default 0.5)
   * return_threshold: return rate above which withdrawal is recommended (default 0.1)
   * recommendations_min: minimum number of cross-recs required (default 2)
   * high_intent_threshold: minimum intent ratio for “Hidden Nugget” (default 0.6)
   * trending_multiplier: avg_catalog_heat multiplier for "Detected Trend" (default 2.0)
   *
   * SQL to execute if these thresholds are not yet in BD:
   * INSERT IGNORE INTO clic_products_cockpit_ai_rule_thresholds VALUES
   * ('high_intent_threshold', 0.60, 0.60, NOW()),
   * ('trending_multiplier', 2.00, 2.00, NOW());
   */
  class RuleRegistry
  {
    /**
     * @var array<string, RuleInterface> Indexed by rule code
     */
    private array $rules = [];
    private mixed $app;
    private bool $debug;

    public function __construct()
    {
      $this->debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';

      if (!Registry::exists('Ecommerce')) {
        Registry::set('Ecommerce', new EcommerceApp());
      }

      $this->app = Registry::get('Ecommerce');
      $this->app->loadDefinitions('Sites/ClicShoppingAdmin/cockpit_ai');
    }

    /**
     * Charge les seuils adaptatifs depuis clic_cockpit_ai_rule_thresholds.
     * Retourne les valeurs par défaut si la table est vide ou inaccessible.
     *
     * @return array<string, float>
     */
    private static function loadThresholds(): array
    {
      $defaults = [
        'seo_low_score'        => 50.0,
        'description_poor'     => 0.5,
        'return_threshold'     => 0.1,
        'recommendations_min'  => 2.0,
        // Seuils tracking (Phase 3)
        'high_intent_threshold' => 0.6,   // ratio intention min pour "Pépite Cachée"
        'trending_multiplier'   => 2.0,   // x fois avg_catalog_heat pour "Tendance"
      ];

      try {
        $db = Registry::get('Db');
        $Q  = $db->prepare('SELECT rule_key, 
                                  threshold 
                            FROM :table_products_cockpit_ai_rule_thresholds
                            ');
        $Q->execute();

        while ($row = $Q->fetch()) {
          $key = $row['rule_key'];
          if (array_key_exists($key, $defaults)) {
            $defaults[$key] = (float)$row['threshold'];
          }
        }
      } catch (\Throwable) {
        // Table absente ou erreur DB → défauts utilisés, moteur continue
      }

      return $defaults;
    }

    /**
     * Factory: create a RuleRegistry pre-populated with the standard v4.23+v5 rules catalog.
     * Seuils chargés depuis BD via loadThresholds() — adaptables par RuleAdjuster.
     *
     * @return self Registry with standard rules
     */
    public function standard(): self
    {
      $registry   = new self();
      $thresholds = self::loadThresholds();

      // Extraction des seuils pour injection dans les closures
      $seoThreshold    = $thresholds['seo_low_score'];
      $descThreshold   = $thresholds['description_poor'];
      $retThreshold    = $thresholds['return_threshold'];
      $recoMin         = (int)$thresholds['recommendations_min'];
      $highIntentMin   = $thresholds['high_intent_threshold'];  // Phase 3
      $trendMultiplier = $thresholds['trending_multiplier'];    // Phase 3

      // ────────────────────────────────────────────────────────────────────
      // Rule 1: seo_not_analyzed (Req. 14.1)
      // ────────────────────────────────────────────────────────────────────
      $registry->register(new Rule(
        code:        'seo_not_analyzed',
        action:      new Action(
          code:        'launch_seo_analysis',
          label:       $this->app->getDef('text_label_seo_analyzed'),
          priority:    ActionPriority::High,
          description: $this->app->getDef('text_seo_not_analyzed'),
          exclusive:   false,
        ),
        condition:   static function (array $ctx): bool {
          return isset($ctx['seo_status'])
            && $ctx['seo_status'] === 'NOT_ANALYZED';
        },
        specificity: 1,
      ));

      // ────────────────────────────────────────────────────────────────────
      // Rule 2: seo_low_score (Req. 14.2)
      // ────────────────────────────────────────────────────────────────────
      $registry->register(new Rule(
        code:        'seo_low_score',
        action:      new Action(
          code:        'optimize_seo',
          label:       $this->app->getDef('text_label_optimize_seo'),
          priority:    ActionPriority::High,
          description: $this->app->getDef('text_seo_low_score', ['seoThreshold' => $seoThreshold]),
          exclusive:   false,
        ),
        condition:   static function (array $ctx) use ($seoThreshold): bool {
          return isset($ctx['seo_status'], $ctx['seo_score'])
            && $ctx['seo_status'] === 'ANALYZED'
            && $ctx['seo_score'] < $seoThreshold;
        },
        specificity: 2,
      ));

      // ────────────────────────────────────────────────────────────────────
      // Rule 2b: seo_quality_regression (new — Phase 2 benchmark)
      // Fires when the latest SEO optimization attempt regressed the content
      // quality vs the source description (SeoQualityBenchmark verdict =
      // 'regression', or coverage < 60 %, or repetition > 20 %).  Triggers
      // a re-optimize action so the regression is addressed before the
      // catalog drifts.  Higher specificity than seo_low_score so it wins
      // when both apply — a regressed product needs the dedicated retry,
      // not a generic "optimize SEO" action.
      // ────────────────────────────────────────────────────────────────────
      $registry->register(new Rule(
        code:        'seo_quality_regression',
        action:      new Action(
          code:        'reoptimize_seo_quality',
          label:       $this->app->getDef('text_label_seo_regression') ?: 'Re-optimize SEO (quality regression)',
          priority:    ActionPriority::High,
          description: $this->app->getDef('text_seo_quality_regression')
            ?: 'The latest SEO optimization attempt regressed the content quality benchmark — re-run with broader source-attribute coverage.',
          exclusive:   false,
        ),
        condition:   static function (array $ctx): bool {
          $verdict   = $ctx['seo_benchmark_verdict']    ?? null;
          $coverage  = $ctx['seo_benchmark_coverage']   ?? null;
          $repeat    = $ctx['seo_benchmark_repetition'] ?? null;
          return $verdict === 'regression'
            || ($coverage !== null && $coverage < 0.60)
            || ($repeat   !== null && $repeat   > 0.20);
        },
        specificity: 3,
      ));

      // ────────────────────────────────────────────────────────────────────
      // Rule 3: description_poor (Req. 14.3)
      // ────────────────────────────────────────────────────────────────────
      $registry->register(new Rule(
        code:        'description_poor',
        action:      new Action(
          code:        'optimize_description',
          label:       $this->app->getDef('text_label_optimize_description'),
          priority:    ActionPriority::High,
          description: $this->app->getDef('text_seo_not_analyzed', ['descThreshold' => $descThreshold]),
          exclusive:   false,
        ),
        condition:   static function (array $ctx) use ($descThreshold): bool {
          return isset($ctx['score_description'])
            && $ctx['score_description'] < $descThreshold;
        },
        specificity: 1,
      ));

      // ────────────────────────────────────────────────────────────────────
      // Rule 4: special_needed (Req. 14.4)
      // ────────────────────────────────────────────────────────────────────
      $registry->register(new Rule(
        code: 'special_needed',
        action: new Action(
          code: 'create_promotion',
          label:       $this->app->getDef('text_label_create_promotion'),
          priority: ActionPriority::Medium,
          description: $this->app->getDef('text_special_needed'),
          exclusive: false,
        ),
        condition: static function (array $ctx): bool {
          // Déclenche si le produit n'est pas encore un "Success" (Q1)
          return isset($ctx['quadrant']) && in_array($ctx['quadrant'], ['Q2', 'Q3'], true);
        },
        specificity: 2,
      ));

      // ────────────────────────────────────────────────────────────────────
      // Rule 5: feature_needed (Req. 14.5)
      // ────────────────────────────────────────────────────────────────────
      // --- Règle FEATURED (Visibility) ---
      $registry->register(new Rule(
        code: 'featured_needed',
        action: new Action(
          code: 'add_featured_status',
          label:  $this->app->getDef('text_label_add_featured_status'),
          priority: ActionPriority::Medium,
          description: $this->app->getDef('text_featured_needed'),
          exclusive: false,
        ),
        condition: static function (array $ctx): bool {
          // On suggère de vérifier/activer si le score commercial est améliorable
          return isset($ctx['quadrant']) && $ctx['quadrant'] === 'Q2';
        },
        specificity: 1,
      ));

      $registry->register(new Rule(
        code: 'favorites_needed',
        action: new Action(
          code: 'add_favorites_status',
          label:  $this->app->getDef('text_label_add_favorites_status'),
          priority: ActionPriority::Medium,
          description: $this->app->getDef('text_favorites_needed'),
          exclusive: false,
        ),
        condition: static function (array $ctx): bool {
          // On suggère de vérifier/activer si le score commercial est améliorable
          return isset($ctx['quadrant']) && $ctx['quadrant'] === 'Q2';
        },
        specificity: 1,
      ));



      // ────────────────────────────────────────────────────────────────────
      // Rule 6: recommendations_low (Req. 14.6)
      // ────────────────────────────────────────────────────────────────────
      $registry->register(new Rule(
        code: 'cross_sell_needed',
        action: new Action(
          code: 'add_cross_recommendations',
          label:  $this->app->getDef('text_label_add_cross_recommendations'),
          priority: ActionPriority::Low,
          description: $this->app->getDef('text_cross_sell_needed', ['recoMin' => $recoMin]),
          exclusive: false,
        ),
        condition: static function (array $ctx) use ($recoMin): bool {
          return ($ctx['scores']['y'] ?? 0) > 50 && ($ctx['recommendations_count'] ?? 0) < $recoMin;
        },
        specificity: 1,
      ));

      // ────────────────────────────────────────────────────────────────────
      // Rule 7: category_reposition (Req. 14.7)
      // ────────────────────────────────────────────────────────────────────
      $registry->register(new Rule(
        code:        'category_reposition',
        action:      new Action(
          code:        'reposition_category',
          label:  $this->app->getDef('text_label_reposition_category'),
          priority:    ActionPriority::Medium,
          description: $this->app->getDef('text_category_reposition', ['retThreshold' => $retThreshold]),
          exclusive:   false,
        ),
        condition:   static function (array $ctx) use ($retThreshold): bool {
          return isset($ctx['quadrant'], $ctx['return_rate'])
            && $ctx['quadrant'] === 'Q3'
            && $ctx['return_rate'] <= $retThreshold;
        },
        specificity: 2,
      ));

      $registry->register(new Rule(
        code:        'consider_removal',
        action:      new Action(
          code:        'consider_removal',
          label:  $this->app->getDef('text_label_consider_removal'),
          priority:    ActionPriority::Critical,
          description: $this->app->getDef('text_consider_removal', ['retThreshold' => $retThreshold]),
          exclusive:   true,
        ),
        condition:   static function (array $ctx) use ($retThreshold): bool {
          return isset($ctx['quadrant'], $ctx['return_rate'])
            && $ctx['quadrant'] === 'Q3'
            && $ctx['return_rate'] > $retThreshold;
        },
        specificity: 2,
      ));

      // ────────────────────────────────── ──────────────────────────────────
        // Rule 9: hidden_gem — “Hidden Gem” (Phase 3 tracking)
        //
        // Detects a Q4 product (high potential, barely visible) which receives a
        // qualified traffic (high_intent_ratio) but not yet generating
        // sales. Signal: visitors are actively looking for it but it
        // lack of visibility in the catalog.
        //
        // Conditions (all required):
        // quadrant = Q4 → high potential, low performance
        // high_intent_ratio > BD threshold → qualified traffic (search, product sheet)
        // total_impressions_7d > min_volume → sufficient volume (critic point 9)
        // order_count = 0 → no sales yet
        // tracking_valid = true → reliable tracking data
        //
        // Action: ADD_FEATURED_STATUS (High) — boost visibility
        // Specificity: 5 conditions → priority over less precise rules
        // ────────────────────────────────── ──────────────────────────────────
      $registry->register(new Rule(
        code: 'hidden_gem',
        action: new Action(
          code:        'add_featured_status',
          label:  $this->app->getDef('text_label_add_featured_status'),
          priority:    ActionPriority::High,
          description: $this->app->getDef('text_hidden_gem', ['highIntentMin' => $highIntentMin]),
          exclusive:   false,
          metadata:    ['trigger' => 'hidden_gem', 'rule_version' => 'v5'],
        ),
        condition: static function (array $ctx) use ($highIntentMin): bool {
          // Guard 1 : données tracking valides et volume suffisant (critic point 9)
          if (($ctx['tracking_valid'] ?? false) !== true) {
            return false;
          }
          // Guard 2 : volume minimum absolu (20 = seuil ranking, cf plan)
          if (($ctx['total_impressions_7d'] ?? 0) < 20) {
            return false;
          }
          return isset($ctx['quadrant'])
            && $ctx['quadrant'] === 'Q4'
            && isset($ctx['high_intent_ratio'])
            && $ctx['high_intent_ratio'] > $highIntentMin
            && ($ctx['order_count'] ?? 0) === 0;
        },
        specificity: 5,
      ));

      // ────────────────────────────────────────────────────────────────────
      // Rule 10: trending_product — "Tendance Détectée" (Phase 3 tracking)
      //
      // Détecte un produit dont la popularité pondérée dépasse significativement
      // la moyenne catalogue — signal de tendance organique ou virale.
      // Le "fer est chaud" : agir maintenant maximise la conversion.
      //
      // Conditions (toutes requises) :
      //   popularity_heat > avg_catalog_heat * multiplicateur BD  → pic de trafic
      //   total_impressions_7d > min_volume                        → volume fiable
      //   quadrant != Q1                                           → pas déjà en succès
      //   tracking_valid = true                                    → données fiables
      //
      // Action : CREATE_PROMOTION (High) — capitaliser pendant que le trafic est chaud
      // Specificité : 4 conditions
      //
      // Note : ConflictResolver (Phase 7) arbitrera si hidden_gem et trending_product
      // se déclenchent simultanément (trending_product prioritaire car promo > featured).
      // ────────────────────────────────────────────────────────────────────
      $registry->register(new Rule(
        code: 'trending_product',
        action: new Action(
          code:        'create_promotion',
          label:  $this->app->getDef('text_label_create_promotion'),
          priority:    ActionPriority::High,
          description: $this->app->getDef('text_trending_product', ['trendMultiplier' => $trendMultiplier]),
          exclusive:   false,
          metadata:    ['trigger' => 'high_intent_flash', 'rule_version' => 'v5'],
        ),
        condition: static function (array $ctx) use ($trendMultiplier): bool {
          // Guard 1 : données tracking valides
          if (($ctx['tracking_valid'] ?? false) !== true) {
            return false;
          }
          // Guard 2 : volume minimum pour éviter le bruit statistique (critic point 9)
          if (($ctx['total_impressions_7d'] ?? 0) < 20) {
            return false;
          }
          // Guard 3 : avg_catalog_heat doit être disponible et > 0
          $avgHeat = $ctx['avg_catalog_heat'] ?? null;
          if ($avgHeat === null || $avgHeat <= 0) {
            return false;
          }
          $heat = $ctx['popularity_heat'] ?? null;
          if ($heat === null) {
            return false;
          }
          return $heat > ($avgHeat * $trendMultiplier)
            && isset($ctx['quadrant'])
            && $ctx['quadrant'] !== 'Q1';
        },
        specificity: 4,
      ));

      return $registry;
    }

    /**
     * Register a new rule in the registry.
     *
     * @param RuleInterface $rule The rule to register
     * @throws \InvalidArgumentException If a rule with the same code already exists
     */
    public function register(RuleInterface $rule): void
    {
      $code = $rule instanceof Rule ? $rule->getCode() : spl_object_hash($rule);

      if (isset($this->rules[$code])) {
        throw new \InvalidArgumentException(
          "Rule '{$code}' is already registered in RuleRegistry"
        );
      }

      $this->rules[$code] = $rule;
    }

    /**
     * Retrieve a rule by its code.
     *
     * @param string $code Rule identifier
     * @return RuleInterface|null The rule, or null if not found
     */
    public function get(string $code): ?RuleInterface
    {
      return $this->rules[$code] ?? null;
    }

    /**
     * Return all registered rules.
     *
     * @return array<RuleInterface> Array of all rules
     */
    public function all(): array
    {
      return array_values($this->rules);
    }

    /**
     * Return the number of registered rules.
     */
    public function count(): int
    {
      return count($this->rules);
    }

    /**
     * Check if a rule with the given code exists.
     */
    public function has(string $code): bool
    {
      return isset($this->rules[$code]);
    }
  }
