<?php
/**
 * Cache Storage Manager
 * Manages cache data storage and retrieval
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Cache\SubQueryCache;


use ClicShopping\OM\Registry;

/**
 * Manages CRUD operations on cache table
 */

class CacheStorage
{
  private mixed $db;
  private bool $debug;
  
  public function __construct(bool $debug = false)
  {
    $this->db = Registry::get('Db');
    $this->debug = $debug;
  }
  
  /**
   * Get cache entry with interpretation
   * 
   * @param string $cacheKey Cache key
   * @return array|null Data or null if not found
   */
  public function get(string $cacheKey): ?array
  {
    try {
      $query = $this->db->prepare("SELECT  sql_query,
                                          query_results,
                                          interpretation,
                                          entity_id,
                                          entity_type,
                                          created_at,
                                          hit_count
                                  FROM :table_rag_query_cache
                                  WHERE cache_key = :cache_key
                                  AND expires_at > NOW()
                                  LIMIT 1
                                ");
      
      $query->bindValue(':cache_key', $cacheKey);
      $query->execute();
      
      if ($query->fetch()) {
        $results = json_decode($query->value('query_results'), true);
        
        if ($this->debug) {
          $hasInterpretation = $query->value('interpretation') !== null ? 'yes' : 'no';
          error_log("CacheStorage: GET HIT for key {$cacheKey} (has_interpretation: {$hasInterpretation})");
        }
        
        return [
          'sql_query' => $query->value('sql_query'),
          'results' => $results,
          'result_count' => is_array($results) ? count($results) : 0,
          'interpretation' => $query->value('interpretation'),
          'entity_id' => $query->valueInt('entity_id'),
          'entity_type' => $query->value('entity_type'),
          'created_at' => $query->value('created_at'),
          'hit_count' => $query->valueInt('hit_count'),
          'from_cache' => true
        ];
      }
      
      if ($this->debug) {
        error_log("CacheStorage: GET MISS for key {$cacheKey}");
      }
      
      return null;
      
    } catch (\Exception $e) {
      error_log("CacheStorage: Error getting cache: " . $e->getMessage());
      return null;
    }
  }
  
  /**
   * Store cache entry with interpretation
   * 
   * @param string $cacheKey Cache key
   * @param string $userQuery User question
   * @param string $sqlQuery SQL query
   * @param array $results Results
   * @param int $ttl Time to live in seconds
   * @param string|null $interpretation Natural language interpretation
   * @param int|null $entityId Entity ID
   * @param string|null $entityType Entity type
   * @return bool Success
   */
  public function set(
    string $cacheKey, 
    string $userQuery, 
    string $sqlQuery, 
    array $results, 
    int $ttl,
    ?string $interpretation = null,
    ?int $entityId = null,
    ?string $entityType = null
  ): bool
  {
    try {
      $query = $this->db->prepare("
        INSERT INTO :table_rag_query_cache
        (cache_key, user_query, sql_query, query_results, interpretation, entity_id, entity_type, created_at, expires_at, hit_count)
        VALUES
        (:cache_key, :user_query, :sql_query, :query_results, :interpretation, :entity_id, :entity_type, NOW(), DATE_ADD(NOW(), INTERVAL :ttl SECOND), 0)
        ON DUPLICATE KEY UPDATE
        sql_query = VALUES(sql_query),
        query_results = VALUES(query_results),
        interpretation = VALUES(interpretation),
        entity_id = VALUES(entity_id),
        entity_type = VALUES(entity_type),
        created_at = NOW(),
        expires_at = DATE_ADD(NOW(), INTERVAL :ttl SECOND),
        hit_count = 0
      ");
      
      $query->bindValue(':cache_key', $cacheKey);
      $query->bindValue(':user_query', substr($userQuery, 0, 500));
      $query->bindValue(':sql_query', $sqlQuery);
      $query->bindValue(':query_results', json_encode($results, JSON_UNESCAPED_UNICODE));
      $query->bindValue(':interpretation', $interpretation);
      $query->bindValue(':entity_id', $entityId);
      $query->bindValue(':entity_type', $entityType);
      $query->bindInt(':ttl', $ttl);
      
      $query->execute();
      
      if ($this->debug) {
        $hasInterpretation = $interpretation !== null ? 'yes' : 'no';
        error_log("CacheStorage: SET for key {$cacheKey} (TTL: {$ttl}s, has_interpretation: {$hasInterpretation})");
      }
      
      return true;
      
    } catch (\Exception $e) {
      error_log("CacheStorage: Error setting cache: " . $e->getMessage());
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
    try {
      $query = $this->db->prepare("
        DELETE FROM :table_rag_query_cache
        WHERE cache_key = :cache_key
      ");
      
      $query->bindValue(':cache_key', $cacheKey);
      $query->execute();
      
      if ($this->debug) {
        error_log("CacheStorage: DELETE for key {$cacheKey}");
      }
      
      return true;
      
    } catch (\Exception $e) {
      error_log("CacheStorage: Error deleting cache: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Flush all cache
   * 
   * @return bool Success
   */
  public function flush(): bool
  {
    try {
      $this->db->query("TRUNCATE TABLE :table_rag_query_cache");
      
      if ($this->debug) {
        error_log("CacheStorage: FLUSH - All cache cleared");
      }
      
      return true;
    } catch (\Exception $e) {
      error_log("CacheStorage: Error flushing cache: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Increment hit counter
   * 
   * @param string $cacheKey Cache key
   * @return void
   */
  public function incrementHitCount(string $cacheKey): void
  {
    try {
      $this->db->query("UPDATE :table_rag_query_cache
                        SET hit_count = hit_count + 1
                        WHERE cache_key = '{$cacheKey}'
                      ");
    } catch (\Exception $e) {
      error_log("CacheStorage: Error incrementing hit count: " . $e->getMessage());
    }
  }
}
