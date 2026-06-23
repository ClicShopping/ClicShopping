<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\ObjectiveOptim;

use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCriticFactory;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\ObjectiveProposal;
use ClicShopping\AI\InterfacesAI\ObjectiveGateInterface;
use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\RegistryAI\CriticRegistry;

/**
 * AnalyticsObjectiveGate — domain gate (§Z Z3) routing an objective proposal through the
 * agnostic ActorCriticCoordinator (patron ChatCriticSeam). Returns the consensus score so
 * the agnostic ObjectiveExecutor can threshold it. Never throws — returns 0.0 on any error
 * so the engine fails closed (no apply).
 */
class AnalyticsObjectiveGate implements ObjectiveGateInterface
{
  public const ACTION_TYPE = 'objective_proposal_eval';

  public function __construct(private readonly bool $debug = false)
  {
  }

  public function evaluate(ObjectiveProposal $proposal): float
  {
    try {
      $coordinator = ActorCriticFactory::create(
        [fn(ActorRegistry $r) => new ObjectiveProposalActor($this->debug, $r)],
        [
          fn(CriticRegistry $r) => new ObjectiveProposalCritic('soundness', $this->debug, $r),
          fn(CriticRegistry $r) => new ObjectiveProposalCritic('risk', $this->debug, $r),
        ]
      );

      // Objective optimisation is system-initiated: a system context, default language.
      $ctx = new Context('system', 1);
      $action = new Action(
        self::ACTION_TYPE,
        ['description' => $proposal->getDescription(), 'proposal' => $proposal->getPayload()],
        $ctx,
        'medium',
        30
      );

      return $coordinator->coordinateExecution($action)->getConsensusScore();
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('AnalyticsObjectiveGate: coordinator failed, fail-closed 0.0 - ' . $e->getMessage());
      }
      return 0.0;
    }
  }
}
