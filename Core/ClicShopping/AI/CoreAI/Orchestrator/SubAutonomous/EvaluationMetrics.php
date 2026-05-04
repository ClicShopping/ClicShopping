<?php
/**
 * EvaluationMetrics Class
 *
 * Defines and calculates evaluation metrics for different output types.
 * Manages metric definitions, weights, and score calculations for inter-agent evaluation.
 * Supports multiple evaluation dimensions: accuracy, completeness, efficiency, and clarity.
 *
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous
 * @since 1.0.0
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubAutonomous;

use InvalidArgumentException;

class EvaluationMetrics
{
  // Metric definitions for each output type
  private array $metricDefinitions = [];
  
  // Weights for each output type's dimensions
  private array $weights = [];
  
  // Valid dimension names
  private const VALID_DIMENSIONS = ['accuracy', 'completeness', 'efficiency', 'clarity'];
  
  /**
   * Constructor
   *
   * Initializes the evaluation metrics with default metrics for common output types.
   */
  public function __construct()
  {
    $this->initializeDefaultMetrics();
  }
  
  /**
   * Initialize default metrics for common output types
   *
   * Sets up predefined metric definitions and weights for:
   * - sql_query: SQL query generation and validation
   * - reasoning_chain: Logical reasoning and inference
   * - validation_result: Validation and verification outputs
   */
  private function initializeDefaultMetrics(): void
  {
    // SQL Query metrics
    $this->defineMetrics('sql_query', [
      'accuracy' => [
        'description' => 'Correctness of SQL syntax and logic',
        'criteria' => [
          'Valid SQL syntax',
          'Correct table and column references',
          'Proper JOIN conditions',
          'Accurate WHERE clauses',
          'Correct aggregation functions'
        ]
      ],
      'completeness' => [
        'description' => 'Coverage of all required data and conditions',
        'criteria' => [
          'All requested fields included',
          'All necessary filters applied',
          'Required JOINs present',
          'Proper GROUP BY and ORDER BY',
          'Handles edge cases'
        ]
      ],
      'efficiency' => [
        'description' => 'Query performance and optimization',
        'criteria' => [
          'Uses appropriate indexes',
          'Minimizes subqueries',
          'Avoids unnecessary JOINs',
          'Efficient WHERE conditions',
          'Proper LIMIT usage'
        ]
      ],
      'clarity' => [
        'description' => 'Readability and maintainability',
        'criteria' => [
          'Clear table aliases',
          'Logical structure',
          'Proper formatting',
          'Meaningful column names',
          'Comments where needed'
        ]
      ]
    ]);
    
    $this->setWeights('sql_query', [
      'accuracy' => 0.40,      // 40% - Most important for correctness
      'completeness' => 0.30,  // 30% - Ensures all requirements met
      'efficiency' => 0.20,    // 20% - Performance matters
      'clarity' => 0.10        // 10% - Readability is helpful
    ]);
    
    // Reasoning Chain metrics
    $this->defineMetrics('reasoning_chain', [
      'accuracy' => [
        'description' => 'Logical correctness and validity of reasoning',
        'criteria' => [
          'Valid logical inferences',
          'Correct application of rules',
          'No logical fallacies',
          'Sound conclusions',
          'Accurate fact usage'
        ]
      ],
      'completeness' => [
        'description' => 'Coverage of all reasoning steps',
        'criteria' => [
          'All premises stated',
          'No missing steps',
          'All assumptions explicit',
          'Complete argument chain',
          'Addresses counterarguments'
        ]
      ],
      'efficiency' => [
        'description' => 'Conciseness and directness of reasoning',
        'criteria' => [
          'No redundant steps',
          'Direct path to conclusion',
          'Minimal assumptions',
          'Efficient proof strategy',
          'Avoids circular reasoning'
        ]
      ],
      'clarity' => [
        'description' => 'Understandability and structure',
        'criteria' => [
          'Clear step labeling',
          'Logical flow',
          'Explicit connections',
          'Unambiguous language',
          'Well-organized structure'
        ]
      ]
    ]);
    
    $this->setWeights('reasoning_chain', [
      'accuracy' => 0.35,      // 35% - Logical correctness is critical
      'completeness' => 0.35,  // 35% - All steps must be present
      'efficiency' => 0.10,    // 10% - Conciseness is less critical
      'clarity' => 0.20        // 20% - Understanding is important
    ]);
    
    // Validation Result metrics
    $this->defineMetrics('validation_result', [
      'accuracy' => [
        'description' => 'Correctness of validation checks',
        'criteria' => [
          'Correct validation rules applied',
          'Accurate error detection',
          'No false positives',
          'No false negatives',
          'Proper severity assessment'
        ]
      ],
      'completeness' => [
        'description' => 'Coverage of all validation aspects',
        'criteria' => [
          'All required checks performed',
          'Edge cases validated',
          'All error types covered',
          'Complete error messages',
          'All constraints verified'
        ]
      ],
      'efficiency' => [
        'description' => 'Performance of validation process',
        'criteria' => [
          'Fast validation execution',
          'Minimal redundant checks',
          'Early failure detection',
          'Efficient rule evaluation',
          'Optimized validation order'
        ]
      ],
      'clarity' => [
        'description' => 'Clarity of validation results',
        'criteria' => [
          'Clear error messages',
          'Specific issue identification',
          'Actionable feedback',
          'Proper error categorization',
          'Helpful suggestions'
        ]
      ]
    ]);
    
    $this->setWeights('validation_result', [
      'accuracy' => 0.40,      // 40% - Correct validation is paramount
      'completeness' => 0.30,  // 30% - All checks must be done
      'efficiency' => 0.10,    // 10% - Speed is less critical
      'clarity' => 0.20        // 20% - Clear feedback is important
    ]);
  }
  
  /**
   * Define metrics for an output type
   *
   * Sets the metric definitions for a specific output type.
   * Each metric must include all four required dimensions.
   *
   * @param string $outputType The output type identifier
   * @param array $metrics Array of metric definitions with dimensions
   * @throws InvalidArgumentException If metrics are invalid
   */
  public function defineMetrics(string $outputType, array $metrics): void
  {
    // Validate that all required dimensions are present
    foreach (self::VALID_DIMENSIONS as $dimension) {
      if (!isset($metrics[$dimension])) {
        throw new InvalidArgumentException(
          "Missing required dimension '{$dimension}' for output type '{$outputType}'"
        );
      }
      
      // Validate dimension structure
      if (!isset($metrics[$dimension]['description']) || !isset($metrics[$dimension]['criteria'])) {
        throw new InvalidArgumentException(
          "Dimension '{$dimension}' must have 'description' and 'criteria' fields"
        );
      }
      
      if (!\is_array($metrics[$dimension]['criteria'])) {
        throw new InvalidArgumentException(
          "Criteria for dimension '{$dimension}' must be an array"
        );
      }
    }
    
    $this->metricDefinitions[$outputType] = $metrics;
  }
  
  /**
   * Get metrics for an output type
   *
   * Retrieves the metric definitions for a specific output type.
   *
   * @param string $outputType The output type identifier
   * @return array The metric definitions or empty array if not found
   */
  public function getMetrics(string $outputType): array
  {
    return $this->metricDefinitions[$outputType] ?? [];
  }
  
  /**
   * Calculate overall score from dimension scores
   *
   * Calculates the weighted average of dimension scores for an output type.
   * Uses the configured weights for the output type.
   *
   * @param string $outputType The output type identifier
   * @param array $dimensions Array of dimension scores (accuracy, completeness, efficiency, clarity)
   * @return float The calculated overall score (0.0 - 1.0)
   * @throws InvalidArgumentException If dimensions are invalid or weights not configured
   */
  public function calculateScore(string $outputType, array $dimensions): float
  {
    // Validate that weights are configured for this output type
    if (!isset($this->weights[$outputType])) {
      throw new InvalidArgumentException(
        "Weights not configured for output type '{$outputType}'"
      );
    }
    
    // Validate that all required dimensions are present
    foreach (self::VALID_DIMENSIONS as $dimension) {
      if (!isset($dimensions[$dimension])) {
        throw new InvalidArgumentException(
          "Missing required dimension score '{$dimension}'"
        );
      }
      
      // Validate score range
      $score = $dimensions[$dimension];
      if (!\is_numeric($score) || $score < 0.0 || $score > 1.0) {
        throw new InvalidArgumentException(
          "Dimension score '{$dimension}' must be between 0.0 and 1.0, got: {$score}"
        );
      }
    }
    
    // Calculate weighted average
    $weights = $this->weights[$outputType];
    $overallScore = 0.0;
    
    foreach (self::VALID_DIMENSIONS as $dimension) {
      $overallScore += $dimensions[$dimension] * $weights[$dimension];
    }
    
    // Round to 2 decimal places
    return \round($overallScore, 2);
  }
  
  /**
   * Get weights for an output type
   *
   * Retrieves the dimension weights for a specific output type.
   *
   * @param string $outputType The output type identifier
   * @return array The weights array or empty array if not found
   */
  public function getWeights(string $outputType): array
  {
    return $this->weights[$outputType] ?? [];
  }
  
  /**
   * Set weights for an output type
   *
   * Sets the dimension weights for a specific output type.
   * Weights must sum to 1.0 and cover all four dimensions.
   *
   * @param string $outputType The output type identifier
   * @param array $weights Array of weights for each dimension
   * @throws InvalidArgumentException If weights are invalid
   */
  public function setWeights(string $outputType, array $weights): void
  {
    // Validate that all required dimensions have weights
    foreach (self::VALID_DIMENSIONS as $dimension) {
      if (!isset($weights[$dimension])) {
        throw new InvalidArgumentException(
          "Missing weight for dimension '{$dimension}'"
        );
      }
      
      // Validate weight range
      $weight = $weights[$dimension];
      if (!\is_numeric($weight) || $weight < 0.0 || $weight > 1.0) {
        throw new InvalidArgumentException(
          "Weight for dimension '{$dimension}' must be between 0.0 and 1.0, got: {$weight}"
        );
      }
    }
    
    // Validate that weights sum to 1.0 (with small tolerance for floating point)
    $sum = \array_sum($weights);
    if (\abs($sum - 1.0) > 0.001) {
      throw new InvalidArgumentException(
        "Weights must sum to 1.0, got: {$sum}"
      );
    }
    
    $this->weights[$outputType] = $weights;
  }
  
  /**
   * Get all registered output types
   *
   * Returns a list of all output types that have metric definitions.
   *
   * @return array Array of output type identifiers
   */
  public function getAllOutputTypes(): array
  {
    return \array_keys($this->metricDefinitions);
  }
  
  /**
   * Check if metrics are defined for an output type
   *
   * Determines whether metric definitions exist for a specific output type.
   *
   * @param string $outputType The output type identifier
   * @return bool True if metrics are defined, false otherwise
   */
  public function hasMetrics(string $outputType): bool
  {
    return isset($this->metricDefinitions[$outputType]);
  }
  
  /**
   * Check if weights are configured for an output type
   *
   * Determines whether weights are configured for a specific output type.
   *
   * @param string $outputType The output type identifier
   * @return bool True if weights are configured, false otherwise
   */
  public function hasWeights(string $outputType): bool
  {
    return isset($this->weights[$outputType]);
  }
  
  /**
   * Remove metrics for an output type
   *
   * Removes the metric definitions and weights for a specific output type.
   *
   * @param string $outputType The output type identifier
   */
  public function removeMetrics(string $outputType): void
  {
    unset($this->metricDefinitions[$outputType]);
    unset($this->weights[$outputType]);
  }
  
  /**
   * Get metric summary
   *
   * Returns a summary of all configured metrics including
   * output types, dimensions, and weights.
   *
   * @return array Summary array with metrics information
   */
  public function getMetricSummary(): array
  {
    $summary = [
      'total_output_types' => \count($this->metricDefinitions),
      'output_types' => []
    ];
    
    foreach ($this->metricDefinitions as $outputType => $metrics) {
      $summary['output_types'][$outputType] = [
        'dimensions' => \array_keys($metrics),
        'weights' => $this->weights[$outputType] ?? null,
        'weights_configured' => isset($this->weights[$outputType])
      ];
    }
    
    return $summary;
  }
  
  /**
   * Validate dimension scores
   *
   * Validates that a set of dimension scores is complete and within valid ranges.
   *
   * @param array $dimensions Array of dimension scores
   * @return array Array with 'valid' boolean and 'errors' array
   */
  public function validateDimensionScores(array $dimensions): array
  {
    $errors = [];
    
    // Check for missing dimensions
    foreach (self::VALID_DIMENSIONS as $dimension) {
      if (!isset($dimensions[$dimension])) {
        $errors[] = "Missing required dimension: {$dimension}";
      } else {
        // Validate score range
        $score = $dimensions[$dimension];
        if (!\is_numeric($score) || $score < 0.0 || $score > 1.0) {
          $errors[] = "Invalid score for {$dimension}: must be between 0.0 and 1.0";
        }
      }
    }
    
    // Check for extra dimensions
    foreach (\array_keys($dimensions) as $dimension) {
      if (!\in_array($dimension, self::VALID_DIMENSIONS, true)) {
        $errors[] = "Unknown dimension: {$dimension}";
      }
    }
    
    return [
      'valid' => empty($errors),
      'errors' => $errors
    ];
  }
  
  /**
   * Get dimension criteria
   *
   * Retrieves the evaluation criteria for a specific dimension of an output type.
   *
   * @param string $outputType The output type identifier
   * @param string $dimension The dimension name
   * @return array The criteria array or empty array if not found
   */
  public function getDimensionCriteria(string $outputType, string $dimension): array
  {
    if (!isset($this->metricDefinitions[$outputType][$dimension])) {
      return [];
    }
    
    return $this->metricDefinitions[$outputType][$dimension]['criteria'] ?? [];
  }
  
  /**
   * Get dimension description
   *
   * Retrieves the description for a specific dimension of an output type.
   *
   * @param string $outputType The output type identifier
   * @param string $dimension The dimension name
   * @return string The description or empty string if not found
   */
  public function getDimensionDescription(string $outputType, string $dimension): string
  {
    if (!isset($this->metricDefinitions[$outputType][$dimension])) {
      return '';
    }
    
    return $this->metricDefinitions[$outputType][$dimension]['description'] ?? '';
  }
}
