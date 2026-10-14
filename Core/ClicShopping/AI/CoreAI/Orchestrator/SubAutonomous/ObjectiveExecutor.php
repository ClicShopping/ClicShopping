<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use ClicShopping\AI\Config\ObjectiveExecutorConfig;
use ClicShopping\AI\InterfacesAI\ObjectiveGateInterface;
use ClicShopping\AI\InterfacesAI\ObjectiveProposerInterface;

/**
 * ObjectiveExecutor — closes the §Z objective loop (Z3).
 *
 * Agnostic engine: drives ONE pending objective through measure -> propose -> gate ->
 * apply -> re-measure -> judge -> persist, mirroring the closed-loop discipline of
 * CorrectionAgent. It depends only on agnostic contracts (ObjectiveProposerInterface,
 * ObjectiveGateInterface) plus the existing ObjectiveRegistry (persistence/lifecycle)
 * and the semantic ConflictDetector (Z2). It knows nothing of any specific domain —
 * no query, content, or metric semantics.
 *
 * Dormant by construction: execute() is a no-op unless ObjectiveExecutorConfig is ON
 * (default OFF). No live path calls it until the gate flips (post-2B). Never throws.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 2026-06-23
 */
class ObjectiveExecutor
{
  public function __construct(
    private readonly ObjectiveRegistry $registry,
    private readonly ?ConflictDetector $conflictDetector = null,
    private readonly float $gateThreshold = 0.6,
    private readonly bool $debug = false
  ) {
  }

  /**
   * Execute one pending objective. Returns the final status string:
   * 'skipped_disabled' | 'skipped_not_pending' | 'deferred_conflict' | 'completed' | 'failed'.
   */
  public function execute(
    LocalObjective $objective,
    ObjectiveProposerInterface $proposer,
    ObjectiveGateInterface $gate
  ): string {
    // 1. Guard — dormant unless explicitly enabled.
    if (!ObjectiveExecutorConfig::isEnabled()) {
      return 'skipped_disabled';
    }

    $id = $objective->getId();

    try {
      // 2. Claim — only pending objectives are executable.
      if ($objective->getStatus() !== 'pending') {
        return 'skipped_not_pending';
      }
      $this->registry->updateObjectiveStatus($id, 'active');

      // 3. Conflict check (Z2 hook) — defer if it clashes with an in-flight objective.
      // Deferral is NOT failure: reset to 'pending' so the objective stays re-executable
      // once the conflicting objective clears (markFailed would permanently close it).
      if ($this->conflictDetector !== null) {
        $conflicts = $this->conflictDetector->detectConflicts($objective);
        if (!empty($conflicts)) {
          $this->registry->updateObjectiveStatus($id, 'pending', 'deferred_conflict');
          return 'deferred_conflict';
        }
      }

      // 4. Measure before.
      $baseline = $proposer->measureBaseline($objective);
      $this->recordScalarMetrics($id, 'baseline', $baseline);

      // 5. Propose.
      $proposal = $proposer->propose($objective, $baseline);
      if ($proposal === null) {
        $this->registry->markFailed($id, 'no_proposal');
        return 'failed';
      }

      // 6. Gate (critic) — no apply without a passing score.
      $score = $gate->evaluate($proposal);
      $this->registry->recordMetric($id, 'critic_score', $score);
      if ($score < $this->gateThreshold) {
        $this->registry->markFailed($id, 'critic_rejected');
        return 'failed';
      }

      // 7. Apply (advisory proposers no-op here).
      $proposer->apply($proposal);

      // 8. Measure after.
      $result = $proposer->measureResult($objective);
      $this->recordScalarMetrics($id, 'result', $result);

      // 9. Judge on the measured before/after delta vs the objective's criteria.
      $success = $proposer->evaluateSuccess($baseline, $result, $objective->getSuccessCriteria());
      if ($success) {
        $this->registry->markCompleted($id, [
          'baseline' => $baseline,
          'result' => $result,
          'proposal' => $proposal->toArray(),
          'critic_score' => $score,
        ]);
        return 'completed';
      }

      $this->registry->markFailed($id, 'criteria_not_met');
      return 'failed';
    } catch (\Throwable $e) {
      // Never leave an objective stuck 'active'; never throw to callers.
      try {
        $this->registry->markFailed($id, 'error: ' . $e->getMessage());
      } catch (\Throwable $inner) {
        if ($this->debug) {
          error_log('ObjectiveExecutor: markFailed also failed - ' . $inner->getMessage());
        }
      }
      if ($this->debug) {
        error_log('ObjectiveExecutor: execution error - ' . $e->getMessage());
      }
      return 'failed';
    }
  }

  /**
   * Record the numeric entries of a measurement bag as scalar metrics.
   * recordMetric() only accepts floats; non-numeric values are skipped here and
   * survive in full inside the markCompleted() metrics JSON.
   */
  private function recordScalarMetrics(string $objectiveId, string $prefix, array $metrics): void
  {
    foreach ($metrics as $name => $value) {
      if (\is_int($value) || \is_float($value)) {
        $this->registry->recordMetric($objectiveId, $prefix . '_' . $name, (float)$value);
      }
    }
  }
}
