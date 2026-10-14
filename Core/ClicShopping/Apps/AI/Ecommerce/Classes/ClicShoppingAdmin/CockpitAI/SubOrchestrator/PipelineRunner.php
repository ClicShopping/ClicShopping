<?php
  /**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

  namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator;

  use ClicShopping\OM\Registry;
  use ClicShopping\AI\Security\SecurityLogger;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubScoring\CatalogNormalization;
  use ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\EmbeddingService;

  /**
   * PipelineRunner
   *
   * Generic step-execution engine for the CockpitAI 8-step pipeline.
   * (Requirements 10.9, 10.10, 22.1)
   *
   * Responsibilities:
   *  - Run a callable step with try/catch
   *  - Log step start / complete / failed with structured data
   *  - Return the appropriate fallback value for non-critical failures
   *  - Re-throw for critical steps (Steps 1 and 3)
   *
   * This class contains NO business logic — it is purely mechanical.
   * Extracted from CockpitAIOrchestrator to keep the orchestrator thin.
   *
   * ── Fallback table ───────────────────────────────────────────────────────────
   *
   *  Step | Critical | Fallback
   *  ─────┼──────────┼──────────────────────────────────────────────────────────
   *   1   | YES      | throw (pipeline aborts)
   *   2   | no       | CatalogNormalization::defaults()
   *   3   | YES      | throw (pipeline aborts)
   *   4   | no       | ['seo_status' => 'NOT_ANALYZED', 'seo_score' => null]
   *   5   | no       | [] (empty RAG context)
   *   6   | no       | generic analysis text
   *   7   | no       | [] (empty action list)
   *   8   | no       | null (report returned without storage)
   */
  class PipelineRunner
  {
    private SecurityLogger $logger;
    private bool $debug;
    private mixed $embeddingService;

    public function __construct(SecurityLogger $logger, bool $debug = false)
    {
      $this->logger = $logger;
      $this->debug  = $debug;

      Registry::set('EmbeddingService', new EmbeddingService());
      $this->embeddingService = Registry::get('EmbeddingService');
    }

    /**
     * Execute one pipeline step.
     *
     * @param int      $stepNumber   Step number 1–8
     * @param string   $stepKey      Context key under which the result is stored
     * @param callable $fn           The step logic: fn(array $context): mixed
     * @param array    $context      Current pipeline context (passed by value, returned updated)
     * @param bool     $critical     When true, any exception aborts the pipeline
     * @return array                 Updated pipeline context
     * @throws \Exception            When $critical = true and the step throws
     */
    public function run(
      int      $stepNumber,
      string   $stepKey,
      callable $fn,
      array    $context,
      bool     $critical = false,
    ): array {
      $stepStart = microtime(true);

      $this->log('info', "step_{$stepNumber}_start", [
        'step'       => $stepKey,
        'product_id' => $context['product_id'] ?? null,
      ]);

      try {
        $result = $fn($context);

        $context['steps_completed'][] = $stepNumber;
        $context[$stepKey]            = $result;

        $this->log('info', "step_{$stepNumber}_complete", [
          'step'        => $stepKey,
          'duration_ms' => $this->ms($stepStart),
          'product_id'  => $context['product_id'] ?? null,
        ]);

        return $context;

      } catch (\Throwable $e) {
        $context['steps_failed'][] = $stepNumber;

        $this->log('error', "step_{$stepNumber}_failed", [
          'step'        => $stepKey,
          'error'       => $e->getMessage(),
          'duration_ms' => $this->ms($stepStart),
          'product_id'  => $context['product_id'] ?? null,
          'critical'    => $critical,
        ]);

        if ($critical) {
          throw new \Exception(
            "Critical step {$stepNumber} ({$stepKey}) failed: " . $e->getMessage(),
            0,
            $e
          );
        }

        $context[$stepKey] = $this->fallback($stepNumber, $context);

        return $context;
      }
    }

    /**
     * Structured logging — skips info-level in production.
     */
    private function log(string $level, string $event, array $data): void
    {
      if (!$this->debug && $level === 'info') {
        return;
      }

      $this->logger->logStructured(
        $level,
        'CockpitAIOrchestrator',
        $event,
        array_merge($data, [
          'timestamp' => date('Y-m-d H:i:s'),
          'module'    => 'CockpitAI',
        ])
      );
    }

    /**
     * Elapsed milliseconds since $start.
     */
    private function ms(float $start): float
    {
      return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Return the fallback value for a failed non-critical step.
     *
     * @param int   $stepNumber
     * @param array $context
     * @return mixed
     */
    private function fallback(int $stepNumber, array $context): mixed
    {
      return match ($stepNumber) {
        2 => CatalogNormalization::defaults(),
        4 => ['seo_status' => 'NOT_ANALYZED', 'seo_score' => null, 'fallback' => true],
        5 => [],    // empty RAG context
        6 => $this->genericAnalysis($context),
        7 => [],    // empty action list
        8 => null,  // report returned without embedding storage
        default => null,
      };
    }

    /**
     * Analyse LLM générique de secours lorsque l'étape 6 échoue.
     * Optimisée pour lire le cache avant de proposer un texte par défaut.
     */
    private function genericAnalysis(array $context): array
    {
      $productId = (int)($context['product_id'] ?? 0);
      $languageId = (int)($context['language_id'] ?? 1);

      // --- TENTATIVE DE RÉCUPÉRATION DU CACHE (Logique de secours optimisée) ---
      if ($productId > 0) {
        try {
          $latest = $this->embeddingService->getLatestEmbedding($productId, $languageId);

          if (!empty($latest)) {
            return [
              'analysis_text' => $latest['analysis_text'],
              'metadata'      => $latest['metadata'],
              'source'        => 'fallback_cache' // Indique qu'on utilise une archive suite à une erreur
            ];
          }
        } catch (\Throwable $e) {
          // Si le service d'embedding échoue aussi, on continue vers le texte générique
        }
      }

      // --- TEXTE GÉNÉRIQUE PAR DÉFAUT (Si aucun cache n'existe) ---
      $quadrant = $context['scoring_calculation']['quadrant'] ?? 'Q_intermediate';

      return [
        'analysis_text' => "L'analyse en temps réel est indisponible. Basé sur le quadrant " . $quadrant . ", ce produit nécessite une attention sur ses performances commerciales et sa visibilité.",
        'metadata'      => [],
        'source'        => 'fallback_generic'
      ];
    }
  }