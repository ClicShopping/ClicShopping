<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Validator;

/**
 * AnalyticsQualityEvaluator - Orchestrator for all analytics validators.
 *
 * This evaluator coordinates all validators and aggregates their scores:
 * - SqlQualityValidator: SQL quality evaluation
 * - SqlSecurityValidator: SQL security validation
 * - SqlPerformanceValidator: SQL performance evaluation
 * - SchemaValidator: Database schema validation
 *
 * Provides comprehensive evaluation across multiple dimensions:
 * - Accuracy: Correctness and validity
 * - Completeness: Coverage of requirements
 * - Efficiency: Performance and optimization
 * - Clarity: Readability and maintainability
 */
class AnalyticsQualityEvaluator
{
    private SqlQualityValidator $qualityValidator;
    private SqlSecurityValidator $securityValidator;
    private SqlPerformanceValidator $performanceValidator;
    private SchemaValidator $schemaValidator;
    private bool $debug;

    /**
     * Constructor with validator injection
     *
     * @param SqlQualityValidator $qualityValidator SQL quality validator
     * @param SqlSecurityValidator $securityValidator SQL security validator
     * @param SqlPerformanceValidator $performanceValidator SQL performance validator
     * @param SchemaValidator $schemaValidator Schema validator
     * @param bool $debug Enable debug mode
     */
    public function __construct(
        SqlQualityValidator $qualityValidator,
        SqlSecurityValidator $securityValidator,
        SqlPerformanceValidator $performanceValidator,
        SchemaValidator $schemaValidator,
        bool $debug = false
    ) {
        $this->qualityValidator = $qualityValidator;
        $this->securityValidator = $securityValidator;
        $this->performanceValidator = $performanceValidator;
        $this->schemaValidator = $schemaValidator;
        $this->debug = $debug;
    }

    /**
     * Evaluate SQL query across all dimensions.
     *
     * @param string $sql SQL query to evaluate
     * @param array $context Evaluation context
     *              [
     *                'tables' => array,
     *                'explanation' => string,
     *                'query_type' => string
     *              ]
     * @return array Comprehensive evaluation results
     *               [
     *                 'dimension_scores' => [
     *                   'accuracy' => float,
     *                   'completeness' => float,
     *                   'efficiency' => float,
     *                   'clarity' => float
     *                 ],
     *                 'overall_score' => float,
     *                 'validator_results' => [
     *                   'quality' => array,
     *                   'security' => array,
     *                   'performance' => array
     *                 ],
     *                 'feedback' => string,
     *                 'strengths' => array,
     *                 'improvements' => array
     *               ]
     */
    public function evaluateSqlQuery(string $sql, array $context = []): array
    {
        $tables = $context['tables'] ?? [];
        $explanation = $context['explanation'] ?? '';
        $queryType = $context['query_type'] ?? 'SELECT';

        // Run all validators
        $qualityResult = $this->qualityValidator->evaluateSqlQuality($sql);
        $securityResult = $this->securityValidator->validateSqlSecurity($sql);
        $performanceResult = $this->performanceValidator->evaluateSqlPerformance($sql, $tables);

        // Aggregate scores across dimensions
        $dimensionScores = $this->aggregateSqlQueryScores(
            $qualityResult,
            $securityResult,
            $performanceResult
        );

        // Calculate overall score
        $overallScore = $this->calculateOverallScore($dimensionScores);

        // Generate feedback
        $feedback = $this->generateFeedback($dimensionScores, 'sql_query');

        // Identify strengths and improvements
        $strengths = $this->identifyStrengths($dimensionScores);
        $improvements = $this->identifyImprovements($dimensionScores);

        return [
            'dimension_scores' => $dimensionScores,
            'overall_score' => $overallScore,
            'validator_results' => [
                'quality' => $qualityResult,
                'security' => $securityResult,
                'performance' => $performanceResult
            ],
            'feedback' => $feedback,
            'strengths' => $strengths,
            'improvements' => $improvements
        ];
    }

    /**
     * Evaluate query results across all dimensions.
     *
     * @param array $results Query results to evaluate
     * @param array $context Evaluation context
     *              [
     *                'row_count' => int,
     *                'execution_time' => float,
     *                'sql' => string
     *              ]
     * @return array Comprehensive evaluation results
     */
    public function evaluateQueryResults(array $results, array $context = []): array
    {
        $rowCount = $context['row_count'] ?? count($results);
        $executionTime = $context['execution_time'] ?? 0.0;
        $sql = $context['sql'] ?? '';

        // Evaluate results quality
        $accuracyScore = $this->evaluateResultsAccuracy($results, $rowCount);
        $completenessScore = $this->evaluateResultsCompleteness($results);
        $efficiencyScore = $this->evaluateResultsEfficiency($executionTime, $rowCount);
        $clarityScore = $this->evaluateResultsClarity($results);

        $dimensionScores = [
            'accuracy' => $accuracyScore,
            'completeness' => $completenessScore,
            'efficiency' => $efficiencyScore,
            'clarity' => $clarityScore
        ];

        $overallScore = $this->calculateOverallScore($dimensionScores);
        $feedback = $this->generateFeedback($dimensionScores, 'query_results');
        $strengths = $this->identifyStrengths($dimensionScores);
        $improvements = $this->identifyImprovements($dimensionScores);

        return [
            'dimension_scores' => $dimensionScores,
            'overall_score' => $overallScore,
            'validator_results' => [
                'results_analysis' => [
                    'row_count' => $rowCount,
                    'execution_time' => $executionTime,
                    'has_data' => !empty($results)
                ]
            ],
            'feedback' => $feedback,
            'strengths' => $strengths,
            'improvements' => $improvements
        ];
    }

    /**
     * Evaluate schema information across all dimensions.
     *
     * @param array $schema Schema information to evaluate
     *              [
     *                'tables' => array,
     *                'columns' => array,
     *                'relationships' => array
     *              ]
     * @param array $context Evaluation context
     * @return array Comprehensive evaluation results
     */
    public function evaluateSchemaInfo(array $schema, array $context = []): array
    {
        $tables = $schema['tables'] ?? [];
        $columns = $schema['columns'] ?? [];
        $relationships = $schema['relationships'] ?? [];

        // Validate schema using SchemaValidator
        $schemaResult = $this->schemaValidator->validateSchema($tables, $columns, $relationships);

        // Map schema validation to dimension scores
        $dimensionScores = [
            'accuracy' => $schemaResult['overall_score'],
            'completeness' => $schemaResult['checks']['has_tables'] && $schemaResult['checks']['has_columns'] ? 0.9 : 0.5,
            'efficiency' => 0.9, // Schema queries are typically efficient
            'clarity' => $schemaResult['checks']['has_relationships'] ? 0.9 : 0.7
        ];

        $overallScore = $this->calculateOverallScore($dimensionScores);
        $feedback = $this->generateFeedback($dimensionScores, 'schema_info');
        $strengths = $this->identifyStrengths($dimensionScores);
        $improvements = $this->identifyImprovements($dimensionScores);

        return [
            'dimension_scores' => $dimensionScores,
            'overall_score' => $overallScore,
            'validator_results' => [
                'schema' => $schemaResult
            ],
            'feedback' => $feedback,
            'strengths' => $strengths,
            'improvements' => $improvements
        ];
    }

    /**
     * Aggregate scores from SQL query validators.
     *
     * @param array $qualityResult Quality validation result
     * @param array $securityResult Security validation result
     * @param array $performanceResult Performance validation result
     * @return array Dimension scores
     */
    private function aggregateSqlQueryScores(
        array $qualityResult,
        array $securityResult,
        array $performanceResult
    ): array {
        // Extract dimension scores from quality result (uses 'scores' key, not 'dimension_scores')
        $qualityScores = $qualityResult['scores'] ?? [
            'accuracy' => 0.5,
            'completeness' => 0.5,
            'efficiency' => 0.5,
            'clarity' => 0.5
        ];

        // Accuracy: Quality (60%) + Security (40%)
        $accuracy = ($qualityScores['accuracy'] * 0.6) +
                   ($securityResult['overall_score'] * 0.4);

        // Completeness: Quality completeness (100%)
        $completeness = $qualityScores['completeness'];

        // Efficiency: Quality efficiency (40%) + Performance (60%)
        $efficiency = ($qualityScores['efficiency'] * 0.4) +
                     ($performanceResult['overall_score'] * 0.6);

        // Clarity: Quality clarity (100%)
        $clarity = $qualityScores['clarity'];

        return [
            'accuracy' => max(0.0, min(1.0, $accuracy)),
            'completeness' => max(0.0, min(1.0, $completeness)),
            'efficiency' => max(0.0, min(1.0, $efficiency)),
            'clarity' => max(0.0, min(1.0, $clarity))
        ];
    }

    /**
     * Calculate overall score from dimension scores.
     *
     * @param array $dimensionScores Dimension scores
     * @return float Overall score (0.0 to 1.0)
     */
    private function calculateOverallScore(array $dimensionScores): float
    {
        // Weighted average: accuracy (35%), completeness (25%), efficiency (25%), clarity (15%)
        $overallScore = ($dimensionScores['accuracy'] * 0.35) +
                       ($dimensionScores['completeness'] * 0.25) +
                       ($dimensionScores['efficiency'] * 0.25) +
                       ($dimensionScores['clarity'] * 0.15);

        return max(0.0, min(1.0, $overallScore));
    }

    /**
     * Generate structured feedback based on dimension scores.
     *
     * @param array $dimensionScores Dimension scores
     * @param string $outputType Output type (sql_query, query_results, schema_info)
     * @return string Feedback text
     */
    public function generateFeedback(array $dimensionScores, string $outputType = 'sql_query'): string
    {
        $feedback = [];

        $overallScore = $this->calculateOverallScore($dimensionScores);

        // Overall assessment
        if ($overallScore >= 0.8) {
            $feedback[] = "Excellent {$outputType} with high quality across all dimensions.";
        } elseif ($overallScore >= 0.6) {
            $feedback[] = "Good {$outputType} with room for improvement in some areas.";
        } else {
            $feedback[] = "The {$outputType} needs significant improvement.";
        }

        // Dimension-specific feedback
        if ($dimensionScores['accuracy'] < 0.6) {
            $feedback[] = "Accuracy concerns: Check syntax, logic, and data correctness.";
        }

        if ($dimensionScores['completeness'] < 0.6) {
            $feedback[] = "Completeness issues: Ensure all requirements are addressed.";
        }

        if ($dimensionScores['efficiency'] < 0.6) {
            $feedback[] = "Efficiency problems: Consider optimization and performance improvements.";
        }

        if ($dimensionScores['clarity'] < 0.6) {
            $feedback[] = "Clarity issues: Improve formatting, naming, and documentation.";
        }

        return implode(' ', $feedback);
    }

    /**
     * Identify strengths based on dimension scores.
     *
     * @param array $dimensionScores Dimension scores
     * @return array List of strengths
     */
    private function identifyStrengths(array $dimensionScores): array
    {
        $strengths = [];

        if ($dimensionScores['accuracy'] >= 0.8) {
            $strengths[] = "High accuracy and correctness";
        }

        if ($dimensionScores['completeness'] >= 0.8) {
            $strengths[] = "Complete and comprehensive solution";
        }

        if ($dimensionScores['efficiency'] >= 0.8) {
            $strengths[] = "Efficient and optimized approach";
        }

        if ($dimensionScores['clarity'] >= 0.8) {
            $strengths[] = "Clear and well-structured output";
        }

        return $strengths;
    }

    /**
     * Identify improvements based on dimension scores.
     *
     * @param array $dimensionScores Dimension scores
     * @return array List of improvements
     */
    private function identifyImprovements(array $dimensionScores): array
    {
        $improvements = [];

        if ($dimensionScores['accuracy'] < 0.7) {
            $improvements[] = "Improve accuracy by validating syntax and logic";
        }

        if ($dimensionScores['completeness'] < 0.7) {
            $improvements[] = "Ensure all requirements are fully addressed";
        }

        if ($dimensionScores['efficiency'] < 0.7) {
            $improvements[] = "Optimize for better performance and efficiency";
        }

        if ($dimensionScores['clarity'] < 0.7) {
            $improvements[] = "Enhance clarity through better formatting and documentation";
        }

        return $improvements;
    }

    /**
     * Evaluate results accuracy.
     *
     * @param array $results Query results
     * @param int $rowCount Expected row count
     * @return float Accuracy score (0.0 to 1.0)
     */
    private function evaluateResultsAccuracy(array $results, int $rowCount): float
    {
        $score = 0.5; // Base score

        // Check if row count matches results
        if (count($results) === $rowCount) {
            $score += 0.3;
        }

        // Check for data consistency
        if ($this->hasConsistentData($results)) {
            $score += 0.2;
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Evaluate results completeness.
     *
     * @param array $results Query results
     * @return float Completeness score (0.0 to 1.0)
     */
    private function evaluateResultsCompleteness(array $results): float
    {
        $score = 0.5; // Base score

        // Check if results are not empty
        if (!empty($results)) {
            $score += 0.3;
        }

        // Check if all expected columns are present
        if ($this->hasExpectedColumns($results)) {
            $score += 0.2;
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Evaluate results efficiency.
     *
     * @param float $executionTime Execution time in seconds
     * @param int $rowCount Row count
     * @return float Efficiency score (0.0 to 1.0)
     */
    private function evaluateResultsEfficiency(float $executionTime, int $rowCount): float
    {
        $score = 0.8; // Base score

        // Penalize slow queries
        if ($executionTime > 5.0) {
            $score -= 0.3;
        } elseif ($executionTime > 2.0) {
            $score -= 0.1;
        }

        // Consider row count vs execution time ratio
        if ($rowCount > 0 && $executionTime > 0) {
            $rowsPerSecond = $rowCount / $executionTime;
            if ($rowsPerSecond > 1000) {
                $score += 0.1;
            }
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Evaluate results clarity.
     *
     * @param array $results Query results
     * @return float Clarity score (0.0 to 1.0)
     */
    private function evaluateResultsClarity(array $results): float
    {
        $score = 0.5; // Base score

        // Check for consistent structure
        if ($this->hasConsistentStructure($results)) {
            $score += 0.3;
        }

        // Check for meaningful column names
        if ($this->hasMeaningfulColumnNames($results)) {
            $score += 0.2;
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Check if results have consistent data.
     *
     * @param array $results Query results
     * @return bool True if data is consistent
     */
    private function hasConsistentData(array $results): bool
    {
        if (empty($results)) {
            return true;
        }

        $firstRow = reset($results);
        if (!is_array($firstRow)) {
            return true;
        }

        $expectedKeys = array_keys($firstRow);
        foreach ($results as $row) {
            if (!is_array($row) || array_keys($row) !== $expectedKeys) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if results have expected columns.
     *
     * @param array $results Query results
     * @return bool True if expected columns present
     */
    private function hasExpectedColumns(array $results): bool
    {
        if (empty($results)) {
            return false;
        }

        $firstRow = reset($results);
        return is_array($firstRow) && !empty($firstRow);
    }

    /**
     * Check if results have consistent structure.
     *
     * @param array $results Query results
     * @return bool True if structure is consistent
     */
    private function hasConsistentStructure(array $results): bool
    {
        return $this->hasConsistentData($results);
    }

    /**
     * Check if results have meaningful column names.
     *
     * @param array $results Query results
     * @return bool True if column names are meaningful
     */
    private function hasMeaningfulColumnNames(array $results): bool
    {
        if (empty($results)) {
            return true;
        }

        $firstRow = reset($results);
        if (!is_array($firstRow)) {
            return true;
        }

        foreach (array_keys($firstRow) as $columnName) {
            if (strlen($columnName) > 2) {
                return true; // At least one meaningful name
            }
        }

        return false;
    }

    /**
     * Get quality validator instance.
     *
     * @return SqlQualityValidator
     */
    public function getQualityValidator(): SqlQualityValidator
    {
        return $this->qualityValidator;
    }

    /**
     * Get security validator instance.
     *
     * @return SqlSecurityValidator
     */
    public function getSecurityValidator(): SqlSecurityValidator
    {
        return $this->securityValidator;
    }

    /**
     * Get performance validator instance.
     *
     * @return SqlPerformanceValidator
     */
    public function getPerformanceValidator(): SqlPerformanceValidator
    {
        return $this->performanceValidator;
    }

    /**
     * Get schema validator instance.
     *
     * @return SchemaValidator
     */
    public function getSchemaValidator(): SchemaValidator
    {
        return $this->schemaValidator;
    }
}
