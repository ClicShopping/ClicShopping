<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\InterfacesAI;

use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Action;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActionResult;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Context;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\ActorCapability;
use ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\Feedback;

/**
 * Interface for Actor agents (execution specialists)
 * 
 * Defines the contract for all actor agents focused on action execution.
 * Actor agents are responsible for executing actions and producing outputs,
 * but do not evaluate peer outputs (that's the critic's role).
 * 
 * This interface enables the Actor-Critic separation architecture where
 * actors focus exclusively on execution while critics handle evaluation.
 * 
 * @package ClicShopping\AI\InterfacesAI
 * @version 1.0.0
 * @since 2026-01-30
 */
interface ActorAgentInterface
{
    /**
     * Execute an action and produce a result
     * 
     * @param Action $action The action to execute
     * @return ActionResult The execution result with output and metrics
     * @throws ActorExecutionException If execution fails
     */
    public function executeAction(Action $action): ActionResult;
    
    /**
     * Propose an action based on current context
     * 
     * @param Context $context Current system context
     * @return Action Proposed action with confidence score
     */
    public function proposeAction(Context $context): Action;
    
    /**
     * Get actor capabilities
     * 
     * @return array<string, ActorCapability> Map of action types to capabilities
     */
    public function getCapabilities(): array;
    
    /**
     * Evaluate confidence for executing a specific action
     * 
     * @param Action $action Action to evaluate
     * @return float Confidence score (0.0-1.0)
     */
    public function evaluateConfidence(Action $action): float;
    
    /**
     * Receive feedback from critics
     * 
     * @param Feedback $feedback Aggregated feedback from evaluation
     * @return void
     */
    public function receiveFeedback(Feedback $feedback): void;
    
    /**
     * Get unique actor identifier
     * 
     * @return string Actor ID
     */
    public function getActorId(): string;
}