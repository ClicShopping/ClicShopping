<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * BuildResponseStage
 *
 * Tenth stage of the orchestration pipeline. Builds the final orchestration response from the
 * execution result and the resolved entity identity.
 *
 * Carries sequencing only — it delegates to the already-injected {@see ResponseProcessor} component
 * (passing the {@see LlmResponseProcessor}). Writes the built response onto the pipeline context.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\CoreAI\Response\LlmResponseProcessor;
use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;

class BuildResponseStage implements OrchestrationStageInterface
{
  public function __construct(
    private ResponseProcessor $responseProcessorComponent,
    private LlmResponseProcessor $responseProcessor
  ) {
  }

  public function id(): string
  {
    return 'build_response';
  }

  public function run(OrchestrationContext $context): ?array
  {
    $context->response = $this->responseProcessorComponent->buildOrchestrationResponse(
      $context->executionResult,
      $context->intent,
      $context->query,
      $context->startTime,
      $context->entityId,
      $context->entityType,
      $this->responseProcessor
    );

    return null;
  }
}
