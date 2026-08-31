<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shared;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Security\RateLimit;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Common\CronLogger;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Faq\SeoFaqPipeline;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoActionLogRepository;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoCronStrategy;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoEmbedding;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoMultilingualOrchestrator;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoOriginalSnapshotRepository;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoSerpReportRepository;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron as Cronjob;

/**
 * SeoCronRunner — shared engine for the daily SEO cron.
 *
 * Reached through the unified Cronjob/Process dispatch: the AI/Ecommerce
 * Shop\Cronjob\Process and ClicShoppingAdmin\Cronjob\Process hooks fan out to
 * this runner alongside the CockpitAI concern, exactly like Currency/Gdpr wire
 * their Process hooks. This is the single source of truth for the SEO batch;
 * it self-gates on the productSeoOptimization cron code + master switch.
 *
 * Runs the full 3-phase SEO pipeline on a curated batch of products:
 *
 *   Phase 1  — initial audit (SeoEmbedding::process)
 *     ↓
 *   Phase 2  — multilingual optimization (SeoMultilingualOrchestrator)
 *     ↓
 *   Phase 3  — grounded FAQ generation (SeoFaqPipeline)
 *              SKIPPED when CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_FAQ_STATUS != 'True'
 *
 * Acceptance policy (added 2026-07-02):
 *   - When Phase 2 applies cleanly (preservation gate passed), the analysis is
 *     considered CORRECT and is AUTO-ACCEPTED — the same effect as the admin
 *     pressing "Accept": the latest SERP report is flagged 'accepted' and an
 *     attributable 'accepted' row (admin = 'cron') is written to the action log.
 *   - When Phase 2 does NOT apply (gate abort / regression / error), the product
 *     is NOT accepted; instead its id + the reason are collected into a note
 *     (cron_log.metadata.failed_products + a readable admin_note) so the
 *     administrator is warned about exactly which products need a manual review
 *     and why. A run that would otherwise be green is downgraded to 'partial'
 *     whenever such a note exists, so the warning surfaces on the dashboard.
 *
 * Target selection (SeoCronStrategy):
 *   A. Never analysed yet           — newest products first
 *   B. Modified since last analysis — keep optimised content in sync
 *   C. Recent benchmark regressions — automatic catch-up
 *
 * Rate limiting:
 *   - global  lock: 1 cron run per hour
 *   - product cooldown: 1 full pipeline per (product, language) per 24h
 *   - LLM daily quota: configurable cap on total LLM calls per day
 *
 * Configuration constants:
 *   CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_STATUS         (default True)  master switch
 *   CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_FAQ_STATUS     (default True)  include Phase 3
 *   CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_BATCH_SIZE     (default 10)    products per run
 *     — SEO is ~3 min/product, so size this to the trigger's time budget:
 *       batchSize ≈ max_execution_time / 180s. Under an HTTP trigger with a low
 *       max_execution_time even a small batch can time out; run via a CLI cron
 *       (set_time_limit(0)) for large catch-ups. The backlog drains across runs
 *       (per-product 24h cooldown), so a small batch is safe, just slower.
 *   CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_LLM_DAILY_LIMIT (default 500)  total LLM-bound runs/day
 */
class SeoCronRunner
{
  private string $entityType;
  private string $cronCode;
  private string $statusConst;
  private string $embeddingTable;
  private string $rlLock;
  private string $rlCooldown;
  private string $rlLlm;

  private bool  $debug;
  private int   $batchSize;
  private bool  $faqEnabled;
  private mixed $apps;
  /**
   * @param string $entityType 'product' (default — unchanged behaviour) or
   *        'category'. Categories reuse the same 1/2 phases minus Phase 3
   *        (there is no category FAQ pipeline) and self-gate on their own cron
   *        code, master switch and rate-limit buckets so the two crons never
   *        block each other.
   */
  public function __construct(string $entityType = 'product')
  {
    // Ensure the Ecommerce app is registered so the downstream SEO classes
    // (SeoEmbedding, orchestrator, …) can resolve it from the Registry.
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }

    $this->apps = Registry::get('Ecommerce');
    $this->apps->loadDefinitions('Sites/ClicShoppingAdmin/seo_cron');

    $this->entityType = $entityType === 'category' ? 'category' : 'product';

    if ($this->entityType === 'category') {
      $this->cronCode       = 'categorySeoOptimization';
      $this->statusConst    = 'CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_CATEGORY_STATUS';
      $this->embeddingTable = 'categories_seo_embedding';
      $this->rlLock         = 'seo_cron_category_lock';
      $this->rlCooldown     = 'seo_cron_category';
      $this->rlLlm          = 'seo_cron_category_llm_calls';
    } else {
      $this->cronCode       = 'productSeoOptimization';
      $this->statusConst    = 'CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_STATUS';
      $this->embeddingTable = 'products_seo_embedding';
      $this->rlLock         = 'seo_cron_lock';
      $this->rlCooldown     = 'seo_cron_product';
      $this->rlLlm          = 'seo_cron_llm_calls';
    }

    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG')
      && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';

    $this->batchSize = defined('CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_BATCH_SIZE')
      ? (int)CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_BATCH_SIZE
      : 10;

    // FAQ (Phase 3) exists only for products; categories have no FAQ pipeline.
    $this->faqEnabled = $this->entityType === 'product' && (!defined('CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_FAQ_STATUS') || CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_FAQ_STATUS === 'True');
  }

  /**
   * Entry point invoked by the Shop / ClicShoppingAdmin cron hooks.
   *
   * @return bool|null false when opted-out / provider offline, null otherwise
   */
  public function run(): bool|null
  {
    // Master switch — even if scheduler triggers the cron, opt out cleanly.
    if (defined($this->statusConst) && constant($this->statusConst) !== 'True') {
      return false;
    }

    $requiredConstants = [
      'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
      'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
      'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
    ];

    if (!CLICSHOPPING::checkAppsIsActivated($requiredConstants)) {
      return false;
    }

    if (!Gpt::checkGptStatus()) {
      return false;
    }

    $this->cronJob();
    return null;
  }

  private function cronJob(): void
  {
    $cronCode      = $this->cronCode;
    $cronIdUpdate  = Cronjob::getCronCode($cronCode);

    if (isset($_GET['cronId'])) {
      $cronId = HTML::sanitize($_GET['cronId']);
      if (!empty($cronId) && is_numeric($cronId)) {
        Cronjob::updateCron((int)$cronId);
        if ($cronIdUpdate == (int)$cronId) {
          $this->runOptimization();
        }
      }
    } else {
      if ($cronIdUpdate) {
        Cronjob::updateCron((int)$cronIdUpdate);
      }
      $this->runOptimization();
    }
  }

  /**
   * Main pipeline — selects targets, runs Phase 1/2/[3] per product/language,
   * auto-accepts the correct ones, collects a warning note for the rest, and
   * persists a unified log row.
   */
  private function runOptimization(): void
  {
    $logger = new CronLogger($this->entityType === 'category' ? 'seo_category' : 'seo', $this->cronCode);
    $logger->start();

    //Global lock: refuse a second SEO cron run within the hour
    $globalLock = new RateLimit($this->rlLock, 1, 3600);
    if (!$globalLock->checkLimit('global')) {
      $logger->finish('skipped', [
        'error_messages' => $this->apps->getDef('text_seo_cron_msg_global_lock'),
      ]);

      if ($this->debug) {
        error_log('[SeoOptimization] Skipped: global lock active.');
      }
      return;
    }

    // ── Target selection
    try {
      $strategy = new SeoCronStrategy($this->entityType);
      $targets  = $strategy->fetchTargets($this->batchSize);
    } catch (\Throwable $e) {
      $logger->finish('failed', ['error_messages' => $this->apps->getDef('text_seo_cron_msg_target_failed', ['error' => $e->getMessage()])]);
      return;
    }

    if (empty($targets)) {
      $logger->finish('completed', [
        'targets_found' => 0,
        'metadata'      => ['note' => $this->apps->getDef('text_seo_cron_msg_no_eligible', ['entity' => $this->apps->getDef('text_seo_cron_entity_' . $this->entityType)])],
      ]);
      return;
    }

    // ── Active languages
    $languages = [];
    try {
      foreach (Registry::get('Language')->getAll() as $code => $row) {
        if ((int)($row['status'] ?? 1) !== 0 && !empty($row['id'])) {
          $languages[(int)$row['id']] = (string)$code;
        }
      }
    } catch (\Throwable $e) {
      $logger->finish('failed', ['error_messages' => $this->apps->getDef('text_seo_cron_msg_lang_failed', ['error' => $e->getMessage()])]);
      return;
    }

    if (empty($languages)) {
      $logger->finish('failed', ['error_messages' => $this->apps->getDef('text_seo_cron_msg_no_language')]);
      return;
    }

    // Per-entity cooldown + LLM daily quota (buckets namespaced per entity type so the product and category crons never share a lock)
    $perProductLimit = new RateLimit($this->rlCooldown, 1, 86400);
    $llmDailyLimit   = defined('CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_LLM_DAILY_LIMIT') ? (int)CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_LLM_DAILY_LIMIT : 500;
    $llmQuota = new RateLimit($this->rlLlm, $llmDailyLimit, 86400);

    $baseUrl = HTTP::getShopUrlDomain();
    $counters = [
      'targets_found'     => count($targets),
      'targets_processed' => 0,
      'success_count'     => 0,
      'failure_count'     => 0,
      'skipped_count'     => 0,
      'accepted_count'    => 0,
      'phase1_ok'         => 0,
      'phase2_ok'         => 0,
      'phase3_ok'         => 0,
    ];
    $errors = [];
    // Products whose analysis was NOT auto-accepted, with the reason, so the
    // administrator can be warned (id + why). One entry per failing product.
    $failedProducts = [];

    foreach ($targets as $productId) {
      if (!$llmQuota->checkLimit('global')) {
        if ($this->debug) {
          error_log('[SeoOptimization] LLM daily quota reached, stopping.');
        }
        $errors[] = $this->apps->getDef('text_seo_cron_msg_llm_quota');
        break;
      }

      $cooldownKey = 'p' . $productId;
      if (!$perProductLimit->checkLimit($cooldownKey)) {
        $counters['skipped_count']++;
        continue;
      }

      $counters['targets_processed']++;
      $productOk = false;

      try {
        // ── Phase 1 — initial audit per language (cheap, deterministic)
        $phase1Done = $this->runPhase1($productId, $languages, $baseUrl, $errors);
        if ($phase1Done) {
          $counters['phase1_ok']++;
        }

        // ── Phase 2 — multilingual optimisation (LLM-bound, ~1-3 min)
        $phase2 = $this->runPhase2($productId, $baseUrl, $errors);
        $phase2Done = $phase2['ok'];
        if ($phase2Done) {
          $counters['phase2_ok']++;

          // The analysis is correct → auto-accept it (best-effort, never
          // fails the run). Mirrors the manual "Accept" action.
          if ($this->autoAcceptProduct($productId)) {
            $counters['accepted_count']++;
          }
        } else {
          // Not applied → warn the administrator with the id + reason.
          $failedProducts[] = [
            'id'     => $productId,
            'reason' => $phase2['reason'] ?? 'optimization not applied',
          ];
        }

        // ── Phase 3 — FAQ generation (optional)
        if ($this->faqEnabled && $phase2Done) {
          $phase3Done = $this->runPhase3($productId, $baseUrl, $errors);
          if ($phase3Done) {
            $counters['phase3_ok']++;
          }
        }

        $productOk = $phase1Done || $phase2Done;
      } catch (\Throwable $e) {
        $errors[] = $this->apps->getDef('text_seo_cron_err_generic', ['entity' => $this->entityType, 'id' => $productId, 'error' => $e->getMessage()]);
      }

      if ($productOk) {
        $counters['success_count']++;
      } else {
        $counters['failure_count']++;
      }
    }

    $status = match (true) {
      $counters['failure_count'] === 0 && $counters['success_count'] > 0 => 'completed',
      $counters['success_count'] > 0                                      => 'partial',
      default                                                              => 'failed',
    };

    // If any product needs a manual review, never report a fully-green run:
    // the admin must be able to notice the warning on the dashboard.
    if (!empty($failedProducts) && $status === 'completed') {
      $status = 'partial';
    }

    $adminNote = $this->buildAdminNote($failedProducts);
    if ($adminNote !== '') {
      // Surface the note at the top of the free-text channel too (that column
      // is the one the cron log page already renders).
      array_unshift($errors, $adminNote);
    }

    $logger->finish($status, [
      'targets_found'     => $counters['targets_found'],
      'targets_processed' => $counters['targets_processed'],
      'success_count'     => $counters['success_count'],
      'failure_count'     => $counters['failure_count'],
      'skipped_count'     => $counters['skipped_count'],
      'error_messages'    => !empty($errors) ? array_slice($errors, 0, 50) : null,
      'metadata'          => [
        'phase1_success'  => $counters['phase1_ok'],
        'phase2_success'  => $counters['phase2_ok'],
        'phase3_success'  => $counters['phase3_ok'],
        'accepted_count'  => $counters['accepted_count'],
        'failed_products' => array_slice($failedProducts, 0, 50),
        'admin_note'      => $adminNote !== '' ? $adminNote : null,
        'faq_enabled'     => $this->faqEnabled,
        'batch_size'      => $this->batchSize,
        'language_count'  => count($languages),
      ],
    ]);

    if ($this->debug) {
      error_log(sprintf(
        '[SeoOptimization] run done: status=%s found=%d processed=%d ok=%d fail=%d skipped=%d accepted=%d review=%d',
        $status,
        $counters['targets_found'],
        $counters['targets_processed'],
        $counters['success_count'],
        $counters['failure_count'],
        $counters['skipped_count'],
        $counters['accepted_count'],
        count($failedProducts)
      ));
    }
  }

  /**
   * Build the human-readable administrator warning listing every product that
   * was NOT auto-accepted, with its id and the reason. Pure (no side effects)
   * so the decision can be unit-tested.
   *
   * @param array<int, array{id:int, reason:string}> $failedProducts
   */
  public function buildAdminNote(array $failedProducts): string
  {
    if (empty($failedProducts)) {
      return '';
    }

    $reasonDefault = $this->apps->getDef('text_seo_cron_reason_default');

    $parts = [];
    foreach ($failedProducts as $fp) {
      $id     = (int)($fp['id'] ?? 0);
      $reason = trim((string)($fp['reason'] ?? ''));
      if ($reason === '') {
        $reason = $reasonDefault;
      }
      $parts[] = $this->apps->getDef('text_seo_cron_note_item', ['id' => $id, 'reason' => $reason]);
    }

    return $this->apps->getDef('text_seo_cron_admin_note', [
      'count'  => count($failedProducts),
      'entity' => $this->apps->getDef('text_seo_cron_entity_' . $this->entityType),
      'list'   => implode('; ', $parts),
    ]);
  }

  /**
   * Auto-accept a product whose analysis is correct: flag the latest SERP
   * report 'accepted' and append an attributable action-log entry, exactly
   * like the manual Accept button (admin = 'cron'). Best-effort: any failure
   * is swallowed so it never breaks the cron run.
   */
  private function autoAcceptProduct(int $productId): bool
  {
    try {
      (new SeoSerpReportRepository())->markLatestStatus($this->entityType, $productId, 'accepted');

      try {
        (new SeoActionLogRepository())->record(
          $this->entityType,
          $productId,
          0,
          'accepted',
          0,
          'cron',
          ['triggered_by' => 'cron', 'auto' => true]
        );
      } catch (\Throwable $ignored) {
        // The audit trail is best-effort; acceptance itself already succeeded.
      }

      return true;
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log(sprintf('[SeoOptimization] auto-accept failed product %d: %s', $productId, $e->getMessage()));
      }
      return false;
    }
  }

  /**
   * Build the shop-front URL for the crawled entity, mirroring the URL
   * convention used by the manual AJAX endpoints (product description page vs.
   * category listing page).
   */
  private function entityUrl(int $entityId, string $baseUrl, string $languageCode): string
  {
    if ($this->entityType === 'category') {
      return $baseUrl . 'index.php?cPath=' . $entityId . '&language=' . urlencode($languageCode);
    }

    return $baseUrl . 'index.php?Products&Description&products_id=' . $entityId
         . '&language=' . urlencode($languageCode);
  }

  /**
   * Phase 1: cheap audit per language.  We loop because SeoEmbedding::process
   * is single-language; the cron processes ALL active locales to seed history.
   *
   * @param array<int, string>     $languages  [languageId => 'code']
   * @param list<string>           $errors     accumulator (passed by reference)
   */
  private function runPhase1(int $productId, array $languages, string $baseUrl, array &$errors): bool
  {
    $repo = new SeoEmbedding($this->embeddingTable);
    $anyOk = false;
    foreach ($languages as $languageId => $code) {
      $url = $this->entityUrl($productId, $baseUrl, (string)$code);
      try {
        $r = $repo->process(
          entityId:    $productId,
          languageId:  (int)$languageId,
          url:         $url,
          baseUrl:     $baseUrl,
          pageType:    $this->entityType,
          triggeredBy: 'cron'
        );
        if (($r['success'] ?? false) === true) {
          $anyOk = true;
        }
      } catch (\Throwable $e) {
        $errors[] = (string)$this->apps->getDef('text_seo_cron_err_phase1', ['entity' => $this->entityType, 'id' => $productId, 'lang' => $languageId, 'error' => $e->getMessage()]);
      }
    }
    return $anyOk;
  }

  /**
   * Phase 2: multilingual optimisation through the agentic pipeline.
   *
   * @param list<string> $errors accumulator (passed by reference)
   * @return array{ok:bool, reason:?string} ok=true when the optimization was
   *         applied; otherwise reason carries why it was not (for the admin note).
   */
  private function runPhase2(int $productId, string $baseUrl, array &$errors): array
  {
    try {
      // HARD PRECONDITION (same as the manual optimize endpoint): preserve the genuine original (write-once, all languages) BEFORE the orchestrator overwrites anything
      try {
        SeoOriginalSnapshotRepository::captureEntityOriginals($this->entityType, $productId);
      } catch (\Throwable $e) {
        $reason = $this->apps->getDef('text_seo_cron_reason_snapshot_failed', ['error' => $e->getMessage()]);
        $errors[] = (string)$this->apps->getDef('text_seo_cron_err_phase2', ['entity' => $this->entityType, 'id' => $productId, 'error' => $reason]);
        return ['ok' => false, 'reason' => $reason];
      }

      $orchestrator = new SeoMultilingualOrchestrator($this->entityType);
      $r = $orchestrator->run($productId, $baseUrl, 'cron');

      if (($r['success'] ?? false) === true) {
        return ['ok' => true, 'reason' => null];
      }

      $reason = (string)($r['error'] ?? $this->apps->getDef('text_seo_cron_reason_failed'));
      // The preservation gate reports the dropped facts — surface them so the
      // admin note is actionable ("why").
      if (!empty($r['missing_entities']) && \is_array($r['missing_entities'])) {
        $reason .= ' ' . $this->apps->getDef('text_seo_cron_reason_missing_facts', ['facts' => implode(', ', array_slice($r['missing_entities'], 0, 8))]);
      }
      $errors[] = (string)$this->apps->getDef('text_seo_cron_err_phase2', ['entity' => $this->entityType, 'id' => $productId, 'error' => $reason]);
      return ['ok' => false, 'reason' => $reason];
    } catch (\Throwable $e) {
      $reason = $e->getMessage();
      $errors[] = (string)$this->apps->getDef('text_seo_cron_err_phase2', ['entity' => $this->entityType, 'id' => $productId, 'error' => $reason]);
      return ['ok' => false, 'reason' => $reason];
    }
  }

  /**
   * Phase 3: grounded FAQ generation per product.
   */
  private function runPhase3(int $productId, string $baseUrl, array &$errors): bool
  {
    try {
      $pipeline = new SeoFaqPipeline($this->entityType);
      $r = $pipeline->run($productId, $baseUrl, 'cron');
      if (($r['success'] ?? false) === true) {
        return true;
      }
      $errors[] = $this->apps->getDef('text_seo_cron_err_phase3', ['entity' => $this->entityType, 'id' => $productId, 'error' => $r['error'] ?? $this->apps->getDef('text_seo_cron_reason_failed')]);
    } catch (\Throwable $e) {
      $errors[] = $this->apps->getDef('text_seo_cron_err_phase3', ['entity' => $this->entityType, 'id' => $productId, 'error' => $e->getMessage()]);
    }
    return false;
  }
}
