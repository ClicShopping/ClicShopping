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
 * ChatQualityCritic
 *
 * Critic that scores the QUALITY facet of a chat answer from the already-computed
 * guardrails evaluation (LlmResponseEvaluator output), carried in the ActionResult.
 * It performs NO new LLM call — it re-packages the verdict the live path already
 * produced, so the seam adds no per-request LLM cost.
 */
class ChatQualityCritic implements CriticAgentInterface
{
  public const OUTPUT_TYPE = 'chat_response';

  private string $criticId;
  private bool $debug;

  public function __construct(bool $debug = false, ?CriticRegistry $registry = null)
  {
    $this->criticId = 'chat_quality_critic_' . uniqid();
    $this->debug = $debug;

    if ($registry !== null) {
      $registry->registerCritic($this);
    }
  }

  public function evaluateAction(ActionResult $result): Evaluation
  {
    $output = $result->getOutput();
    $eval = \is_array($output) && \is_array($output['evaluation'] ?? null) ? $output['evaluation'] : [];

    $overall = (float)($eval['overall_score'] ?? 0.0);
    $relevance = (float)($eval['relevance'] ?? 0.0);
    $halluRisk = (float)($eval['hallucination_risk'] ?? 0.0);
    $scores = $eval['llm_evaluation']['scores'] ?? [];
    $accuracy15 = isset($scores['accuracy']) ? (int)$scores['accuracy'] : null;

    // Map to the Evaluation 0-1 score shape. accuracy: prefer the 1-5 LLM score, else overall.
    $accuracy = $accuracy15 !== null ? max(0.0, min(1.0, $accuracy15 / 5)) : $overall;
    $completeness = $overall;
    $efficiency = $relevance > 0.0 ? $relevance : $overall;
    $clarity = max(0.0, min(1.0, 1.0 - $halluRisk)); // fidelity proxy

    $evalScores = [
      'accuracy' => $accuracy,
      'completeness' => $completeness,
      'efficiency' => $efficiency,
      'clarity' => $clarity,
    ];

    $feedback = sprintf(
      'Chat quality from guardrails verdict: overall %.2f, relevance %.2f, hallucination_risk %.2f',
      $overall, $relevance, $halluRisk
    );
    $strengths = $overall >= 0.7 ? ['Answer scored well on the live guardrails verdict.'] : [];
    $improvements = [];
    foreach (($eval['llm_evaluation']['detected_issues'] ?? []) as $issue) {
      if (\is_string($issue) && $issue !== '') {
        $improvements[] = $issue;
      }
    }

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
        'chat',
        ['accuracy' => 0.4, 'completeness' => 0.25, 'efficiency' => 0.2, 'clarity' => 0.15],
        ['min_quality' => 0.6],
        ['accuracy' => 0.6, 'completeness' => 0.6, 'efficiency' => 0.5, 'clarity' => 0.6]
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
