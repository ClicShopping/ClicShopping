<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Agent;

use ClicShopping\AI\Config\AutonomousConfig;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\LocalObjective;
use ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous\ObjectiveRegistry;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * AnalyticsObjectiveRunner - Autonomous self-optimization concern of the AnalyticsAgent.
 *
 * Extracted from AnalyticsAgent (god-class decomposition): the agent's autonomous-objective
 * lifecycle (create / execute / collaborate) and its optimization handlers (currently
 * placeholders). AnalyticsAgent delegates its createLocalObjective()/executeObjective()/
 * canCollaborate() interface methods here, behaviour unchanged.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Agent
 * @since 2026-06-10
 */
class AnalyticsObjectiveRunner
{
  private AutonomousConfig $autonomousConfig;
  private bool $debug;
  private SecurityLogger $securityLogger;

  public function __construct(AutonomousConfig $autonomousConfig, bool $debug, SecurityLogger $securityLogger)
  {
    $this->autonomousConfig = $autonomousConfig;
    $this->debug = $debug;
    $this->securityLogger = $securityLogger;
  }

  /**
   * Create a local optimization objective for the analytics domain.
   *
   * @param string $goalStatement
   * @param array $successCriteria
   * @param string $priority
   * @return LocalObjective
   */
  public function createLocalObjective(
    string $goalStatement,
    array $successCriteria,
    string $priority
  ): LocalObjective {

    if (!$this->autonomousConfig->canAgentCreateObjectives('AnalyticsAgent')) {
      throw new \RuntimeException('AnalyticsAgent is not authorized to create objectives (disabled in configuration)');
    }

    // Estimate completion time based on priority
    $estimatedTime = match ($priority) {
      'critical' => 300,  // 5 minutes
      'high' => 900,      // 15 minutes
      'medium' => 1800,   // 30 minutes
      'low' => 3600,      // 1 hour
      default => 1800
    };

    $objective = new LocalObjective(
      'AnalyticsAgent',
      $goalStatement,
      $successCriteria,
      $priority,
      $estimatedTime
    );

    // Register with ObjectiveRegistry (it resolves its own Db/dependencies internally).
    $objectiveRegistry = new ObjectiveRegistry();
    $objectiveRegistry->registerObjective($objective);

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "AnalyticsAgent created objective: {$goalStatement}",
        'info'
      );
    }

    return $objective;
  }

}
