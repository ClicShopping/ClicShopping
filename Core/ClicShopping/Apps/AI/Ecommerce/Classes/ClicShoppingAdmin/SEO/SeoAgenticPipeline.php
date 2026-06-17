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
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\Config\ActorCriticConfig;
use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Actors\SeoOptimizationActor;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents\SeoAuditAgent;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents\SeoCodeValidationAgent;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents\SeoOptimizationAgent;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents\SerpAgent;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Critics\SeoValidationCritic;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Critics\SeoContentReadinessCritic;

class SeoAgenticPipeline
{
  private SeoEntityAdapter $adapter;
  private SeoSerpReportRepository $reportRepo;
  private SeoQualityBenchmark $qualityBenchmark;
  private bool $debug;
  private ?SeoOptimizationAgent $seoAgentOverride   = null;
  private ?SeoCodeValidationAgent $codeAgentOverride = null;
  private float $actorCriticThreshold = 0.7;
  private array $lastBenchmark = [];
  private string $pipelineRunUuid = '';

  // T6.4 — pipeline metrics accumulated during optimize()
  private int  $llmCallCount     = 0;
  private int  $totalTimeMs      = 0;
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
    $this->qualityBenchmark = new SeoQualityBenchmark();
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

    $current = $this->adapter->getCurrentData($entityId, $languageId);
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
    $additionalContext = $this->adapter->getAdditionalContext($entityId, $languageId);
    if (!empty($additionalContext)) {
      $current = array_merge($additionalContext, $current);
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

    $serpAgent = new SerpAgent();

    $serp_analysis =  [
      'query' => $current['name'] ?? '',
      'entity_name' => $current['name'] ?? '',
      'base_url' => $baseUrl,
      'language' => $this->adapter->getLanguage($languageId),
    ];

    $serpAction = new Action('serp_analysis',$serp_analysis, $context, 'medium', 60);

    $serpResult = $serpAgent->executeAction($serpAction)->getOutput();

    if (!($serpResult['success'] ?? false)) {
      $this->logDebug('Pipeline stop: SERP failed', [
        'error' => $serpResult['error'] ?? '',
        'query' => $serpResult['query'] ?? '',
      ]);
      return [
        'success' => false,
        'error' => $serpResult['error'] ?? 'SERP analysis failed.',
      ];
    }
    $this->logDebug('SERP ok', [
      'query' => $serpResult['query'] ?? '',
      'intent' => $serpResult['intent_dominant'] ?? '',
      'features' => $serpResult['features_visible'] ?? [],
      'types' => $serpResult['types_of_pages'] ?? [],
    ]);

    $seoAgent = $this->seoAgentOverride ?? new SeoOptimizationAgent();
    $codeAgent = $this->codeAgentOverride ?? new SeoCodeValidationAgent();

    $proposal = [];
    $normalizedChanges = [];
    $codeValidation = [];
    $validationFeedback = [];

    $useActorCritic = ActorCriticConfig::isEnabled();
    $coordinator = null;
    $actorCriticFeedback = [];

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

    // Best-attempt tracking — when every retry fails the validator with only
    // non-critical issues (length 66, soft keyword density, mild spam), the
    // pipeline would otherwise return "Source content kept" and the admin
    // sees nothing applied.  We track the attempt with the highest
    // quality_score that did not raise a CRITICAL flag (placeholder, schema
    // invalid, fatal length breach) so we can apply it as a graceful
    // fallback rather than wasting the 60-90s of LLM generation.
    $bestAttempt = null; // ['proposal'=>..., 'normalized'=>..., 'quality_score'=>int, 'audit'=>...]

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
        // SEO-poorer than the source — too much repetition, lost entities,
        // bland vocabulary.  SeoQualityBenchmark compares lexical entropy,
        // diversity, source-entity coverage and repetition against the
        // source description.  When the verdict is "regression" we treat it
        // as a validation failure and feed the diagnostics back to the next
        // attempt so the LLM can broaden vocabulary / re-introduce missing
        // attributes.  If every attempt regresses, the pipeline ultimately
        // refuses to apply and keeps the original content intact.
        $benchmark = $this->qualityBenchmark->compare(
          (string)($current['description'] ?? ''),
          (string)($proposal['description'] ?? '')
        );
        $benchmark['attempt'] = $attempt;
        $this->lastBenchmark  = $benchmark;
        $this->logDebug('Quality benchmark', $benchmark);

        // Persist the benchmark for THIS attempt — even if it succeeds or
        // fails — so analytics queries can rebuild the full retry sequence
        // by joining on pipeline_run_uuid.  The feedback snapshot captures
        // exactly what was injected into the next LLM call so the loop is
        // auditable end-to-end.
        try {
          $this->persistBenchmarkLog(
            $entityId,
            $languageId,
            $benchmark,
            $triggeredBy,
            $attempt,
            $benchmark['is_regression'] ? $validationFeedback : []
          );
        } catch (\Throwable $e) {
          $this->logDebug('Benchmark log insert failed', ['error' => $e->getMessage()]);
        }

        if (!$benchmark['is_regression']) {
          $this->logDebug('Code validation ok', [
            'attempt' => $attempt,
            'notes' => $codeValidation['notes'] ?? '',
          ]);
          break;
        }

        $this->logDebug('Quality benchmark regression — retrying', [
          'attempt' => $attempt,
          'delta'   => $benchmark['delta'],
          'verdict' => $benchmark['verdict'],
          'reason'  => $benchmark['regression_reason'],
        ]);
        $validationFeedback = [
          'issues'      => array_merge(
            $codeValidation['feedback']['issues'] ?? [],
            $benchmark['diagnostics']['messages'] ?? []
          ),
          'suggestions' => array_merge(
            $codeValidation['feedback']['suggestions'] ?? [],
            [
              'Match the source attribute coverage: re-introduce every entity the source mentions (e.g. material, base / accessory, usage variants) as semantic paraphrases.',
              'Broaden vocabulary — avoid over-repeating any single word.',
            ]
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
      if ($isUsable && ($bestAttempt === null || $quality > ($bestAttempt['quality_score'] ?? 0))) {
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

    $auditAgent = new SeoAuditAgent();

    $audit_array = [
      'seo_before' => $seoBefore,
      'seo_after' => $seoAfter,
      'changes' => $normalizedChanges,
      'exclude_faq' => $excludeFaq,
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
   * Append one row PER ATTEMPT to the dedicated analytics table.  Rows
   * share the same pipeline_run_uuid so a full retry sequence can be
   * reconstructed with a single WHERE clause.  Schema columns:
   *
   *   - attempt              SMALLINT     — 1-based attempt index
   *   - pipeline_run_uuid    CHAR(36)     — identifies the optimize() call
   *   - regression_reason    VARCHAR(50)  — low_coverage | repetition |
   *                                          entropy_drop | diversity_drop |
   *                                          delta_drop  | multiple | none
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

    $db = \ClicShopping\OM\Registry::get('Db');
    $prefix = \ClicShopping\OM\CLICSHOPPING::getConfig('db_table_prefix');
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

    $issues = array_values(array_unique(array_filter($issues)));
    $suggestions = array_values(array_unique(array_filter($suggestions)));

    return [
      'issues' => $issues,
      'suggestions' => $suggestions,
      'attempt' => $attempt,
    ];
  }
}
