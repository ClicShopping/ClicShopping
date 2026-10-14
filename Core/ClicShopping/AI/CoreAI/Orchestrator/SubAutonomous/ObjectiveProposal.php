<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

/**
 * ObjectiveProposal — immutable remediation proposed for a LocalObjective (§Z Z3).
 *
 * Agnostic value object: `type` and `payload` are domain-defined; `description` is the
 * human-readable string the critic scores. The engine never interprets the payload.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 2026-06-23
 */
final class ObjectiveProposal
{
  public function __construct(
    private readonly string $type,
    private readonly array $payload,
    private readonly string $description
  ) {
  }

  public function getType(): string
  {
    return $this->type;
  }

  public function getPayload(): array
  {
    return $this->payload;
  }

  public function getDescription(): string
  {
    return $this->description;
  }

  public function toArray(): array
  {
    return [
      'type' => $this->type,
      'payload' => $this->payload,
      'description' => $this->description,
    ];
  }
}
