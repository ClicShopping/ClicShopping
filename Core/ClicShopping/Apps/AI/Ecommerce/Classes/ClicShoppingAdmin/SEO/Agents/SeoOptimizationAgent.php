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
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Prompts\ContentGenerationPrompts;

/**
 * SeoOptimizationAgent
 *
 * Role:
 * Domain-level SEO content generation agent responsible for producing
 * optimized SEO proposals for product and category pages.
 *
 * Responsibilities:
 * - Generate meta title, meta description, meta keywords.
 * - Generate enriched description, summary.
 * - Generate FAQ and H2/H3 heading structures.
 * - Generate schema.org JSON-LD (Product or Category).
 * - Normalize and extract the primary keyword.
 * - Integrate SERP report signals (intent, topics, keywords, competitors).
 * - Handle multilingual input/output via translation wrappers.
 * - Return a normalized ActionResult compatible with the Actor-Critic framework.
 *
 * This class contains SEO content generation intelligence.
 * Orchestration and actor registration are handled by SeoOptimizationActor.
 */
class SeoOptimizationAgent implements ActorAgentInterface
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
   * Translation service wrapper for input/output normalization.
   */
  private TranslationServiceWrapper $translator;

  /**
   * Prompt builder for content generation tasks.
   */
  private ?ContentGenerationPrompts $prompts = null;

  /**
   * Constructor.
   */
  public function __construct()
  {
    $this->actorId = 'seo_optimization_agent_' . uniqid();

    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG')
      && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';

    $this->llm        = new LLMServiceWrapper($this->debug);
    $this->translator = new TranslationServiceWrapper($this->debug);
  }

  /**
   * Executes the SEO optimization action.
   *
   * Workflow:
   * 1. Extract entity data and SERP signals from action parameters.
   * 2. Initialize prompt builder for the target language.
   * 3. Generate all SEO content fields via LLM.
   * 4. Build and return a normalized ActionResult.
   */
  public function executeAction(Action $action): ActionResult
  {
    $start  = microtime(true);
    $params = $action->getParameters();

    $serpReport         = $params['serp_report']         ?? [];
    $current            = $params['current_content']     ?? [];
    $entityName         = (string)($params['entity_name']         ?? '');
    $entityType         = (string)($params['entity_type']         ?? 'product');
    $validationFeedback = $params['validation_feedback'] ?? [];
    // Phase 2 / Phase 3 split: when true, FAQ generation is skipped here
    // and produced separately by SeoFaqPipeline with grounding / hallucination checks.
    $excludeFaq         = (bool)($params['exclude_faq']   ?? false);

    $context    = $action->getContext();
    $languageId = $context->getLanguageId() ?? 1;
    $langCode   = $this->translator->getLanguageCode($languageId);

    $this->prompts = new ContentGenerationPrompts($langCode);

    // Get language name dynamically from OM/Language via TranslationServiceWrapper
    $outputLanguage = $this->translator->getLanguageName($langCode);

    // --- SERP signals ---
    $intent           = (string)($serpReport['intent_dominant']     ?? 'transactional');
    // Filter out meta-SEO noise (LLM-hallucinated topics like
    // "Search Engine Optimization techniques") so they never leak into the
    // generation prompt.  See isMetaSeoTerm() for the deny list.
    $topicsArr        = array_values(array_filter(
      $serpReport['topics'] ?? [],
      fn($t): bool => is_string($t) && $t !== '' && !$this->isMetaSeoTerm($t)
    ));
    $keywordsArr      = array_values(array_filter(
      $serpReport['keywords'] ?? [],
      fn($k): bool => is_string($k) && $k !== '' && !$this->isMetaSeoTerm($k)
    ));
    $topics           = implode(', ', $topicsArr);
    $keywords         = implode(', ', $keywordsArr);
    $peopleAlsoAsk    = implode('; ', $serpReport['people_also_ask'] ?? []);
    $aiOverview       = (string)($serpReport['ai_overview']['summary'] ?? '');
    $competitorTitles = $this->extractCompetitorTitles($serpReport);
    $competitorSnips  = $this->extractCompetitorSnippets($serpReport);

    // --- Entity signals ---
    $primaryKeyword  = $this->resolvePrimaryKeyword($current, $keywords, $entityName);
    $productBrand    = (string)($current['brand']     ?? '');
    $productModel    = (string)($current['model']     ?? '');
    // products.products_price is DECIMAL(15,4) → comes through as
    // "200.0000" which the LLM faithfully repeats in meta descriptions.
    // Normalise to two decimals for human-facing copy; the schema.org
    // numeric field is regenerated separately from the raw value.
    $rawPrice        = (string)($current['price']     ?? '');
    $productPrice    = ($rawPrice !== '' && is_numeric($rawPrice))
      ? number_format((float)$rawPrice, 2, '.', '')
      : $rawPrice;
    $productCurrency = (string)($current['currency']  ?? 'EUR');
    $productStock    = (string)($current['quantity']  ?? '');
    $productSku      = (string)($current['sku']       ?? $current['model'] ?? '');
    $productUrl      = (string)($current['url']       ?? '');
    $productImage    = (string)($current['image']     ?? '');
    $entityDesc      = (string)($current['description'] ?? '');
    $availability    = ((int)($current['quantity'] ?? 0) > 0) ? 'InStock' : 'OutOfStock';

    // Category-specific signals
    $categoryUrl    = (string)($current['url']           ?? '');
    $baseUrl        = (string)($current['base_url']      ?? '');
    $productCount   = (string)($current['product_count'] ?? '');
    $breadcrumbPath = json_encode($current['breadcrumb_path'] ?? []);
    $topProducts    = json_encode($current['top_products']    ?? []);

    // Validation feedback as string
    $feedbackStr = $this->formatValidationFeedback($validationFeedback);

    // Shared prompt variables
    $vars = [
      'entity_name'          => $entityName,
      'entity_type'          => $entityType,
      'primary_keyword'      => $primaryKeyword,
      'search_intent'        => $intent,
      'topics'               => $topics,
      'keywords'             => $keywords,
      'people_also_ask'      => $peopleAlsoAsk,
      'ai_overview_insights' => $aiOverview,
      'competitor_titles'    => $competitorTitles,
      'competitor_snippets'  => $competitorSnips,
      'product_brand'        => $productBrand,
      'product_model'        => $productModel,
      'product_price'        => $productPrice,
      'product_currency'     => $productCurrency,
      'product_stock'        => $productStock,
      'product_sku'          => $productSku,
      'product_url'          => $productUrl,
      'product_image'        => $productImage,
      'entity_description'   => $entityDesc,
      'validation_feedback'  => $feedbackStr,
      'availability'         => $availability,
      'category_url'         => $categoryUrl,
      'base_url'             => $baseUrl,
      'product_count'        => $productCount,
      'breadcrumb_path'      => $breadcrumbPath,
      'top_products'         => $topProducts,
      'description'          => $entityDesc,
      'output_language'      => $outputLanguage,
    ];

    try {
      $metaTitle   = $this->generateMetaTitle($vars);
      $metaDesc    = $this->generateMetaDescription($vars);
      $metaKws     = $this->generateMetaKeywords($vars);
      $summary     = $this->generateSummary($vars);
      $description = $this->generateDescription($vars);
      $faq         = $excludeFaq ? [] : $this->generateFaq($vars);
      $h2          = $this->generateH2($vars);
      $schema      = $this->generateSchema($vars, $entityType);

    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[SeoOptimizationAgent] Generation error: ' . $e->getMessage());
        error_log('[SeoOptimizationAgent] Trace: ' . $e->getTraceAsString());
      }

      $metaTitle   = $entityName;
      $metaDesc    = '';
      $metaKws     = $primaryKeyword;
      $summary     = '';
      $description = $entityDesc;
      $faq         = [];
      $h2          = [];
      $schema      = '';
    }

    $output = [
      'meta_title'       => $metaTitle,
      'meta_description' => $metaDesc,
      'meta_keywords'    => $metaKws,
      'summary'          => $summary,
      'description'      => $description,
      'primary_keyword'  => $primaryKeyword,
      'faq'              => $faq,
      'h2'               => $h2,
      'schema_org_json'  => $schema,
      'approved'         => ($metaTitle !== '' && $metaDesc !== ''), // SeoCodeValidationAgent makes final decision
    ];

    $metrics = [
      'execution_time_ms' => (int)((microtime(true) - $start) * 1000),
    ];

    return new ActionResult(
      $action->getActionId(),
      $this->actorId,
      $output,
      'seo_proposal',
      $metrics,
      $action->getContext(),
      'success'
    );
  }

  // -------------------------------------------------------------------------
  // Generation helpers
  // -------------------------------------------------------------------------

  private function generateMetaTitle(array $vars): string
  {
    // Hard-strip identifiers and numeric facts from the meta title prompt:
    // model numbers, SKU codes, price and currency NEVER belong in a SERP
    // title — they waste characters on a 60-char budget and produce noise
    // like "Ricardo Set of 2 Glasses REF-1526836441" (74 chars), busting
    // the 30-65 SEO length rule.  Brand + product name + one differentiator
    // is enough for ranking and CTR.
    $titleVars = $vars;
    $titleVars['product_price']    = '';
    $titleVars['product_currency'] = '';
    $titleVars['product_model']    = '';
    $titleVars['product_sku']      = '';
    $titleVars['product_stock']    = '';

    return trim($this->llm->generateResponse(
      $this->prompts->getMetaTitlePrompt($titleVars),
      ['maxTokens' => 80, 'temperature' => 0.2]
    ));
  }

  private function generateMetaDescription(array $vars): string
  {
    // Same hard-strip as the meta title: SKU, model, price, currency and
    // stock counts have no place in the SERP description either.  "Under
    // 50.00 Shop now" with a hallucinated price is worse than no price at
    // all — and Google often rewrites descriptions that contain shaky
    // numeric claims, so we lose the field anyway.
    $descVars = $vars;
    $descVars['product_price']    = '';
    $descVars['product_currency'] = '';
    $descVars['product_model']    = '';
    $descVars['product_sku']      = '';
    $descVars['product_stock']    = '';

    return trim($this->llm->generateResponse(
      $this->prompts->getMetaDescriptionPrompt($descVars),
      ['maxTokens' => 120, 'temperature' => 0.3]
    ));
  }

  private function generateMetaKeywords(array $vars): string
  {
    // Same hard-strip as title / description: keywords mentioning SKUs
    // ("ricardo REF-1526836441") or inventing numeric facts ("350ml" for a
    // 420ml product) trigger spam detection downstream and pollute the
    // search index.  Strip the numeric / identifier vars so the LLM only
    // works with brand + name + topics.
    $kwVars = $vars;
    $kwVars['product_price']    = '';
    $kwVars['product_currency'] = '';
    $kwVars['product_stock']    = '';
    $kwVars['product_sku']      = '';
    $kwVars['product_model']    = '';

    return trim($this->llm->generateResponse(
      $this->prompts->getMetaKeywordsPrompt($kwVars),
      ['maxTokens' => 120, 'temperature' => 0.2]
    ));
  }

  private function generateSummary(array $vars): string
  {
    // Summary is shown alongside the description on the product page; same
    // anti-hallucination policy as the meta and description blocks.
    $sumVars = $vars;
    $sumVars['product_price']    = '';
    $sumVars['product_currency'] = '';
    $sumVars['product_stock']    = '';
    $sumVars['product_sku']      = '';
    $sumVars['product_model']    = '';

    return trim($this->llm->generateResponse(
      $this->prompts->getSummaryPrompt($sumVars),
      ['maxTokens' => 120, 'temperature' => 0.2]
    ));
  }

  private function generateDescription(array $vars): string
  {
    // Hard-strip identifiers and numeric facts from the description prompt:
    // price, currency, stock count, SKU and model number belong in
    // structured data (schema.org Offer) and dedicated UI fields, never in
    // the description text.  Even with the prompt forbidding them, leaving
    // them in the vars block is enough for the LLM to weave "priced at
    // 200.00 / model REF-…" sentences into the prose.  Removing them
    // from the input eliminates the temptation entirely.
    $descVars = $vars;
    $descVars['product_price']    = '';
    $descVars['product_currency'] = '';
    $descVars['product_stock']    = '';
    $descVars['product_sku']      = '';
    $descVars['product_model']    = '';

    return trim($this->llm->generateResponse(
      $this->prompts->getEnrichedDescriptionPrompt($descVars),
      ['maxTokens' => 600, 'temperature' => 0.35]
    ));
  }

  private function generateFaq(array $vars): array
  {
    try {
      return $this->llm->generateStructuredResponse(
        $this->prompts->getFaqPrompt($vars),
        ['maxTokens' => 500, 'temperature' => 0.3, 'cache' => false]
      );
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[SeoOptimizationAgent] FAQ generation failed: ' . $e->getMessage());
      }
      return [];
    }
  }

  /**
   * Generate the FAQ block in isolation for Phase 3.
   *
   * Phase 2 emits SEO content with exclude_faq = true.  Phase 3 then calls
   * this method from SeoFaqPipeline to produce the FAQ candidate that will
   * be screened by AnswerGroundingVerifier and HallucinationDetector before
   * being persisted via FaqRepository.
   *
   * The caller is responsible for building the $vars array (entity_name,
   * product_*, primary_keyword, etc.) — mirror the shape used internally
   * by executeAction().  Returns an empty array on failure so the calling
   * pipeline can decide whether to retry or accept the empty result rather
   * than persist a hallucinated FAQ.
   *
   * @param array  $vars     Prompt variables consumed by ContentGenerationPrompts.
   * @param string $langCode Target language code (e.g. 'en', 'fr').
   * @return array<int, array{q:string,a:string}>
   */
  public function generateFaqForVars(array $vars, string $langCode): array
  {
    $this->prompts = new ContentGenerationPrompts($langCode);
    return $this->generateFaq($vars);
  }

  private function generateH2(array $vars): array
  {
    try {
      $items = $this->llm->generateStructuredResponse(
        $this->prompts->getH2Prompt($vars),
        ['maxTokens' => 500, 'temperature' => 0.3, 'cache' => false]
      );
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[SeoOptimizationAgent] H2 generation failed: ' . $e->getMessage());
      }
      return [];
    }

    if (!is_array($items)) {
      return [];
    }

    return array_values(array_map(function (mixed $item): array {
      if (!is_array($item)) {
        $item = ['text' => (string)$item];
      }
      $text = (string)($item['text'] ?? '');
      return [
        'level'  => (int)($item['level'] ?? 2),
        'text'   => $text,
        'anchor' => $item['anchor'] ?? $this->slugify($text),
      ];
    }, array_filter($items, fn($item) => !empty($item))));
  }

  private function generateSchema(array $vars, string $entityType): string
  {
    if ($entityType === 'product') {
      $prompt = $this->prompts->getSchemaProductPrompt($vars);
    } elseif ($entityType === 'category') {
      $prompt = $this->prompts->getSchemaCategoryPrompt($vars);
    } else {
      return '';
    }

    $raw   = $this->llm->generateResponse($prompt, ['maxTokens' => 600, 'temperature' => 0.2]);
    $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
    $clean = preg_replace('/\s*```$/', '', $clean);

    json_decode($clean);
    return json_last_error() === JSON_ERROR_NONE ? $clean : '';
  }

  // -------------------------------------------------------------------------
  // Signal extraction helpers
  // -------------------------------------------------------------------------

  private function resolvePrimaryKeyword(array $current, string $keywords, string $entityName): string
  {
    $notes = $current['notes'] ?? '';
    if (is_array($notes)) {
      $notes = implode(' ', $notes);
    }

    if (preg_match('/primary[_\s]keyword[:\s]+([^\n,;]+)/i', (string)$notes, $m)) {
      $kw = trim($m[1]);
      if ($kw !== '' && !$this->isMetaSeoTerm($kw)) {
        return $kw;
      }
    }

    // When WebSearch returns no results, the SERP topic-extraction LLM tends
    // to hallucinate meta-SEO words ("SEO", "content marketing", "keyword
    // research"…) as keywords.  Picking one of those as the primary keyword
    // contaminates every downstream prompt.  Filter them out before falling
    // back to the entity name, which is always a safer anchor.
    $parts = array_filter(
      array_map('trim', explode(',', $keywords)),
      fn(string $kw): bool => $kw !== '' && !$this->isMetaSeoTerm($kw)
    );
    if (!empty($parts)) {
      return reset($parts);
    }

    return strtolower($entityName);
  }

  /**
   * Returns true when the candidate keyword is a generic meta-SEO term that
   * should never be promoted as a primary keyword.  Match is case- and
   * whitespace-insensitive and looks at substrings so multi-word variants
   * ("Search Engine Optimization (SEO) techniques", "Content marketing
   * strategies") are caught as well.
   */
  private function isMetaSeoTerm(string $keyword): bool
  {
    $needle = strtolower(trim($keyword));
    if ($needle === '') {
      return false;
    }
    static $banned = [
      'seo',
      'search engine optimization',
      'content marketing',
      'keyword research',
      'on-page',
      'off-page',
      'user intent',
      'serp',
      'meta description',
      'meta title',
    ];
    foreach ($banned as $term) {
      if (str_contains($needle, $term)) {
        return true;
      }
    }
    return false;
  }

  private function extractCompetitorTitles(array $serpReport): string
  {
    $insights = $serpReport['competitor_insights'] ?? [];
    $titles   = [];
    foreach ($insights as $item) {
      if (!empty($item['title'])) {
        $titles[] = $item['title'];
      }
    }
    return implode(' | ', array_slice($titles, 0, 5));
  }

  private function extractCompetitorSnippets(array $serpReport): string
  {
    $insights = $serpReport['competitor_insights'] ?? [];
    $snips    = [];
    foreach ($insights as $item) {
      if (!empty($item['snippet'])) {
        $snips[] = $item['snippet'];
      }
    }
    return implode(' | ', array_slice($snips, 0, 3));
  }

  private function formatValidationFeedback(array $feedback): string
  {
    if (empty($feedback)) {
      return '';
    }
    $issues = $feedback['issues'] ?? (is_array($feedback) ? $feedback : []);
    if (empty($issues)) {
      return '';
    }
    return 'Previous issues to fix: ' . implode('; ', array_slice($issues, 0, 5));
  }

  // -------------------------------------------------------------------------
  // Utility helpers
  // -------------------------------------------------------------------------

  private function parseJsonArray(string $raw, array $default): array
  {
    $clean   = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
    $clean   = preg_replace('/\s*```$/', '', $clean);
    $decoded = json_decode($clean, true);
    return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $default;
  }

  private function slugify(string $text): string
  {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
  }

  // -------------------------------------------------------------------------
  // ActorAgentInterface — orchestration stubs
  // -------------------------------------------------------------------------

  public function proposeAction(Context $context): Action
  {
    return new Action('seo_optimize', [], $context, 'high', 90);
  }

  public function getCapabilities(): array
  {
    return [
      'seo_optimize' => new ActorCapability(
        'seo_optimize',
        0.8,
        'seo',
        'expert',
        ['serp_report', 'current_content']
      ),
    ];
  }

  public function evaluateConfidence(Action $action): float
  {
    return 0.8;
  }

  public function receiveFeedback(Feedback $feedback): void
  {
    // Feedback handled at the Actor layer (SeoOptimizationActor).
  }

  public function getActorId(): string
  {
    return $this->actorId;
  }
}
