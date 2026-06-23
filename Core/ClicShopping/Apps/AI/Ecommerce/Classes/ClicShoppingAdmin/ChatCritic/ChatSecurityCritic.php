<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\ChatCritic;

use ClicShopping\AI\InterfacesAI\CriticAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Evaluation;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Prediction;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\EvaluationCriteria;
use ClicShopping\AI\RegistryAI\CriticRegistry;

/**
 * ChatSecurityCritic
 *
 * Critic that scores the SECURITY facet of a chat answer from the already-computed
 * guardrails evaluation (security_analysis.overall_security_score). No new LLM call.
 */
class ChatSecurityCritic implements CriticAgentInterface
{
  public const OUTPUT_TYPE = 'chat_response';

  private string $criticId;
  private bool $debug;

  public function __construct(bool $debug = false, ?CriticRegistry $registry = null)
  {
    $this->criticId = 'chat_security_critic_' . uniqid();
    $this->debug = $debug;

    if ($registry !== null) {
      $registry->registerCritic($this);
    }
  }

  public function evaluateAction(ActionResult $result): Evaluation
  {
    $output = $result->getOutput();
    $eval = \is_array($output) && \is_array($output['evaluation'] ?? null) ? $output['evaluation'] : [];
    $security = (float)($eval['security_analysis']['overall_security_score'] ?? 0.8);

    // Security is a single dimension; mirror it across the Evaluation score shape so the
    // overall (weighted) score equals the security score.
    $evalScores = [
      'accuracy' => $security,
      'completeness' => $security,
      'efficiency' => $security,
      'clarity' => $security,
    ];

    $feedback = sprintf('Chat security from guardrails verdict: overall_security_score %.2f', $security);
    $strengths = $security >= 0.8 ? ['Answer is well-formed / safe per the security analysis.'] : [];
    $improvements = $security < 0.7 ? ['Security score below threshold — review for unsafe / malformed content.'] : [];

    return new Evaluation($this->criticId, $result->getResultId(), $evalScores, $feedback, $strengths, $improvements);
  }

  public function predictOutcome(Action $action): Prediction
  {
    return new Prediction(
      $action->getActionId(),
      $this->criticId,
      ['expected_quality' => 0.8],
      0.8,
      [],
      ['success' => 0.8],
      []
    );
  }

  public function getEvaluationCriteria(): array
  {
    return [
      self::OUTPUT_TYPE => new EvaluationCriteria(
        self::OUTPUT_TYPE,
        0.8,
        'chat',
        ['accuracy' => 0.25, 'completeness' => 0.25, 'efficiency' => 0.25, 'clarity' => 0.25],
        ['min_security' => 0.7],
        ['accuracy' => 0.7, 'completeness' => 0.7, 'efficiency' => 0.7, 'clarity' => 0.7]
      ),
    ];
  }

  public function provideFeedback(ActionResult $result): Feedback
  {
    $evaluation = $this->evaluateAction($result);
    return new Feedback(
      $result->getProducerAgentId(),
      $result->getResultId(),
      $evaluation->getOverallScore(),
      ['correctness' => [$evaluation->getFeedback()]],
      $evaluation->getStrengths(),
      $evaluation->getImprovements()
    );
  }

  public function getCriticId(): string
  {
    return $this->criticId;
  }
}
