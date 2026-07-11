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
 * SeoCronStrategy
 *
 * Selects which entities (products OR categories) should be processed by the
 * SEO cron, ordered by priority:
 *   A. Never analysed yet           — newest first
 *   B. Modified since last analysis — keep optimised content in sync
 *   C. Recent benchmark regressions — automatic catch-up
 *
 * The entity type is chosen at construction (default 'product' — the product
 * behaviour is unchanged). Products and categories use different column names
 * (products_status vs categories_status, products_last_modified vs
 * last_modified, …), so target selection is driven by the ENTITY map below.
 *
 * Deduplication across sources guarantees an entity is enqueued only once per
 * run.  The strategy never returns more than $limit targets so the cron can
 * stay within its execution budget.
 */
class SeoCronStrategy
{
  private mixed $db;
  private string $entityType;

  /** @var array<string,string> resolved column/table map for $entityType */
  private array $cfg;

  /**
   * Per-entity table/column map. All identifiers here are trusted constants
   * (never user input) and are interpolated directly into the SQL; the table
   * names are passed through the :table_ placeholder so the DB prefix applies.
   */
  private const ENTITY = [
    'product' => [
      'table'         => 'products',
      'id'            => 'products_id',
      'status'        => 'products_status',
      'date_added'    => 'products_date_added',
      'last_modified' => 'products_last_modified',
      'desc_table'    => 'products_description',
      'desc_col'      => 'products_description',
      'seo_table'     => 'products_seo_embedding',
    ],
    'category' => [
      'table'         => 'categories',
      'id'            => 'categories_id',
      'status'        => 'categories_status',
      'date_added'    => 'date_added',
      'last_modified' => 'last_modified',
      'desc_table'    => 'categories_description',
      'desc_col'      => 'categories_description',
      'seo_table'     => 'categories_seo_embedding',
    ],
  ];

  public function __construct(string $entityType = 'product')
  {
    $this->db         = Registry::get('Db');
    $this->entityType = isset(self::ENTITY[$entityType]) ? $entityType : 'product';
    $this->cfg        = self::ENTITY[$this->entityType];
  }

  /**
   * Fetch up to $limit entity IDs to process, ordered by priority.
   *
   * @return list<int>  entity_id list (deduplicated, capped at $limit)
   */
  public function fetchTargets(int $limit = 30): array
  {
    if ($limit <= 0) {
      return [];
    }

    $picked = [];

    // ── A. Never analysed yet (has description, active, no embedding row) ─
    foreach ($this->fetchNeverAnalysed($limit) as $id) {
      $picked[$id] = true;
      if (count($picked) >= $limit) {
        return array_keys($picked);
      }
    }

    // B. Modified since last analysis
    foreach ($this->fetchModifiedSinceLastAnalysis($limit) as $id) {
      if (isset($picked[$id])) {
        continue;
      }
      $picked[$id] = true;
      if (count($picked) >= $limit) {
        return array_keys($picked);
      }
    }

    // C. Recent benchmark regressions (critical=1 last 7 days) ─
    foreach ($this->fetchRecentRegressions($limit) as $id) {
      if (isset($picked[$id])) {
        continue;
      }
      $picked[$id] = true;
      if (count($picked) >= $limit) {
        break;
      }
    }

    return array_keys($picked);
  }

  /**
   * Source A — active entities with a non-empty description that have NO row
   * in the SEO embedding table yet.  Newest first.
   *
   * @return list<int>
   */
  private function fetchNeverAnalysed(int $limit): array
  {
    $c = $this->cfg;
    try {
      $stmt = $this->db->prepare('
        SELECT p.' . $c['id'] . ' AS entity_id
        FROM :table_' . $c['table'] . ' p
        WHERE p.' . $c['status'] . ' = 1
          AND EXISTS (
            SELECT 1 FROM :table_' . $c['desc_table'] . ' pd
            WHERE pd.' . $c['id'] . ' = p.' . $c['id'] . '
              AND TRIM(COALESCE(pd.' . $c['desc_col'] . ', "")) <> ""
          )
          AND NOT EXISTS (
            SELECT 1 FROM :table_' . $c['seo_table'] . ' e
            WHERE e.entity_id   = p.' . $c['id'] . '
              AND e.entity_type = "' . $this->entityType . '"
          )
        ORDER BY p.' . $c['date_added'] . ' DESC
        LIMIT :lim
      ');
      $stmt->bindInt(':lim', $limit);
      $stmt->execute();
      return array_map(fn($r) => (int)$r['entity_id'], $stmt->fetchAll() ?: []);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Source B — entities whose last_modified is newer than their latest SEO
   * analysis.
   *
   * @return list<int>
   */
  private function fetchModifiedSinceLastAnalysis(int $limit): array
  {
    $c = $this->cfg;
    try {
      $stmt = $this->db->prepare('
        SELECT p.' . $c['id'] . ' AS entity_id
        FROM :table_' . $c['table'] . ' p
        INNER JOIN (
          SELECT entity_id, MAX(date_modified) AS last_analysis
          FROM :table_' . $c['seo_table'] . '
          WHERE entity_type = "' . $this->entityType . '"
          GROUP BY entity_id
        ) e ON e.entity_id = p.' . $c['id'] . '
        WHERE p.' . $c['status'] . ' = 1
          AND p.' . $c['last_modified'] . ' IS NOT NULL
          AND p.' . $c['last_modified'] . ' > e.last_analysis
        ORDER BY p.' . $c['last_modified'] . ' DESC
        LIMIT :lim
      ');
      $stmt->bindInt(':lim', $limit);
      $stmt->execute();
      return array_map(fn($r) => (int)$r['entity_id'], $stmt->fetchAll() ?: []);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Source C — distinct entity_id from clic_seo_quality_benchmark_log where the
   * latest critical regression happened in the last 7 days.
   *
   * @return list<int>
   */
  private function fetchRecentRegressions(int $limit): array
  {
    try {
      $stmt = $this->db->prepare('
        SELECT DISTINCT entity_id AS entity_id
        FROM :table_seo_quality_benchmark_log
        WHERE entity_type = "' . $this->entityType . '"
          AND verdict = "regression"
          AND critical = 1
          AND date_modified > NOW() - INTERVAL 7 DAY
        ORDER BY date_modified DESC
        LIMIT :lim
      ');
      $stmt->bindInt(':lim', $limit);
      $stmt->execute();
      return array_map(fn($r) => (int)$r['entity_id'], $stmt->fetchAll() ?: []);
    } catch (\Throwable $e) {
      return [];
    }
  }
}
