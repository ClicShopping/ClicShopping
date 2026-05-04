<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
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
   */
  private string $actorId;

  /**
   * Debug flag controlling logging verbosity.
   */
  private bool $debug;

  /**
   * Wrapper around the Large Language Model service.
   */
  private LLMServiceWrapper $llm;

  /**
   * Wrapper around translation service.
   * Used for bidirectional language normalization.
   */
  private TranslationServiceWrapper $translator;

  /**
   * Prompt builder specific to audit tasks.
   */
  private ?AuditPrompts $prompts = null;

  /**
   * Constructor.
   *
   * - Generates unique actor ID.
   * - Enables debug mode if configured.
   * - Instantiates LLM and translation services.
   */
  public function __construct()
  {
    $this->actorId = 'seo_audit_actor_' . uniqid();

    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';

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
   */
  public function executeAction(Action $action): ActionResult
  {
    $start = microtime(true);
    $params = $action->getParameters();

    $before  = $params['seo_before'] ?? [];
    $after   = $params['seo_after']  ?? [];
    $changes = $params['changes']    ?? [];

    $scoreBefore = (int)($before['seo_score'] ?? 0);
    $scoreAfter  = (int)($after['seo_score']  ?? 0);
    $delta       = $scoreAfter - $scoreBefore;
    $improved    = $delta > 0;

    $context      = $action->getContext();
    $languageId   = $context->getLanguageId() ?? 1;
    $languageCode = $this->translator->getLanguageCode($languageId);
    $entityType   = (string)($context->getSystemState()['entity_type'] ?? '');

    $this->prompts = new AuditPrompts($languageCode);

    $summary = '';
    $improvements = [];
    $recommendations = [];

    try {
      /**
       * Step 1: Normalize input to English for LLM coherence.
       */
      $beforeEn = $this->translateAuditData($before, $languageCode);
      $afterEn = $this->translateAuditData($after, $languageCode);
      $changesEn = $this->translateAuditData($changes, $languageCode);

      /**
       * Step 2: Generate AI outputs.
       */
      $summary = $this->generateSummary($beforeEn, $afterEn, $changesEn);
      $improvements = $this->analyzeImprovements($beforeEn, $afterEn, $changesEn);
      $recommendations = $this->generateRecommendations($beforeEn, $afterEn, $changesEn);

      /**
       * Step 3: Translate AI output back to target language if required.
       */
      $translated = $this->translateReport([
        'summary' => $summary,
        'improvements' => $improvements,
        'recommendations' => $recommendations,
      ], $languageCode);

      $summary = $translated['summary'];
      $improvements = $translated['improvements'];
      $recommendations = $translated['recommendations'];

    } catch (\Throwable $e) {

      if ($this->debug) {
        error_log('[SeoAuditAgent] Error: ' . $e->getMessage());
        error_log('[SeoAuditAgent] Trace: ' . $e->getTraceAsString());
      }

      /**
       * Deterministic fallback summary.
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
     * Final normalized output.
     */
    $thinContentBefore = (bool)($before['thin_content']           ?? false);
    $thinContentAfter  = (bool)($after['thin_content']            ?? false);
    $schemaDetected    = (bool)($after['schema_org']['detected']  ?? false);
    $schemaTypes       = $after['schema_org']['types']            ?? [];
    $wordcountAfter    = (int)($after['wordcount_body']           ?? 0);

    // Append thin-content and schema signals to recommendations when present
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

    $metrics = [
      'execution_time_ms' => (int)((microtime(true) - $start) * 1000),
    ];

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
   */
  private function generateSummary(array $before, array $after, array $changes): string
  {
    $prompt = $this->prompts->getSummaryPrompt([
      'before' => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'after' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    return $this->llm->generateResponse($prompt, [
      'maxTokens' => 500,
      'temperature' => 0.4,
    ]);
  }

  /**
   * Generates structured improvements list.
   */
  private function analyzeImprovements(array $before, array $after, array $changes): array
  {
    $prompt = $this->prompts->getImprovementsPrompt([
      'before' => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'after' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    return $this->llm->generateStructuredResponse($prompt, [
      'maxTokens' => 400,
      'temperature' => 0.3,
    ]);
  }

  /**
   * Generates structured recommendations list.
   */
  private function generateRecommendations(array $before, array $after, array $changes): array
  {
    $prompt = $this->prompts->getRecommendationsPrompt([
      'before' => json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'after' => json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    return $this->llm->generateStructuredResponse($prompt, [
      'maxTokens' => 400,
      'temperature' => 0.4,
    ]);
  }

  /**
   * Translates report output to target language if needed.
   *
   * improvements and recommendations are arrays of objects (e.g. [{title, description}])
   * or flat string arrays, depending on the LLM response format.
   * We handle both cases to avoid TypeError when translateBatch receives arrays.
   */
  private function translateReport(array $report, string $targetLang): array
  {
    if ($targetLang === 'en') {
      return $report;
    }

    $report['summary'] = $this->translator->translate((string)$report['summary'], 'en', $targetLang);

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
   */
  private function translateItemList(array $items, string $fromLang, string $toLang): array
  {
    $out = [];

    foreach ($items as $item) {
      if (is_string($item)) {
        // Flat string list
        $out[] = $this->translator->translate($item, $fromLang, $toLang);
      } elseif (is_array($item)) {
        // Structured object — translate every string value inside
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
   * Proposes default SEO audit action.
   */
  public function proposeAction(Context $context): Action
  {
    return new Action('seo_audit', [], $context, 'medium', 60);
  }

  /**
   * Declares audit capability.
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
   * Returns confidence score for audit actions.
   */
  public function evaluateConfidence(Action $action): float
  {
    return 0.7;
  }

  /**
   * Receives critic feedback.
   * Currently not used.
   */
  public function receiveFeedback(Feedback $feedback): void
  {
    // Intentionally empty.
  }

  /**
   * Returns unique actor identifier.
   */
  public function getActorId(): string
  {
    return $this->actorId;
  }
}