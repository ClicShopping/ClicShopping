<?php
/**
 * AgentAbstentionManager
 *
 * Manages agent abstention decisions based on confidence thresholds.
 * Enables agents to abstain from tasks when confidence is too low,
 * delegate to more capable peers, or escalate to human operators.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAbstention
 * @since 2026-01-28
 * @author ClicShopping Team
 *
 * Requirements: 15.1, 15.2, 15.3, 15.4, 15.5
 * Task: 25.1 - Create AgentAbstentionManager class
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAbstention;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;

class AgentAbstentionManager
{
    /**
     * @var array Default confidence thresholds
     */
    private array $confidenceThresholds = [
        'abstention' => 0.3,  // Below this: abstain and escalate to human
        'delegation' => 0.5   // Below this: delegate to peer agent
    ];

    /**
     * @var array Agent-specific threshold overrides
     */
    private array $agentThresholds = [];

    /**
     * @var \ClicShopping\OM\Db Database instance
     */
    private $db;

    /**
     * @var string Database table prefix
     */
    private string $tablePrefix;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Registry::get('Db');
        $this->tablePrefix = CLICSHOPPING::getConfig('db_table_prefix');
        $this->loadAgentThresholds();
    }

    /**
     * Load agent-specific thresholds from configuration
     */
    private function loadAgentThresholds(): void
    {
        // Load from rag_agent_autonomous_config table if it exists
        try {
            $tableName = ':table_rag_agent_autonomous_config';

            $query = "
                SELECT 
                    config_key,
                    config_value
                FROM {$tableName}
                WHERE config_key LIKE 'confidence_thresholds.%'
            ";

            $result = $this->db->query($query);

            while ($row = $result->fetch()) {
                $configKey = (string)$row['config_key'];
                $agentId = substr($configKey, strlen('confidence_thresholds.'));
                if ($agentId === '') {
                    continue;
                }

                $config = json_decode($row['config_value'], true);
                if (!is_array($config)) {
                    continue;
                }

                if (!isset($this->agentThresholds[$agentId])) {
                    $this->agentThresholds[$agentId] = [];
                }

                if (isset($config['abstention_threshold'])) {
                    $this->agentThresholds[$agentId]['abstention'] = (float)$config['abstention_threshold'];
                }
                if (isset($config['delegation_threshold'])) {
                    $this->agentThresholds[$agentId]['delegation'] = (float)$config['delegation_threshold'];
                }
            }
        } catch (\Exception $e) {
            // Table might not exist yet, use defaults
        }
    }

    /**
     * Evaluate agent's confidence for a task
     *
     * Calculates a confidence score (0.0-1.0) based on:
     * - Agent's historical performance on similar tasks
     * - Task complexity
     * - Available context and information
     * - Agent's registered capabilities
     *
     * @param string $agentId Agent identifier
     * @param string $task Task description or identifier
     * @param array $context Task context and parameters
     * @return float Confidence score (0.0-1.0)
     *
     * Requirement 15.1: Agent SHALL calculate a confidence score
     */
    public function evaluateConfidence(
        string $agentId,
        string $task,
        array $context
    ): float {
        // Base confidence starts at 0.5
        $confidence = 0.5;

        // Factor 1: Check if agent has capability for this task type
        $taskType = $context['task_type'] ?? 'unknown';
        if ($this->hasCapability($agentId, $taskType)) {
            $confidence += 0.2;
        } else {
            $confidence -= 0.3;
        }

        // Factor 2: Historical performance on similar tasks
        $historicalScore = $this->getHistoricalPerformance($agentId, $taskType);
        $confidence += ($historicalScore - 0.5) * 0.4; // Weight historical performance

        // Factor 3: Context completeness
        $contextCompleteness = $this->assessContextCompleteness($context);
        $confidence += ($contextCompleteness - 0.5) * 0.2;

        // Factor 4: Task complexity (if provided)
        if (isset($context['complexity'])) {
            $complexityPenalty = ($context['complexity'] - 0.5) * 0.2;
            $confidence -= $complexityPenalty;
        }

        // Ensure confidence is within bounds [0.0, 1.0]
        return max(0.0, min(1.0, $confidence));
    }

    /**
     * Check if agent has capability for task type
     *
     * @param string $agentId Agent identifier
     * @param string $taskType Task type
     * @return bool True if agent has capability
     */
    private function hasCapability(string $agentId, string $taskType): bool
    {
        try {
            $tableName = ':table_rag_agent_capabilities';

            $query = "
                SELECT COUNT(*) as count
                FROM {$tableName}
                WHERE agent_id = :agent_id
                AND output_type = :task_type
            ";

            $Qcheck = $this->db->prepare($query);
            $Qcheck->bindValue(':agent_id', $agentId);
            $Qcheck->bindValue(':task_type', $taskType);
            $Qcheck->execute();
            $result = $Qcheck->fetch();

            return $result['count'] > 0;
        } catch (\Exception $e) {
            // Table might not exist or query failed, return false
            return false;
        }
    }

    /**
     * Get historical performance score for agent on task type
     *
     * @param string $agentId Agent identifier
     * @param string $taskType Task type
     * @return float Average performance score (0.0-1.0)
     */
    private function getHistoricalPerformance(string $agentId, string $taskType): float
    {
        try {
            $tableName = ':table_rag_agent_evaluations';

            $query = "
                SELECT AVG(overall_score) as avg_score
                FROM {$tableName}
                WHERE producer_agent_id = :agent_id
                AND output_type = :task_type
                AND evaluated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ";

            $Qperf = $this->db->prepare($query);
            $Qperf->bindValue(':agent_id', $agentId);
            $Qperf->bindValue(':task_type', $taskType);
            $Qperf->execute();
            $result = $Qperf->fetch();

            // Default to 0.5 if no historical data
            return $result['avg_score'] ? (float)$result['avg_score'] : 0.5;
        } catch (\Exception $e) {
            // Table might not exist or query failed, return default
            return 0.5;
        }
    }

    /**
     * Assess completeness of task context
     *
     * @param array $context Task context
     * @return float Completeness score (0.0-1.0)
     */
    private function assessContextCompleteness(array $context): float
    {
        $requiredFields = ['task_type', 'description', 'parameters'];
        $presentFields = 0;

        foreach ($requiredFields as $field) {
            if (isset($context[$field]) && !empty($context[$field])) {
                $presentFields++;
            }
        }

        return $presentFields / count($requiredFields);
    }

    /**
     * Determine if agent should abstain from task
     *
     * @param string $agentId Agent identifier
     * @param float $confidence Confidence score
     * @return bool True if agent should abstain
     *
     * Requirement 15.2: Agent SHALL abstain when confidence below critical threshold
     */
    public function shouldAbstain(string $agentId, float $confidence): bool
    {
        $threshold = $this->getAbstentionThreshold($agentId);
        return $confidence < $threshold;
    }

    /**
     * Get abstention threshold for agent
     *
     * @param string $agentId Agent identifier
     * @return float Abstention threshold
     */
    private function getAbstentionThreshold(string $agentId): float
    {
        return $this->agentThresholds[$agentId]['abstention'] 
            ?? $this->confidenceThresholds['abstention'];
    }

    /**
     * Determine if agent should delegate task
     *
     * @param string $agentId Agent identifier
     * @param float $confidence Confidence score
     * @return bool True if agent should delegate
     *
     * Requirement 15.3: Agent SHALL delegate when confidence below delegation threshold
     */
    public function shouldDelegate(string $agentId, float $confidence): bool
    {
        $abstentionThreshold = $this->getAbstentionThreshold($agentId);
        $delegationThreshold = $this->getDelegationThreshold($agentId);

        // Delegate if confidence is between abstention and delegation thresholds
        return $confidence >= $abstentionThreshold && $confidence < $delegationThreshold;
    }

    /**
     * Get delegation threshold for agent
     *
     * @param string $agentId Agent identifier
     * @return float Delegation threshold
     */
    private function getDelegationThreshold(string $agentId): float
    {
        return $this->agentThresholds[$agentId]['delegation']
            ?? $this->confidenceThresholds['delegation'];
    }

    /**
     * Get abstention decision with recommended action
     *
     * Returns a decision object with:
     * - action: 'execute', 'delegate', or 'abstain'
     * - reason: Explanation for the decision
     * - confidence: The confidence score
     * - suggested_delegate: Agent ID to delegate to (if applicable)
     *
     * @param string $agentId Agent identifier
     * @param float $confidence Confidence score
     * @param string $taskType Type of task
     * @return array Abstention decision
     *
     * Requirements: 15.2, 15.3
     */
    public function getAbstentionDecision(
        string $agentId,
        float $confidence,
        string $taskType = 'unknown'
    ): array {
        $abstentionThreshold = $this->getAbstentionThreshold($agentId);
        $delegationThreshold = $this->getDelegationThreshold($agentId);

        if ($confidence >= $delegationThreshold) {
            // High confidence: execute
            return [
                'action' => 'execute',
                'reason' => "Confidence score ($confidence) is above delegation threshold ($delegationThreshold)",
                'confidence' => $confidence,
                'suggested_delegate' => null
            ];
        } elseif ($confidence >= $abstentionThreshold) {
            // Medium confidence: delegate
            $suggestedDelegate = $this->findCapableDelegate($agentId, $taskType);
            return [
                'action' => 'delegate',
                'reason' => "Confidence score ($confidence) is below delegation threshold ($delegationThreshold) but above abstention threshold ($abstentionThreshold)",
                'confidence' => $confidence,
                'suggested_delegate' => $suggestedDelegate
            ];
        } else {
            // Low confidence: abstain and escalate
            return [
                'action' => 'abstain',
                'reason' => "Confidence score ($confidence) is below abstention threshold ($abstentionThreshold)",
                'confidence' => $confidence,
                'suggested_delegate' => null
            ];
        }
    }

    /**
     * Find capable delegate for task
     *
     * @param string $currentAgentId Current agent ID
     * @param string $taskType Task type
     * @return string|null Suggested delegate agent ID
     */
    private function findCapableDelegate(string $currentAgentId, string $taskType): ?string
    {
        try {
            $tableName = ':table_rag_agent_capabilities';

            // Find agents with higher capability level for this task type
            $query = "
                SELECT agent_id, capability_level
                FROM {$tableName}
                WHERE output_type = :task_type
                AND agent_id != :current_agent_id
                ORDER BY 
                    CASE capability_level
                        WHEN 'expert' THEN 3
                        WHEN 'competent' THEN 2
                        WHEN 'novice' THEN 1
                    END DESC
                LIMIT 1
            ";

            $Qdelegate = $this->db->prepare($query);
            $Qdelegate->bindValue(':task_type', $taskType);
            $Qdelegate->bindValue(':current_agent_id', $currentAgentId);
            $Qdelegate->execute();
            $result = $Qdelegate->fetch();

            return $result ? $result['agent_id'] : null;
        } catch (\Exception $e) {
            // Table might not exist or query failed, return null
            return null;
        }
    }

    /**
     * Set confidence thresholds for an agent
     *
     * @param string $agentId Agent identifier
     * @param float $abstentionThreshold Threshold for abstention (0.0-1.0)
     * @param float $delegationThreshold Threshold for delegation (0.0-1.0)
     * @throws \InvalidArgumentException If thresholds are invalid
     *
     * Requirement 15.5: System SHALL provide configurable thresholds per agent type
     */
    public function setThresholds(
        string $agentId,
        float $abstentionThreshold,
        float $delegationThreshold
    ): void {
        // Validate thresholds
        if ($abstentionThreshold < 0.0 || $abstentionThreshold > 1.0) {
            throw new \InvalidArgumentException("Abstention threshold must be between 0.0 and 1.0");
        }
        if ($delegationThreshold < 0.0 || $delegationThreshold > 1.0) {
            throw new \InvalidArgumentException("Delegation threshold must be between 0.0 and 1.0");
        }
        if ($abstentionThreshold >= $delegationThreshold) {
            throw new \InvalidArgumentException("Abstention threshold must be less than delegation threshold");
        }

        // Store in memory
        $this->agentThresholds[$agentId] = [
            'abstention' => $abstentionThreshold,
            'delegation' => $delegationThreshold
        ];

        // Persist to configuration (could be database or config file)
        $this->saveAgentThresholds($agentId, $abstentionThreshold, $delegationThreshold);
    }

    /**
     * Save agent thresholds to configuration
     *
     * @param string $agentId Agent identifier
     * @param float $abstentionThreshold Abstention threshold
     * @param float $delegationThreshold Delegation threshold
     */
    private function saveAgentThresholds(
        string $agentId,
        float $abstentionThreshold,
        float $delegationThreshold
    ): void {
        try {
            $tableName = ':table_rag_agent_autonomous_config';

            // Store in rag_agent_autonomous_config table
            $query = "
                INSERT INTO {$tableName} (
                    config_key,
                    config_value,
                    created_at,
                    updated_at
                ) VALUES (
                    :config_key,
                    :config_value,
                    NOW(),
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    config_value = :config_value,
                    updated_at = NOW()
            ";

            $configKey = 'confidence_thresholds.' . $agentId;
            $configValue = json_encode([
                'abstention_threshold' => $abstentionThreshold,
                'delegation_threshold' => $delegationThreshold
            ]);

            $Qsave = $this->db->prepare($query);
            $Qsave->bindValue(':config_key', $configKey);
            $Qsave->bindValue(':config_value', $configValue);
            $Qsave->execute();
        } catch (\Exception $e) {
            // Log error but don't fail
            error_log("Failed to save agent thresholds: " . $e->getMessage());
        }
    }

    /**
     * Log abstention event to database
     *
     * @param string $agentId Agent that abstained
     * @param string $taskId Task identifier
     * @param string $taskType Type of task
     * @param float $confidence Confidence score
     * @param string $reason Reason for abstention
     * @param string $actionTaken Action taken (escalate_human, delegate_peer, defer)
     * @param string|null $delegatedTo Agent ID if delegated
     * @return int Abstention ID
     *
     * Requirement 15.4: System SHALL log abstention with confidence score and reasoning
     */
    public function logAbstention(
        string $agentId,
        string $taskId,
        string $taskType,
        float $confidence,
        string $reason,
        string $actionTaken,
        ?string $delegatedTo = null
    ): int {
        // Build table name with prefix
        $tableName = ':table_rag_agent_abstentions';

        // Use direct table name in query (not :table_ placeholder) to avoid ENUM issues
        $query = "
            INSERT INTO {$tableName} (
                agent_id,
                task_id,
                task_type,
                confidence_score,
                abstention_reason,
                action_taken,
                delegated_to
            ) VALUES (
                :agent_id,
                :task_id,
                :task_type,
                :confidence_score,
                :abstention_reason,
                :action_taken,
                :delegated_to
            )
        ";

        $Qinsert = $this->db->prepare($query);
        $Qinsert->bindValue(':agent_id', $agentId);
        $Qinsert->bindValue(':task_id', $taskId);
        $Qinsert->bindValue(':task_type', $taskType);
        $Qinsert->bindValue(':confidence_score', $confidence);
        $Qinsert->bindValue(':abstention_reason', $reason);
        $Qinsert->bindValue(':action_taken', $actionTaken);
        $Qinsert->bindValue(':delegated_to', $delegatedTo);
        $Qinsert->execute();

        return (int)$this->db->lastInsertId();
    }

    /**
     * Get abstention statistics for an agent
     *
     * @param string $agentId Agent identifier
     * @param int $days Number of days to look back
     * @return array Statistics
     */
    public function getAbstentionStatistics(string $agentId, int $days = 30): array
    {
        $tableName = ':table_rag_agent_abstentions';
        
        $query = "
            SELECT 
                COUNT(*) as total_abstentions,
                AVG(confidence_score) as avg_confidence,
                SUM(CASE WHEN action_taken = 'escalate_human' THEN 1 ELSE 0 END) as escalations,
                SUM(CASE WHEN action_taken = 'delegate_peer' THEN 1 ELSE 0 END) as delegations,
                SUM(CASE WHEN action_taken = 'defer' THEN 1 ELSE 0 END) as deferrals
            FROM {$tableName}
            WHERE agent_id = :agent_id
            AND abstained_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ";

        $Qstats = $this->db->prepare($query);
        $Qstats->bindValue(':agent_id', $agentId);
        $Qstats->bindValue(':days', $days);
        $Qstats->execute();

        return $Qstats->fetch() ?: [
            'total_abstentions' => 0,
            'avg_confidence' => 0.0,
            'escalations' => 0,
            'delegations' => 0,
            'deferrals' => 0
        ];
    }
}
