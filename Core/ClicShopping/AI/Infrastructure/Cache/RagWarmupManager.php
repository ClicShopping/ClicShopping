<?php
/**
 * RAG Warmup Manager
 *
 * Handles cold-cache regeneration with TTL-based re-warm logic.
 */

namespace ClicShopping\AI\Infrastructure\Cache;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Rag\MultiDBRAGManager;
use ClicShopping\AI\Infrastructure\Schema\SchemaRetriever;
use ClicShopping\AI\DomainsAI\WebSearch\Cache\SearchCacheManager;

class RagWarmupManager
{
  private const WARMUP_CACHE_KEY = 'rag_warmup_state';

  private bool $enabled = false;
  private bool $debug = false;
  private int $ttlSeconds = 3600;
  private string $stateFile;
  private ?RagCache $ragCache = null;
  private static bool $warmupRunning = false;

  public function __construct(bool $debug = false)
  {
    $this->enabled = defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True';
    $this->debug = $debug || (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True');

    $this->ttlSeconds = $this->resolveTtl();
    $this->stateFile = CLICSHOPPING::BASE_DIR . 'Work/Cache/Rag/warmup_state.json';

    if ($this->enabled) {
      $this->ragCache = new RagCache(true);
    }
  }

  public function warmupIfNeeded(string $triggerQuery = ''): void
  {
    if (!$this->enabled || self::$warmupRunning) {
      return;
    }

    if (!$this->isWarmupExpired()) {
      if ($this->debug) {
        error_log("[warmup] Cache warm-up skipped (TTL valid)");
      }
      return;
    }

    self::$warmupRunning = true;
    $startTime = microtime(true);

    if ($this->debug) {
      $trigger = $triggerQuery !== '' ? substr($triggerQuery, 0, 80) : 'n/a';
      error_log("[warmup] Starting cold-cache regeneration (trigger: {$trigger})");
    }

    try {
      // Semantic / Hybrid: initialize vector stores
      new MultiDBRAGManager();

      // Analytics / Hybrid: build schema index (cache-aware)
      new SchemaRetriever($this->debug, false);

      // Web search: initialize cache vector store
      new SearchCacheManager();
    } catch (\Exception $e) {
      if ($this->debug) {
        error_log("[warmup] Warm-up failed: " . $e->getMessage());
      }
    }

    $this->persistWarmupTimestamp();

    if ($this->debug) {
      $durationMs = round((microtime(true) - $startTime) * 1000, 2);
      error_log("[warmup] Cold-cache regeneration complete in {$durationMs}ms");
      error_log("[warmup] Warm-up executed (total={$durationMs}ms)");
    }

    self::$warmupRunning = false;
  }

  private function resolveTtl(): int
  {
    if (defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_WARMUP_TTL')) {
      return max(60, (int)CLICSHOPPING_APP_CHATGPT_RA_CACHE_WARMUP_TTL);
    }

    if (defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_TTL')) {
      return max(60, (int)CLICSHOPPING_APP_CHATGPT_RA_CACHE_TTL);
    }

    return 3600;
  }

  private function isWarmupExpired(): bool
  {
    $timestamp = $this->getWarmupTimestamp();

    if ($timestamp === null) {
      return true;
    }

    return (time() - $timestamp) > $this->ttlSeconds;
  }

  private function getWarmupTimestamp(): ?int
  {
    $timestamp = null;

    if ($this->ragCache !== null) {
      $value = $this->ragCache->get(self::WARMUP_CACHE_KEY);
      if (is_array($value) && isset($value['timestamp'])) {
        $timestamp = (int)$value['timestamp'];
      }
    }

    if ($timestamp !== null) {
      return $timestamp;
    }

    if (!file_exists($this->stateFile)) {
      return null;
    }

    $raw = file_get_contents($this->stateFile);
    if ($raw === false || $raw === '') {
      return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['timestamp'])) {
      return null;
    }

    return (int)$data['timestamp'];
  }

  private function persistWarmupTimestamp(): void
  {
    $payload = [
      'timestamp' => time(),
      'ttl' => $this->ttlSeconds
    ];

    if ($this->ragCache !== null) {
      $this->ragCache->set(self::WARMUP_CACHE_KEY, $payload, $this->ttlSeconds);
    }

    $dir = dirname($this->stateFile);
    if (!is_dir($dir)) {
      mkdir($dir, 0775, true);
    }

    file_put_contents($this->stateFile, json_encode($payload));
  }
}
