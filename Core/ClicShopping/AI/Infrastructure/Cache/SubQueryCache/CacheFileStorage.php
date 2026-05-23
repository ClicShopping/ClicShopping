<?php
/**
 * File-based Cache Storage
 * Manages disk cache in Work/Cache/Rag directory
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Cache\SubQueryCache;


use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\FileSystem;

/**
 * File-based cache storage in Work/Cache/Rag
 * Used as fallback or complement to database cache
 */

class CacheFileStorage
{
  private string $cachePath;
  private bool $debug;
  private bool $enabled;
  
  public function __construct(bool $debug = false)
  {
    $this->enabled = defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER')   && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True';
    $this->cachePath = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Work/Cache/Rag/';
    $this->debug = $debug;
    
    if ($this->enabled) {
      $this->ensureCacheDirectory();
    }
    
    if ($this->debug && !$this->enabled) {
      error_log("CacheFileStorage: Cache disabled (CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER = False)");
    }
  }
  
  /**
   * Ensure cache directory exists
   * 
   * @return void
   */
  private function ensureCacheDirectory(): void
  {
    if (!is_dir($this->cachePath)) {
      try {
        mkdir($this->cachePath, 0755, true);
        
        if ($this->debug) {
          error_log("CacheFileStorage: Created cache directory: {$this->cachePath}");
        }
      } catch (\Exception $e) {
        error_log("CacheFileStorage: Error creating cache directory: " . $e->getMessage());
      }
    }
  }
  
  /**
   * Generate cache file path
   * 
   * @param string $cacheKey Cache key
   * @return string Full file path
   */
  private function getFilePath(string $cacheKey): string
  {
    $subDir = substr($cacheKey, 0, 2);
    $dirPath = $this->cachePath . $subDir . '/';
    
    if (!is_dir($dirPath)) {
      @mkdir($dirPath, 0755, true);
    }
    
    return $dirPath . $cacheKey . '.cache';
  }
  
  /**
   * Get cache entry from file
   * 
   * @param string $cacheKey Cache key
   * @return array|null Data or null if not found/expired
   */
  public function get(string $cacheKey): ?array
  {
    if (!$this->enabled) {
      if ($this->debug) {
        error_log("CacheFileStorage: Cache disabled, returning null");
      }
      return null;
    }
    
    $filePath = $this->getFilePath($cacheKey);
    
    if (!file_exists($filePath)) {
      if ($this->debug) {
        error_log("CacheFileStorage: MISS - File not found: {$cacheKey}");
      }
      return null;
    }
    
    try {
      $content = file_get_contents($filePath);
      if ($content === false) {
        return null;
      }
      
      $data = json_decode($content, true);
      
      if (isset($data['expires_at']) && time() > $data['expires_at']) {
        @unlink($filePath);
        
        if ($this->debug) {
          error_log("CacheFileStorage: EXPIRED - Removed: {$cacheKey}");
        }
        
        return null;
      }
      
      if (isset($data['hit_count'])) {
        $data['hit_count']++;
        file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE));
      }
      
      if ($this->debug) {
        error_log("CacheFileStorage: HIT - {$cacheKey}");
      }
      
      return [
        'sql' => $data['sql_query'] ?? '',
        'results' => $data['query_results'] ?? [],
        'timestamp' => $data['created_at'] ?? time(),
        'hit_count' => $data['hit_count'] ?? 0,
        'from_file' => true
      ];
      
    } catch (\Exception $e) {
      error_log("CacheFileStorage: Error reading cache: " . $e->getMessage());
      return null;
    }
  }
  
  /**
   * Store cache entry in file
   * 
   * @param string $cacheKey Cache key
   * @param string $userQuery User question
   * @param string $sqlQuery SQL query
   * @param array $results Results
   * @param int $ttl Time to live in seconds
   * @return bool Success
   */
  public function set(string $cacheKey, string $userQuery, string $sqlQuery, array $results, int $ttl): bool
  {
    if (!$this->enabled) {
      if ($this->debug) {
        error_log("CacheFileStorage: Cache disabled, skipping file creation for key {$cacheKey}");
      }
      return false;
    }
    
    $filePath = $this->getFilePath($cacheKey);
    
    try {
      $data = [
        'cache_key' => $cacheKey,
        'user_query' => substr($userQuery, 0, 500),
        'sql_query' => $sqlQuery,
        'query_results' => $results,
        'created_at' => time(),
        'expires_at' => time() + $ttl,
        'hit_count' => 0
      ];
      
      $result = file_put_contents(
        $filePath,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
      );
      
      if ($result !== false) {
        if ($this->debug) {
          error_log("CacheFileStorage: SET - {$cacheKey} (TTL: {$ttl}s)");
        }
        return true;
      }
      
      return false;
      
    } catch (\Exception $e) {
      error_log("CacheFileStorage: Error writing cache: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Delete cache entry
   * 
   * @param string $cacheKey Cache key
   * @return bool Success
   */
  public function delete(string $cacheKey): bool
  {
    if (!$this->enabled) {
      return true;
    }
    
    $filePath = $this->getFilePath($cacheKey);
    
    if (file_exists($filePath)) {
      $result = @unlink($filePath);
      
      if ($this->debug && $result) {
        error_log("CacheFileStorage: DELETE - {$cacheKey}");
      }
      
      return $result;
    }
    
    return true;
  }
  
  /**
   * Flush all file cache
   * 
   * @return bool Success
   */
  public function flush(): bool
  {
    if (!$this->enabled) {
      if ($this->debug) {
        error_log("CacheFileStorage: Cache disabled, nothing to flush");
      }
      return true;
    }
    
    try {
      $deleted = 0;
      
      $dirs = glob($this->cachePath . '*', GLOB_ONLYDIR);
      
      foreach ($dirs as $dir) {
        $files = glob($dir . '/*.cache');
        foreach ($files as $file) {
          if (@unlink($file)) {
            $deleted++;
          }
        }
        @rmdir($dir);
      }
      
      if ($this->debug) {
        error_log("CacheFileStorage: FLUSH - Removed {$deleted} files");
      }
      
      return true;
      
    } catch (\Exception $e) {
      error_log("CacheFileStorage: Error flushing cache: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Clean expired files
   * 
   * @return int Number of deleted files
   */
  public function cleanExpired(): int
  {
    if (!$this->enabled) {
      return 0;
    }
    
    $deleted = 0;
    
    try {
      $dirs = glob($this->cachePath . '*', GLOB_ONLYDIR);
      
      foreach ($dirs as $dir) {
        $files = glob($dir . '/*.cache');
        
        foreach ($files as $file) {
          $content = @file_get_contents($file);
          if ($content === false) {
            continue;
          }
          
          $data = json_decode($content, true);
          
          if (isset($data['expires_at']) && time() > $data['expires_at']) {
            if (@unlink($file)) {
              $deleted++;
            }
          }
        }
      }
      
      if ($this->debug && $deleted > 0) {
        error_log("CacheFileStorage: Cleaned {$deleted} expired files");
      }
      
    } catch (\Exception $e) {
      error_log("CacheFileStorage: Error cleaning expired: " . $e->getMessage());
    }
    
    return $deleted;
  }
  
  /**
   * Get file cache statistics
   * 
   * @return array Statistics
   */
  public function getStats(): array
  {
    if (!$this->enabled) {
      return [
        'enabled' => false,
        'total_files' => 0,
        'active_files' => 0,
        'expired_files' => 0,
        'total_size_bytes' => 0,
        'total_size_mb' => 0,
        'total_hits' => 0,
        'avg_hits' => 0
      ];
    }
    
    $totalFiles = 0;
    $totalSize = 0;
    $expiredFiles = 0;
    $totalHits = 0;
    
    try {
      $dirs = glob($this->cachePath . '*', GLOB_ONLYDIR);
      
      foreach ($dirs as $dir) {
        $files = glob($dir . '/*.cache');
        
        foreach ($files as $file) {
          $totalFiles++;
          $totalSize += filesize($file);
          
          $content = @file_get_contents($file);
          if ($content !== false) {
            $data = json_decode($content, true);
            
            if (isset($data['expires_at']) && time() > $data['expires_at']) {
              $expiredFiles++;
            }
            
            if (isset($data['hit_count'])) {
              $totalHits += $data['hit_count'];
            }
          }
        }
      }
      
    } catch (\Exception $e) {
      error_log("CacheFileStorage: Error getting stats: " . $e->getMessage());
    }
    
    return [
      'enabled' => true,
      'total_files' => $totalFiles,
      'active_files' => $totalFiles - $expiredFiles,
      'expired_files' => $expiredFiles,
      'total_size_bytes' => $totalSize,
      'total_size_mb' => round($totalSize / 1024 / 1024, 2),
      'total_hits' => $totalHits,
      'avg_hits' => $totalFiles > 0 ? round($totalHits / $totalFiles, 1) : 0
    ];
  }
}
