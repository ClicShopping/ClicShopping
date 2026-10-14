<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubRules;

  /**
   * Rule
   *
   * Concrete implementation of RuleInterface.
   * Encapsulates a named rule with a callable condition and its associated Action.
   * (Requirements 13.1, 13.2, 13.5, 13.6)
   *
   * The condition is a pure PHP callable (closure or invokable) that receives
   * the RuleContext array and returns bool.  This keeps rules stateless,
   * easily testable in isolation, and registerable at runtime without subclassing.
   *
   * Usage — registering the standard 'consider_removal' rule:
   * ──────────────────────────────────────────────────────────
   * $action = new Action(
   *   code:        'consider_removal',
   *   label:       'Consider Removal',
   *   priority:    ActionPriority::Critical,
   *   description: 'Product in Q3 with high return rate. Consider removing from catalog.',
   *   exclusive:   true,
   * );
   *
   * $rule = new Rule(
   *   code:        'consider_removal',
   *   action:      $action,
   *   condition:   static function (array $ctx): bool {
   *     return $ctx['quadrant'] === 'Q3'
   *       && isset($ctx['return_rate'])
   *       && $ctx['return_rate'] > ($ctx['return_threshold'] ?? 0.1);
   *   },
   *   specificity: 2,   // 2 conditions: quadrant=Q3 AND returns>threshold
   * );
   * ──────────────────────────────────────────────────────────
   *
   * RuleContext keys expected by standard rules (§ 6.3 / Req. 14):
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
   */
  class Rule implements RuleInterface
  {
    /**
     * @param string         $code        Unique rule identifier (e.g. 'seo_not_analyzed')
     * @param Action         $action      The Action to produce when this rule fires
     * @param callable       $condition   Pure function (array $context): bool
     * @param int            $specificity Number of distinct conditions (>= 1, default 1)
     */
    public function __construct(
      private readonly string   $code,
      private readonly Action   $action,
      private readonly mixed    $condition,   // callable — mixed avoids Closure-only restriction
      private readonly int      $specificity = 1,
    ) {
      if ($specificity < 1) {
        throw new \InvalidArgumentException(
          "Rule '{$code}': specificity must be >= 1, got {$specificity}"
        );
      }
    }

    /**
     * Return the unique rule code identifier.
     */
    public function getCode(): string
    {
      return $this->code;
    }

    /**
     * Evaluate the rule condition against the RuleContext.
     *
     * The callable receives the full context array. Any exception thrown
     * by the condition is caught and treated as false (rule does not fire),
     * ensuring a single bad rule cannot crash the entire rules engine.
     *
     * @param array $context RuleContext — product state snapshot
     * @return bool True if the condition is satisfied
     */
    public function evaluate(array $context): bool
    {
      try {
        return (bool) ($this->condition)($context);
      } catch (\Throwable) {
        return false;
      }
    }

    /**
     * {@inheritdoc}
     */
    public function getActionCode(): string
    {
      return $this->action->code;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority(): ActionPriority
    {
      return $this->action->priority;
    }

    /**
     * {@inheritdoc}
     */
    public function isExclusive(): bool
    {
      return $this->action->exclusive;
    }

    /**
     * {@inheritdoc}
     */
    public function getSpecificity(): int
    {
      return $this->specificity;
    }

    /**
     * Return the fully-built Action instance associated with this rule.
     * Used by RecommendationEngine to collect the Action after evaluation.
     */
    public function getAction(): Action
    {
      return $this->action;
    }
  }