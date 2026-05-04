<?php
  /**
   *
   * @copyright 2008 - https://www.clicshopping.org
   * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
   * @Licence GPL 2 & MIT
   * @Info : https://www.clicshopping.org/forum/trademark/
   *
   */


  /*
  IMPORTANT : USE SubOrchestrator for other function
  */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;
  use ClicShopping\AI\Security\SecurityLogger;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SecurityIntegration;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator\ConcurrencyManager;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator\ContextBuilder;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator\DataCollector;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator\LlmAnalysisGenerator;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator\PipelineRunner;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator\ReportBuilder;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator\SeoInvoker;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubRules\RuleRegistry;
  use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;


  /**
   * CockpitAIOrchestrator
   *
   * Single entry point for the CockpitAI analysis pipeline.
   *
   * This class is a pure sequencer: it wires the 8 pipeline steps in order and
   * delegates every responsibility to a dedicated SubOrchestrator component:
   *
   *   DataCollector        — Step 1  : read-only DB queries
   *   ScoringEngine        — Steps 2-3: catalog normalization + dual-axis scoring
   *   EmbeddingService     — Steps 5, 8: RAG retrieval + embedding persistence
   *   (SEO Agent)          — Step 4  : conditional SEO invocation (task 7.5)
   *   (LLM Actor / Gpt)    — Step 6  : LLM analysis generation   (task 7.7)
   *   RecommendationEngine — Step 7  : rules engine + conflict resolution
   *   ContextBuilder       — assembles the immutable Context for scoring
   *   PipelineRunner       — wraps each step with fallback + structured log
   *   ReportBuilder        — builds Analysis_Report + embedding metadata
   *
   * Both are stubs in this class and delegated to invokeSEOAgent() /
   * generateLLMAnalysis() which will be fleshed out in later tasks.
   *
   * ── Pipeline steps ───────────────────────────────────────────────────────────
   *
   *  # | Context key             | Critical | Timeout | Fallback
   *  ──┼─────────────────────────┼──────────┼─────────┼──────────────────
   *  1 | data_collection         | YES      | 2 s     | abort
   *  2 | catalog_normalization   | no       | 3 s     | defaults()
   *  3 | scoring_calculation     | YES      | 2 s     | abort
   *  4 | seo_analysis            | no       | 5 s     | NOT_ANALYZED
   *  5 | rag_context_retrieval   | no       | 1 s     | []
   *  6 | llm_analysis_generation | no       | 5 s     | generic text
   *  7 | rules_engine_execution  | no       | 1 s     | []
   *  8 | embedding_persistence   | no       | 2 s     | null
   */
  class CockpitAIOrchestrator
  {
    private DataCollector        $dataCollector;
    private ScoringEngine        $scoringEngine;
    private RecommendationEngine $recommendationEngine;
    private EmbeddingService     $embeddingService;
    private ContextBuilder       $contextBuilder;
    private PipelineRunner       $runner;
    private ReportBuilder        $reportBuilder;
    private SeoInvoker           $seoInvoker;
    private LlmAnalysisGenerator $llmGenerator;
    private ConcurrencyManager   $concurrencyManager;
    private SecurityIntegration  $security;
    private SecurityLogger       $logger;
    private bool                 $debug;
    private mixed $rule_registry;
    private mixed $db;

    public function __construct()
    {
      $this->debug = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG') && CLICSHOPPING_APP_ECOMMERCE_CAI_DEBUG === 'True';
      $this->rule_registry = new RuleRegistry();

      $this->security             = new SecurityIntegration();
      $this->logger               = $this->security->getLogger();
      $this->concurrencyManager   = new ConcurrencyManager();
      $this->dataCollector        = new DataCollector();
      $this->scoringEngine        = new ScoringEngine();
      $this->recommendationEngine = new RecommendationEngine($this->rule_registry->standard());
      $this->embeddingService     = new EmbeddingService();
      $this->contextBuilder       = new ContextBuilder();
      $this->runner               = new PipelineRunner($this->logger, $this->debug);
      $this->reportBuilder        = new ReportBuilder();
      $this->seoInvoker           = new SeoInvoker();
      $this->llmGenerator         = new LlmAnalysisGenerator();

      $this->db = Registry::get('Db');

      $this->checkStatus();
    }

    /*
     * Check if the status of the app
     * return bool
     */
    public function checkStatus(): bool
    {
      $requiredConstants = [
        'CLICSHOPPING_APP_ECOMMERCE_CAI_STATUS',
        'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
        'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
        'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
      ];

      CLICSHOPPING::checkAppsIsActivated($requiredConstants);

      if (!Gpt::checkGptStatus()) {
        return false;
      }

      return true;
    }

    /**
     * Execute the complete 8-step CockpitAI analysis pipeline.
     *
     * @param int    $productId   Product to analyze
     * @param int    $languageId  Language for analysis and RAG filtering
     * @param string $userId      User triggering the analysis (audit log)
     * @return array              Analysis_Report (JSON-serializable)
     * @throws \Exception         If a critical step fails (Steps 1 or 3) or concurrency limit exceeded
     */
    public function executeAnalysis(int $productId, int $languageId, string $userId): array
    {
      // ── Security Integration (Requirements 21.1, 21.3, 21.4, 21.5) ────
      // Step 1: Validate user permissions
      $permissionCheck = $this->security->validateUserPermissions();
      if (!$permissionCheck['valid']) {
        throw new \Exception($permissionCheck['error']);
      }

      // Step 2: Sanitize and validate inputs
      $inputValidation = $this->security->sanitizeAndValidateInputs($productId, $languageId);
      if (!$inputValidation['valid']) {
        throw new \Exception($inputValidation['error']);
      }

      // Use validated inputs
      $productId = $inputValidation['product_id'];
      $languageId = $inputValidation['language_id'];
      $userId = (int)$permissionCheck['user_id']; // Use validated user ID from session

      // Step 3: Log analysis request initiation
      $this->security->logAnalysisRequest($productId, $languageId, $userId, 'initiated');

      // ── Concurrency Control (Requirement 23.5) ────────────────────────
      // Check if user has reached concurrent analysis limit
      if (!$this->concurrencyManager->acquireSlot($userId)) {
        $stats = $this->concurrencyManager->getStats($userId);
        
        $this->logger->logStructured('warning', 'CockpitAIOrchestrator', 'concurrency_limit_exceeded', [
          'product_id'  => $productId,
          'user_id'     => $userId,
          'current'     => $stats['current'],
          'max'         => $stats['max'],
          'timestamp'   => date('Y-m-d H:i:s'),
        ]);

        throw new \Exception(
          "Concurrency limit exceeded: {$stats['current']}/{$stats['max']} analyses running. " .
          "Please wait for an analysis to complete before starting a new one."
        );
      }

      $startTime = microtime(true);

      $ctx = [
        'product_id'      => $productId,
        'language_id'     => $languageId,
        'user_id'         => $userId,
        'start_time'      => $startTime,
        'steps_completed' => [],
        'steps_failed'    => [],

      ];

      $this->logger->logStructured('info', 'CockpitAIOrchestrator', 'pipeline_start', [
        'product_id'  => $productId,
        'language_id' => $languageId,
        'user_id'     => $userId,
        'timestamp'   => date('Y-m-d H:i:s'),
      ]);

      try {
        // ── Step 1: Data Collection (CRITICAL) ────────────────────────────
        $ctx = $this->runner->run(1, 'data_collection', function () use ($productId, $languageId): array {
          return $this->dataCollector->collect($productId, $languageId);
        }, $ctx, critical: true);

        $ctx['product_data'] = $ctx['data_collection'];

        // ── Step 2: Catalog Normalization ─────────────────────────────────
        $ctx = $this->runner->run(2, 'catalog_normalization', function (): object {
          return $this->scoringEngine->computeCatalogNormalization();
        }, $ctx, critical: false);

        // ── Step 3: Scoring Calculation (CRITICAL) ────────────────────────
        $ctx = $this->runner->run(3, 'scoring_calculation', function (array $context): array {
          // velocityMax : utilise la vélocité du produit courant comme référence relative.
          // Dans le pipeline produit-par-produit, stock_velocity du produit analysé
          // est la meilleure approximation disponible sans parcourir tout le catalogue.
          // calculateVelocityMax(allProducts) sera utilisé dans un futur bulk-analysis.
          $velocityMax = max(1.0, (float)($context['product_data']['stock_velocity'] ?? 1.0));

          $scoringContext = $this->contextBuilder->build(
            productData: $context['product_data'],
            catalog:     $context['catalog_normalization'],
            history:     [],
            languageId:  $context['language_id'],
            userId:      (int) $context['user_id'],
            velocityMax: $velocityMax,
          );
          $context['strategy_preferences'] = $this->contextBuilder->getStrategyPreferences();

          return $this->scoringEngine->computeScores($context['product_data'], $scoringContext);
        }, $ctx, critical: true);

        // ── Step 4: SEO Analysis (conditional) ───────────────────────────
        // Timeout: 5s (handled by PipelineRunner)
        // Fallback: mark seo_status='NOT_ANALYZED', continue
        $ctx = $this->runner->run(4, 'seo_analysis', function (array $context) use ($productId, $languageId): array {
          return $this->seoInvoker->invoke($productId, $languageId, $context['product_data']);
        }, $ctx, critical: false);

        // ── Step 5: RAG Context Retrieval ─────────────────────────────────
        $ctx = $this->runner->run(5, 'rag_context_retrieval', function () use ($productId, $languageId): array {
          return $this->embeddingService->getHistoricalContext($productId, $languageId, 3);
        }, $ctx, critical: false);


        // ── Step 6: LLM Analysis Generation ──────────────────────────────
        $ctx = $this->runner->run(6, 'llm_analysis_generation', function (array $context): array {
          return $this->generateLLMAnalysis($context);
        }, $ctx, critical: false);

        // ── Step 7: Rules Engine Execution ────────────────────────────────
        $ctx = $this->runner->run(7, 'rules_engine_execution', function (array $context): array {
          $scores  = $context['scoring_calculation'];
          $product = $context['product_data'];

          // Build separate ScoreResult shapes for X and Y so that
          // RecommendationEngine::buildContext() can read ['score'] and ['factors']
          // independently on each axis (fix: previously both were the same $scores array,
          // causing score_x and score_y to always resolve to 0.0 in RuleContext).
          $scoreXResult = [
            'score'   => $scores['score_x']   ?? 0.0,
            'factors' => $scores['factors_x'] ?? [],
          ];
          $scoreYResult = [
            'score'   => $scores['score_y']   ?? 0.0,
            'factors' => $scores['factors_y'] ?? [],
          ];

          return $this->recommendationEngine->generateActions(
            scoreXResult: $scoreXResult,
            scoreYResult: $scoreYResult,
            quadrant:     $scores['quadrant'],
            productData:  $product,
            config: [
              'seo_status'       => $product['seo_status'] ?? 'NOT_ANALYZED',
              'seo_score'        => $product['seo_score']  ?? null,
              'return_threshold' => $this->contextBuilder->getThresholds()['T_low'] / 100,
            ],
          );
        }, $ctx, critical: false);

        // ── Step 8: Embedding Persistence ─────────────────────────────────
        $ctx = $this->runner->run(8, 'embedding_persistence', function (array $context) use ($productId, $languageId): ?int {
          $metadata = $this->reportBuilder->buildMetadata(
            $context,
            $this->embeddingService->getEmbeddingFormatVersion()
          );
          return $this->embeddingService->storeEmbedding($metadata, $productId, $languageId);
        }, $ctx, critical: false);


       // ── Step 9: Rapport & Auto-Pilote (REQ-EXE-01) ────────────────────

        // 1. Construction du rapport final à partir du contexte
        $report = $this->reportBuilder->buildReport($ctx);

        // 2. Exécution automatique si le mode AUTO est activé
        if (defined('CLICSHOPPING_APP_ECOMMERCE_CAI_AUTO_MODE') && CLICSHOPPING_APP_ECOMMERCE_CAI_AUTO_MODE === 'True') {
          // On utilise la méthode centralisée pour éviter les doublons de code
          $report = $this->processAutoPilot($productId, $ctx, $report);
        }

        $this->logger->logStructured('info', 'CockpitAIOrchestrator', 'pipeline_complete', [
          'product_id'      => $productId,
          'duration_ms'     => round((microtime(true) - $startTime) * 1000, 2),
          'steps_completed' => count($ctx['steps_completed']),
          'steps_failed'    => count($ctx['steps_failed']),
          'timestamp'       => date('Y-m-d H:i:s'),
        ]);

        // Log completion (Requirement 21.5) - On utilise bien le $userId validé
        $this->security->logAnalysisRequest($productId, $languageId, $userId, 'completed', [
          'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ]);

        $this->concurrencyManager->releaseSlot($userId);

        if ($this->debug) {
          error_log("--------------------");
          error_log("[COCPITAI - End of analysis");
          error_log("--------------------");
        }

        return $report;
      } catch (\Exception $e) {
        $this->logger->logStructured('error', 'CockpitAIOrchestrator', 'pipeline_failed', [
          'product_id'      => $productId,
          'error'           => $e->getMessage(),
          'duration_ms'     => round((microtime(true) - $startTime) * 1000, 2),
          'steps_completed' => count($ctx['steps_completed'] ?? []),
          'steps_failed'    => count($ctx['steps_failed']    ?? []),
          'timestamp'       => date('Y-m-d H:i:s'),
        ]);

        // Log analysis request failure (Requirement 21.5)
        $this->security->logAnalysisRequest($productId, $languageId, $userId, 'failed', [
          'error' => $e->getMessage(),
          'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
        ]);

        // Release concurrency slot on error
        $this->concurrencyManager->releaseSlot($userId);

        throw $e;
      }
    }

    /**
     * Step 6 — LLM Analysis Generation via Gpt::class.
     * Task 7.7: Implemented via LlmAnalysisGenerator.
     *
     * Requirements 10.6, 12.1-12.8:
     * - Constructs prompt with scores, valid factors only, RAG context, strategies, actions
     * - Calls LLM via Gpt::getGptResponse()
     * - Timeout 5s (handled by PipelineRunner)
     * - Fallback: generic analysis if LLM fails
     */
    private function generateLLMAnalysis(array $context): array
    {
      return $this->llmGenerator->generate($context);
    }

    /**
     * Centralisation de l'exécution Auto-Pilote (Step 9)
     * Vérifie les 7 points de validation via ActionExecutor -> ActionValidator
     */
    private function processAutoPilot(int $productId, array $ctx, array $report): array
    {
      try {
        $executor = new ActionExecutor();

        // Préparation des données pour le validateur
        $productData = $ctx['product_data'] ?? [];
        $productData['scores'] = [
          'x' => $ctx['scoring_calculation']['score_x'] ?? 0,
          'y' => $ctx['scoring_calculation']['score_y'] ?? 0
        ];
        $productData['quadrant'] = $ctx['scoring_calculation']['quadrant'] ?? 'unknown';
        $productData['language_id'] = $ctx['language_id'] ?? 1;

        $actions = [];
        if (isset($ctx['rules_engine_execution']) && is_array($ctx['rules_engine_execution'])) {
          foreach ($ctx['rules_engine_execution'] as $actionItem) {
            if (is_object($actionItem) && method_exists($actionItem, 'toArray')) {
              // On utilise la méthode de ton fichier Action.php
              $actions[] = $actionItem->toArray();
            } else {
              $actions[] = (array)$actionItem;
            }
          }
        }

        if ($this->debug) {
          error_log("[CockpitAI Debug] Auto-Pilot Start for product $productId. Actions count: " . count($actions));
        }

        if (!empty($actions)) {
          $executionResults = $executor->executePlan($productId, $actions, $productData);
          $report['technical']['execution_results'] = $executionResults;
        }

      } catch (\Throwable $e) {
        error_log("[CockpitAI Error] processAutoPilot failed: " . $e->getMessage());
      }

      return $report;
    }
    /**
     * Execute the full CockpitAI analysis pipeline from a CronJob context.
     *
     * Identical to executeAnalysis() in every pipeline step, with two differences:
     *
     *  1. Security: skips validateUserPermissions() (session-based) and
     *     sanitizeAndValidateInputs() (DB existence checks are still done internally
     *     by DataCollector — if the product doesn't exist, Step 1 throws and aborts).
     *
     *  2. ConcurrencyManager: uses a dedicated 'cron' slot that does not compete
     *     with regular user slots.
     *
     * The result is stored in products_cockpit_ai_embedding  (Step 8) exactly as a manual analysis,
     * so the RAG historical context grows with each daily cron run.
     *
     * @param int    $productId   Product to analyse
     * @param int    $languageId  Language for analysis and RAG filtering
     * @param string $cronUserId  System user identifier for audit log (e.g. 'cron')
     * @return array              Analysis_Report (same shape as executeAnalysis())
     * @throws \Exception         If a critical step fails (Steps 1 or 3)
     */
    public function executeAnalysisCron(int $productId, int $languageId, string $cronUserId = 'cron'): array
    {
      $startTime = microtime(true);

      // --- LOGIQUE DE VERSIONING : Détection du rafraîchissement ---
      $QlastEmbedding = $this->db->get('products_cockpit_ai_embedding ', 'date_added', ['product_id' => $productId], ['date_added' => 'desc'], 1);

      $QlastLogFlag = $this->db->get('products_cockpit_ai_action_log', 'date_created', [
        'product_id' => $productId,
        'action_type' => 'system_update_flag'
      ], ['date_created' => 'desc'], 1);

      $forceRefresh = false;
      if ($QlastEmbedding->check() && $QlastLogFlag->check()) {
        // Si le flag de modification est plus récent que la dernière analyse, on force l'IA
        if (strtotime($QlastLogFlag->value('date_created')) > strtotime($QlastEmbedding->value('date_added'))) {
          $forceRefresh = true;
        }
      }

      // Initialisation du contexte complet (sans écrasement)
      $ctx = [
        'product_id'      => $productId,
        'language_id'     => $languageId,
        'user_id'         => $cronUserId,
        'start_time'      => $startTime,
        'force_refresh'   => $forceRefresh, // Flag injecté pour le Step 4
        'steps_completed' => [],
        'steps_failed'    => [],
      ];

      $this->logger->logStructured('info', 'CockpitAIOrchestrator', 'cron_pipeline_start', [
        'product_id'    => $productId,
        'language_id'   => $languageId,
        'user_id'       => $cronUserId,
        'force_refresh' => $forceRefresh,
        'timestamp'     => date('Y-m-d H:i:s'),
      ]);

      try {
        // ── Step 1: Data Collection (CRITICAL) ────────────────────────────
        $ctx = $this->runner->run(1, 'data_collection', function () use ($productId, $languageId): array {
          return $this->dataCollector->collect($productId, $languageId);
        }, $ctx, critical: true);

        $ctx['product_data'] = $ctx['data_collection'];

        // ── Step 2: Catalog Normalization ─────────────────────────────────
        $ctx = $this->runner->run(2, 'catalog_normalization', function (): object {
          return $this->scoringEngine->computeCatalogNormalization();
        }, $ctx, critical: false);

        // ── Step 3: Scoring Calculation (CRITICAL) ────────────────────────
        $ctx = $this->runner->run(3, 'scoring_calculation', function (array $context): array {
          $velocityMax = max(1.0, (float)($context['product_data']['stock_velocity'] ?? 1.0));

          $scoringContext = $this->contextBuilder->build(
            productData: $context['product_data'],
            catalog:     $context['catalog_normalization'],
            history:     [],
            languageId:  $context['language_id'],
            userId:      0, // no real user in cron context
            velocityMax: $velocityMax,
          );
          $context['strategy_preferences'] = $this->contextBuilder->getStrategyPreferences();

          return $this->scoringEngine->computeScores($context['product_data'], $scoringContext);
        }, $ctx, critical: true);

        // ── Step 4: SEO Analysis (conditional) ───────────────────────────
        $ctx = $this->runner->run(4, 'seo_analysis', function (array $context) use ($productId, $languageId): array {
          return $this->seoInvoker->invoke($productId, $languageId, $context['product_data']);
        }, $ctx, critical: false);

        // ── Step 5: RAG Context Retrieval ─────────────────────────────────
        $ctx = $this->runner->run(5, 'rag_context_retrieval', function () use ($productId, $languageId): array {
          return $this->embeddingService->getHistoricalContext($productId, $languageId, 3);
        }, $ctx, critical: false);

        // ── Step 6: LLM Analysis Generation ──────────────────────────────
        $ctx = $this->runner->run(6, 'llm_analysis_generation', function (array $context): array {
          return $this->generateLLMAnalysis($context);
        }, $ctx, critical: false);

        // ── Step 7: Rules Engine Execution ────────────────────────────────
        $ctx = $this->runner->run(7, 'rules_engine_execution', function (array $context): array {
          $scores  = $context['scoring_calculation'];
          $product = $context['product_data'];

          $scoreXResult = [
            'score'   => $scores['score_x']   ?? 0.0,
            'factors' => $scores['factors_x'] ?? [],
          ];
          $scoreYResult = [
            'score'   => $scores['score_y']   ?? 0.0,
            'factors' => $scores['factors_y'] ?? [],
          ];

          return $this->recommendationEngine->generateActions(
            scoreXResult: $scoreXResult,
            scoreYResult: $scoreYResult,
            quadrant:     $scores['quadrant'],
            productData:  $product,
            config: [
              'seo_status'       => $product['seo_status'] ?? 'NOT_ANALYZED',
              'seo_score'        => $product['seo_score']  ?? null,
              'return_threshold' => $this->contextBuilder->getThresholds()['T_low'] / 100,
            ],
          );
        }, $ctx, critical: false);



        // ── Step 8: Embedding Persistence ─────────────────────────────────
        $ctx = $this->runner->run(8, 'embedding_persistence', function (array $context) use ($productId, $languageId): ?int {
          $rawScores = $context['scoring_calculation'] ?? [];

          // On construit manuellement SANS passer par buildMetadata pour éviter l'erreur de log
          $finalMetadata = [
            'version'                  => $this->embeddingService->getEmbeddingFormatVersion(),
            'embedding_format_version' => $this->embeddingService->getEmbeddingFormatVersion(),
            'schema'                   => 'cockpit_ai_v1',
            'entity_id'                => (int)$productId,
            'scores' => [
              'score_x'   => (float)($rawScores['score_x'] ?? 0),
              'score_y'   => (float)($rawScores['score_y'] ?? 0),
              'factors_x' => $rawScores['factors_x'] ?? [],
              'factors_y' => $rawScores['factors_y'] ?? [],
              'quadrant'  => $rawScores['quadrant'] ?? 'Q_intermediate'
            ],
            'factors_x'         => $rawScores['factors_x'] ?? [], // Doublon racine
            'factors_y'         => $rawScores['factors_y'] ?? [], // Doublon racine
            'analysis'          => $context['llm_analysis_generation'] ?? [],
            'inventory_metrics' => $context['product_data'] ?? [],
            'seo'               => $context['seo_analysis'] ?? [],
            'strategy'          => $context['strategy_preferences'] ?? [],
            'commercial_metrics'=> $context['commercial_analysis'] ?? [],
            'actions'           => $context['rules_engine_actions'] ?? [],
          ];

          // On appelle directement le service
          return $this->embeddingService->storeEmbedding($finalMetadata, $productId, $languageId);
        }, $ctx, critical: false);

// ── Step 9: Rapport & Auto-Pilote (REQ-EXE-01) ────────────────────
        $report = $this->reportBuilder->buildReport($ctx);

        // Activation réelle de l'exécution automatique pour le CRON
        if (defined('CLICSHOPPING_APP_ECOMMERCE_CAI_AUTO_MODE') && CLICSHOPPING_APP_ECOMMERCE_CAI_AUTO_MODE === 'True') {
          $report = $this->processAutoPilot($productId, $ctx, $report);
        }

        $this->logger->logStructured('info', 'CockpitAIOrchestrator', 'cron_pipeline_complete', [
          'product_id'      => $productId,
          'language_id'     => $languageId,
          'duration_ms'     => round((microtime(true) - $startTime) * 1000, 2),
          'steps_completed' => count($ctx['steps_completed']),
          'steps_failed'    => count($ctx['steps_failed']),
          'timestamp'       => date('Y-m-d H:i:s'),
        ]);

        return $report;

      } catch (\Exception $e) {
        $this->logger->logStructured('error', 'CockpitAIOrchestrator', 'cron_pipeline_failed', [
          'product_id'  => $productId,
          'language_id' => $languageId,
          'error'       => $e->getMessage(),
          'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
          'timestamp'   => date('Y-m-d H:i:s'),
        ]);

        throw $e;
      }
    }

    /**
     * Méthode de nettoyage logique (Flag) appelée par les Hooks
     * Ne supprime rien (Versioning), mais demande un refresh au prochain passage.
     */
    public function clearCockpitCache(int $productId): void
    {
      $this->db->save('products_cockpit_ai_action_log', [
        'product_id' => (int)$productId,
        'action_type' => 'system_update_flag',
        'status' => 'executed',
        'validation_reason' => 'Data change detected. Requesting fresh AI analysis.',
        'date_created' => 'now()'
      ]);

      if ($this->debug) {
        error_log("[CockpitAI] Versioning flag set for product $productId");
      }
    }
  }