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
                    'message' => CLICSHOPPING::getDef('text_rate_limiter_wait', ['wait' => $wait]),
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
        $key_count = 'ratelimit_count_' . $operation;
        unset($_SESSION[$key_count]);
    }

    /**
     * Check a sliding-window attempt counter (brute-force guard).
     * Prunes timestamps older than $window, then blocks when $max_attempts is reached.
     * Lazily prunes and writes back $_SESSION[$key] as a side effect.
     *
     * @param string $operation Operation name (e.g. 'coupon_submit')
     * @param int $max_attempts Attempts allowed inside the window
     * @param int $window Window length in seconds
     * @return array ['allowed' => bool, 'message' => string, 'wait_seconds' => int]
     */
    public function checkAttempts(string $operation, int $max_attempts, int $window): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $key = 'ratelimit_count_' . $operation;
        $window_seconds = $window;
        $stamps = $this->pruneStamps((array)($_SESSION[$key] ?? []), $window_seconds);
        $_SESSION[$key] = $stamps;

        if ($stamps !== [] && \count($stamps) >= $max_attempts) {
            $wait = $window_seconds - (time() - (int)$stamps[0]);
            return [
                'allowed' => false,
                'message' => CLICSHOPPING::getDef('text_rate_limiter_wait', ['wait' => $wait]),
                'wait_seconds' => $wait,
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'wait_seconds' => 0,
        ];
    }

    /**
     * Record one attempt in the sliding window and prune expired ones.
     *
     * @param string $operation Operation name
     * @param int $window Window length in seconds
     * @return void
     */
    public function recordAttempt(string $operation, int $window): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $key = 'ratelimit_count_' . $operation;
        $stamps = $this->pruneStamps((array)($_SESSION[$key] ?? []), $window);
        $stamps[] = time();
        $_SESSION[$key] = $stamps;
    }

    /**
     * Drop timestamps older than the sliding window. Pure — no session side effect.
     *
     * @param array $stamps List of unix timestamps
     * @param int $window Window length in seconds
     * @return array<int> Kept timestamps, re-indexed
     */
    private function pruneStamps(array $stamps, int $window): array
    {
        $now = time();
        return array_values(array_filter(
            $stamps,
            static fn($t): bool => ($now - (int)$t) < $window
        ));
    }
}
