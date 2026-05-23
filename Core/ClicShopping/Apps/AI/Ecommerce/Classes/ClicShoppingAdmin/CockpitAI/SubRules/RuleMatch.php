<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubRules;

  /**
   * RuleMatch
   *
   * Immutable value object produced when a Rule evaluates to true.
   * Bundles the fired Rule with the Action it produces, giving ConflictResolver
   * access to both rule metadata (priority, specificity, exclusivity, code) and
   * the resulting Action in a single typed structure.
   *
   * Created by RecommendationEngine after evaluating all registered rules:
   *
   *   foreach ($registry->all() as $rule) {
   *     if ($rule->evaluate($context)) {
   *       $matches[] = new RuleMatch($rule, $rule->getAction());
   *     }
   *   }
   *   $actions = $conflictResolver->resolve($matches);
   */
  readonly class RuleMatch
  {
    public function __construct(
      public RuleInterface $rule,
      public Action        $action,
    ) {
    }
  }