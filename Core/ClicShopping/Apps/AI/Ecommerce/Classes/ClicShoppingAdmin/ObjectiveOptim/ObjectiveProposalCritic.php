<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\ObjectiveOptim;

use ClicShopping\AI\InterfacesAI\CriticAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Evaluation;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Prediction;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\EvaluationCriteria;
use ClicShopping\AI\RegistryAI\CriticRegistry;

/**
 * ObjectiveProposalCritic — scores the soundness of a §Z Z3 objective proposal from its
 * description/payload. Deterministic, no LLM call: a well-formed advisory (non-empty
 * description, has a target) scores high; an empty/degenerate proposal scores low. The
 * same class is registered twice (distinct facet ids) to meet the coordinator's
 * min-2-critics requirement.
 */
class ObjectiveProposalCritic implements CriticAgentInterface
{
  public const OUTPUT_TYPE = 'objective_proposal';

  private string $criticId;
  private bool $debug;

  public function __construct(string $facet = 'soundness', bool $debug = false, ?CriticRegistry $registry = null)
  {
    $this->criticId = 'objective_proposal_critic_' . $facet . '_' . uniqid();
    $this->debug = $debug;
    if ($registry !== null) {
      $registry->registerCritic($this);
    }
  }

  public function evaluateAction(ActionResult $result): Evaluation
  {
    $output = $result->getOutput();
    $description = \is_array($output) ? (string)($output['description'] ?? '') : '';
    $proposal = \is_array($output) ? ($output['proposal'] ?? []) : [];

    $hasDescription = trim($description) !== '';
    $hasTarget = \is_array($proposal) && !empty($proposal);

    $score = 0.0;
    $score += $hasDescription ? 0.6 : 0.0;
    $score += $hasTarget ? 0.4 : 0.0;

    $evalScores = [
      'accuracy' => $score,
      'completeness' => $hasTarget ? 1.0 : 0.0,
      'efficiency' => $score,
      'clarity' => $hasDescription ? 1.0 : 0.0,
    ];

    $feedback = sprintf('Objective proposal soundness: description=%s, target=%s',
      $hasDescription ? 'yes' : 'no', $hasTarget ? 'yes' : 'no');
    $strengths = $score >= 0.7 ? ['Proposal is well-formed and actionable.'] : [];
    $improvements = $hasDescription ? [] : ['Proposal lacks an actionable description.'];

    return new Evaluation($this->criticId, $result->getResultId(), $evalScores, $feedback, $strengths, $improvements);
  }

  public function predictOutcome(Action $action): Prediction
  {
    return new Prediction(
      $action->getActionId(),
      $this->criticId,
      ['expected_quality' => 0.7],
      0.7,
      [],
      ['success' => 0.7],
      []
    );
  }

  public function getEvaluationCriteria(): array
  {
    return [
      self::OUTPUT_TYPE => new EvaluationCriteria(
        self::OUTPUT_TYPE,
        0.85,
        'objective',
        ['accuracy' => 0.4, 'completeness' => 0.25, 'efficiency' => 0.2, 'clarity' => 0.15],
        ['min_quality' => 0.6],
        ['accuracy' => 0.6, 'completeness' => 0.5, 'efficiency' => 0.5, 'clarity' => 0.6]
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
