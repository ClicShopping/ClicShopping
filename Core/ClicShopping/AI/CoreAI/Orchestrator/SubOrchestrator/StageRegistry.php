<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

/**
 * StageRegistry
 *
 * Agnostic, ordered collection of {@see OrchestrationStageInterface} steps that make up the
 * orchestration pipeline. The core orchestrator builds the default (agnostic) sequence; a domain App
 * can append or positionally insert its own stage — by stage id — without touching the core, the
 * same registration pattern used by the other agnostic registries (e.g. WebSearchEngineRegistry).
 *
 * Insertion helpers fall back to append when the anchor id is unknown, so registration order between
 * domains never throws.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubOrchestrator;

use ClicShopping\AI\InterfacesAI\OrchestrationStageInterface;

class StageRegistry
{
  /** @var OrchestrationStageInterface[] Ordered list of pipeline stages. */
  private array $stages = [];

  /**
   * Append a stage to the end of the pipeline.
   */
  public function append(OrchestrationStageInterface $stage): self
  {
    $this->stages[] = $stage;
    return $this;
  }

  /**
   * Insert a stage immediately before the stage whose id() === $beforeId.
   * Appends if no stage with that id is registered.
   */
  public function insertBefore(string $beforeId, OrchestrationStageInterface $stage): self
  {
    foreach ($this->stages as $i => $existing) {
      if ($existing->id() === $beforeId) {
        array_splice($this->stages, $i, 0, [$stage]);
        return $this;
      }
    }

    return $this->append($stage);
  }

  /**
   * Insert a stage immediately after the stage whose id() === $afterId.
   * Appends if no stage with that id is registered.
   */
  public function insertAfter(string $afterId, OrchestrationStageInterface $stage): self
  {
    foreach ($this->stages as $i => $existing) {
      if ($existing->id() === $afterId) {
        array_splice($this->stages, $i + 1, 0, [$stage]);
        return $this;
      }
    }

    return $this->append($stage);
  }

  /**
   * @return OrchestrationStageInterface[] The ordered stages, ready to be iterated by the orchestrator.
   */
  public function all(): array
  {
    return $this->stages;
  }
}
