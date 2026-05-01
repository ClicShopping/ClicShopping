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
use ClicShopping\AI\Agents\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\Agents\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\Agents\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\Agents\Orchestrator\SubActorCritic\ActorCapability;
use ClicShopping\AI\Agents\Orchestrator\SubActorCritic\Feedback;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoEntityAdapter;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services\LLMServiceWrapper;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services\TranslationServiceWrapper;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Prompts\ValidationPrompts;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Models\ValidationResult;

/**
 * SeoCodeValidationAgent
 *
 * Role:
 * Domain-level SEO code validation agent responsible for analyzing
 * proposed SEO changes for content entities, evaluating quality, coherence,
 * spam indicators, and length constraints.
 *
 * Responsibilities:
 * - Validate meta titles, descriptions, keywords, headings, and content.
 * - Detect spam, keyword stuffing, and unnatural patterns.
 * - Evaluate content quality via LLM-driven structured responses.
 * - Check coherence and language consistency.
 * - Aggregate issues, suggestions, and quality metrics into ValidationResult.
 * - Return normalized ActionResult for Actor-Critic integration.
 *
 * This class encapsulates the intelligence for SEO code/content validation.
 * Orchestration and actor registration are handled externally.
 */

class SeoCodeValidationAgent implements ActorAgentInterface
{
  /**
   * Unique runtime actor identifier.
   */
  private string $actorId;

  /**
   * Debug mode flag for verbose logging.
   */
  private bool $debug;

  /**
   * Wrapper for LLM interactions.
   */
  private LLMServiceWrapper $llm;

  /**
   * Translation service wrapper for input/output normalization.
   */
  private TranslationServiceWrapper $translator;

  /**
   * Prompt templates for validation.
   */
  private ?ValidationPrompts $prompts = null;

  /**
   * Constructor.
   *
   * - Generates unique actor ID.
   * - Determines debug mode from configuration.
   * - Instantiates LLM and translation wrappers.
   */
  public function __construct()
  {
    $this->actorId = 'seo_code_validator_' . uniqid();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG')
      && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';
    $this->llm = new LLMServiceWrapper($this->debug);
    $this->translator = new TranslationServiceWrapper($this->debug);
  }

  /**
   * Executes SEO code validation action.
   *
   * Workflow:
   * 1. Extract entity type and proposed changes.
   * 2. Initialize validation prompts and entity adapter.
   * 3. Perform checks: length, quality, spam, coherence.
   * 4. Aggregate issues, suggestions, approval status, and quality score.
   * 5. Return ActionResult containing ValidationResult and metrics.
   */
  public function executeAction(Action $action): ActionResult
  {
    $start = microtime(true);
    $params = $action->getParameters();

    $entityType = (string)($params['entity_type'] ?? '');
    $changes = $params['changes'] ?? [];
    $context = $action->getContext();
    $languageId = $context->getLanguageId() ?? 1;
    $languageCode = $this->translator->getLanguageCode($languageId);
    $this->prompts = new ValidationPrompts($languageCode);

    $adapter = new SeoEntityAdapter($entityType);

    $errors          = [];
    $issues          = [];
    $suggestions     = [];
    $approved        = true;
    $qualityScore    = 0;
    $isSpam          = false;
    $spamIndicators  = [];
    $coherence       = [];
    $schemaOrgResult = ['passed' => true, 'issues' => [], 'suggestions' => []];

    if (!$adapter->isSupported()) {
      $errors[] = 'Entity type not supported for SEO optimization';
      $approved = false;
    }

    if (empty($changes)) {
      $errors[] = 'No changes proposed';
      $approved = false;
    }

    $lengthCheck = $this->checkLengths($changes);
    $issues = array_merge($issues, $lengthCheck['issues']);
    $suggestions = array_merge($suggestions, $lengthCheck['suggestions']);
    $approved = $approved && $lengthCheck['passed'];

    try {
      $quality = $this->validateQuality($changes, $entityType, $languageCode);
      $qualityScore = (int)($quality['quality_score'] ?? 0);
      $issues = array_merge($issues, $quality['issues'] ?? []);
      $suggestions = array_merge($suggestions, $quality['suggestions'] ?? []);
      if ($qualityScore < 70) {
        $approved = false;
      }
    } catch (\Throwable $e) {
      $errors[] = 'Quality validation failed: ' . $e->getMessage();
      if ($this->debug) {
        error_log('SeoCodeValidationAgent::validateQuality error: ' . $e->getMessage());
      }
    }

    try {
      $spam = $this->detectSpam($changes, $entityType, $languageCode);
      $isSpam = (bool)($spam['is_spam'] ?? false);
      $spamIndicators = $spam['spam_indicators'] ?? [];
      if ($isSpam) {
        $approved = false;
        $issues[] = 'Spam or keyword stuffing detected';
        $suggestions[] = 'Reduce keyword repetition and improve natural language flow';
      }
    } catch (\Throwable $e) {
      $errors[] = 'Spam detection failed: ' . $e->getMessage();
      if ($this->debug) {
        error_log('SeoCodeValidationAgent::detectSpam error: ' . $e->getMessage());
      }
    }

    try {
      $coherence = $this->checkCoherence($changes, $entityType, $languageCode);
      if (($coherence['passed'] ?? true) === false) {
        $approved  = false;
        $issues    = array_merge($issues, $coherence['issues']      ?? []);
        $suggestions = array_merge($suggestions, $coherence['suggestions'] ?? []);
      }
    } catch (\Throwable $e) {
      $errors[] = 'Coherence check failed: ' . $e->getMessage();
      if ($this->debug) {
        error_log('SeoCodeValidationAgent::checkCoherence error: ' . $e->getMessage());
      }
    }

    // ── T3.4 : Schema.org JSON-LD validation ────────────────────────────────
    $schemaJson = (string)($changes['schema_org_json'] ?? '');
    if ($schemaJson !== '') {
      try {
        $schemaOrgResult = $this->validateSchemaOrg($schemaJson, $entityType, $languageCode);
        if (($schemaOrgResult['passed'] ?? true) === false) {
          // Schema issues are warnings — they do not block approval on their own,
          // but they are surfaced as suggestions so the retry loop can improve them.
          $issues      = array_merge($issues,      $schemaOrgResult['issues']      ?? []);
          $suggestions = array_merge($suggestions, $schemaOrgResult['suggestions'] ?? []);
        }
      } catch (\Throwable $e) {
        $errors[] = 'Schema.org validation failed: ' . $e->getMessage();
        if ($this->debug) {
          error_log('SeoCodeValidationAgent::validateSchemaOrg error: ' . $e->getMessage());
        }
      }
    } elseif (in_array($entityType, ['product', 'category'], true)) {
      // Schema block expected but missing
      $issues[]      = 'schema.org JSON-LD block is missing for entity type: ' . $entityType;
      $suggestions[] = match ($entityType) {
        'product'  => 'Generate a schema.org Product JSON-LD block with name, offers (price, availability) and brand.',
        'category' => 'Generate schema.org BreadcrumbList and ItemList JSON-LD blocks for this category.',
        default    => 'Add a relevant schema.org JSON-LD block.',
      };
      // Missing schema does not block approval — surfaced as strong suggestion
    }

    $issues = array_values(array_unique(array_filter($issues)));
    $suggestions = array_values(array_unique(array_filter($suggestions)));

    $metrics = [
      'execution_time_ms' => (int)((microtime(true) - $start) * 1000),
      'hooks_checked' => false,
    ];

    $validationResult = new ValidationResult([
      'approved'        => $approved,
      'quality_score'   => $qualityScore,
      'issues'          => $issues,
      'suggestions'     => $suggestions,
      'is_spam'         => $isSpam,
      'spam_indicators' => $spamIndicators,
      'lengths'         => $lengthCheck,
      'coherence'       => $coherence,
    ]);

    $output = array_merge($validationResult->toArray(), [
      'errors'         => $errors,
      'schema_org'     => $schemaOrgResult,
      'feedback'       => [
        'issues'      => $issues,
        'suggestions' => $suggestions,
      ],
      'notes' => 'LLM validation + length checks + schema.org validation applied.',
    ]);

    if ($this->debug) {
      error_log('SeoCodeValidationAgent::result ' . json_encode([
          'entity_type' => $entityType,
          'approved' => $approved,
          'quality_score' => $qualityScore,
          'is_spam' => $isSpam,
          'issues' => $issues,
          'suggestions' => $suggestions,
          'errors' => $errors,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    return new ActionResult(
      $action->getActionId(),
      $this->actorId,
      $output,
      'seo_code_validation',
      $metrics,
      $action->getContext(),
      $approved ? 'success' : 'failed'
    );
  }

  /**
   * Validates meta title, description, keywords lengths.
   */
  private function checkLengths(array $changes): array
  {
    $issues = [];
    $suggestions = [];

    $metaTitle = (string)($changes['meta_title'] ?? '');
    $metaDescription = (string)($changes['meta_description'] ?? '');
    $metaKeywords = (string)($changes['meta_keywords'] ?? '');

    $titleLen = strlen($metaTitle);
    if ($titleLen < 45 || $titleLen > 70) {
      $issues[] = 'Meta title must be 45-70 characters';
      $suggestions[] = 'Rewrite meta title to fit 45-70 characters while keeping the primary keyword';
    }

    $descLen = strlen($metaDescription);
    if ($descLen < 120 || $descLen > 165) {
      $issues[] = 'Meta description must be 120-165 characters (current: ' . $descLen . ')';
      $suggestions[] = 'Rewrite meta description to 120-165 characters with one concrete benefit and a light CTA';
    }

    if ($metaKeywords === '') {
      $issues[] = 'Meta keywords are missing';
      $suggestions[] = 'Generate 8-12 comma-separated keywords mixing informational and transactional intent';
    }

    return [
      'passed' => $issues === [],
      'issues' => $issues,
      'suggestions' => $suggestions,
      'lengths' => [
        'meta_title' => $titleLen,
        'meta_description' => $descLen,
      ],
    ];
  }

  /**
   * LLM-driven quality validation.
   */
  private function validateQuality(array $changes, string $entityType, string $languageCode): array
  {
    $content = $this->buildValidationContent($changes, $languageCode);
    $primaryKeyword = (string)($changes['primary_keyword'] ?? '');
    $entityName = (string)($changes['summary'] ?? $changes['meta_title'] ?? '');

    $prompt = $this->prompts->getQualityPrompt([
      'entity_type' => $entityType,
      'entity_name' => $entityName,
      'content' => $content,
      'primary_keyword' => $primaryKeyword,
      'topics' => '', // Could be enhanced later
    ]);

    return $this->llm->generateStructuredResponse($prompt, [
      'maxTokens' => 400,
      'temperature' => 0.3,
    ]);
  }

  /**
   * Builds a single string representation of all content fields for validation.
   * Translates to English if required.
   */
  private function buildValidationContent(array $changes, string $languageCode): string
  {
    $parts = [];
    if (!empty($changes['meta_title'])) {
      $parts[] = 'Meta Title: ' . $changes['meta_title'];
    }
    // Note: primary_keyword is now passed as a separate variable to prompts, not in content
    if (!empty($changes['meta_description'])) {
      $parts[] = 'Meta Description: ' . $changes['meta_description'];
    }
    if (!empty($changes['meta_keywords'])) {
      $parts[] = 'Meta Keywords: ' . $changes['meta_keywords'];
    }
    if (!empty($changes['description'])) {
      $parts[] = 'Description: ' . $changes['description'];
    }
    if (!empty($changes['h2']) && is_array($changes['h2'])) {
      $h2Texts = [];
      foreach ($changes['h2'] as $item) {
        // Each item is either a plain string or an associative array with a 'text' key
        if (is_array($item)) {
          $h2Texts[] = (string)($item['text'] ?? '');
        } else {
          $h2Texts[] = (string)$item;
        }
      }
      $h2Texts = array_filter($h2Texts);
      if ($h2Texts) {
        $parts[] = 'H2: ' . implode(' | ', $h2Texts);
      }
    }
    if (!empty($changes['faq']) && is_array($changes['faq'])) {
      $faqText = [];
      foreach ($changes['faq'] as $item) {
        $faqText[] = ($item['q'] ?? '') . ' - ' . ($item['a'] ?? '');
      }
      $parts[] = 'FAQ: ' . implode(' | ', $faqText);
    }

    $content = implode("\n", $parts);

    if ($languageCode !== 'en' && $content !== '') {
      try {
        $content = $this->translator->translate($content, $languageCode, 'en');
      } catch (\Throwable $e) {
        if ($this->debug) {
          error_log('[SeoCodeValidationAgent] Translation to EN failed: ' . $e->getMessage());
        }
      }
    }

    return $content;
  }

  /**
   * LLM-driven spam/keyword stuffing detection.
   */
  private function detectSpam(array $changes, string $entityType, string $languageCode): array
  {
    $content = $this->buildValidationContent($changes, $languageCode);
    $primaryKeyword = (string)($changes['primary_keyword'] ?? '');
    $entityName = (string)($changes['summary'] ?? $changes['meta_title'] ?? '');

    $prompt = $this->prompts->getSpamPrompt([
      'entity_type' => $entityType,
      'entity_name' => $entityName,
      'content' => $content,
      'primary_keyword' => $primaryKeyword,
    ]);

    return $this->llm->generateStructuredResponse($prompt, [
      'maxTokens' => 300,
      'temperature' => 0.2,
    ]);
  }

  /**
   * LLM-driven coherence check for content.
   */
  private function checkCoherence(array $changes, string $entityType, string $languageCode): array
  {
    $content = $this->buildValidationContent($changes, $languageCode);
    $entityName = (string)($changes['summary'] ?? $changes['meta_title'] ?? '');
    $searchIntent = 'transactional'; // Default for products/categories

    $prompt = $this->prompts->getCoherencePrompt([
      'entity_type' => $entityType,
      'entity_name' => $entityName,
      'content' => $content,
      'search_intent' => $searchIntent,
    ]);

    return $this->llm->generateStructuredResponse($prompt, [
      'maxTokens' => 300,
      'temperature' => 0.3,
    ]);
  }

  public function proposeAction(Context $context): Action
  {
    return new Action('seo_code_validation', [], $context, 'medium', 30);
  }

  public function getCapabilities(): array
  {
    return [
      'seo_code_validation' => new ActorCapability('seo_code_validation', 0.6, 'validation', 'competent', ['entity_type', 'changes']),
    ];
  }

  public function evaluateConfidence(Action $action): float
  {
    return 0.6;
  }

  public function receiveFeedback(Feedback $feedback): void
  {
    // No-op
  }

  public function getActorId(): string
  {
    return $this->actorId;
  }

  // ── T3.4 : Schema.org JSON-LD validation ─────────────────────────────────────

  /**
   * Validates a JSON-LD schema.org block proposed by SeoOptimizationAgent.
   *
   * Step 1: Structural check — is the JSON parseable and does it contain @type?
   * Step 2: Required-fields check per entity type (deterministic, no LLM needed).
   * Step 3: LLM semantic check — correctness, completeness, best-practice.
   *
   * Returns:
   *   passed      (bool)   – true when no blocking issue found
   *   issues      (array)  – blocking or notable problems
   *   suggestions (array)  – non-blocking improvement suggestions
   *   score       (int)    – 0-100 quality indicator
   */
  private function validateSchemaOrg(
    string $schemaJson,
    string $entityType,
    string $languageCode
  ): array {
    $result = [
      'passed'      => true,
      'issues'      => [],
      'suggestions' => [],
      'score'       => 100,
    ];

    // ── Step 1 : JSON syntax check ───────────────────────────────────────────
    $decoded = json_decode($schemaJson, true);
    if ($decoded === null) {
      return [
        'passed'      => false,
        'issues'      => ['schema.org JSON-LD is not valid JSON: ' . json_last_error_msg()],
        'suggestions' => ['Ensure the generated JSON-LD is valid JSON before injecting it into the page.'],
        'score'       => 0,
      ];
    }

    // Support both single object and @graph array
    $entries = isset($decoded['@graph']) && is_array($decoded['@graph'])
      ? $decoded['@graph']
      : [$decoded];

    $types = [];
    foreach ($entries as $entry) {
      foreach ((array)($entry['@type'] ?? []) as $t) {
        $types[] = $t;
      }
    }

    if (empty($types)) {
      $result['issues'][]      = 'schema.org JSON-LD is missing @type declaration.';
      $result['suggestions'][] = 'Every schema.org block must declare a @type (e.g. Product, BreadcrumbList).';
      $result['passed']        = false;
      $result['score']         = 20;
    }

    // ── Step 2 : Required fields per entity type ────────────────────────────
    if ($entityType === 'product') {
      $firstEntry  = $entries[0] ?? [];
      $missingKeys = [];

      if (empty($firstEntry['name']))   $missingKeys[] = 'name';
      if (empty($firstEntry['offers'])) $missingKeys[] = 'offers';

      if (!empty($missingKeys)) {
        $result['issues'][]      = 'Product schema is missing required fields: ' . implode(', ', $missingKeys);
        $result['suggestions'][] = 'A Product schema must include at minimum: name, offers (with price, priceCurrency, availability).';
        $result['passed']        = false;
        $result['score']         = max(0, $result['score'] - 30);
      }

      // Check offers sub-fields
      $offers = $firstEntry['offers'] ?? [];
      if (!empty($offers)) {
        $offerEntry    = isset($offers['@type']) ? $offers : ($offers[0] ?? []);
        $missingOffers = [];
        if (empty($offerEntry['price']))         $missingOffers[] = 'price';
        if (empty($offerEntry['priceCurrency'])) $missingOffers[] = 'priceCurrency';
        if (empty($offerEntry['availability']))  $missingOffers[] = 'availability';
        if (!empty($missingOffers)) {
          $result['suggestions'][] = 'Offer block is missing: ' . implode(', ', $missingOffers) . '. Add them for richer snippets.';
          $result['score']         = max(0, $result['score'] - 15);
        }
      }
    }

    if ($entityType === 'category') {
      $foundBreadcrumb = in_array('BreadcrumbList', $types, true);
      if (!$foundBreadcrumb) {
        $result['issues'][]      = 'Category schema is missing BreadcrumbList.';
        $result['suggestions'][] = 'Add a BreadcrumbList schema to improve navigation rich snippets in search results.';
        $result['score']         = max(0, $result['score'] - 20);
      }
    }

    // ── Step 3 : LLM semantic validation ────────────────────────────────────
    // Only call LLM when the JSON is structurally sound (avoid wasting tokens on broken JSON)
    if ($result['passed'] && $result['score'] >= 60) {
      try {
        $prompt   = $this->prompts->getSchemaOrgPrompt([
          'entity_type' => $entityType,
          'schema_json' => mb_substr($schemaJson, 0, 2000), // cap to avoid token overflow
        ]);
        $llmCheck = $this->llm->generateStructuredResponse($prompt, [
          'maxTokens'   => 400,
          'temperature' => 0.2,
        ]);

        $llmIssues      = $llmCheck['issues']      ?? [];
        $llmSuggestions = $llmCheck['suggestions'] ?? [];
        $llmScore       = (int)($llmCheck['score'] ?? 100);

        $result['issues']      = array_merge($result['issues'],      $llmIssues);
        $result['suggestions'] = array_merge($result['suggestions'], $llmSuggestions);
        $result['score']       = (int)(($result['score'] + $llmScore) / 2);

        if (!empty($llmIssues) && ($llmCheck['blocking'] ?? false) === true) {
          $result['passed'] = false;
        }
      } catch (\Throwable $e) {
        if ($this->debug) {
          error_log('[SeoCodeValidationAgent] Schema LLM check failed: ' . $e->getMessage());
        }
        // LLM failure is non-blocking for schema validation
      }
    }

    $result['issues']      = array_values(array_unique(array_filter($result['issues'])));
    $result['suggestions'] = array_values(array_unique(array_filter($result['suggestions'])));

    return $result;
  }
}