<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;

use ClicShopping\AI\Config\AgentSystemConfig;
use ClicShopping\AI\CoreAI\Orchestrator\SubValidation\ValidationGate;
use ClicShopping\AI\DomainsAI\Analytics\Helper\AnalyticsErrorHandler;
use ClicShopping\AI\DomainsAI\DomainRegistry;
use ClicShopping\AI\Helper\TypeSafetyGuard;
use ClicShopping\AI\InterfacesAI\AnalyticsResultEnricherInterface;
use ClicShopping\AI\Security\LlmGuardrails;
use ClicShopping\OM\CLICSHOPPING;

/**
 * AnalyticsResultStage — result stage of AnalyticsAgent: early returns, domain enrichment,
 * interpretation and the optional validation gate. Stateless: the per-request state stays on
 * the agent; the gate re-runs the agent through a closure, never through the agent itself.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Agent
 */
class AnalyticsResultStage
{
  public function __construct(
    private ResultInterpreter $resultInterpreter,
    private AnalyticsErrorHandler $errorHandler,
    private bool $debug = false
  ) {
  }

  /**
   * STEP 2.5: hand the executed rows to the active domain's result enrichers.
   *
   * Skipped when the interpretation is already cached: the answer text is built, enriching
   * would only pay the enricher's queries for nothing.
   *
   * @param array $results Executed query results
   * @return array Results whose rows may carry extra columns
   */
  public function enrichResultRows(array $results): array
  {
    if (empty($results['results']) || !\is_array($results['results'])) {
      return $results;
    }

    if (!empty($results['interpretation'])) {
      return $results;
    }

    $domainApp = DomainRegistry::getInstance()->getActiveApp();

    if ($domainApp === null || !method_exists($domainApp, 'getAnalyticsResultEnrichers')) {
      return $results;
    }

    foreach ($domainApp->getAnalyticsResultEnrichers() as $enricher) {
      if (!$enricher instanceof AnalyticsResultEnricherInterface) {
        continue;
      }

      try {
        $enriched = $enricher->enrich($results['results']);

        if ($enriched !== $results['results']) {
          $this->debugLog("Rows enriched by " . $enricher::class, "ENRICH");
          $results['derived_columns'] = array_values(array_unique(array_merge(
            $results['derived_columns'] ?? [],
            self::addedColumns($results['results'], $enriched)
          )));
          $results['results'] = $enriched;
        }
      } catch (\Throwable $e) {
        // An enricher is additive: its failure must never cost the answer.
        $this->debugLog("Result enricher failed: " . $e->getMessage(), "ENRICH");
      }
    }

    return $results;
  }

  /**
   * Column names present in the enriched rows and absent from the ones handed to the enricher.
   *
   * @param array $before Rows as executed
   * @param array $after Rows as returned by the enricher
   * @return array<int, string>
   */
  private static function addedColumns(array $before, array $after): array
  {
    $keysOf = static function (array $rows): array {
      $keys = [];

      foreach ($rows as $row) {
        if (\is_array($row)) {
          $keys += array_flip(array_map('strval', array_keys($row)));
        }
      }

      return $keys;
    };

    return array_keys(array_diff_key($keysOf($after), $keysOf($before)));
  }

  /**
   * Resolve an early-return response for clarification/ambiguous/empty results.
   *
   * Moved verbatim from AnalyticsAgent::processBusinessQuery. Returns the response to send back
   * directly (clarification, ambiguous, or a no-results error), or null to continue
   * the normal interpretation flow.
   *
   * @param array $results Executed query results
   * @param string $question Original business question
   * @return array|null Early response, or null to continue
   */
  public function resolveEarlyResultReturn(array $results, string $question): ?array
  {
    // Handle unknown or incomplete results
    // ✅ FIX: Allow ambiguous results which use 'interpretation_results' instead of 'results'
    $isAmbiguous = isset($results['type']) && $results['type'] === 'analytics_results_ambiguous';
    $isClarification = isset($results['type']) && $results['type'] === 'clarification_needed';
    $hasResults = isset($results['results']);
    $hasInterpretationResults = isset($results['interpretation_results']) && !empty($results['interpretation_results']);

    // ✅ FIX: For clarification requests, return them directly
    if ($isClarification) {
      $this->debugLog("✅ Clarification needed - returning directly");
      return $results;
    }

    // ✅ FIX: For ambiguous results, return them directly without interpretation
    if ($isAmbiguous && $hasInterpretationResults) {
      $this->debugLog("✅ Ambiguous results detected - returning directly");
      return $results;
    }

    if (!$hasResults && !$hasInterpretationResults && !$isAmbiguous) {
      $this->debugLog("WARNING: No results array in executeQuery response");
      return [
        'type' => 'error',
        'error' => 'Query execution failed to return results',
        'question' => $question,
        'details' => $results
      ];
    }

    return null;
  }

  /**
   * Determine the interpretation for an executed analytics result.
   *
   * Moved verbatim from AnalyticsAgent::processBusinessQuery (STEP 3). Reuses a cached
   * interpretation when present, otherwise generates an empty-results message or a
   * fresh interpretation from the result rows.
   *
   * @param string $question Original business question
   * @param array $results Executed query results
   * @param bool $asksAction The plan read the question as asking what to DO
   * @return mixed Interpretation (normally a string; may be array on upstream quirks)
   */
  public function determineInterpretation(string $question, array $results, bool $asksAction): mixed
  {
    // 🆕 Check if interpretation is already in cache
    if (isset($results['interpretation']) && !empty($results['interpretation'])) {
      $interpretation = $results['interpretation'];
      $this->debugLog("✅ Using cached interpretation");

      // Type-safe logging with TypeSafetyGuard
      if (is_array($interpretation)) {
        $this->debugLog(" WARNING: Cached interpretation is an array, not a string");
      }

      $logSnippet = TypeSafetyGuard::safeSubstr($interpretation, 0, 200);
      $this->debugLog("Interpretation: " . $logSnippet . "...");
    } else {
      if (empty($results['results'])) {
        $this->debugLog("⚠️  WARNING: No results to interpret, generating empty results message");
        $interpretation = $this->errorHandler->generateEmptyResultsMessage($question, $results, $this->debug);
        $this->debugLog(" Empty results message: " . $interpretation);
      } elseif ($this->resultInterpreter->isEmptyResult($results['results'])) {
        // An aggregate over no row is an answer: totals are 0 over the window, never "refine your question".
        $interpretation = CLICSHOPPING::getDef('text_empty_aggregate_window');
      } else {
        // Generate new interpretation only if we have data
        $interpretation = $this->resultInterpreter->interpretResults($question, $results['results'], $results['sql_query'] ?? '', asksAction: $asksAction);
        $this->debugLog(" Generated new interpretation");

        // Type-safe logging with TypeSafetyGuard
        if (is_array($interpretation)) {
          $this->debugLog(" WARNING: interpretResults() returned an array, not a string");
        }

        $logSnippet = TypeSafetyGuard::safeSubstr($interpretation, 0, 200);
        $this->debugLog("Interpretation: " . $logSnippet . "...");
      }
    }

    return $interpretation;
  }

  /**
   * Negative reports the user filed on THIS question, for the critic to weigh.
   *
   * Matching is exact on the stored question: a report on another question says nothing
   * about this answer. An unverified report is an input to the critic, never a generator
   * instruction (AGENTS.md).
   *
   * @param string $question Question under evaluation
   * @param mixed $conversationMemory Conversation memory of the request, null when none
   * @return array<int, string> Reported wordings, newest first
   */
  private function collectUserReportsFor(string $question, mixed $conversationMemory): array
  {
    if ($conversationMemory === null || !method_exists($conversationMemory, 'getFeedbackContext')) {
      return [];
    }

    $needle = mb_strtolower(trim($question));
    $reports = [];

    foreach ($conversationMemory->getFeedbackContext($question, 10) as $item) {
      if (($item['feedback_type'] ?? '') !== 'negative') {
        continue;
      }

      $comment = trim((string)($item['correction_comment'] ?? ''));

      if ($comment !== '' && mb_strtolower(trim((string)($item['original_query'] ?? ''))) === $needle) {
        $reports[] = $comment;
      }
    }

    return array_slice($reports, 0, 3);
  }

  /**
   * Apply the optional LLM validation gate to a built analytics response.
   *
   * OFF by default (flag undefined) -> no behaviour change. When enabled, an LLM
   * evaluation (model-agnostic, no regex) scores the answer; ValidationGate turns the
   * score into a decision. On 'regenerate' it re-runs generation ONCE with the critique
   * as feedback, keeping the new answer ONLY if it scores strictly better (never a
   * regression). The computed evaluation is attached to the response so the formatter
   * reuses it (no double LLM call). Mutates $results and $response by reference.
   *
   * @param string $question Original business question
   * @param bool $includeSQL Whether SQL fields are exposed in the response
   * @param mixed $interpretation Generated interpretation (string when gate runs)
   * @param array $results Query results, mutated by reference on regeneration
   * @param array $response Built response, mutated by reference
   * @param mixed $conversationMemory Conversation memory of the request, null when none
   * @param \Closure $regenerate fn(string $question, array $feedback): array{results: array, asks_action: bool} —
   *        re-runs the agent; `asks_action` is read AFTER the run, from the regenerated plan
   * @return void
   */
  public function applyValidationGate(string $question, bool $includeSQL, mixed $interpretation, array &$results, array &$response, mixed $conversationMemory, \Closure $regenerate): void
  {
    if (AgentSystemConfig::isValidationGateEnabled()
        && is_string($interpretation) && $interpretation !== '') {
      try {
        $evaluation = LlmGuardrails::checkGuardrails($question, $interpretation, [
          'user_reports' => $this->collectUserReportsFor($question, $conversationMemory)
        ]);

        if (is_array($evaluation)) {
          $score = isset($evaluation['overall_score']) ? (float) $evaluation['overall_score'] : null;
          $issues = $evaluation['llm_evaluation']['detected_issues'] ?? [];
          $decision = ValidationGate::decide($score, $issues);

          // Bounded regeneration (one attempt), non-regressive (keep only if strictly better).
          if ($decision['action'] === 'regenerate' && $score !== null && !empty($results['results'])) {
            $feedback = [[
              'feedback_type' => 'correction',
              'original_query' => $question,
              'sql_query' => $results['sql_query'] ?? '',
              'corrected_response' => '',
              'correction_comment' => 'The previous SQL was judged low quality (score ' . round($score, 2)
                . '). Do NOT reproduce it. Issues: ' . implode('; ', array_slice($issues, 0, 5))
                . '. Regenerate a corrected SQL that preserves ALL constraints of the question.',
              'interaction_id' => 'validation_gate_' . uniqid(),
            ]];

            ['results' => $regen, 'asks_action' => $regenAsksAction] = $regenerate($question, $feedback);

            if (($regen['type'] ?? 'error') !== 'error' && !empty($regen['results'])) {
              $regenInterp = $this->resultInterpreter->interpretResults($question, $regen['results'], $regen['sql_query'] ?? '', asksAction: $regenAsksAction);

              if (is_string($regenInterp) && $regenInterp !== '') {
                $regenEval = LlmGuardrails::checkGuardrails($question, $regenInterp);
                $regenScore = is_array($regenEval) && isset($regenEval['overall_score']) ? (float) $regenEval['overall_score'] : null;

                if ($regenScore !== null && $regenScore > $score) {
                  // Adopt the strictly-better regenerated answer.
                  $interpretation = $regenInterp;
                  $results = $regen;
                  $evaluation = $regenEval;
                  $score = $regenScore;
                  $issues = $regenEval['llm_evaluation']['detected_issues'] ?? [];
                  $decision = ValidationGate::decide($score, $issues);

                  $response['interpretation'] = $regenInterp;
                  $response['results'] = $regen['results'];
                  $response['count'] = $regen['count'] ?? count($regen['results']);
                  if ($includeSQL) {
                    $response['sql_query'] = $regen['sql_query'] ?? ($response['sql_query'] ?? 'N/A');
                  }
                  $this->debugLog("Validation gate: regenerated (improved to " . round($regenScore, 2) . ")");
                } else {
                  $this->debugLog("Validation gate: regeneration not better, kept original");
                }
              }
            }
          }

          $response['validation'] = [
            'action' => $decision['action'],
            'reason' => $decision['reason'],
            'score' => $decision['score'],
          ];
          // Pass the computed evaluation to the formatter to avoid a second LLM call.
          $response['validation_evaluation'] = $evaluation;
          $this->debugLog("Validation gate: {$decision['action']} ({$decision['reason']})");
        }
      } catch (\Exception $e) {
        $this->debugLog("Validation gate error: " . $e->getMessage());
      }
    }
  }

  private function debugLog(string $message, string $context = '', array $data = []): void
  {
    if (!$this->debug) {
      return;
    }

    $logMessage = $message;

    if (!empty($context)) {
      $logMessage = "[{$context}] {$message}";
    }

    if (!empty($data)) {
      $logMessage .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    error_log($logMessage);
  }
}
