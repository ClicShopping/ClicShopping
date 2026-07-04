<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\Shop\Cronjob;

  use ClicShopping\AI\Security\RateLimit;
  use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTML;
  use ClicShopping\OM\Interfaces\HooksInterface;
  use ClicShopping\OM\Mail;
  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\Common\CronLogger;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\CockpitAIOrchestrator;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\FeedbackCollector;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\RuleAdjuster;
  use ClicShopping\Apps\AI\Ecommerce\Classes\Shared\SeoCronRunner;
  use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron as Cronjob;
  
/**
 * Process — CockpitAI Daily Analysis CronJob
 *
 * Runs daily (recommended: 02:00 AM) to execute a full CockpitAI analysis for
 * every product that received at least one order during the current day.
 *
 * Results are stored in `products_cockpit_ai_embedding ` exactly as if the merchant had
 * triggered the analysis manually — meaning:
 *  - Velocity metrics are freshly calculated from the latest stock + demand data
 *  - Score X, Score Y, quadrant, LLM analysis, and action plan are all updated
 *  - The RAG historical context grows automatically with each daily run
 *  - The analysis report evolves daily without any user intervention
 *
 * ── Why this approach ────────────────────────────────────────────────────────
 *
 * Running the full pipeline (not just velocity recalculation) means:
 *  1. The embedding reflects the true daily state of the product
 *  2. RAG context accumulates over time, improving LLM recommendations
 *  3. No separate velocity cache table is needed — one source of truth
 *  4. The CronJob is trivially testable (same codepath as manual analysis)
 *
 * ── Pipeline per product ─────────────────────────────────────────────────────
 *
 *  For each (product_id, language_id) pair from today's orders:
 *    → CockpitAIOrchestrator::executeAnalysisCron()
 *    → DataCollector (fresh velocity from ProductStock)
 *    → ScoringEngine (Score X + Y with updated velocity factors)
 *    → LlmAnalysisGenerator (inventory-aware prompt)
 *    → EmbeddingService (stored in products_cockpit_ai_embedding )
 *
 * ── Security context ─────────────────────────────────────────────────────────
 *
 * The cron runs without an admin HTTP session. It calls executeAnalysisCron()
 * which bypasses validateUserPermissions() (session-based) and uses the system
 * user ID 'cron' for audit logging. All other pipeline steps are identical.
 *
 * ── Configuration ────────────────────────────────────────────────────────────
 *
 *  CLICSHOPPING_APP_ECOMMERCE_CAI_CRON_EMAIL  — recipient for summary email
 *                                               defaults to STORE_OWNER_EMAIL_ADDRESS
 *  CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG       — verbose error_log output
 */
class Process implements HooksInterface
{
  private const CRON_USER_ID = 'productCockpitAi';
  private const MAX_PRODUCTS_PER_RUN = 200;

    public mixed $app;
    private mixed $db;
    private bool $debug;

    /**
     * Initializes the cron job process
     */
    public function __construct()
    {
      if (!Registry::exists('Ecommerce')) {
        Registry::set('Ecommerce', new EcommerceApp());
      }
      $this->app = Registry::get('Ecommerce');
      $this->db = Registry::get('Db');

      $this->debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';
    }


  /**
   * Executes the main process for the cron job
   * This is the entry point called by the framework.
   * @return void
   */
  public function execute()
  {
    // Unified Cronjob/Process dispatch — this single hook fans out to every
    // AI/Ecommerce daily cron concern, exactly like Currency/Gdpr do. Each
    // concern self-gates on its own clic_cron code + master switch + rate limit,
    // so they run independently whether triggered by the full sweep
    // (?cronjob&runall) or a single admin "Run" (?cronId=…).
    $this->runCockpitAiCron();

    // SEO daily optimization (Phase 1 audit / 2 multilingual / 3 optional FAQ)
    // — delegated to its shared runner, self-gated on productSeoOptimization +
    // CLICSHOPPING_APP_ECOMMERCE_EC_CRON_SEO_STATUS. Same source of truth as the
    // former standalone SeoOptimization hook, now reached through Process.
    (new SeoCronRunner())->run();
  }

  /**
   * CockpitAI daily analysis concern.
   *
   * Self-gates on the CockpitAI master switch + provider status, then on its own
   * cron code inside cronJob(). Void so an opt-out never aborts the sibling SEO
   * concern dispatched right after it.
   */
  private function runCockpitAiCron(): void
  {
    // Master switch — when the merchant flips this constant to 'False' in
    // app configuration, the scheduler still fires us but we opt out
    // cleanly instead of consuming LLM credits.
    if (defined('CLICSHOPPING_APP_ECOMMERCE_CAI_CRON_STATUS')
        && CLICSHOPPING_APP_ECOMMERCE_CAI_CRON_STATUS !== 'True') {
      return;
    }

    $requiredConstants = [
      'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
      'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
      'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
    ];

    CLICSHOPPING::checkAppsIsActivated($requiredConstants);

    if (!Gpt::checkGptStatus()) {
      return;
    }

    $this->cronJob();
  }
    /**
     * Handles the execution of the cron job
     *
     * This method checks for a 'cronId' parameter, validates it, and if it matches
     *
     * @return void
     */
    private function cronJob(): void
    {
      $cron_code = self::CRON_USER_ID; // Code unique identifiant ce cron dans la table
      $cron_id_update = Cronjob::getCronCode($cron_code);

      if (isset($_GET['cronId'])) {
        $cron_id = HTML::sanitize($_GET['cronId']);

        if ($cron_id !== null && !empty($cron_id) && is_numeric($cron_id)) {
          $cron_id = (int)$cron_id;
          Cronjob::updateCron($cron_id);

          if ($cron_id_update == $cron_id) {
            $this->runAnalysis();
          }
        } else {
          if ($this->debug) {
            error_log('[ProductCockpitAi] Invalid cronId parameter detected');
          }
        }
      } else {
        // Direct execution (CLI or call without specific ID)
        if ($cron_id_update) {
          Cronjob::updateCron($cron_id_update);
        }
        $this->runAnalysis();
      }
    }

    /**
     * Main Analysis Logic
     */
  private function runAnalysis(): void
  {
    $startTime = microtime(true);
    $summary = [
      'date' => date('Y-m-d'),
      'products_found' => 0,
      'analyses_succeeded' => 0,
      'analyses_failed' => 0,
      'actions_executed' => 0, // Compteur pour le Flash Discount
      'errors' => [],
    ];

    // ── Unified cron log + global rate-limit lock (one run per hour) ────
    $logger = new CronLogger('cockpitai', self::CRON_USER_ID);
    $logger->start();

    $globalLock = new RateLimit('cockpitai_cron_lock', 1, 3600);
    if (!$globalLock->checkLimit('global')) {
      if ($this->debug) {
        error_log('[ProductCockpitAi] Skipped: another run is active within the hour.');
      }
      $logger->finish('skipped', [
        'error_messages' => 'Global lock active — another CockpitAI cron run is in progress.',
      ]);
      return;
    }

    try {
      // 1. Target collection: today's orders + modified since last analysis + never analysed
      $targets = $this->fetchTodayTargets();
      $summary['products_found'] = count($targets);

      if (empty($targets)) {
        // Close the cron_log row for the no-op run (no targets today) so the
        // observability table never leaves a dangling 'running' entry.
        $logger->finish('completed', [
          'targets_found' => 0,
          'targets_processed' => 0,
          'success_count' => 0,
          'failure_count' => 0,
        ]);
        $this->sendSummaryEmail($summary, 0);
        return;
      }

      // 2. Safety Cap
      if (count($targets) > self::MAX_PRODUCTS_PER_RUN) {
        $targets = array_slice($targets, 0, self::MAX_PRODUCTS_PER_RUN);
      }

      // 3. Orchestration and Execution (Loop 3)
      $orchestrator = new CockpitAIOrchestrator();

      foreach ($targets as $target) {
        $productId = $target['products_id'];
        $languageId = $target['languages_id'];

        try {
          // CRITICAL CALL: executeAnalysis triggers the ActionExecutor
          // Si CLICSHOPPING_APP_ECOMMERCE_CAI_AUTO_MODE est True, le prix change ici.
          $result = $orchestrator->executeAnalysis($productId, $languageId, self::CRON_USER_ID);

          $summary['analyses_succeeded']++;

          // Check whether a real action was applied (REQ-EXE-02)
          if (!empty($result['technical']['execution_results'])) {
            foreach ($result['technical']['execution_results'] as $exec) {
              if ($exec['status'] === 'SUCCESS') {
                $summary['actions_executed']++;
              }
            }
          }

        } catch (\Throwable $e) {
          $summary['analyses_failed']++;
          $summary['errors'][] = "Prod #{$productId}: " . $e->getMessage();
        }
      }

    } catch (\Throwable $e) {
      $summary['errors'][] = 'Fatal: ' . $e->getMessage();
    }

    // ── Feedback Loop Adaptatif (optionnel) ───────────────────────────────
    // Executed after all analyses of the day, regardless of their result.
    // Does not affect the email report if disabled.
    $summary['feedback_loop'] = 'disabled';

    if ($this->isFeedbackLoopEnabled()) {
      try {
        // Step A: Collect Score Y feedback for eligible actions (D+7)
        $collector = new FeedbackCollector();
        $collectorStats = $collector->run();

        $summary['feedback_loop'] = 'enabled';
        $summary['feedback_processed'] = $collectorStats['processed'];
        $summary['feedback_errors']    = $collectorStats['errors'];

        if ($this->debug) {
          error_log("[ProductCockpitAi] FeedbackCollector: processed={$collectorStats['processed']} skipped={$collectorStats['skipped']} errors={$collectorStats['errors']}");
        }

        // Step B: Threshold adjustment when enough data has been collected
        if ($collectorStats['processed'] > 0) {
          $adjuster    = new RuleAdjuster();
          $adjustments = $adjuster->run();

          $summary['rule_adjustments'] = count($adjustments);

          if ($this->debug && !empty($adjustments)) {
            foreach ($adjustments as $adj) {
              if ($this->debug) {
                error_log("[ProductCockpitAi] RuleAdjuster: {$adj['action_type']} [{$adj['direction']}] samples={$adj['sample_size']}");
              }

              foreach ($adj['adjustments'] as $rule => $change) {
                if ($this->debug) {
                  error_log("[ProductCockpitAi]   $rule: {$change['from']} → {$change['to']}");
                }
              }
            }
          }
        }

      } catch (\Throwable $e) {
        $summary['feedback_loop']   = 'error';
        $summary['errors'][]        = 'FeedbackLoop: ' . $e->getMessage();
        error_log("[ProductCockpitAi] FeedbackLoop error: " . $e->getMessage());
      }
    }

    // 4. Finalisation et Rapport
    $this->sendSummaryEmail($summary, microtime(true) - $startTime);

    // 5. Persist the unified cron-log row so analytics queries can compare
    // CockpitAI runs with SEO / FAQ runs in a single SELECT.
    $finalStatus = match (true) {
      $summary['analyses_failed'] === 0 && $summary['analyses_succeeded'] > 0 => 'completed',
      $summary['analyses_succeeded'] > 0                                       => 'partial',
      $summary['products_found']   === 0                                       => 'completed',
      default                                                                   => 'failed',
    };
    $logger->finish($finalStatus, [
      'targets_found'     => (int)$summary['products_found'],
      'targets_processed' => (int)$summary['analyses_succeeded'] + (int)$summary['analyses_failed'],
      'success_count'     => (int)$summary['analyses_succeeded'],
      'failure_count'     => (int)$summary['analyses_failed'],
      'error_messages'    => !empty($summary['errors']) ? array_slice($summary['errors'], 0, 50) : null,
      'metadata'          => [
        'actions_executed' => (int)$summary['actions_executed'],
        'feedback_loop'    => $summary['feedback_loop']    ?? 'disabled',
        'feedback_processed' => $summary['feedback_processed'] ?? 0,
        'rule_adjustments'   => $summary['rule_adjustments']   ?? 0,
      ],
    ]);
  }
    
  /**
   * Fetch distinct (product_id, language_id) pairs for products ordered today.
   *
   * Crosses today's ordered products with all active store languages so the
   * analysis is stored for every language the store supports.
   *
   * "Today" = date_purchased >= CURDATE() in the server timezone.
   * Status ≥ 3 = processing or completed (matches DataCollector convention).
   *
   * @return array<array{int, int}>  Array of [product_id, language_id] pairs
   */
  private function fetchTodayTargets(): array
  {
    // ── Source A: products ordered today (priority 1) ──────────────────
    $Qproducts = $this->db->prepare('SELECT DISTINCT op.products_id
                                      FROM :table_orders_products op
                                      INNER JOIN :table_orders o ON op.orders_id = o.orders_id
                                      WHERE o.orders_status >= 3
                                        AND DATE(o.date_purchased) = CURDATE()
                                      ORDER BY op.products_id');
    $Qproducts->execute();

    $productIds = [];
    while ($row = $Qproducts->fetch()) {
      $productIds[(int)$row['products_id']] = true;
    }

    // ── Source B: products modified since the last CockpitAI analysis ──
    // Keeps the catalogue in sync for items edited without a new sale.
    try {
      $QmodSinceLast = $this->db->prepare('
        SELECT p.products_id
        FROM :table_products p
        INNER JOIN (
          SELECT entity_id, MAX(date_modified) AS last_analysis
          FROM :table_products_cockpit_ai_embedding
          GROUP BY entity_id
        ) e ON e.entity_id = p.products_id
        WHERE p.products_status = 1
          AND p.products_last_modified IS NOT NULL
          AND p.products_last_modified > e.last_analysis
        ORDER BY p.products_last_modified DESC
        LIMIT 100
      ');
      $QmodSinceLast->execute();
      while ($row = $QmodSinceLast->fetch()) {
        $productIds[(int)$row['products_id']] = true;
      }
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[ProductCockpitAi] Source B (modified) query failed: ' . $e->getMessage());
      }
    }

    // ── Source C: active in-stock products never analysed (priority 3) ─
    try {
      $QneverAnalysed = $this->db->prepare('
        SELECT p.products_id
        FROM :table_products p
        WHERE p.products_status = 1
          AND p.products_quantity > 0
          AND NOT EXISTS (
            SELECT 1 FROM :table_products_cockpit_ai_embedding e
            WHERE e.entity_id = p.products_id
          )
        ORDER BY p.products_date_added DESC
        LIMIT 50
      ');
      $QneverAnalysed->execute();
      while ($row = $QneverAnalysed->fetch()) {
        $productIds[(int)$row['products_id']] = true;
      }
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[ProductCockpitAi] Source C (never analysed) query failed: ' . $e->getMessage());
      }
    }

    if (empty($productIds)) return [];
    $productIds = array_keys($productIds);

    // 2. Fetch active languages
    $Qlangs = $this->db->prepare('SELECT languages_id 
                                 FROM :table_languages 
                                 ORDER BY sort_order ASC
                                 ');
    $Qlangs->execute();
    $languages = $Qlangs->fetchAll();

    // 3. Build the target list (Product x Languages)
    $targets = [];
    foreach ($productIds as $pId) {
      foreach ($languages as $l) {
        $targets[] = [
          'products_id' => $pId,
          'languages_id' => (int)$l['languages_id']
        ];
      }
    }

    return $targets;
  }

  /**
   * Send summary email to configured recipient.
   *
   * Recipient: CLICSHOPPING_APP_ECOMMERCE_CAI_CRON_EMAIL constant.
   * If not defined or empty, falls back to STORE_OWNER_EMAIL_ADDRESS.
   *
   * @param array $summary  Result counters and errors
   * @param float $duration Total execution time in seconds
   */
  private function sendSummaryEmail(array $summary, float $duration): void
  {
    $recipient = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_CRON_EMAIL') && trim(CLICSHOPPING_APP_ECOMMERCE_CAI_CRON_EMAIL) !== '' ? trim(CLICSHOPPING_APP_ECOMMERCE_CAI_CRON_EMAIL) : (STORE_OWNER_EMAIL_ADDRESS ?? '');

    if (empty($recipient)) {
      if ($this->debug) {
        error_log('[CockpitAI Cron] No recipient configured — skipping summary email.');
      }

      return;
    }

      $date = $summary['date'];
      $ok = $summary['analyses_succeeded'];
      $fail = $summary['analyses_failed'];
      $found = $summary['products_found'];
      $dur = round($duration, 2);
      $hasError = $fail > 0;
      $status = $hasError ? 'Completed with errors' : 'Completed successfully';
      $executed = $summary['actions_executed'] ?? 0; // Nouvelle variable pour la Step 9

      $subject = "[CockpitAI] Daily analysis — {$date} — {$ok}/{$found} succeeded";

    // ── Plain text ─────────────────────────────────────────────────────────
    $text  = "CockpitAI — Daily Analysis CronJob\n";
    $text .= str_repeat('=', 40) . "\n";
    $text .= "Date              : {$date}\n";
    $text .= "Status            : {$status}\n";
    $text .= "Duration          : {$dur} s\n\n";
    $text .= "Products found    : {$found}\n";
    $text .= "Analyses OK       : {$ok}\n";
    $text .= "Analyses failed   : {$fail}\n\n";
    $text .= "Actions Executed  : {$executed}\n\n";

    // Feedback loop status
    $feedbackStatus = $summary['feedback_loop'] ?? 'disabled';
    $text .= "Feedback Loop     : {$feedbackStatus}\n";
    if ($feedbackStatus === 'enabled') {
      $text .= "  Feedback collected : " . ($summary['feedback_processed'] ?? 0) . "\n";
      $text .= "  Rule adjustments   : " . ($summary['rule_adjustments']   ?? 0) . "\n";
    }
    $text .= "\n"; // Ajout TXT

    if (!empty($summary['errors'])) {
      $text .= "Errors:\n";
      foreach (array_slice($summary['errors'], 0, 20) as $err) {
        $text .= "  - {$err}\n";
      }
      if (count($summary['errors']) > 20) {
        $text .= '  … and ' . (count($summary['errors']) - 20) . " more.\n";
      }
    } else {
      $text .= "No errors.\n";
    }
    $text .= "\n-- CockpitAI AI Module";

    // ── HTML ───────────────────────────────────────────────────────────────
    $statusColor = $hasError ? '#c0392b' : '#27ae60';
    $actionColor = ($executed > 0) ? '#2980b9' : '#333'; // Blue when actions were performed
    $errHtml     = '';

    if (!empty($summary['errors'])) {
      $errHtml = '<p><strong>Errors:</strong></p><ul style="color:#c0392b;">';
      foreach (array_slice($summary['errors'], 0, 20) as $err) {
        $errHtml .= '<li>' . HTML::outputProtected($err) . '</li>';
      }
      if (count($summary['errors']) > 20) {
        $errHtml .= '<li>… and ' . (count($summary['errors']) - 20) . ' more.</li>';
      }
      $errHtml .= '</ul>';
    }

    $feedbackStatus   = $summary['feedback_loop'] ?? 'disabled';
    $feedbackColor    = match($feedbackStatus) { 'enabled' => '#27ae60', 'error' => '#c0392b', default => '#999' };
    $feedbackHtml     = '';

    if ($feedbackStatus === 'enabled') {
      $fbProcessed  = $summary['feedback_processed'] ?? 0;
      $fbAdjusted   = $summary['rule_adjustments']   ?? 0;
      $feedbackHtml = "<tr><td><strong>Feedback collecté</strong></td><td>{$fbProcessed} actions</td></tr>"
                    . "<tr><td><strong>Seuils ajustés</strong></td><td>{$fbAdjusted} règle(s)</td></tr>";
    }

    $html = <<<HTML
    <html><body style="font-family:Arial,sans-serif;font-size:14px;color:#333;max-width:520px;">
    <h2 style="color:#2c3e50;">CockpitAI — Daily Analysis CronJob</h2>
    <p style="color:{$statusColor};font-weight:bold;">{$status}</p>
    <table cellpadding="6" cellspacing="0" border="1"
           style="border-collapse:collapse;width:100%;margin-bottom:16px;">
      <tr><td><strong>Date</strong></td><td>{$date}</td></tr>
      <tr><td><strong>Duration</strong></td><td>{$dur} s</td></tr>
      <tr><td><strong>Products found today</strong></td><td>{$found}</td></tr>
      <tr><td><strong>Analyses succeeded</strong></td>
          <td style="color:#27ae60;font-weight:bold;">{$ok}</td></tr>
      <tr><td><strong>Analyses failed</strong></td>
          <td style="color:{$statusColor};font-weight:bold;">{$fail}</td></tr>
      <tr style="background-color:#f9f9f9;">
          <td><strong>Auto-Actions</strong></td>
          <td style="color:{$actionColor};font-weight:bold;">{$executed}</td></tr>
      <tr style="background-color:#f0f8ff;">
          <td><strong>Feedback Loop</strong></td>
          <td style="color:{$feedbackColor};font-weight:bold;">{$feedbackStatus}</td></tr>
      {$feedbackHtml}
    </table>
    {$errHtml}
    <p style="color:#666;font-size:12px;"><em>Actions auto : requiert CLICSHOPPING_APP_ECOMMERCE_CAI_AUTO_MODE = True</em><br>
    <em>Feedback loop : requiert CLICSHOPPING_APP_ECOMMERCE_CAI_ADAPTIVE_RULES = True</em></p>
    <p style="color:#999;font-size:11px;margin-top:24px;">— CockpitAI AI Module</p>
    </body></html>
    HTML;

    try {
      $fromAddr = \defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
      $fromName = \defined('STORE_NAME') ? STORE_NAME : 'ClicShopping';

      $mail = new Mail();
      $mail->addHtml($html, $text);
      $mail->send($recipient, $fromName, $fromAddr, '', $subject);
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[CockpitAI Cron] Failed to send email: ' . $e->getMessage());
      }
    }
  }

  /**
   * Feedback loop adaptatif — activable indépendamment de l'analyse principale.
   *
   * Quand TRUE  : FeedbackCollector + RuleAdjuster s'exécutent après les analyses.
   *               Les seuils dans products_cockpit_ai_rule_thresholds s'ajustent automatiquement.
   * Quand FALSE : Le système fonctionne avec les seuils statiques par défaut.
   *               Comportement identique à l'ancienne approche v4.
   *
   * Configurable via : CLICSHOPPING_APP_ECOMMERCE_CAI_ADAPTIVE_RULES = 'True'
   */
  private function isFeedbackLoopEnabled(): bool
  {
    return \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_ADAPTIVE_RULES') && CLICSHOPPING_APP_ECOMMERCE_CAI_ADAPTIVE_RULES === 'True';
  }
}
