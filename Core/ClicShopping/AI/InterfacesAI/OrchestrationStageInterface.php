<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * OrchestrationStageInterface
 *
 * Contract for a single, ordered step of the orchestration pipeline. A stage reads from and writes
 * to the shared {@see OrchestrationContext}; it does NOT own business logic — it delegates to the
 * existing focused components (QueryProcessor, IntentAnalyzer, the Sub* validators, …) and only
 * carries the sequencing.
 *
 * Stages are registered (and re-ordered) through {@see StageRegistry}. Because both this interface
 * and the context are agnostic, a domain App can implement its own stage and register it via the
 * registry — the same agnostic extension pattern used elsewhere (e.g. WebSearchEngineProviderInterface)
 * — without modifying the core orchestrator.
 */

namespace ClicShopping\AI\InterfacesAI;

use ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator\OrchestrationContext;

interface OrchestrationStageInterface
{
  /**
   * Stable, agnostic identifier for this stage. Used by {@see StageRegistry} for positional
   * insertion (insertBefore/insertAfter) and for structured logging. Convention: snake_case,
   * e.g. "prepare_execution_scope".
   */
  public function id(): string;

  /**
   * Run this stage against the shared orchestration context.
   *
   * @param OrchestrationContext $context Mutable orchestration state shared across the pipeline.
   * @return array|null Return a non-null array to SHORT-CIRCUIT the pipeline: that array becomes the
   *                    orchestration result (e.g. an early hybrid route, a clarification, or a
   *                    rejection). Return null to let the pipeline continue to the next stage.
   */
  public function run(OrchestrationContext $context): ?array;
}
