<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
   * @Licence GPL 2 & MIT
   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */

  namespace ClicShopping\Apps\AI\Ecommerce\Module\Hooks\Shop\Cronjob;

  use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
  use ClicShopping\Apps\Tools\Cronjob\Classes\ClicShoppingAdmin\Cron;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\HTML;
  use ClicShopping\OM\Registry;
  use ClicShopping\Apps\AI\Ecommerce\Ecommerce as EcommerceApp;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\CockpitAIOrchestrator;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\FeedbackCollector;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\RuleAdjuster;
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
class Process implements \ClicShopping\OM\Modules\HooksInterface
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
      $cron_code = CRON_USER_ID; // Code unique identifiant ce cron dans la table
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
        // Exécution directe (CLI ou appel sans ID spécifique)
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

    try {
      // 1. Récupération des cibles (Boucles 1 & 2 à l'intérieur de fetch)
      $targets = $this->fetchTodayTargets();
      $summary['products_found'] = count($targets);

      if (empty($targets)) {
        $this->sendSummaryEmail($summary, 0);
        return;
      }

      // 2. Limite de sécurité (Safety Cap)
      if (count($targets) > self::MAX_PRODUCTS_PER_RUN) {
        $targets = array_slice($targets, 0, self::MAX_PRODUCTS_PER_RUN);
      }

      // 3. Orchestration et Exécution (Boucle 3)
      $orchestrator = new CockpitAIOrchestrator();

      foreach ($targets as $target) {
        $productId = $target['products_id'];
        $languageId = $target['languages_id'];

        try {
          // APPEL CRITIQUE : executeAnalysis déclenche l'ActionExecutor
          // Si CLICSHOPPING_APP_ECOMMERCE_CAI_AUTO_MODE est True, le prix change ici.
          $result = $orchestrator->executeAnalysis($productId, $languageId, self::CRON_USER_ID);

          $summary['analyses_succeeded']++;

          // On vérifie si une action réelle a été appliquée (REQ-EXE-02)
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
    // Exécuté après toutes les analyses du jour, indépendamment de leur résultat.
    // N'affecte pas le rapport email si désactivé.
    $summary['feedback_loop'] = 'disabled';

    if ($this->isFeedbackLoopEnabled()) {
      try {
        // Étape A : Collecte du feedback Score Y pour les actions éligibles (J+7)
        $collector = new FeedbackCollector();
        $collectorStats = $collector->run();

        $summary['feedback_loop'] = 'enabled';
        $summary['feedback_processed'] = $collectorStats['processed'];
        $summary['feedback_errors']    = $collectorStats['errors'];

        if ($this->debug) {
          error_log("[ProductCockpitAi] FeedbackCollector: processed={$collectorStats['processed']} skipped={$collectorStats['skipped']} errors={$collectorStats['errors']}");
        }

        // Étape B : Ajustement des seuils si suffisamment de données collectées
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
    // 1. Récupérer les produits commandés aujourd'hui
    $Qproducts = $this->db->prepare('SELECT DISTINCT op.products_id
                                      FROM :table_orders_products op
                                      INNER JOIN :table_orders o ON op.orders_id = o.orders_id
                                      WHERE o.orders_status >= 3
                                        AND DATE(o.date_purchased) = CURDATE()
                                      ORDER BY op.products_id');
    $Qproducts->execute();

    $productIds = [];
    while ($row = $Qproducts->fetch()) {
      $productIds[] = (int)$row['products_id'];
    }

    if (empty($productIds)) return [];

    // 2. Récupérer les langues actives
    $Qlangs = $this->db->prepare('SELECT languages_id 
                                 FROM :table_languages 
                                 ORDER BY sort_order ASC
                                 ');
    $Qlangs->execute();
    $languages = $Qlangs->fetchAll();

    // 3. Construire la liste des cibles (Produit x Langues)
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
    $actionColor = ($executed > 0) ? '#2980b9' : '#333'; // Bleu si des actions ont été faites
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
