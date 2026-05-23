<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Cache;


use ClicShopping\OM\CLICSHOPPING;

/**
 * Manages caching of translations to minimize redundant calls to the translation API.
 */

class TranslationCache
{
  /**
   * @var string Directory where cache files are stored.
   */
  private string $cacheDir;

  /**
   * @var int Lifetime of cache files in seconds (e.g., 30 days).
   */
  private int $lifetime;

  /**
   * @var bool Cache enabled flag.
   */
  private bool $cache;

  /**
   * TranslationCache constructor.
   *
   * @param int $lifetime Cache lifetime in seconds.
   */
  public function __construct(int $lifetime = 2592000)
  {
    $this->cache = defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True';
    $this->cacheDir = CLICSHOPPING::BASE_DIR . 'Work/Cache/Rag/Translation/';
    $this->lifetime = $lifetime;
    $this->checkTranslationCache();

    if (!is_dir($this->cacheDir)) {
      if (!mkdir($concurrentDirectory = $this->cacheDir, 0755, true) && !is_dir($concurrentDirectory)) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
      }
    }
  }

  public function checkTranslationCache() : bool
  {
    if ($this->cache === false) {
      $this->clearCache();
      return false;
    } else {
      return true;
    }
  }

  /**
   * Retrieves a cached translation for a given original text and language ID.
   *
   * @param string $originalText The text to translate.
   * @param int $languageId The ID of the language.
   * @return string|null The translated text, or null if not found or expired.
   */
  public function getCachedTranslation(string $originalText, int $languageId): ?string
  {
    $file = $this->getCacheFile($originalText, $languageId);
    if (file_exists($file)) {
      $data = json_decode(file_get_contents($file), true);

      // Check if the cache is expired
      if ((time() - ($data['timestamp'] ?? 0)) < $this->lifetime) {
        return $data['translated_text'] ?? null;
      }

      // If expired, delete the file to trigger a new translation
      @unlink($file);
    }

    return null;
  }

  /**
   * Stores a translation in the cache.
   *
   * @param string $originalText The original text.
   * @param string $translatedText The translated text.
   * @param int $languageId The language ID.
   */
  public function cacheTranslation(string $originalText, string $translatedText, int $languageId): void
  {
    $file = $this->getCacheFile($originalText, $languageId);
    $data = [
      'original_text' => $originalText,
      'translated_text' => $translatedText,
      'timestamp' => time()
    ];

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
  }

  /**
   * Returns the full path to the cache file for the given key.
   *
   * @param string $originalText The original text.
   * @param int $languageId The language ID.
   * @return string The full path to the cache file.
   */
  private function getCacheFile(string $originalText, int $languageId): string
  {
    // Create a unique filename based on the text and language
    $hash = md5($originalText . $languageId);
    return $this->cacheDir . $hash . '.json';
  }

  /**
   * Clears the entire translation cache.
   *
   * @return bool True on success, false on failure.
   */
  public function clearCache(): bool
  {
    $files = glob($this->cacheDir . '*.json');
    $success = true;

    foreach ($files as $file) {
      if (!@unlink($file)) {
        $success = false;
      }
    }

    return $success;
  }

  /**
   * Get cache statistics.
   *
   * @return array Statistics about the cache.
   */
  public function getStatistics(): array
  {
    if (!is_dir($this->cacheDir)) {
      return [
        'enabled' => $this->cache,
        'directory' => $this->cacheDir,
        'file_count' => 0,
        'total_size' => 0,
        'oldest_file' => null,
        'newest_file' => null
      ];
    }

    $files = glob($this->cacheDir . '*.json');
    $totalSize = 0;
    $oldestTime = PHP_INT_MAX;
    $newestTime = 0;

    foreach ($files as $file) {
      $totalSize += filesize($file);
      $mtime = filemtime($file);
      $oldestTime = min($oldestTime, $mtime);
      $newestTime = max($newestTime, $mtime);
    }

    return [
      'enabled' => $this->cache,
      'directory' => $this->cacheDir,
      'file_count' => count($files),
      'total_size' => $totalSize,
      'total_size_mb' => round($totalSize / 1024 / 1024, 2),
      'oldest_file' => $oldestTime < PHP_INT_MAX ? date('Y-m-d H:i:s', $oldestTime) : null,
      'newest_file' => $newestTime > 0 ? date('Y-m-d H:i:s', $newestTime) : null,
      'lifetime' => $this->lifetime,
      'lifetime_days' => round($this->lifetime / 86400, 1)
    ];
  }
}
