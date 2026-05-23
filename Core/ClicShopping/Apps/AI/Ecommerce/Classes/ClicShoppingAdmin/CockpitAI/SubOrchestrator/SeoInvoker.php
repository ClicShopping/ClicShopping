<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator;

use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents\SeoAuditAgent;

/**
 * SeoInvoker
 *
 * Responsible for invoking the existing SEO Agent within the CockpitAI pipeline.
 * (Requirements 9.1, 9.2, 9.3, 9.4, 9.5, 10.4)
 *
 * This class acts as an adapter between the CockpitAI pipeline and the
 * existing SEO Agent infrastructure (Actor-Critic framework).
 *
 * Responsibilities:
 * - Build Context and Action for SEO Agent invocation
 * - Execute SeoAuditAgent when seo_status = 'ANALYZED'
 * - Extract and normalize SEO results for CockpitAI pipeline
 * - Handle timeout and fallback scenarios
 *
 * Design notes:
 * - Uses existing SeoAuditAgent (no new agent needed for v4.23)
 * - Timeout handled by PipelineRunner (5s)
 * - Fallback: mark seo_status='NOT_ANALYZED', continue pipeline
 */
class SeoInvoker
{
  private bool $debug;

  public function __construct()
  {
    $this->debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';
  }

  /**
   * Invoke SEO Agent for product analysis.
   *
   * @param int   $productId   Product to analyze
   * @param int   $languageId  Language for analysis
   * @param array $productData Product data from DataCollector
   * @return array SEO analysis result
   *
   * Result structure:
   * [
   *   'seo_status'  => 'ANALYZED' | 'NOT_ANALYZED',
   *   'seo_score'   => float | null,
   *   'summary'     => string,
   *   'improvements' => array,
   *   'recommendations' => array,
   *   'skipped'     => bool (true if not invoked)
   * ]
   */
  public function invoke(int $productId, int $languageId, array $productData): array
  {
    // Check if SEO analysis is already available
    $seoStatus = $productData['seo_status'] ?? 'NOT_ANALYZED';

    if ($seoStatus !== 'ANALYZED') {
      if ($this->debug) {
        error_log("[SeoInvoker] SEO status is '{$seoStatus}', skipping SEO Agent invocation.");
      }

      return [
        'seo_status' => 'NOT_ANALYZED',
        'seo_score'  => null,
        'summary'    => '',
        'improvements' => [],
        'recommendations' => [],
        'skipped'    => true,
      ];
    }

    try {
      // Build Context for SEO Agent
      $context = $this->buildContext($productId, $languageId, $productData);

      // Build Action for SEO Audit
      $action = $this->buildAction($context, $productData);

      // Execute SEO Agent
      $agent = new SeoAuditAgent();
      $result = $agent->executeAction($action);

      // Extract and normalize results
      return $this->normalizeResult($result);

    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[SeoInvoker] SEO Agent invocation failed: ' . $e->getMessage());
        error_log('[SeoInvoker] Trace: ' . $e->getTraceAsString());
      }

      // Fallback: mark as NOT_ANALYZED and continue
      return [
        'seo_status' => 'NOT_ANALYZED',
        'seo_score'  => null,
        'summary'    => 'SEO analysis failed',
        'improvements' => [],
        'recommendations' => [],
        'error'      => $e->getMessage(),
        'skipped'    => false,
      ];
    }
  }

  /**
   * Build Context object for SEO Agent.
   *
   * Context carries system state and metadata required by the Actor-Critic framework.
   */
  private function buildContext(int $productId, int $languageId, array $productData): Context
  {
    $systemState = [
      'entity_type' => 'product',
      'entity_id'   => $productId,
      'language_id' => $languageId,
      'product_name' => $productData['name'] ?? '',
      'seo_status'  => $productData['seo_status'] ?? 'NOT_ANALYZED',
    ];

    return new Context(
      'CockpitAI_user',  // userId
      $languageId,       // languageId
      $systemState,      // systemState
      [],                // userPreferences
      []                 // environmentalData
    );
  }

  /**
   * Build Action object for SEO Audit Agent.
   *
   * Action encapsulates the request parameters for the agent.
   */
  private function buildAction(Context $context, array $productData): Action
  {
    // Extract SEO before/after states from product data
    // For CockpitAI, we're auditing the current SEO state
    $seoBefore = [
      'seo_score'       => $productData['seo_score_before'] ?? 0,
      'meta_title'      => $productData['meta_title_before'] ?? '',
      'meta_description' => $productData['meta_description_before'] ?? '',
      'thin_content'    => $productData['thin_content_before'] ?? false,
    ];

    $seoAfter = [
      'seo_score'       => $productData['seo_score'] ?? 0,
      'meta_title'      => $productData['meta_title'] ?? '',
      'meta_description' => $productData['meta_description'] ?? '',
      'thin_content'    => $productData['thin_content'] ?? false,
      'schema_org'      => $productData['schema_org'] ?? ['detected' => false, 'types' => []],
      'wordcount_body'  => $productData['wordcount_body'] ?? 0,
    ];

    $changes = $productData['seo_changes'] ?? [];

    $parameters = [
      'seo_before' => $seoBefore,
      'seo_after'  => $seoAfter,
      'changes'    => $changes,
    ];

    return new Action(
      'seo_audit',     // actionType
      $parameters,     // parameters
      $context,        // context
      'medium',        // priority
      5                // estimatedExecutionTime (5s timeout)
    );
  }

  /**
   * Normalize ActionResult from SEO Agent to CockpitAI pipeline format.
   */
  private function normalizeResult($actionResult): array
  {
    $output = $actionResult->getOutput();

    return [
      'seo_status'  => 'ANALYZED',
      'seo_score'   => $output['score_after'] ?? $output['quality_score'] ?? null,
      'summary'     => $output['summary'] ?? '',
      'improvements' => $output['improvements'] ?? [],
      'recommendations' => $output['recommendations'] ?? [],
      'delta'       => $output['delta'] ?? 0,
      'improved'    => $output['improved'] ?? false,
      'thin_content' => $output['thin_content_after'] ?? false,
      'schema_detected' => $output['schema_detected'] ?? false,
      'skipped'     => false,
    ];
  }
}
