<?php
/**
 * AgentAbstentionIntegration
 *
 * Integration helper for agent abstention logic in autonomous agent execution flow.
 * Provides methods to integrate confidence evaluation, abstention decisions,
 * delegation, and escalation into agent task execution.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAbstention
 * @since 2026-01-28
 * @author ClicShopping Team
 *
 * Requirements: 15.1, 15.2, 15.3
 * Task: 25.3 - Integrate abstention logic with autonomous agents
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAbstention;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

class AgentAbstentionIntegration
{
    /**
     * @var AgentAbstentionManager Abstention manager instance
     */
    private AgentAbstentionManager $abstentionManager;

    /**
     * @var \ClicShopping\OM\Db Database instance
     */
    private $db;

    /**
     * @var string Database table prefix
     */
    private string $tablePrefix;

    /**
     * @var bool Debug mode
     */
    private bool $debug;

    /**
     * Constructor
     *
     * @param bool $debug Enable debug logging
     */
    public function __construct(bool $debug = false)
    {
        $this->abstentionManager = new AgentAbstentionManager();
        $this->db = Registry::get('Db');
        $this->tablePrefix = CLICSHOPPING::getConfig('db_table_prefix');
        $this->debug = $debug;
    }

    /**
     * Execute task with abstention logic
     *
     * Wraps task execution with confidence evaluation and abstention checks.
     * Flow:
     * 1. Evaluate confidence for the task
     * 2. Check if agent should abstain
     * 3. If abstaining: escalate to human operator
     * 4. If delegating: find and delegate to capable peer
     * 5. If executing: proceed with task execution
     *
     * @param string $agentId Agent identifier
     * @param string $taskId Task identifier
     * @param string $taskType Type of task
     * @param array $context Task context
     * @param callable $executeCallback Callback to execute the task
     * @return array Execution result with status and output
     *
     * Requirements: 15.1, 15.2, 15.3
     */
    public function executeWithAbstentionCheck(
        string $agentId,
        string $taskId,
        string $taskType,
        array $context,
        callable $executeCallback
    ): array {
        // Step 1: Evaluate confidence
        $confidence = $this->abstentionManager->evaluateConfidence(
            $agentId,
            $taskId,
            array_merge($context, ['task_type' => $taskType])
        );

        if ($this->debug) {
            error_log("Agent $agentId confidence for task $taskId: $confidence");
        }

        // Step 2: Get abstention decision
        $decision = $this->abstentionManager->getAbstentionDecision(
            $agentId,
            $confidence,
            $taskType
        );

        // Step 3: Handle decision
        switch ($decision['action']) {
            case 'abstain':
                return $this->handleAbstention($agentId, $taskId, $taskType, $confidence, $decision);

            case 'delegate':
                return $this->handleDelegation($agentId, $taskId, $taskType, $confidence, $decision, $executeCallback);

            case 'execute':
            default:
                return $this->handleExecution($agentId, $taskId, $taskType, $confidence, $executeCallback);
        }
    }

    /**
     * Handle abstention - escalate to human operator
     *
     * @param string $agentId Agent identifier
     * @param string $taskId Task identifier
     * @param string $taskType Task type
     * @param float $confidence Confidence score
     * @param array $decision Abstention decision
     * @return array Result with escalation status
     *
     * Requirement 15.2: Escalate to human operator when confidence below threshold
     */
    private function handleAbstention(
        string $agentId,
        string $taskId,
        string $taskType,
        float $confidence,
        array $decision
    ): array {
        // Log abstention
        $abstentionId = $this->abstentionManager->logAbstention(
            $agentId,
            $taskId,
            $taskType,
            $confidence,
            $decision['reason'],
            'escalate_human',
            null
        );

        if ($this->debug) {
            error_log("Agent $agentId abstained from task $taskId (confidence: $confidence)");
        }

        // Create escalation alert
        $this->createEscalationAlert($agentId, $taskId, $taskType, $confidence, $decision['reason']);

        return [
            'status' => 'abstained',
            'action' => 'escalate_human',
            'confidence' => $confidence,
            'reason' => $decision['reason'],
            'abstention_id' => $abstentionId,
            'message' => "Task requires human operator review due to low confidence ($confidence)"
        ];
    }

    /**
     * Create escalation alert for human operator
     *
     * @param string $agentId Agent identifier
     * @param string $taskId Task identifier
     * @param string $taskType Task type
     * @param float $confidence Confidence score
     * @param string $reason Abstention reason
     */
    private function createEscalationAlert(
        string $agentId,
        string $taskId,
        string $taskType,
        float $confidence,
        string $reason
    ): void {
        try {
            $tableName = ':table_rag_agent_administrator_alerts';

            $query = "
                INSERT INTO {$tableName} (
                    alert_type,
                    severity,
                    agent_id,
                    message,
                    details,
                    created_at
                ) VALUES (
                    'agent_abstention',
                    'medium',
                    :agent_id,
                    :message,
                    :details,
                    NOW()
                )
            ";

            $message = "Agent $agentId abstained from task $taskId due to low confidence";
            $details = json_encode([
                'task_id' => $taskId,
                'task_type' => $taskType,
                'confidence' => $confidence,
                'reason' => $reason,
                'action_required' => 'human_review'
            ]);

            $Qinsert = $this->db->prepare($query);
            $Qinsert->bindValue(':agent_id', $agentId);
            $Qinsert->bindValue(':message', $message);
            $Qinsert->bindValue(':details', $details);
            $Qinsert->execute();
        } catch (\Exception $e) {
            error_log("Failed to create escalation alert: " . $e->getMessage());
        }
    }

    /**
     * Handle delegation - delegate to more capable peer
     *
     * @param string $agentId Agent identifier
     * @param string $taskId Task identifier
     * @param string $taskType Task type
     * @param float $confidence Confidence score
     * @param array $decision Delegation decision
     * @param callable $executeCallback Execution callback
     * @return array Result with delegation status
     *
     * Requirement 15.3: Delegate to peer when confidence below delegation threshold
     */
    private function handleDelegation(
        string $agentId,
        string $taskId,
        string $taskType,
        float $confidence,
        array $decision,
        callable $executeCallback
    ): array {
        $suggestedDelegate = $decision['suggested_delegate'];

        // Log abstention with delegation
        $abstentionId = $this->abstentionManager->logAbstention(
            $agentId,
            $taskId,
            $taskType,
            $confidence,
            $decision['reason'],
            'delegate_peer',
            $suggestedDelegate
        );

        if ($this->debug) {
            error_log("Agent $agentId delegating task $taskId to $suggestedDelegate (confidence: $confidence)");
        }

        // If delegate found, attempt delegation
        if ($suggestedDelegate) {
            try {
                // In a real implementation, this would invoke the delegate agent
                // For now, we return delegation information
                return [
                    'status' => 'delegated',
                    'action' => 'delegate_peer',
                    'confidence' => $confidence,
                    'reason' => $decision['reason'],
                    'delegated_to' => $suggestedDelegate,
                    'abstention_id' => $abstentionId,
                    'message' => "Task delegated to $suggestedDelegate (more capable peer)"
                ];
            } catch (\Exception $e) {
                // Delegation failed, escalate to human
                return $this->handleAbstention($agentId, $taskId, $taskType, $confidence, $decision);
            }
        } else {
            // No delegate available, escalate to human
            return $this->handleAbstention($agentId, $taskId, $taskType, $confidence, $decision);
        }
    }

    /**
     * Handle execution - proceed with task execution
     *
     * @param string $agentId Agent identifier
     * @param string $taskId Task identifier
     * @param string $taskType Task type
     * @param float $confidence Confidence score
     * @param callable $executeCallback Execution callback
     * @return array Result with execution output
     */
    private function handleExecution(
        string $agentId,
        string $taskId,
        string $taskType,
        float $confidence,
        callable $executeCallback
    ): array {
        if ($this->debug) {
            error_log("Agent $agentId executing task $taskId (confidence: $confidence)");
        }

        try {
            // Execute the task
            $output = $executeCallback();

            return [
                'status' => 'executed',
                'action' => 'execute',
                'confidence' => $confidence,
                'output' => $output,
                'message' => "Task executed successfully with confidence $confidence"
            ];
        } catch (\Exception $e) {
            // Execution failed
            return [
                'status' => 'failed',
                'action' => 'execute',
                'confidence' => $confidence,
                'error' => $e->getMessage(),
                'message' => "Task execution failed: " . $e->getMessage()
            ];
        }
    }

    /**
     * Get abstention manager instance
     *
     * @return AgentAbstentionManager
     */
    public function getAbstentionManager(): AgentAbstentionManager
    {
        return $this->abstentionManager;
    }
}