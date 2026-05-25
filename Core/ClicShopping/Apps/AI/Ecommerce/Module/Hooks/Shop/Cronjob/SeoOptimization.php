<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\Shop\Cronjob;

use ClicShopping\AI\Security\RateLimit;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Common\CronLogger;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\Faq\SeoFaqPipeline;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoCronStrategy;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoEmbedding;
use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO\SeoMultilingualOrchestrator;
use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron as Cronjob;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * SeoOptimization — daily SEO cron
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
 *   CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_BATCH_SIZE     (default 30)    products per run
 *   CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_LLM_DAILY_LIMIT (default 500)  total LLM-bound runs/day
 */
class SeoOptimization implements \ClicShopping\OM\Modules\HooksInterface
{
  private const CRON_CODE = 'productSeoOptimization';

  private mixed $app;
  private mixed $db;
  private bool  $debug;
  private int   $batchSize;
  private bool  $faqEnabled;

  public function __construct()
  {
    if (!Registry::exists('Ecommerce')) {
      Registry::set('Ecommerce', new EcommerceApp());
    }
    $this->app   = Registry::get('Ecommerce');
    $this->db    = Registry::get('Db');
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG')
      && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';

    $this->batchSize = defined('CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_BATCH_SIZE')
      ? (int)CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_BATCH_SIZE
      : 30;

    $this->faqEnabled = !defined('CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_FAQ_STATUS')
      || CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_FAQ_STATUS === 'True';
  }

  public function execute()
  {
    // Master switch — even if scheduler triggers the cron, opt out cleanly.
    if (defined('CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_STATUS')
        && CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_STATUS !== 'True') {
      return false;
    }

    $requiredConstants = [
      'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
      'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
      'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
    ];
    CLICSHOPPING::checkAppsIsActivated($requiredConstants);

    if (!Gpt::checkGptStatus()) {
      return false;
    }

    $this->cronJob();
    return null;
  }

  private function cronJob(): void
  {
    $cronCode      = self::CRON_CODE;
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
   * persists a unified log row.
   */
  private function runOptimization(): void
  {
    $logger = new CronLogger('seo', self::CRON_CODE);
    $logger->start();

    // ── Global lock: refuse a second SEO cron run within the hour ──────
    $globalLock = new RateLimit('seo_cron_lock', 1, 3600);
    if (!$globalLock->checkLimit('global')) {
      $logger->finish('skipped', [
        'error_messages' => 'Global lock active — another SEO cron run is already in progress within the hour.',
      ]);
      if ($this->debug) {
        error_log('[SeoOptimization] Skipped: global lock active.');
      }
      return;
    }

    // ── Target selection ──────────────────────────────────────────────
    try {
      $strategy = new SeoCronStrategy();
      $targets  = $strategy->fetchTargets($this->batchSize);
    } catch (\Throwable $e) {
      $logger->finish('failed', ['error_messages' => 'Target selection failed: ' . $e->getMessage()]);
      return;
    }

    if (empty($targets)) {
      $logger->finish('completed', [
        'targets_found' => 0,
        'metadata'      => ['note' => 'no eligible product found'],
      ]);
      return;
    }

    // ── Active languages ──────────────────────────────────────────────
    $languages = [];
    try {
      foreach (Registry::get('Language')->getAll() as $code => $row) {
        if ((int)($row['status'] ?? 1) !== 0 && !empty($row['id'])) {
          $languages[(int)$row['id']] = (string)$code;
        }
      }
    } catch (\Throwable $e) {
      $logger->finish('failed', ['error_messages' => 'Language enumeration failed: ' . $e->getMessage()]);
      return;
    }

    if (empty($languages)) {
      $logger->finish('failed', ['error_messages' => 'No active language found.']);
      return;
    }

    // ── Per-product cooldown + LLM daily quota ────────────────────────
    $perProductLimit = new RateLimit('seo_cron_product', 1, 86400);
    $llmDailyLimit   = defined('CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_LLM_DAILY_LIMIT')
      ? (int)CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_LLM_DAILY_LIMIT
      : 500;
    $llmQuota = new RateLimit('seo_cron_llm_calls', $llmDailyLimit, 86400);

    $baseUrl = \ClicShopping\OM\HTTP::getShopUrlDomain();
    $counters = [
      'targets_found'     => count($targets),
      'targets_processed' => 0,
      'success_count'     => 0,
      'failure_count'     => 0,
      'skipped_count'     => 0,
      'phase1_ok'         => 0,
      'phase2_ok'         => 0,
      'phase3_ok'         => 0,
    ];
    $errors = [];

    foreach ($targets as $productId) {
      if (!$llmQuota->checkLimit('global')) {
        if ($this->debug) {
          error_log('[SeoOptimization] LLM daily quota reached, stopping.');
        }
        $errors[] = 'LLM daily quota reached, remaining targets skipped.';
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
        $phase2Done = $this->runPhase2($productId, $baseUrl, $errors);
        if ($phase2Done) {
          $counters['phase2_ok']++;
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
        $errors[] = sprintf('product %d: %s', $productId, $e->getMessage());
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
        'faq_enabled'     => $this->faqEnabled,
        'batch_size'      => $this->batchSize,
        'language_count'  => count($languages),
      ],
    ]);

    if ($this->debug) {
      error_log(sprintf(
        '[SeoOptimization] run done: status=%s found=%d processed=%d ok=%d fail=%d skipped=%d',
        $status,
        $counters['targets_found'],
        $counters['targets_processed'],
        $counters['success_count'],
        $counters['failure_count'],
        $counters['skipped_count']
      ));
    }
  }

  /**
   * Phase 1: cheap audit per language.  We loop because SeoEmbedding::process
   * is single-language; the cron processes ALL active locales to seed history.
   *
   * @param int[]                  $languages  [languageId => 'code']
   * @param list<string>           $errors     accumulator (passed by reference)
   */
  private function runPhase1(int $productId, array $languages, string $baseUrl, array &$errors): bool
  {
    $repo = new SeoEmbedding('products_seo_embedding');
    $anyOk = false;
    foreach ($languages as $languageId => $code) {
      $url = $baseUrl . 'index.php?Products&Description&products_id=' . $productId
           . '&language=' . urlencode($code);
      try {
        $r = $repo->process(
          entityId:    $productId,
          languageId:  (int)$languageId,
          url:         $url,
          baseUrl:     $baseUrl,
          pageType:    'product',
          triggeredBy: 'cron'
        );
        if (($r['success'] ?? false) === true) {
          $anyOk = true;
        }
      } catch (\Throwable $e) {
        $errors[] = sprintf('phase1 product %d lang %d: %s', $productId, $languageId, $e->getMessage());
      }
    }
    return $anyOk;
  }

  /**
   * Phase 2: multilingual optimisation through the agentic pipeline.
   */
  private function runPhase2(int $productId, string $baseUrl, array &$errors): bool
  {
    try {
      $orchestrator = new SeoMultilingualOrchestrator('product');
      $r = $orchestrator->run($productId, $baseUrl, 'cron');
      if (($r['success'] ?? false) === true) {
        return true;
      }
      $errors[] = sprintf('phase2 product %d: %s', $productId, $r['error'] ?? 'failed');
    } catch (\Throwable $e) {
      $errors[] = sprintf('phase2 product %d: %s', $productId, $e->getMessage());
    }
    return false;
  }

  /**
   * Phase 3: grounded FAQ generation per product.
   */
  private function runPhase3(int $productId, string $baseUrl, array &$errors): bool
  {
    try {
      $pipeline = new SeoFaqPipeline('product');
      $r = $pipeline->run($productId, $baseUrl, 'cron');
      if (($r['success'] ?? false) === true) {
        return true;
      }
      $errors[] = sprintf('phase3 product %d: %s', $productId, $r['error'] ?? 'failed');
    } catch (\Throwable $e) {
      $errors[] = sprintf('phase3 product %d: %s', $productId, $e->getMessage());
    }
    return false;
  }
}
