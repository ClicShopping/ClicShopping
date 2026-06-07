<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubValidation;

/**
 * ValidationGate — turns an LLM quality evaluation into a deterministic decision.
 *
 * Robustness in this codebase must come from the LLM agentic critique (objective / evaluation /
 * validation / critique), NOT from regex/pattern guards that break when the LLM model changes
 * (multi-LLM: OpenAI today, Phi-4 tomorrow). This class therefore performs NO content/regex
 * detection: it only applies a deterministic policy to the **normalized 0..1 quality score**
 * already produced by an LLM evaluator (e.g. LlmGuardrails::checkGuardrails / evaluateLlmResponse).
 * A score threshold is model-agnostic, so it stays valid across LLM providers.
 *
 * Decision values:
 *  - 'pass'       : score acceptable → deliver as-is.
 *  - 'annotate'   : moderate concern → deliver but surface the quality note to the user.
 *  - 'regenerate' : low quality → caller may re-run generation with the critique as feedback
 *                   (bounded by the caller). Execution of the re-run is the caller's responsibility.
 */
final class ValidationGate
{
  /**
   * @param float|null $overallScore Normalized 0..1 quality score from the LLM evaluator.
   * @param array      $detectedIssues Textual issues reported by the evaluator (passed through).
   * @param float      $regenerateBelow Strictly-below threshold for 'regenerate'.
   * @param float      $annotateBelow Strictly-below threshold for 'annotate'.
   * @return array{action:string, reason:string, score:float|null, issues:array}
   */
  public static function decide(
    ?float $overallScore,
    array $detectedIssues = [],
    float $regenerateBelow = 0.5,
    float $annotateBelow = 0.7
  ): array {
    $issues = array_values($detectedIssues);

    if ($overallScore === null) {
      return ['action' => 'pass', 'reason' => 'no score available', 'score' => null, 'issues' => $issues];
    }

    if ($overallScore < $regenerateBelow) {
      return ['action' => 'regenerate', 'reason' => 'low quality score', 'score' => $overallScore, 'issues' => $issues];
    }

    if ($overallScore < $annotateBelow) {
      return ['action' => 'annotate', 'reason' => 'moderate quality concern', 'score' => $overallScore, 'issues' => $issues];
    }

    return ['action' => 'pass', 'reason' => 'acceptable', 'score' => $overallScore, 'issues' => $issues];
  }
}
