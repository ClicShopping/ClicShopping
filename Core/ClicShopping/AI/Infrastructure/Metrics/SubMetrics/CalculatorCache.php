<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Metrics\SubMetrics;

use ClicShopping\AI\Security\SecurityLogger;

/**
 * CalculatorCache
 *
 * DB-backed result cache for CalculatorTool. Extracted verbatim from
 * CalculatorTool (2026-06-20) — owns only the cache concern (hash, get, set,
 * clean, clear, stats). Dependencies injected by CalculatorTool.
 */
class CalculatorCache
{
  private mixed $db;
  private SecurityLogger $securityLogger;
  private bool $debug;
  private int $cacheTTL;

  public function __construct(mixed $db, SecurityLogger $securityLogger, bool $debug, int $cacheTTL)
  {
    $this->db = $db;
    $this->securityLogger = $securityLogger;
    $this->debug = $debug;
    $this->cacheTTL = $cacheTTL;
  }

  /**
   * Generate cache hash
   * 
   * @param string $expression Expression
   * @param array $variables Variables
   * @return string Hash
   */
  private function generateCacheHash(string $expression, array $variables): string
  {
    $data = $expression . json_encode($variables, JSON_UNESCAPED_UNICODE);
    return hash('sha256', $data);
  }

  /**
   * Get cached result
   * 
   * @param string $expression Expression
   * @param array $variables Variables
   * @return array|null Cached result or null
   */
  public function getCachedResult(string $expression, array $variables): ?array
  {
    if (!$this->db) {
      return null;
    }

    try {
      $hash = $this->generateCacheHash($expression, $variables);

      $sql = "SELECT * FROM :table_rag_calculator_cache 
              WHERE expression_hash = :hash 
              AND created_at > DATE_SUB(NOW(), INTERVAL :ttl SECOND)
              LIMIT 1";

      $stmt = $this->db->prepare($sql);
      $stmt->execute([
        'hash' => $hash,
        'ttl' => $this->cacheTTL
      ]);

      $row = $stmt->fetch(\PDO::FETCH_ASSOC);

      if ($row) {
        $updateSql = "UPDATE :table_rag_calculator_cache 
                      SET last_accessed = NOW(), 
                          access_count = access_count + 1 
                      WHERE cache_id = :id";
        $updateStmt = $this->db->prepare($updateSql);
        $updateStmt->execute(['id' => $row['cache_id']]);

        return [
          'success' => true,
          'result' => (float)$row['result'],
          'expression' => $expression,
          'prepared_expression' => $expression,
          'execution_time' => (float)$row['execution_time'],
          'type' => $row['result_type'],
          'from_cache' => true,
          'cache_age' => time() - strtotime($row['created_at']),
          'access_count' => (int)$row['access_count'] + 1,
        ];
      }

      return null;
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Cache retrieval error: " . $e->getMessage(),
          'error'
        );
      }
      return null;
    }
  }

  /**
   * Cache result
   * 
   * @param string $expression Expression
   * @param array $variables Variables
   * @param array $result Result
   * @return bool Success
   */
  public function cacheResult(string $expression, array $variables, array $result): bool
  {
    if (!$this->db) {
      return false;
    }

    try {
      $hash = $this->generateCacheHash($expression, $variables);

      $sql = "INSERT INTO :table_rag_calculator_cache 
              (expression, expression_hash, result, result_type, variables, 
               execution_time, created_at, last_accessed, access_count) 
              VALUES 
              (:expression, :hash, :result, :type, :variables, 
               :exec_time, NOW(), NOW(), 0)
              ON DUPLICATE KEY UPDATE 
                result = VALUES(result),
                result_type = VALUES(result_type),
                execution_time = VALUES(execution_time),
                last_accessed = NOW(),
                access_count = access_count + 1";

      $stmt = $this->db->prepare($sql);

      return $stmt->execute([
        'expression' => substr($expression, 0, 500),
        'hash' => $hash,
        'result' => $result['result'],
        'type' => $result['type'],
        'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE),
        'exec_time' => $result['execution_time'],
      ]);
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Cache storage error: " . $e->getMessage(),
          'error'
        );
      }
      return false;
    }
  }

  /**
   * Clean expired cache
   * 
   * @return int Number of deleted entries
   */
  public function cleanCache(): int
  {
    if (!$this->db) {
      return 0;
    }

    try {
      $sql = "DELETE FROM :table_rag_calculator_cache 
              WHERE created_at < DATE_SUB(NOW(), INTERVAL :ttl SECOND)";

      $stmt = $this->db->prepare($sql);
      $stmt->execute(['ttl' => $this->cacheTTL]);

      $deleted = $stmt->rowCount();

      if ($this->debug && $deleted > 0) {
        $this->securityLogger->logSecurityEvent(
          "Cleaned {$deleted} expired cache entries",
          'info'
        );
      }

      return $deleted;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Cache cleaning error: " . $e->getMessage(),
        'error'
      );
      return 0;
    }
  }

  /**
   * Clear all cache
   * 
   * @return bool Success
   */
  public function clearCache(): bool
  {
    if (!$this->db) {
      return false;
    }

    try {
      $this->db->exec("TRUNCATE TABLE calculator_cache");

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Calculator cache cleared",
          'info'
        );
      }

      return true;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Cache clearing error: " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }

  /**
   * Get cache statistics
   * 
   * @return array Cache statistics
   */
  public function getCacheStats(): array
  {
    if (!$this->db) {
      return ['enabled' => false];
    }

    try {
      $stats = [];

      $stmt = $this->db->query("SELECT COUNT(*) as total FROM :table_rag_calculator_cache");
      $stats['total_entries'] = (int)$stmt->fetchColumn();

      $sql = "SELECT COUNT(*) as valid FROM :table_rag_calculator_cache 
              WHERE created_at > DATE_SUB(NOW(), INTERVAL :ttl SECOND)";
      $stmt = $this->db->prepare($sql);
      $stmt->execute(['ttl' => $this->cacheTTL]);
      $stats['valid_entries'] = (int)$stmt->fetchColumn();

      $stmt = $this->db->query("SELECT SUM(access_count) as total FROM :table_rag_calculator_cache");
      $stats['total_accesses'] = (int)$stmt->fetchColumn();

      $stmt = $this->db->query(
        "SELECT expression, 
                access_count 
         FROM :table_rag_calculator_cache 
         ORDER BY access_count DESC 
         LIMIT 1"
      );
      $popular = $stmt->fetch(\PDO::FETCH_ASSOC);
      $stats['most_popular'] = $popular ? [
        'expression' => $popular['expression'],
        'accesses' => (int)$popular['access_count']
      ] : null;

      if ($stats['total_accesses'] > 0) {
        $stats['hit_rate'] = round(
          ($stats['total_accesses'] / ($stats['total_entries'] + $stats['total_accesses'])) * 100,
          2
        );
      } else {
        $stats['hit_rate'] = 0;
      }

      $stats['enabled'] = true;
      $stats['ttl'] = $this->cacheTTL;

      return $stats;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Cache stats error: " . $e->getMessage(),
        'error'
      );
      return ['enabled' => true, 'error' => $e->getMessage()];
    }
  }
}
