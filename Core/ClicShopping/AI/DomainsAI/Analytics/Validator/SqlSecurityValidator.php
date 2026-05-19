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

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Security\DbSecurity;

/**
 * SqlSecurityValidator - Pure business logic for SQL security validation.
 *
 * This validator evaluates SQL query security based on:
 * - Dangerous pattern detection (DROP, TRUNCATE, ALTER, etc.)
 * - SQL injection risk assessment
 * - Query safety validation
 * - Security scoring and threat classification
 *
 * Extracted from AnalyticsCritic to separate business logic from Actor-Critic infrastructure.
 *
 * @package ClicShopping\AI\DomainsAI\Analytics\Validator
 * @version 1.0.0
 * @since 2026-05-03
 */
class SqlSecurityValidator
{
    private SecurityLogger $securityLogger;
    private ?DbSecurity $dbSecurity;
    private bool $debug;

    /**
     * Dangerous SQL patterns that should never appear in analytics queries
     */
    private const DANGEROUS_PATTERNS = [
        '/DROP\s+/i',
        '/TRUNCATE\s+/i',
        '/ALTER\s+/i',
        '/DELETE\s+/i',
        '/INSERT\s+/i',
        '/UPDATE\s+/i',
        '/GRANT\s+/i',
        '/REVOKE\s+/i',
        '/INTO\s+OUTFILE/i',
        '/LOAD\s+DATA/i',
        '/INFORMATION_SCHEMA/i',
        '/--/',
        '/\/\*/',
        '/\*\//',
        '/;.*--/',
        '/UNION\s+SELECT/i'
    ];

    /**
     * SQL injection patterns to detect
     */
    private const INJECTION_PATTERNS = [
        '/\'\s*OR\s*\'/i',
        '/\'\s*OR\s*1\s*=\s*1/i',
        '/\'\s*OR\s*\'1\'\s*=\s*\'1/i',
        '/--/',
        '/\/\*/',
        '/\*\//',
        '/;.*--/',
        '/UNION\s+SELECT/i',
        '/exec\s*\(/i',
        '/execute\s*\(/i',
        '/xp_cmdshell/i',
        '/sp_executesql/i'
    ];

    /**
     * Constructor
     *
     * @param SecurityLogger|null $securityLogger Security logger instance
     * @param DbSecurity|null $dbSecurity Database security instance
     * @param bool $debug Enable debug mode
     */
    public function __construct(
        ?SecurityLogger $securityLogger = null,
        ?DbSecurity $dbSecurity = null,
        bool $debug = false
    ) {
        $this->securityLogger = $securityLogger ?? new SecurityLogger();
        $this->dbSecurity = $dbSecurity; // Don't instantiate if null - it's optional
        $this->debug = $debug;
    }

    /**
     * Validate SQL security across multiple dimensions.
     *
     * @param string $sql The SQL query to validate
     * @return array Validation results with security assessment
     *               [
     *                 'overall_score' => float,
     *                 'is_secure' => bool,
     *                 'risk_level' => string,
     *                 'checks' => [
     *                   'has_dangerous_patterns' => bool,
     *                   'has_injection_risks' => bool,
     *                   'is_read_only' => bool,
     *                   'has_comments' => bool,
     *                   'has_union' => bool
     *                 ],
     *                 'threats' => array,
     *                 'recommendations' => array
     *               ]
     */
    public function validateSqlSecurity(string $sql): array
    {
        // Perform individual security checks
        $checks = [
            'has_dangerous_patterns' => $this->hasDangerousPatterns($sql),
            'has_injection_risks' => $this->detectSqlInjection($sql),
            'is_read_only' => $this->isReadOnlyQuery($sql),
            'has_comments' => $this->hasComments($sql),
            'has_union' => $this->hasUnionStatement($sql)
        ];

        // Calculate security score
        $securityScore = $this->calculateSecurityScore($checks);

        // Determine if query is secure
        $isSecure = $this->isSecureSql($sql);

        // Determine risk level
        $riskLevel = $this->determineRiskLevel($securityScore, $checks);

        // Identify threats
        $threats = $this->identifyThreats($checks, $sql);

        // Generate recommendations
        $recommendations = $this->generateRecommendations($checks, $threats);

        // Log security validation if threats detected
        if (!empty($threats)) {
            $this->securityLogger->logSecurityEvent(
                "SQL security validation detected threats",
                'warning',
                [
                    'sql_preview' => substr($sql, 0, 100),
                    'risk_level' => $riskLevel,
                    'threat_count' => count($threats)
                ]
            );
        }

        return [
            'overall_score' => $securityScore,
            'is_secure' => $isSecure,
            'risk_level' => $riskLevel,
            'checks' => $checks,
            'threats' => $threats,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Check for dangerous SQL patterns.
     *
     * @param string $sql The SQL query
     * @return bool True if dangerous patterns found
     */
    public function hasDangerousPatterns(string $sql): bool
    {
        foreach (self::DANGEROUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $sql)) {
                if ($this->debug) {
                    $this->securityLogger->logSecurityEvent(
                        "Dangerous pattern detected: {$pattern}",
                        'warning',
                        ['sql_preview' => substr($sql, 0, 100)]
                    );
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Detect SQL injection patterns.
     *
     * @param string $sql The SQL query
     * @return bool True if injection risks detected
     */
    public function detectSqlInjection(string $sql): bool
    {
        foreach (self::INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $sql)) {
                if ($this->debug) {
                    $this->securityLogger->logSecurityEvent(
                        "SQL injection pattern detected: {$pattern}",
                        'warning',
                        ['sql_preview' => substr($sql, 0, 100)]
                    );
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Check if SQL is secure (no dangerous patterns or injection risks).
     *
     * @param string $sql The SQL query
     * @return bool True if secure
     */
    public function isSecureSql(string $sql): bool
    {
        return !$this->hasDangerousPatterns($sql) && !$this->detectSqlInjection($sql);
    }

    /**
     * Check if query is read-only (SELECT, SHOW, DESCRIBE, EXPLAIN).
     *
     * @param string $sql The SQL query
     * @return bool True if read-only
     */
    private function isReadOnlyQuery(string $sql): bool
    {
        $sqlUpper = strtoupper(trim($sql));

        $readOnlyKeywords = ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'];

        foreach ($readOnlyKeywords as $keyword) {
            if (str_starts_with($sqlUpper, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if query contains SQL comments.
     *
     * @param string $sql The SQL query
     * @return bool True if comments found
     */
    private function hasComments(string $sql): bool
    {
      return str_contains($sql, '--') || str_contains($sql, '/*');
    }

    /**
     * Check if query contains UNION statement.
     *
     * @param string $sql The SQL query
     * @return bool True if UNION found
     */
    private function hasUnionStatement(string $sql): bool
    {
      return stripos($sql, 'union') !== false;
    }

    /**
     * Calculate overall security score based on checks.
     *
     * @param array $checks Security check results
     * @return float Security score (0.0 to 1.0)
     */
    public function calculateSecurityScore(array $checks): float
    {
        $score = 1.0; // Start with perfect score

        // Heavy penalties for critical security issues
        if ($checks['has_dangerous_patterns']) {
            $score -= 0.8; // Critical: dangerous operations
        }

        if ($checks['has_injection_risks']) {
            $score -= 0.7; // Critical: injection risks
        }

        // Moderate penalties for suspicious patterns
        if (!$checks['is_read_only']) {
            $score -= 0.3; // Moderate: write operations in analytics
        }

        if ($checks['has_comments']) {
            $score -= 0.1; // Minor: comments can hide malicious code
        }

        if ($checks['has_union']) {
            $score -= 0.2; // Moderate: UNION can be used for injection
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Determine risk level based on security score and checks.
     *
     * @param float $securityScore Overall security score
     * @param array $checks Security check results
     * @return string Risk level ('critical', 'high', 'medium', 'low', 'safe')
     */
    private function determineRiskLevel(float $securityScore, array $checks): string
    {
        // Critical risk: dangerous patterns or injection detected
        if ($checks['has_dangerous_patterns'] || $checks['has_injection_risks']) {
            return 'critical';
        }

        // High risk: very low security score
        if ($securityScore < 0.3) {
            return 'high';
        }

        // Medium risk: moderate security score
        if ($securityScore < 0.6) {
            return 'medium';
        }

        // Low risk: good security score but some concerns
        if ($securityScore < 0.9) {
            return 'low';
        }

        // Safe: excellent security score
        return 'safe';
    }

    /**
     * Identify specific security threats.
     *
     * @param array $checks Security check results
     * @param string $sql The SQL query
     * @return array List of identified threats
     */
    private function identifyThreats(array $checks, string $sql): array
    {
        $threats = [];

        if ($checks['has_dangerous_patterns']) {
            $threats[] = [
                'type' => 'dangerous_operation',
                'severity' => 'critical',
                'description' => 'Query contains dangerous SQL operations (DROP, TRUNCATE, ALTER, etc.)',
                'impact' => 'Could modify or destroy database structure or data'
            ];
        }

        if ($checks['has_injection_risks']) {
            $threats[] = [
                'type' => 'sql_injection',
                'severity' => 'critical',
                'description' => 'Query contains patterns commonly used in SQL injection attacks',
                'impact' => 'Could allow unauthorized data access or manipulation'
            ];
        }

        if (!$checks['is_read_only']) {
            $threats[] = [
                'type' => 'write_operation',
                'severity' => 'high',
                'description' => 'Query performs write operations (INSERT, UPDATE, DELETE)',
                'impact' => 'Analytics queries should be read-only'
            ];
        }

        if ($checks['has_comments']) {
            $threats[] = [
                'type' => 'sql_comments',
                'severity' => 'medium',
                'description' => 'Query contains SQL comments which can hide malicious code',
                'impact' => 'Comments may be used to bypass security filters'
            ];
        }

        if ($checks['has_union']) {
            $threats[] = [
                'type' => 'union_statement',
                'severity' => 'medium',
                'description' => 'Query contains UNION statement which can be exploited',
                'impact' => 'UNION can be used to extract data from other tables'
            ];
        }

        return $threats;
    }

    /**
     * Generate security recommendations.
     *
     * @param array $checks Security check results
     * @param array $threats Identified threats
     * @return array List of recommendations
     */
    private function generateRecommendations(array $checks, array $threats): array
    {
        $recommendations = [];

        if ($checks['has_dangerous_patterns']) {
            $recommendations[] = 'CRITICAL: Remove all dangerous SQL operations (DROP, TRUNCATE, ALTER, etc.)';
            $recommendations[] = 'Analytics queries should only read data, never modify structure';
        }

        if ($checks['has_injection_risks']) {
            $recommendations[] = 'CRITICAL: Remove SQL injection patterns';
            $recommendations[] = 'Use parameterized queries with prepared statements';
            $recommendations[] = 'Validate and sanitize all user inputs';
        }

        if (!$checks['is_read_only']) {
            $recommendations[] = 'Use only SELECT, SHOW, DESCRIBE, or EXPLAIN statements for analytics';
            $recommendations[] = 'Separate read and write operations into different query paths';
        }

        if ($checks['has_comments']) {
            $recommendations[] = 'Remove SQL comments from queries';
            $recommendations[] = 'Use application-level documentation instead of SQL comments';
        }

        if ($checks['has_union']) {
            $recommendations[] = 'Review UNION usage for necessity and security';
            $recommendations[] = 'Ensure UNION is not being used to access unauthorized data';
        }

        if (empty($threats)) {
            $recommendations[] = 'Query appears secure - continue following security best practices';
        }

        return $recommendations;
    }

    /**
     * Generate human-readable security report.
     *
     * @param array $validationResult Result from validateSqlSecurity()
     * @return string Human-readable security report
     */
    public function generateSecurityReport(array $validationResult): string
    {
        $score = $validationResult['overall_score'];
        $riskLevel = $validationResult['risk_level'];
        $isSecure = $validationResult['is_secure'] ? 'SECURE' : 'INSECURE';

        $report = "SQL Security Validation: {$isSecure}\n";
        $report .= "Risk Level: " . strtoupper($riskLevel) . "\n";
        $report .= "Security Score: " . number_format($score, 2) . "\n";

        if (!empty($validationResult['threats'])) {
            $report .= "\n⚠️  THREATS DETECTED:\n";
            foreach ($validationResult['threats'] as $threat) {
                $report .= "  [{$threat['severity']}] {$threat['description']}\n";
                $report .= "    Impact: {$threat['impact']}\n";
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
     * Get security logger instance.
     *
     * @return SecurityLogger
     */
    public function getSecurityLogger(): SecurityLogger
    {
        return $this->securityLogger;
    }

    /**
     * Get database security instance.
     *
     * @return DbSecurity|null
     */
    public function getDbSecurity(): ?DbSecurity
    {
        return $this->dbSecurity;
    }
}
