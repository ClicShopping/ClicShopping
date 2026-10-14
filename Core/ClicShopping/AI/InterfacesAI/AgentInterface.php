<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

/**
 * Interface for reasoning Agents (autonomous reasoning units)
 *
 * Contract for the reasoning agents of the AI subsystem — the "brains" that
 * carry out LLM-backed work. Distinct from the Actor/Critic ROLES of the
 * actor-critic pattern ({@see ActorInterface}, {@see CriticInterface}):
 * an Agent may be wrapped by an Actor/Critic, but is not itself a graded role.
 *
 * The contract is introspection-oriented (identity, declared capabilities,
 * runtime metrics) so a future AgentRegistry can discover, route to and monitor
 * agents uniformly. It deliberately imposes NO shared execution signature —
 * each agent keeps its own domain entry point.
 *
 * @package ClicShopping\AI\InterfacesAI
 */
interface AgentInterface
{
    /**
     * Get the stable, unique agent identifier.
     *
     * @return string Agent ID
     */
    public function getAgentId(): string;

    /**
     * Get the capabilities the agent declares (discovery / routing).
     *
     * @return array Declared capabilities
     */
    public function getCapabilities(): array;

    /**
     * Get runtime introspection metrics for the agent.
     *
     * @return array Runtime statistics
     */
    public function getStats(): array;
}
