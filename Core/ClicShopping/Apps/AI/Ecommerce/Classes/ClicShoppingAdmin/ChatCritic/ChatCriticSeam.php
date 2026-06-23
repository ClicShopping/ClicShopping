<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\ChatCritic;

use ClicShopping\AI\Config\ChatCriticConfig;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCriticFactory;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\AI\Security\LlmResponseEvaluator;

/**
 * ChatCriticSeam
 *
 * Domain entry point (§Z Z1) that routes the chat quality verdict through the
 * agnostic ActorCriticCoordinator — patron SEO. Observe-only: the persisted verdict
 * is ALWAYS LlmResponseEvaluator::deriveQualitySignal($guardrailsEval) (parity with
 * the legacy path); the coordinator consensus is attached as an extra observational
 * field for future gate calibration. Any failure falls back to the plain verdict so
 * the chat response is never broken.
 *
 * Dark-launch: gated by ChatCriticConfig::isSeamEnabled() (OFF by code default).
 */
class ChatCriticSeam
{
  public const ACTION_TYPE = 'chat_response_eval';

  public static function isEnabled(): bool
  {
    return ChatCriticConfig::isSeamEnabled();
  }

  /**
   * Run the chat answer + its pre-computed guardrails evaluation through the agnostic
   * coordinator and return a deriveQualitySignal-shaped verdict (+ observational
   * consensus_score). Never throws — falls back to the plain verdict on any error.
   *
   * @param string $answer The already-generated chat answer text.
   * @param array  $guardrailsEval The LlmResponseEvaluator output (LlmGuardrails::getLastEvaluation()).
   * @param Context $ctx Evaluation context (user/language).
   * @return array deriveQualitySignal verdict, optionally with 'consensus_score'.
   */
  public static function evaluate(string $answer, array $guardrailsEval, Context $ctx): array
  {
    $verdict = LlmResponseEvaluator::deriveQualitySignal($guardrailsEval);

    $debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    try {
      $coordinator = ActorCriticFactory::create(
        [fn(ActorRegistry $r) => new ChatReplayActor($debug, $r)],
        [
          fn(CriticRegistry $r) => new ChatQualityCritic($debug, $r),
          fn(CriticRegistry $r) => new ChatSecurityCritic($debug, $r),
        ]
      );

      $action = new Action(
        self::ACTION_TYPE,
        ['answer' => $answer, 'evaluation' => $guardrailsEval],
        $ctx,
        'medium',
        30
      );

      $coordinated = $coordinator->coordinateExecution($action);
      $verdict['consensus_score'] = $coordinated->getConsensusScore();
    } catch (\Throwable $e) {
      if ($debug) {
        error_log('ChatCriticSeam: coordinator failed, observe-only fallback to plain verdict - ' . $e->getMessage());
      }
      // $verdict already holds the plain deriveQualitySignal result.
    }

    return $verdict;
  }
}
