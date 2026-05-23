<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCriticCoordinator;
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
  private bool $debug;
  private ?SeoOptimizationAgent $seoAgentOverride   = null;
  private ?SeoCodeValidationAgent $codeAgentOverride = null;
  private float $actorCriticThreshold = 0.7;

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
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';
    $this->seoAgentOverride = $seoAgentOverride;
    $this->codeAgentOverride = $codeAgentOverride;
  }

  public function optimize(
    int $entityId,
    int $languageId,
    string $url,
    string $baseUrl,
    string $triggeredBy = 'manual'
  ): array {
    $pipelineStart         = microtime(true);
    $this->llmCallCount    = 0;
    $this->attemptCount    = 0;
    $this->actorCriticUsed = false;

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
        $actorRegistry = new ActorRegistry();
        $criticRegistry = new CriticRegistry();
        new SeoOptimizationActor($this->debug, $actorRegistry, $seoAgent);
        new SeoValidationCritic($this->debug, $criticRegistry, $codeAgent);
        new SeoContentReadinessCritic($this->debug, $criticRegistry);
        $coordinator = new ActorCriticCoordinator($actorRegistry, $criticRegistry);
        $this->actorCriticUsed = true;   // T6.4
      } catch (\Throwable $e) {
        $this->logDebug('Actor-Critic init failed, fallback to legacy', [
          'error' => $e->getMessage(),
        ]);
        $useActorCritic = false;
      }
    }

    for ($attempt = 1; $attempt <= 3; $attempt++) {
      $this->attemptCount = $attempt;   // T6.4
      $array_seo_optimize = [
        'serp_report' => $serpResult,
        'current_content' => $current,
        'entity_name' => $current['name'] ?? '',
        'entity_type' => $this->adapter->getEntityType(),
        'validation_feedback' => $validationFeedback,
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
      ];

      $codeAction = new Action('seo_code_validation', $array_code_validation, $context, 'medium', 30);
      $codeValidation = $codeAgent->executeAction($codeAction)->getOutput();

      if (($codeValidation['approved'] ?? false) === true) {
        $this->logDebug('Code validation ok', [
          'attempt' => $attempt,
          'notes' => $codeValidation['notes'] ?? '',
        ]);
        break;
      }

      $this->logDebug('Code validation failed', [
        'attempt' => $attempt,
        'validation' => $codeValidation,
      ]);

      $validationFeedback = [
        'issues' => $codeValidation['feedback']['issues'] ?? [],
        'suggestions' => $codeValidation['feedback']['suggestions'] ?? [],
        'attempt' => $attempt,
      ];
      $this->logDebug('Validation feedback injected', $validationFeedback);
    }

    if (!($codeValidation['approved'] ?? false)) {
      return [
        'success' => false,
        'error' => 'Code validation failed after retries.',
        'validation' => $codeValidation,
        'proposal' => $proposal,
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
      // T6.4 — visible in UI agentic audit panel
      'pipeline_metrics'=> [
        'llm_calls'         => $this->llmCallCount,
        'total_time_ms'     => (int)((microtime(true) - $pipelineStart) * 1000),
        'attempts'          => $this->attemptCount,
        'actor_critic_used' => $this->actorCriticUsed,
      ],
    ];
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
