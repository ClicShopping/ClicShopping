<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\ObjectiveOptim;

use ClicShopping\AI\InterfacesAI\ActorAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCapability;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\AI\RegistryAI\ActorRegistry;

/**
 * ObjectiveProposalActor — non-regenerating echo actor for the §Z Z3 objective gate.
 * Replays the proposal payload carried in the Action; never generates or learns, so
 * the coordinator's regeneration step (if globally ON for SEO) re-executes to the same
 * output and the objective gate stays a pure scoring pass.
 */
class ObjectiveProposalActor implements ActorAgentInterface
{
  public const ACTION_TYPE = 'objective_proposal_eval';
  public const OUTPUT_TYPE = 'objective_proposal';

  private string $actorId;
  private bool $debug;

  public function __construct(bool $debug = false, ?ActorRegistry $registry = null)
  {
    $this->actorId = 'objective_proposal_actor_' . uniqid();
    $this->debug = $debug;
    if ($registry !== null) {
      $registry->registerActor($this);
    }
  }

  public function executeAction(Action $action): ActionResult
  {
    $params = $action->getParameters();
    $output = [
      'description' => (string)($params['description'] ?? ''),
      'proposal' => \is_array($params['proposal'] ?? null) ? $params['proposal'] : [],
    ];

    return new ActionResult(
      $action->getActionId(),
      $this->actorId,
      $output,
      self::OUTPUT_TYPE,
      ['actor' => 'objective_proposal_replay', 'regenerating' => false],
      $action->getContext(),
      'success'
    );
  }

  public function proposeAction(Context $context): Action
  {
    return new Action(self::ACTION_TYPE, [], $context, 'medium', 30);
  }

  public function getCapabilities(): array
  {
    return [
      self::ACTION_TYPE => new ActorCapability(
        self::ACTION_TYPE,
        0.9,
        'objective',
        'expert',
        ['description', 'proposal']
      ),
    ];
  }

  public function evaluateConfidence(Action $action): float
  {
    return 0.9;
  }

  public function receiveFeedback(Feedback $feedback): void
  {
    if ($this->debug) {
      error_log('ObjectiveProposalActor: received feedback (ignored, non-learning)');
    }
  }

  public function getActorId(): string
  {
    return $this->actorId;
  }
}
