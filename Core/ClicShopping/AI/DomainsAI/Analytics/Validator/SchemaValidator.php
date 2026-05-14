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
 * SchemaValidator - Pure business logic for database schema validation.
 *
 * This validator evaluates database schema information by:
 * - Validating table existence in the database
 * - Validating column existence for specified tables
 * - Evaluating schema information completeness
 * - Providing schema validation scoring and recommendations
 *
 * Extracted from AnalyticsCritic to separate business logic from Actor-Critic infrastructure.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Validator
 * @version 1.0.0
 * @since 2026-05-03
 */
class SchemaValidator
{
    private DatabaseSchemaManager $schemaManager;
    private bool $debug;

    /**
     * Constructor
     *
     * @param DatabaseSchemaManager $schemaManager Schema manager for database access
     * @param bool $debug Enable debug mode
     */
    public function __construct(
        DatabaseSchemaManager $schemaManager,
        bool $debug = false
    ) {
        $this->schemaManager = $schemaManager;
        $this->debug = $debug;
    }

    /**
     * Validate database schema information.
     *
     * @param array $tables List of table names to validate
     * @param array $columns List of columns to validate (format: ['table' => 'column'])
     * @param array $relationships List of relationships to validate
     * @return array Validation results
     *               [
     *                 'overall_score' => float,
     *                 'checks' => [
     *                   'tables_exist' => bool,
     *                   'columns_exist' => bool,
     *                   'has_tables' => bool,
     *                   'has_columns' => bool,
     *                   'has_relationships' => bool,
     *                   'table_count' => int,
     *                   'column_count' => int
     *                 ],
     *                 'issues' => array,
     *                 'recommendations' => array,
     *                 'validation_status' => string
     *               ]
     */
    public function validateSchema(array $tables, array $columns = [], array $relationships = []): array
    {
        // Perform individual schema checks
        $checks = [
            'tables_exist' => $this->validateTablesExist($tables),
            'columns_exist' => $this->validateColumnsExist($columns),
            'has_tables' => !empty($tables),
            'has_columns' => !empty($columns),
            'has_relationships' => !empty($relationships),
            'table_count' => count($tables),
            'column_count' => count($columns)
        ];

        // Calculate schema score
        $schemaScore = $this->calculateSchemaScore($checks);

        // Identify schema issues
        $issues = $this->identifySchemaIssues($checks, $tables, $columns);

        // Generate recommendations
        $recommendations = $this->generateSchemaRecommendations($checks, $issues);

        // Determine validation status
        $validationStatus = $this->determineValidationStatus($schemaScore, $checks);

        return [
            'overall_score' => $schemaScore,
            'checks' => $checks,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'validation_status' => $validationStatus
        ];
    }

    /**
     * Validate that specified tables exist in the database.
     *
     * @param array $tables List of table names
     * @return bool True if all tables exist
     */
    public function validateTablesExist(array $tables): bool
    {
        if (empty($tables)) {
            return true; // No tables to validate
        }

        try {
            $schema = $this->schemaManager->getDatabaseSchema();
            
            foreach ($tables as $table) {
                if (!isset($schema[$table])) {
                    if ($this->debug) {
                        error_log("SchemaValidator: Table '{$table}' not found in schema");
                    }
                    return false;
                }
            }
            
            return true;
        } catch (\Exception $e) {
            if ($this->debug) {
                error_log("SchemaValidator: Error validating tables - " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Validate that specified columns exist in their respective tables.
     *
     * @param array $columns List of columns (format: ['table' => 'column'] or ['table.column'])
     * @return bool True if all columns exist
     */
    public function validateColumnsExist(array $columns): bool
    {
        if (empty($columns)) {
            return true; // No columns to validate
        }

        try {
            $schema = $this->schemaManager->getDatabaseSchema();

            foreach ($columns as $key => $value) {
                // Handle both formats: ['table' => 'column'] and ['table.column']
                if (is_string($key)) {
                    // Format: ['table' => 'column']
                    $table = $key;
                    $column = $value;
                } else {
                    // Format: ['table.column']
                    if (str_contains($value, '.')) {
                        list($table, $column) = explode('.', $value, 2);
                    } else {
                        // Cannot validate without table context
                        if ($this->debug) {
                            error_log("SchemaValidator: Column '{$value}' has no table context");
                        }
                        continue;
                    }
                }

                // Check if table exists
                if (!isset($schema[$table])) {
                    if ($this->debug) {
                        error_log("SchemaValidator: Table '{$table}' not found for column '{$column}'");
                    }
                    return false;
                }

                // Check if column exists in table
                if (!isset($schema[$table]['columns'][$column])) {
                    if ($this->debug) {
                        error_log("SchemaValidator: Column '{$column}' not found in table '{$table}'");
                    }
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            if ($this->debug) {
                error_log("SchemaValidator: Error validating columns - " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Calculate overall schema validation score.
     *
     * @param array $checks Schema check results
     * @return float Schema score (0.0 to 1.0)
     */
    public function calculateSchemaScore(array $checks): float
    {
        $score = 0.5; // Base score

        // Penalty for empty schema information (must check first)
        if (!$checks['has_tables'] && !$checks['has_columns']) {
            $score -= 0.3;
        }

        // Bonus for tables existing
        if ($checks['tables_exist']) {
            $score += 0.3;
        } else if ($checks['has_tables']) {
            // Penalty for non-existent tables (only if tables were provided)
            $score -= 0.3;
        }

        // Bonus for columns existing
        if ($checks['columns_exist']) {
            $score += 0.2;
        }

        // Bonus for having tables
        if ($checks['has_tables']) {
            $score += 0.1;
        }

        // Bonus for having columns
        if ($checks['has_columns']) {
            $score += 0.1;
        }

        // Bonus for having relationships
        if ($checks['has_relationships']) {
            $score += 0.1;
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Identify schema validation issues.
     *
     * @param array $checks Schema check results
     * @param array $tables Tables list
     * @param array $columns Columns list
     * @return array List of identified issues
     */
    private function identifySchemaIssues(array $checks, array $tables, array $columns): array
    {
        $issues = [];

        if (!$checks['tables_exist']) {
            $issues[] = [
                'type' => 'invalid_tables',
                'severity' => 'high',
                'description' => 'One or more specified tables do not exist in the database',
                'impact' => 'Queries using these tables will fail'
            ];
        }

        if (!$checks['columns_exist']) {
            $issues[] = [
                'type' => 'invalid_columns',
                'severity' => 'high',
                'description' => 'One or more specified columns do not exist in their tables',
                'impact' => 'Queries using these columns will fail'
            ];
        }

        if (!$checks['has_tables']) {
            $issues[] = [
                'type' => 'missing_tables',
                'severity' => 'medium',
                'description' => 'No tables specified in schema information',
                'impact' => 'Cannot validate schema completeness'
            ];
        }

        if (!$checks['has_columns'] && $checks['has_tables']) {
            $issues[] = [
                'type' => 'missing_columns',
                'severity' => 'low',
                'description' => 'No columns specified for the tables',
                'impact' => 'Schema information is incomplete'
            ];
        }

        if (!$checks['has_relationships'] && $checks['table_count'] > 1) {
            $issues[] = [
                'type' => 'missing_relationships',
                'severity' => 'low',
                'description' => 'Multiple tables but no relationships specified',
                'impact' => 'May miss important table connections'
            ];
        }

        return $issues;
    }

    /**
     * Generate schema validation recommendations.
     *
     * @param array $checks Schema check results
     * @param array $issues Identified issues
     * @return array List of recommendations
     */
    private function generateSchemaRecommendations(array $checks, array $issues): array
    {
        $recommendations = [];

        if (!$checks['tables_exist']) {
            $recommendations[] = 'Verify table names against the database schema';
            $recommendations[] = 'Check for typos in table names';
            $recommendations[] = 'Ensure tables are in the correct database';
        }

        if (!$checks['columns_exist']) {
            $recommendations[] = 'Verify column names against the table schema';
            $recommendations[] = 'Check for typos in column names';
            $recommendations[] = 'Ensure columns exist in the specified tables';
        }

        if (!$checks['has_tables']) {
            $recommendations[] = 'Specify which tables are involved in the query';
            $recommendations[] = 'Provide complete schema information';
        }

        if (!$checks['has_columns'] && $checks['has_tables']) {
            $recommendations[] = 'Include column information for better validation';
            $recommendations[] = 'Specify which columns are being accessed';
        }

        if (!$checks['has_relationships'] && $checks['table_count'] > 1) {
            $recommendations[] = 'Document relationships between tables';
            $recommendations[] = 'Specify foreign key constraints';
        }

        if (empty($issues)) {
            $recommendations[] = 'Schema validation passed successfully';
            $recommendations[] = 'All tables and columns exist in the database';
        }

        return $recommendations;
    }

    /**
     * Determine validation status based on score and checks.
     *
     * @param float $score Schema validation score
     * @param array $checks Schema check results
     * @return string Validation status ('valid', 'warning', 'invalid', 'incomplete')
     */
    private function determineValidationStatus(float $score, array $checks): string
    {
        // Critical failure: tables or columns don't exist
        if (!$checks['tables_exist'] || !$checks['columns_exist']) {
            return 'invalid';
        }

        // Incomplete: missing essential information
        if (!$checks['has_tables']) {
            return 'incomplete';
        }

        // Warning: score is acceptable but not perfect
        if ($score >= 0.7 && $score < 0.9) {
            return 'warning';
        }

        // Valid: high score and all checks pass
        if ($score >= 0.9) {
            return 'valid';
        }

        // Default to warning for medium scores
        return 'warning';
    }

    /**
     * Generate human-readable schema validation report.
     *
     * @param array $validationResult Result from validateSchema()
     * @return string Human-readable validation report
     */
    public function generateSchemaReport(array $validationResult): string
    {
        $score = $validationResult['overall_score'];
        $status = $validationResult['validation_status'];

        $report = "Schema Validation: " . strtoupper($status) . "\n";
        $report .= "Validation Score: " . number_format($score, 2) . "\n";

        $checks = $validationResult['checks'];
        $report .= "\nSchema Checks:\n";
        $report .= "  - Tables exist: " . ($checks['tables_exist'] ? '✓ Yes' : '❌ No') . "\n";
        $report .= "  - Columns exist: " . ($checks['columns_exist'] ? '✓ Yes' : '❌ No') . "\n";
        $report .= "  - Tables provided: " . ($checks['has_tables'] ? "✓ Yes ({$checks['table_count']})" : '❌ No') . "\n";
        $report .= "  - Columns provided: " . ($checks['has_columns'] ? "✓ Yes ({$checks['column_count']})" : '⚠️  No') . "\n";
        $report .= "  - Relationships: " . ($checks['has_relationships'] ? '✓ Yes' : '⚠️  No') . "\n";

        if (!empty($validationResult['issues'])) {
            $report .= "\n⚠️  SCHEMA ISSUES:\n";
            foreach ($validationResult['issues'] as $issue) {
                $report .= "  [{$issue['severity']}] {$issue['description']}\n";
                $report .= "    Impact: {$issue['impact']}\n";
            }
        }

        if (!empty($validationResult['recommendations'])) {
            $report .= "\n📋 RECOMMENDATIONS:\n";
            foreach ($validationResult['recommendations'] as $recommendation) {
                $report .= "  - {$recommendation}\n";
            }
        }

        return $report;
    }

    /**
     * Get schema manager instance.
     *
     * @return DatabaseSchemaManager
     */
    public function getSchemaManager(): DatabaseSchemaManager
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
