<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * ClarifyBeforeSplitStage
 *
 * Runs the clarification gate on the WHOLE question, before it is cut into sub-queries.
 *
 * A cut half does re-enter the full pipeline, but amputated of the question it came from:
 * AnalyticsExecutor flags it `$isSubQuery` (its text differs from the plan query) and AnalyticsAgent
 * then skips ambiguity detection for it. The whole question never reaches an agent on a decomposed
 * run either — so without this stage the gate runs NOWHERE: "how many orders" asks the user for a
 * period when asked alone, and silently assumes one when it is half of a compound question.
 *
 * Placed before `route_hybrid_early` because the cut has two routes downstream and both start here:
 * `HybridQueryDecomposer` (via the hybrid handler) and `SubTaskPlannerAnalytics` (which reads the
 * analysis' own `sub_queries` when `is_hybrid` is false). The condition is therefore the cut itself
 * — two or more sub-queries — not the intent type.
 *
 * Only `clarify` short-circuits: `generate_both` and `use_default` need the per-half SQL generator
 * and stay in AnalyticsAgent.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\DomainsAI\Analytics\Helper\Detection\AmbiguousQueryDetector;
use ClicShopping\AI\DomainsAI\Semantic\Processor\EnglishQueryNormalizer;
use ClicShopping\AI\DomainsAI\Shared\Helper\AgentResponseHelper;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;

class ClarifyBeforeSplitStage implements OrchestrationStageInterface
{
  /** Built on the first question that is actually cut — a single question never pays for it. */
  private ?AmbiguousQueryDetector $detector = null;

  public function __construct(
    private SecurityLogger $securityLogger,
    private bool $debug
  ) {
  }

  public function id(): string
  {
    return 'clarify_before_split';
  }

  public function run(OrchestrationContext $context): ?array
  {
    $subQueries = $context->intent['sub_queries'] ?? [];

    if (!is_array($subQueries) || \count($subQueries) < 2) {
      return null;
    }

    $query = trim($context->queryToProcess);

    if ($query === '') {
      return null;
    }

    $verdict = $this->clarificationWantedByEveryAnalyticsHalf($subQueries);

    if ($verdict === null) {
      return null;
    }

    $ambiguityType = $verdict['ambiguity_type'] ?? null;

    $this->securityLogger->logStructured('info', 'OrchestratorAgent', 'CLARIFY_BEFORE_SPLIT', [
      'query' => substr($query, 0, 100),
      'triggered_by' => $verdict['half'],
      'ambiguity_type' => $ambiguityType,
      'sub_query_count' => \count($subQueries),
      'note' => 'a half needs clarification; asked once on the whole question, the split never ran'
    ]);

    $response = AgentResponseHelper::buildClarificationRequest($query, $ambiguityType);
    $message = $response['message'] ?? '';

    // Returning non-null short-circuits the pipeline, so this IS the final response: it carries the
    // keys the formatter and the chat read, not only the helper's own.
    return $response + [
      'success' => true,
      'clarification_needed' => true,
      'question' => $query,
      'text_response' => $message,
      'response' => $message,
      'intent' => $context->intent,
      'agent_used' => 'orchestrator',
    ];
  }

  /**
   * Ask only when EVERY analytics half wants a clarification.
   *
   * Two measurements shape this, both 2026-08-23:
   *  - never ask the detector about the whole string: the five-part question came back
   *    `time / clarify` while its five halves each came back `proceed`. It invents a missing
   *    period on a long multi-intent sentence. A half is the shape it reads reliably — and the
   *    shape that will be planned.
   *  - "every", not "any": one half short of a period does not make the question unanswerable.
   *    Blocking five answers for one imprecision took `hybrid_five_level` from 3/3 to 0/3 — the
   *    same shape as the refused-metric border SQL-40 had to walk back.
   *
   * Only analytics halves count: a period cannot be missing from "the return policy".
   * The scan stops at the first half that is happy, so a question that will not ask costs one call.
   *
   * @param array $subQueries Sub-queries produced by the analysis, in either shape
   * @return array{half: string, ambiguity_type: string|null}|null Verdict, or null to keep going
   */
  private function clarificationWantedByEveryAnalyticsHalf(array $subQueries): ?array
  {
    $first = null;

    foreach ($subQueries as $subQuery) {
      if (is_array($subQuery)) {
        $text = $subQuery['query'] ?? $subQuery['text'] ?? '';
        $type = $subQuery['intent_type'] ?? $subQuery['type'] ?? 'analytics';
      } else {
        $text = $subQuery;
        $type = 'analytics';
      }

      if (!is_string($text) || trim($text) === '' || $type !== 'analytics') {
        continue;
      }

      // The detector reads English: a French half scores no keyword and comes back unambiguous.
      $analysis = $this->detector()->detectAmbiguity(EnglishQueryNormalizer::normalize(trim($text)));

      if (($analysis['is_ambiguous'] ?? false) !== true || ($analysis['recommendation'] ?? '') !== 'clarify') {
        return null;
      }

      $first ??= [
        'half' => substr(trim($text), 0, 100),
        'ambiguity_type' => $analysis['ambiguity_type'] ?? null,
      ];
    }

    return $first;
  }

  /**
   * Lazily build the detector, on the same façade and the same fallback as AnalyticsAgent.
   */
  private function detector(): AmbiguousQueryDetector
  {
    if ($this->detector === null) {
      try {
        $chat = Gpt::getChatForModel(Gpt::defaultModel());
      } catch (\Exception $e) {
        $chat = Gpt::getChatForModel(Gpt::getTechnicalFallbackModel());
      }

      $this->detector = new AmbiguousQueryDetector($chat, $this->securityLogger, $this->debug);
    }

    return $this->detector;
  }
}
