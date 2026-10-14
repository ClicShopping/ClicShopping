<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine;

/**
 * WeightNormalizer - Normalizes critic weights to ensure valid probability distribution
 * 
 * Ensures all weights sum to 1.0, handles edge cases (all zeros, negatives),
 * and applies optional min/max bounds specified by LLM analysis.
 * All normalization operations are logged for audit purposes.
 * 
 * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubActorCritic\WeightingEngine
 * @version 1.0.0
 * @since 2026-02-06
 */
class WeightNormalizer
{
    private const EPSILON = 0.001; // Tolerance for floating point comparisons
    private const MIN_WEIGHT = 0.0;
    private const MAX_WEIGHT = 1.0;
    
    private array $normalizationLog = [];
    
    /**
     * Normalize weights to ensure they sum to 1.0
     * 
     * Takes raw weights and normalizes them so their sum equals 1.0.
     * Handles edge cases:
     * - All zeros → equal weighting (1/N)
     * - Negative values → set to 0 before normalization
     * - Single critic → weight = 1.0
     * 
     * Requirements: 7.1, 7.2, 7.3, 7.5
     * 
     * @param array<string, float> $weights Map of critic_id => weight
     * @return array<string, float> Normalized weights
     * @throws \InvalidArgumentException If weights array is empty
     */
    public function normalize(array $weights): array
    {
        if (empty($weights)) {
            throw new \InvalidArgumentException('Cannot normalize empty weights array');
        }
        
        $criticIds = array_keys($weights);
        $numCritics = count($criticIds);
        
        // Log original weights
        $this->logOperation('normalize_start', [
            'original_weights' => $weights,
            'num_critics' => $numCritics
        ]);
        
        // Handle negative weights - set to 0
        $cleanedWeights = [];
        $hadNegatives = false;
        
        foreach ($weights as $criticId => $weight) {
            if ($weight < 0) {
                $cleanedWeights[$criticId] = 0.0;
                $hadNegatives = true;
                $this->logOperation('negative_weight_corrected', [
                    'critic_id' => $criticId,
                    'original_weight' => $weight,
                    'corrected_weight' => 0.0
                ]);
            } else {
                $cleanedWeights[$criticId] = $weight;
            }
        }
        
        // Calculate sum
        $sum = array_sum($cleanedWeights);
        
        // Handle edge case: all zeros → equal weighting
        if ($sum < self::EPSILON) {
            $equalWeight = 1.0 / $numCritics;
            $normalizedWeights = array_fill_keys($criticIds, $equalWeight);
            
            $this->logOperation('equal_weighting_applied', [
                'reason' => 'all_weights_zero_or_negative',
                'equal_weight' => $equalWeight,
                'normalized_weights' => $normalizedWeights
            ]);
            
            return $normalizedWeights;
        }
        
        // Normalize: divide each weight by sum
        $normalizedWeights = [];
        foreach ($cleanedWeights as $criticId => $weight) {
            $normalizedWeights[$criticId] = $weight / $sum;
        }
        
        // Verify normalization (sum should be 1.0 within epsilon)
        $normalizedSum = array_sum($normalizedWeights);
        $sumDifference = abs($normalizedSum - 1.0);
        
        if ($sumDifference > self::EPSILON) {
            // Adjust largest weight to ensure exact sum of 1.0
            $normalizedWeights = $this->adjustForRoundingError($normalizedWeights);
            
            $this->logOperation('rounding_adjustment', [
                'original_sum' => $normalizedSum,
                'difference' => $sumDifference,
                'adjusted_weights' => $normalizedWeights
            ]);
        }
        
        $this->logOperation('normalize_complete', [
            'original_sum' => $sum,
            'normalized_sum' => array_sum($normalizedWeights),
            'had_negatives' => $hadNegatives,
            'normalized_weights' => $normalizedWeights
        ]);
        
        return $normalizedWeights;
    }
    
    /**
     * Validate weights before normalization
     * 
     * Checks that:
     * - All weights are non-negative (or can be corrected)
     * - Sum is greater than 0 (or can be handled with equal weighting)
     * - No NaN or infinite values
     * 
     * Requirements: 7.2, 7.3
     * 
     * @param array<string, float> $weights Map of critic_id => weight
     * @return array Validation result with 'valid' boolean and 'issues' array
     */
    public function validateWeights(array $weights): array
    {
        $issues = [];
        $canBeFixed = true;
        
        if (empty($weights)) {
            return [
                'valid' => false,
                'can_be_fixed' => false,
                'issues' => ['empty_weights_array']
            ];
        }
        
        $allZero = true;
        $hasNegatives = false;
        $hasInvalid = false;
        
        foreach ($weights as $criticId => $weight) {
            // Check for NaN or infinite
            if (!is_finite($weight)) {
                $issues[] = "invalid_value_for_{$criticId}";
                $hasInvalid = true;
                $canBeFixed = false;
            }
            
            // Check for negative
            if ($weight < 0) {
                $issues[] = "negative_weight_for_{$criticId}";
                $hasNegatives = true;
                // Negatives can be fixed by setting to 0
            }
            
            // Check if all are zero
            if ($weight > self::EPSILON) {
                $allZero = false;
            }
        }
        
        // All zeros can be fixed with equal weighting
        if ($allZero && !$hasInvalid) {
            $issues[] = 'all_weights_zero';
            // This can be fixed
        }
        
        $valid = empty($issues);
        
        $this->logOperation('validate_weights', [
            'valid' => $valid,
            'can_be_fixed' => $canBeFixed,
            'issues' => $issues,
            'weights' => $weights
        ]);
        
        return [
            'valid' => $valid,
            'can_be_fixed' => $canBeFixed,
            'issues' => $issues
        ];
    }
    
    /**
     * Apply min/max bounds to weights if specified by LLM
     * 
     * Applies optional bounds constraints to weights before normalization.
     * After applying bounds, re-normalizes to ensure sum = 1.0.
     * 
     * Requirements: 7.4
     * 
     * @param array<string, float> $weights Map of critic_id => weight
     * @param array|null $bounds Optional bounds: ['min' => float, 'max' => float]
     * @return array<string, float> Bounded and normalized weights
     */
    public function applyBounds(array $weights, ?array $bounds = null): array
    {
        if ($bounds === null || empty($bounds)) {
            $this->logOperation('no_bounds_applied', [
                'weights' => $weights
            ]);
            return $weights;
        }
        
        $minBound = $bounds['min'] ?? self::MIN_WEIGHT;
        $maxBound = $bounds['max'] ?? self::MAX_WEIGHT;
        
        // Validate bounds
        if ($minBound < self::MIN_WEIGHT || $maxBound > self::MAX_WEIGHT || $minBound > $maxBound) {
            $this->logOperation('invalid_bounds', [
                'min' => $minBound,
                'max' => $maxBound,
                'reason' => 'bounds_out_of_range_or_inverted'
            ]);
            return $weights;
        }
        
        $this->logOperation('apply_bounds_start', [
            'original_weights' => $weights,
            'min_bound' => $minBound,
            'max_bound' => $maxBound
        ]);
        
        $boundedWeights = [];
        $adjustmentsMade = false;
        
        foreach ($weights as $criticId => $weight) {
            $originalWeight = $weight;
            
            // Apply min bound
            if ($weight < $minBound) {
                $weight = $minBound;
                $adjustmentsMade = true;
                $this->logOperation('min_bound_applied', [
                    'critic_id' => $criticId,
                    'original_weight' => $originalWeight,
                    'bounded_weight' => $weight
                ]);
            }
            
            // Apply max bound
            if ($weight > $maxBound) {
                $weight = $maxBound;
                $adjustmentsMade = true;
                $this->logOperation('max_bound_applied', [
                    'critic_id' => $criticId,
                    'original_weight' => $originalWeight,
                    'bounded_weight' => $weight
                ]);
            }
            
            $boundedWeights[$criticId] = $weight;
        }
        
        // Re-normalize after applying bounds
        if ($adjustmentsMade) {
            // First normalize
            $boundedWeights = $this->normalize($boundedWeights);
            
            // Check if renormalization violated bounds and adjust if needed
            $needsSecondPass = false;
            foreach ($boundedWeights as $criticId => $weight) {
                if ($weight > $maxBound + self::EPSILON) {
                    $needsSecondPass = true;
                    break;
                }
            }
            
            // If bounds were violated after normalization, apply iterative adjustment
            if ($needsSecondPass) {
                $boundedWeights = $this->iterativelyApplyBounds($boundedWeights, $minBound, $maxBound);
            }
            
            $this->logOperation('bounds_renormalized', [
                'bounded_weights' => $boundedWeights,
                'needed_second_pass' => $needsSecondPass
            ]);
        }
        
        return $boundedWeights;
    }
    
    /**
     * Get normalization log for audit purposes
     * 
     * Returns all logged normalization operations for this instance.
     * Used by WeightAuditLogger to store complete audit trail.
     * 
     * Requirements: 7.5
     * 
     * @return array Array of log entries
     */
    public function getNormalizationLog(): array
    {
        return $this->normalizationLog;
    }
    
    /**
     * Clear normalization log
     * 
     * Clears the log for this instance. Should be called after
     * log has been persisted to avoid memory buildup.
     * 
     * @return void
     */
    public function clearLog(): void
    {
        $this->normalizationLog = [];
    }
    
    /**
     * Adjust weights to fix rounding errors
     * 
     * Ensures weights sum to exactly 1.0 by adjusting the largest weight.
     * This handles floating point precision issues.
     * 
     * @param array<string, float> $weights Normalized weights
     * @return array<string, float> Adjusted weights
     */
    private function adjustForRoundingError(array $weights): array
    {
        $sum = array_sum($weights);
        $difference = 1.0 - $sum;
        
        // Find critic with largest weight
        $maxCriticId = array_keys($weights, max($weights))[0];
        
        // Adjust largest weight
        $weights[$maxCriticId] += $difference;
        
        return $weights;
    }
    
    /**
     * Iteratively apply bounds to ensure constraints are met after normalization
     * 
     * Uses iterative approach to ensure all weights stay within bounds
     * while maintaining sum = 1.0. This handles cases where normalization
     * causes weights to exceed max bound.
     * 
     * @param array<string, float> $weights Weights to bound
     * @param float $minBound Minimum weight
     * @param float $maxBound Maximum weight
     * @param int $maxIterations Maximum iterations to prevent infinite loops
     * @return array<string, float> Bounded and normalized weights
     */
    private function iterativelyApplyBounds(array $weights, float $minBound, float $maxBound, int $maxIterations = 10): array
    {
        $iteration = 0;
        
        while ($iteration < $maxIterations) {
            $needsAdjustment = false;
            $excess = 0.0;
            $adjustableWeights = [];
            
            // Identify weights that exceed max bound and calculate excess
            foreach ($weights as $criticId => $weight) {
                if ($weight > $maxBound + self::EPSILON) {
                    $excess += ($weight - $maxBound);
                    $weights[$criticId] = $maxBound;
                    $needsAdjustment = true;
                } elseif ($weight < $maxBound - self::EPSILON) {
                    // This weight can absorb excess
                    $adjustableWeights[] = $criticId;
                }
            }
            
            if (!$needsAdjustment) {
                break; // All weights within bounds
            }
            
            // Distribute excess to adjustable weights
            if (!empty($adjustableWeights) && $excess > 0) {
                $excessPerWeight = $excess / count($adjustableWeights);
                
                foreach ($adjustableWeights as $criticId) {
                    $weights[$criticId] += $excessPerWeight;
                    
                    // Ensure we don't exceed max bound
                    if ($weights[$criticId] > $maxBound) {
                        $weights[$criticId] = $maxBound;
                    }
                }
            }
            
            $iteration++;
        }
        
        // Final normalization to ensure sum = 1.0
        $sum = array_sum($weights);
        if (abs($sum - 1.0) > self::EPSILON) {
            foreach ($weights as $criticId => $weight) {
                $weights[$criticId] = $weight / $sum;
            }
        }
        
        $this->logOperation('iterative_bounds_applied', [
            'iterations' => $iteration,
            'final_weights' => $weights
        ]);
        
        return $weights;
    }
    
    /**
     * Log a normalization operation
     * 
     * Adds an entry to the normalization log with timestamp.
     * 
     * Requirements: 7.5
     * 
     * @param string $operation Operation name
     * @param array $data Operation data
     * @return void
     */
    private function logOperation(string $operation, array $data): void
    {
        $this->normalizationLog[] = [
            'operation' => $operation,
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            'data' => $data
        ];
    }
}
