<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services\TranslationServiceWrapper;

/**
 * SeoMultilingualOrchestrator
 *
 * Phase 2 entry point of the SEO optimization workflow.  Runs the agentic
 * content optimization once against the source language (English) and then
 * propagates the result to every other configured shop language by translating
 * the textual SEO fields rather than re-running the full SERP + LLM pipeline
 * per locale.
 *
 * Workflow:
 *  1. Resolve the English language (code = 'en') from OM/Language; abort if
 *     the shop has no English locale because every downstream translation is
 *     expected to start from EN.
 *  2. Call SeoAgenticPipeline::runContentOptimization() for EN, which writes
 *     the SERP report, applies the SEO fields to products_description (EN)
 *     and produces the audit + proposal payload.  The FAQ block is excluded
 *     here — it is produced separately by SeoFaqPipeline in Phase 3 with
 *     anti-hallucination grounding checks.
 *  3. For every other enabled language, translate the EN proposal's textual
 *     fields via TranslationServiceWrapper and apply them through
 *     SeoEntityAdapter::applySeoChanges() against the language-specific row
 *     of products_description.
 *
 * For each translated locale the orchestrator persists BOTH a per-language embedding-history
 * record (products_seo_embedding, for the UI's 3-button gating) AND a per-language SERP report
 * row (clic_seo_serp_reports), so per-language readers such as CockpitAI's seo_status report the
 * locale as analyzed. The translated rows are proxies: they re-use the EN audit + score (the
 * translated public page is not re-crawled per locale) with the translated SEO fields applied.
 *
 * Notes:
 *  - DB access goes through Registry::get('Db') (this class lives outside
 *    Core/ClicShopping/AI/, so Doctrine ORM does not apply per AGENTS.md).
 *  - No language identifier is hardcoded: the EN id is resolved from
 *    OM/Language at runtime.
 */
class SeoMultilingualOrchestrator
{
  private string $entityType;
  private SeoEntityAdapter $adapter;
  private TranslationServiceWrapper $translator;
  private SeoEmbedding $embeddingHistory;
  private bool $debug;

  /**
   * Translatable textual fields propagated from the EN proposal to other locales.
   * Keys match SeoEntityAdapter::normalizeChanges() output and the columns
   * declared in SeoEntityAdapter::ENTITY_MAP.
   */
  private const TRANSLATABLE_FIELDS = [
    'meta_title',
    'meta_description',
    'meta_keywords',
    'summary',
    'description',
  ];

  public function __construct(string $entityType = 'product')
  {
    $this->entityType = strtolower(trim($entityType));
    $this->adapter    = new SeoEntityAdapter($this->entityType);
    $this->translator = new TranslationServiceWrapper(
      defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True'
    );
    // Embedding history table differs per entity type so the display hook
    // (ProductsSerp / CategoriesSerp) can still rely on its own table.
    $this->embeddingHistory = new SeoEmbedding($this->entityType . 's_seo_embedding');
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG') && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';
  }

  /**
   * Execute the multilingual SEO optimization for a single entity.
   *
   * @param int    $entityId    Product (or supported entity) primary key.
   * @param string $baseUrl     Shop base URL (HTTP::getShopUrlDomain()).
   * @param string $triggeredBy Origin label persisted alongside the audit
   *                            ('manual', 'ajax', 'cron', ...).
   * @return array{
   *   success: bool,
   *   source_language: array{id:int, code:string},
   *   source_result?: array,
   *   languages: array<string, array{language_id:int, status:string, message?:string, applied_fields?:array}>,
   *   error?: string
   * }
   */
  public function run(int $entityId, string $baseUrl, string $triggeredBy = 'manual'): array
  {
    if (!$this->adapter->isSupported()) {
      return [
        'success' => false,
        'error'   => 'Entity type not supported for SEO optimization.',
        'languages' => [],
        'source_language' => ['id' => 0, 'code' => ''],
      ];
    }

    $languages = $this->getEnabledLanguages();
    if (empty($languages)) {
      return [
        'success' => false,
        'error'   => 'No enabled languages found.',
        'languages' => [],
        'source_language' => ['id' => 0, 'code' => ''],
      ];
    }

    $sourceLang = $languages['en'] ?? null;
    if ($sourceLang === null) {
      return [
        'success' => false,
        'error'   => 'English locale (code = "en") is required as the source language.',
        'languages' => [],
        'source_language' => ['id' => 0, 'code' => ''],
      ];
    }

    $sourceLanguageId = (int)$sourceLang['id'];

    $sourceUrl = $this->buildEntityUrl($entityId, 'en');
    $pipeline  = new SeoAgenticPipeline($this->entityType);
    $sourceResult = $pipeline->runContentOptimization(
      entityId: $entityId,
      languageId: $sourceLanguageId,
      url: $sourceUrl,
      baseUrl: $baseUrl,
      triggeredBy: $triggeredBy
    );

    if (($sourceResult['success'] ?? false) !== true) {
      return [
        'success' => false,
        'error'   => 'Source-language optimization failed: ' . ($sourceResult['error'] ?? 'unknown error'),
        'source_language' => ['id' => $sourceLanguageId, 'code' => 'en'],
        'source_result'   => $sourceResult,
        'languages' => [],
      ];
    }

    $proposal       = $sourceResult['proposal'] ?? [];
    $sourceAudit    = (array)($sourceResult['audit'] ?? []);
    $sourceBenchmark = (array)($sourceResult['benchmark'] ?? []);
    $scoreBefore    = (int)($sourceResult['seo_score_before'] ?? 0);
    $scoreAfter     = (int)($sourceResult['seo_score_after']  ?? 0);
    $sourceApplied  = $this->extractAppliedFields($proposal);

    // Persist the EN row in products_seo_embedding so the display hook can
    // see Phase 2 as completed.  SeoAgenticPipeline writes its agentic audit
    // to products_seo_serp_report but does NOT touch the embedding history.
    $this->embeddingHistory->recordOptimizedReport(
      entityId:      $entityId,
      languageId:    $sourceLanguageId,
      pageType:      $this->entityType,
      url:           $sourceUrl,
      scoreBefore:   $scoreBefore,
      scoreAfter:    $scoreAfter,
      appliedFields: $sourceApplied,
      auditResult:   $sourceAudit,
      triggeredBy:   $triggeredBy,
      benchmark:     $sourceBenchmark
    );

    $perLanguage = [
      'en' => [
        'language_id'    => $sourceLanguageId,
        'status'         => 'applied',
        'applied_fields' => $sourceApplied,
      ],
    ];

    // Persist a per-language SERP report row for the translated locales too (proxy: re-uses the
    // EN audit + score, the same approach already used for the embedding history below). Without  this, CockpitAI's per-language SEO status lookup 
    $reportRepo = new SeoSerpReportRepository();

    foreach ($languages as $code => $info) {
      if ($code === 'en') {
        continue;
      }

      $targetLanguageId = (int)$info['id'];
      $translated = $this->translateProposal($proposal, 'en', $code);

      try {
        $applied = $this->adapter->applySeoChanges(
          entityId:   $entityId,
          languageId: $targetLanguageId,
          changes:    $translated,
          normalize:  true
        );
      } catch (\Throwable $e) {
        $perLanguage[$code] = [
          'language_id' => $targetLanguageId,
          'status'      => 'failed',
          'message'     => $e->getMessage(),
        ];
        $this->logDebug('Translate/apply failed', [
          'language_code' => $code,
          'language_id'   => $targetLanguageId,
          'error'         => $e->getMessage(),
        ]);
        continue;
      }

      if ($applied) {
        // Re-use the EN audit score as a proxy: the public-front page for
        // the translated locale is not re-crawled here (would multiply the
        // duration by the number of languages).  Marking the locale with the
        // same delta keeps the history coherent until a future per-locale
        // re-audit job is introduced.  The benchmark is also EN-derived but
        // surfaced per language so each locale's history modal can render
        // the comparison table.
        $this->embeddingHistory->recordOptimizedReport(
          entityId:      $entityId,
          languageId:    $targetLanguageId,
          pageType:      $this->entityType,
          url:           $this->buildEntityUrl($entityId, $code),
          scoreBefore:   $scoreBefore,
          scoreAfter:    $scoreAfter,
          appliedFields: $translated,
          auditResult:   $sourceAudit,
          triggeredBy:   $triggeredBy,
          benchmark:     $sourceBenchmark
        );

        // Mirror the SERP report into clic_seo_serp_reports for this locale so downstream
        // per-language readers (CockpitAI seo_status) see the locale as analyzed. Proxy values:
        // the score/audit are EN-derived (the translated page is not re-crawled here), the
        // proposed changes are the translated ones. Defensive: never fail the whole run on it.
        try {
          $reportRepo->insert([
            'entity_type'      => $this->entityType,
            'entity_id'        => $entityId,
            'language_id'      => $targetLanguageId,
            'url'              => $this->buildEntityUrl($entityId, $code),
            'serp_source'      => 'translated_from_en',
            'serp_query'       => '',
            'serp_data'        => [],
            'seo_before'       => [],
            'seo_after'        => $translated,
            'proposed_changes' => $translated,
            'audit_result'     => $sourceAudit,
            'summary'          => $sourceAudit['summary'] ?? '',
            'seo_score_before' => $scoreBefore,
            'seo_score_after'  => $scoreAfter,
            'status'           => 'applied',
            'triggered_by'     => $triggeredBy,
            'benchmark'        => $sourceBenchmark,
            'pipeline_metrics' => ['proxy_from_language' => 'en'],
          ]);
        } catch (\Throwable $e) {
          $this->logDebug('Per-locale SERP report insert failed', [
            'language_code' => $code,
            'language_id'   => $targetLanguageId,
            'error'         => $e->getMessage(),
          ]);
        }
      }

      $perLanguage[$code] = [
        'language_id'    => $targetLanguageId,
        'status'         => $applied ? 'applied' : 'skipped',
        'applied_fields' => $applied ? $translated : [],
      ];

      $this->logDebug('Locale propagated', [
        'language_code' => $code,
        'language_id'   => $targetLanguageId,
        'applied'       => $applied,
      ]);
    }

    return [
      'success'         => true,
      'source_language' => ['id' => $sourceLanguageId, 'code' => 'en'],
      'source_result'   => $sourceResult,
      'languages'       => $perLanguage,
    ];
  }

  /**
   * Return enabled languages keyed by their language code.
   *
   * @return array<string, array{id:int, code:string, name:string, status:int}>
   */
  private function getEnabledLanguages(): array
  {
    try {
      $all = Registry::get('Language')->getAll();
    } catch (\Throwable $e) {
      $this->logDebug('Failed to read languages', ['error' => $e->getMessage()]);
      return [];
    }

    $out = [];
    foreach ($all as $code => $row) {
      if ((int)($row['status'] ?? 1) === 0) {
        continue;
      }
      $out[$code] = $row;
    }

    return $out;
  }

  /**
   * Build the shop-front URL of an entity for a given language code.
   * Currently only the product entity is supported; the path mirrors the
   * URL convention already used by the AJAX endpoints.
   */
  private function buildEntityUrl(int $entityId, string $languageCode): string
  {
    return match ($this->entityType) {
      'product' => HTTP::getShopUrlDomain()
        . 'index.php?Products&Description&products_id=' . $entityId
        . '&language=' . urlencode($languageCode),
      default   => HTTP::getShopUrlDomain(),
    };
  }

  /**
   * Translate the translatable subset of an EN proposal to the target locale.
   *
   * Returns the same array shape consumed by SeoEntityAdapter::applySeoChanges()
   * but with each textual field translated.  Non-textual or pass-through keys
   * (primary_keyword, schema_org_json) are intentionally NOT translated:
   * keywords stay in EN so SeoCodeValidationAgent can later evaluate them
   * uniformly, and schema.org JSON-LD is locale-agnostic in its structure.
   */
  private function translateProposal(array $proposal, string $fromLang, string $toLang): array
  {
    $changes = $this->adapter->normalizeChanges($proposal);

    foreach (self::TRANSLATABLE_FIELDS as $field) {
      if (!isset($changes[$field]) || $changes[$field] === '') {
        continue;
      }
      $changes[$field] = $this->translator->translate(
        (string)$changes[$field],
        $fromLang,
        $toLang
      );
    }

    // FAQ is not produced here in Phase 2 — it lives in Phase 3 with its
    // grounding checks.  H2 / schema / primary_keyword are not text fields
    // applied to products_description either, and they are not meaningful
    // as per-language "suggestions" in the history modal.  Stripping them
    // also keeps recordOptimizedReport's metadata payload scalar-only, which
    // ProductsSerp::renderOptimizationMode() relies on when it iterates
    // metadata.suggestions through htmlspecialchars().
    unset($changes['faq'], $changes['h2'], $changes['schema_org_json'], $changes['primary_keyword']);

    return $changes;
  }

  /**
   * Extract the textual subset of the proposal for the source-language entry
   * of the per-language result, so the AJAX caller can surface it in the UI
   * without having to re-read the database.
   */
  private function extractAppliedFields(array $proposal): array
  {
    $out = [];
    foreach (self::TRANSLATABLE_FIELDS as $field) {
      if (isset($proposal[$field]) && $proposal[$field] !== '') {
        $out[$field] = (string)$proposal[$field];
      }
    }
    return $out;
  }

  private function logDebug(string $message, array $context = []): void
  {
    if (!$this->debug) {
      return;
    }
    $payload = $context;
    $payload['message']   = $message;
    $payload['timestamp'] = date('c');
    error_log('SEO_MULTILINGUAL_ORCHESTRATOR ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  }
}
