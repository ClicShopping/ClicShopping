<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\DomainsAI\Analytics\Validator;

/**
 * SqlQualityValidator - Pure business logic for SQL query quality evaluation.
 *
 * This validator evaluates SQL query quality based on:
 * - Syntax correctness (SELECT, FROM presence)
 * - Best practices (avoiding SELECT *, using WHERE clauses)
 * - Query structure (LIMIT clauses, proper filtering)
 * - Code clarity and readability
 *
 * Extracted from SqlQualityCritic to separate business logic from Actor-Critic infrastructure.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Validator
 * @version 1.0.0
 * @since 2026-05-03
 */
class SqlQualityValidator
{
    /**
     * Evaluate SQL query quality across multiple dimensions.
     *
     * @param string $sql The SQL query to evaluate
     * @return array Evaluation results with scores and details
     *               [
     *                 'overall_score' => float,
     *                 'scores' => [
     *                   'accuracy' => float,
     *                   'completeness' => float,
     *                   'efficiency' => float,
     *                   'clarity' => float
     *                 ],
     *                 'checks' => [
     *                   'has_select' => bool,
     *                   'has_from' => bool,
     *                   'has_where' => bool,
     *                   'uses_wildcard' => bool,
     *                   'has_limit' => bool
     *                 ],
     *                 'issues' => string[],
     *                 'recommendations' => string[]
     *               ]
     */
    public function evaluateSqlQuality(string $sql): array
    {
        $sqlUpper = strtoupper($sql);

        // Perform individual checks
        $checks = [
            'has_select' => $this->hasSelectClause($sqlUpper),
            'has_from' => $this->hasFromClause($sqlUpper),
            'has_where' => $this->hasWhereClause($sqlUpper),
            'uses_wildcard' => $this->checkSelectWildcard($sqlUpper),
            'has_limit' => $this->checkLimitClause($sqlUpper)
        ];

        // Calculate dimension scores
        $scores = $this->calculateDimensionScores($checks);

        // Calculate overall score
        $overallScore = $this->calculateQualityScore($scores);

        // Identify issues and recommendations
        $issues = $this->identifyIssues($checks, $scores);
        $recommendations = $this->generateRecommendations($checks, $scores);

        return [
            'overall_score' => $overallScore,
            'scores' => $scores,
            'checks' => $checks,
            'issues' => $issues,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Check if SQL query uses SELECT * (wildcard).
     *
     * @param string $sql The SQL query (should be uppercase)
     * @return bool True if SELECT * is used
     */
    public function checkSelectWildcard(string $sql): bool
    {
        return str_contains($sql, 'SELECT *');
    }

    /**
     * Check if SQL query has a LIMIT clause.
     *
     * @param string $sql The SQL query (should be uppercase)
     * @return bool True if LIMIT clause is present
     */
    public function checkLimitClause(string $sql): bool
    {
        return str_contains($sql, 'LIMIT');
    }

    /**
     * Check if SQL query has a WHERE clause.
     *
     * @param string $sql The SQL query (should be uppercase)
     * @return bool True if WHERE clause is present
     */
    public function checkWhereClause(string $sql): bool
    {
        return str_contains($sql, 'WHERE');
    }

    /**
     * Check if SQL query has a SELECT clause.
     *
     * @param string $sql The SQL query (should be uppercase)
     * @return bool True if SELECT clause is present
     */
    private function hasSelectClause(string $sql): bool
    {
        return str_contains($sql, 'SELECT');
    }

    /**
     * Check if SQL query has a FROM clause.
     *
     * @param string $sql The SQL query (should be uppercase)
     * @return bool True if FROM clause is present
     */
    private function hasFromClause(string $sql): bool
    {
        return str_contains($sql, 'FROM');
    }

    /**
     * Check if SQL query has a WHERE clause (private version for internal use).
     *
     * @param string $sql The SQL query (should be uppercase)
     * @return bool True if WHERE clause is present
     */
    private function hasWhereClause(string $sql): bool
    {
        return str_contains($sql, 'WHERE');
    }

    /**
     * Calculate dimension scores based on checks.
     *
     * Dimensions:
     * - accuracy: Correctness of SQL syntax and structure
     * - completeness: Presence of necessary clauses
     * - efficiency: Query optimization (avoiding wildcards, using LIMIT)
     * - clarity: Query readability and structure
     *
     * @param array $checks Array of boolean check results
     * @return array Dimension scores (0.0 to 1.0)
     */
    private function calculateDimensionScores(array $checks): array
    {
        // Accuracy: Basic SQL structure correctness
        $accuracy = ($checks['has_select'] && $checks['has_from']) ? 0.7 : 0.3;
        $accuracy += $checks['has_where'] ? 0.1 : 0.0;
        $accuracy = $checks['uses_wildcard'] ? $accuracy - 0.1 : $accuracy;

        // Completeness: Presence of important clauses
        $completeness = ($checks['has_select'] && $checks['has_from']) ? 0.65 : 0.35;
        $completeness += $checks['has_where'] ? 0.1 : 0.0;

        // Efficiency: Query optimization
        $efficiency = $checks['uses_wildcard'] ? 0.45 : 0.65;
        $efficiency += $checks['has_limit'] ? 0.1 : 0.0;

        // Clarity: Query structure and readability
        $clarity = $checks['has_select'] ? 0.6 : 0.4;

        return [
            'accuracy' => $this->clamp($accuracy),
            'completeness' => $this->clamp($completeness),
            'efficiency' => $this->clamp($efficiency),
            'clarity' => $this->clamp($clarity)
        ];
    }

    /**
     * Calculate overall quality score from dimension scores.
     *
     * @param array $scores Dimension scores
     * @return float Overall quality score (0.0 to 1.0)
     */
    public function calculateQualityScore(array $scores): float
    {
        if (empty($scores)) {
            return 0.0;
        }

        return array_sum($scores) / count($scores);
    }

    /**
     * Identify issues based on checks and scores.
     *
     * @param array $checks Boolean check results
     * @param array $scores Dimension scores
     * @return array List of identified issues
     */
    private function identifyIssues(array $checks, array $scores): array
    {
        $issues = [];

        if (!$checks['has_select']) {
            $issues[] = 'Missing SELECT clause';
        }

        if (!$checks['has_from']) {
            $issues[] = 'Missing FROM clause';
        }

        if ($checks['uses_wildcard']) {
            $issues[] = 'SELECT * detected (anti-pattern)';
        }

        if (!$checks['has_limit']) {
            $issues[] = 'Missing LIMIT clause (potential performance issue)';
        }

        if (!$checks['has_where']) {
            $issues[] = 'Missing WHERE clause (may return too many rows)';
        }

        // Add dimension-specific issues
        foreach ($scores as $dimension => $score) {
            if ($score < 0.5) {
                $issues[] = ucfirst($dimension) . ' score is low (' . number_format($score, 2) . ')';
            }
        }

        return $issues;
    }

    /**
     * Generate recommendations based on checks and scores.
     *
     * @param array $checks Boolean check results
     * @param array $scores Dimension scores
     * @return array List of recommendations
     */
    private function generateRecommendations(array $checks, array $scores): array
    {
        $recommendations = [];

        if ($checks['uses_wildcard']) {
            $recommendations[] = 'Specify columns explicitly instead of using SELECT *';
        }

        if (!$checks['has_limit']) {
            $recommendations[] = 'Add LIMIT clause to prevent excessive result sets';
        }

        if (!$checks['has_where']) {
            $recommendations[] = 'Consider adding WHERE clause for better filtering';
        }

        // Add dimension-specific recommendations
        foreach ($scores as $dimension => $score) {
            if ($score < 0.6) {
                $recommendations[] = 'Improve ' . $dimension . ' (current: ' . number_format($score, 2) . ')';
            }
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Query quality is good, consider reviewing for specific use case optimization';
        }

        return $recommendations;
    }

    /**
     * Clamp a value between 0.0 and 1.0.
     *
     * @param float $value The value to clamp
     * @return float Clamped value
     */
    private function clamp(float $value): float
    {
        if ($value < 0.0) {
            return 0.0;
        }
        if ($value > 1.0) {
            return 1.0;
        }
        return $value;
    }

    /**
     * Get quality level description based on score.
     *
     * @param float $score Overall quality score
     * @return string Quality level ('excellent', 'good', 'acceptable', 'poor')
     */
    public function getQualityLevel(float $score): string
    {
        if ($score >= 0.85) {
            return 'excellent';
        } elseif ($score >= 0.75) {
            return 'good';
        } elseif ($score >= 0.6) {
            return 'acceptable';
        } else {
            return 'poor';
        }
    }

    /**
     * Generate human-readable feedback from evaluation results.
     *
     * @param array $evaluationResult Result from evaluateSqlQuality()
     * @return string Human-readable feedback
     */
    public function generateFeedback(array $evaluationResult): string
    {
        $score = $evaluationResult['overall_score'];
        $quality = $this->getQualityLevel($score);

        $feedback = "SQL Quality Evaluation: {$quality} (score: " . number_format($score, 2) . ")\n";

        if (!empty($evaluationResult['issues'])) {
            $feedback .= "\nIssues:\n";
            foreach ($evaluationResult['issues'] as $issue) {
                $feedback .= "  - {$issue}\n";
            }
        }

        if (!empty($evaluationResult['recommendations'])) {
            $feedback .= "\nRecommendations:\n";
            foreach ($evaluationResult['recommendations'] as $recommendation) {
                $feedback .= "  - {$recommendation}\n";
            }
        }

        return $feedback;
    }
}
