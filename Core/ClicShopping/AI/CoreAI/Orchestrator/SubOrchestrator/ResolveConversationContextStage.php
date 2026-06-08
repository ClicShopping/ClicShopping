<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * ResolveConversationContextStage
 *
 * Second stage of the orchestration pipeline. Resolves the conversation context for the request:
 * runs the parallel context fetch, lets QueryProcessor decide the effective context, mirrors both
 * onto working memory, and applies a context switch when the decision requests it.
 *
 * Carries sequencing only — it delegates to the already-injected {@see QueryProcessor} and folds in
 * the verbatim former {@see OrchestratorAgent::handleContextSwitch()}. The resolved conversation
 * context is written onto {@see OrchestrationContext::$context} for the downstream (still inline)
 * steps; the raw context and the context decision are consumed here and not needed further.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\CoreAI\Memory\ConversationMemory;
use ClicShopping\AI\CoreAI\Memory\WorkingMemory;
use ClicShopping\AI\Handler\Query\QueryProcessor;
use ClicShopping\AI\Infrastructure\Monitoring\PerformanceTracker;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;
use ClicShopping\AI\Security\SecurityLogger;

class ResolveConversationContextStage implements OrchestrationStageInterface
{
  public function __construct(
    private QueryProcessor $queryProcessor,
    private WorkingMemory $workingMemory,
    private PerformanceTracker $performanceTracker,
    private ?ConversationMemory $conversationMemory,
    private SecurityLogger $securityLogger,
    private bool $debug
  ) {
  }

  public function id(): string
  {
    return 'resolve_conversation_context';
  }

  public function run(OrchestrationContext $context): ?array
  {
    // Phase 2: Query Processing - Delegate parallel execution to QueryProcessor
    $parallelResult = $this->queryProcessor->executeParallelOperations($context->query);
    $context->rawContext = $parallelResult['raw_context'];

    $this->performanceTracker->addMarker('after_parallel'); // Phase 5: Use PerformanceTracker

    // Phase 2: Query Processing - Delegate context decision to QueryProcessor
    $contextResult = $this->queryProcessor->processContextDecision($context->query, $context->rawContext);
    $context->context = $contextResult['context'];
    $context->contextDecision = $contextResult['context_decision'];

    // Store only a bounded descriptor of the conversation context: the full structure grows with
    // the conversation and overflows WorkingMemory's per-value cap, and nothing reads this key back
    // (it exists for introspection only — the live context flows through the pipeline context and
    // ConversationMemory). A compact descriptor keeps the introspection value without the overflow.
    $this->workingMemory->set('conversation_context', $this->describeContext($context->context));
    $this->workingMemory->set('context_decision', $context->contextDecision);

    $this->applyContextSwitch($context->contextDecision);

    return null;
  }

  /**
   * Build a bounded, introspection-friendly descriptor of the conversation context.
   *
   * The full context can exceed WorkingMemory's per-value cap and has no programmatic consumer, so
   * only its shape is recorded (top-level keys, item count, serialized size).
   *
   * @param array $context Resolved conversation context
   * @return array Compact descriptor safe to store in working memory
   */
  private function describeContext(array $context): array
  {
    return [
      'summary' => true,
      'keys' => array_keys($context),
      'item_count' => count($context),
      'size_bytes' => strlen(serialize($context)),
    ];
  }

  /**
   * Apply a conversation-context switch when the context decision requests it.
   *
   * When clear_conversation_context is set, clears the last tracked entity from the conversation
   * memory (best-effort: failures are logged, not propagated) and logs the switch in debug mode.
   * No-op otherwise. Side-effects only.
   *
   * @param array $contextDecision Context decision from QueryProcessor::processContextDecision()
   * @return void
   */
  private function applyContextSwitch(array $contextDecision): void
  {
    if ($contextDecision['clear_conversation_context'] && $this->conversationMemory) {
      try {
        // Clear the last entity from EntityTracker
        $this->conversationMemory->clearLastEntity();

        if ($this->debug) {
          $this->securityLogger->logSecurityEvent(
            "Cleared last entity due to context switch: " . $contextDecision['reason'],
            'info'
          );
        }
      } catch (\Exception $e) {
        $this->securityLogger->logSecurityEvent(
          "Error clearing last entity: " . $e->getMessage(),
          'warning'
        );
      }
    }

    if ($this->debug && $contextDecision['clear_conversation_context']) {
      $this->securityLogger->logSecurityEvent(
        "Context cleared: " . $contextDecision['reason'],
        'info'
      );
    }
  }
}
