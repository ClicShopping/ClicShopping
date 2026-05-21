<?php
/**
 * Rate Limiter for heavy operations
 * 
 * Prevents abuse of resource-intensive operations like backup, restore, upgrade, etc.
 * Uses session-based rate limiting with configurable time windows.
 */

namespace ClicShopping\OM;

class RateLimiter
{
    /**
     * Default rate limit in seconds (60 seconds between operations)
     */
    const DEFAULT_LIMIT = 60;

    /**
     * Session key prefix for rate limiting
     */
    const SESSION_KEY_PREFIX = 'ratelimit_';

    /**
     * Check if an operation is allowed
     * 
     * @param string $operation The operation name (e.g., 'backup', 'restore')
     * @param int $limit_seconds Time window in seconds (default: 60)
     * @return array ['allowed' => bool, 'message' => string, 'wait_seconds' => int]
     */
    public static function check(string $operation, int $limit_seconds = self::DEFAULT_LIMIT): array
    {
        $session_key = self::SESSION_KEY_PREFIX . $operation;
        
        // Initialize session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Get last execution time
        $last_execution = isset($_SESSION[$session_key]) ? (int)$_SESSION[$session_key] : 0;
        $now = time();
        $elapsed = $now - $last_execution;

        if ($elapsed < $limit_seconds) {
            $wait_seconds = $limit_seconds - $elapsed;
            return [
                'allowed' => false,
                'message' => sprintf(
                    'Veuillez attendre %d secondes avant de relancer cette opération.',
                    $wait_seconds
                ),
                'wait_seconds' => $wait_seconds
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'wait_seconds' => 0
        ];
    }

    /**
     * Record an operation execution
     * 
     * @param string $operation The operation name
     */
    public static function record(string $operation): void
    {
        $session_key = self::SESSION_KEY_PREFIX . $operation;
        
        // Initialize session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION[$session_key] = time();
    }

    /**
     * Reset rate limit for an operation (e.g., after successful completion)
     * 
     * @param string $operation The operation name
     */
    public static function reset(string $operation): void
    {
        $session_key = self::SESSION_KEY_PREFIX . $operation;
        
        // Initialize session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[$session_key]);
    }

    /**
     * Get remaining wait time for an operation
     * 
     * @param string $operation The operation name
     * @param int $limit_seconds Time window in seconds
     * @return int Remaining seconds
     */
    public static function getRemainingTime(string $operation, int $limit_seconds = self::DEFAULT_LIMIT): int
    {
        $session_key = self::SESSION_KEY_PREFIX . $operation;
        
        // Initialize session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $last_execution = isset($_SESSION[$session_key]) ? (int)$_SESSION[$session_key] : 0;
        $now = time();
        $elapsed = $now - $last_execution;

        return max(0, $limit_seconds - $elapsed);
    }
}
