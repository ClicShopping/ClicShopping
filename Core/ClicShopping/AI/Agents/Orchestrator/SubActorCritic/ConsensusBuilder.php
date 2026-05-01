<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\Agents\Orchestrator\SubActorCritic;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use Exception;
use InvalidArgumentException;

/**
 * ConsensusBuilder Class
 *
 * Builds consensus from multiple critic evaluations for the Actor-Critic architecture.
 * Calculates weighted consensus scores, detects outliers, initiates discussion phases,
 * and aggregates feedback into categorized strengths and improvements.
 *
 * Requirements: 12.1, 12.2, 12.3, 12.4
 * 
 * @package ClicShopping\AI\Agents\Orchestrator\SubActorCritic
 * @version 1.0.0
 * @since 2026-01-30
 */
class ConsensusBuilder
{
    private $db;
    private bool $debug;
    
    // Configuration constants
    private const CONSENSUS_THRESHOLD = 0.15; // Maximum standard deviation for consensus
    private const OUTLIER_THRESHOLD = 2.0; // Z-score threshold for outlier detection
    private const MIN_EVALUATIONS = 2; // Minimum evaluations for consensus
    private const DISCUSSION_TIMEOUT = 300; // 5 minutes timeout for discussions
    
    // Weighted scoring weights
    private const ACCURACY_WEIGHT = 0.35;
    private const COMPLETENESS_WEIGHT = 0.25;
    private const EFFICIENCY_WEIGHT = 0.25;
    private const CLARITY_WEIGHT = 0.15;
    
    /**
     * Constructor
     *
     * Initializes the consensus builder with database connection.
     */
    public function __construct()
    {
        $this->db = Registry::get('Db');
        $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                       CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    }
    
    /**
     * Build consensus from multiple critic evaluations
     *
     * Main entry point for consensus building. Analyzes evaluations,
     * calculates weighted consensus score, identifies outliers, and
     * aggregates feedback into categorized strengths and improvements.
     *
     * Requirements: 12.1, 12.2, 12.3, 12.4
     *
     * @param array $evaluations Array of Evaluation objects
     * @return Consensus The consensus result
     * @throws InvalidArgumentException If evaluations array is invalid
     * @throws Exception If consensus building fails
     */
    public function buildConsensus(array $evaluations): Consensus
    {
        // Validate evaluations array
        if (empty($evaluations)) {
            throw new InvalidArgumentException('Evaluations array cannot be empty');
        }

        if (count($evaluations) < self::MIN_EVALUATIONS) {
            throw new InvalidArgumentException(
                'At least ' . self::MIN_EVALUATIONS . ' evaluations required for consensus'
            );
        }

        // Validate all elements are Evaluation instances
        foreach ($evaluations as $evaluation) {
            if (!($evaluation instanceof Evaluation)) {
                throw new InvalidArgumentException('All evaluations must be Evaluation instances');
            }
        }

        // Verify all evaluations are for the same output
        $outputId = $evaluations[0]->getOutputId();
        foreach ($evaluations as $evaluation) {
            if ($evaluation->getOutputId() !== $outputId) {
                throw new InvalidArgumentException('All evaluations must be for the same output');
            }
        }

        try {
            // Calculate weighted consensus score (Requirement 12.1)
            $consensusScore = $this->calculateWeightedConsensusScore($evaluations);
            
            // Detect outlier evaluations (Requirement 12.2)
            $outliers = $this->detectOutliers($evaluations);
            
            // Determine if consensus is reached
            $consensusReached = $this->isConsensusReached($evaluations);
            
            // Aggregate feedback from all critics (Requirement 12.4)
            $aggregatedFeedback = $this->aggregateFeedback($evaluations);
            
            // If outliers detected and consensus not reached, initiate discussion (Requirement 12.3)
            if (!empty($outliers) && !$consensusReached) {
                $discussionResult = $this->initiateDiscussionPhase($evaluations, $outliers);
                if ($discussionResult !== null) {
                    $consensusScore = $discussionResult['score'];
                    $consensusReached = true;
                    $aggregatedFeedback['discussion_notes'] = $discussionResult['notes'];
                }
            }
            
            // Create consensus result
            $consensus = new Consensus(
                $outputId,
                $evaluations,
                $consensusScore,
                $consensusReached,
                $aggregatedFeedback,
                $outliers
            );
            
            // Store consensus as authoritative if reached (Requirement 12.5)
            if ($consensusReached) {
                try {
                    $this->storeAuthoritativeConsensus($consensus);
                } catch (Exception $e) {
                    // Log error but don't fail consensus building if storage fails
                    if ($this->debug) {
                        error_log("ConsensusBuilder: Failed to store consensus (continuing): " . $e->getMessage());
                    }
                }
            }
            
            if ($this->debug) {
                error_log(sprintf(
                    "ConsensusBuilder: Built consensus for output %s - Score: %.2f, Reached: %s, Outliers: %d",
                    $outputId,
                    $consensusScore,
                    $consensusReached ? 'Yes' : 'No',
                    count($outliers)
                ));
            }
            
            return $consensus;
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ConsensusBuilder: Failed to build consensus - " . $e->getMessage());
            }
            throw new Exception('Failed to build consensus: ' . $e->getMessage());
        }
    }
    
    /**
     * Calculate weighted consensus score from evaluations
     *
     * Calculates the consensus score as a weighted average of critic scores
     * using dimension-specific weights for accuracy, completeness, efficiency, and clarity.
     *
     * Requirement 12.1: Calculate consensus score as weighted average
     *
     * @param array $evaluations Array of Evaluation objects
     * @return float Weighted consensus score (0.0-1.0)
     */
    private function calculateWeightedConsensusScore(array $evaluations): float
    {
        $totalWeightedScore = 0.0;
        $totalWeight = 0.0;
        
        foreach ($evaluations as $evaluation) {
            // Calculate weighted score for this evaluation
            $weightedScore = 
                ($evaluation->getAccuracyScore() * self::ACCURACY_WEIGHT) +
                ($evaluation->getCompletenessScore() * self::COMPLETENESS_WEIGHT) +
                ($evaluation->getEfficiencyScore() * self::EFFICIENCY_WEIGHT) +
                ($evaluation->getClarityScore() * self::CLARITY_WEIGHT);
            
            // For now, all evaluations have equal weight (1.0)
            // In future, this could be based on critic expertise or reputation
            $evaluationWeight = 1.0;
            
            $totalWeightedScore += $weightedScore * $evaluationWeight;
            $totalWeight += $evaluationWeight;
        }
        
        return $totalWeight > 0 ? $totalWeightedScore / $totalWeight : 0.0;
    }
    
    /**
     * Detect outlier evaluations using statistical analysis
     *
     * Identifies evaluations with scores that significantly deviate from
     * the mean using z-score analysis. Outliers are evaluations with
     * z-scores exceeding the outlier threshold.
     *
     * Requirement 12.2: Detect outlier evaluations
     *
     * @param array $evaluations Array of Evaluation objects
     * @return array Array of outlier data with evaluator_id, score, and z_score
     */
    private function detectOutliers(array $evaluations): array
    {
        if (count($evaluations) < 3) {
            // Need at least 3 evaluations for meaningful outlier detection
            return [];
        }
        
        // Extract overall scores
        $scores = array_map(function($evaluation) {
            return $evaluation->getOverallScore();
        }, $evaluations);
        
        // Calculate mean and standard deviation
        $mean = array_sum($scores) / count($scores);
        
        $variance = 0.0;
        foreach ($scores as $score) {
            $variance += pow($score - $mean, 2);
        }
        $variance /= count($scores);
        $stdDev = sqrt($variance);
        
        // Avoid division by zero
        if ($stdDev == 0) {
            return [];
        }
        
        // Identify outliers using z-score
        $outliers = [];
        foreach ($evaluations as $evaluation) {
            $score = $evaluation->getOverallScore();
            $zScore = abs(($score - $mean) / $stdDev);
            
            if ($zScore >= self::OUTLIER_THRESHOLD) {
                $outliers[] = [
                    'evaluator_id' => $evaluation->getEvaluatorAgentId(),
                    'score' => $score,
                    'z_score' => $zScore,
                    'deviation' => abs($score - $mean),
                    'evaluation_id' => $evaluation->getEvaluationId()
                ];
            }
        }
        
        if ($this->debug && !empty($outliers)) {
            error_log(sprintf(
                "ConsensusBuilder: Detected %d outliers with mean=%.2f, stdDev=%.2f",
                count($outliers),
                $mean,
                $stdDev
            ));
        }
        
        return $outliers;
    }
    
    /**
     * Determine if consensus is reached based on score agreement
     *
     * @param array $evaluations Array of Evaluation objects
     * @return bool True if consensus is reached
     */
    private function isConsensusReached(array $evaluations): bool
    {
        $scores = array_map(function($evaluation) {
            return $evaluation->getOverallScore();
        }, $evaluations);
        
        if (count($scores) <= 1) {
            return true;
        }
        
        // Calculate standard deviation
        $mean = array_sum($scores) / count($scores);
        $variance = 0.0;
        foreach ($scores as $score) {
            $variance += pow($score - $mean, 2);
        }
        $variance /= count($scores);
        $stdDev = sqrt($variance);
        
        // Consensus reached if standard deviation is below threshold
        return $stdDev <= self::CONSENSUS_THRESHOLD;
    }
    
    /**
     * Aggregate feedback from all critics into categorized strengths and improvements
     *
     * Combines feedback from all evaluations into structured categories:
     * correctness, efficiency, completeness, best_practice, strengths, improvements.
     *
     * Requirement 12.4: Aggregate feedback into categorized strengths and improvements
     *
     * @param array $evaluations Array of Evaluation objects
     * @return array Aggregated feedback with categorized content
     */
    private function aggregateFeedback(array $evaluations): array
    {
        $aggregated = [
            'correctness' => [],
            'efficiency' => [],
            'completeness' => [],
            'best_practice' => [],
            'strengths' => [],
            'improvements' => [],
            'summary' => ''
        ];
        
        $allStrengths = [];
        $allImprovements = [];
        
        foreach ($evaluations as $evaluation) {
            $evaluatorId = $evaluation->getEvaluatorAgentId();
            
            // Collect strengths and improvements
            $strengths = $evaluation->getStrengths();
            $improvements = $evaluation->getImprovements();
            
            foreach ($strengths as $strength) {
                $allStrengths[] = [
                    'evaluator' => $evaluatorId,
                    'content' => $strength,
                    'score' => $evaluation->getOverallScore()
                ];
            }
            
            foreach ($improvements as $improvement) {
                $allImprovements[] = [
                    'evaluator' => $evaluatorId,
                    'content' => $improvement,
                    'score' => $evaluation->getOverallScore()
                ];
            }
            
            // Categorize feedback based on dimension scores
            $feedback = $evaluation->getFeedback();
            
            // Categorize based on which dimension scored lowest
            $scores = [
                'accuracy' => $evaluation->getAccuracyScore(),
                'completeness' => $evaluation->getCompletenessScore(),
                'efficiency' => $evaluation->getEfficiencyScore(),
                'clarity' => $evaluation->getClarityScore()
            ];
            
            $lowestDimension = array_keys($scores, min($scores))[0];
            
            switch ($lowestDimension) {
                case 'accuracy':
                    $aggregated['correctness'][] = [
                        'evaluator' => $evaluatorId,
                        'feedback' => $feedback,
                        'score' => $scores['accuracy']
                    ];
                    break;
                case 'completeness':
                    $aggregated['completeness'][] = [
                        'evaluator' => $evaluatorId,
                        'feedback' => $feedback,
                        'score' => $scores['completeness']
                    ];
                    break;
                case 'efficiency':
                    $aggregated['efficiency'][] = [
                        'evaluator' => $evaluatorId,
                        'feedback' => $feedback,
                        'score' => $scores['efficiency']
                    ];
                    break;
                case 'clarity':
                    $aggregated['best_practice'][] = [
                        'evaluator' => $evaluatorId,
                        'feedback' => $feedback,
                        'score' => $scores['clarity']
                    ];
                    break;
            }
        }
        
        // Deduplicate and prioritize feedback
        $aggregated['strengths'] = $this->deduplicateAndPrioritize($allStrengths);
        $aggregated['improvements'] = $this->deduplicateAndPrioritize($allImprovements);
        
        // Generate summary
        $aggregated['summary'] = $this->generateFeedbackSummary($aggregated, count($evaluations));
        
        return $aggregated;
    }
    
    /**
     * Initiate discussion phase for reconciliation when outliers are detected
     *
     * When outliers are detected, initiates a discussion phase to allow
     * critics to reconcile their differences and reach consensus.
     *
     * Requirement 12.3: Initiate discussion phase for reconciliation
     *
     * @param array $evaluations Array of Evaluation objects
     * @param array $outliers Array of outlier data
     * @return array|null Discussion result with score and notes, or null if failed
     */
    private function initiateDiscussionPhase(array $evaluations, array $outliers): ?array
    {
        if (empty($outliers)) {
            return null;
        }
        
        try {
            $outputId = $evaluations[0]->getOutputId();
            $outlierIds = array_column($outliers, 'evaluator_id');
            
            if ($this->debug) {
                error_log(sprintf(
                    "ConsensusBuilder: Initiating discussion phase for output %s with %d outliers: %s",
                    $outputId,
                    count($outliers),
                    implode(', ', $outlierIds)
                ));
            }
            
            // Calculate scores excluding outliers
            $nonOutlierEvaluations = array_filter($evaluations, function($evaluation) use ($outlierIds) {
                return !in_array($evaluation->getEvaluatorAgentId(), $outlierIds, true);
            });
            
            if (empty($nonOutlierEvaluations)) {
                // All evaluations are outliers, use median
                $allScores = array_map(function($eval) {
                    return $eval->getOverallScore();
                }, $evaluations);
                sort($allScores);
                $medianScore = $allScores[intval(count($allScores) / 2)];
                
                return [
                    'score' => $medianScore,
                    'notes' => 'All evaluations were outliers. Used median score for consensus.'
                ];
            }
            
            // Calculate weighted average of non-outlier evaluations
            $consensusScore = $this->calculateWeightedConsensusScore($nonOutlierEvaluations);
            
            // Check if outliers are within acceptable range of consensus
            $maxAcceptableDeviation = self::CONSENSUS_THRESHOLD * 2; // More lenient for discussion
            $reconciled = true;
            
            foreach ($outliers as $outlier) {
                $deviation = abs($outlier['score'] - $consensusScore);
                if ($deviation > $maxAcceptableDeviation) {
                    $reconciled = false;
                    break;
                }
            }
            
            if ($reconciled) {
                // Include outliers with reduced weight
                $totalWeightedScore = 0.0;
                $totalWeight = 0.0;
                
                foreach ($evaluations as $evaluation) {
                    $weight = in_array($evaluation->getEvaluatorAgentId(), $outlierIds, true) ? 0.3 : 1.0;
                    $totalWeightedScore += $evaluation->getOverallScore() * $weight;
                    $totalWeight += $weight;
                }
                
                $finalScore = $totalWeight > 0 ? $totalWeightedScore / $totalWeight : $consensusScore;
                
                return [
                    'score' => $finalScore,
                    'notes' => sprintf(
                        'Discussion phase reconciled %d outliers. Final score includes outliers with reduced weight.',
                        count($outliers)
                    )
                ];
            }
            
            // Discussion failed to reconcile
            return null;
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ConsensusBuilder: Discussion phase failed - " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Store consensus as authoritative in database
     *
     * Requirement 12.5: Mark consensus as authoritative and store it
     *
     * @param Consensus $consensus The consensus to store
     * @throws Exception If storage fails
     */
    private function storeAuthoritativeConsensus(Consensus $consensus): void
    {
        // Check if table exists first
        if (!$this->tableExists('rag_agent_coordinated_results')) {
            if ($this->debug) {
                error_log("ConsensusBuilder: Table rag_agent_coordinated_results does not exist, skipping storage");
            }
            return;
        }
        
        try {
            $sql = "INSERT INTO :table_rag_agent_coordinated_results 
                    (consensus_id, output_id, consensus_score, consensus_reached, 
                     aggregated_feedback, outliers, created_at, is_authoritative)
                    VALUES (:consensus_id, :output_id, :consensus_score, :consensus_reached,
                            :aggregated_feedback, :outliers, :created_at, 1)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':consensus_id', $consensus->getConsensusId());
            $stmt->bindValue(':output_id', $consensus->getOutputId());
            $stmt->bindValue(':consensus_score', $consensus->getScore());
            $stmt->bindValue(':consensus_reached', $consensus->isReached() ? 1 : 0);
            $stmt->bindValue(':aggregated_feedback', json_encode($consensus->getAggregatedFeedback()));
            $stmt->bindValue(':outliers', json_encode($consensus->getOutliers()));
            $stmt->bindValue(':created_at', $consensus->getCreatedAt()->format('Y-m-d H:i:s'));
            $stmt->execute();
            
            if ($this->debug) {
                error_log(sprintf(
                    "ConsensusBuilder: Stored authoritative consensus %s for output %s",
                    $consensus->getConsensusId(),
                    $consensus->getOutputId()
                ));
            }
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ConsensusBuilder: Failed to store authoritative consensus - " . $e->getMessage());
            }
            throw new Exception('Failed to store authoritative consensus: ' . $e->getMessage());
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
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Deduplicate and prioritize feedback items
     *
     * @param array $items Array of feedback items with evaluator, content, and score
     * @return array Deduplicated and prioritized items
     */
    private function deduplicateAndPrioritize(array $items): array
    {
        // Group by similar content (simple string similarity)
        $grouped = [];
        foreach ($items as $item) {
            $content = strtolower(trim($item['content']));
            $found = false;
            
            foreach ($grouped as $key => $group) {
                $groupContent = strtolower(trim($group[0]['content']));
                // Simple similarity check (could be improved with more sophisticated algorithms)
                if (similar_text($content, $groupContent) > 0.8 * strlen($content)) {
                    $grouped[$key][] = $item;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $grouped[] = [$item];
            }
        }
        
        // Select best representative from each group and sort by score
        $result = [];
        foreach ($grouped as $group) {
            // Sort group by score descending
            usort($group, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            // Take the highest scored item as representative
            $result[] = $group[0];
        }
        
        // Sort final result by score descending
        usort($result, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $result;
    }
    
    /**
     * Generate feedback summary from aggregated feedback
     *
     * @param array $aggregated Aggregated feedback
     * @param int $evaluatorCount Number of evaluators
     * @return string Summary text
     */
    private function generateFeedbackSummary(array $aggregated, int $evaluatorCount): string
    {
        $summary = "Consensus built from {$evaluatorCount} critic evaluations. ";
        
        $strengthCount = count($aggregated['strengths']);
        $improvementCount = count($aggregated['improvements']);
        
        if ($strengthCount > 0) {
            $summary .= "Identified {$strengthCount} key strengths. ";
        }
        
        if ($improvementCount > 0) {
            $summary .= "Identified {$improvementCount} areas for improvement. ";
        }
        
        // Identify primary concern areas
        $concerns = [];
        if (!empty($aggregated['correctness'])) {
            $concerns[] = 'correctness';
        }
        if (!empty($aggregated['completeness'])) {
            $concerns[] = 'completeness';
        }
        if (!empty($aggregated['efficiency'])) {
            $concerns[] = 'efficiency';
        }
        if (!empty($aggregated['best_practice'])) {
            $concerns[] = 'best practices';
        }
        
        if (!empty($concerns)) {
            $summary .= "Primary concern areas: " . implode(', ', $concerns) . ".";
        }
        
        return $summary;
    }
    
    /**
     * Get consensus threshold
     *
     * @return float The consensus threshold (maximum standard deviation)
     */
    public function getConsensusThreshold(): float
    {
        return self::CONSENSUS_THRESHOLD;
    }
    
    /**
     * Get outlier threshold
     *
     * @return float The outlier threshold (z-score)
     */
    public function getOutlierThreshold(): float
    {
        return self::OUTLIER_THRESHOLD;
    }
    
    /**
     * Get minimum evaluations required
     *
     * @return int The minimum number of evaluations
     */
    public function getMinEvaluations(): int
    {
        return self::MIN_EVALUATIONS;
    }
    
    /**
     * Get discussion timeout
     *
     * @return int The discussion timeout in seconds
     */
    public function getDiscussionTimeout(): int
    {
        return self::DISCUSSION_TIMEOUT;
    }
}