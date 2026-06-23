<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\ChatCritic;

use ClicShopping\AI\InterfacesAI\ActorAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCapability;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\AI\RegistryAI\ActorRegistry;

/**
 * ChatReplayActor
 *
 * Non-regenerating echo actor for the chat critic seam (§Z Z1). It does NOT
 * generate anything: it replays the already-produced chat answer and the
 * already-computed guardrails evaluation carried in the Action parameters.
 *
 * Because executeAction() is a pure function of the action parameters, the
 * coordinator's quality-gate regeneration (Step 5c) — which may be ON globally
 * for SEO — re-executes this actor to the IDENTICAL output, so the never-worse
 * comparison keeps the original and the chat path stays observe-only.
 */
class ChatReplayActor implements ActorAgentInterface
{
  public const ACTION_TYPE = 'chat_response_eval';
  public const OUTPUT_TYPE = 'chat_response';

  private string $actorId;
  private bool $debug;

  public function __construct(bool $debug = false, ?ActorRegistry $registry = null)
  {
    $this->actorId = 'chat_replay_actor_' . uniqid();
    $this->debug = $debug;

    if ($registry !== null) {
      $registry->registerActor($this);
    }
  }

  public function executeAction(Action $action): ActionResult
  {
    $params = $action->getParameters();
    $output = [
      'answer'     => (string)($params['answer'] ?? ''),
      'evaluation' => \is_array($params['evaluation'] ?? null) ? $params['evaluation'] : [],
    ];

    return new ActionResult(
      $action->getActionId(),
      $this->actorId,
      $output,
      self::OUTPUT_TYPE,
      ['actor' => 'chat_replay', 'regenerating' => false],
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
        'chat',
        'expert',
        ['answer', 'evaluation']
      ),
    ];
  }

  public function evaluateConfidence(Action $action): float
  {
    return 0.9;
  }

  public function receiveFeedback(Feedback $feedback): void
  {
    // No-op by design: the replay actor never learns and never regenerates.
    if ($this->debug) {
      error_log('ChatReplayActor: received feedback (ignored, observe-only) score='
        . $feedback->getConsensusScore());
    }
  }

  public function getActorId(): string
  {
    return $this->actorId;
  }
}
