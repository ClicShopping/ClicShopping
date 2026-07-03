<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCriticFactory;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCriticCoordinator;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\Config\ActorCriticConfig;
use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Actors\SeoOptimizationActor;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents\SeoAuditAgent;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents\SeoCodeValidationAgent;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents\SeoOptimizationAgent;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Critics\SeoValidationCritic;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Critics\SeoContentReadinessCritic;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class SeoAgenticPipeline
{
  private SeoEntityAdapter $adapter;
  private SeoSerpReportRepository $reportRepo;
  private SeoObservability $observability;
  private bool $debug;
  private ?SeoOptimizationAgent $seoAgentOverride   = null;
  private ?SeoCodeValidationAgent $codeAgentOverride = null;
  private float $actorCriticThreshold = 0.7;
  private array $lastBenchmark = [];
  private string $pipelineRunUuid = '';

  // T6.4 — pipeline metrics accumulated during optimize()
  private int  $llmCallCount     = 0;
  private int  $attemptCount     = 0;
  private bool $actorCriticUsed  = false;

  public function __construct(
    string $entityType,
    ?SeoOptimizationAgent $seoAgentOverride = null,
    ?SeoCodeValidationAgent $codeAgentOverride = null
  )
  {
    $this->adapter = new SeoEntityAdapter($entityType);
    $this->reportRepo = new SeoSerpReportRepository();
    $this->observability = new SeoObservability();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';
    $this->seoAgentOverride = $seoAgentOverride;
    $this->codeAgentOverride = $codeAgentOverride;
  }

  /**
   * Run the full agentic SEO optimization for a single language.
   *
   * When $excludeFaq is true the underlying SeoOptimizationAgent skips FAQ
   * generation.  This is the Phase 2 entry point: FAQ is then produced
   * separately in Phase 3 by SeoFaqPipeline with grounding / hallucination
   * checks.  Defaults to false to preserve backward compatibility with the
   * legacy single-shot AJAX endpoint.
   */
  public function optimize(
    int $entityId,
    int $languageId,
    string $url,
    string $baseUrl,
    string $triggeredBy = 'manual',
    bool $excludeFaq = false
  ): array {
    $pipelineStart         = microtime(true);
    $this->llmCallCount    = 0;
    $this->attemptCount    = 0;
    $this->actorCriticUsed = false;
    $this->pipelineRunUuid = $this->generateRunUuid();
    $this->lastBenchmark   = [];

    if ($languageId <= 0) {
      $languageId = $this->adapter->getLanguageId($entityId, null);
    }

    $this->logDebug('Pipeline start', [
      'entity_type' => $this->adapter->getEntityType(),
      'entity_id' => $entityId,
      'language_id' => $languageId,
      'url' => $url,
      'base_url' => $baseUrl,
      'triggered_by' => $triggeredBy,
    ]);

    if (!$this->adapter->isSupported()) {
      $this->logDebug('Pipeline stop: unsupported entity type', [
        'entity_type' => $this->adapter->getEntityType(),
      ]);
      return [
        'success' => false,
        'error' => 'Entity type not supported for SEO optimization.',
      ];
    }

    $context = new Context('system', $languageId, [
      'entity_type' => $this->adapter->getEntityType(),
      'entity_id' => $entityId,
    ]);

    $current = $this->loadEntityData($entityId, $languageId);
    if ($current === null) {
      $this->logDebug('Pipeline stop: entity not found', [
        'entity_id' => $entityId,
        'language_id' => $languageId,
      ]);
      return [
        'success' => false,
        'error' => 'Entity data not found for SEO optimization.',
      ];
    }

    $this->logDebug('Loaded entity data', [
      'name' => $current['name'] ?? '',
      'entity_type' => $this->adapter->getEntityType(),
    ]);

    $seoReport = new SeoReport($url, $baseUrl);
    $seoBefore = $seoReport->getSeoData(false, $this->adapter->getEntityType());

    if (!($seoBefore['isAlive'] ?? false)) {
      $this->logDebug('Pipeline stop: initial seo crawl failed', [
        'error' => $seoBefore['error'] ?? '',
        'http_code' => $seoBefore['http_code'] ?? null,
      ]);
      return [
        'success' => false,
        'error' => 'Page inaccessible pour audit SEO initial.',
      ];
    }
    $this->logDebug('Initial SEO score', [
      'seo_score_before' => $seoBefore['seo_score'] ?? 0,
    ]);

    $serpResult = $this->noSerpResult($current, $languageId);

    $seoAgent = $this->seoAgentOverride ?? new SeoOptimizationAgent();
    $codeAgent = $this->codeAgentOverride ?? new SeoCodeValidationAgent();

    $useActorCritic = ActorCriticConfig::isEnabled();
    $coordinator = null;

    if ($useActorCritic) {
      try {
        $coordinator = ActorCriticFactory::create(
          [fn(ActorRegistry $r) => new SeoOptimizationActor($this->debug, $r, $seoAgent)],
          [
            fn(CriticRegistry $r) => new SeoValidationCritic($this->debug, $r, $codeAgent),
            fn(CriticRegistry $r) => new SeoContentReadinessCritic($this->debug, $r),
          ]
        );
        $this->actorCriticUsed = true;   // T6.4
      } catch (\Throwable $e) {
        $this->logDebug('Actor-Critic init failed, fallback to legacy', [
          'error' => $e->getMessage(),
        ]);
        $useActorCritic = false;
      }
    }

    // Agentic, language-agnostic source-fidelity check (Pure LLM Mode). Built once
    // per run (language is fixed); used as the PRIMARY anti-regression signal below.
    $fidelityChecker = new SeoFidelityChecker($this->adapter->getLanguage($languageId), $this->debug);

    $loopResult = $this->runOptimizationRetryLoop(
      $serpResult,
      $current,
      $context,
      $excludeFaq,
      $seoAgent,
      $codeAgent,
      $useActorCritic,
      $coordinator,
      $fidelityChecker,
      $entityId,
      $languageId,
      $triggeredBy
    );
    $proposal          = $loopResult['proposal'];
    $normalizedChanges = $loopResult['normalized'];
    $codeValidation    = $loopResult['codeValidation'];
    $bestAttempt       = $loopResult['bestAttempt'];

    return $this->finalizeOptimization(
      $proposal,
      $normalizedChanges,
      $codeValidation,
      $bestAttempt,
      $current,
      $fidelityChecker,
      $seoReport,
      $seoBefore,
      $serpResult,
      $context,
      $entityId,
      $languageId,
      $url,
      $triggeredBy,
      $excludeFaq,
      $pipelineStart
    );
  }

  /**
   * Load the entity's current SEO data merged with any adapter-specific
   * additional context. Returns null when the entity is not found. Extracted
   * verbatim from optimize().
   *
   * @return array<string, mixed>|null
   */
  private function loadEntityData(int $entityId, int $languageId): ?array
  {
    $current = $this->adapter->getCurrentData($entityId, $languageId);
    if ($current === null) {
      return null;
    }
    $additionalContext = $this->adapter->getAdditionalContext($entityId, $languageId);
    if (!empty($additionalContext)) {
      $current = array_merge($additionalContext, $current);
    }
    return $current;
  }

  /**
   * Empty SERP-shaped result. SEO optimization no longer queries any external
   * SERP source (removed: no measurable quality/fidelity value, slower, could
   * fail at runtime). This keeps the report/enrichment shape so downstream
   * consumers and the stored report columns are unchanged, with no enrichment.
   *
   * @param array<string, mixed> $current
   * @return array<string, mixed>
   */
  private function noSerpResult(array $current, int $languageId): array
  {
    return [
      'success'             => true,
      'source'              => 'none',
      'query'               => (string)($current['name'] ?? ''),
      'language'            => $this->adapter->getLanguage($languageId),
      'intent_dominant'     => 'unknown',
      'features_visible'    => [],
      'types_of_pages'      => [],
      'topics'              => [],
      'keywords'            => [],
      'competitor_insights' => [],
      'top_results'         => [],
    ];
  }

  /**
   * Finalize the optimization after the retry loop: best-attempt fallback,
   * not-applied / preservation gate, apply to the CMS, structural + semantic
   * enhancement gates, audit (with rollback), persist the report and build the
   * result. Every path returns the optimize() result array. Extracted verbatim
   * from optimize() to shrink it.
   *
   * @return array<string, mixed>
   */
  private function finalizeOptimization(
    array $proposal,
    array $normalizedChanges,
    array $codeValidation,
    ?array $bestAttempt,
    array $current,
    SeoFidelityChecker $fidelityChecker,
    SeoReport $seoReport,
    array $seoBefore,
    array $serpResult,
    Context $context,
    int $entityId,
    int $languageId,
    string $url,
    string $triggeredBy,
    bool $excludeFaq,
    float $pipelineStart
  ): array {
    // Best-attempt fallback: when no attempt reached approved=true but at
    // least one produced usable content (no critical flag), apply it.  The
    // alternative is "Source content kept" — the admin sees nothing
    // changed after 60-90 seconds of LLM work, which is the worse UX.
    if (!($codeValidation['approved'] ?? false) && $bestAttempt !== null) {
      $this->logDebug('Falling back to best attempt', [
        'attempt'       => $bestAttempt['attempt'],
        'quality_score' => $bestAttempt['quality_score'],
      ]);
      $proposal          = $bestAttempt['proposal'];
      $normalizedChanges = $bestAttempt['normalized'];
      $codeValidation    = $bestAttempt['code_validation'];
      $codeValidation['approved'] = true;        // mark as soft-approved
      $codeValidation['fallback'] = true;        // surfaced in the return
    }

    if (!($codeValidation['approved'] ?? false)) {
      $isBenchmarkRegression = !empty($this->lastBenchmark)
        && ($this->lastBenchmark['is_regression'] ?? false);

      return [
        'success'   => false,
        'status'    => 'not_applied_regression',
        // Surface a clearer message when the failure comes from the
        // anti-regression guard rather than the structural validator.
        'error'     => $isBenchmarkRegression
          ? 'Source content kept: every optimization attempt regressed the SEO quality score.'
          : 'Code validation failed after retries.',
        'validation' => $codeValidation,
        'benchmark'  => $this->lastBenchmark,
        'proposal'   => $proposal,
      ];
    }

    $originalData = $current;

    // HARD PRESERVATION GATE — never apply content that drops source facts,
    // regardless of which path (approved or best-attempt) selected it. Re-check
    // the final proposal against the source; abort (keep original) on regression.
    $finalSource    = (string)($current['description'] ?? '');
    $finalGenerated = (string)($proposal['description'] ?? '');
    $finalFidelity  = $fidelityChecker->check($finalSource, $finalGenerated);

    // FAIL-CLOSED: abort when the optimized text drops facts (fidelity_ok=false)
    // OR when preservation could not be verified at all (available=false). We never
    // apply content we could not prove faithful — the original is kept intact.
    if ($finalFidelity['available'] === false || $finalFidelity['fidelity_ok'] === false) {
      $missingEntities = $finalFidelity['missing_entities'] ?? [];
      $this->logDebug('Preservation gate: aborting apply (regression)', [
        'preservation_score' => $finalFidelity['preservation_score'] ?? null,
        'missing_entities'   => $missingEntities,
      ]);
      return [
        'success'          => false,
        'status'           => 'not_applied_regression',
        'error'            => 'Optimisation non appliquée : régression de fidélité — des faits de la source seraient perdus.',
        'missing_entities' => $missingEntities,
        'benchmark'        => $this->lastBenchmark,
        'proposal'         => $proposal,
      ];
    }

    $applied = $this->adapter->applySeoChanges($entityId, $languageId, $normalizedChanges, true);

    if (!$applied) {
      $this->logDebug('Pipeline stop: apply changes failed', [
        'changes' => $normalizedChanges,
      ]);
      return [
        'success' => false,
        'error' => 'Failed to apply SEO changes to CMS.',
      ];
    }
    $this->logDebug('Changes applied', [
      'changes' => $normalizedChanges,
    ]);

    $seoAfter = $seoReport->getSeoData(true, $this->adapter->getEntityType());

    // STRUCTURAL ENHANCEMENT GATE (Lot 2a). Compute the structural SEO before/after
    // and ROLL BACK on a critical regression (schema removed / headings zeroed /
    // word-count collapse). Non-critical metrics are informational only.
    $enhancement = (new SeoEnhancementScorer())->score(
      $seoBefore,
      $seoAfter,
      (string)($current['description'] ?? ''),
      (string)($proposal['description'] ?? '')
    );

    if ($enhancement['critical_regression'] === true) {
      $this->adapter->applySeoChanges($entityId, $languageId, $originalData, false);
      $this->logDebug('Enhancement gate: critical structural regression — rolled back', [
        'reasons' => $enhancement['critical_reasons'],
      ]);
      return [
        'success'     => false,
        'status'      => 'not_applied_seo_regression',
        'error'       => 'Optimisation annulée : régression SEO structurelle (' . implode(', ', $enhancement['critical_reasons']) . ').',
        'enhancement' => $enhancement,
        'benchmark'   => $this->lastBenchmark,
      ];
    }

    // SEMANTIC ENHANCEMENT (Lot 2b) — informational keyword/LSI coverage rows + a
    // soft advisory. One LLM call; fail-open (no rows, no advisory on error/empty).
    $semantic = (new SeoSemanticEnhancementScorer($this->adapter->getLanguage($languageId), $this->debug))->score(
      (array)($serpResult['keywords'] ?? []),
      (array)($serpResult['topics'] ?? []),
      (string)($proposal['meta_keywords'] ?? $current['name'] ?? ''),
      (string)($current['description'] ?? ''),
      (string)($proposal['description'] ?? '')
    );
    $semanticRegressed = false;
    if ($semantic['available'] === true) {
      $enhancement['metrics'] = array_merge($enhancement['metrics'], $semantic['metrics']);
      $semanticRegressed = (bool)$semantic['regressed'];
    }

    $auditAgent = new SeoAuditAgent();

    $audit_array = [
      'seo_before'  => $seoBefore,
      'seo_after'   => $seoAfter,
      'changes'     => $normalizedChanges,
      'exclude_faq' => $excludeFaq,
      // De-bias the audit: give it the objective preservation/quality metrics so
      // it reports removals/regressions, not only additions.
      'benchmark'   => [
        'preservation_score' => (float)($finalFidelity['preservation_score'] ?? 1.0),
        'missing_entities'   => $finalFidelity['missing_entities'] ?? [],
        'composite_delta'    => (float)($this->lastBenchmark['delta'] ?? 0),
        'semantic_regressed' => $semanticRegressed,
      ],
    ];

    $auditAction = new Action('seo_audit', $audit_array, $context, 'medium', 60);

    $audit = $auditAgent->executeAction($auditAction)->getOutput();
    $this->logDebug('Audit result', $audit);

    $auditApproved = (bool)($audit['approved'] ?? false);
    $scoreBefore = (int)($audit['score_before'] ?? 0);
    $scoreAfter = (int)($audit['score_after'] ?? 0);
    $changesApplied = $audit['changes_applied'] ?? [];
    $contentImproved = ($scoreAfter >= $scoreBefore) && !empty($changesApplied);

    if (!$auditApproved && $contentImproved) {
      $this->logDebug('Audit soft-accepted (score unchanged but content improved)', [
        'score_before' => $scoreBefore,
        'score_after' => $scoreAfter,
        'changes_applied' => $changesApplied,
      ]);
      $auditApproved = true;
    }

    if (!$auditApproved) {
      // rollback
      $this->adapter->applySeoChanges($entityId, $languageId, $originalData, false);
      $this->logDebug('Rollback applied', [
        'original' => $originalData,
      ]);

      return [
        'success' => false,
        'error' => 'Audit SEO non valide. Rollback effectue.',
        'audit' => $audit,
        'seo_score_before' => $scoreBefore,
        'seo_score_after' => $scoreAfter,
      ];
    }

    $reportId = $this->reportRepo->insert([
      'entity_type'      => $this->adapter->getEntityType(),
      'entity_id'        => $entityId,
      'language_id'      => $languageId,
      'url'              => $url,
      'serp_source'      => $serpResult['source'] ?? 'serpapi',
      'serp_query'       => $serpResult['query']  ?? '',
      'serp_data'        => $serpResult,
      'seo_before'       => $seoBefore,
      'seo_after'        => $seoAfter,
      'proposed_changes' => $normalizedChanges,
      'audit_result'     => $audit,
      'summary'          => $audit['summary'] ?? '',
      'seo_score_before' => $audit['score_before'] ?? 0,
      'seo_score_after'  => $audit['score_after']  ?? 0,
      'status'           => 'applied',
      'triggered_by'     => $triggeredBy,
      'benchmark'        => $this->lastBenchmark,
      // T6.4 — pipeline metrics
      'pipeline_metrics' => [
        'llm_calls'          => $this->llmCallCount,
        'total_time_ms'      => (int)((microtime(true) - $pipelineStart) * 1000),
        'attempts'           => $this->attemptCount,
        'actor_critic_used'  => $this->actorCriticUsed,
      ],
    ]);
    $this->logDebug('Report stored', [
      'report_id' => $reportId,
    ]);

    return [
      'success'         => true,
      'mode'            => 'agentic_optimization',
      'seo_score_before'=> $audit['score_before'] ?? 0,
      'seo_score_after' => $audit['score_after']  ?? 0,
      'improved'        => $audit['improved']      ?? false,
      'message'         => $audit['summary']       ?? 'Optimization applied.',
      'audit_summary'   => $audit['summary']       ?? '',
      'audit'           => $audit,
      'proposal'        => $proposal,
      'serp'            => $serpResult,
      'report_id'       => $reportId,
      'benchmark'       => $this->lastBenchmark,
      'enhancement'     => $enhancement,
      // T6.4 — visible in UI agentic audit panel
      'pipeline_metrics'=> [
        'llm_calls'         => $this->llmCallCount,
        'total_time_ms'     => (int)((microtime(true) - $pipelineStart) * 1000),
        'attempts'          => $this->attemptCount,
        'actor_critic_used' => $this->actorCriticUsed,
      ],
    ];
  }

  /**
   * Retry loop (max 3 attempts): generate a SEO proposal, validate it and enforce
   * the agentic fidelity gate, injecting feedback into the next attempt on failure.
   * Returns the last proposal / normalized changes / code-validation and the best
   * usable attempt seen, for optimize() to apply or fall back to. Extracted verbatim
   * from optimize() to shrink it; loop-internal state stays local to this method.
   *
   * @return array{proposal: array, normalized: array, codeValidation: array, bestAttempt: ?array}
   */
  private function runOptimizationRetryLoop(
    array $serpResult,
    array $current,
    Context $context,
    bool $excludeFaq,
    SeoOptimizationAgent $seoAgent,
    SeoCodeValidationAgent $codeAgent,
    bool $useActorCritic,
    ?ActorCriticCoordinator $coordinator,
    SeoFidelityChecker $fidelityChecker,
    int $entityId,
    int $languageId,
    string $triggeredBy
  ): array {
    $proposal = [];
    $normalizedChanges = [];
    $codeValidation = [];
    $validationFeedback = [];
    $actorCriticFeedback = [];
    $bestAttempt = null;

    for ($attempt = 1; $attempt <= 3; $attempt++) {
      $this->attemptCount = $attempt;   // T6.4
      $array_seo_optimize = [
        'serp_report' => $serpResult,
        'current_content' => $current,
        'entity_name' => $current['name'] ?? '',
        'entity_type' => $this->adapter->getEntityType(),
        'validation_feedback' => $validationFeedback,
        'exclude_faq' => $excludeFaq,
      ];

      $seoAction = new Action('seo_optimize', $array_seo_optimize, $context, 'high', 90);
      if ($useActorCritic && $coordinator !== null) {
        try {
          $coordinated = $coordinator->coordinateExecution($seoAction);
          $proposal = $coordinated->getFinalOutput();
          $actorCriticFeedback = $coordinated->getAggregatedFeedback();

          $consensusScore = $coordinated->getConsensusScore();
          $consensusOk = $consensusScore >= $this->actorCriticThreshold;

          if (($proposal['approved'] ?? false) && $consensusOk) {
            $this->logDebug('Actor-Critic consensus ok', [
              'attempt' => $attempt,
              'consensus_score' => $consensusScore,
            ]);
          } else {
            $this->logDebug('Actor-Critic consensus failed', [
              'attempt' => $attempt,
              'consensus_score' => $consensusScore,
            ]);
            $validationFeedback = $this->mapActorCriticFeedback($actorCriticFeedback, $attempt);
            continue;
          }
        } catch (\Throwable $e) {
          $this->logDebug('Actor-Critic execution failed, fallback to legacy', [
            'error' => $e->getMessage(),
          ]);
          $useActorCritic = false;
          $proposal = $seoAgent->executeAction($seoAction)->getOutput();
        }
      } else {
        $proposal = $seoAgent->executeAction($seoAction)->getOutput();
      }

      if (!($proposal['approved'] ?? false)) {
        $this->logDebug('SEO proposal rejected (generation)', [
          'attempt' => $attempt,
          'proposal' => $proposal,
        ]);
        $validationFeedback = [
          'issues' => ['Generation returned empty required fields'],
          'suggestions' => ['Provide meta title and meta description within required lengths'],
          'attempt' => $attempt,
        ];
        continue;
      }

      $this->logDebug('SEO proposal', [
        'attempt' => $attempt,
        'meta_title' => $proposal['meta_title'] ?? '',
        'meta_description' => $proposal['meta_description'] ?? '',
        'meta_keywords' => $proposal['meta_keywords'] ?? '',
        'faq_count' => isset($proposal['faq']) ? count($proposal['faq']) : 0,
      ]);

      $normalizedChanges = $this->adapter->normalizeChanges($proposal);
      $this->logDebug('Normalized changes', $normalizedChanges);

      $array_code_validation = [
        'entity_type' => $this->adapter->getEntityType(),
        'changes' => $normalizedChanges,
        // Forward the Phase 2 vs Phase 3 distinction: when Phase 2 ran with
        // exclude_faq=true the validator must NOT penalise the absence of a
        // FAQ section (Phase 3 handles FAQ with grounding checks).
        'exclude_faq' => $excludeFaq,
      ];

      $codeAction = new Action('seo_code_validation', $array_code_validation, $context, 'medium', 30);
      $codeValidation = $codeAgent->executeAction($codeAction)->getOutput();

      if (($codeValidation['approved'] ?? false) === true) {
        // Anti-regression guard.  The validator may approve a proposal that
        // is syntactically clean (right lengths, no spam, valid schema) but
        // SEO-poorer than the source — lost source facts, keyword stuffing.
        //
        // GATE = agentic LLM fidelity check (Pure LLM Mode, language-agnostic:
        // EN/FR/DE/IT/…). It judges semantically whether the optimized text
        // still preserves every source fact — robust to synonyms/paraphrase.
        // When fidelity fails we treat it as a validation failure and feed the
        // missing facts back to the next attempt so the LLM can re-introduce
        // them. If every attempt regresses, the pipeline ultimately refuses to
        // apply and keeps the original content intact. When the LLM is
        // unavailable the fidelity gate is skipped (NO language-coupled
        // fallback) — the code-validation gate still guards quality.
        //
        // SeoObservability is NOT a gate: it produces language-agnostic
        // metrics (entropy / diversity / repetition / word-count) for the
        // report / UI / analytics only. Source-entity coverage comes from the
        // fidelity check's LLM `coverage_estimate`, never a keyword heuristic.
        $source    = (string)($current['description'] ?? '');
        $generated = (string)($proposal['description'] ?? '');

        $fidelity = $fidelityChecker->check($source, $generated);

        if ($fidelity['available']) {
          $isRegression     = !$fidelity['fidelity_ok'];
          $missingFeedback  = $fidelity['missing_facts'];
          $regressionWhy    = $fidelity['fidelity_ok'] ? 'fidelity_ok' : 'fidelity_missing_facts';
          $coverageEstimate = (float)$fidelity['coverage_estimate'];
        } else {
          // LLM fidelity unavailable → skip the fidelity gate for this attempt;
          // the code-validation gate still guards quality.
          $isRegression     = false;
          $missingFeedback  = [];
          $regressionWhy    = 'fidelity_unavailable_skipped';
          $coverageEstimate = 1.0;
        }

        // Observability metrics (language-agnostic). Coverage is sourced from
        // the agentic fidelity check, NOT a keyword/stem heuristic.
        $sourceScore    = $this->observability->scoreText($source);
        $generatedScore = $this->observability->scoreText($generated, $coverageEstimate);
        $delta          = round($generatedScore['score'] - $sourceScore['score'], 3);

        $verdict = $isRegression
          ? 'regression'
          : ($delta > 0.05 ? 'improvement' : 'parity');

        $benchmark = [
          'source_score'      => $sourceScore,
          'generated_score'   => $generatedScore,
          'delta'             => $delta,
          'verdict'           => $verdict,
          'regression_reason' => $isRegression ? $regressionWhy : 'none',
          'is_regression'     => $isRegression,
          'diagnostics'       => [
            'critical'      => $isRegression,
            'coverage'      => round($coverageEstimate, 3),
            'repetition'    => (float)($generatedScore['breakdown']['repetition'] ?? 0),
            'missing_facts' => $missingFeedback,
            'messages'      => [],
          ],
          'fidelity'          => $fidelity,
          'attempt'           => $attempt,
        ];
        $this->lastBenchmark = $benchmark;
        $this->logDebug('Observability + fidelity benchmark', $benchmark);

        // Persist the benchmark for THIS attempt so analytics can rebuild the
        // full retry sequence; the feedback snapshot captures exactly what was
        // injected into the next LLM call so the loop is auditable end-to-end.
        try {
          $this->persistBenchmarkLog(
            $entityId,
            $languageId,
            $benchmark,
            $triggeredBy,
            $attempt,
            $isRegression ? $validationFeedback : []
          );
        } catch (\Throwable $e) {
          $this->logDebug('Benchmark log insert failed', ['error' => $e->getMessage()]);
        }

        if (!$isRegression) {
          $this->logDebug('Fidelity/quality ok', ['attempt' => $attempt, 'why' => $regressionWhy]);
          break;
        }

        $this->logDebug('Fidelity regression — retrying', [
          'attempt'       => $attempt,
          'why'           => $regressionWhy,
          'missing_facts' => $missingFeedback,
        ]);

        $missingLine = empty($missingFeedback)
          ? ''
          : 'The optimized text is MISSING these source facts — re-include EACH one (the source wording verbatim, or an equivalent paraphrase): ' . implode('; ', array_slice($missingFeedback, 0, 40)) . '.';

        $validationFeedback = [
          'issues'      => array_merge(
            $codeValidation['feedback']['issues'] ?? [],
            $missingFeedback
          ),
          'suggestions' => array_merge(
            $codeValidation['feedback']['suggestions'] ?? [],
            array_values(array_filter([
              $missingLine,
              'Preserve 100% of the source facts: add enrichment AROUND them, never replace or drop a source attribute.',
              'Broaden vocabulary — avoid over-repeating any single word.',
            ]))
          ),
          'attempt' => $attempt,
        ];
        // Force the loop to keep retrying.
        $codeValidation['approved'] = false;
        continue;
      }

      $this->logDebug('Code validation failed', [
        'attempt' => $attempt,
        'validation' => $codeValidation,
      ]);

      $quality = (int)($codeValidation['quality_score'] ?? 0);
      $hasPlaceholder = !empty($codeValidation['coherence']['placeholder_fields_detected'] ?? []);
      $hasSpam        = (bool)($codeValidation['is_spam'] ?? false);
      $schemaOk       = (bool)($codeValidation['schema_org']['passed'] ?? true);
      $lengthOk       = (bool)($codeValidation['lengths']['passed']    ?? true);
      // Length is the most common soft-fail (66 chars vs 65); treat it as
      // acceptable for fallback IF the title chars are within 2 of the cap.
      $lengthRecoverable = true;
      if (!$lengthOk) {
        $titleChars = (int)($codeValidation['lengths']['lengths']['meta_title_chars'] ?? 0);
        $lengthRecoverable = $titleChars > 0 && $titleChars <= 70;
      }
      $isUsable = !$hasPlaceholder && !$hasSpam && $schemaOk && $lengthRecoverable && $quality >= 70;
      if ($isUsable && ($bestAttempt === null || $quality > $bestAttempt['quality_score'])) {
        $bestAttempt = [
          'attempt'         => $attempt,
          'quality_score'   => $quality,
          'proposal'        => $proposal,
          'normalized'      => $normalizedChanges,
          'code_validation' => $codeValidation,
        ];
        $this->logDebug('Best-attempt candidate recorded', [
          'attempt'       => $attempt,
          'quality_score' => $quality,
        ]);
      }

      $validationFeedback = [
        'issues' => $codeValidation['feedback']['issues'] ?? [],
        'suggestions' => $codeValidation['feedback']['suggestions'] ?? [],
        'attempt' => $attempt,
      ];
      $this->logDebug('Validation feedback injected', $validationFeedback);
    }

    return [
      'proposal'       => $proposal,
      'normalized'     => $normalizedChanges,
      'codeValidation' => $codeValidation,
      'bestAttempt'    => $bestAttempt,
    ];
  }

  /**
   * Append one row PER ATTEMPT to the dedicated analytics table.  Rows
   * share the same pipeline_run_uuid so a full retry sequence can be
   * reconstructed with a single WHERE clause.  Schema columns:
   *
   *   - attempt              SMALLINT     — 1-based attempt index
   *   - pipeline_run_uuid    CHAR(36)     — identifies the optimize() call
   *   - regression_reason    VARCHAR(50)  — fidelity_missing_facts | none
   *                                          (derived from SeoFidelityChecker)
   *   - critical             TINYINT(1)   — same as diagnostics.critical,
   *                                          materialised for index speed
   *   - feedback_snapshot    TEXT         — exact payload that was injected
   *                                          into the next LLM attempt
   *                                          (JSON), or NULL when the
   *                                          attempt was accepted
   *
   * Silently no-ops if the table is missing — the benchmark is observational
   * and should never block the apply path.
   */
  private function persistBenchmarkLog(
    int    $entityId,
    int    $languageId,
    array  $benchmark,
    string $triggeredBy,
    int    $attempt,
    array  $feedbackSnapshot = []
  ): void {
    if (empty($benchmark)) {
      return;
    }

    $db = Registry::get('Db');
    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $table  = $prefix . 'seo_quality_benchmark_log';

    // Detect table existence once; silently skip if the migration has not
    // run on this environment yet.
    static $tableExists = null;
    if ($tableExists === null) {
      try {
        $Qcheck = $db->query("SHOW TABLES LIKE '" . $table . "'");
        $tableExists = (bool)$Qcheck->fetch();
      } catch (\Throwable $e) {
        $tableExists = false;
      }
    }
    if (!$tableExists) {
      return;
    }

    $srcBreak = $benchmark['source_score']['breakdown']    ?? [];
    $genBreak = $benchmark['generated_score']['breakdown'] ?? [];
    $diagnostics = $benchmark['diagnostics'] ?? [];
    $isCritical = (int)(bool)($diagnostics['critical'] ?? $benchmark['is_regression'] ?? false);

    $sql = "INSERT INTO {$table} (
              entity_type, entity_id, language_id, triggered_by,
              pipeline_run_uuid, attempt,
              verdict, regression_reason, critical, delta,
              source_score, generated_score,
              source_entropy, generated_entropy,
              source_diversity, generated_diversity,
              entity_coverage, repetition,
              diagnostics, feedback_snapshot, date_modified
            ) VALUES (
              :entity_type, :entity_id, :language_id, :triggered_by,
              :pipeline_run_uuid, :attempt,
              :verdict, :regression_reason, :critical, :delta,
              :source_score, :generated_score,
              :source_entropy, :generated_entropy,
              :source_diversity, :generated_diversity,
              :entity_coverage, :repetition,
              :diagnostics, :feedback_snapshot, NOW()
            )";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':entity_type',         $this->adapter->getEntityType());
    $stmt->bindInt(':entity_id',             $entityId);
    $stmt->bindInt(':language_id',           $languageId);
    $stmt->bindValue(':triggered_by',        $triggeredBy);
    $stmt->bindValue(':pipeline_run_uuid',   $this->pipelineRunUuid);
    $stmt->bindInt(':attempt',               $attempt);
    $stmt->bindValue(':verdict',             (string)($benchmark['verdict'] ?? 'unknown'));
    $stmt->bindValue(':regression_reason',   (string)($benchmark['regression_reason'] ?? 'none'));
    $stmt->bindInt(':critical',              $isCritical);
    $stmt->bindValue(':delta',               (float)($benchmark['delta'] ?? 0));
    $stmt->bindValue(':source_score',        (float)($benchmark['source_score']['score']    ?? 0));
    $stmt->bindValue(':generated_score',     (float)($benchmark['generated_score']['score'] ?? 0));
    $stmt->bindValue(':source_entropy',      (float)($srcBreak['normalized_entropy'] ?? 0));
    $stmt->bindValue(':generated_entropy',   (float)($genBreak['normalized_entropy'] ?? 0));
    $stmt->bindValue(':source_diversity',    (float)($srcBreak['diversity'] ?? 0));
    $stmt->bindValue(':generated_diversity', (float)($genBreak['diversity'] ?? 0));
    $stmt->bindValue(':entity_coverage',     (float)($genBreak['entity_coverage'] ?? 0));
    $stmt->bindValue(':repetition',          (float)($genBreak['repetition'] ?? 0));
    $stmt->bindValue(':diagnostics',         json_encode($diagnostics,      JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $stmt->bindValue(':feedback_snapshot',   empty($feedbackSnapshot)
      ? null
      : json_encode($feedbackSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $stmt->execute();
  }

  /**
   * RFC 4122 v4 UUID for the pipeline_run_uuid column.  CHAR(36) and
   * deterministic length matches the schema constraint.
   */
  private function generateRunUuid(): string
  {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant 10xx
    $hex = bin2hex($bytes);
    return sprintf(
      '%s-%s-%s-%s-%s',
      substr($hex, 0, 8),
      substr($hex, 8, 4),
      substr($hex, 12, 4),
      substr($hex, 16, 4),
      substr($hex, 20, 12)
    );
  }

  /**
   * Phase 2 entry point — content optimization without FAQ.
   *
   * Thin wrapper over optimize() that forces FAQ exclusion so the FAQ block
   * can be produced separately in Phase 3 by SeoFaqPipeline with grounding
   * and hallucination checks.  Used by SeoMultilingualOrchestrator for the
   * source-language (EN) pass; translated locales are then derived via
   * TranslationServiceWrapper rather than re-running the full pipeline.
   */
  public function runContentOptimization(
    int $entityId,
    int $languageId,
    string $url,
    string $baseUrl,
    string $triggeredBy = 'manual'
  ): array {
    return $this->optimize($entityId, $languageId, $url, $baseUrl, $triggeredBy, true);
  }

  private function logDebug(string $message, array $context = []): void
  {
    if (!$this->debug) {
      return;
    }

    $payload = $context;
    $payload['message'] = $message;
    $payload['timestamp'] = date('c');

    error_log('SEO_AGENTIC_PIPELINE ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  }

  private function mapActorCriticFeedback(array $aggregated, int $attempt): array
  {
    $issues = [];
    $suggestions = [];

    foreach (['correctness', 'completeness', 'efficiency', 'best_practice'] as $bucket) {
      foreach ($aggregated[$bucket] ?? [] as $item) {
        if (!empty($item['feedback'])) {
          $issues[] = (string)$item['feedback'];
        }
      }
    }

    foreach ($aggregated['improvements'] ?? [] as $item) {
      if (!empty($item['content'])) {
        $suggestions[] = (string)$item['content'];
      }
    }

    // Values are already guarded by !empty() above, so array_filter would be a
    // no-op — just dedupe and re-index.
    $issues = array_values(array_unique($issues));
    $suggestions = array_values(array_unique($suggestions));

    return [
      'issues' => $issues,
      'suggestions' => $suggestions,
      'attempt' => $attempt,
    ];
  }
}
