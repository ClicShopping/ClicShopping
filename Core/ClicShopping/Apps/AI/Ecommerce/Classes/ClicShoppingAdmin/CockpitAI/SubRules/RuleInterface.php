<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubRules;

  /**
   * RuleInterface
   *
   * Formal contract for all rules in the CockpitAI rules engine.
   * (Requirements 13.1, 13.2, 13.3, 13.4, 13.5, 13.6)
   *
   * A Rule encapsulates:
   *  - A condition (evaluable against a RuleContext)
   *  - A resulting Action (code, priority, exclusivity)
   *  - A specificity count used as tiebreaker during conflict resolution
   *
   * Conflict resolution order (ConflictResolver):
   *  1. Exclusive actions cancel all others
   *  2. Priority order: Critical > High > Medium > Low
   *  3. Specificity tiebreaker: more conditions = more specific = wins
   *  4. Lexicographic order on rule code as final tiebreaker
   *
   * RuleContext array keys used by standard rules (§ 6.3 strategy doc):
   *  - 'quadrant'         string   Q1|Q2|Q3|Q4|Q_intermediate
   *  - 'score_x'          float    [0..100]
   *  - 'score_y'          float    [0..100]
   *  - 'seo_status'       string   NOT_ANALYZED|ANALYZED
   *  - 'seo_score'        float|null
   *  - 'score_description' float   normalized description factor [0..1]
   *  - 'promo_active'     bool
   *  - 'feature'          bool
   *  - 'recommendations'  int      number of active cross-recommendations
   *  - 'return_rate'      float    [0..1]
   *  - 'return_threshold' float    configurable threshold (default 0.1)
   */
  interface RuleInterface
  {
    /**
     * Evaluate this rule against the current product state.
     *
     * @param array $context RuleContext — product state snapshot (see above)
     * @return bool True if the rule condition is satisfied and its action should fire
     */
    public function evaluate(array $context): bool;

    /**
     * Return the action code that this rule produces when it fires.
     *
     * The code must match an Action::$code registered in the rules catalog.
     * Example: 'seo_optimization', 'consider_removal', 'create_promotion'
     *
     * @return string Action code
     */
    public function getActionCode(): string;

    /**
     * Return the priority level of this rule's action.
     *
     * Used by ConflictResolver to order actions when multiple rules fire.
     *
     * @return ActionPriority Priority enum value
     */
    public function getPriority(): ActionPriority;

    /**
     * Return true if this rule produces an exclusive action.
     *
     * An exclusive action, when triggered, cancels all other actions.
     * Only one exclusive action can appear in the final action plan.
     * Example: 'consider_removal' is exclusive — it excludes 'create_promotion'.
     *
     * @return bool Exclusive flag
     */
    public function isExclusive(): bool;

    /**
     * Return the specificity count of this rule.
     *
     * Specificity = number of distinct conditions checked by the rule condition.
     * Used as tiebreaker when two rules share the same priority level.
     * Higher specificity wins (more precise rule takes precedence).
     *
     * Examples:
     *  - 'seo_not_analyzed'  → 1 condition  → specificity 1
     *  - 'promo_needed'      → 2 conditions → specificity 2  (quadrant IN (Q2,Q3) AND promo_active=false)
     *  - 'consider_removal'  → 2 conditions → specificity 2  (quadrant=Q3 AND returns>threshold)
     *
     * @return int Specificity count (>= 1)
     */
    public function getSpecificity(): int;
  }