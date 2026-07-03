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
 * Selects which products should be processed by the SEO cron, ordered by
 * priority:
 *   A. Never analysed yet           — newest products first
 *   B. Modified since last analysis — keep optimised content in sync
 *   C. Recent benchmark regressions — automatic catch-up
 *
 * Deduplication across sources guarantees a product is enqueued only once
 * per run.  The strategy never returns more than $limit targets so the cron
 * can stay within its execution budget.
 */
class SeoCronStrategy
{
  private mixed $db;

  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Fetch up to $limit product IDs to process, ordered by priority.
   *
   * @return list<int>  product_id list (deduplicated, capped at $limit)
   */
  public function fetchTargets(int $limit = 30): array
  {
    if ($limit <= 0) {
      return [];
    }

    $picked = [];

    // ── A. Never analysed yet (has description, active, no embedding row) ─
    foreach ($this->fetchNeverAnalysed($limit) as $pid) {
      $picked[$pid] = true;
      if (count($picked) >= $limit) {
        return array_keys($picked);
      }
    }

    // B. Modified since last analysis
    foreach ($this->fetchModifiedSinceLastAnalysis($limit) as $pid) {
      if (isset($picked[$pid])) {
        continue;
      }
      $picked[$pid] = true;
      if (count($picked) >= $limit) {
        return array_keys($picked);
      }
    }

    // C. Recent benchmark regressions (critical=1 last 7 days) ─
    foreach ($this->fetchRecentRegressions($limit) as $pid) {
      if (isset($picked[$pid])) {
        continue;
      }
      $picked[$pid] = true;
      if (count($picked) >= $limit) {
        break;
      }
    }

    return array_keys($picked);
  }

  /**
   * Source A — active products with a non-empty description that have NO
   * row in clic_products_seo_embedding yet.  Oldest first (so brand-new
   * additions are not starved by very old never-analysed products).
   *
   * @return list<int>
   */
  private function fetchNeverAnalysed(int $limit): array
  {
    try {
      $stmt = $this->db->prepare('
        SELECT p.products_id
        FROM :table_products p
        WHERE p.products_status = 1
          AND EXISTS (
            SELECT 1 FROM :table_products_description pd
            WHERE pd.products_id = p.products_id
              AND TRIM(COALESCE(pd.products_description, "")) <> ""
          )
          AND NOT EXISTS (
            SELECT 1 FROM :table_products_seo_embedding e
            WHERE e.entity_id   = p.products_id
              AND e.entity_type = "product"
          )
        ORDER BY p.products_date_added DESC
        LIMIT :lim
      ');
      $stmt->bindInt(':lim', $limit);
      $stmt->execute();
      return array_map(fn($r) => (int)$r['products_id'], $stmt->fetchAll() ?: []);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Source B — products whose products_last_modified is newer than their
   * latest SEO analysis.
   *
   * @return list<int>
   */
  private function fetchModifiedSinceLastAnalysis(int $limit): array
  {
    try {
      $stmt = $this->db->prepare('
        SELECT p.products_id
        FROM :table_products p
        INNER JOIN (
          SELECT entity_id, MAX(date_modified) AS last_analysis
          FROM :table_products_seo_embedding
          WHERE entity_type = "product"
          GROUP BY entity_id
        ) e ON e.entity_id = p.products_id
        WHERE p.products_status = 1
          AND p.products_last_modified IS NOT NULL
          AND p.products_last_modified > e.last_analysis
        ORDER BY p.products_last_modified DESC
        LIMIT :lim
      ');
      $stmt->bindInt(':lim', $limit);
      $stmt->execute();
      return array_map(fn($r) => (int)$r['products_id'], $stmt->fetchAll() ?: []);
    } catch (\Throwable $e) {
      return [];
    }
  }

  /**
   * Source C — distinct entity_id from clic_seo_quality_benchmark_log where
   * the latest critical regression happened in the last 7 days.
   *
   * @return list<int>
   */
  private function fetchRecentRegressions(int $limit): array
  {
    try {
      $stmt = $this->db->prepare('
        SELECT DISTINCT entity_id AS products_id
        FROM :table_seo_quality_benchmark_log
        WHERE entity_type = "product"
          AND verdict = "regression"
          AND critical = 1
          AND date_modified > NOW() - INTERVAL 7 DAY
        ORDER BY date_modified DESC
        LIMIT :lim
      ');
      $stmt->bindInt(':lim', $limit);
      $stmt->execute();
      return array_map(fn($r) => (int)$r['products_id'], $stmt->fetchAll() ?: []);
    } catch (\Throwable $e) {
      return [];
    }
  }
}
