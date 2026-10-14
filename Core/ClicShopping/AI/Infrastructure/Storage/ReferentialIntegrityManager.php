<?php
/**
 * ReferentialIntegrityManager
 * 
 * Manages referential integrity between tables without using CASCADE
 * Follows manual constraint management policy
 */

namespace ClicShopping\AI\Infrastructure\Storage;

use ClicShopping\OM\Registry;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;

/**
 * ReferentialIntegrityManager Class
 * 
 * 🔧 MIGRATED TO DOCTRINEORM: December 6, 2025
 * All database queries now use DoctrineOrm instead of PDO
 */
class ReferentialIntegrityManager
{
  private string $prefix;
  
  public function __construct()
  {
    $this->prefix = CLICSHOPPING::getConfig('db_table_prefix');
  }
  
  /**
   * Deletes interaction and all associated statistics
   * Manually handles cascade deletion
   * 
   * @param int $interactionId Interaction ID to delete
   * @return bool True if success, false otherwise
   */
  public function deleteInteractionWithStats(int $interactionId): bool
  {
    try {
      // 1. Delete associated statistics
      DoctrineOrm::execute("
        DELETE FROM {$this->prefix}rag_statistics 
        WHERE interaction_id = ?
      ", [$interactionId]);
      
      // 2. Delete interaction
      DoctrineOrm::execute("
        DELETE FROM {$this->prefix}chatgpt_interactions 
        WHERE interaction_id = ?
      ", [$interactionId]);
      
      return true;
      
    } catch (\Exception $e) {
      error_log("ReferentialIntegrityManager: Error deleting interaction {$interactionId}: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Updates interaction and its associated statistics
   * 
   * @param int $interactionId Interaction ID
   * @param int $newInteractionId New ID (if changed)
   * @return bool True if success, false otherwise
   */
  public function updateInteractionId(int $interactionId, int $newInteractionId): bool
  {
    try {
      // Update statistics
      DoctrineOrm::execute("
        UPDATE {$this->prefix}rag_statistics 
        SET interaction_id = ? 
        WHERE interaction_id = ?
      ", [$newInteractionId, $interactionId]);
      
      return true;
      
    } catch (\Exception $e) {
      error_log("ReferentialIntegrityManager: Error updating interaction ID: " . $e->getMessage());
      return false;
    }
  }
  
  /**
   * Cleans orphaned statistics (without associated interaction)
   * Useful for maintaining data consistency
   * 
   * @return int Number of deleted statistics
   */
  public function cleanOrphanedStats(): int
  {
    try {
      $deleted = DoctrineOrm::execute("
        DELETE s FROM {$this->prefix}rag_statistics s
        LEFT JOIN {$this->prefix}chatgpt_interactions i ON s.interaction_id = i.interaction_id
        WHERE i.interaction_id IS NULL
          AND s.interaction_id IS NOT NULL
      ");
      
      if ($deleted > 0) {
        error_log("ReferentialIntegrityManager: Cleaned {$deleted} orphaned statistics");
      }
      
      return $deleted;
      
    } catch (\Exception $e) {
      error_log("ReferentialIntegrityManager: Error cleaning orphaned stats: " . $e->getMessage());
      return 0;
    }
  }
  
  /**
   * Checks referential integrity
   * Returns number of orphaned statistics
   * 
   * @return int Number of orphaned statistics
   */
  public function checkIntegrity(): int
  {
    try {
      $results = DoctrineOrm::select("
        SELECT COUNT(*) as orphaned_count
        FROM {$this->prefix}rag_statistics s
        LEFT JOIN {$this->prefix}chatgpt_interactions i ON s.interaction_id = i.interaction_id
        WHERE i.interaction_id IS NULL
          AND s.interaction_id IS NOT NULL
      ");
      
      return $results[0]['orphaned_count'] ?? 0;
      
    } catch (\Exception $e) {
      error_log("ReferentialIntegrityManager: Error checking integrity: " . $e->getMessage());
      return -1;
    }
  }
  
  /**
   * Deletes old statistics (older than X days)
   * 
   * @param int $days Number of days to keep
   * @return int Number of deleted statistics
   */
  public function deleteOldStats(int $days = 90): int
  {
    try {
      $deleted = DoctrineOrm::execute("
        DELETE FROM {$this->prefix}rag_statistics 
        WHERE date_added < DATE_SUB(NOW(), INTERVAL ? DAY)
      ", [$days]);
      
      if ($deleted > 0) {
        error_log("ReferentialIntegrityManager: Deleted {$deleted} old statistics (>{$days} days)");
      }
      
      return $deleted;
      
    } catch (\Exception $e) {
      error_log("ReferentialIntegrityManager: Error deleting old stats: " . $e->getMessage());
      return 0;
    }
  }
  
  /**
   * Deletes old interactions and their statistics
   * 
   * @param int $days Number of days to keep
   * @return array ['interactions' => count, 'statistics' => count]
   */
  public function deleteOldInteractions(int $days = 90): array
  {
    try {
      // 1. Get IDs of interactions to delete
      $results = DoctrineOrm::select("
        SELECT interaction_id 
        FROM {$this->prefix}chatgpt_interactions 
        WHERE date_added < DATE_SUB(NOW(), INTERVAL ? DAY)
      ", [$days]);
      
      $ids = [];
      foreach ($results as $row) {
        $ids[] = $row['interaction_id'];
      }
      
      if (empty($ids)) {
        return ['interactions' => 0, 'statistics' => 0];
      }
      
      // 2. Delete associated statistics
      $placeholders = implode(',', array_fill(0, \count($ids), '?'));
      $statsDeleted = DoctrineOrm::execute("
        DELETE FROM {$this->prefix}rag_statistics 
        WHERE interaction_id IN ({$placeholders})
      ", $ids);
      
      // 3. Delete interactions
      $interactionsDeleted = DoctrineOrm::execute("
        DELETE FROM {$this->prefix}chatgpt_interactions 
        WHERE date_added < DATE_SUB(NOW(), INTERVAL ? DAY)
      ", [$days]);
      
      error_log("ReferentialIntegrityManager: Deleted {$interactionsDeleted} old interactions and {$statsDeleted} statistics (>{$days} days)");
      
      return [
        'interactions' => $interactionsDeleted,
        'statistics' => $statsDeleted
      ];
      
    } catch (\Exception $e) {
      error_log("ReferentialIntegrityManager: Error deleting old interactions: " . $e->getMessage());
      return ['interactions' => 0, 'statistics' => 0];
    }
  }
}
