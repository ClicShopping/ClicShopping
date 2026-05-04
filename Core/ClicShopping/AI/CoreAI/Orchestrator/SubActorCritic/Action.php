<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic;

/**
 * Action class
 * 
 * Represents an action to be executed by an actor agent.
 * Contains all necessary information for action execution including
 * type, parameters, context, priority, and estimated execution time.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class Action
{
    private string $actionId;
    private string $actionType;
    private array $parameters;
    private Context $context;
    private string $priority; // 'low', 'medium', 'high', 'critical'
    private int $estimatedExecutionTime; // seconds
    
    public function __construct(
        string $actionType,
        array $parameters,
        Context $context,
        string $priority = 'medium',
        int $estimatedExecutionTime = 60
    ) {
        $this->actionId = $this->generateId();
        $this->actionType = $actionType;
        $this->parameters = $parameters;
        $this->context = $context;
        $this->priority = $priority;
        $this->estimatedExecutionTime = $estimatedExecutionTime;
    }
    
    public function getActionId(): string { return $this->actionId; }
    public function getType(): string { return $this->actionType; }
    public function getParameters(): array { return $this->parameters; }
    public function getContext(): Context { return $this->context; }
    public function getPriority(): string { return $this->priority; }
    public function getEstimatedExecutionTime(): int { return $this->estimatedExecutionTime; }
    
    private function generateId(): string
    {
        return 'action_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
    
    public function toArray(): array
    {
        return [
            'action_id' => $this->actionId,
            'action_type' => $this->actionType,
            'parameters' => $this->parameters,
            'context' => $this->context->toArray(),
            'priority' => $this->priority,
            'estimated_execution_time' => $this->estimatedExecutionTime
        ];
    }
}