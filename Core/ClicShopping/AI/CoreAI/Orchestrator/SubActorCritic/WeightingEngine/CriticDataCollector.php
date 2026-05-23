<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine;

use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\ReputationStore;
use ClicShopping\AI\RegistryAI\CriticRegistry;
use ClicShopping\AI\InterfacesAI\CriticAgentInterface;

/**
 * CriticDataCollector - Gathers critic data for LLM weight analysis
 * 
 * Collects comprehensive critic profiles including reputation history,
 * domain expertise, confidence patterns, and recent performance data.
 * Integrates with existing ReputationStore and CriticRegistry.
 * 
 * Requirements: 1.3, 2.1, 3.1, 4.1, 5.1, 6.1
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine
 * @version 1.0.0
 * @since 2026-02-06
 */
class CriticDataCollector
{
    private ReputationStore $reputationStore;
    private CriticRegistry $criticRegistry;
    
    // Default values for missing data
    private const DEFAULT_REPUTATION = 0.75;
    private const DEFAULT_EXPERTISE_LEVEL = 0.5;
    private const DEFAULT_CONFIDENCE = 0.7;
    
    public function __construct(
        ReputationStore $reputationStore,
        CriticRegistry $criticRegistry
    ) {
        $this->reputationStore = $reputationStore;
        $this->criticRegistry = $criticRegistry;
    }
    
    /**
     * Collect comprehensive data for all critics
     * 
     * Gathers reputation, domain expertise, confidence, and recent evaluations
     * for each critic. Handles missing data with sensible defaults.
     * 
     * Requirements: 1.3, 2.1, 3.1, 4.1, 5.1, 6.1
     * 
     * @param array<CriticAgentInterface> $critics Array of critic agents
     * @return array<string, array> Map of critic_id => critic data
     */
    public function collectCriticData(array $critics): array
    {
        $criticData = [];
        
        foreach ($critics as $critic) {
            $criticId = $critic->getCriticId();
            
            try {
                $criticData[$criticId] = [
                    'critic_id' => $criticId,
                    'critic_name' => $this->getCriticName($critic),
                    'reputation' => $this->getReputationData($criticId),
                    'domain' => $this->getDomainExpertise($critic),
                    'expertise_level' => $this->getExpertiseLevel($critic),
                    'confidence' => $this->getConfidenceData($criticId),
                    'recent_evaluations' => $this->getRecentEvaluations($criticId),
                    'last_evaluation_date' => $this->getLastEvaluationDate($criticId),
                    'total_evaluations' => $this->getTotalEvaluations($criticId)
                ];
            } catch (\Exception $e) {
                // Log error but continue with defaults
                error_log("CriticDataCollector: Error collecting data for critic {$criticId}: " . $e->getMessage());
                
                $criticData[$criticId] = $this->getDefaultCriticData($criticId, $critic);
            }
        }
        
        return $criticData;
    }
    
    /**
     * Get critic profile from registry
     * 
     * Extracts domain and expertise level from EvaluationCriteria.
     * Uses CriticRegistry to get critic capabilities.
     * 
     * Requirements: 3.1
     * 
     * @param string $criticId Critic identifier
     * @return array Critic profile data
     */
    public function getCriticProfile(string $criticId): array
    {
        $critic = $this->criticRegistry->getCritic($criticId);
        
        if ($critic === null) {
            return $this->getDefaultProfile($criticId);
        }
        
        $criteria = $critic->getEvaluationCriteria();
        $domains = [];
        $expertiseLevels = [];
        
        // Extract domain and expertise from all evaluation criteria
        foreach ($criteria as $outputType => $criterion) {
            if (is_object($criterion)) {
                if (method_exists($criterion, 'getDomain')) {
                    $domain = $criterion->getDomain();
                    if (!empty($domain)) {
                        $domains[] = $domain;
                    }
                }
                
                if (method_exists($criterion, 'getExpertiseLevel')) {
                    $expertiseLevels[] = $criterion->getExpertiseLevel();
                }
            }
        }
        
        // Calculate average expertise level
        $avgExpertise = !empty($expertiseLevels) 
            ? array_sum($expertiseLevels) / count($expertiseLevels)
            : self::DEFAULT_EXPERTISE_LEVEL;
        
        return [
            'critic_id' => $criticId,
            'critic_name' => $this->getCriticName($critic),
            'domains' => array_unique($domains),
            'expertise_level' => $avgExpertise,
            'evaluation_capabilities' => array_keys($criteria)
        ];
    }
    
    /**
     * Get reputation history from ReputationStore
     * 
     * Retrieves historical reputation data for trend analysis.
     * Calls ReputationStore->getHistory() with configurable time window.
     * 
     * Requirements: 2.1, 6.1
     * 
     * @param string $criticId Critic identifier
     * @param int $days Number of days of history (default: 90)
     * @return array Reputation history data
     */
    public function getReputationHistory(string $criticId, int $days = 90): array
    {
        try {
            $history = $this->reputationStore->getHistory($criticId, $days);
            
            if (empty($history)) {
                return $this->getDefaultReputationHistory();
            }
            
            // Convert history objects to arrays for LLM consumption
            $historyData = [];
            foreach ($history as $record) {
                $historyData[] = [
                    'evaluation_id' => $record->evaluationId,
                    'consensus_score' => $record->consensusScore,
                    'critic_score' => $record->criticScore,
                    'alignment_delta' => $record->alignmentDelta,
                    'reputation_impact' => $record->reputationImpact,
                    'old_reputation' => $record->oldReputation,
                    'new_reputation' => $record->newReputation,
                    'recorded_at' => $record->recordedAt->format('Y-m-d H:i:s')
                ];
            }
            
            // Calculate trend metrics
            $trend = $this->calculateReputationTrend($historyData);
            $stability = $this->calculateReputationStability($historyData);
            
            return [
                'data_points' => $historyData,
                'trend' => $trend,
                'stability' => $stability,
                'total_records' => count($historyData)
            ];
            
        } catch (\Exception $e) {
            error_log("CriticDataCollector: Error getting reputation history for {$criticId}: " . $e->getMessage());
            return $this->getDefaultReputationHistory();
        }
    }
    
    /**
     * Get reputation data for a critic
     * 
     * Requirements: 2.1
     * 
     * @param string $criticId Critic identifier
     * @return array Reputation data
     */
    private function getReputationData(string $criticId): array
    {
        try {
            $reputation = $this->reputationStore->getReputation($criticId);
            
            if ($reputation === null) {
                return [
                    'score' => self::DEFAULT_REPUTATION,
                    'status' => 'bootstrapping',
                    'consensus_alignment' => self::DEFAULT_REPUTATION,
                    'feedback_quality' => self::DEFAULT_REPUTATION,
                    'consistency_score' => self::DEFAULT_REPUTATION,
                    'expertise_accuracy' => self::DEFAULT_REPUTATION,
                    'total_evaluations' => 0
                ];
            }
            
            return [
                'score' => $reputation->reputationScore,
                'status' => $reputation->status,
                'consensus_alignment' => $reputation->consensusAlignment,
                'feedback_quality' => $reputation->feedbackQuality,
                'consistency_score' => $reputation->consistencyScore,
                'expertise_accuracy' => $reputation->expertiseAccuracy,
                'total_evaluations' => $reputation->totalEvaluations
            ];
            
        } catch (\Exception $e) {
            error_log("CriticDataCollector: Error getting reputation for {$criticId}: " . $e->getMessage());
            return [
                'score' => self::DEFAULT_REPUTATION,
                'status' => 'bootstrapping',
                'consensus_alignment' => self::DEFAULT_REPUTATION,
                'feedback_quality' => self::DEFAULT_REPUTATION,
                'consistency_score' => self::DEFAULT_REPUTATION,
                'expertise_accuracy' => self::DEFAULT_REPUTATION,
                'total_evaluations' => 0
            ];
        }
    }
    
    /**
     * Get domain expertise from critic's evaluation criteria
     * 
     * Requirements: 3.1
     * 
     * @param CriticAgentInterface $critic Critic agent
     * @return array List of domain specializations
     */
    private function getDomainExpertise(CriticAgentInterface $critic): array
    {
        $criteria = $critic->getEvaluationCriteria();
        $domains = [];
        
        foreach ($criteria as $outputType => $criterion) {
            if (is_object($criterion) && method_exists($criterion, 'getDomain')) {
                $domain = $criterion->getDomain();
                if (!empty($domain) && !in_array($domain, $domains, true)) {
                    $domains[] = $domain;
                }
            }
        }
        
        return !empty($domains) ? $domains : ['general'];
    }
    
    /**
     * Get expertise level from critic's evaluation criteria
     * 
     * Requirements: 3.1
     * 
     * @param CriticAgentInterface $critic Critic agent
     * @return float Average expertise level (0.0-1.0)
     */
    private function getExpertiseLevel(CriticAgentInterface $critic): float
    {
        $criteria = $critic->getEvaluationCriteria();
        $expertiseLevels = [];
        
        foreach ($criteria as $outputType => $criterion) {
            if (is_object($criterion) && method_exists($criterion, 'getExpertiseLevel')) {
                $expertiseLevels[] = $criterion->getExpertiseLevel();
            }
        }
        
        if (empty($expertiseLevels)) {
            return self::DEFAULT_EXPERTISE_LEVEL;
        }
        
        return array_sum($expertiseLevels) / count($expertiseLevels);
    }
    
    /**
     * Get confidence data for a critic
     * 
     * Requirements: 4.1
     * 
     * @param string $criticId Critic identifier
     * @return array Confidence data
     */
    private function getConfidenceData(string $criticId): array
    {
        // For now, return default confidence
        // In future, this could analyze historical confidence patterns
        return [
            'current_confidence' => self::DEFAULT_CONFIDENCE,
            'average_confidence' => self::DEFAULT_CONFIDENCE,
            'confidence_stability' => 0.8,
            'over_confidence_detected' => false,
            'under_confidence_detected' => false
        ];
    }
    
    /**
     * Get recent evaluations for a critic
     * 
     * Requirements: 5.1
     * 
     * @param string $criticId Critic identifier
     * @return array Recent evaluation data
     */
    private function getRecentEvaluations(string $criticId): array
    {
        try {
            $history = $this->reputationStore->getHistory($criticId, 30);
            
            return [
                'count_30_days' => count($history),
                'latest_evaluations' => array_slice(array_map(function($record) {
                    return [
                        'evaluation_id' => $record->evaluationId,
                        'consensus_score' => $record->consensusScore,
                        'critic_score' => $record->criticScore,
                        'alignment_delta' => $record->alignmentDelta,
                        'recorded_at' => $record->recordedAt->format('Y-m-d H:i:s')
                    ];
                }, $history), 0, 10)
            ];
            
        } catch (\Exception $e) {
            error_log("CriticDataCollector: Error getting recent evaluations for {$criticId}: " . $e->getMessage());
            return [
                'count_30_days' => 0,
                'latest_evaluations' => []
            ];
        }
    }
    
    /**
     * Get last evaluation date for a critic
     * 
     * Requirements: 5.1
     * 
     * @param string $criticId Critic identifier
     * @return string|null Last evaluation date or null
     */
    private function getLastEvaluationDate(string $criticId): ?string
    {
        try {
            $history = $this->reputationStore->getHistory($criticId, 90);
            
            if (empty($history)) {
                return null;
            }
            
            return $history[0]->recordedAt->format('Y-m-d H:i:s');
            
        } catch (\Exception $e) {
            error_log("CriticDataCollector: Error getting last evaluation date for {$criticId}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get total evaluations count for a critic
     * 
     * Requirements: 5.1
     * 
     * @param string $criticId Critic identifier
     * @return int Total evaluations count
     */
    private function getTotalEvaluations(string $criticId): int
    {
        try {
            $reputation = $this->reputationStore->getReputation($criticId);
            
            if ($reputation === null) {
                return 0;
            }
            
            return $reputation->totalEvaluations;
            
        } catch (\Exception $e) {
            error_log("CriticDataCollector: Error getting total evaluations for {$criticId}: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get critic name from critic agent
     * 
     * @param CriticAgentInterface $critic Critic agent
     * @return string Critic name
     */
    private function getCriticName(CriticAgentInterface $critic): string
    {
        // Try to get name from critic if method exists
        if (method_exists($critic, 'getName')) {
            return $critic->getName();
        }
        
        // Fall back to critic ID
        return $critic->getCriticId();
    }
    
    /**
     * Calculate reputation trend from history
     * 
     * Requirements: 6.1
     * 
     * @param array $historyData Reputation history data
     * @return string Trend description (improving, stable, declining)
     */
    private function calculateReputationTrend(array $historyData): string
    {
        if (count($historyData) < 2) {
            return 'insufficient_data';
        }
        
        // Calculate linear regression slope
        $n = count($historyData);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        
        foreach ($historyData as $i => $record) {
            $x = $i;
            $y = $record['new_reputation'];
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        
        // Classify trend based on slope
        if ($slope > 0.01) {
            return 'improving';
        } elseif ($slope < -0.01) {
            return 'declining';
        } else {
            return 'stable';
        }
    }
    
    /**
     * Calculate reputation stability from history
     * 
     * Requirements: 6.1
     * 
     * @param array $historyData Reputation history data
     * @return float Stability score (0.0-1.0, higher is more stable)
     */
    private function calculateReputationStability(array $historyData): float
    {
        if (count($historyData) < 2) {
            return 0.5; // Neutral for insufficient data
        }
        
        // Calculate standard deviation of reputation scores
        $scores = array_map(fn($record) => $record['new_reputation'], $historyData);
        $mean = array_sum($scores) / count($scores);
        $variance = array_sum(array_map(fn($score) => pow($score - $mean, 2), $scores)) / count($scores);
        $stdDev = sqrt($variance);
        
        // Convert to stability score (lower std dev = higher stability)
        // Assuming reputation range is 0.0-1.0, std dev of 0.1 or less is very stable
        $stability = max(0.0, min(1.0, 1.0 - ($stdDev * 5)));
        
        return $stability;
    }
    
    /**
     * Get default critic data when collection fails
     * 
     * @param string $criticId Critic identifier
     * @param CriticAgentInterface $critic Critic agent
     * @return array Default critic data
     */
    private function getDefaultCriticData(string $criticId, CriticAgentInterface $critic): array
    {
        return [
            'critic_id' => $criticId,
            'critic_name' => $this->getCriticName($critic),
            'reputation' => [
                'score' => self::DEFAULT_REPUTATION,
                'status' => 'bootstrapping',
                'consensus_alignment' => self::DEFAULT_REPUTATION,
                'feedback_quality' => self::DEFAULT_REPUTATION,
                'consistency_score' => self::DEFAULT_REPUTATION,
                'expertise_accuracy' => self::DEFAULT_REPUTATION,
                'total_evaluations' => 0
            ],
            'domain' => ['general'],
            'expertise_level' => self::DEFAULT_EXPERTISE_LEVEL,
            'confidence' => [
                'current_confidence' => self::DEFAULT_CONFIDENCE,
                'average_confidence' => self::DEFAULT_CONFIDENCE,
                'confidence_stability' => 0.8,
                'over_confidence_detected' => false,
                'under_confidence_detected' => false
            ],
            'recent_evaluations' => [
                'count_30_days' => 0,
                'latest_evaluations' => []
            ],
            'last_evaluation_date' => null,
            'total_evaluations' => 0
        ];
    }
    
    /**
     * Get default profile when critic not found
     * 
     * @param string $criticId Critic identifier
     * @return array Default profile
     */
    private function getDefaultProfile(string $criticId): array
    {
        return [
            'critic_id' => $criticId,
            'critic_name' => $criticId,
            'domains' => ['general'],
            'expertise_level' => self::DEFAULT_EXPERTISE_LEVEL,
            'evaluation_capabilities' => []
        ];
    }
    
    /**
     * Get default reputation history when none available
     * 
     * @return array Default reputation history
     */
    private function getDefaultReputationHistory(): array
    {
        return [
            'data_points' => [],
            'trend' => 'insufficient_data',
            'stability' => 0.5,
            'total_records' => 0
        ];
    }
}
