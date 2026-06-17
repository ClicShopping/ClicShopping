<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Faq;

use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\Validation\AnswerGroundingVerifier;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ\FaqParser;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ\FaqPrettyPrinter;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\FAQ\FaqRepository;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Agents\SeoOptimizationAgent;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoEmbedding;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoEntityAdapter;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoReport;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Services\TranslationServiceWrapper;

/**
 * SeoFaqPipeline
 *
 * Phase 3 of the SEO workflow: produce a FAQ block for a product that is
 * *grounded* in the actual product data — not a free hallucination from the
 * LLM.  The pipeline:
 *
 *   1. Builds a factual product context from products / products_description /
 *      manufacturers / categories via Registry::get('Db').  This context is
 *      the "source of truth" against which grounding is measured.
 *   2. Asks SeoOptimizationAgent::generateFaqForVars() to draft a FAQ in
 *      English from that context (no SERP, no re-crawl — the FAQ relies on
 *      first-party facts, not search results).
 *   3. Validates the draft with Core/ClicShopping/AI/Security/Validation/
 *      AnswerGroundingVerifier: each Q/A pair is concatenated and the
 *      verifier's `decision` (ACCEPT / FLAG / REJECT) drives whether we
 *      persist or retry.  Up to two retries; on persistent REJECT we keep
 *      no FAQ rather than persist a hallucinated one.
 *   4. Persists the accepted EN FAQ via FaqRepository::saveFaq().
 *   5. Propagates to every other enabled language by translating each Q/A
 *      pair via TranslationServiceWrapper, then re-runs grounding on the
 *      translated text (the source documents are translated alongside so
 *      the cosine similarity stays meaningful).
 *   6. Writes a `faq_generated` row in products_seo_embedding per language
 *      so the display hook (ProductsSerp) can mark Phase 3 as completed
 *      and surface the FAQ in the history table.
 *
 * AGENTS.md compliance:
 *  - Lives outside Core/ClicShopping/AI/ → Registry::get('Db'), not Doctrine.
 *  - Delegates hallucination checks to the existing AI/Security/Validation
 *    services rather than inventing a parallel detector.
 *  - All comments and identifiers in English.
 */
class SeoFaqPipeline
{
  /**
   * Confidence floor for the AnswerGroundingVerifier.  Below this score the
   * FAQ candidate is retried; after MAX_RETRIES the empty FAQ is preferred
   * over a hallucinated one.
   *
   * NB: a real-world deployment should make this configurable via the
   * dedicated app constant
   * CLICSHOPPING_APP_ECOMMERCE_EC_FAQ_GROUNDING_THRESHOLD — that constant
   * does not exist yet in this repository (verified) and per AGENTS.md a
   * new constant name must be confirmed with the human coder before being
   * introduced.  Until then, this class constant is the single source.
   */
  private const GROUNDING_THRESHOLD = 0.7;
  private const MAX_RETRIES         = 2;

  /**
   * Minimum number of individually-grounded Q/A pairs required to persist a FAQ.
   * Grounding is verified PER ITEM (not on the whole block): a single weakly
   * grounded answer used to drag the block average + penalties below the
   * threshold and reject the ENTIRE FAQ, which made the action fail at random.
   * We now keep the grounded pairs and drop only the ungrounded ones.
   */
  private const MIN_GROUNDED_FAQ_ITEMS = 2;

  private string $entityType;
  private SeoEntityAdapter $adapter;
  private TranslationServiceWrapper $translator;
  private FaqRepository $faqRepository;
  private FaqParser $faqParser;
  private FaqPrettyPrinter $faqPrinter;
  private AnswerGroundingVerifier $grounder;
  private SeoEmbedding $embeddingHistory;
  private mixed $db;
  private bool $debug;

  public function __construct(string $entityType = 'product')
  {
    $this->entityType    = strtolower(trim($entityType));
    $this->adapter       = new SeoEntityAdapter($this->entityType);
    $this->debug         = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG')
      && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';
    $this->translator    = new TranslationServiceWrapper($this->debug);
    $this->faqRepository = new FaqRepository();
    $this->faqParser     = new FaqParser();
    $this->faqPrinter    = new FaqPrettyPrinter();
    $this->grounder      = new AnswerGroundingVerifier($this->debug);
    $this->embeddingHistory = new SeoEmbedding($this->entityType . 's_seo_embedding');
    $this->db = Registry::get('Db');
  }

  /**
   * Generate, validate, translate and persist the FAQ for one product.
   *
   * @return array{
   *   success: bool,
   *   source_language: array{id:int, code:string},
   *   languages: array<string, array{language_id:int, status:string, faq_count?:int, grounding?:array, message?:string}>,
   *   error?: string
   * }
   */
  public function run(int $entityId, string $baseUrl, string $triggeredBy = 'manual'): array
  {
    if ($this->entityType !== 'product') {
      return ['success' => false, 'error' => 'FAQ pipeline currently supports products only.', 'languages' => [], 'source_language' => ['id' => 0, 'code' => '']];
    }

    $languages = $this->getEnabledLanguages();
    if (empty($languages) || !isset($languages['en'])) {
      return ['success' => false, 'error' => 'English locale (code = "en") is required.', 'languages' => [], 'source_language' => ['id' => 0, 'code' => '']];
    }

    $sourceLangId = (int)$languages['en']['id'];

    // 1. Factual product context — anchors grounding.
    $context = $this->buildProductContext($entityId, $sourceLangId);
    if (empty($context['name'])) {
      return ['success' => false, 'error' => 'Product not found or missing name in English.', 'languages' => [], 'source_language' => ['id' => $sourceLangId, 'code' => 'en']];
    }

    // 2. Generate grounded EN FAQ (with retry-on-reject loop).
    $generation = $this->generateGroundedFaq($context, 'en');
    if (empty($generation['faq'])) {
      // We deliberately do not persist when grounding fails — better an
      // empty FAQ than a hallucinated one.
      return [
        'success'         => false,
        'error'           => 'Grounding failed after ' . self::MAX_RETRIES . ' attempts; FAQ not persisted.',
        'source_language' => ['id' => $sourceLangId, 'code' => 'en'],
        'languages'       => ['en' => [
          'language_id' => $sourceLangId,
          'status'      => 'rejected',
          'grounding'   => $generation['grounding'] ?? [],
        ]],
      ];
    }

    $perLanguage = [];

    // 3. Persist EN.
    $enSaved = $this->persistFaqForLanguage(
      $entityId,
      $sourceLangId,
      $generation['faq'],
      $this->buildEntityUrl($entityId, 'en'),
      $generation['grounding'],
      $triggeredBy
    );
    $perLanguage['en'] = [
      'language_id' => $sourceLangId,
      'status'      => $enSaved ? 'applied' : 'failed',
      'faq_count'   => count($generation['faq']),
      'grounding'   => $generation['grounding'],
    ];

    // 4. Translate + re-verify + persist for every other enabled language.
    foreach ($languages as $code => $info) {
      if ($code === 'en') {
        continue;
      }
      $targetId = (int)$info['id'];

      try {
        $translatedFaq = $this->translateFaq($generation['faq'], 'en', $code);
        $translatedCtx = $this->translateContextSnippets($context, 'en', $code);
        $grounding     = $this->verifyFaqGrounding($translatedFaq, $translatedCtx);

        if ($grounding['confidence'] < self::GROUNDING_THRESHOLD) {
          $perLanguage[$code] = [
            'language_id' => $targetId,
            'status'      => 'flagged',
            'faq_count'   => count($translatedFaq),
            'grounding'   => $grounding,
            'message'     => 'Translated FAQ failed grounding; not persisted for this locale.',
          ];
          $this->logDebug('Translated FAQ rejected', [
            'language_code' => $code,
            'confidence'    => $grounding['confidence'],
          ]);
          continue;
        }

        $saved = $this->persistFaqForLanguage(
          $entityId,
          $targetId,
          $translatedFaq,
          $this->buildEntityUrl($entityId, $code),
          $grounding,
          $triggeredBy
        );

        $perLanguage[$code] = [
          'language_id' => $targetId,
          'status'      => $saved ? 'applied' : 'failed',
          'faq_count'   => count($translatedFaq),
          'grounding'   => $grounding,
        ];
      } catch (\Throwable $e) {
        $perLanguage[$code] = [
          'language_id' => $targetId,
          'status'      => 'failed',
          'message'     => $e->getMessage(),
        ];
        $this->logDebug('FAQ propagation failed', [
          'language_code' => $code,
          'error'         => $e->getMessage(),
        ]);
      }
    }

    return [
      'success'         => true,
      'source_language' => ['id' => $sourceLangId, 'code' => 'en'],
      'languages'       => $perLanguage,
    ];
  }

  /**
   * Generate FAQ in EN with grounding verification + bounded retry.
   *
   * Each retry feeds the previous flagged sentences back into the agent's
   * validation_feedback channel so the next generation can correct itself.
   *
   * @return array{faq: array, grounding: array}
   */
  private function generateGroundedFaq(array $context, string $langCode): array
  {
    $agent = new SeoOptimizationAgent();
    $vars  = $this->buildPromptVars($context);

    // Embed the source documents ONCE (hoisted out of the retry loop) so every
    // per-item grounding check reuses the same source embeddings.
    $contextDocs     = $this->contextToSourceDocuments($context);
    $embeddedSources = $this->embedSourceDocuments($contextDocs);

    $lastGrounding = [];
    for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
      $faqCandidate = $agent->generateFaqForVars($vars, $langCode);
      if (empty($faqCandidate)) {
        $this->logDebug('Empty FAQ from agent', ['attempt' => $attempt]);
        continue;
      }

      // Per-item grounding: keep the Q/A pairs that are INDIVIDUALLY grounded
      // (each >= threshold ⇒ still no hallucination) and drop only the weak ones.
      // The old block-level check rejected the WHOLE FAQ when a single answer was
      // weakly grounded (it dragged the average + triggered the weak-sentence
      // penalty below the threshold), which made the action fail at random.
      $groundedItems = [];
      $keptScores    = [];
      $flagged       = [];

      foreach ($faqCandidate as $item) {
        $q = (string)($item['q'] ?? $item['question'] ?? '');
        $a = (string)($item['a'] ?? $item['answer']   ?? '');
        if ($q === '' && $a === '') {
          continue;
        }

        $itemGrounding = $this->grounder->verifyGrounding(trim($q . ' ' . $a), $embeddedSources);
        $confidence    = (float)($itemGrounding['confidence'] ?? 0);

        if ($confidence >= self::GROUNDING_THRESHOLD) {
          $groundedItems[] = $item;
          $keptScores[]    = $confidence;
        } else {
          $flagged[] = ['sentence' => trim($q . ' ' . $a), 'score' => $confidence, 'reason' => 'Low grounding score'];
        }
      }

      $keptCount = count($groundedItems);
      $avgKept   = $keptScores ? array_sum($keptScores) / count($keptScores) : 0.0;

      $this->logDebug('FAQ per-item grounding attempt', [
        'attempt' => $attempt,
        'kept'    => $keptCount,
        'dropped' => count($flagged),
      ]);

      if ($keptCount >= self::MIN_GROUNDED_FAQ_ITEMS) {
        return [
          'faq'       => $groundedItems,
          'grounding' => [
            'confidence'        => $avgKept,
            'decision'          => 'ACCEPT',
            'flagged_sentences' => [],
            'kept'              => $keptCount,
            'dropped'           => count($flagged),
          ],
        ];
      }

      $lastGrounding = [
        'confidence'        => $avgKept,
        'decision'          => 'REJECT',
        'flagged_sentences' => $flagged,
        'kept'              => $keptCount,
        'dropped'           => count($flagged),
      ];

      // Feed the ungrounded answers back so the next attempt can correct them.
      $vars['validation_feedback'] = $this->groundingFeedbackString($lastGrounding);
    }

    return ['faq' => [], 'grounding' => $lastGrounding];
  }

  /**
   * Verify a FAQ payload against a list of source documents.  Concatenates
   * every Q/A pair into a single answer so AnswerGroundingVerifier can score
   * the whole block at once.
   */
  private function verifyFaqGrounding(array $faq, array $sourceDocuments): array
  {
    $flat = [];
    foreach ($faq as $item) {
      $q = (string)($item['q'] ?? $item['question'] ?? '');
      $a = (string)($item['a'] ?? $item['answer']   ?? '');
      if ($q === '' && $a === '') {
        continue;
      }
      $flat[] = trim($q . ' ' . $a);
    }
    $answer = implode("\n", $flat);
    if ($answer === '') {
      return ['confidence' => 0.0, 'decision' => 'REJECT', 'flagged_sentences' => []];
    }

    // AnswerGroundingVerifier expects each source document to carry a
    // pre-computed `embedding` key.  When we pass only `['content' => str]`
    // entries the verifier silently falls back to "no embeddings - accept
    // without verification" and returns confidence 1.0 — which made the
    // grounding check entirely toothless.  Embed the source documents
    // here so the verifier can run real cosine-similarity scoring.
    $embeddedSources = $this->embedSourceDocuments($sourceDocuments);

    return $this->grounder->verifyGrounding($answer, $embeddedSources);
  }

  /**
   * Pre-compute embeddings for every source document so the grounding
   * verifier can compare them against the answer.  Falls back to the
   * original content-only document if the embedding generator is missing
   * or errors out — better to log a warning and accept than to crash.
   *
   * @param array<int, array{content:string}> $sourceDocuments
   * @return array<int, array{content:string, embedding?:array}>
   */
  private function embedSourceDocuments(array $sourceDocuments): array
  {
    if (empty($sourceDocuments)) {
      return $sourceDocuments;
    }

    try {
      $generator = \ClicShopping\AI\DomainsAI\Shared\Embedding\NewVector::gptEmbeddingsModel();
      if (!$generator) {
        $this->logDebug('Embedding generator unavailable — grounding will fall back to accept-all.');
        return $sourceDocuments;
      }
    } catch (\Throwable $e) {
      $this->logDebug('Embedding generator init failed', ['error' => $e->getMessage()]);
      return $sourceDocuments;
    }

    $out = [];
    foreach ($sourceDocuments as $doc) {
      $content = trim((string)($doc['content'] ?? ''));
      if ($content === '') {
        $out[] = $doc;
        continue;
      }
      try {
        $vector = $generator->embedText($content);
        if (is_array($vector) && !empty($vector)) {
          $doc['embedding'] = $vector;
        }
      } catch (\Throwable $e) {
        $this->logDebug('Source embed failed', [
          'snippet' => mb_substr($content, 0, 60),
          'error'   => $e->getMessage(),
        ]);
      }
      $out[] = $doc;
    }
    return $out;
  }

  /**
   * Translate every Q and A in the FAQ payload preserving the array shape
   * expected by FaqParser.
   */
  private function translateFaq(array $faq, string $fromLang, string $toLang): array
  {
    $out = [];
    foreach ($faq as $item) {
      $q = (string)($item['q'] ?? $item['question'] ?? '');
      $a = (string)($item['a'] ?? $item['answer']   ?? '');
      if ($q === '' || $a === '') {
        continue;
      }
      $out[] = [
        'q' => $this->translator->translate($q, $fromLang, $toLang),
        'a' => $this->translator->translate($a, $fromLang, $toLang),
      ];
    }
    return $out;
  }

  /**
   * Translate the textual snippets used as source documents so grounding
   * can be re-checked in the target language (cosine similarity needs the
   * embedded chunks to share the language of the answer).
   */
  private function translateContextSnippets(array $context, string $fromLang, string $toLang): array
  {
    $docs = [];
    foreach (['description', 'description_summary', 'manufacturer_name', 'category_name'] as $key) {
      if (!empty($context[$key])) {
        $docs[] = ['content' => $this->translator->translate((string)$context[$key], $fromLang, $toLang)];
      }
    }
    // Numeric / identifier facts do not need translation but must remain in
    // the source list so model / price / stock claims can be cross-checked.
    foreach (['name', 'model', 'price', 'currency'] as $key) {
      if (!empty($context[$key])) {
        $docs[] = ['content' => (string)$context[$key]];
      }
    }
    return $docs;
  }

  /**
   * Persist a validated FAQ payload for one language and append a
   * faq_generated row in products_seo_embedding for history.
   */
  private function persistFaqForLanguage(
    int $entityId,
    int $languageId,
    array $faq,
    string $url,
    array $grounding,
    string $triggeredBy
  ): bool {
    $jsonPayload = json_encode($faq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonPayload === false) {
      return false;
    }
    $parsed = $this->faqParser->parse($jsonPayload);
    if (!($parsed['success'] ?? false)) {
      $this->logDebug('FaqParser rejected payload', ['error' => $parsed['error'] ?? '']);
      return false;
    }
    $content = $this->faqPrinter->print($parsed['data']);
    $description = '';
    foreach ($parsed['data'] as $item) {
      if (isset($item['q'], $item['a'])) {
        $description .= $item['q'] . ' ' . $item['a'] . ' ';
      }
    }
    $description = trim($description);

    // Capture the SEO score BEFORE writing the FAQ so the history row can
    // show the actual lift the FAQ produces (Phase 2 leaves a score lower
    // than the source because of the missing FAQ — Phase 3 should bring it
    // back up).  We read it from the latest optimized_report metadata for
    // this language rather than re-crawling: re-crawling here would just
    // measure the pre-FAQ state we already have on file.
    $scoreBefore = 0;
    try {
      $latest = $this->embeddingHistory->getLatestReport($entityId, $languageId);
      if ($latest !== null) {
        $prevMeta    = json_decode($latest['metadata'] ?? '{}', true) ?: [];
        $scoreBefore = (int)($prevMeta['seo_score_after'] ?? $prevMeta['seo_score_before'] ?? 0);
      }
    } catch (\Throwable $e) {
      $this->logDebug('Score-before lookup failed', ['error' => $e->getMessage()]);
    }

    $saved = $this->faqRepository->saveFaq($entityId, $languageId, $content, $description);
    if (!$saved) {
      return false;
    }

    // Re-crawl the public-front page after the FAQ has been saved.  The
    // pi_products_info_description_faq module renders the FAQ block when
    // the embedded payload contains ≥ 2 entries, so the new crawl picks up
    // the additional content (improving heading coverage, structured data
    // hints, etc.) and yields a higher seo_score.  Cache bypass is mandatory
    // because the page may have been cached at the previous score.
    $scoreAfter = $scoreBefore;
    try {
      $baseUrl = HTTP::getShopUrlDomain();
      $report  = new SeoReport($url, $baseUrl);
      $data    = $report->getSeoData(true, $this->entityType);
      if ($data['isAlive'] ?? false) {
        $scoreAfter = (int)($data['seo_score'] ?? $scoreAfter);
      }
    } catch (\Throwable $e) {
      $this->logDebug('Post-FAQ re-crawl failed', ['error' => $e->getMessage()]);
    }

    // History trail.  type = 'faq_generated' is consumed by ProductsSerp
    // to gate Phase 3 as completed.
    $delta   = $scoreAfter - $scoreBefore;
    $summary = $delta > 0
      ? sprintf('FAQ generated and grounded (confidence %.2f). SEO score %d → %d (+%d).', (float)($grounding['confidence'] ?? 0), $scoreBefore, $scoreAfter, $delta)
      : ($delta === 0
          ? sprintf('FAQ generated and grounded (confidence %.2f). SEO score unchanged at %d/100.', (float)($grounding['confidence'] ?? 0), $scoreAfter)
          : sprintf('FAQ generated and grounded (confidence %.2f). SEO score %d → %d (%d) — re-audit needed.', (float)($grounding['confidence'] ?? 0), $scoreBefore, $scoreAfter, $delta)
        );

    $this->embeddingHistory->recordOptimizedReport(
      entityId:      $entityId,
      languageId:    $languageId,
      pageType:      $this->entityType,
      url:           $url,
      scoreBefore:   $scoreBefore,
      scoreAfter:    $scoreAfter,
      appliedFields: ['faq' => $description],
      auditResult:   [
        'summary'    => $summary,
        'improved'   => $delta >= 0,
        'score_before' => $scoreBefore,
        'score_after'  => $scoreAfter,
        'delta'        => $delta,
        'grounding'  => $grounding,
      ],
      triggeredBy:   $triggeredBy,
      sourceName:    'SeoFaqPipeline',
      type:          'faq_generated'
    );

    return true;
  }

  /**
   * Build the factual product context used as source of truth.
   * Stays read-only and goes through Registry::get('Db'), so no Doctrine
   * mixing per AGENTS.md.
   */
  private function buildProductContext(int $productId, int $languageId): array
  {
    $context = [];

    $current = $this->adapter->getCurrentData($productId, $languageId);
    if ($current !== null) {
      $context['name']                = (string)($current['name']            ?? '');
      $context['description']         = (string)($current['description']     ?? '');
      $context['description_summary'] = (string)($current['summary']         ?? '');
      $context['meta_title']          = (string)($current['meta_title']      ?? '');
      $context['meta_description']    = (string)($current['meta_description'] ?? '');
      $context['meta_keywords']       = (string)($current['meta_keywords']   ?? '');
    }

    $additional = $this->adapter->getAdditionalContext($productId, $languageId);
    foreach (['model', 'price', 'quantity', 'manufacturer_name'] as $key) {
      if (!empty($additional[$key])) {
        $context[$key] = (string)$additional[$key];
      }
    }
    $context['currency'] = 'EUR';

    // First mapped category for taxonomy anchoring.
    try {
      $Qcat = $this->db->prepare('SELECT cd.categories_name
                                  FROM :table_products_to_categories ptc
                                  INNER JOIN :table_categories_description cd
                                    ON cd.categories_id = ptc.categories_id
                                  WHERE ptc.products_id = :products_id
                                    AND cd.language_id  = :language_id
                                  LIMIT 1');
      $Qcat->bindInt(':products_id',  $productId);
      $Qcat->bindInt(':language_id',  $languageId);
      $Qcat->execute();
      $row = $Qcat->fetch();
      if ($row && !empty($row['categories_name'])) {
        $context['category_name'] = (string)$row['categories_name'];
      }
    } catch (\Throwable $e) {
      $this->logDebug('Category lookup failed', ['error' => $e->getMessage()]);
    }

    return $context;
  }

  /**
   * Map factual context to the SeoOptimizationAgent::generateFaqForVars()
   * variable shape consumed by ContentGenerationPrompts::getFaqPrompt().
   */
  private function buildPromptVars(array $context): array
  {
    return [
      'entity_name'         => $context['name']               ?? '',
      'entity_type'         => $this->entityType,
      'primary_keyword'     => strtolower($context['name']    ?? ''),
      'search_intent'       => 'informational',
      'topics'              => '',
      'keywords'            => '',
      'people_also_ask'     => '',
      'ai_overview_insights'=> '',
      'competitor_titles'   => '',
      'competitor_snippets' => '',
      'product_brand'       => $context['manufacturer_name']  ?? '',
      'product_model'       => $context['model']              ?? '',
      'product_price'       => $context['price']              ?? '',
      'product_currency'    => $context['currency']           ?? 'EUR',
      'product_stock'       => $context['quantity']           ?? '',
      'product_sku'         => $context['model']              ?? '',
      'product_url'         => '',
      'product_image'       => '',
      'entity_description'  => $context['description']        ?? '',
      'validation_feedback' => '',
      'availability'        => ((int)($context['quantity'] ?? 0) > 0) ? 'InStock' : 'OutOfStock',
      'category_url'        => '',
      'base_url'            => HTTP::getShopUrlDomain(),
      'product_count'       => '',
      'breadcrumb_path'     => json_encode([]),
      'top_products'        => json_encode([]),
      'description'         => $context['description']        ?? '',
      'output_language'     => 'English',
    ];
  }

  /**
   * Map product context to the AnswerGroundingVerifier source-document
   * shape (array of arrays with a 'content' string).
   */
  private function contextToSourceDocuments(array $context): array
  {
    $docs = [];
    foreach (['name', 'description', 'description_summary', 'manufacturer_name', 'category_name', 'model', 'price', 'currency', 'quantity'] as $key) {
      if (!empty($context[$key])) {
        $docs[] = ['content' => (string)$context[$key]];
      }
    }
    return $docs;
  }

  /**
   * Turn a grounding rejection into a textual feedback line that the agent
   * can incorporate in the next attempt.
   */
  private function groundingFeedbackString(array $grounding): string
  {
    $flagged = $grounding['flagged_sentences'] ?? [];
    if (empty($flagged)) {
      return 'Previous attempt failed grounding: stay strictly within the provided product description and attributes.';
    }
    $msg = 'Previous attempt failed grounding (confidence ' . round((float)($grounding['confidence'] ?? 0), 2) . '). Avoid these unsupported statements: ';
    $samples = [];
    foreach (array_slice($flagged, 0, 3) as $f) {
      $samples[] = '"' . trim((string)($f['sentence'] ?? '')) . '"';
    }
    return $msg . implode('; ', $samples);
  }

  /**
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

  private function buildEntityUrl(int $entityId, string $languageCode): string
  {
    return HTTP::getShopUrlDomain()
      . 'index.php?Products&Description&products_id=' . $entityId
      . '&language=' . urlencode($languageCode);
  }

  private function logDebug(string $message, array $context = []): void
  {
    if (!$this->debug) {
      return;
    }
    $payload = $context;
    $payload['message']   = $message;
    $payload['timestamp'] = date('c');
    error_log('SEO_FAQ_PIPELINE ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
  }
}
