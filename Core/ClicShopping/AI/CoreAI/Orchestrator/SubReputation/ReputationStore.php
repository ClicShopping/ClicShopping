<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\ReputationScore;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\ReputationHistory;

/**
 * ReputationStore - Persists reputation data to database
 * 
 * Manages CRUD operations for reputation scores and history.
 * Provides data access layer for the reputation system.
 * 
 * Requirements: 1.1, 2.6, 9.1
 */
class ReputationStore
{
    private $db;
    private string $prefix;
    
    public function __construct()
    {
        $this->db = Registry::get('Db');
        $this->prefix = CLICSHOPPING::getConfig('db_table_prefix', 'Database');
    }
    
    /**
     * Get reputation score for a critic
     * 
     * Requirements: 1.1
     * 
     * @param string $criticId Critic identifier
     * @return ReputationScore|null Reputation score or null if not found
     */
    public function getReputation(string $criticId): ?ReputationScore
    {
        $sql = "
            SELECT 
                critic_id,
                reputation_score,
                consensus_alignment,
                feedback_quality,
                consistency_score,
                expertise_accuracy,
                total_evaluations,
                status,
                calculated_at,
                last_decay_at
            FROM :table_rag_agent_reputation
            WHERE critic_id = :critic_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['critic_id' => $criticId]);
        $row = $stmt->fetch();
        
        if (!$row) {
            return null;
        }
        
        return $this->mapRowToReputationScore($row);
    }
    
    /**
     * Map database row to ReputationScore object
     *
     * @param array $row Database row
     * @return ReputationScore Reputation score object
     */
    private function mapRowToReputationScore(array $row): ReputationScore
    {
        $reputation = new ReputationScore();
        $reputation->criticId = $row['critic_id'];
        $reputation->reputationScore = (float)$row['reputation_score'];
        $reputation->consensusAlignment = (float)$row['consensus_alignment'];
        $reputation->feedbackQuality = (float)$row['feedback_quality'];
        $reputation->consistencyScore = (float)$row['consistency_score'];
        $reputation->expertiseAccuracy = (float)$row['expertise_accuracy'];
        $reputation->totalEvaluations = (int)$row['total_evaluations'];
        $reputation->status = $row['status'];
        $reputation->calculatedAt = new \DateTimeImmutable($row['calculated_at']);
        $reputation->lastDecayAt = new \DateTimeImmutable($row['last_decay_at']);

        return $reputation;
    }
    
    /**
     * Save reputation score to database
     *
     * Requirements: 1.1, 2.6
     *
     * @param ReputationScore $reputation Reputation score to save
     * @return bool True if successful
     */
    public function saveReputation(ReputationScore $reputation): bool
    {
        $sql = "
            INSERT INTO :table_rag_agent_reputation (
                critic_id,
                reputation_score,
                consensus_alignment,
                feedback_quality,
                consistency_score,
                expertise_accuracy,
                total_evaluations,
                status,
                calculated_at,
                last_decay_at
            ) VALUES (
                :critic_id,
                :reputation_score,
                :consensus_alignment,
                :feedback_quality,
                :consistency_score,
                :expertise_accuracy,
                :total_evaluations,
                :status,
                :calculated_at,
                :last_decay_at
            ) ON DUPLICATE KEY UPDATE
                reputation_score = VALUES(reputation_score),
                consensus_alignment = VALUES(consensus_alignment),
                feedback_quality = VALUES(feedback_quality),
                consistency_score = VALUES(consistency_score),
                expertise_accuracy = VALUES(expertise_accuracy),
                total_evaluations = VALUES(total_evaluations),
                status = VALUES(status),
                calculated_at = VALUES(calculated_at),
                last_decay_at = VALUES(last_decay_at)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'critic_id' => $reputation->criticId,
            'reputation_score' => $reputation->reputationScore,
            'consensus_alignment' => $reputation->consensusAlignment,
            'feedback_quality' => $reputation->feedbackQuality,
            'consistency_score' => $reputation->consistencyScore,
            'expertise_accuracy' => $reputation->expertiseAccuracy,
            'total_evaluations' => $reputation->totalEvaluations,
            'status' => $reputation->status,
            'calculated_at' => $reputation->calculatedAt->format('Y-m-d H:i:s'),
            'last_decay_at' => $reputation->lastDecayAt->format('Y-m-d H:i:s')
        ]);

        // Check if operation succeeded by verifying row count
        // Note: ClicShopping DbStatement may return false even on success
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get reputation history for a critic
     *
     * Requirements: 2.6, 9.1
     *
     * @param string $criticId Critic identifier
     * @param int $days Number of days of history to retrieve
     * @return array<ReputationHistory> Array of reputation history records
     */
    public function getHistory(string $criticId, int $days = 30): array
    {
        $sql = "
            SELECT 
                history_id,
                critic_id,
                evaluation_id,
                consensus_score,
                critic_score,
                alignment_delta,
                reputation_impact,
                old_reputation,
                new_reputation,
                recorded_at
            FROM :table_rag_agent_reputation_history
            WHERE critic_id = :critic_id
              AND recorded_at > DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY recorded_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'critic_id' => $criticId,
            'days' => $days
        ]);

        $history = [];
        while ($row = $stmt->fetch()) {
            $history[] = $this->mapRowToReputationHistory($row);
        }

        return $history;
    }
    
    /**
     * Map database row to ReputationHistory object
     *
     * @param array $row Database row
     * @return ReputationHistory Reputation history object
     */
    private function mapRowToReputationHistory(array $row): ReputationHistory
    {
        $history = new ReputationHistory();
        $history->historyId = (int)$row['history_id'];
        $history->criticId = $row['critic_id'];
        $history->evaluationId = $row['evaluation_id'];
        $history->consensusScore = (float)$row['consensus_score'];
        $history->criticScore = (float)$row['critic_score'];
        $history->alignmentDelta = (float)$row['alignment_delta'];
        $history->reputationImpact = (float)$row['reputation_impact'];
        $history->oldReputation = (float)$row['old_reputation'];
        $history->newReputation = (float)$row['new_reputation'];
        $history->recordedAt = new \DateTimeImmutable($row['recorded_at']);

        return $history;
    }
    
    /**
     * Save reputation history record
     *
     * Requirements: 2.6, 9.1
     *
     * @param ReputationHistory $history History record to save
     * @return bool True if successful
     */
    public function saveHistory(ReputationHistory $history): bool
    {
        $sql = "
            INSERT INTO :table_rag_agent_reputation_history (
                critic_id,
                evaluation_id,
                consensus_score,
                critic_score,
                alignment_delta,
                reputation_impact,
                old_reputation,
                new_reputation,
                recorded_at
            ) VALUES (
                :critic_id,
                :evaluation_id,
                :consensus_score,
                :critic_score,
                :alignment_delta,
                :reputation_impact,
                :old_reputation,
                :new_reputation,
                :recorded_at
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'critic_id' => $history->criticId,
            'evaluation_id' => $history->evaluationId,
            'consensus_score' => $history->consensusScore,
            'critic_score' => $history->criticScore,
            'alignment_delta' => $history->alignmentDelta,
            'reputation_impact' => $history->reputationImpact,
            'old_reputation' => $history->oldReputation,
            'new_reputation' => $history->newReputation,
            'recorded_at' => $history->recordedAt->format('Y-m-d H:i:s')
        ]);

        // Check if operation succeeded by verifying row count
        // Note: ClicShopping DbStatement may return false even on success
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get multiple reputation scores by critic IDs
     *
     * Efficiently retrieves reputation scores for multiple critics in a single query.
     * Used for batch cache warming and bulk operations.
     *
     * @param array $criticIds Array of critic identifiers
     * @return array Map of critic_id => ReputationScore
     */
    public function getMultipleReputations(array $criticIds): array
    {
        if (empty($criticIds)) {
            return [];
        }

        // Create placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($criticIds), '?'));

        $sql = "
            SELECT 
                critic_id,
                reputation_score,
                consensus_alignment,
                feedback_quality,
                consistency_score,
                expertise_accuracy,
                total_evaluations,
                status,
                calculated_at,
                last_decay_at
            FROM :table_rag_agent_reputation
            WHERE critic_id IN ({$placeholders})
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($criticIds);

        $reputations = [];
        while ($row = $stmt->fetch()) {
            $reputations[$row['critic_id']] = $this->mapRowToReputationScore($row);
        }

        return $reputations;
    }
    
    /**
     * Get all reputation scores
     *
     * @return array<ReputationScore> Array of all reputation scores
     */
    public function getAllReputations(): array
    {
        $sql = "
            SELECT 
                critic_id,
                reputation_score,
                consensus_alignment,
                feedback_quality,
                consistency_score,
                expertise_accuracy,
                total_evaluations,
                status,
                calculated_at,
                last_decay_at
            FROM :table_rag_agent_reputation
            ORDER BY reputation_score DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $reputations = [];
        while ($row = $stmt->fetch()) {
            $reputations[] = $this->mapRowToReputationScore($row);
        }

        return $reputations;
    }
    
    /**
     * Get reputation scores by status
     *
     * @param string $status Status filter ('bootstrapping', 'establishing', 'established')
     * @return array<ReputationScore> Array of reputation scores
     */
    public function getReputationsByStatus(string $status): array
    {
        $sql = "
            SELECT 
                critic_id,
                reputation_score,
                consensus_alignment,
                feedback_quality,
                consistency_score,
                expertise_accuracy,
                total_evaluations,
                status,
                calculated_at,
                last_decay_at
            FROM {:table_rag_agent_reputation
            WHERE status = :status
            ORDER BY reputation_score DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $status]);

        $reputations = [];
        while ($row = $stmt->fetch()) {
            $reputations[] = $this->mapRowToReputationScore($row);
        }

        return $reputations;
    }
    
    /**
     * Get critics with reputation below threshold
     *
     * @param float $threshold Reputation threshold
     * @return array<ReputationScore> Array of reputation scores
     */
    public function getLowReputationCritics(float $threshold = 0.6): array
    {
        $sql = "
            SELECT 
                critic_id,
                reputation_score,
                consensus_alignment,
                feedback_quality,
                consistency_score,
                expertise_accuracy,
                total_evaluations,
                status,
                calculated_at,
                last_decay_at
            FROM :table_rag_agent_reputation
            WHERE reputation_score < :threshold
            ORDER BY reputation_score ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['threshold' => $threshold]);

        $reputations = [];
        while ($row = $stmt->fetch()) {
            $reputations[] = $this->mapRowToReputationScore($row);
        }

        return $reputations;
    }
    
    /**
     * Delete reputation data for a critic
     *
     * @param string $criticId Critic identifier
     * @return bool True if successful
     */
    public function deleteReputation(string $criticId): bool
    {
        // Delete reputation score
        $sql = "DELETE FROM :table_rag_agent_reputation WHERE critic_id = :critic_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['critic_id' => $criticId]);

        // Note: History is retained for audit purposes

        // Check if operation succeeded by verifying row count
        // Note: ClicShopping DbStatement may return false even on success
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get all critics (for decay scheduler)
     *
     * Returns basic critic information needed for decay processing.
     *
     * @return array Array of critic data with keys: critic_id, reputation_score, last_decay_at
     */
    public function getAllCritics(): array
    {
        $sql = "
            SELECT 
                critic_id,
                reputation_score,
                last_decay_at
            FROM {$this->prefix}rag_agent_reputation
            ORDER BY critic_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Update reputation after decay
     *
     * Updates only the reputation score and last_decay_at timestamp.
     * Used by the decay scheduler.
     *
     * @param string $criticId Critic identifier
     * @param float $newReputation New reputation score after decay
     * @return bool True if successful
     */
    public function updateReputationAfterDecay(string $criticId, float $newReputation): bool
    {
        $sql = "
            UPDATE {$this->prefix}rag_agent_reputation
            SET reputation_score = :reputation_score,
                last_decay_at = NOW()
            WHERE critic_id = :critic_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'critic_id' => $criticId,
            'reputation_score' => $newReputation
        ]);

        // Check if operation succeeded by verifying row count
        // Note: ClicShopping DbStatement may return false even on success
        return $stmt->rowCount() > 0;
    }
}
