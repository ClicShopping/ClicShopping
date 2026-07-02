<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

use ClicShopping\OM\Registry;

/**
 * SeoOriginalSnapshotRepository
 *
 * Write-once persistence of the genuine original SEO content (per entity +
 * language), captured before the first optimization and never overwritten or
 * purged. Source of truth for "Reject" / "Revenir au texte initial".
 */
class SeoOriginalSnapshotRepository
{
  private mixed $db;

  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Insert the original content for (entityType, entityId, languageId) if no row
   * exists yet. No-op on an existing row (write-once, enforced by the UNIQUE key).
   * Returns false when there is nothing to store.
   */
  public function captureIfAbsent(string $entityType, int $entityId, int $languageId, ?array $content): bool
  {
    if (empty($content)) {
      return false;
    }

    $stmt = $this->db->prepare(
      'INSERT INTO :table_seo_original_snapshot
         (entity_type, entity_id, language_id, original_content, created_at)
       VALUES (:entity_type, :entity_id, :language_id, :content, NOW())
       ON DUPLICATE KEY UPDATE id = id'
    );
    $stmt->bindValue(':entity_type', $entityType);
    $stmt->bindInt(':entity_id', $entityId);
    $stmt->bindInt(':language_id', $languageId);
    $stmt->bindValue(':content', json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $stmt->execute();

    return true;
  }

  /**
   * Decoded original content for one language, or null when no snapshot exists.
   *
   * @return array<string,mixed>|null
   */
  public function get(string $entityType, int $entityId, int $languageId): ?array
  {
    $stmt = $this->db->prepare('SELECT original_content 
                                FROM :table_seo_original_snapshot
                                WHERE entity_type = :entity_type AND entity_id = :entity_id AND language_id = :language_id
                                LIMIT 1
                              ');
    $stmt->bindValue(':entity_type', $entityType);
    $stmt->bindInt(':entity_id', $entityId);
    $stmt->bindInt(':language_id', $languageId);
    $stmt->execute();

    $row = $stmt->fetch();
    if (!$row) {
      return null;
    }
    $decoded = json_decode((string)$row['original_content'], true);

    return is_array($decoded) ? $decoded : null;
  }

  /** Whether a snapshot exists for the entity in any language. */
  public function exists(string $entityType, int $entityId): bool
  {
    $stmt = $this->db->prepare('SELECT 1 FROM :table_seo_original_snapshot
                                WHERE entity_type = :entity_type 
                                AND entity_id = :entity_id LIMIT 1
                                ');
    $stmt->bindValue(':entity_type', $entityType);
    $stmt->bindInt(':entity_id', $entityId);
    $stmt->execute();

    return (bool)$stmt->fetch();
  }

  /**
   * Restore the original content verbatim (no normalization) for one language.
   * Returns false when there is no snapshot to restore.
   */
  public function restore(SeoEntityAdapter $adapter, string $entityType, int $entityId, int $languageId): bool
  {
    $content = $this->get($entityType, $entityId, $languageId);
    if ($content === null) {
      return false;
    }

    return $adapter->applySeoChanges($entityId, $languageId, $content, false);
  }

  /**
   * Restore every given language. Returns the number of languages restored.
   *
   * @param list<int> $languageIds
   */
  public function restoreAllLanguages(SeoEntityAdapter $adapter, string $entityType, int $entityId, array $languageIds): int
  {
    $restored = 0;
    foreach ($languageIds as $languageId) {
      if ($this->restore($adapter, $entityType, $entityId, (int)$languageId)) {
        $restored++;
      }
    }

    return $restored;
  }

  /**
   * Capture the genuine original SEO content (write-once, EVERY enabled language)
   * for an entity BEFORE any optimization overwrites it. This is the shared
   * hard-precondition helper for ALL Phase 2 callers (the manual optimize endpoint
   * and the SEO cron), so the genuine original is preserved exactly once no matter
   * which path triggers the first optimization. Exceptions propagate so the caller
   * can abort the optimization — never overwrite content we cannot roll back.
   */
  public static function captureEntityOriginals(string $entityType, int $entityId): void
  {
    $adapter = new SeoEntityAdapter($entityType);
    $repo    = new self();

    foreach (Registry::get('Language')->getAll() as $languageRow) {
      $languageId = (int)($languageRow['id'] ?? 0);
      if ($languageId <= 0) {
        continue;
      }
      $current = $adapter->getCurrentData($entityId, $languageId);
      if ($current !== null) {
        $repo->captureIfAbsent($entityType, $entityId, $languageId, $current);
      }
    }
  }
}
