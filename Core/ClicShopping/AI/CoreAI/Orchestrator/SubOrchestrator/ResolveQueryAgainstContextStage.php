<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * ResolveQueryAgainstContextStage
 *
 * Third stage of the orchestration pipeline. Analyses how the query relates to the resolved
 * conversation context and, when related, rewrites the query with the context-enriched variant.
 * The relation analysis and the (possibly rewritten) query are mirrored onto working memory.
 *
 * Carries sequencing only — it delegates to the already-injected {@see QueryProcessor}. It reads the
 * resolved conversation context and the current query from the pipeline context, and writes back the
 * context analysis plus the enriched query so the downstream (still inline) steps continue from them.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\CoreAI\Memory\WorkingMemory;
use ClicShopping\AI\Handler\Query\QueryProcessor;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;

class ResolveQueryAgainstContextStage implements OrchestrationStageInterface
{
  public function __construct(
    private QueryProcessor $queryProcessor,
    private WorkingMemory $workingMemory
  ) {
  }

  public function id(): string
  {
    return 'resolve_query_against_context';
  }

  public function run(OrchestrationContext $context): ?array
  {
    // Query-context relation analysis - Delegate to QueryProcessor
    $relationAnalysis = $this->queryProcessor->analyzeQueryContextRelation($context->queryToProcess, $context->context);
    $context->contextAnalysis = $relationAnalysis['context_analysis'];
    $this->workingMemory->set('context_analysis', $context->contextAnalysis);

    // Use enriched query if related to context
    if ($context->contextAnalysis['is_related_to_context']) {
      $context->queryToProcess = $relationAnalysis['enriched_query'];
    }

    $this->workingMemory->set('resolved_query', $context->queryToProcess);

    return null;
  }
}
