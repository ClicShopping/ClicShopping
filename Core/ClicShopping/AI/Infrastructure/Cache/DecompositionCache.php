<?php
/**
 * DecompositionCache - Caching for hybrid query decomposition results
 * 
 * Manages caching of query decomposition results to minimize redundant LLM calls.
 * Stores decomposition results (sub-queries) for hybrid queries.
 * 
 * Cache Structure:
 * - Directory: Work/Cache/Rag/Hybrid/
 * - Format: JSON files with md5 hash filenames
 * - TTL: 1 hour (3600 seconds)
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Cache;

use ClicShopping\OM\CLICSHOPPING;

/**
 * DecompositionCache
 * 
 * Caches hybrid query decomposition results to avoid redundant LLM calls.
 * Requirement 7.3: Cache decomposition results for identical queries
 */
class DecompositionCache
{
    /**
     * @var string Directory where cache files are stored
     */
    private string $cacheDir;
    
    /**
     * @var int Lifetime of cache files in seconds (default: 1 hour)
     */
    private int $lifetime;
    
    /**
     * @var bool Whether caching is enabled (from configuration)
     */
    private bool $cacheEnabled;
    
    /**
     * @var bool Enable debug logging
     */
    private bool $debug;
    
    /**
     * DecompositionCache constructor
     * 
     * @param int $lifetime Cache lifetime in seconds (default: 1 hour = 3600s)
     * @param bool $debug Enable debug logging
     */
    public function __construct(int $lifetime = 3600, bool $debug = false)
    {
        $this->cacheEnabled = \defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True';
        $this->cacheDir = CLICSHOPPING::BASE_DIR . 'Work/Cache/Rag/Hybrid/';
        $this->lifetime = $lifetime;
        $this->debug = $debug || (\defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True');
        
        // Check cache configuration
        $this->checkDecompositionCache();
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($concurrentDirectory = $this->cacheDir, 0755, true) && !is_dir($concurrentDirectory)) {
                throw new \RuntimeException(\sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
            
            if ($this->debug) {
                error_log("[DecompositionCache] Created cache directory: {$this->cacheDir}");
            }
        }
    }
    
    /**
     * Check cache configuration and clear cache if disabled
     * 
     * @return bool True if cache is enabled, false otherwise
     */
    public function checkDecompositionCache(): bool
    {
        if ($this->cacheEnabled === false) {
            if ($this->debug) {
                error_log("[DecompositionCache] Cache disabled, clearing cache");
            }
            $this->clearCache();
            return false;
        }
        
        if ($this->debug) {
            error_log("[DecompositionCache] Cache enabled");
        }
        return true;
    }
    
    /**
     * Retrieves a cached decomposition for a given query
     * 
     * Requirement 7.3: Check cache before calling LLM
     * 
     * @param string $query Original query
     * @param array $intent Intent with sub_types
     * @return array|null The decomposition result, or null if not found or expired
     */
    public function getCachedDecomposition(string $query, array $intent): ?array
    {
        if (!$this->cacheEnabled) {
            if ($this->debug) {
                error_log(\sprintf(
                    "[DecompositionCache] Cache DISABLED - query: \"%s\"",
                    \substr($query, 0, 50)
                ));
            }
            return null;
        }
        
        $file = $this->getCacheFile($query, $intent);
        $cacheKey = \basename($file, '.json');
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            
            // Check if the cache is expired
            $age = time() - ($data['timestamp'] ?? 0);
            if ($age < $this->lifetime) {
                // Cache hit
                if ($this->debug) {
                    error_log(\sprintf(
                        "[DecompositionCache] Cache HIT - query: \"%s\", sub_queries: %d, age: %ds/%ds, key: %s",
                        \substr($query, 0, 50),
                        \count($data['sub_queries'] ?? []),
                        $age,
                        $this->lifetime,
                        $cacheKey
                    ));
                }
                
                // Return decomposition result
                return $data['sub_queries'] ?? [];
            }
            
            // If expired, delete the file to trigger a new decomposition
            @unlink($file);
            
            if ($this->debug) {
                error_log(\sprintf(
                    "[DecompositionCache] Cache EXPIRED - query: \"%s\", age: %ds > TTL: %ds, key: %s, file deleted",
                    \substr($query, 0, 50),
                    $age,
                    $this->lifetime,
                    $cacheKey
                ));
            }
        } else {
            // Cache miss
            if ($this->debug) {
                error_log(\sprintf(
                    "[DecompositionCache] Cache MISS - query: \"%s\", key: %s, file: %s",
                    \substr($query, 0, 50),
                    $cacheKey,
                    \basename($file)
                ));
            }
        }
        
        return null;
    }
    
    /**
     * Stores a decomposition result in the cache
     * 
     * Requirement 7.3: Store decomposition results in cache
     * 
     * @param string $query Original query
     * @param array $intent Intent with sub_types
     * @param array $subQueries Decomposition result (sub-queries array)
     */
    public function cacheDecomposition(string $query, array $intent, array $subQueries): void
    {
        if (!$this->cacheEnabled) {
            if ($this->debug) {
                error_log(\sprintf(
                    "[DecompositionCache] Cache DISABLED - not storing decomposition for query: \"%s\"",
                    \substr($query, 0, 50)
                ));
            }
            return;
        }
        
        $file = $this->getCacheFile($query, $intent);
        $cacheKey = \basename($file, '.json');
        
        $data = [
            'query' => $query,
            'intent_type' => $intent['type'] ?? 'hybrid',
            'sub_types' => $intent['sub_types'] ?? [],
            'sub_queries' => $subQueries,
            'timestamp' => time()
        ];
        
        $success = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        
        if ($this->debug) {
            if ($success !== false) {
                error_log(\sprintf(
                    "[DecompositionCache] Cache STORED - query: \"%s\", sub_queries: %d, key: %s, file: %s, size: %d bytes",
                    \substr($query, 0, 50),
                    \count($subQueries),
                    $cacheKey,
                    \basename($file),
                    $success
                ));
            } else {
                error_log(\sprintf(
                    "[DecompositionCache] Cache STORAGE FAILED - query: \"%s\", key: %s, file: %s, error: failed to write file",
                    \substr($query, 0, 50),
                    $cacheKey,
                    \basename($file)
                ));
            }
        }
    }
    
    /**
     * Returns the full path to the cache file for the given query
     * 
     * Requirement 7.3: Create cache key from query hash and intent
     * 
     * @param string $query Original query
     * @param array $intent Intent with sub_types
     * @return string The full path to the cache file
     */
    private function getCacheFile(string $query, array $intent): string
    {
        // Create a unique filename based on query and intent sub_types
        // This ensures cache hits for the same query with same sub_types
        $subTypes = $intent['sub_types'] ?? [];
        sort($subTypes); // Sort to ensure consistent cache key regardless of order
        
        $cacheKey = $query . json_encode($subTypes);
        $hash = md5($cacheKey);
        return "{$this->cacheDir}{$hash}.json";
    }
    
    /**
     * Clears the entire decomposition cache
     * 
     * @return bool True on success, false on failure
     */
    public function clearCache(): bool
    {
        if (!is_dir($this->cacheDir)) {
            if ($this->debug) {
                error_log(\sprintf(
                    "[DecompositionCache] Cache CLEAR - directory does not exist: %s",
                    $this->cacheDir
                ));
            }
            return true; // Nothing to clear
        }
        
        $files = glob("{$this->cacheDir}*.json");
        $success = true;
        $count = 0;
        $failed = 0;
        
        foreach ($files as $file) {
            if (@unlink($file)) {
                $count++;
            } else {
                $success = false;
                $failed++;
            }
        }
        
        if ($this->debug) {
            error_log(\sprintf(
                "[DecompositionCache] Cache CLEARED - directory: %s, files_deleted: %d, files_failed: %d, success: %s",
                $this->cacheDir,
                $count,
                $failed,
                $success ? 'true' : 'false'
            ));
        }
        
        return $success;
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Statistics about the cache
     */
    public function getStatistics(): array
    {
        if (!is_dir($this->cacheDir)) {
            return [
                'enabled' => $this->cacheEnabled,
                'directory' => $this->cacheDir,
                'file_count' => 0,
                'total_size' => 0,
                'oldest_file' => null,
                'newest_file' => null
            ];
        }
        
        $files = glob("{$this->cacheDir}*.json");
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
            'enabled' => $this->cacheEnabled,
            'directory' => $this->cacheDir,
            'file_count' => \count($files),
            'total_size' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'oldest_file' => $oldestTime < PHP_INT_MAX ? date('Y-m-d H:i:s', $oldestTime) : null,
            'newest_file' => $newestTime > 0 ? date('Y-m-d H:i:s', $newestTime) : null,
            'lifetime' => $this->lifetime,
            'lifetime_hours' => round($this->lifetime / 3600, 1)
        ];
    }
}
