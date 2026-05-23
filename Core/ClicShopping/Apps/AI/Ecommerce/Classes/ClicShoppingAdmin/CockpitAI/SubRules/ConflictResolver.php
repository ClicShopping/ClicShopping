<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubRules;

  /**
   * ConflictResolver
   *
   * Formal conflict resolution strategy for the CockpitAI rules engine.
   * (Requirements 13.3, 13.4)
   *
   * When multiple rules fire simultaneously their actions may be incompatible.
   * This class applies a deterministic four-pass resolution to produce an
   * ordered, conflict-free action list.
   *
   * ── Resolution algorithm (§ 6.2 strategy doc) ──────────────────────────────
   *
   * Pass 1 — Exclusive actions
   *   If any triggered rule is marked exclusive (e.g. 'consider_removal'),
   *   the highest-priority exclusive action wins and ALL other actions are
   *   discarded.  Only one exclusive action is ever returned.
   *   Rationale: an exclusive action signals a terminal situation (e.g. product
   *   should be removed); showing other recommendations alongside it would be
   *   misleading.
   *
   * Pass 2 — Priority sort
   *   Non-exclusive actions are sorted by ActionPriority descending:
   *   Critical (4) > High (3) > Medium (2) > Low (1).
   *
   * Pass 3 — Specificity tiebreaker
   *   When two actions share the same priority, the rule with the higher
   *   specificity count (= more conditions) takes precedence.
   *   Rationale: a more specific rule is a better fit for the current state.
   *
   * Pass 4 — Lexicographic tiebreaker
   *   If priority AND specificity are equal, actions are sorted by rule code
   *   ascending (a < z).  This guarantees deterministic output regardless of
   *   rule registration order.
   *
   * ── Input contract ──────────────────────────────────────────────────────────
   *
   * resolve() accepts an array of RuleMatch value-objects, each bundling the
   * fired Rule with its produced Action.  The caller (RecommendationEngine) is
   * responsible for evaluating all registered rules and collecting only those
   * that returned true before calling resolve().
   *
   * resolve([]) returns [] — empty input, empty output (no crash).
   *
   * ── Output contract ─────────────────────────────────────────────────────────
   *
   * Returns an ordered array of Action objects ready for the Analysis_Report
   * action_plan section.  The array is never null; it may be empty.
   *
   * ── Usage example ───────────────────────────────────────────────────────────
   *
   *   $matches = [];
   *   foreach ($this->registry->all() as $rule) {
   *     if ($rule->evaluate($context)) {
   *       $matches[] = new RuleMatch($rule, $rule->getAction());
   *     }
   *   }
   *   $actions = $this->conflictResolver->resolve($matches);
   */
  class ConflictResolver
  {
    /**
     * Resolve conflicts between triggered rules and return an ordered action list.
     *
     * Phase 7 — Arbitrage tracking :
     * Si ADD_FAVORITES et CREATE_PROMOTION sont tous deux déclenchés,
     * le module_spread du tracking tranche :
     *   module_spread >= 3 → produit déjà très visible → CREATE_PROMOTION prime
     *   module_spread < 3  → produit peu visible → ADD_FAVORITES prime
     *
     * @param RuleMatch[] $matches  Rules that fired (evaluate() returned true)
     * @param array       $context  RuleContext (optionnel — pour arbitrage tracking)
     * @return Action[]             Ordered, conflict-free action list
     */
    public function resolve(array $matches, array $context = []): array
    {
      if (empty($matches)) {
        return [];
      }

      // PASS 0 — Critical removal (force exclusion)
      foreach ($matches as $match) {
        if ($match->action->priority->value === 4 && $match->action->code === 'PRODUCT_REMOVAL') {
          return [$match->action];
        }
      }

      // ── Pass 1: Generic Exclusive actions ─────────────────────────────────
      $exclusiveMatches = array_values(array_filter(
        $matches,
        static fn(RuleMatch $m): bool => $m->rule->isExclusive()
      ));

      if (!empty($exclusiveMatches)) {
        usort($exclusiveMatches, $this->matchComparator(...));
        return [$exclusiveMatches[0]->action];
      }

      // ── Phase 7 : Arbitrage tracking ADD_FAVORITES vs CREATE_PROMOTION ────
      // Appliqué avant le tri général pour éviter d'afficher les deux actions
      // contradictoires (l'une optimise la visibilité, l'autre le prix).
      $matches = $this->resolveTrackingConflict($matches, $context);

      // ── Pass 2-4: Sort non-exclusive actions ───────────────────────────────
      usort($matches, $this->matchComparator(...));

      return array_map(static fn(RuleMatch $m): Action => $m->action, $matches);
    }

    /**
     * Arbitrage tracking : si ADD_FAVORITES et CREATE_PROMOTION coexistent,
     * supprimer l'action la moins pertinente selon module_spread.
     *
     * Logique (plan Phase 7) :
     *   module_spread >= 3 → produit vu partout → ADD_FAVORITES inutile,
     *                        le produit a déjà de la visibilité → garder PROMOTION
     *   module_spread < 3  → produit peu visible → commencer par ADD_FAVORITES
     *                        avant de baisser le prix → supprimer PROMOTION
     *   tracking_valid = false → pas de données → laisser les deux (tri standard)
     *
     * @param RuleMatch[] $matches
     * @param array       $context
     * @return RuleMatch[]
     */
    private function resolveTrackingConflict(array $matches, array $context): array
    {
      // Tracking non disponible → pas d'arbitrage
      if (($context['tracking_valid'] ?? false) !== true) {
        return $matches;
      }

      // Identifier les deux actions en conflit
      $hasFavorites = false;
      $hasPromotion = false;

      foreach ($matches as $match) {
        if ($match->action->code === 'add_favorites_status') $hasFavorites = true;
        if ($match->action->code === 'create_promotion')     $hasPromotion = true;
      }

      // Pas de conflit → rien à faire
      if (!$hasFavorites || !$hasPromotion) {
        return $matches;
      }

      $moduleSpread = (int)($context['module_spread'] ?? 0);

      if ($moduleSpread >= 3) {
        // Produit déjà bien distribué → ADD_FAVORITES n'apporte pas de valeur
        // Supprimer ADD_FAVORITES, garder CREATE_PROMOTION
        $matches = array_values(array_filter(
          $matches,
          static fn(RuleMatch $m): bool => $m->action->code !== 'add_favorites_status'
        ));

      } else {
        // Produit peu visible → commencer par la visibilité avant le prix
        // Supprimer CREATE_PROMOTION, garder ADD_FAVORITES
        $matches = array_values(array_filter(
          $matches,
          static fn(RuleMatch $m): bool => $m->action->code !== 'create_promotion'
        ));
      }

      return $matches;
    }

    /**
     * Three-key comparator for RuleMatch objects.
     *
     * Returns negative when $a should come before $b (higher priority first).
     *
     * Key 1 — Priority       : higher ActionPriority value first
     * Key 2 — Specificity    : higher specificity first (more specific rule wins)
     * Key 3 — Rule code      : ascending lexicographic (deterministic tiebreaker)
     */
    private function matchComparator(RuleMatch $a, RuleMatch $b): int
    {
      // Key 1: priority descending (higher int = higher priority)
      $priorityCmp = $this->comparePriority($a->action->priority, $b->action->priority);

      if ($priorityCmp !== 0) {
        return $priorityCmp;
      }

      // Key 2: specificity descending
      $specificityCmp = $b->rule->getSpecificity() <=> $a->rule->getSpecificity();

      if ($specificityCmp !== 0) {
        return $specificityCmp;
      }

      // Key 3: rule code ascending (lexicographic)
      return $a->rule->getCode() <=> $b->rule->getCode();
    }

    /**
     * Compare two ActionPriority values for descending sort.
     *
     * Returns:
     *  -1  when $a has higher priority than $b  (a sorts before b)
     *   0  when $a and $b have equal priority
     *  +1  when $a has lower priority than $b   (b sorts before a)
     *
     * Priority order: Critical(4) > High(3) > Medium(2) > Low(1)
     *
     * @param ActionPriority $a
     * @param ActionPriority $b
     * @return int  -1 | 0 | 1
     */
    public function comparePriority(ActionPriority $a, ActionPriority $b): int
    {
      //  Critical(4) > High(3) > Medium(2) > Low(1)
      return $b->value <=> $a->value;
    }
  }