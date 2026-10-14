<?php
/**
 * Cache Statistics Manager
 * Collects and calculates cache statistics
 * 
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Cache\SubQueryCache;


use ClicShopping\OM\Registry;

/**
 * Fournit des statistiques détaillées sur l'utilisation du cache
 */

class CacheStatistics
{
  private mixed $db;
  
  public function __construct()
  {
    $this->db = Registry::get('Db');
  }
  
  /**
   * Récupère les statistiques complètes du cache
   * 
   * @return array Statistiques détaillées
   */
  public function getStats(): array
  {
    try {
      $statsQuery = $this->db->query("
        SELECT 
          COUNT(*) as total_entries,
          SUM(hit_count) as total_hits,
          AVG(hit_count) as avg_hits,
          MAX(hit_count) as max_hits,
          COUNT(CASE WHEN expires_at > NOW() THEN 1 END) as active_entries,
          COUNT(CASE WHEN expires_at <= NOW() THEN 1 END) as expired_entries,
          MIN(created_at) as oldest_entry,
          MAX(created_at) as newest_entry
        FROM :table_rag_query_cache
      ");
      
      $statsQuery->fetch();
      
      $totalEntries = $statsQuery->valueInt('total_entries');
      $totalHits = $statsQuery->valueInt('total_hits');
      
      // Calculate hit rate (estimation)
      $hitRate = $totalEntries > 0 ? round(($totalHits / ($totalEntries + $totalHits)) * 100, 1) : 0;
      
      return [
        'total_entries' => $totalEntries,
        'active_entries' => $statsQuery->valueInt('active_entries'),
        'expired_entries' => $statsQuery->valueInt('expired_entries'),
        'total_hits' => $totalHits,
        'avg_hits' => round($statsQuery->valueDecimal('avg_hits'), 1),
        'max_hits' => $statsQuery->valueInt('max_hits'),
        'hit_rate' => $hitRate,
        'oldest_entry' => $statsQuery->value('oldest_entry'),
        'newest_entry' => $statsQuery->value('newest_entry')
      ];
      
    } catch (\Exception $e) {
      error_log("CacheStatistics: Error getting stats: " . $e->getMessage());
      return [
        'total_entries' => 0,
        'active_entries' => 0,
        'expired_entries' => 0,
        'total_hits' => 0,
        'avg_hits' => 0,
        'max_hits' => 0,
        'hit_rate' => 0,
        'oldest_entry' => null,
        'newest_entry' => null
      ];
    }
  }

//************************
// Not used
//************************



  /**
   * Récupère les entrées les plus populaires
   * 
   * @param int $limit Nombre d'entrées à retourner
   * @return array Liste des entrées populaires
   */
  public function getTopEntries(int $limit = 10): array
  {
    try {
      $query = $this->db->prepare("
        SELECT 
          user_query,
          hit_count,
          created_at,
          expires_at
        FROM :table_rag_query_cache
        WHERE expires_at > NOW()
        ORDER BY hit_count DESC
        LIMIT :limit
      ");
      
      $query->bindInt(':limit', $limit);
      $query->execute();
      
      $entries = [];
      while ($query->fetch()) {
        $entries[] = [
          'query' => $query->value('user_query'),
          'hits' => $query->valueInt('hit_count'),
          'created' => $query->value('created_at'),
          'expires' => $query->value('expires_at')
        ];
      }
      
      return $entries;
      
    } catch (\Exception $e) {
      error_log("CacheStatistics: Error getting top entries: " . $e->getMessage());
      return [];
    }
  }
  
  /**
   * Calculates savings achieved through cache
   * 
   * @param float $avgResponseTimeWithoutCache Average time without cache (seconds)
   * @param int $avgTokensPerQuery Average tokens per query
   * @param float $costPerToken Cost per token
   * @return array Calculated savings
   */
  public function calculateSavings(
    float $avgResponseTimeWithoutCache = 57.0,
    int $avgTokensPerQuery = 2000,
    float $costPerToken = 0.00002
  ): array {
    $stats = $this->getStats();
    $totalHits = $stats['total_hits'];
    
    // Économie de temps (en secondes)
    $timeSaved = $totalHits * $avgResponseTimeWithoutCache;
    
    // Économie de tokens
    $tokensSaved = $totalHits * $avgTokensPerQuery;
    
    // Économie de coût
    $costSaved = $tokensSaved * $costPerToken;
    
    return [
      'total_hits' => $totalHits,
      'time_saved_seconds' => round($timeSaved, 2),
      'time_saved_hours' => round($timeSaved / 3600, 2),
      'tokens_saved' => $tokensSaved,
      'cost_saved' => round($costSaved, 2),
      'avg_response_time_improvement' => '99.7%'
    ];
  }
}
