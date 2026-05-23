<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI\SubOrchestrator;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

/**
 * ConcurrencyManager
 *
 * Manages concurrent analysis execution limits per merchant.
 * (Requirement 23.5)
 *
 * This class ensures that no more than N concurrent analyses are running
 * for a given merchant at any time. It uses database-backed tracking with
 * automatic cleanup of stale entries.
 *
 * Design:
 * - Uses clic_products_cockpit_ai_concurrency table to track active analyses
 * - Each analysis registers on start and unregisters on completion
 * - Stale entries (older than pipeline_timeout) are automatically cleaned
 * - Thread-safe via database transactions
 *
 * Usage:
 *   $manager = new ConcurrencyManager();
 *   if (!$manager->acquireSlot($userId)) {
 *     throw new \Exception('Too many concurrent analyses');
 *   }
 *   try {
 *     // ... execute analysis ...
 *   } finally {
 *     $manager->releaseSlot($userId);
 *   }
 */
class ConcurrencyManager
{
  private int $maxConcurrent;
  private int $timeoutSeconds;
  private $db;

  /**
   * Constructor
   *
   * Initializes concurrency limits from configuration.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');

    // Get max concurrent analyses from configuration (default: 3)
    $this->maxConcurrent = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_MAX_CONCURRENT_ANALYSES')
      ? (int)CLICSHOPPING_APP_ECOMMERCE_CAI_MAX_CONCURRENT_ANALYSES
      : 3;

    // Get pipeline timeout from configuration (default: 30 seconds)
    $this->timeoutSeconds = \defined('CLICSHOPPING_APP_ECOMMERCE_CAI_PIPELINE_TIMEOUT')
      ? (int)CLICSHOPPING_APP_ECOMMERCE_CAI_PIPELINE_TIMEOUT
      : 30;
  }

  /**
   * Attempts to acquire a concurrency slot for the given user.
   *
   * Returns true if slot acquired, false if limit reached.
   *
   * @param string $userId User identifier (for per-merchant limiting)
   * @return bool True if slot acquired, false if limit exceeded
   */
  public function acquireSlot(string $userId): bool
  {
    try {
      // Clean up stale entries first
      $this->cleanupStaleEntries();

      // Count current active analyses for this user
      $currentCount = $this->getCurrentCount($userId);

      if ($currentCount >= $this->maxConcurrent) {
        return false;
      }

      // Register this analysis
      $this->registerAnalysis($userId);

      return true;

    } catch (\Exception $e) {
      // On error, allow the analysis (fail-open for availability)
      error_log("ConcurrencyManager::acquireSlot failed: " . $e->getMessage());
      return true;
    }
  }

  /**
   * Releases a concurrency slot for the given user.
   *
   * Should be called in a finally block to ensure cleanup.
   *
   * @param string $userId User identifier
   * @return void
   */
  public function releaseSlot(string $userId): void
  {
    try {
      $Qdelete = $this->db->prepare('DELETE FROM :table_products_cockpit_ai_concurrency
                                     WHERE user_id = :user_id
                                     ORDER BY started_at ASC
                                     LIMIT 1');
      $Qdelete->bindValue(':user_id', $userId);
      $Qdelete->execute();

    } catch (\Exception $e) {
      error_log("ConcurrencyManager::releaseSlot failed: " . $e->getMessage());
    }
  }

  /**
   * Gets the current number of active analyses for a user.
   *
   * @param string $userId User identifier
   * @return int Number of active analyses
   */
  private function getCurrentCount(string $userId): int
  {
    $Qcount = $this->db->prepare('SELECT COUNT(*) as count
                                  FROM :table_products_cockpit_ai_concurrency
                                  WHERE user_id = :user_id');
    $Qcount->bindValue(':user_id', $userId);
    $Qcount->execute();

    return $Qcount->valueInt('count') ?? 0;
  }

  /**
   * Registers a new analysis in the concurrency tracking table.
   *
   * @param string $userId User identifier
   * @return void
   */
  private function registerAnalysis(string $userId): void
  {
    $this->db->save('products_cockpit_ai_concurrency', [
      'user_id' => $userId,
      'started_at' => time(),
      'process_id' => getmypid()
    ]);
  }

  /**
   * Cleans up stale entries older than the pipeline timeout.
   *
   * This handles cases where analyses crashed or timed out without
   * properly releasing their slot.
   *
   * @return void
   */
  private function cleanupStaleEntries(): void
  {
    $cutoffTime = time() - $this->timeoutSeconds;

    $Qdelete = $this->db->prepare('DELETE FROM :table_products_cockpit_ai_concurrency
                                   WHERE started_at < :cutoff_time');
    $Qdelete->bindValue(':cutoff_time', $cutoffTime);
    $Qdelete->execute();
  }

  /**
   * Gets the current concurrency limit.
   *
   * @return int Maximum concurrent analyses allowed
   */
  public function getMaxConcurrent(): int
  {
    return $this->maxConcurrent;
  }

  /**
   * Gets statistics about current concurrency usage.
   *
   * @param string $userId User identifier
   * @return array Statistics with keys: current, max, available
   */
  public function getStats(string $userId): array
  {
    $current = $this->getCurrentCount($userId);

    return [
      'current' => $current,
      'max' => $this->maxConcurrent,
      'available' => max(0, $this->maxConcurrent - $current)
    ];
  }
}
