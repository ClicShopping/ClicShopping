<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * OrchestrationContext
 *
 * Agnostic, typed state holder threaded through the orchestration stage pipeline
 * (see OrchestrationStage / StageRegistry). It carries the immutable query inputs and the
 * intermediate results each stage produces — purely generic orchestration state, with NO domain
 * or brand-specific fields, so the pipeline stays agnostic and reusable across domains.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

class OrchestrationContext
{
  /** Original user query (immutable). */
  public readonly string $query;

  /** Resolved/enriched query a stage may rewrite (e.g. contextual-reference resolution). */
  public string $queryToProcess;

  /** Orchestration start time, for performance accounting (immutable). */
  public readonly float $startTime;

  // ---- Intermediate state, populated by stages as the pipeline progresses ----

  public string $executionId = '';
  public array $rawContext = [];
  public array $context = [];
  public array $contextDecision = [];
  public array $contextAnalysis = [];
  public array $intent = [];
  public string $intentType = '';
  public array $enrichedContext = [];
  public array $complexityDetection = [];
  public array $validation = [];
  public ?object $plan = null;
  public array $validationResults = [];
  public array $executionResult = [];
  public mixed $entityId = null;
  public string $entityType = 'unknown';
  public array $response = [];

  public function __construct(string $query, string $queryToProcess, float $startTime)
  {
    $this->query = $query;
    $this->queryToProcess = $queryToProcess;
    $this->startTime = $startTime;
  }
}
