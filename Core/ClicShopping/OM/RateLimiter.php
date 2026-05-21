<?php
/**
 * Rate Limiter - Session-based rate limiting for heavy operations
 *
 * Prevents abuse of resource-intensive operations by tracking
 * timestamps in the user session.
 *
 * @package ClicShopping\OM
 */

namespace ClicShopping\OM;

class RateLimiter
{
    /**
     * Time windows per operation type (seconds)
     * Defined at instantiation to allow per-context configuration.
     */
    private array $windows;

    /**
     * Constructor.
     *
     * @param array $default_windows Associative array of operation => seconds.
     */
    public function __construct(array $default_windows = [])
    {
        $this->windows = $default_windows;
    }

    /**
     * Check if an operation is allowed within the rate limit window.
     *
     * @param string $operation The operation name (e.g., 'import_data', 'crawler')
     * @return array ['allowed' => bool, 'message' => string, 'wait_seconds' => int]
     */
    public function check(string $operation): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $window = $this->windows[$operation] ?? 60;
        $key = 'ratelimit_' . $operation;

        if (isset($_SESSION[$key])) {
            $elapsed = time() - $_SESSION[$key];
            if ($elapsed < $window) {
                $wait = $window - $elapsed;
                return [
                    'allowed' => false,
                    'message' => sprintf('Veuillez attendre %d secondes avant de relancer cette opération.', $wait),
                    'wait_seconds' => $wait,
                ];
            }
        }

        return [
            'allowed' => true,
            'message' => '',
            'wait_seconds' => 0,
        ];
    }

    /**
     * Record that an operation was completed. Starts the rate limit window.
     *
     * @param string $operation The operation name
     * @return void
     */
    public function record(string $operation): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $key = 'ratelimit_' . $operation;
        $_SESSION[$key] = time();
    }

    /**
     * Reset the rate limit for an operation (useful for admin override).
     *
     * @param string $operation The operation name
     * @return void
     */
    public function reset(string $operation): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $key = 'ratelimit_' . $operation;
        unset($_SESSION[$key]);
    }
}
