<?php
/**
 * Security Logger Class
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 *
 */

namespace ClicShopping\AI\Security;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Infrastructure\Cache\Cache;
use ClicShopping\AI\Infrastructure\Metrics\SecurityStatistics;

/**
 * Class SecurityLogger
 * Provides comprehensive security event logging with database integration
 * Supports multiple log levels, formats, and database persistence
 * 
 * Requirements: 8.1, 8.2, 8.3
 */
class SecurityLogger
{
    private mixed $logFile;
    private mixed $maxLogSize;
    private mixed $logRotations;
    private mixed $logLevel;
    private mixed $db;
    private mixed $stats;
    
    // Performance optimization: Cache log level numeric values
    private static $levelCache = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3
    ];
    
    private $minimumLevelNumeric;
    
    // Performance optimization: Buffer log entries for batch writing
    private $logBuffer = [];
    private $bufferSize = 10; // Write after 10 entries
    private $bufferEnabled = false;

    /**
     * Constructor for SecurityLogger
     * Initializes logging parameters and ensures log directory exists
     *
     * @param string $logLevel Minimum log level to record (debug, info, warning, error)
     * @param int $maxLogSize Maximum log file size in bytes before rotation
     * @param int $logRotations Number of log rotations to maintain
     * @param bool $bufferEnabled Enable log buffering for better performance
     */
    public function __construct(string $logLevel = 'info', int $maxLogSize = 10485760, int $logRotations = 5, bool $bufferEnabled = false)
    {
        $this->logFile = Cache::getLogFilePath();
        $this->maxLogSize = $maxLogSize;
        $this->logRotations = $logRotations;
        $this->logLevel = $logLevel;
        $this->bufferEnabled = $bufferEnabled;
        
        // Cache numeric level for performance
        $this->minimumLevelNumeric = self::$levelCache[$logLevel] ?? self::$levelCache['info'];
        
        // Initialize database connection
        if (Registry::exists('Db')) {
            $this->db = Registry::get('Db');
        }
        
        // Register shutdown function to flush buffer
        if ($bufferEnabled) {
            register_shutdown_function($this->flushBuffer(...));
        }
	
	$this->stats = new SecurityStatistics();
    }

    /**
     * Logs a security event with specified level
     * Formats log entry with timestamp, level, and message
     *
     * @param string $message Security event message
     * @param string $level Log level (debug, info, warning, error)
     * @param array $context Additional context data for the log entry
     * @return bool True if log entry was written successfully
     */
    public function logSecurityEvent(string $message, string $level = 'info', array $context = []): bool
    {
        // Performance optimization: Fast level check using cached numeric values
        $levelNumeric = self::$levelCache[$level] ?? self::$levelCache['info'];
        
        if ($levelNumeric < $this->minimumLevelNumeric) {
            return false;
        }
        
        // Format log entry
        $logEntry = $this->formatLogEntry($level, $message, $context);
        
        // Use buffering if enabled
        if ($this->bufferEnabled) {
            return $this->addToBuffer($logEntry);
        }
        
        // Direct write if buffering disabled
        return $this->writeLogEntry($logEntry);
    }

    /**
     * Logs an AI application/infrastructure error to the dedicated
     * ai_errors-YYYYMMDD.txt channel, keeping it OUT of the real-security
     * journal (rag_security.log) and out of the native errors-*.txt read by the
     * non-AI admin.
     *
     * Application errors (failed cache init, RAG document I/O, LLM evaluation
     * failures, JSON parse errors…) are diagnostics for the AI maintainer, never
     * security events, and are always written regardless of the security log level.
     *
     * @param string $message Application error message
     * @param array $context Additional context data, json-encoded
     * @return bool True if the entry was written successfully
     */
    public function logApplicationError(string $message, array $context = []): bool
    {
        $logEntry = $this->formatLogEntry('error', $message, $context);

        return $this->writeApplicationErrorEntry($logEntry);
    }

    /**
     * Format log entry (extracted for reusability)
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @return string Formatted log entry
     */
    private function formatLogEntry(string $level, string $message, array $context = []): string
    {
        // Performance optimization: Use static timestamp format
        static $timestampFormat = 'Y-m-d H:i:s';
        $timestamp = date($timestampFormat);
        
        // Performance optimization: Only encode context if not empty
        $contextStr = empty($context) ? '' : (' ' . json_encode($context));
        
        return "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
    }
    
    /**
     * Write log entry to file
     *
     * @param string $logEntry Formatted log entry
     * @return bool True if written successfully
     */
    private function writeLogEntry(string $logEntry): bool
    {
        // Check if log rotation is needed (only when actually writing)
        $this->rotateLogIfNeeded();
        
        // Write to log file
        return (bool)file_put_contents(
            $this->logFile,
            $logEntry,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Write an entry to the AI application-error channel (ai_errors-YYYYMMDD.txt).
     *
     * Separate from writeLogEntry() so application errors never touch the
     * security journal. The date-stamped filename rotates daily by itself.
     *
     * @param string $logEntry Formatted log entry
     * @return bool True if written successfully
     */
    private function writeApplicationErrorEntry(string $logEntry): bool
    {
        return (bool)file_put_contents(
            Cache::getApplicationErrorLogFilePath(),
            $logEntry,
            FILE_APPEND | LOCK_EX
        );
    }

    /**
     * Add log entry to buffer
     *
     * @param string $logEntry Formatted log entry
     * @return bool True if added successfully
     */
    private function addToBuffer(string $logEntry): bool
    {
        $this->logBuffer[] = $logEntry;
        
        // Flush buffer if size limit reached
        if (count($this->logBuffer) >= $this->bufferSize) {
            return $this->flushBuffer();
        }
        
        return true;
    }
    
    /**
     * Flush log buffer to file
     *
     * @return bool True if flushed successfully
     */
    public function flushBuffer(): bool
    {
        if (empty($this->logBuffer)) {
            return true;
        }
        
        // Check if log rotation is needed
        $this->rotateLogIfNeeded();
        
        // Write all buffered entries at once
        $success = (bool)file_put_contents(
            $this->logFile,
            implode('', $this->logBuffer),
            FILE_APPEND | LOCK_EX
        );
        
        // Clear buffer
        $this->logBuffer = [];
        
        return $success;
    }

    /**
     * Logs a structured event in JSON format
     * Provides consistent logging format across all components
     * 
     * @param string $level Log level (info, warning, error)
     * @param string $component Component name (e.g., 'Semantics', 'OrchestratorAgent')
     * @param string $operation Operation being performed (e.g., 'classification', 'validation')
     * @param array $data Additional data to log
     * @return bool True if log entry was written successfully
     */
    public function logStructured(string $level, string $component, string $operation, array $data = []): bool
    {
        // Performance optimization: Fast level check using cached numeric values
        $levelNumeric = self::$levelCache[$level] ?? self::$levelCache['info'];
        if ($levelNumeric < $this->minimumLevelNumeric) {
            return false;
        }
        
        // Create structured log entry
        $logEntry = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'), // UTC timestamp in ISO 8601 format
            'level' => $level,
            'component' => $component,
            'operation' => $operation,
            'data' => $data
        ];
        
        // Format as JSON with newline
        $jsonEntry = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        
        // Use buffering if enabled
        if ($this->bufferEnabled) {
            return $this->addToBuffer($jsonEntry);
        }
        
        // Direct write if buffering disabled
        return $this->writeLogEntry($jsonEntry);
    }

    /**
     * Rotates log file if it exceeds maximum size
     * Maintains specified number of log rotations
     *
     * @return void
     */
    private function rotateLogIfNeeded(): void
    {
        // Check if log file exists and exceeds size limit
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxLogSize) {
            // Remove oldest log if rotation limit reached
            if (file_exists($this->logFile . '.' . $this->logRotations)) {
                unlink($this->logFile . '.' . $this->logRotations);
            }
            
            // Shift existing logs
            for ($i = $this->logRotations - 1; $i >= 1; $i--) {
                if (file_exists($this->logFile . '.' . $i)) {
                    rename(
                        $this->logFile . '.' . $i,
                        $this->logFile . '.' . ($i + 1)
                    );
                }
            }
            
            // Rename current log
            rename($this->logFile, $this->logFile . '.1');
        }
    }

    /**
     * Gets all security logs with optional filtering
     * Retrieves log entries from all rotated files
     *
     * @param string $level Minimum log level to include
     * @param int $limit Maximum number of entries to return
     * @param int $offset Starting offset for pagination
     * @return array Array of log entries
     */
    public function getSecurityLogs(string $level = 'info', int $limit = 100, int $offset = 0): array
    {
        $logs = [];
        $count = 0;
        $skip = $offset;
        
        // Performance optimization: Calculate minimum level once
        $minimumLevelNumeric = self::$levelCache[$level] ?? self::$levelCache['info'];
        
        // Process all log files in reverse order (newest first)
        for ($i = $this->logRotations; $i >= 0; $i--) {
            $currentFile = $i === 0 ? $this->logFile : $this->logFile . '.' . $i;
            
            if (!file_exists($currentFile)) {
                continue;
            }
            
            $fileHandle = fopen($currentFile, 'r');
            if ($fileHandle) {
                while (($line = fgets($fileHandle)) !== false) {
                    // Parse log level from line
                    if (preg_match('/\[(debug|info|warning|error)\]/', $line, $matches)) {
                        $entryLevel = $matches[1];
                        
                        // Performance optimization: Direct numeric comparison instead of shouldLog()
                        $entryLevelNumeric = self::$levelCache[$entryLevel] ?? self::$levelCache['info'];
                        if ($entryLevelNumeric < $minimumLevelNumeric) {
                            continue;
                        }
                        
                        // Skip entries for pagination
                        if ($skip > 0) {
                            $skip--;
                            continue;
                        }
                        
                        // Add to results
                        $logs[] = $line;
                        $count++;
                        
                        // Stop if limit reached
                        if ($count >= $limit) {
                            break 2;
                        }
                    }
                }
                fclose($fileHandle);
            }
        }
        
        return $logs;
    }

    /**
     * Clears all security logs
     * Removes all log files including rotations
     *
     * @return bool True if logs were successfully cleared
     */
    public function clearLogs(): bool
    {
        $success = true;
        
        // Remove main log file
        if (file_exists($this->logFile)) {
            $success = $success && unlink($this->logFile);
        }
        
        // Remove rotated logs
        for ($i = 1; $i <= $this->logRotations; $i++) {
            if (file_exists($this->logFile . '.' . $i)) {
                $success = $success && unlink($this->logFile . '.' . $i);
            }
        }
        
        return $success;
    }
    
    /**
     * Log a security query (non-threatening)
     * 
     * @param string $query The query
     * @param float $threatScore Threat score (0.0-1.0)
     * @param string $threatType Type detected
     * @param array $context Additional context
     * @return bool True if logged successfully
     */
    public function logQuery(string $query, float $threatScore, string $threatType, array $context = []): bool
    {
        $message = "Query analyzed: {$threatType} (score: {$threatScore})";
        
        $logContext = array_merge([
            'query_preview' => substr($query, 0, 100),
            'threat_type' => $threatType,
            'threat_score' => $threatScore
        ], $context);
        
        return $this->logSecurityEvent($message, 'info', $logContext);
    }
    
    /**
     * Log an error
     * 
     * @param string $message Error message
     * @param array $context Additional context
     * @return bool True if logged successfully
     */
    public function logError(string $message, array $context = []): bool
    {
        return $this->logApplicationError($message, $context);
    }
    
    /**
     * Log a security event to database
     * Comprehensive event logging with all security-related details
     * Also checks threat rates and triggers alerts if needed
     * 
     * @param string $eventType Event type (threat_detected, threat_blocked, etc.)
     * @param array $details Event details including query, threat info, detection details
     * @return bool True if logged successfully
     * 
     * Requirements: 8.1, 8.2
     */
    private const ALLOWED_EVENT_TYPES = [
        'threat_detected', 'threat_blocked', 'false_positive',
        'security_check_passed', 'security_check_failed',
        'pattern_fallback', 'llm_unavailable',
        'response_validation_failed', 'leakage_detected',
        'grounding_failed', 'query_allowed', 'query_blocked',
        'security_fallback', 'layer_performance', 'test_event',
        'obfuscation_detected', 'websearch_facade_initialized',
    ];

    private const ALLOWED_DETECTION_METHODS = [
        'llm_semantic', 'pattern_based', 'response_validation', 'hybrid',
        'llm', 'llm_analysis', 'llm_intent_flag', 'llm_cached',
        'pattern_removal', 'pattern_prefilter', 'pattern_fallback',
        'keyword_analysis', 'dynamic_keyword_match', 'error', 'unknown',
    ];

    public function logEvent(string $eventType, array $details): bool
    {
        // Validate event_type against allowed ENUM values before DB insert
        if (!in_array($eventType, self::ALLOWED_EVENT_TYPES, true)) {
            $details['original_event_type'] = $eventType;
            $eventType = 'security_check_failed';
        }

        // Log to file first (always)
        $fileLogged = $this->logSecurityEvent(
            "Security Event: {$eventType}",
            $details['severity'] ?? 'info',
            $details
        );
        
        // Log to database if available
        if (!$this->db) {
            return $fileLogged;
        }
        
        try {
            // Use table name without prefix - save() method adds it automatically
            $table = 'rag_security_events';
            
            // Generate unique event ID
            $eventId = $this->generateEventId();
            
            // Calculate expiration date (90 days default)
            $retentionDays = $details['retention_days'] ?? 90;
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$retentionDays} days"));
            
            // Prepare data for insertion
            $data = [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'severity' => $details['severity'] ?? 'medium',
                'threat_type' => $details['threat_type'] ?? null,
                'threat_score' => $details['threat_score'] ?? null,
                'confidence' => $details['confidence'] ?? null,
                'user_query' => $details['query'] ?? '',
                'query_language' => $details['language'] ?? 'en',
                'query_hash' => $details['query_hash'] ?? (isset($details['query']) && $details['query'] ? md5($details['query']) : null),
                'detection_method' => in_array($details['detection_method'] ?? 'unknown', self::ALLOWED_DETECTION_METHODS, true)
                    ? ($details['detection_method'] ?? 'unknown')
                    : 'unknown',
                'detection_layer' => $details['detection_layer'] ?? null,
                'matched_patterns' => isset($details['matched_patterns']) ? json_encode($details['matched_patterns']) : null,
                'llm_reasoning' => $details['reasoning'] ?? null,
                'action_taken' => $details['action_taken'] ?? 'logged_only',
                'blocked' => $details['blocked'] ?? 0,
                'response_generated' => $details['response'] ?? null,
                'response_blocked' => $details['response_blocked'] ?? 0,
                'user_id' => $details['user_id'] ?? null,
                'session_id' => $details['session_id'] ?? null,
                'ip_address' => $details['ip_address'] ?? null,
                'user_agent' => $details['user_agent'] ?? null,
                'interaction_id' => $details['interaction_id'] ?? null,
                'request_type' => $details['request_type'] ?? null,
                'agent_used' => $details['agent_used'] ?? null,
                'detection_time_ms' => $details['detection_time_ms'] ?? null,
                'total_processing_time_ms' => $details['total_processing_time_ms'] ?? null,
                'metadata' => isset($details['metadata']) ? json_encode($details['metadata']) : null,
                'context' => isset($details['context']) ? json_encode($details['context']) : null,
                'error_message' => $details['error_message'] ?? null,
                'expires_at' => $expiresAt,
                'archived' => 0
            ];
            
            // Insert into database
            $this->db->save($table, $data);
            
            // Check if we should trigger alerts (for threat events)
            if (in_array($eventType, ['threat_detected', 'threat_blocked'], true) && isset($details['blocked']) && $details['blocked']) {
                $this->checkAndTriggerAlerts();
            }
            
            return true;
        } catch (\Exception $e) {
            // Log error to file if database insert fails
            $this->logSecurityEvent(
                "Failed to log event to database: " . $e->getMessage(),
                'error',
                ['event_type' => $eventType, 'original_details' => $details]
            );
            
            return $fileLogged;
        }
    }
    
    /**
     * Log a threat detection event
     * Convenience method for logging threats with proper structure
     * 
     * @param string $query The malicious query
     * @param string $threatType Type of threat (instruction_override, exfiltration, hallucination)
     * @param float $threatScore Threat score (0.0-1.0)
     * @param string $reasoning Human-readable reasoning
     * @param array $context Additional context
     * @return bool True if logged successfully
     * 
     * Requirements: 8.1, 8.2
     */
    public function logThreat(string $query, string $threatType, float $threatScore, string $reasoning, array $context = []): bool
    {
        // Determine severity based on threat score
        $severity = 'low';
        if ($threatScore >= 0.9) {
            $severity = 'critical';
        } elseif ($threatScore >= 0.7) {
            $severity = 'high';
        } elseif ($threatScore >= 0.5) {
            $severity = 'medium';
        }
        
        // Prepare event details
        $details = array_merge([
            'query' => $query,
            'threat_type' => $threatType,
            'threat_score' => $threatScore,
            'reasoning' => $reasoning,
            'severity' => $severity,
            'blocked' => $threatScore >= 0.7 ? 1 : 0,
            'action_taken' => $threatScore >= 0.7 ? 'blocked' : 'flagged'
        ], $context);
        
        // Log as threat_detected or threat_blocked based on score
        $eventType = $threatScore >= 0.7 ? 'threat_blocked' : 'threat_detected';
        
        return $this->logEvent($eventType, $details);
    }
    
    /**
     * Generate security report for specified period
     * Aggregates security events and provides statistics
     * 
     * @param string $period Report period ('daily', 'weekly', 'monthly')
     * @param string|null $startDate Optional start date (Y-m-d format)
     * @param string|null $endDate Optional end date (Y-m-d format)
     * @return array Report data with statistics and event summaries
     * 
     * Requirements: 8.3
     */
    public function generateReport(string $period = 'daily', ?string $startDate = null, ?string $endDate = null): array
    {
        // Use SecurityStatistics for comprehensive reporting
        
        switch ($period) {
            case 'daily':
                return 	$this->stats->generateDailyReport($startDate);
            case 'weekly':
                return 	$this->stats->generateWeeklyReport($startDate);
            case 'monthly':
                // Monthly is 30 days
                $start = $startDate ?? date('Y-m-d', strtotime('-30 days'));
                $end = $endDate ?? date('Y-m-d');
                return 	$this->stats->generateWeeklyReport($start); // Use weekly format for monthly
            default:
                return 	$this->stats->generateDailyReport($startDate);
        }
    }
    
    /**
     * Calculate detection rates
     * 
     * @param string $startDate Start date (Y-m-d H:i:s)
     * @param string $endDate End date (Y-m-d H:i:s)
     * @return array Detection rate statistics
     * 
     * Requirements: 8.3
     */
    public function calculateDetectionRates(string $startDate, string $endDate): array
    {
        return 	$this->stats->calculateDetectionRates($startDate, $endDate);
    }
    
    /**
     * Calculate false positive rates
     * 
     * @param string $startDate Start date (Y-m-d H:i:s)
     * @param string $endDate End date (Y-m-d H:i:s)
     * @return array False positive rate statistics
     * 
     * Requirements: 8.3
     */
    public function calculateFalsePositiveRates(string $startDate, string $endDate): array
    {
        return 	$this->stats->calculateFalsePositiveRates($startDate, $endDate);
    }
    
    /**
     * Generate trend analysis
     * 
     * @param int $days Number of days to analyze (default: 30)
     * @return array Trend analysis data
     * 
     * Requirements: 8.3
     */
    public function generateTrendAnalysis(int $days = 30): array
    {
        return 	$this->stats->generateTrendAnalysis($days);
    }
    
    /**
     * Get comprehensive security metrics
     * 
     * @param int $days Number of days to analyze (default: 7)
     * @return array Comprehensive metrics
     * 
     * Requirements: 8.3
     */
    public function getComprehensiveMetrics(int $days = 7): array
    {
        return 	$this->stats->getComprehensiveMetrics($days);
    }
    
    /**
     * Get security health score
     * 
     * @param int $days Number of days to analyze (default: 7)
     * @return array Health score and breakdown
     * 
     * Requirements: 8.3
     */
    public function getSecurityHealthScore(int $days = 7): array
    {
        return 	$this->stats->getSecurityHealthScore($days);
    }
    
    /**
     * Generate unique event ID (UUID v4)
     * 
     * @return string UUID
     */
    private function generateEventId(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Get security statistics for dashboard
     * Provides quick overview of security metrics
     * 
     * @param int $hours Number of hours to look back (default: 24)
     * @return array Statistics data
     */
    public function getStatistics(int $hours = 24): array
    {
        if (!$this->db) {
            return ['error' => 'Database connection not available'];
        }
        
        try {
            $prefix = CLICSHOPPING::getConfig('db_table_prefix');
            $table = ':table_rag_security_events';  // Use :table_ prefix for queries
            
            $startTime = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
            
            // Get counts by severity
            $query = "SELECT 
                        COUNT(*) as total_events,
                        SUM(CASE WHEN severity = 'critical' THEN 1 ELSE 0 END) as critical_count,
                        SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_count,
                        SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium_count,
                        SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low_count,
                        SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_count
                     FROM {$table}
                     WHERE created_at >= :start_time";
            
            $result = $this->db->prepare($query);
            $result->bindValue(':start_time', $startTime);
            $result->execute();
            $stats = $result->fetch();
            
            return [
                'period_hours' => $hours,
                'total_events' => $stats['total_events'] ?? 0,
                'critical_count' => $stats['critical_count'] ?? 0,
                'high_count' => $stats['high_count'] ?? 0,
                'medium_count' => $stats['medium_count'] ?? 0,
                'low_count' => $stats['low_count'] ?? 0,
                'blocked_count' => $stats['blocked_count'] ?? 0,
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            $this->logSecurityEvent(
                "Failed to get security statistics: " . $e->getMessage(),
                'error'
            );
            
            return ['error' => 'Failed to get statistics: ' . $e->getMessage()];
        }
    }
    
    /**
     * Log a security decision (allow or block)
     * Logs all security decisions made by the orchestrator
     * 
     * @param string $query User query
     * @param bool $blocked Whether the query was blocked
     * @param float $threatScore Threat score (0.0-1.0)
     * @param string $threatType Type of threat detected
     * @param string $reasoning Decision reasoning
     * @param array $context Additional context
     * @return bool True if logged successfully
     * 
     * Requirements: 8.1, 8.2
     */
    public function logSecurityDecision(
        string $query,
        bool $blocked,
        float $threatScore,
        string $threatType,
        string $reasoning,
        array $context = []
    ): bool {
        // Determine event type
        $eventType = $blocked ? 'query_blocked' : 'query_allowed';
        
        // Determine severity
        $severity = 'low';
        if ($threatScore >= 0.9) {
            $severity = 'critical';
        } elseif ($threatScore >= 0.7) {
            $severity = 'high';
        } elseif ($threatScore >= 0.5) {
            $severity = 'medium';
        }
        
        // Map detection_method to valid ENUM value
        $detectionMethod = $context['detection_method'] ?? 'llm_semantic';
        if (!in_array($detectionMethod, ['llm_semantic', 'pattern_based', 'response_validation', 'hybrid'], true)) {
            $detectionMethod = 'llm_semantic'; // default
        }
        
        $safeContext = $context;
        unset($safeContext['detection_method']);

        // Prepare event details
        $details = [
            'query' => $query,
            'blocked' => $blocked ? 1 : 0,
            'threat_score' => $threatScore,
            'threat_type' => $threatType,
            'reasoning' => $reasoning,
            'severity' => $severity,
            'action_taken' => $blocked ? 'blocked' : 'allowed',
            'detection_method' => $detectionMethod,
            ...$safeContext
        ];
        
        // Log to database
        return $this->logEvent($eventType, $details);
    }
    
    /**
     * Log fallback usage (LLM to pattern)
     * Logs when the system falls back from LLM to pattern-based detection
     * 
     * @param string $query User query
     * @param string $reason Reason for fallback (e.g., 'llm_unavailable', 'llm_timeout', 'llm_error')
     * @param array $context Additional context
     * @return bool True if logged successfully
     * 
     * Requirements: 8.1, 8.2
     */
    public function logFallbackUsage(string $query, string $reason, array $context = []): bool
    {
        // Log to file
        $this->logSecurityEvent(
            "Security fallback triggered: {$reason}",
            'warning',
            [
                'query_preview' => substr($query, 0, 100),
                'fallback_reason' => $reason,
                ...$context
            ]
        );
        
        // Map detection_method to valid ENUM value
        $detectionMethod = $context['detection_method'] ?? 'pattern_based';
        if (!in_array($detectionMethod, ['llm_semantic', 'pattern_based', 'response_validation', 'hybrid'], true)) {
            $detectionMethod = 'pattern_based'; // default for fallback
        }
        
        $safeContext = $context;
        unset($safeContext['detection_method']);

        // Log to database
        $details = [
            'query' => $query,
            'severity' => 'medium',
            'action_taken' => 'fallback_triggered',
            'detection_method' => $detectionMethod,
            'metadata' => [
                'fallback_reason' => $reason,
                'from_layer' => 'llm',
                'to_layer' => 'pattern',
                ...$safeContext
            ],
            ...$safeContext
        ];
        
        return $this->logEvent('security_fallback', $details);
    }
    
    /**
     * Log layer performance metrics
     * Logs performance metrics for security layers (LLM, pattern, response validation)
     * 
     * @param string $layer Layer name ('llm', 'pattern', 'response_validation')
     * @param float $latencyMs Latency in milliseconds
     * @param bool $success Whether the layer executed successfully
     * @param array $metrics Additional performance metrics
     * @return bool True if logged successfully
     * 
     * Requirements: 8.1, 8.2
     */
    public function logLayerPerformance(
        string $layer,
        float $latencyMs,
        bool $success,
        array $metrics = []
    ): bool {
        // Log to file
        $this->logSecurityEvent(
            "Security layer performance: {$layer}",
            'info',
            [
                'layer' => $layer,
                'latency_ms' => $latencyMs,
                'success' => $success,
                ...$metrics
            ]
        );
        
        // Map layer to detection_method ENUM value
        $detectionMethod = 'llm_semantic'; // default
        if ($layer === 'pattern') {
            $detectionMethod = 'pattern_based';
        } elseif ($layer === 'response_validation') {
            $detectionMethod = 'response_validation';
        } elseif ($layer === 'hybrid') {
            $detectionMethod = 'hybrid';
        }
        
        // Log to database
        $details = [
            'severity' => 'low',
            'detection_layer' => $layer,
            'detection_time_ms' => $latencyMs,
            'detection_method' => $detectionMethod,
            'action_taken' => $success ? 'layer_executed' : 'layer_failed',
            'metadata' => [
                'layer' => $layer,
                'success' => $success,
                'performance_metrics' => $metrics
            ]
        ];
        
        return $this->logEvent('layer_performance', $details);
    }
    
    /**
     * Log blocked query with full details
     * Convenience method for logging blocked queries with comprehensive information
     * 
     * @param string $query User query
     * @param string $threatType Type of threat detected
     * @param float $threatScore Threat score (0.0-1.0)
     * @param string $reasoning Human-readable reasoning
     * @param string $detectionMethod Detection method used ('llm', 'pattern', 'hybrid')
     * @param array $context Additional context
     * @return bool True if logged successfully
     * 
     * Requirements: 8.1, 8.2
     */
    public function logBlockedQuery(
        string $query,
        string $threatType,
        float $threatScore,
        string $reasoning,
        string $detectionMethod,
        array $context = []
    ): bool {
        // Determine severity based on threat score
        $severity = 'low';
        if ($threatScore >= 0.9) {
            $severity = 'critical';
        } elseif ($threatScore >= 0.7) {
            $severity = 'high';
        } elseif ($threatScore >= 0.5) {
            $severity = 'medium';
        }
        
        // Log to file
        $this->logSecurityEvent(
            "Query blocked: {$threatType} (score: {$threatScore})",
            'warning',
            [
                'query_preview' => substr($query, 0, 100),
                'threat_type' => $threatType,
                'threat_score' => $threatScore,
                'detection_method' => $detectionMethod,
                ...$context
            ]
        );
        
        // Map detection_method to valid ENUM value
        $detectionMethodEnum = 'llm_semantic'; // default
        if ($detectionMethod === 'pattern') {
            $detectionMethodEnum = 'pattern_based';
        } elseif ($detectionMethod === 'hybrid') {
            $detectionMethodEnum = 'hybrid';
        } elseif ($detectionMethod === 'response_validation') {
            $detectionMethodEnum = 'response_validation';
        }
        
        $safeContext = $context;
        unset($safeContext['detection_method']);

        // Prepare event details
        $details = [
            'query' => $query,
            'threat_type' => $threatType,
            'threat_score' => $threatScore,
            'reasoning' => $reasoning,
            'severity' => $severity,
            'blocked' => 1,
            'action_taken' => 'blocked',
            'detection_method' => $detectionMethodEnum,
            ...$safeContext
        ];
        
        // Log to database
        return $this->logEvent('threat_blocked', $details);
    }
    
    /**
     * Log obfuscation detection
     * Logs when obfuscation techniques are detected in a query
     * 
     * @param string $query Original query
     * @param array $obfuscationTypes Types of obfuscation detected
     * @param array $details Additional details (original, normalized, confidence_boost)
     * @return bool True if logged successfully
     * 
     * Requirements: 6.4.2
     */
    public function logObfuscationDetection(string $query, array $obfuscationTypes, array $details = []): bool
    {
        // Log to file
        $this->logSecurityEvent(
            "Obfuscation detected: " . implode(', ', $obfuscationTypes),
            'warning',
            [
                'query_preview' => substr($query, 0, 100),
                'obfuscation_types' => $obfuscationTypes,
                'confidence_boost' => $details['confidence_boost'] ?? 0.0,
                'normalized_preview' => isset($details['normalized']) ? substr($details['normalized'], 0, 100) : null
            ]
        );
        
        // Log to database
        $eventDetails = [
            'query' => $query,
            'severity' => 'medium',
            'action_taken' => 'obfuscation_detected',
            'detection_method' => 'pattern_based',
            'detection_layer' => 'preprocessing',
            'metadata' => [
                'obfuscation_types' => $obfuscationTypes,
                'original' => $details['original'] ?? $query,
                'normalized' => $details['normalized'] ?? $query,
                'confidence_boost' => $details['confidence_boost'] ?? 0.0
            ]
        ];
        
        return $this->logEvent('obfuscation_detected', $eventDetails);
    }
    
    /**
     * Check threat rates and trigger alerts if needed
     * Called after logging threat events
     * 
     * @return void
     */
    private function checkAndTriggerAlerts(): void
    {
        try {
            $alerter = new SecurityAlerter();
            $alerter->checkThreatRate();
        } catch (\Exception $e) {
            // Don't let alerting errors break the system
            $this->logSecurityEvent(
                "Failed to check threat rate for alerting: " . $e->getMessage(),
                'error'
            );
        }
    }
    
    /**
     * Trigger system failure alert
     * Public method to allow other components to trigger failure alerts
     * 
     * @param string $failureType Type of failure
     * @param string $errorMessage Error message
     * @param array $context Additional context
     * @return bool True if alert sent
     * 
     * Requirements: 8.4
     */
    public function triggerSystemFailureAlert(string $failureType, string $errorMessage, array $context = []): bool
    {
        try {
            $alerter = new SecurityAlerter();
            return $alerter->sendSystemFailureAlert($failureType, $errorMessage, $context);
        } catch (\Exception $e) {
            $this->logSecurityEvent(
                "Failed to send system failure alert: " . $e->getMessage(),
                'error',
                ['failure_type' => $failureType, 'original_error' => $errorMessage]
            );
            return false;
        }
    }
    
    /**
     * Send hourly digest
     * Should be called by cron job or scheduled task
     * 
     * @return bool True if digest sent
     * 
     * Requirements: 8.4
     */
    public function sendHourlyDigest(): bool
    {
        try {
            $alerter = new SecurityAlerter();
            return $alerter->sendHourlyDigest();
        } catch (\Exception $e) {
            $this->logSecurityEvent(
                "Failed to send hourly digest: " . $e->getMessage(),
                'error'
            );
            return false;
        }
    }
    
    /**
     * Test alert configuration
     * Sends a test email to verify alerting is working
     * 
     * @return array Result with success status and message
     * 
     * Requirements: 8.4
     */
    public function testAlertConfiguration(): array
    {
        try {
            $alerter = new SecurityAlerter();
            return $alerter->sendTestAlert();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to test alert configuration: ' . $e->getMessage()
            ];
        }
    }
}
