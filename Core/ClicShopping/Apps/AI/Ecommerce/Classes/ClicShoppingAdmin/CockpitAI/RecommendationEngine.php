<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubRules\{
    Action,
    ConflictResolver,
    RuleInterface,
    RuleMatch,
    RuleRegistry
  };

  /**
   * RecommendationEngine
   *
   * Rules-based action generation engine for the CockpitAI module.
   * (Requirements 13.1, 13.2, 13.3, 13.4, 13.5, 13.6)
   *
   * Responsibilities:
   *  1. Evaluate all registered rules against the current product state
   *  2. Collect triggered actions
   *  3. Resolve conflicts using ConflictResolver (priority, specificity, exclusivity)
   *  4. Return an ordered, conflict-free action list for the Analysis_Report
   *
   * ── Architecture ────────────────────────────────────────────────────────────
   *
   * The engine operates in three phases:
   *
   * Phase 1 — Context Assembly
   *   Build a RuleContext array from the product state, scores, and quadrant.
   *   This context is passed to every rule's evaluate() method.
   *
   * Phase 2 — Rule Evaluation
   *   Iterate over all registered rules (from RuleRegistry).
   *   For each rule, call evaluate($context).
   *   If true, create a RuleMatch bundling the rule and its action.
   *
   * Phase 3 — Conflict Resolution
   *   Pass all RuleMatch objects to ConflictResolver.
   *   Apply the formal resolution strategy:
   *     1. Exclusive actions cancel all others
   *     2. Sort by priority (Critical > High > Medium > Low)
   *     3. Tiebreak by specificity (more conditions = more specific = wins)
   *     4. Final tiebreak by rule code (lexicographic)
   *
   * ── RuleContext Structure ──────────────────────────────────────────────────
   *
   * The context array passed to rules contains:
   *
   *   'quadrant'          : string   — Q1|Q2|Q3|Q4|Q_intermediate
   *   'score_x'           : float    — [0..100]
   *   'score_y'           : float    — [0..100]
   *   'seo_status'        : string   — NOT_ANALYZED|ANALYZED
   *   'seo_score'         : float|null
   *   'score_description' : float    — normalized [0..1] from factors_x['description']
   *   'promo_active'      : bool
   *   'feature'           : bool
   *   'recommendations'   : int      — number of active cross-recommendations
   *   'return_rate'       : float    — [0..1]
   *   'return_threshold'  : float    — configurable, default 0.1
   *
   * ── Usage Example ───────────────────────────────────────────────────────────
   *
   *   $registry = new RuleRegistry();
   *   $registry->registerStandardRules();
   *
   *   $engine = new RecommendationEngine($registry);
   *
   *   $actions = $engine->generateActions(
   *     scoreX:   $scoreXResult,
   *     scoreY:   $scoreYResult,
   *     quadrant: $quadrant,
   *     product:  $productData,
   *     config:   $config
   *   );
   *
   *   // $actions is an ordered array of Action objects ready for JSON serialization
   *
   * ── Error Handling ──────────────────────────────────────────────────────────
   *
   * If a rule's evaluate() throws an exception, the Rule class catches it and
   * returns false (rule does not fire).  This ensures a single bad rule cannot
   * crash the entire engine.
   *
   * If RuleRegistry is empty, generateActions() returns an empty array (no crash).
   *
   * ── Testing Strategy ────────────────────────────────────────────────────────
   *
   * Unit tests should verify:
   *  - Each standard rule fires correctly for its trigger condition
   *  - Exclusive actions (e.g. 'consider_removal') cancel all others
   *  - Priority ordering is respected (Critical > High > Medium > Low)
   *  - Specificity tiebreaker works when priorities are equal
   *  - Empty registry returns empty action list
   *  - Invalid context keys do not crash the engine
   */
  class RecommendationEngine
  {
    private const PRIORITY_MAP = [
      'CRITICAL' => 4,
      'HIGH'     => 3,
      'MEDIUM'   => 2,
      'LOW'      => 1
    ];
    private ConflictResolver $conflictResolver;

    /**
     * @param RuleRegistry $registry Registry containing all registered rules
     */
    public function __construct(
      private readonly RuleRegistry $registry
    ) {
      $this->conflictResolver = new ConflictResolver();
    }

    /**
     * Generate prioritized action recommendations for the current product state.
     *
     * This is the main entry point for the rules engine, called by the
     * CockpitAIOrchestrator in Step 7 of the analysis pipeline.
     *
     * @param array $scoreXResult  ScoreResult for Score_X (product quality)
     * @param array $scoreYResult  ScoreResult for Score_Y (commercial performance)
     * @param string $quadrant     Quadrant code (Q1, Q2, Q3, Q4, Q_intermediate)
     * @param array $productData   Product data array with commercial metrics
     * @param array $config        Configuration array with thresholds and preferences
     * @return Action[]            Ordered, conflict-free action list
     */
    public function generateActions(
      array  $scoreXResult,
      array  $scoreYResult,
      string $quadrant,
      array  $productData,
      array  $config
    ): array {
      // Phase 1: Assemble RuleContext
      $context = $this->buildContext(
        $scoreXResult,
        $scoreYResult,
        $quadrant,
        $productData,
        $config
      );

      // Phase 2: Evaluate all rules
      $matches = $this->evaluateRules($context);

      // Phase 3: Resolve conflicts (Phase 7 : passe le contexte pour arbitrage tracking)
      return $this->conflictResolver->resolve($matches, $context);
    }

    /**
     * Build the RuleContext array from product state and scoring results.
     *
     * The context contains all data points needed by standard rules (Req. 14).
     *
     * @param array $scoreXResult  ScoreResult for Score_X
     * @param array $scoreYResult  ScoreResult for Score_Y
     * @param string $quadrant     Quadrant code
     * @param array $productData   Product data with commercial metrics
     * @param array $config        Configuration with thresholds
     * @return array               RuleContext array
     */
    private function buildContext(
      array  $scoreXResult,
      array  $scoreYResult,
      string $quadrant,
      array  $productData,
      array  $config
    ): array {
      // Extract score_description from Score_X factors.
      // ScoringEngine::serializeFactors() stores each factor under its code key with
      // a 'normalized' field (not 'normalized_value'). The factors array is stored
      // directly in $scoreXResult['factors'] (already the factors_x sub-array, since
      // the Orchestrator builds scoreXResult = ['score' => ..., 'factors' => factors_x]).
      $scoreDescription = 0.0;
      if (isset($scoreXResult['factors']['description'])) {
        $factor = $scoreXResult['factors']['description'];
        if (is_array($factor)) {
          // Prefer 'normalized'; fall back to 'normalized_value' for forward-compat
          $scoreDescription = (float) ($factor['normalized'] ?? $factor['normalized_value'] ?? 0.0);
        }
      }

      $finalQuadrant = in_array($quadrant, ['Q1', 'Q2', 'Q3', 'Q4']) ? $quadrant : 'Q_intermediate';

      return [
        'quadrant'          => $finalQuadrant,
        'score_x'           => (float) ($scoreXResult['score'] ?? 0.0),
        'score_y'           => (float) ($scoreYResult['score'] ?? 0.0),

        // SEO status
        'seo_status'        => (string) ($config['seo_status'] ?? 'NOT_ANALYZED'),
        'seo_score'         => isset($config['seo_score']) ? (float) $config['seo_score'] : null,

        // SEO Quality Benchmark — exposed so RuleRegistry::seo_quality_regression
        // (and any future rule) can branch on the latest content-quality verdict.
        'seo_benchmark_score'      => isset($productData['seo_benchmark_score'])      ? (float)$productData['seo_benchmark_score'] : null,
        'seo_benchmark_verdict'    => $productData['seo_benchmark_verdict']           ?? null,
        'seo_benchmark_reason'     => $productData['seo_benchmark_reason']            ?? null,
        'seo_benchmark_coverage'   => isset($productData['seo_benchmark_coverage'])   ? (float)$productData['seo_benchmark_coverage']   : null,
        'seo_benchmark_diversity'  => isset($productData['seo_benchmark_diversity'])  ? (float)$productData['seo_benchmark_diversity']  : null,
        'seo_benchmark_repetition' => isset($productData['seo_benchmark_repetition']) ? (float)$productData['seo_benchmark_repetition'] : null,

        // Factor details
        'score_description' => $scoreDescription,

        // Commercial metrics
        'promo_active'      => (bool) ($productData['promo_active'] ?? false),
        'feature'           => (bool) ($productData['feature'] ?? false),
        'favorite'          => (bool) ($productData['favorites'] ?? false),
        'recommendations'   => (int) ($productData['recommendations'] ?? 0),
        'return_rate'       => (float) ($productData['return_rate'] ?? 0.0),
        'order_count'       => (int) ($productData['order_count'] ?? 0),

        // Configuration thresholds
        'return_threshold'  => (float) ($config['return_threshold'] ?? 0.1),

        // ── Tracking metrics (Phase 3) ────────────────────────────────────
        // Injectés depuis DataCollector::collectTrackingMetrics()
        // tracking_valid = false → les règles tracking font un early return false
        'tracking_valid'        => (bool) ($productData['tracking_valid'] ?? false),
        'popularity_heat'       => $productData['popularity_heat'] ?? null,
        'avg_catalog_heat'      => $productData['avg_catalog_heat'] ?? null,
        'total_impressions_7d'  => (int) ($productData['total_impressions_7d'] ?? 0),
        'high_intent_ratio'     => $productData['high_intent_ratio'] ?? null,
        'module_spread'         => (int) ($productData['module_spread'] ?? 0),
      ];
    }

    /**
     * Evaluate all registered rules against the RuleContext.
     *
     * For each rule that fires (evaluate() returns true), create a RuleMatch
     * bundling the rule and its action.
     *
     * @param array $context  RuleContext array
     * @return RuleMatch[]    Array of fired rules with their actions
     */
    public function evaluateRules(array $context): array
    {
      $matches = [];

      foreach ($this->registry->all() as $rule) {
        if ($rule->evaluate($context)) {
          $matches[] = new RuleMatch($rule, $rule->getAction());
        }
      }

      return $matches;
    }

    /**
     * Get the ConflictResolver instance (for testing).
     *
     * @return ConflictResolver
     */
    public function getConflictResolver(): ConflictResolver
    {
      return $this->conflictResolver;
    }

    /**
     * Get the RuleRegistry instance (for testing).
     *
     * @return RuleRegistry
     */
    public function getRegistry(): RuleRegistry
    {
      return $this->registry;
    }
  }
