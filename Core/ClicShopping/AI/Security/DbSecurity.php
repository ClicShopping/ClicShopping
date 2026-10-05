<?php
/**
 * Database Security Class
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\AI\Security;

use ClicShopping\AI\Security\RateLimit;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\AI\Infrastructure\Schema\SchemaEmbedder;
use ClicShopping\AI\Security\InputValidator;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Config\TechnicalDefaults;

/**
 * Class DbSecurity
 * Provides enhanced security for database operations
 * Implements protection against SQL injection and other database attacks
 */
class DbSecurity
{
    private $db;
    private $logger;
    private $rateLimit;
    private $queryWhitelist;
    private $queryBlacklist;

    /**
     * Constructor for DbSecurity
     * Initializes database connection and security components
     */
    public function __construct()
    {
        $this->db = Registry::get('Db');
        $this->logger = new SecurityLogger();
        $this->rateLimit = new RateLimit('database_queries', 100, 60); // 100 queries per minute
        
        // Initialize query whitelist/blacklist patterns
        $this->initializeQueryPatterns();
    }

    /**
     * Initializes query whitelist and blacklist patterns
     * Defines allowed and forbidden SQL patterns
     *
     * @return void
     */
    private function initializeQueryPatterns(): void
    {
        // Whitelist of allowed query patterns for analytics
        $this->queryWhitelist = [
            '/^SELECT\s+.*\s+FROM\s+/i',
            '/^SHOW\s+/i',
            '/^DESCRIBE\s+/i',
            '/^EXPLAIN\s+/i'
        ];
        
        // Blacklist of forbidden query patterns
        $this->queryBlacklist = [
            '/DROP\s+/i',
            '/TRUNCATE\s+/i',
            '/ALTER\s+/i',
            '/GRANT\s+/i',
            '/REVOKE\s+/i',
            '/INTO\s+OUTFILE/i',
            '/LOAD\s+DATA/i',
            '/INFORMATION_SCHEMA/i'
        ];
    }

    /**
     * Executes a SQL query with enhanced security checks
     * Validates, sanitizes, and monitors query execution
     *
     * @param string $query SQL query to execute
     * @param array $params Parameters for prepared statement
     * @param string $userId User identifier for rate limiting and auditing
     * @return array Query result with status and data
     */
    public function executeSecureQuery(string $query, array $params = [], string $userId = 'system'): array
    {
        // Check rate limiting
        if (!$this->rateLimit->checkLimit($userId)) {
            $this->logger->logSecurityEvent(
                "Rate limit exceeded for user {$userId}",
                'error'
            );
            
            return [
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.',
                'error_code' => 'RATE_LIMIT_EXCEEDED'
            ];
        }
        
        // Validate query against whitelist/blacklist
        if (!$this->validateQueryPatterns($query)) {
            $this->logger->logSecurityEvent(
                "Forbidden query pattern attempted: " . htmlspecialchars($query),
                'error'
            );
            
            return [
                'success' => false,
                'error' => 'Query contains forbidden operations.',
                'error_code' => 'FORBIDDEN_QUERY'
            ];
        }
        
        // Validate query syntax and check for injection attempts
        $validation = InputValidator::validateSqlQuery($query);
        if (!$validation['valid']) {
            $this->logger->logSecurityEvent(
                "SQL validation failed: " . implode(', ', $validation['issues']),
                'error'
            );
            
            return [
                'success' => false,
                'error' => 'Query validation failed: ' . implode(', ', $validation['issues']),
                'error_code' => 'VALIDATION_FAILED'
            ];
        }
        
        // ============================================================================
        // SAFETY LIMIT: Add automatic LIMIT clause to prevent memory exhaustion
        // ============================================================================
        if (stripos($query, 'SELECT') === 0 && stripos($query, 'LIMIT') === false) {
            // Define safety limit from TechnicalConfig
            $safetyLimit = TechnicalDefaults::int('CLICSHOPPING_APP_CHATGPT_RA_SQL_SAFETY_LIMIT');
            
            // Add LIMIT clause
            $originalQuery = $query;
            $query = rtrim($query, ';') . ' LIMIT ' . $safetyLimit;
            
            // Log the automatic limit addition
            $this->logger->logSecurityEvent(
                "Automatic LIMIT clause added to query for safety. Original query had no LIMIT. Safety limit: {$safetyLimit} rows.",
                'info',
                [
                    'user_id' => $userId,
                    'original_query' => substr($originalQuery, 0, 200),
                    'modified_query' => substr($query, 0, 200),
                    'safety_limit' => $safetyLimit,
                    'reason' => 'Memory protection - prevents fetching unlimited rows'
                ]
            );
        }
        
        // Execute query with proper error handling
        try {
            $stmt = $this->db->prepare($query);
            
            // Bind parameters if provided
            foreach ($params as $key => $value) {
                $type = \PDO::PARAM_STR;
                if (is_int($value)) {
                    $type = \PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $type = \PDO::PARAM_BOOL;
                } elseif (is_null($value)) {
                    $type = \PDO::PARAM_NULL;
                }
                
                $stmt->bindValue(
                    is_numeric($key) ? $key + 1 : $key,
                    $value,
                    $type
                );
            }
            
            $stmt->execute();
            
            // Log successful query execution
            $this->logger->logSecurityEvent(
                "Query executed successfully by {$userId}",
                'info'
            );
            
            // Return results based on query type
            if (stripos($query, 'SELECT') === 0 || stripos($query, 'SHOW') === 0 || stripos($query, 'DESCRIBE') === 0) {
                return [
                    'success' => true,
                    'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
                    'row_count' => $stmt->rowCount()
                ];
            } else {
                return [
                    'success' => true,
                    'affected_rows' => $stmt->rowCount()
                ];
            }
        } catch (\PDOException $e) {
            // Log error and return error information
            $this->logger->logSecurityEvent(
                "Database error: " . $e->getMessage(),
                'error'
            );
            
            return [
                'success' => false,
                'error' => 'Database error occurred.',
                'error_code' => 'DB_ERROR',
                'debug_message' => $e->getMessage() // Only included in development
            ];
        }
    }

    /**
     * Validates query against whitelist and blacklist patterns
     * Ensures query conforms to allowed patterns and contains no forbidden operations
     *
     * @param string $query SQL query to validate
     * @return bool True if query passes pattern validation
     */
    private function validateQueryPatterns(string $query): bool
    {
        // Check if query matches any whitelist pattern
        $whitelistMatch = false;
        foreach ($this->queryWhitelist as $pattern) {
            if (preg_match($pattern, $query)) {
                $whitelistMatch = true;
                break;
            }
        }
        
        if (!$whitelistMatch) {
            return false;
        }
        
        // Check if query contains any blacklisted pattern
        foreach ($this->queryBlacklist as $pattern) {
            if (preg_match($pattern, $query)) {
                return false;
            }
        }

        return true;
    }

    /** Keywords that write, change the schema or touch server files: never in a read. */
    private const WRITE_KEYWORDS = [
        'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE', 'RENAME',
        'GRANT', 'REVOKE', 'LOAD', 'LOAD_FILE', 'HANDLER', 'CALL', 'OUTFILE', 'DUMPFILE', 'LOCK', 'UNLOCK',
    ];

    /**
     * A read is ONE statement opened by SELECT, WITH, SHOW, DESCRIBE or EXPLAIN, with no write
     * keyword. Literals, quoted identifiers and comments are blanked first, so a word inside a
     * string never trips it. Enforced by DoctrineOrm::select*(): generated SQL can never write.
     *
     * @param string $sql SQL about to be executed
     * @return string|null Why it is refused, or null when it may run
     */
    public static function readOnlyViolation(string $sql): ?string
    {
        // A MariaDB executable comment /*! ... */ RUNS its content: refused, never blanked.
        if (str_contains($sql, '/*!')) {
            return 'executable comment';
        }

        $code = preg_replace([
            "/'(?:[^'\\\\]|\\\\.|'')*'/s",
            '/"(?:[^"\\\\]|\\\\.|"")*"/s',
            '/`(?:[^`]|``)*`/s',
            '/\/\*.*?\*\//s',
            '/(--\s|#)[^\n]*/',
        ], [' 0 ', ' 0 ', ' 0 ', ' ', ' '], $sql) ?? '';

        $code = rtrim(trim($code), "; \t\n\r");

        if (str_contains($code, ';')) {
            return 'several statements';
        }

        if (preg_match('/^\(*\s*(SELECT|WITH|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i', $code) !== 1) {
            return 'not a read';
        }

        if (preg_match_all('/\b([A-Z_]+)\b(\s*\()?/i', $code, $tokens, PREG_SET_ORDER) > 0) {
            foreach ($tokens as $token) {
                $word = strtoupper($token[1]);

                // REPLACE( is the string function; REPLACE INTO is a write.
                if (in_array($word, self::WRITE_KEYWORDS, true) && !($word === 'REPLACE' && !empty($token[2]))) {
                    return 'write keyword ' . $word;
                }
            }
        }

        return null;
    }

    /**
     * A table its schema declares `ai_execute = deny` (credentials, tokens, sessions) is never
     * read. Only string literals are blanked: a quoted identifier, a comment still name the table.
     *
     * @param string $sql SQL about to be executed
     * @return string|null Why it is refused, or null when it may run
     */
    public static function deniedTableViolation(string $sql): ?string
    {
        $denied = array_keys(SchemaEmbedder::declaredExecutionDenied());

        if ($denied === []) {
            return null;
        }

        $code = preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/s", ' 0 ', $sql) ?? '';
        $code = str_replace(['`', '"'], '', $code);
        $prefix = (string)CLICSHOPPING::getConfig('db_table_prefix');

        foreach ($denied as $table) {
            if (preg_match('/(?<![A-Za-z0-9_$])' . preg_quote($prefix . $table, '/') . '(?![A-Za-z0-9_$])/i', $code) === 1) {
                return 'denied table ' . $prefix . $table;
            }
        }

        return null;
    }
}
