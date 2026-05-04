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

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use Exception;
use InvalidArgumentException;

/**
 * FeedbackManager Class
 *
 * Manages feedback creation, delivery tracking, acknowledgment handling,
 * and feedback history retrieval for the Actor-Critic architecture.
 * Provides structured feedback to actors based on critic evaluations and consensus.
 *
 * Requirements: 13.1, 13.2, 13.3, 13.4, 13.5
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class FeedbackManager
{
    private $db;
    private bool $debug;
    
    /**
     * Constructor
     *
     * Initializes the feedback manager with database connection.
     */
    public function __construct()
    {
        $this->db = Registry::get('Db');
        $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                       CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    }
    
    /**
     * Create feedback from consensus and evaluations
     *
     * Creates structured feedback for an actor based on consensus results
     * and individual critic evaluations. Packages categorized feedback,
     * strengths, and improvements into a Feedback object.
     *
     * Requirement 13.1: Create feedback for actors
     * Requirement 13.2: Include consensus score, categorized feedback, strengths, and improvements
     *
     * @param Consensus $consensus Consensus result from critic evaluations
     * @param array $evaluations Array of Evaluation objects
     * @return Feedback Created feedback object
     * @throws InvalidArgumentException If consensus or evaluations are invalid
     * @throws Exception If feedback creation fails
     */
    public function createFeedback(Consensus $consensus, array $evaluations): Feedback
    {
        // Validate inputs
        if (!($consensus instanceof Consensus)) {
            throw new InvalidArgumentException('Consensus must be a Consensus instance');
        }
        
        if (empty($evaluations)) {
            throw new InvalidArgumentException('Evaluations array cannot be empty');
        }
        
        try {
            // Get target actor from output ID
            $outputId = $consensus->getOutputId();
            $targetActorId = $this->getProducerActorId($outputId);
            
            if (empty($targetActorId) || $targetActorId === 'unknown') {
                throw new Exception("Cannot determine producer actor for output: {$outputId}");
            }
            
            // Get aggregated feedback from consensus
            $aggregated = $consensus->getAggregatedFeedback();
            
            // Create feedback object (Requirement 13.2)
            $feedback = new Feedback(
                $targetActorId,
                $outputId,
                $consensus->getScore(),
                $aggregated['categorized'] ?? [
                    'correctness' => $aggregated['correctness'] ?? [],
                    'efficiency' => $aggregated['efficiency'] ?? [],
                    'completeness' => $aggregated['completeness'] ?? [],
                    'best_practice' => $aggregated['best_practice'] ?? []
                ],
                $this->extractStrengthsContent($aggregated['strengths'] ?? []),
                $this->extractImprovementsContent($aggregated['improvements'] ?? [])
            );
            
            // Persist feedback to database
            $this->persistFeedback($feedback, $consensus);
            
            if ($this->debug) {
                error_log(sprintf(
                    "FeedbackManager: Created feedback %s for actor %s (output: %s, score: %.2f)",
                    $feedback->getFeedbackId(),
                    $targetActorId,
                    $outputId,
                    $consensus->getScore()
                ));
            }
            
            return $feedback;
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("FeedbackManager: Failed to create feedback - " . $e->getMessage());
            }
            throw new Exception('Failed to create feedback: ' . $e->getMessage());
        }
    }
    
    /**
     * Track feedback delivery to an actor
     *
     * Records when feedback is successfully delivered to an actor.
     * Updates the delivery timestamp in the database for tracking purposes.
     *
     * Requirement 13.2: Track feedback delivery
     *
     * @param string $actorId Actor ID receiving the feedback
     * @param Feedback $feedback Feedback being delivered
     * @return void
     * @throws InvalidArgumentException If actor ID or feedback is invalid
     * @throws Exception If tracking fails
     */
    public function trackDelivery(string $actorId, Feedback $feedback): void
    {
        // Validate inputs
        if (empty($actorId)) {
            throw new InvalidArgumentException('Actor ID cannot be empty');
        }
        
        if (!($feedback instanceof Feedback)) {
            throw new InvalidArgumentException('Feedback must be a Feedback instance');
        }
        
        // Verify actor ID matches feedback target
        if ($actorId !== $feedback->getTargetActorId()) {
            throw new InvalidArgumentException(
                "Actor ID mismatch: expected {$feedback->getTargetActorId()}, got {$actorId}"
            );
        }
        
        try {
            // Check if table exists
            if (!$this->tableExists('rag_agent_actor_critic_feedback')) {
                if ($this->debug) {
                    error_log("FeedbackManager: Table rag_agent_actor_critic_feedback does not exist, skipping delivery tracking");
                }
                return;
            }
            
            // Update delivery timestamp
            $sql = "UPDATE :table_rag_agent_actor_critic_feedback 
                    SET delivered_at = :delivered_at
                    WHERE feedback_id = :feedback_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':delivered_at', date('Y-m-d H:i:s'));
            $stmt->bindValue(':feedback_id', $feedback->getFeedbackId());
            $stmt->execute();
            
            if ($this->debug) {
                error_log(sprintf(
                    "FeedbackManager: Tracked delivery of feedback %s to actor %s",
                    $feedback->getFeedbackId(),
                    $actorId
                ));
            }
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("FeedbackManager: Failed to track delivery - " . $e->getMessage());
            }
            throw new Exception('Failed to track feedback delivery: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle feedback acknowledgment from an actor
     *
     * Records when an actor acknowledges receipt of feedback.
     * Updates the acknowledgment status and timestamp in both the
     * Feedback object and the database.
     *
     * Requirement 13.3: Handle acknowledgment of feedback
     *
     * @param Feedback $feedback Feedback being acknowledged
     * @param string $actorId Actor ID acknowledging the feedback
     * @return void
     * @throws InvalidArgumentException If feedback or actor ID is invalid
     * @throws Exception If acknowledgment handling fails
     */
    public function handleAcknowledgment(Feedback $feedback, string $actorId): void
    {
        // Validate inputs
        if (!($feedback instanceof Feedback)) {
            throw new InvalidArgumentException('Feedback must be a Feedback instance');
        }
        
        if (empty($actorId)) {
            throw new InvalidArgumentException('Actor ID cannot be empty');
        }
        
        // Verify actor ID matches feedback target
        if ($actorId !== $feedback->getTargetActorId()) {
            throw new InvalidArgumentException(
                "Actor ID mismatch: expected {$feedback->getTargetActorId()}, got {$actorId}"
            );
        }
        
        // Check if already acknowledged
        if ($feedback->isAcknowledged()) {
            if ($this->debug) {
                error_log(sprintf(
                    "FeedbackManager: Feedback %s already acknowledged by actor %s",
                    $feedback->getFeedbackId(),
                    $actorId
                ));
            }
            return;
        }
        
        try {
            // Mark feedback as acknowledged
            $feedback->acknowledge();
            
            // Check if table exists
            if (!$this->tableExists('rag_agent_actor_critic_feedback')) {
                if ($this->debug) {
                    error_log("FeedbackManager: Table rag_agent_actor_critic_feedback does not exist, skipping acknowledgment tracking");
                }
                return;
            }
            
            // Update acknowledgment in database
            $sql = "UPDATE :table_rag_agent_actor_critic_feedback 
                    SET acknowledged = 1,
                        acknowledged_at = :acknowledged_at
                    WHERE feedback_id = :feedback_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':acknowledged_at', $feedback->getAcknowledgedAt()->format('Y-m-d H:i:s'));
            $stmt->bindValue(':feedback_id', $feedback->getFeedbackId());
            $stmt->execute();
            
            if ($this->debug) {
                error_log(sprintf(
                    "FeedbackManager: Actor %s acknowledged feedback %s",
                    $actorId,
                    $feedback->getFeedbackId()
                ));
            }
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("FeedbackManager: Failed to handle acknowledgment - " . $e->getMessage());
            }
            throw new Exception('Failed to handle feedback acknowledgment: ' . $e->getMessage());
        }
    }
    
    /**
     * Retrieve complete feedback history for an actor
     *
     * Retrieves all feedback received by a specific actor, including
     * delivery and acknowledgment status. Results are ordered by
     * creation date (most recent first).
     *
     * Requirement 13.4: Provide access to complete feedback history
     * Requirement 13.5: Include delivery and acknowledgment tracking
     *
     * @param string $actorId Actor ID to retrieve history for
     * @param int $limit Maximum number of feedback items to retrieve (default: 100)
     * @param int $offset Offset for pagination (default: 0)
     * @return array Array of feedback history items with metadata
     * @throws InvalidArgumentException If actor ID is invalid
     * @throws Exception If retrieval fails
     */
    public function getFeedbackHistory(string $actorId, int $limit = 100, int $offset = 0): array
    {
        // Validate inputs
        if (empty($actorId)) {
            throw new InvalidArgumentException('Actor ID cannot be empty');
        }
        
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Limit must be between 1 and 1000');
        }
        
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset must be non-negative');
        }
        
        try {
            // Check if table exists
            if (!$this->tableExists('rag_agent_actor_critic_feedback')) {
                if ($this->debug) {
                    error_log("FeedbackManager: Table rag_agent_actor_critic_feedback does not exist, returning empty history");
                }
                return [];
            }
            
            // Retrieve feedback history (Requirements 13.4, 13.5)
            $sql = "SELECT 
                        feedback_id,
                        target_agent_id,
                        output_id,
                        consensus_score,
                        categorized_feedback,
                        strengths,
                        improvements,
                        acknowledged,
                        created_at,
                        delivered_at,
                        acknowledged_at
                    FROM :table_rag_agent_actor_critic_feedback
                    WHERE target_agent_id = :actor_id
                    ORDER BY created_at DESC
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':actor_id', $actorId);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            
            $history = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $history[] = [
                    'feedback_id' => $row['feedback_id'],
                    'output_id' => $row['output_id'],
                    'consensus_score' => (float)$row['consensus_score'],
                    'categorized_feedback' => json_decode($row['categorized_feedback'], true),
                    'strengths' => json_decode($row['strengths'], true),
                    'improvements' => json_decode($row['improvements'], true),
                    'acknowledged' => (bool)$row['acknowledged'],
                    'created_at' => $row['created_at'],
                    'delivered_at' => $row['delivered_at'],
                    'acknowledged_at' => $row['acknowledged_at']
                ];
            }
            
            if ($this->debug) {
                error_log(sprintf(
                    "FeedbackManager: Retrieved %d feedback items for actor %s (limit: %d, offset: %d)",
                    count($history),
                    $actorId,
                    $limit,
                    $offset
                ));
            }
            
            return $history;
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("FeedbackManager: Failed to retrieve feedback history - " . $e->getMessage());
            }
            throw new Exception('Failed to retrieve feedback history: ' . $e->getMessage());
        }
    }
    
    /**
     * Get feedback statistics for an actor
     *
     * Retrieves aggregated statistics about feedback received by an actor,
     * including total count, average consensus score, acknowledgment rate,
     * and recent trends.
     *
     * @param string $actorId Actor ID to get statistics for
     * @return array Feedback statistics
     * @throws InvalidArgumentException If actor ID is invalid
     * @throws Exception If retrieval fails
     */
    public function getFeedbackStatistics(string $actorId): array
    {
        // Validate input
        if (empty($actorId)) {
            throw new InvalidArgumentException('Actor ID cannot be empty');
        }
        
        try {
            // Check if table exists
            if (!$this->tableExists('rag_agent_actor_critic_feedback')) {
                if ($this->debug) {
                    error_log("FeedbackManager: Table rag_agent_actor_critic_feedback does not exist, returning empty statistics");
                }
                return [
                    'total_feedback' => 0,
                    'average_score' => 0.0,
                    'acknowledgment_rate' => 0.0,
                    'recent_trend' => 'stable'
                ];
            }
            
            // Get overall statistics
            $sql = "SELECT 
                        COUNT(*) as total_feedback,
                        AVG(consensus_score) as average_score,
                        SUM(CASE WHEN acknowledged = 1 THEN 1 ELSE 0 END) as acknowledged_count
                    FROM :table_rag_agent_actor_critic_feedback
                    WHERE target_agent_id = :actor_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':actor_id', $actorId);
            $stmt->execute();
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $totalFeedback = (int)$stats['total_feedback'];
            $averageScore = (float)($stats['average_score'] ?? 0.0);
            $acknowledgedCount = (int)$stats['acknowledged_count'];
            $acknowledgmentRate = $totalFeedback > 0 ? $acknowledgedCount / $totalFeedback : 0.0;
            
            // Get recent trend (last 10 vs previous 10)
            $recentTrend = $this->calculateRecentTrend($actorId);
            
            return [
                'total_feedback' => $totalFeedback,
                'average_score' => round($averageScore, 2),
                'acknowledgment_rate' => round($acknowledgmentRate, 2),
                'acknowledged_count' => $acknowledgedCount,
                'recent_trend' => $recentTrend
            ];
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("FeedbackManager: Failed to get feedback statistics - " . $e->getMessage());
            }
            throw new Exception('Failed to get feedback statistics: ' . $e->getMessage());
        }
    }
    
    /**
     * Get producer actor ID from output ID
     *
     * Retrieves the actor ID that produced a specific output by querying
     * the actor executions table.
     *
     * @param string $outputId Output ID (result ID)
     * @return string Actor ID or 'unknown' if not found
     */
    private function getProducerActorId(string $outputId): string
    {
        try {
            // Check if table exists
            if (!$this->tableExists('rag_agent_actor_executions')) {
                if ($this->debug) {
                    error_log("FeedbackManager: Table rag_agent_actor_executions does not exist");
                }
                return 'unknown';
            }

            $sql = "SELECT actor_id 
                    FROM :table_rag_agent_actor_executions
                    WHERE result_id = :output_id 
                    LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':output_id', $outputId);
            $stmt->execute();

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result['actor_id'] ?? 'unknown';

        } catch (Exception $e) {
            if ($this->debug) {
                error_log("FeedbackManager: Failed to get producer actor ID - " . $e->getMessage());
            }
            return 'unknown';
        }
    }
    
    /**
     * Persist feedback to database
     *
     * Stores feedback in the database for tracking and history purposes.
     *
     * @param Feedback $feedback Feedback to persist
     * @param Consensus $consensus Associated consensus
     * @return void
     * @throws Exception If persistence fails
     */
    private function persistFeedback(Feedback $feedback, Consensus $consensus): void
    {
        try {
            // Check if table exists
            if (!$this->tableExists('rag_agent_actor_critic_feedback')) {
                if ($this->debug) {
                    error_log("FeedbackManager: Table rag_agent_actor_critic_feedback does not exist, skipping persistence");
                }
                return;
            }
            
            $sql = "INSERT INTO :table_rag_agent_actor_critic_feedback 
                    (feedback_id, target_agent_id, output_id, consensus_score,
                     categorized_feedback, strengths, improvements, acknowledged, created_at)
                    VALUES (:feedback_id, :target_agent_id, :output_id, :consensus_score,
                            :categorized_feedback, :strengths, :improvements, 0, :created_at)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':feedback_id', $feedback->getFeedbackId());
            $stmt->bindValue(':target_agent_id', $feedback->getTargetActorId());
            $stmt->bindValue(':output_id', $feedback->getOutputId());
            $stmt->bindValue(':consensus_score', $feedback->getConsensusScore());
            $stmt->bindValue(':categorized_feedback', json_encode($feedback->getCategorizedFeedback()));
            $stmt->bindValue(':strengths', json_encode($feedback->getStrengths()));
            $stmt->bindValue(':improvements', json_encode($feedback->getImprovements()));
            $stmt->bindValue(':created_at', date('Y-m-d H:i:s'));
            $stmt->execute();
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("FeedbackManager: Failed to persist feedback - " . $e->getMessage());
            }
            throw new Exception('Failed to persist feedback: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if a database table exists
     *
     * @param string $tableName The table name (without prefix)
     * @return bool True if table exists
     */
  private function tableExists(string $tableName): bool
  {
    try {
      $prefix = CLICSHOPPING::getConfig('db_table_prefix');
      $fullTableName = $prefix . $tableName;
      $sql = "SHOW TABLES LIKE ?";
      $stmt = $this->db->prepare($sql);
      $stmt->execute([$fullTableName]);
      return $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
      return false;
    }
  }
    
    /**
     * Extract content from strengths array
     *
     * Extracts just the content strings from the structured strengths array.
     *
     * @param array $strengths Structured strengths array
     * @return array Array of strength content strings
     */
    private function extractStrengthsContent(array $strengths): array
    {
        return array_map(function($item) {
            return is_array($item) && isset($item['content']) ? $item['content'] : (string)$item;
        }, $strengths);
    }
    
    /**
     * Extract content from improvements array
     *
     * Extracts just the content strings from the structured improvements array.
     *
     * @param array $improvements Structured improvements array
     * @return array Array of improvement content strings
     */
    private function extractImprovementsContent(array $improvements): array
    {
        return array_map(function($item) {
            return is_array($item) && isset($item['content']) ? $item['content'] : (string)$item;
        }, $improvements);
    }
    
    /**
     * Calculate recent trend in feedback scores
     *
     * Compares recent feedback scores to previous scores to determine trend.
     *
     * @param string $actorId Actor ID
     * @return string Trend: 'improving', 'declining', or 'stable'
     */
    private function calculateRecentTrend(string $actorId): string
    {
        try {
            // Get last 10 feedback scores
            $sql = "SELECT consensus_score 
                    FROM :table_rag_agent_actor_critic_feedback
                    WHERE target_agent_id = :actor_id
                    ORDER BY created_at DESC
                    LIMIT 20";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':actor_id', $actorId);
            $stmt->execute();
            
            $scores = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $scores[] = (float)$row['consensus_score'];
            }
            
            if (count($scores) < 10) {
                return 'stable'; // Not enough data
            }
            
            // Split into recent (first 10) and previous (next 10)
            $recent = array_slice($scores, 0, 10);
            $previous = array_slice($scores, 10, 10);
            
            $recentAvg = array_sum($recent) / count($recent);
            $previousAvg = count($previous) > 0 ? array_sum($previous) / count($previous) : $recentAvg;
            
            $difference = $recentAvg - $previousAvg;
            
            if ($difference > 0.05) {
                return 'improving';
            } elseif ($difference < -0.05) {
                return 'declining';
            } else {
                return 'stable';
            }
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("FeedbackManager: Failed to calculate trend - " . $e->getMessage());
            }
            return 'stable';
        }
    }
}
