<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\Security\SecurityLogger;

/**
 * MemoryDeduplicator Class
 *
 * Persistence-level duplicate detection and cleanup for the long-term memory
 * vector store. Extracted verbatim from LongTermMemoryManager (2026-06-23) to
 * concentrate the raw SQL against the embedding table (column-existence probing,
 * COUNT-based duplicate checks, dedup DELETEs) in a single MemoryPersistence
 * concern — shrinking the cyclomatic load of the store/search methods.
 *
 * Stateless across calls; the caller supplies the target table name (so this
 * class stays decoupled from the MariaDBVectorStore instance).
 *
 * Responsibilities:
 * - Count existing rows matching an interaction_id (direct column or JSON fallback)
 * - Count existing rows matching a content hash for a user/language (last 7 days)
 * - Remove duplicate rows by interaction_id and by content hash
 */
class MemoryDeduplicator
{
  private SecurityLogger $logger;
  private bool $debug;

  /**
   * Constructor
   *
   * @param bool $debug Enable debug logging
   */
  public function __construct(bool $debug = false)
  {
    $this->debug = $debug;
    $this->logger = new SecurityLogger();
  }

  /**
   * Count rows already stored for a given interaction_id (duplicate check).
   *
   * Uses the direct interaction_id column when present (fast), otherwise falls
   * back to a JSON LIKE scan of the metadata column.
   *
   * @param string $tableName Vector-store table name
   * @param string $interactionId Interaction id to look up
   * @return int Number of existing rows (0 = not a duplicate)
   */
  public function interactionIdDuplicateCount(string $tableName, string $interactionId): int
  {
    // Check if interaction_id column exists
    $hasColumn = DoctrineOrm::columnExists($tableName, 'interaction_id');

    if ($hasColumn) {
      // Use direct column (fast)
      $sql = "SELECT COUNT(*) as count
              FROM `{$tableName}`
              WHERE interaction_id = :interaction_id
              LIMIT 1";
      $existingCount = DoctrineOrm::selectValue($sql, [
        'interaction_id' => $interactionId
      ]);
    } else {
      // Fallback to JSON search
      $sql = "SELECT COUNT(*) as count
              FROM `{$tableName}`
              WHERE metadata LIKE :pattern
              LIMIT 1";
      $existingCount = DoctrineOrm::selectValue($sql, [
        'pattern' => '%' . addcslashes($interactionId, '%_') . '%'
      ]);
    }

    return (int) $existingCount;
  }

  /**
   * Count rows already stored with the exact same content hash for this
   * user/language within the last 7 days (duplicate check).
   *
   * @param string $tableName Vector-store table name
   * @param string $contentHash md5 of the content
   * @param string $userId User id
   * @param int $languageId Language id
   * @return int Number of existing rows (0 = not a duplicate)
   */
  public function contentHashDuplicateCount(string $tableName, string $contentHash, string $userId, int $languageId): int
  {
    // Check 2: By exact content hash + user_id + language_id
    $hasUserIdColumn = DoctrineOrm::columnExists($tableName, 'user_id');

    // Build user_id condition based on column availability
    if ($hasUserIdColumn) {
      $userIdCondition = "user_id = :user_id";
      $params = [
        'language_id' => $languageId,
        'content_hash' => $contentHash,
        'user_id' => $userId
      ];
    } else {
      $userIdCondition = "(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.user_id')) = :user_id OR sourcename = :user_id)";
      $params = [
        'language_id' => $languageId,
        'content_hash' => $contentHash,
        'user_id' => $userId
      ];
    }

    // Check if exact same content exists for this user/language (within last 7 days)
    $sql = "SELECT COUNT(*) as count
            FROM `{$tableName}`
            WHERE language_id = :language_id
            AND MD5(content) = :content_hash
            AND {$userIdCondition}
            AND date_modified > DATE_SUB(NOW(), INTERVAL 7 DAY)
            LIMIT 1";

    return (int) DoctrineOrm::selectValue($sql, $params);
  }

  /**
   * Clean duplicate entries from the vector store.
   * Removes entries with same interaction_id or very similar content.
   *
   * @param string $tableName Vector-store table name
   * @return array Statistics about cleaned duplicates
   */
  public function cleanDuplicates(string $tableName): array
  {
    try {
      $stats = [
        'by_interaction_id' => 0,
        'by_content_hash' => 0,
        'total_cleaned' => 0
      ];

      // Check if columns exist
      $hasInteractionIdColumn = DoctrineOrm::columnExists($tableName, 'interaction_id');
      $hasUserIdColumn = DoctrineOrm::columnExists($tableName, 'user_id');

      // Clean duplicates by interaction_id (keep the first one)
      if ($hasInteractionIdColumn) {
        $sql = "DELETE t1 FROM `{$tableName}` t1
                INNER JOIN `{$tableName}` t2
                WHERE t1.id > t2.id
                AND t1.interaction_id = t2.interaction_id
                AND t1.interaction_id IS NOT NULL
                AND t1.interaction_id != ''";
      } else {
        $sql = "DELETE t1 FROM `{$tableName}` t1
                INNER JOIN `{$tableName}` t2
                WHERE t1.id > t2.id
                AND JSON_UNQUOTE(JSON_EXTRACT(t1.metadata, '$.interaction_id')) = JSON_UNQUOTE(JSON_EXTRACT(t2.metadata, '$.interaction_id'))
                AND JSON_UNQUOTE(JSON_EXTRACT(t1.metadata, '$.interaction_id')) IS NOT NULL
                AND JSON_UNQUOTE(JSON_EXTRACT(t1.metadata, '$.interaction_id')) != ''";
      }
      $stats['by_interaction_id'] = DoctrineOrm::execute($sql);

      // Clean duplicates by content hash (keep the oldest one)
      if ($hasUserIdColumn) {
        $sql = "DELETE t1 FROM `{$tableName}` t1
                INNER JOIN `{$tableName}` t2
                WHERE t1.id > t2.id
                AND t1.language_id = t2.language_id
                AND t1.user_id = t2.user_id
                AND JSON_UNQUOTE(JSON_EXTRACT(t1.metadata, '$.content_hash')) = JSON_UNQUOTE(JSON_EXTRACT(t2.metadata, '$.content_hash'))
                AND JSON_UNQUOTE(JSON_EXTRACT(t1.metadata, '$.content_hash')) IS NOT NULL";
      } else {
        $sql = "DELETE t1 FROM `{$tableName}` t1
                INNER JOIN `{$tableName}` t2
                WHERE t1.id > t2.id
                AND t1.language_id = t2.language_id
                AND (
                  JSON_UNQUOTE(JSON_EXTRACT(t1.metadata, '$.user_id')) = JSON_UNQUOTE(JSON_EXTRACT(t2.metadata, '$.user_id'))
                  OR t1.sourcename = t2.sourcename
                )
                AND JSON_UNQUOTE(JSON_EXTRACT(t1.metadata, '$.content_hash')) = JSON_UNQUOTE(JSON_EXTRACT(t2.metadata, '$.content_hash'))
                AND JSON_UNQUOTE(JSON_EXTRACT(t1.metadata, '$.content_hash')) IS NOT NULL";
      }
      $stats['by_content_hash'] = DoctrineOrm::execute($sql);

      $stats['total_cleaned'] = $stats['by_interaction_id'] + $stats['by_content_hash'];

      if ($this->debug) {
        $this->logger->logSecurityEvent(
          "Cleaned duplicates: " . json_encode($stats),
          'info'
        );
      }

      return $stats;

    } catch (\Exception $e) {
      $this->logger->logSecurityEvent(
        "Error cleaning duplicates: " . $e->getMessage(),
        'error'
      );
      return [
        'by_interaction_id' => 0,
        'by_content_hash' => 0,
        'total_cleaned' => 0,
        'error' => $e->getMessage()
      ];
    }
  }
}
