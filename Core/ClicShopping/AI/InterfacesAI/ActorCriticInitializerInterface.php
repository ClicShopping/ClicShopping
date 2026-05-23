<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * Interface ActorCriticInitializerInterface
 *
 * Defines the contract for initializing the Actor-Critic system.
 * Handles registration of actors and critics based on configuration.
 *
 * @package ClicShopping\AI\InterfacesAI
 */
interface ActorCriticInitializerInterface
{
  /**
   * Initialize Actor-Critic system
   *
   * Creates registries and registers all enabled actors and critics.
   * Registration is controlled by AgentActorsConfig and AgentCriticsConfig.
   *
   * @param int $languageId Language ID for actors/critics
   * @param bool $debug Debug mode flag for logging
   * @return array Initialization result with counts and status
   *               [
   *                 'actors_registered' => int,
   *                 'critics_registered' => int,
   *                 'actors_config_enabled' => bool,
   *                 'critics_config_enabled' => bool,
   *                 'success' => bool
   *               ]
   * @throws \Exception If initialization fails
   */
  public function initialize(int $languageId, bool $debug): array;

  /**
   * Get initialization statistics
   *
   * Returns statistics from the last initialization attempt.
   *
   * @return array Statistics (actors_registered, critics_registered, etc.)
   */
  public function getStats(): array;
}
