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
 * ActionResult class
 * 
 * Represents the result of an action execution by an actor agent.
 * Contains the output data, execution metrics, context, and metadata
 * needed for critic evaluation.
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class ActionResult
{
    private string $resultId;
    private string $actionId;
    private string $producerAgentId;
    private mixed $output;
    private string $outputType;
    private array $executionMetrics;
    private Context $executionContext;
    private \DateTimeImmutable $timestamp;
    private string $status; // 'success', 'partial', 'failed'
    
    public function __construct(
        string $actionId,
        string $producerAgentId,
        mixed $output,
        string $outputType,
        array $executionMetrics,
        Context $executionContext,
        string $status = 'success'
    ) {
        $this->resultId = $this->generateId();
        $this->actionId = $actionId;
        $this->producerAgentId = $producerAgentId;
        $this->output = $output;
        $this->outputType = $outputType;
        $this->executionMetrics = $executionMetrics;
        $this->executionContext = $executionContext;
        $this->timestamp = new \DateTimeImmutable();
        $this->status = $status;
    }
    
    public function getResultId(): string { return $this->resultId; }
    public function getActionId(): string { return $this->actionId; }
    public function getProducerAgentId(): string { return $this->producerAgentId; }
    public function getOutput(): mixed { return $this->output; }
    public function getOutputType(): string { return $this->outputType; }
    public function getExecutionMetrics(): array { return $this->executionMetrics; }
    public function getExecutionContext(): Context { return $this->executionContext; }
    public function getTimestamp(): \DateTimeImmutable { return $this->timestamp; }
    public function getStatus(): string { return $this->status; }
    
    private function generateId(): string
    {
        return 'result_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }
    
    public function toArray(): array
    {
        return [
            'result_id' => $this->resultId,
            'action_id' => $this->actionId,
            'producer_agent_id' => $this->producerAgentId,
            'output' => $this->output,
            'output_type' => $this->outputType,
            'execution_metrics' => $this->executionMetrics,
            'execution_context' => $this->executionContext->toArray(),
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'status' => $this->status
        ];
    }
}