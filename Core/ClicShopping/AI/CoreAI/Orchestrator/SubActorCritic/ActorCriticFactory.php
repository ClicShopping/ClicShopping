<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

use ClicShopping\AI\RegistryAI\ActorRegistry;
use ClicShopping\AI\RegistryAI\CriticRegistry;

/**
 * DRY factory for the Actor-Critic subsystem.
 *
 * Centralises the boilerplate wiring (create the actor/critic registries, run the domain
 * builders that self-register into them, then build the coordinator) so each domain
 * (SEO, CockpitAI, …) only supplies its own agents instead of repeating the same setup.
 *
 * Configuration is intentionally NOT read here: the {@see ActorCriticCoordinator} constructor
 * already loads it (AgentSystemConfig / AgentTechnicalConfig / ActorCriticConfig + adaptive
 * weighting). This factory only owns the structural wiring.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 */
final class ActorCriticFactory
{
    /**
     * Build a fully wired coordinator from domain actor/critic builders.
     *
     * Each builder receives the freshly created registry and constructs ONE domain actor/critic;
     * those classes self-register into the registry from their constructor (existing convention),
     * so the builder body is typically just `new XxxActor($debug, $registry, $agent)`.
     *
     * @param array<int, callable(ActorRegistry):void>  $actorBuilders  Domain actor builders.
     * @param array<int, callable(CriticRegistry):void> $criticBuilders Domain critic builders.
     * @return ActorCriticCoordinator Coordinator wired with the registered actors and critics.
     */
    public static function create(array $actorBuilders, array $criticBuilders): ActorCriticCoordinator
    {
        $actorRegistry = new ActorRegistry();
        $criticRegistry = new CriticRegistry();

        foreach ($actorBuilders as $build) {
            $build($actorRegistry);
        }

        foreach ($criticBuilders as $build) {
            $build($criticRegistry);
        }

        return new ActorCriticCoordinator($actorRegistry, $criticRegistry);
    }
}
