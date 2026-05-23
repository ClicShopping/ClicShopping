<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Orchestrator\SubReputation;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Manages caching of reputation scores to minimize database queries and improve performance.
 * 
 * This cache stores reputation scores with a 5-minute TTL to balance freshness with performance.
 * The cache automatically invalidates on reputation updates and supports batch warming for
 * frequently accessed critics.
 * 
 * Cache Structure:
 * - Backend: Symfony FilesystemAdapter
 * - TTL: 5 minutes (300 seconds)
 * - Key Format: reputation_{critic_id}
 * - Namespace: reputation_cache
 * 
 * Performance Characteristics:
 * - Cache Hit: ~1ms
 * - Cache Miss: ~50ms (database query)
 * - Batch Warming: ~100ms for 10 critics
 * 
 * @package ClicShopping\AI\CoreAI\Orchestrator\SubReputation
 * @since 2026-02-04
 */
class ReputationCache
{
  /**
   * @var FilesystemAdapter Symfony cache adapter
   */
  private FilesystemAdapter $cache;

  /**
   * @var int Cache TTL in seconds (5 minutes)
   */
  private int $ttl = 300;

  /**
   * @var bool Enable debug logging
   */
  private bool $debug;

  /**
   * @var string Cache directory path
   */
  private string $cacheDir;

  /**
   * @var ReputationStore Reputation data store
   */
  private ReputationStore $reputationStore;

  /**
   * @var array Cache statistics
   */
  private array $stats = [
    'hits' => 0,
    'misses' => 0,
    'sets' => 0,
    'invalidations' => 0,
    'batch_warms' => 0
  ];

  /**
   * ReputationCache constructor.
   *
   * @param ReputationStore|null $reputationStore Reputation data store (optional, will be created if null)
   * @param bool $debug Enable debug logging
   */
  public function __construct(?ReputationStore $reputationStore = null, bool $debug = false)
  {
    $this->debug = $debug || (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True');

    // Set cache directory
    $this->cacheDir = CLICSHOPPING::BASE_DIR . 'Work/Cache/Rag/Reputation/';

    // Create cache directory if it doesn't exist
    if (!is_dir($this->cacheDir)) {
      if (!mkdir($concurrentDirectory = $this->cacheDir, 0755, true) && !is_dir($concurrentDirectory)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
      }
      
      if ($this->debug) {
        error_log("[ReputationCache] Created cache directory: {$this->cacheDir}");
      }
    }

    // Initialize Symfony cache adapter
    $this->cache = new FilesystemAdapter(
      namespace: 'reputation_cache',
      defaultLifetime: $this->ttl,
      directory: $this->cacheDir
    );

    // Initialize reputation store
    $this->reputationStore = $reputationStore ?? new ReputationStore($this->debug);

    if ($this->debug) {
      error_log("[ReputationCache] Initialized with TTL: {$this->ttl}s, Directory: {$this->cacheDir}");
    }
  }

  /**
   * Invalidate cache entry for a specific critic.
   *
   * @param string $criticId Critic identifier
   * @return bool True on success, false on failure
   */
  public function invalidate(string $criticId): bool
  {
    try {
      $cacheKey = $this->getCacheKey($criticId);
      $success = $this->cache->deleteItem($cacheKey);

      if ($success) {
        $this->stats['invalidations']++;

        if ($this->debug) {
          error_log("[ReputationCache] Invalidated cache for critic: {$criticId}");
        }
      }

      return $success;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[ReputationCache] Error invalidating cache for {$criticId}: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Generate cache key for a critic.
   *
   * @param string $criticId Critic identifier
   * @return string Cache key
   */
  private function getCacheKey(string $criticId): string
  {
    return 'reputation_' . $criticId;
  }

  /**
   * Batch warm cache for multiple critics.
   *
   * This method pre-loads reputation scores for multiple critics into the cache,
   * reducing database queries for frequently accessed critics.
   *
   * @param array $criticIds Array of critic identifiers
   * @return array Map of critic_id => reputation score (only successful loads)
   */
  public function batchWarm(array $criticIds): array
  {
    $results = [];
    $startTime = microtime(true);

    try {
      if (empty($criticIds)) {
        return $results;
      }

      if ($this->debug) {
        error_log(sprintf(
          "[ReputationCache] Batch warming cache for %d critics: %s",
          count($criticIds),
          implode(', ', array_slice($criticIds, 0, 5)) . (count($criticIds) > 5 ? '...' : '')
        ));
      }

      // Fetch all reputations from database in one operation
      $reputations = $this->reputationStore->getMultipleReputations($criticIds);

      // Cache each reputation
      foreach ($reputations as $criticId => $reputationScore) {
        if ($reputationScore !== null) {
          $this->set($criticId, $reputationScore->reputationScore);
          $results[$criticId] = $reputationScore->reputationScore;
        }
      }

      $this->stats['batch_warms']++;
      $duration = (microtime(true) - $startTime) * 1000;

      if ($this->debug) {
        error_log(sprintf(
          "[ReputationCache] Batch warm complete: %d/%d critics cached in %.2fms",
          count($results),
          count($criticIds),
          $duration
        ));
      }

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[ReputationCache] Error during batch warm: " . $e->getMessage());
      }
    }

    return $results;
  }

  /**
   * Set reputation score in cache.
   *
   * @param string $criticId Critic identifier
   * @param float $reputation Reputation score (0.5-1.0)
   * @return bool True on success, false on failure
   * @throws \InvalidArgumentException If reputation is out of bounds
   */
  public function set(string $criticId, float $reputation): bool
  {
    // Validate reputation bounds (throw exception before try block)
    if ($reputation < 0.5 || $reputation > 1.0) {
      throw new \InvalidArgumentException(
        "Reputation must be between 0.5 and 1.0, got: {$reputation}"
      );
    }

    try {

      $cacheKey = $this->getCacheKey($criticId);

      // Get cache item
      $item = $this->cache->getItem($cacheKey);
      $item->set($reputation);
      $item->expiresAfter($this->ttl);

      // Save to cache
      $success = $this->cache->save($item);

      if ($success) {
        $this->stats['sets']++;

        if ($this->debug) {
          error_log(sprintf(
            "[ReputationCache] Cached reputation: critic=%s, reputation=%.3f, TTL=%ds",
            $criticId,
            $reputation,
            $this->ttl
          ));
        }
      }

      return $success;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[ReputationCache] Error setting reputation for {$criticId}: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Get multiple reputation scores from cache or database.
   *
   * @param array $criticIds Array of critic identifiers
   * @return array Map of critic_id => reputation score
   */
  public function getMultiple(array $criticIds): array
  {
    $results = [];

    foreach ($criticIds as $criticId) {
      $reputation = $this->get($criticId);
      if ($reputation !== null) {
        $results[$criticId] = $reputation;
      }
    }

    return $results;
  }

  /**
   * Get reputation score from cache or database.
   *
   * @param string $criticId Critic identifier
   * @return float|null Reputation score (0.5-1.0) or null if not found
   */
  public function get(string $criticId): ?float
  {
    try {
      $cacheKey = $this->getCacheKey($criticId);

      // Try to get from cache
      $reputation = $this->cache->get($cacheKey, function (ItemInterface $item) use ($criticId) {
        // Cache miss - fetch from database
        $this->stats['misses']++;

        if ($this->debug) {
          error_log("[ReputationCache] Cache MISS for critic: {$criticId}");
        }

        // Set TTL for this item
        $item->expiresAfter($this->ttl);

        // Fetch from database
        $reputationScore = $this->reputationStore->getReputation($criticId);

        if ($reputationScore !== null) {
          if ($this->debug) {
            error_log(sprintf(
              "[ReputationCache] Loaded from database: critic=%s, reputation=%.3f",
              $criticId,
              $reputationScore->reputationScore
            ));
          }
          return $reputationScore->reputationScore;
        }

        // Not found in database - return null (will not be cached)
        return null;
      });

      if ($reputation !== null) {
        $this->stats['hits']++;

        if ($this->debug) {
          error_log(sprintf(
            "[ReputationCache] Cache HIT for critic: %s, reputation=%.3f",
            $criticId,
            $reputation
          ));
        }
      }

      return $reputation;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[ReputationCache] Error getting reputation for {$criticId}: " . $e->getMessage());
      }

      // Fall back to database on cache error
      $reputationScore = $this->reputationStore->getReputation($criticId);
      return $reputationScore?->reputationScore;
    }
  }

  /**
   * Clear all reputation cache entries.
   *
   * @return bool True on success, false on failure
   */
  public function clear(): bool
  {
    try {
      $success = $this->cache->clear();

      if ($success && $this->debug) {
        error_log("[ReputationCache] Cleared all cache entries");
      }

      return $success;

    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[ReputationCache] Error clearing cache: " . $e->getMessage());
      }
      return false;
    }
  }

  /**
   * Get cache statistics.
   *
   * @return array Cache statistics
   */
  public function getStatistics(): array
  {
    $totalRequests = $this->stats['hits'] + $this->stats['misses'];
    $hitRate = $totalRequests > 0 ? ($this->stats['hits'] / $totalRequests) * 100 : 0;

    return [
      'hits' => $this->stats['hits'],
      'misses' => $this->stats['misses'],
      'sets' => $this->stats['sets'],
      'invalidations' => $this->stats['invalidations'],
      'batch_warms' => $this->stats['batch_warms'],
      'total_requests' => $totalRequests,
      'hit_rate' => round($hitRate, 2),
      'ttl' => $this->ttl,
      'cache_dir' => $this->cacheDir
    ];
  }

  /**
   * Get cache TTL in seconds.
   *
   * @return int TTL in seconds
   */
  public function getTTL(): int
  {
    return $this->ttl;
  }

  /**
   * Set cache TTL in seconds.
   *
   * @param int $ttl TTL in seconds
   * @return void
   */
  public function setTTL(int $ttl): void
  {
    if ($ttl < 0) {
      throw new \InvalidArgumentException("TTL must be non-negative, got: {$ttl}");
    }

    $this->ttl = $ttl;

    if ($this->debug) {
      error_log("[ReputationCache] TTL updated to: {$ttl}s");
    }
  }
}
