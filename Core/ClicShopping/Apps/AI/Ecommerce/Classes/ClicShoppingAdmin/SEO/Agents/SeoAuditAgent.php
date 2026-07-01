<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents;

use ClicShopping\AI\InterfacesAI\ActorAgentInterface;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCapability;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services\LLMServiceWrapper;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services\TranslationServiceWrapper;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Prompts\AuditPrompts;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Models\AuditReport;

/**
 * SeoAuditAgent
 *
 * Role:
 * Domain-level SEO audit agent responsible for analyzing
 * before/after optimization states and generating a structured audit report.
 *
 * Responsibilities:
 * - Compare SEO metrics before and after optimization.
 * - Generate an AI-based summary using an LLM.
 * - Extract structured improvements and recommendations.
 * - Handle multilingual translation (input normalization + output localization).
 * - Return a normalized ActionResult compatible with the Actor-Critic framework.
 *
 * This class contains audit intelligence logic.
 * It does not manage orchestration or registry behavior.
 */
class SeoAuditAgent implements ActorAgentInterface
{
  /**
   * Unique runtime identifier for this agent instance.
   * @var string
   */
  private string $actorId;

  /**
   * Debug flag controlling logging verbosity.
   * @var bool
   */
  private bool $debug;

  /**
   * Wrapper around the Large Language Model service.
   * @var LLMServiceWrapper
   */
  private LLMServiceWrapper $llm;

  /**
   * Wrapper around translation service.
   * Used for bidirectional language normalization.
   * @var TranslationServiceWrapper
   */
  private TranslationServiceWrapper $translator;

  /**
   * Prompt builder specific to audit tasks.
   * @var AuditPrompts|null
   */
  private ?AuditPrompts $prompts = null;

  /**
   * Constructor.
   *
   * - Generates unique actor ID.
   * - Enables debug mode if configured via application constants.
   * - Instantiates LLM and translation services with the debug state.
   */
  public function __construct()
  {
    // Generate a unique actor identifier for log tracing and orchestration identification.
    $this->actorId = 'seo_audit_actor_' . uniqid();

    // Determine debug state based on the specific ClicShopping ChatGPT application constant.
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';

    // Initialize dependencies passing down the global debugging state.
    $this->llm = new LLMServiceWrapper($this->debug);
    $this->translator = new TranslationServiceWrapper($this->debug);
  }

  /**
   * Executes the SEO audit action.
   *
   * Workflow:
   * 1. Extract before/after SEO states and applied changes.
   * 2. Compute score delta and improvement status.
   * 3. Normalize input data to English for LLM processing.
   * 4. Generate summary, improvements, and recommendations.
   * 5. Translate results back to target language if necessary.
   * 6. Build structured AuditReport.
   * 7. Return ActionResult.
   *
   * Any failure in AI generation falls back to deterministic summary.
   * * @param Action $action The action context containing parameters and metadata.
   * @return ActionResult The finalized execution result.
   */
  public function executeAction(Action $action): ActionResult
  {
    $start = microtime(true);
    $params = $action->getParameters();

    // Extract parameters from the incoming action signature.
    $before     = $params['seo_before'] ?? [];
    $after      = $params['seo_after']  ?? [];
    $changes    = $params['changes']    ?? [];
    $excludeFaq = (bool)($params['exclude_faq'] ?? false);
    $benchmark  = $params['benchmark']  ?? [];
    
    // If FAQ handling is delegated elsewhere, strip it from the delta evaluations.
    if ($excludeFaq) {
      unset($changes['faq']);
    }

    // Performance/Score comparison delta analytics.
    $scoreBefore = (int)($before['seo_score'] ?? 0);
    $scoreAfter  = (int)($after['seo_score']  ?? 0);
    $delta       = $scoreAfter - $scoreBefore;
    $improved    = $delta > 0;

    // Resolve system context and language preferences.
    $context      = $action->getContext();
    $languageId   = $context->getLanguageId() ?? 1;
    $languageCode = $this->translator->getLanguageCode($languageId);
    $entityType   = (string)($context->getSystemState()['entity_type'] ?? '');

    // Instantiate localized prompts based on the target system language.
    $this->prompts = new AuditPrompts($languageCode);

    // Initialize report structure properties.
    $summary = '';
    $improvements = [];
    $recommendations = [];

    try {
      // Step 1: Normalize input to English for LLM coherence.
      $beforeEn = $this->translateAuditData($before, $languageCode);
      $afterEn = $this->translateAuditData($after, $languageCode);
      $changesEn = $this->translateAuditData($changes, $languageCode);

      // Step 2: Generate AI outputs via LLM.
      $summary = $this->generateSummary($beforeEn, $afterEn, $changesEn, $excludeFaq, $benchmark);
      $improvements = $this->analyzeImprovements($beforeEn, $afterEn, $changesEn, $excludeFaq, $benchmark);
      $recommendations = $this->generateRecommendations($beforeEn, $afterEn, $changesEn, $excludeFaq, $benchmark);

      // Step 3: Translate AI output back to target language if required.
      $translated = $this->translateReport([
        'summary' => $summary,
        'improvements' => $improvements,
        'recommendations' => $recommendations,
      ], $languageCode);

      $summary = $translated['summary'];
      $improvements = $translated['improvements'];
      $recommendations = $translated['recommendations'];

    } catch (\Throwable $e) {
      // Log errors if debug mode is active.
      if ($this->debug) {
        error_log('[SeoAuditAgent] Error: ' . $e->getMessage());
        error_log('[SeoAuditAgent] Trace: ' . $e->getTraceAsString());
      }

      /**
       * Deterministic fallback summary.
       * Triggered if translation services or LLM pipelines fail.
       */
      $summary = $this->buildSummary($scoreBefore, $scoreAfter, $delta, $changes);
    }

    /**
     * Build structured report object.
     */
    $report = new AuditReport([
      'summary' => $summary,
      'improvements' => $improvements,
      'recommendations' => $recommendations,
      'quality_score' => $scoreAfter,
    ]);

    /**
     * Final normalized output compilation.
     */
    $thinContentBefore = (bool)($before['thin_content']           ?? false);
    $thinContentAfter  = (bool)($after['thin_content']            ?? false);
    $schemaDetected    = (bool)($after['schema_org']['detected']  ?? false);
    $schemaTypes       = $after['schema_org']['types']            ?? [];
    $wordcountAfter    = (int)($after['wordcount_body']           ?? 0);

    // Append thin-content and schema signals to recommendations when present.
    $thinContentWarnings = [];
    if ($thinContentAfter) {
      $thinContentWarnings[] = sprintf(
        'Thin content detected (%d words). A minimum of 150 words of descriptive text is recommended for indexable content.',
        $wordcountAfter
      );
    }
    if (!$schemaDetected && $entityType === 'product') {
      $thinContentWarnings[] = 'No schema.org JSON-LD detected. Add a Product schema to enable Google rich snippets (price, availability, ratings).';
    }
    if (!$schemaDetected && $entityType === 'category') {
      $thinContentWarnings[] = 'No schema.org JSON-LD detected. Add BreadcrumbList and ItemList schemas to improve category page visibility.';
    }
    if (!empty($thinContentWarnings)) {
      $recommendations = array_merge($recommendations, $thinContentWarnings);
    }

    // Merge structured report metrics into the flat output payload array.
    $output = array_merge($report->toArray(), [
      'improved'           => $improved,
      'approved'           => $improved,
      'score_before'       => $scoreBefore,
      'score_after'        => $scoreAfter,
      'delta'              => $delta,
      'changes_applied'    => array_keys($changes),
      'thin_content_after' => $thinContentAfter,
      'schema_detected'    => $schemaDetected,
      'schema_types'       => $schemaTypes,
      'wordcount_after'    => $wordcountAfter,
    ]);

    // Measure operation execution time for agent analytics.
    $metrics = [
      'execution_time_ms' => (int)((microtime(true) - $start) * 1000),
    ];

    // Return the action result payload indicating whether an improvement was hit.
    return new ActionResult(
      $action->getActionId(),
      $this->actorId,
      $output,
      'seo_audit',
      $metrics,
      $action->getContext(),
      $improved ? 'success' : 'partial'
    );
  }

  /**
   * Normalizes audit input data into English.
   * @param array $data Raw parameter metrics array.
   * @param string $languageCode Source language code.
   * @return array Normalized array data in English.
   */
  private function translateAuditData(array $data, string $languageCode): array
  {
    if ($languageCode === 'en') {
      return $data;
    }

    return $this->translateArrayStrings($data, $languageCode);
  }

  /**
   * Recursively translates string values inside arrays.
   * @param array $data Target array with potential nested strings.
   * @param string $languageCode Source language code.
   * @return array Translated structure array.
   */
  private function translateArrayStrings(array $data, string $languageCode): array
  {
    $translated = [];

    foreach ($data as $key => $value) {
      if (is_string($value) && $value !== '') {
        $translated[$key] = $this->translator->translate($value, $languageCode, 'en');
      } elseif (is_array($value)) {
        $translated[$key] = $this->translateArrayStrings($value, $languageCode);
      } else {
        $translated[$key] = $value;
      }
    }

    return $translated;
  }

  /**
   * Generates LLM summary text.
   * @param array $before Metric metadata state prior to changes.
   * @param array $after Metric metadata state following changes.
   * @param array $changes Map of optimizations committed.
   * @param bool $excludeFaq Workflow bypass flag for FAQ sections.
   * @param array $benchmark Extra algorithmic grading signals.
   * @return string Raw text narrative summary from the LLM.
   */
  private function generateSummary(array $before, array $after, array $changes, bool $excludeFaq = false, array $benchmark = []): string
  {
    $prompt = $this->prompts->getSummaryPrompt([
      'before'              => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'after'               => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'changes'             => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'scope_note'          => $this->scopeNote($excludeFaq),
      'preservation_score'  => (float)($benchmark['preservation_score'] ?? 1.0),
      'missing_entities'    => implode('; ', (array)($benchmark['missing_entities'] ?? [])),
      'composite_delta'     => (float)($benchmark['composite_delta'] ?? 0),
      'semantic_regressed'  => !empty($benchmark['semantic_regressed']) ? 'yes' : 'no',
    ]);

    return $this->llm->generateResponse($prompt, [
      'maxTokens' => 500,
      'temperature' => 0.4,
    ]);
  }

  /**
   * Generates structured improvements list.
   * @param array $before Metric metadata state prior to changes.
   * @param array $after Metric metadata state following changes.
   * @param array $changes Map of optimizations committed.
   * @param bool $excludeFaq Workflow bypass flag for FAQ sections.
   * @param array $benchmark Extra algorithmic grading signals.
   * @return array Array list mapping identified optimization paths.
   */
  private function analyzeImprovements(array $before, array $after, array $changes, bool $excludeFaq = false, array $benchmark = []): array
  {
    $prompt = $this->prompts->getImprovementsPrompt([
      'before'              => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'after'               => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'changes'             => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'scope_note'          => $this->scopeNote($excludeFaq),
      'preservation_score'  => (float)($benchmark['preservation_score'] ?? 1.0),
      'missing_entities'    => implode('; ', (array)($benchmark['missing_entities'] ?? [])),
      'composite_delta'     => (float)($benchmark['composite_delta'] ?? 0),
      'semantic_regressed'  => !empty($benchmark['semantic_regressed']) ? 'yes' : 'no',
    ]);

    return $this->llm->generateStructuredResponse($prompt, [
      'maxTokens' => 400,
      'temperature' => 0.3,
    ]);
  }

  /**
   * Generates structured recommendations list.
   * @param array $before Metric metadata state prior to changes.
   * @param array $after Metric metadata state following changes.
   * @param array $changes Map of optimizations committed.
   * @param bool $excludeFaq Workflow bypass flag for FAQ sections.
   * @param array $benchmark Extra algorithmic grading signals.
   * @return array Array list containing further structural proposals.
   */
  private function generateRecommendations(array $before, array $after, array $changes, bool $excludeFaq = false, array $benchmark = []): array
  {
    $prompt = $this->prompts->getRecommendationsPrompt([
      'before'              => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'after'               => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'changes'             => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'scope_note'          => $this->scopeNote($excludeFaq),
      'preservation_score'  => (float)($benchmark['preservation_score'] ?? 1.0),
      'missing_entities'    => implode('; ', (array)($benchmark['missing_entities'] ?? [])),
      'composite_delta'     => (float)($benchmark['composite_delta'] ?? 0),
      'semantic_regressed'  => !empty($benchmark['semantic_regressed']) ? 'yes' : 'no',
    ]);

    return $this->llm->generateStructuredResponse($prompt, [
      'maxTokens' => 400,
      'temperature' => 0.4,
    ]);
  }

  /**
   * Build the phase-scope hint injected as {{scope_note}} into every audit
   * prompt. When Phase 2 runs with exclude_faq, the LLM must NOT recommend
   * adding a FAQ (Phase 3 handles it separately with grounding checks) and
   * must NOT count the missing FAQ as a regression.
   * @param bool $excludeFaq Flag controlling notice inclusion.
   * @return string Constraint rules instructions for the prompt.
   */
  private function scopeNote(bool $excludeFaq): string
  {
    return $excludeFaq
      ? 'IMPORTANT: this is Phase 2 of a multi-phase workflow — FAQ generation is INTENTIONALLY out of scope and will be handled by Phase 3 with anti-hallucination grounding checks. Do NOT recommend adding a FAQ section, do NOT mention the absence of a FAQ as an issue, do NOT list FAQ-related items in improvements or recommendations.'
      : '';
  }

  /**
   * Translates report output to target language if needed.
   *
   * Improvements and recommendations are arrays of objects (e.g. [{title, description}])
   * or flat string arrays, depending on the LLM response format.
   * We handle both cases to avoid TypeError when translateBatch receives arrays.
   * @param array $report The raw English analysis maps.
   * @param string $targetLang Target locale indicator string.
   * @return array Translated audit content structure.
   */
  private function translateReport(array $report, string $targetLang): array
  {
    if ($targetLang === 'en') {
      return $report;
    }

    // Convert the primary narrative block back to the application language context.
    $report['summary'] = $this->translator->translate((string)$report['summary'], 'en', $targetLang);

    // Process lists iteratively to safely secure nested keys or strings.
    if (!empty($report['improvements']) && is_array($report['improvements'])) {
      $report['improvements'] = $this->translateItemList($report['improvements'], 'en', $targetLang);
    }

    if (!empty($report['recommendations']) && is_array($report['recommendations'])) {
      $report['recommendations'] = $this->translateItemList($report['recommendations'], 'en', $targetLang);
    }

    return $report;
  }

  /**
   * Translates a list that may contain either plain strings or associative arrays.
   * Handles both formats returned by generateStructuredResponse().
   * @param array $items Array collection of string or object tokens.
   * @param string $fromLang Source language.
   * @param string $toLang Target language.
   * @return array Localized items collection.
   */
  private function translateItemList(array $items, string $fromLang, string $toLang): array
  {
    $out = [];

    foreach ($items as $item) {
      if (is_string($item)) {
        // Flat string list processing
        $out[] = $this->translator->translate($item, $fromLang, $toLang);
      } elseif (is_array($item)) {
        // Structured object — translate every string value inside dynamically
        $translated = [];
        foreach ($item as $key => $value) {
          $translated[$key] = is_string($value) && $value !== ''
            ? $this->translator->translate($value, $fromLang, $toLang)
            : $value;
        }
        $out[] = $translated;
      } else {
        $out[] = $item;
      }
    }

    return $out;
  }

  /**
   * Deterministic fallback summary builder.
   * Called when LLM generation fails. Incorporates thin-content and schema signals.
   * @param int $before Score before.
   * @param int $after Score after.
   * @param int $delta Score arithmetic discrepancy.
   * @param array $changes Target configurations handled.
   * @return string Formatted static audit evaluation summary text.
   */
  private function buildSummary(int $before, int $after, int $delta, array $changes): string
  {
    if ($delta > 0) {
      $base = sprintf(
        'SEO score improved: %d → %d (+%d pts). Applied changes: %s.',
        $before,
        $after,
        $delta,
        implode(', ', array_keys($changes))
      );
    } elseif ($delta === 0) {
      $base = sprintf(
        'SEO score stable: %d/100. Proposed changes without measurable gain.',
        $after
      );
    } else {
      $base = sprintf(
        'SEO score decreased: %d → %d (%d pts). Review applied changes.',
        $before,
        $after,
        $delta
      );
    }

    return $base;
  }

  /**
   * Proposes default SEO audit action configuration options.
   * @param Context $context Core pipeline execution state information.
   * @return Action Standard ready-to-run scheduled Action instance.
   */
  public function proposeAction(Context $context): Action
  {
    return new Action('seo_audit', [], $context, 'medium', 60);
  }

  /**
   * Declares audit capability parameters for registration.
   * @return array Capability mapping profile dictionary arrays.
   */
  public function getCapabilities(): array
  {
    return [
      'seo_audit' => new ActorCapability(
        'seo_audit',
        0.7,
        'seo',
        'competent',
        ['seo_before', 'seo_after', 'changes']
      ),
    ];
  }

  /**
   * Returns confidence score for executing audit actions.
   * @param Action $action Evaluation criteria object.
   * @return float Reliability score factor index.
   */
  public function evaluateConfidence(Action $action): float
  {
    return 0.7;
  }

  /**
   * Receives critic feedback.
   * Currently not used in this agent configuration module.
   * @param Feedback $feedback Performance critique evaluation data maps.
   * @return void
   */
  public function receiveFeedback(Feedback $feedback): void
  {
    // Intentionally empty.
  }

  /**
   * Returns unique actor identifier.
   * @return string Runtime actor identification name tracker string.
   */
  public function getActorId(): string
  {
    return $this->actorId;
  }
}