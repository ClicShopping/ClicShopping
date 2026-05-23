<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\CockpitAI;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

/**
 * DashboardData
 *
 * Aggregates data from products_cockpit_ai_embedding  for the CockpitAI dashboard.
 *
 * All methods query the embedding table using JSON_EXTRACT on the metadata JSON column.
 * Results are cached for 30 minutes (dashboard TTL) to avoid expensive repeated queries.
 *
 * ── Data sources ─────────────────────────────────────────────────────────────
 *
 * All data comes from clic_products_cockpit_ai_embedding :
 *   metadata->scores->score_x        float    Product quality score
 *   metadata->scores->score_y        float    Commercial performance score
 *   metadata->scores->quadrant       string   Q1|Q2|Q3|Q4|Q_intermediate
 *   metadata->inventory_metrics->*   mixed    Velocity metrics (when present)
 *   entity_id                        int      products_id
 *   language_id                      int
 *   date_modified                    datetime Last analysis date
 *
 * ── Cache strategy ────────────────────────────────────────────────────────────
 *
 * Each method has its own cache key so partial refreshes are possible.
 * All caches share the 'CockpitAI' namespace with TTL = 30 minutes.
 * Call clearCache() to force a full refresh (e.g. after a batch cron run).
 */
class DashboardData
{
  private const TABLE       = 'products_cockpit_ai_embedding ';
  private const CACHE_TTL   = '30'; // minutes
  private const CACHE_NS    = 'CockpitAI';

  private mixed $db;

  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  // ── Public API ─────────────────────────────────────────────────────────────

  /**
   * Distribution of most-recent analyses per quadrant.
   *
   * For each product (entity_id + language_id), only the most recent analysis
   * is counted. Returns counts for all 5 quadrant codes.
   *
   * @param int $languageId
   * @return array<string, int>  ['Q1' => n, 'Q2' => n, 'Q3' => n, 'Q4' => n, 'Q_intermediate' => n]
   */
  public function getQuadrantDistribution(int $languageId): array
  {
    $cacheKey = "dashboard_quadrant_{$languageId}";
    if ($cached = $this->fromCache($cacheKey)) {
      return $cached;
    }

    $defaults = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0, 'Q_intermediate' => 0];

    try {
      // Latest analysis per product only
      $Qdist = $this->db->prepare('
        SELECT
          JSON_UNQUOTE(JSON_EXTRACT(e.metadata, \'$.scores.quadrant\')) AS quadrant,
          COUNT(*) AS cnt
        FROM :table_' . self::TABLE . ' e
        INNER JOIN (
          SELECT entity_id, MAX(date_modified) AS latest
          FROM :table_' . self::TABLE . '
          WHERE language_id = :language_id
          GROUP BY entity_id
        ) latest ON e.entity_id = latest.entity_id
                 AND e.date_modified = latest.latest
        WHERE e.language_id = :language_id2
          AND JSON_EXTRACT(e.metadata, \'$.scores.quadrant\') IS NOT NULL
        GROUP BY quadrant
      ');

      $Qdist->bindInt(':language_id',  $languageId);
      $Qdist->bindInt(':language_id2', $languageId);
      $Qdist->execute();

      $result = $defaults;
      while ($row = $Qdist->fetch()) {
        $q = $row['quadrant'] ?? '';
        if (isset($result[$q])) {
          $result[$q] = (int) $row['cnt'];
        }
      }

      $this->toCache($cacheKey, $result);
      return $result;

    } catch (\Throwable) {
      return $defaults;
    }
  }

  /**
   * Top N products by Score Y (most recent analysis per product).
   *
   * @param int $languageId
   * @param int $limit        Default 10
   * @return array[]  Each row: ['product_id', 'product_name', 'score_x', 'score_y', 'quadrant', 'analysis_date']
   */
  public function getTopProductsByScoreY(int $languageId, int $limit = 10): array
  {
    $cacheKey = "dashboard_top_y_{$languageId}_{$limit}";
    if ($cached = $this->fromCache($cacheKey)) {
      return $cached;
    }

    try {
      $Qtop = $this->db->prepare('
        SELECT
          e.entity_id                                                          AS product_id,
          COALESCE(pd.products_name, CONCAT(\'Product #\', e.entity_id))      AS product_name,
          ROUND(JSON_EXTRACT(e.metadata, \'$.scores.score_x\'), 1)            AS score_x,
          ROUND(JSON_EXTRACT(e.metadata, \'$.scores.score_y\'), 1)            AS score_y,
          JSON_UNQUOTE(JSON_EXTRACT(e.metadata, \'$.scores.quadrant\'))        AS quadrant,
          e.date_modified                                                      AS analysis_date
        FROM :table_' . self::TABLE . ' e
        INNER JOIN (
          SELECT entity_id, MAX(date_modified) AS latest
          FROM :table_' . self::TABLE . '
          WHERE language_id = :language_id
          GROUP BY entity_id
        ) latest ON e.entity_id = latest.entity_id
                 AND e.date_modified = latest.latest
        LEFT JOIN :table_products_description pd
               ON pd.products_id = e.entity_id
              AND pd.language_id = :language_id2
        WHERE e.language_id = :language_id3
          AND JSON_EXTRACT(e.metadata, \'$.scores.score_y\') IS NOT NULL
        ORDER BY score_y DESC
        LIMIT :limit
      ');

      $Qtop->bindInt(':language_id',  $languageId);
      $Qtop->bindInt(':language_id2', $languageId);
      $Qtop->bindInt(':language_id3', $languageId);
      $Qtop->bindInt(':limit', $limit);
      $Qtop->execute();

      $result = [];
      while ($row = $Qtop->fetch()) {
        $result[] = [
          'product_id'    => (int)    $row['product_id'],
          'product_name'  => (string) $row['product_name'],
          'score_x'       => (float)  ($row['score_x'] ?? 0),
          'score_y'       => (float)  ($row['score_y'] ?? 0),
          'quadrant'      => (string) ($row['quadrant'] ?? 'Q_intermediate'),
          'analysis_date' => (string) $row['analysis_date'],
        ];
      }

      $this->toCache($cacheKey, $result);
      return $result;

    } catch (\Throwable) {
      return [];
    }
  }

  /**
   * Products at high stockout risk (stockout_probability > threshold).
   *
   * @param int   $languageId
   * @param float $threshold   Default 0.7 (70% risk)
   * @param int   $limit       Default 15
   * @return array[]  Each row: ['product_id', 'product_name', 'stockout_probability', 'stock_velocity', 'safety_stock', 'score_y']
   */
  public function getStockoutRiskProducts(int $languageId, float $threshold = 0.7, int $limit = 15): array
  {
    $thresholdKey = (int) ($threshold * 100);
    $cacheKey     = "dashboard_stockout_{$languageId}_{$thresholdKey}_{$limit}";
    if ($cached = $this->fromCache($cacheKey)) {
      return $cached;
    }

    try {
      $Qrisk = $this->db->prepare('
        SELECT
          e.entity_id                                                                        AS product_id,
          COALESCE(pd.products_name, CONCAT(\'Product #\', e.entity_id))                   AS product_name,
          ROUND(JSON_EXTRACT(e.metadata, \'$.inventory_metrics.stockout_probability\'), 4)  AS stockout_probability,
          ROUND(JSON_EXTRACT(e.metadata, \'$.inventory_metrics.stock_velocity\'), 2)        AS stock_velocity,
          ROUND(JSON_EXTRACT(e.metadata, \'$.inventory_metrics.safety_stock\'), 2)          AS safety_stock,
          ROUND(JSON_EXTRACT(e.metadata, \'$.scores.score_y\'), 1)                          AS score_y
        FROM :table_' . self::TABLE . ' e
        INNER JOIN (
          SELECT entity_id, MAX(date_modified) AS latest
          FROM :table_' . self::TABLE . '
          WHERE language_id = :language_id
          GROUP BY entity_id
        ) latest ON e.entity_id = latest.entity_id
                 AND e.date_modified = latest.latest
        LEFT JOIN :table_products_description pd
               ON pd.products_id = e.entity_id
              AND pd.language_id = :language_id2
        WHERE e.language_id = :language_id3
          AND JSON_EXTRACT(e.metadata, \'$.inventory_metrics.stockout_probability\') >= :threshold
        ORDER BY stockout_probability DESC
        LIMIT :limit
      ');

      $Qrisk->bindInt(':language_id',  $languageId);
      $Qrisk->bindInt(':language_id2', $languageId);
      $Qrisk->bindInt(':language_id3', $languageId);
      $Qrisk->bindValue(':threshold', $threshold);
      $Qrisk->bindInt(':limit', $limit);
      $Qrisk->execute();

      $result = [];
      while ($row = $Qrisk->fetch()) {
        $result[] = [
          'product_id'           => (int)   $row['product_id'],
          'product_name'         => (string) $row['product_name'],
          'stockout_probability' => (float) ($row['stockout_probability'] ?? 0),
          'stock_velocity'       => $row['stock_velocity'] !== null ? (float) $row['stock_velocity'] : null,
          'safety_stock'         => $row['safety_stock']   !== null ? (float) $row['safety_stock']   : null,
          'score_y'              => (float) ($row['score_y'] ?? 0),
        ];
      }

      $this->toCache($cacheKey, $result);
      return $result;

    } catch (\Throwable) {
      return [];
    }
  }

  /**
   * Score evolution over time for a specific product (all analyses, ordered by date).
   *
   * @param int $productId
   * @param int $languageId
   * @param int $limit       Max analyses to return, default 30
   * @return array[]  Each row: ['date', 'score_x', 'score_y', 'quadrant']
   */
  public function getProductScoreEvolution(int $productId, int $languageId, int $limit = 30): array
  {
    $cacheKey = "dashboard_evolution_{$productId}_{$languageId}";
    if ($cached = $this->fromCache($cacheKey)) {
      return $cached;
    }

    try {
      $Qevol = $this->db->prepare('
        SELECT
          DATE(date_modified)                                           AS date,
          ROUND(JSON_EXTRACT(metadata, \'$.scores.score_x\'), 1)       AS score_x,
          ROUND(JSON_EXTRACT(metadata, \'$.scores.score_y\'), 1)       AS score_y,
          JSON_UNQUOTE(JSON_EXTRACT(metadata, \'$.scores.quadrant\'))  AS quadrant
        FROM :table_' . self::TABLE . '
        WHERE JSON_EXTRACT(metadata, \'$.entity_id\') = :entity_id
          AND language_id = :language_id
        ORDER BY date_modified ASC
        LIMIT :limit
      ');

      $Qevol->bindInt(':entity_id',  $productId);
      $Qevol->bindInt(':language_id', $languageId);
      $Qevol->bindInt(':limit', $limit);
      $Qevol->execute();

      $result = [];
      while ($row = $Qevol->fetch()) {
        $result[] = [
          'date'     => (string) $row['date'],
          'score_x'  => (float)  ($row['score_x'] ?? 0),
          'score_y'  => (float)  ($row['score_y'] ?? 0),
          'quadrant' => (string) ($row['quadrant'] ?? 'Q_intermediate'),
        ];
      }

      $this->toCache($cacheKey, $result);
      return $result;

    } catch (\Throwable) {
      return [];
    }
  }

  /**
   * Velocity summary: counts of fast-moving, slow-moving, and no-sales products.
   *
   * Fast-moving : stock_velocity >= 2.0
   * Slow-moving : 0 < stock_velocity < 2.0
   * No sales    : stock_velocity = 0 or null
   *
   * @param int $languageId
   * @return array  ['fast' => int, 'slow' => int, 'none' => int, 'no_data' => int]
   */
  public function getVelocitySummary(int $languageId): array
  {
    $cacheKey = "dashboard_velocity_{$languageId}";
    if ($cached = $this->fromCache($cacheKey)) {
      return $cached;
    }

    $defaults = ['fast' => 0, 'slow' => 0, 'none' => 0, 'no_data' => 0];

    try {
      $Qvel = $this->db->prepare('
        SELECT
          SUM(CASE
            WHEN JSON_EXTRACT(e.metadata, \'$.inventory_metrics.stock_velocity\') >= 2.0 THEN 1
            ELSE 0
          END) AS fast_count,
          SUM(CASE
            WHEN JSON_EXTRACT(e.metadata, \'$.inventory_metrics.stock_velocity\') > 0
             AND JSON_EXTRACT(e.metadata, \'$.inventory_metrics.stock_velocity\') < 2.0 THEN 1
            ELSE 0
          END) AS slow_count,
          SUM(CASE
            WHEN JSON_EXTRACT(e.metadata, \'$.inventory_metrics.stock_velocity\') = 0 THEN 1
            ELSE 0
          END) AS none_count,
          SUM(CASE
            WHEN JSON_EXTRACT(e.metadata, \'$.inventory_metrics.stock_velocity\') IS NULL THEN 1
            ELSE 0
          END) AS no_data_count
        FROM :table_' . self::TABLE . ' e
        INNER JOIN (
          SELECT entity_id, MAX(date_modified) AS latest
          FROM :table_' . self::TABLE . '
          WHERE language_id = :language_id
          GROUP BY entity_id
        ) latest ON e.entity_id = latest.entity_id
                 AND e.date_modified = latest.latest
        WHERE e.language_id = :language_id2
      ');

      $Qvel->bindInt(':language_id',  $languageId);
      $Qvel->bindInt(':language_id2', $languageId);
      $Qvel->execute();

      $row = $Qvel->fetch();
      if (!$row) {
        return $defaults;
      }

      $result = [
        'fast'    => (int) ($row['fast_count']    ?? 0),
        'slow'    => (int) ($row['slow_count']    ?? 0),
        'none'    => (int) ($row['none_count']    ?? 0),
        'no_data' => (int) ($row['no_data_count'] ?? 0),
      ];

      $this->toCache($cacheKey, $result);
      return $result;

    } catch (\Throwable) {
      return $defaults;
    }
  }

  /**
   * Global KPIs: total products analyzed, average scores, last analysis date.
   *
   * @param int $languageId
   * @return array  ['total_products', 'avg_score_x', 'avg_score_y', 'last_analysis', 'total_analyses']
   */
  public function getKpis(int $languageId): array
  {
    $cacheKey = "dashboard_kpis_{$languageId}";
    if ($cached = $this->fromCache($cacheKey)) {
      return $cached;
    }

    $defaults = [
      'total_products'  => 0,
      'total_analyses'  => 0,
      'avg_score_x'     => 0.0,
      'avg_score_y'     => 0.0,
      'last_analysis'   => null,
    ];

    try {
      $Qkpi = $this->db->prepare('
        SELECT
          COUNT(DISTINCT entity_id)                                            AS total_products,
          COUNT(*)                                                             AS total_analyses,
          ROUND(AVG(JSON_EXTRACT(metadata, \'$.scores.score_x\')), 1)         AS avg_score_x,
          ROUND(AVG(JSON_EXTRACT(metadata, \'$.scores.score_y\')), 1)         AS avg_score_y,
          MAX(date_modified)                                                   AS last_analysis
        FROM :table_' . self::TABLE . '
        WHERE language_id = :language_id
      ');

      $Qkpi->bindInt(':language_id', $languageId);
      $Qkpi->execute();

      $row = $Qkpi->fetch();
      if (!$row) {
        return $defaults;
      }

      $result = [
        'total_products' => (int)   ($row['total_products'] ?? 0),
        'total_analyses' => (int)   ($row['total_analyses'] ?? 0),
        'avg_score_x'    => (float) ($row['avg_score_x']    ?? 0),
        'avg_score_y'    => (float) ($row['avg_score_y']    ?? 0),
        'last_analysis'  => $row['last_analysis'] ?? null,
      ];

      $this->toCache($cacheKey, $result);
      return $result;

    } catch (\Throwable) {
      return $defaults;
    }
  }

  /**
   * Invalidate all dashboard caches (call after cron run or manual analysis).
   */
  public function clearCache(): void
  {
    // ClicShopping Cache class does not support namespace-wide clear,
    // so we clear known keys for common language IDs.
    // For a full clear, restart the cache backend or let TTL expire.
    foreach ([1, 2, 3] as $lid) {
      foreach (['quadrant', 'top_y', 'velocity', 'kpis'] as $key) {
        try {
          (new Cache("dashboard_{$key}_{$lid}", self::CACHE_NS))->clear();
        } catch (\Throwable) {
        }
      }
    }
  }

  // ── Private cache helpers ──────────────────────────────────────────────────

  private function fromCache(string $key): mixed
  {
    try {
      $cache = new Cache($key, self::CACHE_NS);
      if ($cache->exists(self::CACHE_TTL)) {
        $data = $cache->get();
        if ($data !== null) {
          return $data;
        }
      }
    } catch (\Throwable) {
    }
    return null;
  }

  private function toCache(string $key, mixed $data): void
  {
    try {
      (new Cache($key, self::CACHE_NS))->save($data);
    } catch (\Throwable) {
    }
  }
}
