<?php
declare(strict_types=1);

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation;

use ClicShopping\OM\Registry;
use ClicShopping\AI\CoreAI\Orchestrator\SubReputation\Models\ConsensusResult;
use DateTimeImmutable;
use Exception;

/**
 * ConsensusQualityTracker - Tracks and analyzes consensus quality over time
 * 
 * Calculates correlation between reputation weighting and consensus quality,
 * generates effectiveness reports, and provides insights into the reputation
 * system's impact on consensus building.
 * 
 * Requirements: 11.1, 11.2, 11.3, 11.4, 11.5
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubReputation
 * @version 1.0.0
 * @since 2026-02-04
 */
class ConsensusQualityTracker
{
    private bool $debug;
    private array $consensusHistory = [];
    
    /**
     * Constructor
     * 
     * Initializes the consensus quality tracker.
     */
    public function __construct()
    {
        $this->debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && 
                       CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';
    }
    
    /**
     * Track a consensus result for quality analysis
     * 
     * Stores consensus results in memory for correlation analysis.
     * In production, this would store to database.
     * 
     * Requirement 11.1: Track consensus quality metrics
     * 
     * @param ConsensusResult $result The consensus result to track
     */
    public function trackConsensus(ConsensusResult $result): void
    {
        $this->consensusHistory[] = [
            'weighted_score' => $result->weightedScore,
            'unweighted_score' => $result->unweightedScore,
            'difference' => $result->difference,
            'agreement_level' => $result->agreementLevel,
            'confidence' => $result->confidence,
            'stability' => $result->stability,
            'timestamp' => $result->calculatedAt->getTimestamp()
        ];
        
        if ($this->debug) {
            error_log(sprintf(
                "ConsensusQualityTracker: Tracked consensus - Agreement: %.4f, Confidence: %.4f, Stability: %.4f",
                $result->agreementLevel,
                $result->confidence,
                $result->stability
            ));
        }
    }
    
    /**
     * Calculate correlation between reputation weighting and consensus quality
     * 
     * Calculates Pearson correlation coefficient between the magnitude of
     * reputation weighting (difference between weighted and unweighted) and
     * the overall consensus quality (average of agreement, confidence, stability).
     * 
     * Requirement 11.4: Calculate correlation between reputation weighting and consensus quality
     * 
     * @param int $days Number of days to analyze (default: 30)
     * @return array Correlation analysis results
     */
    public function calculateCorrelation(int $days = 30): array
    {
        try {
            // Filter consensus history to specified time period
            $cutoffTime = (new DateTimeImmutable())->modify("-{$days} days")->getTimestamp();
            $recentConsensus = array_filter(
                $this->consensusHistory,
                fn($c) => $c['timestamp'] >= $cutoffTime
            );
            
            if (count($recentConsensus) < 2) {
                if ($this->debug) {
                    error_log("ConsensusQualityTracker: Insufficient data for correlation (need at least 2 points)");
                }
                return [
                    'correlation' => 0.0,
                    'sample_size' => count($recentConsensus),
                    'error' => 'Insufficient data'
                ];
            }
            
            // Extract variables for correlation
            $weightingMagnitudes = [];
            $qualityScores = [];
            
            foreach ($recentConsensus as $consensus) {
                // Weighting magnitude = absolute difference between weighted and unweighted
                $weightingMagnitudes[] = abs($consensus['difference']);
                
                // Quality score = average of agreement, confidence, and stability
                $qualityScores[] = (
                    $consensus['agreement_level'] +
                    $consensus['confidence'] +
                    $consensus['stability']
                ) / 3.0;
            }
            
            // Calculate Pearson correlation coefficient
            $correlation = $this->calculatePearsonCorrelation($weightingMagnitudes, $qualityScores);
            
            // Calculate additional statistics
            $avgWeighting = array_sum($weightingMagnitudes) / count($weightingMagnitudes);
            $avgQuality = array_sum($qualityScores) / count($qualityScores);
            
            $result = [
                'correlation' => $correlation,
                'sample_size' => count($recentConsensus),
                'avg_weighting_magnitude' => $avgWeighting,
                'avg_quality_score' => $avgQuality,
                'period_days' => $days,
                'calculated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s')
            ];
            
            if ($this->debug) {
                error_log(sprintf(
                    "ConsensusQualityTracker: Correlation: %.4f (n=%d, avg_weighting=%.4f, avg_quality=%.4f)",
                    $correlation,
                    count($recentConsensus),
                    $avgWeighting,
                    $avgQuality
                ));
            }
            
            return $result;
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ConsensusQualityTracker: Error calculating correlation - " . $e->getMessage());
            }
            
            return [
                'correlation' => 0.0,
                'sample_size' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate Pearson correlation coefficient
     * 
     * Calculates the Pearson correlation coefficient between two arrays of values.
     * Returns a value between -1.0 (perfect negative correlation) and 1.0 (perfect positive correlation).
     * 
     * @param array $x First variable array
     * @param array $y Second variable array
     * @return float Pearson correlation coefficient
     * @throws Exception If arrays are different lengths or empty
     */
    private function calculatePearsonCorrelation(array $x, array $y): float
    {
        $n = count($x);
        
        if ($n !== count($y)) {
            throw new Exception("Arrays must be same length");
        }
        
        if ($n < 2) {
            throw new Exception("Need at least 2 data points");
        }
        
        // Calculate means
        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;
        
        // Calculate covariance and standard deviations
        $covariance = 0.0;
        $varianceX = 0.0;
        $varianceY = 0.0;
        
        for ($i = 0; $i < $n; $i++) {
            $diffX = $x[$i] - $meanX;
            $diffY = $y[$i] - $meanY;
            
            $covariance += $diffX * $diffY;
            $varianceX += $diffX * $diffX;
            $varianceY += $diffY * $diffY;
        }
        
        // Handle edge case: no variance
        if ($varianceX == 0 || $varianceY == 0) {
            return 0.0;
        }
        
        // Calculate correlation
        $correlation = $covariance / sqrt($varianceX * $varianceY);
        
        return $correlation;
    }
    
    /**
     * Generate monthly effectiveness report
     * 
     * Generates a comprehensive report on reputation system effectiveness
     * over the past month, including correlation analysis, quality trends,
     * and recommendations.
     * 
     * Requirement 11.5: Generate monthly effectiveness reports
     * 
     * @return array Monthly effectiveness report
     */
    public function generateMonthlyReport(): array
    {
        try {
            // Calculate correlation for the past month
            $correlation = $this->calculateCorrelation(30);
            
            // Get consensus history for the past month
            $cutoffTime = (new DateTimeImmutable())->modify("-30 days")->getTimestamp();
            $monthlyConsensus = array_filter(
                $this->consensusHistory,
                fn($c) => $c['timestamp'] >= $cutoffTime
            );
            
            if (empty($monthlyConsensus)) {
                return [
                    'period' => 'Last 30 days',
                    'total_consensus_operations' => 0,
                    'error' => 'No data available for the period'
                ];
            }
            
            // Calculate quality metrics trends
            $qualityTrends = $this->calculateQualityTrends($monthlyConsensus);
            
            // Calculate weighting impact
            $weightingImpact = $this->calculateWeightingImpact($monthlyConsensus);
            
            // Generate recommendations
            $recommendations = $this->generateRecommendations($correlation, $qualityTrends, $weightingImpact);
            
            $report = [
                'period' => 'Last 30 days',
                'generated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                'total_consensus_operations' => count($monthlyConsensus),
                'correlation_analysis' => $correlation,
                'quality_trends' => $qualityTrends,
                'weighting_impact' => $weightingImpact,
                'recommendations' => $recommendations
            ];
            
            if ($this->debug) {
                error_log(sprintf(
                    "ConsensusQualityTracker: Generated monthly report - %d operations, correlation: %.4f",
                    count($monthlyConsensus),
                    $correlation['correlation'] ?? 0.0
                ));
            }
            
            return $report;
            
        } catch (Exception $e) {
            if ($this->debug) {
                error_log("ConsensusQualityTracker: Error generating monthly report - " . $e->getMessage());
            }
            
            return [
                'period' => 'Last 30 days',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate quality metrics trends
     * 
     * Analyzes trends in agreement, confidence, and stability over time.
     * 
     * @param array $consensusData Array of consensus data
     * @return array Quality trends analysis
     */
    private function calculateQualityTrends(array $consensusData): array
    {
        if (empty($consensusData)) {
            return [
                'avg_agreement' => 0.0,
                'avg_confidence' => 0.0,
                'avg_stability' => 0.0
            ];
        }
        
        $totalAgreement = 0.0;
        $totalConfidence = 0.0;
        $totalStability = 0.0;
        $count = count($consensusData);
        
        foreach ($consensusData as $consensus) {
            $totalAgreement += $consensus['agreement_level'];
            $totalConfidence += $consensus['confidence'];
            $totalStability += $consensus['stability'];
        }
        
        return [
            'avg_agreement' => $totalAgreement / $count,
            'avg_confidence' => $totalConfidence / $count,
            'avg_stability' => $totalStability / $count,
            'overall_quality' => ($totalAgreement + $totalConfidence + $totalStability) / (3 * $count)
        ];
    }
    
    /**
     * Calculate weighting impact
     * 
     * Analyzes the impact of reputation weighting on consensus scores.
     * 
     * @param array $consensusData Array of consensus data
     * @return array Weighting impact analysis
     */
    private function calculateWeightingImpact(array $consensusData): array
    {
        if (empty($consensusData)) {
            return [
                'avg_difference' => 0.0,
                'significant_differences' => 0,
                'percentage_significant' => 0.0
            ];
        }
        
        $totalDifference = 0.0;
        $significantDifferences = 0;
        $significanceThreshold = 0.05;
        
        foreach ($consensusData as $consensus) {
            $difference = abs($consensus['difference']);
            $totalDifference += $difference;
            
            if ($difference >= $significanceThreshold) {
                $significantDifferences++;
            }
        }
        
        $count = count($consensusData);
        
        return [
            'avg_difference' => $totalDifference / $count,
            'significant_differences' => $significantDifferences,
            'percentage_significant' => ($significantDifferences / $count) * 100,
            'significance_threshold' => $significanceThreshold
        ];
    }
    
    /**
     * Generate recommendations based on analysis
     * 
     * Generates actionable recommendations based on correlation and quality trends.
     * 
     * @param array $correlation Correlation analysis results
     * @param array $qualityTrends Quality trends analysis
     * @param array $weightingImpact Weighting impact analysis
     * @return array Recommendations
     */
    private function generateRecommendations(array $correlation, array $qualityTrends, array $weightingImpact): array
    {
        $recommendations = [];
        
        // Analyze correlation
        $correlationValue = $correlation['correlation'] ?? 0.0;
        
        if ($correlationValue > 0.5) {
            $recommendations[] = [
                'type' => 'positive',
                'message' => 'Strong positive correlation detected. Reputation weighting is improving consensus quality.',
                'action' => 'Continue current reputation weighting strategy.'
            ];
        } elseif ($correlationValue < -0.5) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Negative correlation detected. Reputation weighting may be reducing consensus quality.',
                'action' => 'Review reputation calculation formula and weighting factors.'
            ];
        } else {
            $recommendations[] = [
                'type' => 'neutral',
                'message' => 'Weak correlation detected. Reputation weighting has minimal impact on consensus quality.',
                'action' => 'Consider adjusting reputation weights or gathering more data.'
            ];
        }
        
        // Analyze quality trends
        $overallQuality = $qualityTrends['overall_quality'] ?? 0.0;
        
        if ($overallQuality < 0.6) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Low overall consensus quality detected.',
                'action' => 'Review critic selection criteria and reputation calculation methods.'
            ];
        } elseif ($overallQuality > 0.8) {
            $recommendations[] = [
                'type' => 'positive',
                'message' => 'High overall consensus quality achieved.',
                'action' => 'Maintain current system configuration.'
            ];
        }
        
        // Analyze weighting impact
        $percentageSignificant = $weightingImpact['percentage_significant'] ?? 0.0;
        
        if ($percentageSignificant < 10) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Reputation weighting rarely makes significant differences.',
                'action' => 'Consider increasing reputation weight factors or reviewing critic diversity.'
            ];
        } elseif ($percentageSignificant > 50) {
            $recommendations[] = [
                'type' => 'info',
                'message' => 'Reputation weighting frequently makes significant differences.',
                'action' => 'Monitor for potential over-reliance on high-reputation critics.'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Clear consensus history
     * 
     * Clears the in-memory consensus history. Useful for testing.
     */
    public function clearHistory(): void
    {
        $this->consensusHistory = [];
        
        if ($this->debug) {
            error_log("ConsensusQualityTracker: Cleared consensus history");
        }
    }
    
    /**
     * Get consensus history count
     * 
     * @return int Number of consensus results tracked
     */
    public function getHistoryCount(): int
    {
        return count($this->consensusHistory);
    }
}
