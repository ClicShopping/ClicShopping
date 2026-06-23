<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\ObjectiveProposal;

/**
 * ObjectiveGateInterface — the critic gate seam for the §Z Z3 objective loop.
 *
 * The domain implementation wires the shared agnostic actor-critic coordinator and
 * returns the consensus score [0.0, 1.0]. The agnostic ObjectiveExecutor only compares
 * that score against its threshold — it never constructs domain actors/critics.
 *
 * @package ClicShopping\AI\InterfacesAI
 * @since 2026-06-23
 */
interface ObjectiveGateInterface
{
  /**
   * Score the proposal's soundness in [0.0, 1.0] (consensus from the coordinator).
   *
   * @param ObjectiveProposal $proposal
   * @return float
   */
  public function evaluate(ObjectiveProposal $proposal): float;
}
