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

use ClicShopping\AI\DomainsAI\Analytics\Agent\DatabaseSchemaManager;

/**
 * SqlPerformanceValidator - Pure business logic for SQL performance evaluation.
 *
 * This validator evaluates SQL query performance based on:
 * - Query optimization (SELECT *, LIMIT usage)
 * - Index utilization
 * - JOIN complexity
 * - Potential slow query patterns
 * - Performance scoring and recommendations
 *
 * Extracted from AnalyticsCritic to separate business logic from Actor-Critic infrastructure.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Validator
 * @version 1.0.0
 * @since 2026-05-03
 */
class SqlPerformanceValidator
{
    private ?DatabaseSchemaManager $schemaManager;
    private bool $debug;

    /**
     * Slow query patterns that may impact performance
     */
    private const SLOW_PATTERNS = [
        'SELECT *',
        'ORDER BY',
        'GROUP BY',
        'HAVING',
        'DISTINCT'
    ];

    /**
     * Constructor
     *
     * @param DatabaseSchemaManager|null $schemaManager Schema manager for index checking
     * @param bool $debug Enable debug mode
     */
    public function __construct(
        ?DatabaseSchemaManager $schemaManager = null,
        bool $debug = false
    ) {
        $this->schemaManager = $schemaManager;
        $this->debug = $debug;
    }

    /**
     * Evaluate SQL performance across multiple dimensions.
     *
     * @param string $sql The SQL query to evaluate
     * @param array $tables Tables used in the query
     * @return array Performance evaluation results
     *               [
     *                 'overall_score' => float,
     *                 'checks' => [
     *                   'uses_wildcard' => bool,
     *                   'has_limit' => bool,
     *                   'has_proper_indexing' => bool,
     *                   'has_unnecessary_joins' => bool,
     *                   'is_potentially_slow' => bool,
     *                   'join_count' => int,
     *                   'slow_pattern_count' => int
     *                 ],
     *                 'issues' => array,
     *                 'recommendations' => array,
     *                 'estimated_performance' => string
     *               ]
     */
    public function evaluateSqlPerformance(string $sql, array $tables = []): array
    {
        $sqlUpper = strtoupper($sql);

        // Perform individual performance checks
        $checks = [
            'uses_wildcard' => $this->usesWildcard($sqlUpper),
            'has_limit' => $this->hasLimitClause($sqlUpper),
            'has_proper_indexing' => $this->hasProperIndexing($sql, $tables),
            'has_unnecessary_joins' => $this->hasUnnecessaryJoins($sqlUpper),
            'is_potentially_slow' => $this->isPotentiallySlowQuery($sqlUpper),
            'join_count' => $this->countJoins($sqlUpper),
            'slow_pattern_count' => $this->countSlowPatterns($sqlUpper)
        ];

        // Calculate performance score
        $performanceScore = $this->calculatePerformanceScore($checks);

        // Identify performance issues
        $issues = $this->identifyPerformanceIssues($checks, $sql);

        // Generate recommendations
        $recommendations = $this->generatePerformanceRecommendations($checks, $issues);

        // Estimate performance level
        $estimatedPerformance = $this->estimatePerformanceLevel($performanceScore, $checks);

        return [
            'overall_score' => $performanceScore,
            'checks' => $checks,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'estimated_performance' => $estimatedPerformance
        ];
    }

    /**
     * Check if query uses SELECT * wildcard.
     *
     * @param string $sql The SQL query (should be uppercase)
     * @return bool True if SELECT * is used
     */
    private function usesWildcard(string $sql): bool
    {
        return str_contains($sql, 'SELECT *');
    }

    /**
     * Check if query has a LIMIT clause.
     *
     * @param string $sql The SQL query (should be uppercase)
     * @return bool True if LIMIT clause is present
     */
    private function hasLimitClause(string $sql): bool
    {
        return str_contains($sql, 'LIMIT');
    }

    /**
     * Check if query has proper indexing hints.
     *
     * @param string $sql The SQL query
     * @param array $tables Tables used in query
     * @return bool True if proper indexing is likely
     */
    public function hasProperIndexing(string $sql, array $tables): bool
    {
        // If no schema manager, assume indexing is proper if WHERE clause exists
        if ($this->schemaManager === null) {
            return str_contains(strtoupper($sql), 'WHERE');
        }

        // Check if WHERE clauses reference indexed columns
        // This is a simplified check - real implementation would query schema
        return str_contains(strtoupper($sql), 'WHERE');
    }

    /**
     * Check if query has unnecessary JOINs.
     *
     * @param string $sql The SQL query (should be uppercase)
     * @return bool True if excessive JOINs detected (> 5)
     */
    public function hasUnnecessaryJoins(string $sql): bool
    {
        return $this->countJoins($sql) > 5;
    }

    /**
     * Count the number of JOINs in the query.
     *
     * @param string $sql The SQL query (should be uppercase)
     * @return int Number of JOINs
     */
    private function countJoins(string $sql): int
    {
        return substr_count($sql, 'JOIN');
    }

    /**
     * Check if query is potentially slow.
     *
     * @param string $sql The SQL query (should be uppercase)
     * @return bool True if potentially slow patterns detected
     */
    public function isPotentiallySlowQuery(string $sql): bool
    {
        // Check for slow patterns
        foreach (self::SLOW_PATTERNS as $pattern) {
            if (str_contains($sql, $pattern)) {
                return true;
            }
        }

        // Check for multiple JOINs (> 2)
        if ($this->countJoins($sql) > 2) {
            return true;
        }

        return false;
    }

    /**
     * Count slow patterns in the query.
     *
     * @param string $sql The SQL query (should be uppercase)
     * @return int Number of slow patterns detected
     */
    private function countSlowPatterns(string $sql): int
    {
        $count = 0;
        foreach (self::SLOW_PATTERNS as $pattern) {
            if (str_contains($sql, $pattern)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Calculate overall performance score.
     *
     * @param array $checks Performance check results
     * @return float Performance score (0.0 to 1.0)
     */
    public function calculatePerformanceScore(array $checks): float
    {
        $score = 0.7; // Base score

        // Penalty for SELECT * (inefficient)
        if ($checks['uses_wildcard']) {
            $score -= 0.2;
        }

        // Bonus for LIMIT clause
        if ($checks['has_limit']) {
            $score += 0.1;
        }

        // Bonus for proper indexing
        if ($checks['has_proper_indexing']) {
            $score += 0.2;
        }

        // Penalty for unnecessary JOINs
        if ($checks['has_unnecessary_joins']) {
            $score -= 0.1;
        }

        // Penalty for potentially slow query
        if ($checks['is_potentially_slow']) {
            $score -= 0.15;
        }

        // Additional penalty based on JOIN count
        if ($checks['join_count'] > 3) {
            $score -= 0.05 * ($checks['join_count'] - 3);
        }

        // Additional penalty based on slow pattern count
        if ($checks['slow_pattern_count'] > 2) {
            $score -= 0.05 * ($checks['slow_pattern_count'] - 2);
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Identify performance issues.
     *
     * @param array $checks Performance check results
     * @param string $sql The SQL query
     * @return array List of identified issues
     */
    private function identifyPerformanceIssues(array $checks, string $sql): array
    {
        $issues = [];

        if ($checks['uses_wildcard']) {
            $issues[] = [
                'type' => 'wildcard_select',
                'severity' => 'medium',
                'description' => 'SELECT * fetches all columns, which may be inefficient',
                'impact' => 'Increased network traffic and memory usage'
            ];
        }

        if (!$checks['has_limit']) {
            $issues[] = [
                'type' => 'missing_limit',
                'severity' => 'medium',
                'description' => 'Query lacks LIMIT clause, may return excessive rows',
                'impact' => 'Potential memory exhaustion and slow response times'
            ];
        }

        if (!$checks['has_proper_indexing']) {
            $issues[] = [
                'type' => 'missing_indexes',
                'severity' => 'high',
                'description' => 'Query may not utilize proper indexes',
                'impact' => 'Full table scans, significantly slower execution'
            ];
        }

        if ($checks['has_unnecessary_joins']) {
            $issues[] = [
                'type' => 'excessive_joins',
                'severity' => 'high',
                'description' => "Query has {$checks['join_count']} JOINs, which may be excessive",
                'impact' => 'Exponential increase in execution time and resource usage'
            ];
        }

        if ($checks['is_potentially_slow']) {
            $issues[] = [
                'type' => 'slow_patterns',
                'severity' => 'medium',
                'description' => "Query contains {$checks['slow_pattern_count']} potentially slow patterns",
                'impact' => 'Increased execution time, especially on large datasets'
            ];
        }

        return $issues;
    }

    /**
     * Generate performance recommendations.
     *
     * @param array $checks Performance check results
     * @param array $issues Identified issues
     * @return array List of recommendations
     */
    private function generatePerformanceRecommendations(array $checks, array $issues): array
    {
        $recommendations = [];

        if ($checks['uses_wildcard']) {
            $recommendations[] = 'Specify only the columns you need instead of using SELECT *';
            $recommendations[] = 'This reduces network traffic and improves query performance';
        }

        if (!$checks['has_limit']) {
            $recommendations[] = 'Add a LIMIT clause to prevent fetching excessive rows';
            $recommendations[] = 'Consider pagination for large result sets';
        }

        if (!$checks['has_proper_indexing']) {
            $recommendations[] = 'Ensure WHERE clause columns are properly indexed';
            $recommendations[] = 'Review database indexes for the tables involved';
        }

        if ($checks['has_unnecessary_joins']) {
            $recommendations[] = 'Review if all JOINs are necessary';
            $recommendations[] = 'Consider breaking complex queries into smaller ones';
            $recommendations[] = 'Use subqueries or temporary tables if appropriate';
        }

        if ($checks['is_potentially_slow']) {
            $recommendations[] = 'Review use of ORDER BY, GROUP BY, and DISTINCT';
            $recommendations[] = 'Consider adding indexes on sorted/grouped columns';
            $recommendations[] = 'Test query performance on production-sized datasets';
        }

        if ($checks['join_count'] > 3) {
            $recommendations[] = 'High JOIN count detected - consider query optimization';
            $recommendations[] = 'Evaluate if denormalization would improve performance';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Query appears well-optimized for performance';
            $recommendations[] = 'Continue monitoring execution times in production';
        }

        return $recommendations;
    }

    /**
     * Estimate performance level based on score and checks.
     *
     * @param float $score Performance score
     * @param array $checks Performance check results
     * @return string Performance level ('excellent', 'good', 'acceptable', 'poor', 'critical')
     */
    private function estimatePerformanceLevel(float $score, array $checks): string
    {
        // Critical performance issues
        if ($checks['has_unnecessary_joins'] && $checks['join_count'] > 7) {
            return 'critical';
        }

        // Score-based classification
        if ($score >= 0.85) {
            return 'excellent';
        } elseif ($score >= 0.7) {
            return 'good';
        } elseif ($score >= 0.5) {
            return 'acceptable';
        } elseif ($score >= 0.3) {
            return 'poor';
        } else {
            return 'critical';
        }
    }

    /**
     * Generate human-readable performance report.
     *
     * @param array $evaluationResult Result from evaluateSqlPerformance()
     * @return string Human-readable performance report
     */
    public function generatePerformanceReport(array $evaluationResult): string
    {
        $score = $evaluationResult['overall_score'];
        $performance = $evaluationResult['estimated_performance'];

        $report = "SQL Performance Evaluation: " . strtoupper($performance) . "\n";
        $report .= "Performance Score: " . number_format($score, 2) . "\n";

        $checks = $evaluationResult['checks'];
        $report .= "\nPerformance Checks:\n";
        $report .= "  - SELECT * usage: " . ($checks['uses_wildcard'] ? '❌ Yes (inefficient)' : '✓ No') . "\n";
        $report .= "  - LIMIT clause: " . ($checks['has_limit'] ? '✓ Yes' : '❌ Missing') . "\n";
        $report .= "  - Proper indexing: " . ($checks['has_proper_indexing'] ? '✓ Likely' : '⚠️  Uncertain') . "\n";
        $report .= "  - JOIN count: " . $checks['join_count'] . ($checks['has_unnecessary_joins'] ? ' ❌ (excessive)' : ' ✓') . "\n";
        $report .= "  - Slow patterns: " . $checks['slow_pattern_count'] . "\n";

        if (!empty($evaluationResult['issues'])) {
            $report .= "\n⚠️  PERFORMANCE ISSUES:\n";
            foreach ($evaluationResult['issues'] as $issue) {
                $report .= "  [{$issue['severity']}] {$issue['description']}\n";
                $report .= "    Impact: {$issue['impact']}\n";
            }
        }

        if (!empty($evaluationResult['recommendations'])) {
            $report .= "\n📋 RECOMMENDATIONS:\n";
            foreach ($evaluationResult['recommendations'] as $recommendation) {
                $report .= "  - {$recommendation}\n";
            }
        }

        return $report;
    }

    /**
     * Get schema manager instance.
     *
     * @return DatabaseSchemaManager|null
     */
    public function getSchemaManager(): ?DatabaseSchemaManager
    {
        return $this->schemaManager;
    }

    /**
     * Set schema manager instance.
     *
     * @param DatabaseSchemaManager $schemaManager
     * @return void
     */
    public function setSchemaManager(DatabaseSchemaManager $schemaManager): void
    {
        $this->schemaManager = $schemaManager;
    }
}
