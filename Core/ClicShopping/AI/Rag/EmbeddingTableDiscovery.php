<?php
declare(strict_types=1);

/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Rag;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;

/**
 * EmbeddingTableDiscovery - dynamic discovery of *_embedding tables from the database schema.
 *
 * Extracted from MultiDBRAGManager (god-class decomposition): this cross-cutting concern is
 * reused by many callers (entity registries, planners, executors) that only needed the table
 * list, not the full RAG manager. MultiDBRAGManager::knownEmbeddingTable() now delegates here.
 *
 * Behaviour is preserved verbatim, including the function-level static cache (shared across all
 * calls, as the original method's static was) and the exact debug-gating of each log line.
 *
 * @package ClicShopping\AI\Rag
 * @since 2026-06-11
 */
class EmbeddingTableDiscovery
{
  public function __construct(
    private bool $debug = false,
    private ?SecurityLogger $securityLogger = null
  ) {}

  /**
   * Discover all *_embedding tables for the active database via INFORMATION_SCHEMA.
   *
   * @param bool $useCache Reuse the process-wide cached result when available
   * @return array List of detected embedding table names (empty array if none / on failure)
   */
  public function discover(bool $useCache = true): array
  {
    // Static cache to avoid repeated database queries
    static $cachedTables = null;

    if ($useCache && $cachedTables !== null) {
      return $cachedTables;
    }

    $prefix = CLICSHOPPING::getConfig('db_table_prefix');
    $dbName = CLICSHOPPING::getConfig('db_database');

    try {
      // Try to dynamically detect all *_embedding tables from database
      $sql = "SELECT TABLE_NAME
              FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA = :dbName
              AND TABLE_NAME LIKE :pattern
              ORDER BY TABLE_NAME";

      $detectedTables = DoctrineOrm::select($sql, [
        'dbName' => $dbName,
        'pattern' => $prefix . '%_embedding'
      ]);

      $detectedTables = array_column($detectedTables, 'TABLE_NAME');

      if (!empty($detectedTables)) {
        if ($this->debug && $this->securityLogger !== null) {
          $this->securityLogger->logSecurityEvent(
            "Dynamically detected " . count($detectedTables) . " embedding tables from database",
            'info',
            ['tables' => $detectedTables]
          );
        }

        $cachedTables = $detectedTables;
        return $detectedTables;
      }

    } catch (\Exception $e) {
      // Log error but continue with fallback
      if ($this->securityLogger !== null) {
        $this->securityLogger->logSecurityEvent(
          "Failed to dynamically detect embedding tables: " . $e->getMessage(),
          'warning'
        );
      }
    }

    // Fallback: Return empty array (no hardcoded list)
    // The dynamic detection above handles ALL embedding tables automatically.
    // If dynamic detection fails, it's better to return empty than use stale hardcoded list.
    // This ensures the system adapts to new embeddings (including future parallel reads).
    if ($this->debug && $this->securityLogger !== null) {
      $this->securityLogger->logSecurityEvent(
        "No embedding tables detected - returning empty array",
        'warning'
      );
    }

    $cachedTables = [];
    return [];
  }
}
