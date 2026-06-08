<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * FinalizeStage
 *
 * Final stage of the orchestration pipeline. Tears down the working-memory scope, records the
 * request event, updates the execution stats consumed by the DiagnosticManager, and (in debug) logs
 * the performance breakdown and an end summary.
 *
 * Carries sequencing only — it delegates to the already-injected components; behaviour is the
 * verbatim former {@see OrchestratorAgent::finalizeOrchestration()}. The orchestrator's execution
 * stats array is shared by reference so the request/time counters update in place.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\CoreAI\Memory\WorkingMemory;
use ClicShopping\AI\Infrastructure\Monitoring\MetricsCollector;
use ClicShopping\AI\Infrastructure\Monitoring\PerformanceTracker;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;
use ClicShopping\AI\Security\SecurityLogger;

class FinalizeStage implements OrchestrationStageInterface
{
  /** Execution-stats array owned by OrchestratorAgent, shared by reference so counters update in place. */
  private array $executionStats;

  public function __construct(
    private WorkingMemory $workingMemory,
    private MetricsCollector $collector,
    private PerformanceTracker $performanceTracker,
    private SecurityLogger $securityLogger,
    private bool $debug,
    array &$executionStats
  ) {
    $this->executionStats = &$executionStats;
  }

  public function id(): string
  {
    return 'finalize';
  }

  public function run(OrchestrationContext $context): ?array
  {
    $startTime = $context->startTime;

    // 11. Cleanup
    $this->workingMemory->deleteScope($context->executionId);

    $array_record = [
      'component' => 'orchestrator',
      'success' => true,
      'execution_time' => microtime(true) - $startTime,
    ];

    $this->collector->recordEvent('request', $array_record);

    // Update execution stats for DiagnosticManager (shared array, mutated by reference)
    $this->executionStats['total_requests']++;
    $this->executionStats['total_execution_time'] += (microtime(true) - $startTime);

    // Phase 5: Log performance breakdown using PerformanceTracker
    if ($this->debug) {
      $this->performanceTracker->logPerformanceBreakdown();

      // End logging for handleFullOrchestration
      $orchestrationDuration = (microtime(true) - $startTime) * 1000;

      error_log("-----------------------------------------");
      error_log("🏁 [INFO END] handleFullOrchestration");
      error_log("✅ [INFO STATUS] Success: " . ($context->executionResult['success'] ?? false ? 'YES' : 'NO'));
      error_log("🎯 [INFO ENTITY] ID: {$context->entityId}, Type: {$context->entityType}");
      error_log("📊 [INFO RESPONSE] Type: " . ($context->response['type'] ?? 'unknown'));
      error_log("⏱️ [INFO DURATION] Total time: " . round($orchestrationDuration, 2) . "ms");
      error_log("[INFO TIME] End time: " . date('Y-m-d H:i:s.u'));
      error_log("------------------------------------------");
    }

    return null;
  }
}
