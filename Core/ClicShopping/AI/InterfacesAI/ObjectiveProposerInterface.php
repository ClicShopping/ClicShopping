<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\ObjectiveProposal;

/**
 * ObjectiveProposerInterface — the domain-supplied half of the §Z Z3 objective loop.
 *
 * The agnostic ObjectiveExecutor measures, gates, and persists; the domain owns what a
 * measurement MEANS and what a remediation IS. measureBaseline()/measureResult() are the
 * "measure before"/"measure after" of the closed loop.
 *
 * @package ClicShopping\AI\InterfacesAI
 * @since 2026-06-23
 */
interface ObjectiveProposerInterface
{
  /**
   * Measure the metric(s) before any remediation.
   *
   * @param LocalObjective $objective
   * @return array<string, float|int> Metrics map, e.g. ['execution_time_ms' => 2400.0].
   */
  public function measureBaseline(LocalObjective $objective): array;

  /**
   * Produce a remediation, or null to decline (no worthwhile action).
   *
   * @param LocalObjective $objective
   * @param array<string, float|int> $baseline The measureBaseline() result.
   * @return ObjectiveProposal|null
   */
  public function propose(LocalObjective $objective, array $baseline): ?ObjectiveProposal;

  /**
   * Apply the (gated-approved) proposal. May be a no-op for advisory proposers.
   */
  public function apply(ObjectiveProposal $proposal): void;

  /**
   * Measure the metric(s) after apply.
   *
   * @param LocalObjective $objective
   * @return array<string, float|int> Metrics map, same shape as measureBaseline().
   */
  public function measureResult(LocalObjective $objective): array;

  /**
   * Decide success from the measured before/after vs the objective's success criteria.
   *
   * @param array<string, float|int> $baseline
   * @param array<string, float|int> $result
   * @param array<string, mixed> $successCriteria Domain-defined; e.g. analytics uses
   *        ['query' => <sql>, 'max_execution_time_ms' => 1000].
   * @return bool
   */
  public function evaluateSuccess(array $baseline, array $result, array $successCriteria): bool;
}
