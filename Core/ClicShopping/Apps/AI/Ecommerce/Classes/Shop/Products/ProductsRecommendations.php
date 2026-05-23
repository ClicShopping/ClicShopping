<?php
/**
 * ProductsRecommendations
 *
 * Service centralisé de recommandations produits pour ClicShopping.
 * Gère trois contextes : product, cart, checkout, home
 * Gère trois modes    : cas1 (co-achat global), cas2 (historique perso), fallback (bestsellers)
 *
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
  namespace ClicShopping\Apps\AI\Ecommerce\Classes\Shop\Products;

  use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
  use ClicShopping\OM\CLICSHOPPING;
  use ClicShopping\OM\Registry;

  class ProductsRecommendations
  {
    // Minimum number of orders required to activate Case 2 (personal history)
    const MIN_ORDERS_FOR_PERSONAL = 3;

    // Default anti-clone threshold if $cosinus is not provided (avoids very close substitutes)
    const MIN_COSINE_DISTANCE = 0.15;

    // Minimum co-occurrence score for Case 2 (filters weak associations)
    const MIN_RELEVANCE_SCORE = 2;

    public function __construct() {
    }

    /*
     * Checks that all required applications are active.
     * Called at the beginning of get() — centralizes validation for all modules.
     *
     * @return bool  true = all active, false = at least one missing
     */
    public function checkStatus(): bool
    {
      $requiredConstants = [
        'CLICSHOPPING_APP_ECOMMERCE_CAI_STATUS',
        'CLICSHOPPING_APP_ECOMMERCE_EC_STATUS',
        'CLICSHOPPING_APP_CHATGPT_RA_OPENAI_EMBEDDING',
        'CLICSHOPPING_APP_CHATGPT_RA_STATUS',
      ];

      $result = CLICSHOPPING::checkAppsIsActivated($requiredConstants);

      if ($result === false) {
        return false;
      }

      if (!Gpt::checkGptStatus()) {
        return false;
      }

      return true;
    }

    /**
     * Called from any page via Registry::get('ProductsRecommendations')->get([...])
     *
     * @param array $params [
     *   'context'      => string   'product'|'cart'|'checkout'|'home'
     *   'product_ids'  => array    list of reference product IDs
     *   'customer_id'  => int|null null if guest
     *   'group_id'     => int      0 = B2C, >0 = B2B
     *   'language_id'  => int
     *   'limit'        => int
     *   'cosinus'      => float    cosine threshold configured in module (e.g. 0.35)
     * ]
     * @return array  list of recommended product IDs (ordered by score)
     */
    public function get(array $params): array
    {
      // Centralized validation: if a required app is inactive → return empty
      if (!$this->checkStatus()) {
        return [];
      }

      $customer_id = $params['customer_id'] ?? null;
      $group_id    = $params['group_id']    ?? 0;
      $language_id = $params['language_id'] ?? 1;
      $limit       = $params['limit']       ?? 6;
      $product_ids = $params['product_ids'] ?? [];
      $cosinus     = $params['cosinus']     ?? self::MIN_COSINE_DISTANCE;

      if (empty($product_ids)) {
        return [];
      }

      // --- Routing Case 1 / Case 2 ---
      $mode = self::resolveMode($customer_id);

      if ($mode === 'personal') {
        // Case 2: recommendations based on customer history
        $results = self::getPersonalRecommendations(
          $customer_id, $product_ids, $group_id, $language_id, $limit, $cosinus
        );

        // Insufficient results → fallback to Case 1
        if (count($results) < max(1, (int)($limit / 2))) {
          $results = self::getComplementaryRecommendations(
            $product_ids, $group_id, $language_id, $limit, $cosinus
          );
        }
      } else {
        // Case 1: global co-purchase (guest or insufficient history)
        $results = self::getComplementaryRecommendations(
          $product_ids, $group_id, $language_id, $limit, $cosinus
        );
      }

      // Final fallback: bestsellers filtered by group and language
      if (empty($results)) {
        $results = self::getFallbackBestsellers($product_ids, $group_id, $language_id, $limit);
      }

      return $results;
    }

    // -------------------------------------------------------------------------
    // Mode resolution
    // -------------------------------------------------------------------------

    /**
     * Determines whether to use Case 1 or Case 2.
     */
    private static function resolveMode(?int $customer_id): string
    {
      if ($customer_id === null) {
        return 'complementary'; // Guest → Case 1
      }

      $orderCount = self::countCustomerOrders($customer_id);

      return ($orderCount >= self::MIN_ORDERS_FOR_PERSONAL)
        ? 'personal'       // Case 2
        : 'complementary'; // Case 1 (logged-in but insufficient history)
    }

    /**
     * Counts validated orders for a given customer.
     */
    private static function countCustomerOrders(int $customer_id): int
    {
      $CLICSHOPPING_Db = Registry::get('Db');

      $Q = $CLICSHOPPING_Db->prepare('
      SELECT COUNT(DISTINCT orders_id)
      FROM :table_orders
      WHERE customers_id = :customers_id
        AND orders_status >= 2
    ');
      $Q->bindInt(':customers_id', $customer_id);
      $Q->execute();

      return (int)$Q->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // CASE 1: Global complementarity (co-purchase + hybrid score)
    // -------------------------------------------------------------------------

    /**
     * $cosinus is used as an anti-clone threshold:
     * only products with cosine distance > $cosinus are retained.
     * The higher the value, the more similar products are excluded.
     */
    private static function getComplementaryRecommendations(
      array $product_ids,
      int   $group_id,
      int   $language_id,
      int   $limit,
      float $cosinus
    ): array {
      $CLICSHOPPING_Db = Registry::get('Db');

      // Build a comma-separated list of product IDs for SQL IN clauses
      $placeholders = implode(',', array_map('intval', $product_ids));

      // Use the first product as the reference (anchor) for embedding similarity
      $primary_id   = (int)$product_ids[0];

      if ($group_id == 0) {
        // ---- B2C ----
        $sql = "
        WITH target AS (
          -- Retrieve embedding and price for the reference product
          SELECT pe.embedding, p.products_price
          FROM :table_products_embedding pe
          JOIN :table_products p ON p.products_id = pe.entity_id
          WHERE pe.entity_id = :primary_id
            AND pe.language_id = :language_id
          LIMIT 1
        )

        SELECT DISTINCT
          p.products_id,

          -- Cosine distance between candidate and target product
          VEC_DISTANCE_COSINE(pe.embedding, t.embedding) AS dist,

          -- Co-occurrence signal (global complementarity)
          COALESCE(pc.score, 0) AS complementarity,

          -- Hybrid ranking score (complementarity dominates)
          (
            0.15 * (1 - VEC_DISTANCE_COSINE(pe.embedding, t.embedding)) +
            0.55 * COALESCE(pc.score, 0) +
            0.10 * LOG(1 + p.products_ordered) +
            0.10 * p.products_price -
            0.30 * LEAST(1, p.products_price / NULLIF(t.products_price, 0))
          ) AS score

        FROM :table_products p
        JOIN :table_products_embedding pe ON pe.entity_id = p.products_id
          AND pe.language_id = :language_id
        JOIN :table_products_to_categories p2c ON p.products_id = p2c.products_id
        JOIN :table_categories c ON p2c.categories_id = c.categories_id

        -- Inject target embedding into query scope
        CROSS JOIN target t

        -- Join co-occurrence table (multiple source products supported)
        LEFT JOIN :table_products_cooccurrence pc
          ON pc.product_id IN ({$placeholders})
         AND pc.related_id = p.products_id

        WHERE p.products_status = 1
          AND p.products_archive = 0
          AND p.products_view = 1

          -- Exclude source products
          AND p.products_id NOT IN ({$placeholders})

          AND c.status = 1

          -- Strict exclusion of substitutes (same categories)
          AND p2c.categories_id NOT IN (
            SELECT p2c2.categories_id
            FROM :table_products_to_categories p2c2
            WHERE p2c2.products_id IN ({$placeholders})
          )

          -- Anti-clone filter using cosine distance threshold
          AND VEC_DISTANCE_COSINE(pe.embedding, t.embedding) > :min_cosine

        ORDER BY complementarity DESC, score DESC
        LIMIT :limit
      ";
      } else {
        // ---- B2B ----
        $sql = "
        WITH target AS (
          -- Retrieve embedding and price for the reference product
          SELECT pe.embedding, p.products_price
          FROM :table_products_embedding pe
          JOIN :table_products p ON p.products_id = pe.entity_id
          WHERE pe.entity_id = :primary_id
            AND pe.language_id = :language_id
          LIMIT 1
        )

        SELECT DISTINCT
          p.products_id,

          -- Cosine distance between candidate and target product
          VEC_DISTANCE_COSINE(pe.embedding, t.embedding) AS dist,

          -- Co-occurrence signal (global complementarity)
          COALESCE(pc.score, 0) AS complementarity,

          -- Hybrid ranking score
          (
            0.15 * (1 - VEC_DISTANCE_COSINE(pe.embedding, t.embedding)) +
            0.55 * COALESCE(pc.score, 0) +
            0.10 * LOG(1 + p.products_ordered) +
            0.10 * p.products_price -
            0.30 * LEAST(1, p.products_price / NULLIF(t.products_price, 0))
          ) AS score

        FROM :table_products p
        JOIN :table_products_groups g ON p.products_id = g.products_id
        JOIN :table_products_embedding pe ON pe.entity_id = p.products_id
          AND pe.language_id = :language_id
        JOIN :table_products_to_categories p2c ON p.products_id = p2c.products_id
        JOIN :table_categories c ON p2c.categories_id = c.categories_id

        CROSS JOIN target t

        LEFT JOIN :table_products_cooccurrence pc
          ON pc.product_id IN ({$placeholders})
         AND pc.related_id = p.products_id

        WHERE p.products_status = 1
          AND p.products_archive = 0
          AND p.products_view = 1

          -- B2B visibility constraints
          AND g.customers_group_id = :customers_group_id
          AND g.products_group_view = 1
          AND g.price_group_view = 1

          -- Exclude source products
          AND p.products_id NOT IN ({$placeholders})

          AND c.status = 1

          -- Exclude substitutes (same categories)
          AND p2c.categories_id NOT IN (
            SELECT p2c2.categories_id
            FROM :table_products_to_categories p2c2
            WHERE p2c2.products_id IN ({$placeholders})
          )

          -- Anti-clone cosine filter
          AND VEC_DISTANCE_COSINE(pe.embedding, t.embedding) > :min_cosine

        ORDER BY complementarity DESC, score DESC
        LIMIT :limit
      ";
      }

      $Q = $CLICSHOPPING_Db->prepare($sql);
      $Q->bindInt(':primary_id',   $primary_id);
      $Q->bindInt(':language_id',  $language_id);
      $Q->bindInt(':limit',        $limit);

      // Cosine threshold from module configuration (not constant)
      $Q->bindDecimal(':min_cosine', $cosinus);

      if ($group_id != 0) {
        $Q->bindInt(':customers_group_id', $group_id);
      }

      $Q->execute();

      $results = [];
      while ($Q->fetch()) {
        $results[] = $Q->valueInt('products_id');
      }

      return $results;
    }

    // -------------------------------------------------------------------------
    // CASE 2: Personal history-based recommendations
    // -------------------------------------------------------------------------

    /**
     * Uses co-purchase patterns from similar customers.
     *
     * $cosinus acts here as a multiplier for the minimum relevance threshold.
     * Higher value = stricter filtering.
     */
    private static function getPersonalRecommendations(
      int   $customer_id,
      array $product_ids,
      int   $group_id,
      int   $language_id,
      int   $limit,
      float $cosinus
    ): array {
      $CLICSHOPPING_Db = Registry::get('Db');

      // Build list of product IDs to exclude
      $placeholders = implode(',', array_map('intval', $product_ids));

      // Adaptive threshold based on cosine parameter
      $min_relevance = max(self::MIN_RELEVANCE_SCORE, (int)round($cosinus * 10));

      if ($group_id == 0) {
        // ---- B2C ----
        $sql = "
        SELECT DISTINCT
          op_rec.products_id,
          COUNT(*) AS relevance_score

        FROM :table_orders o_ref
        JOIN :table_orders_products op_ref ON o_ref.orders_id = op_ref.orders_id

        -- Customers who bought the same products
        JOIN :table_orders_products op_similar
          ON op_ref.products_id = op_similar.products_id
         AND op_similar.orders_id != o_ref.orders_id

        -- Products also purchased by those similar customers
        JOIN :table_orders_products op_rec ON op_similar.orders_id = op_rec.orders_id

        JOIN :table_products p ON p.products_id = op_rec.products_id
        JOIN :table_products_to_categories p2c ON p.products_id = p2c.products_id
        JOIN :table_categories c ON p2c.categories_id = c.categories_id

        WHERE o_ref.customers_id = :customer_id
          AND p.products_status = 1
          AND p.products_archive = 0
          AND p.products_view = 1
          AND c.status = 1

          -- Exclude products already purchased by the customer
          AND op_rec.products_id NOT IN (
            SELECT op2.products_id
            FROM :table_orders o2
            JOIN :table_orders_products op2 ON o2.orders_id = op2.orders_id
            WHERE o2.customers_id = :customer_id
          )

          -- Exclude current context products
          AND op_rec.products_id NOT IN ({$placeholders})

          -- Exclude same-category substitutes
          AND p2c.categories_id NOT IN (
            SELECT p2c2.categories_id
            FROM :table_products_to_categories p2c2
            WHERE p2c2.products_id IN ({$placeholders})
          )

        GROUP BY op_rec.products_id
        HAVING relevance_score >= :min_relevance
        ORDER BY relevance_score DESC
        LIMIT :limit
      ";
      } else {
        // ---- B2B ----
        $sql = "
        SELECT DISTINCT
          op_rec.products_id,
          COUNT(*) AS relevance_score

        FROM :table_orders o_ref
        JOIN :table_orders_products op_ref ON o_ref.orders_id = op_ref.orders_id

        JOIN :table_orders_products op_similar
          ON op_ref.products_id = op_similar.products_id
         AND op_similar.orders_id != o_ref.orders_id

        JOIN :table_orders_products op_rec ON op_similar.orders_id = op_rec.orders_id

        JOIN :table_products p ON p.products_id = op_rec.products_id
        JOIN :table_products_groups g ON p.products_id = g.products_id
        JOIN :table_products_to_categories p2c ON p.products_id = p2c.products_id
        JOIN :table_categories c ON p2c.categories_id = c.categories_id

        WHERE o_ref.customers_id = :customer_id
          AND p.products_status = 1
          AND p.products_archive = 0
          AND p.products_view = 1

          -- B2B visibility constraints
          AND g.customers_group_id = :customers_group_id
          AND g.products_group_view = 1
          AND g.price_group_view = 1

          AND c.status = 1

          -- Exclude already purchased products
          AND op_rec.products_id NOT IN (
            SELECT op2.products_id
            FROM :table_orders o2
            JOIN :table_orders_products op2 ON o2.orders_id = op2.orders_id
            WHERE o2.customers_id = :customer_id
          )

          AND op_rec.products_id NOT IN ({$placeholders})

          AND p2c.categories_id NOT IN (
            SELECT p2c2.categories_id
            FROM :table_products_to_categories p2c2
            WHERE p2c2.products_id IN ({$placeholders})
          )

        GROUP BY op_rec.products_id
        HAVING relevance_score >= :min_relevance
        ORDER BY relevance_score DESC
        LIMIT :limit
      ";
      }

      $Q = $CLICSHOPPING_Db->prepare($sql);
      $Q->bindInt(':customer_id',   $customer_id);
      $Q->bindInt(':limit',         $limit);
      $Q->bindInt(':min_relevance', $min_relevance);

      if ($group_id != 0) {
        $Q->bindInt(':customers_group_id', $group_id);
      }

      $Q->execute();

      $results = [];
      while ($Q->fetch()) {
        $results[] = $Q->valueInt('products_id');
      }

      return $results;
    }

    // -------------------------------------------------------------------------
    // FALLBACK: Bestsellers filtered by group and language
    // -------------------------------------------------------------------------

    /**
     * Final fallback when no results from Case 1 or Case 2.
     * Applies B2B group filtering and language consistency.
     */
    private static function getFallbackBestsellers(
      array $product_ids,
      int   $group_id,
      int   $language_id,
      int   $limit
    ): array {
      $CLICSHOPPING_Db = Registry::get('Db');

    $placeholders = implode(',', array_map('intval', $product_ids));

    if ($group_id == 0) {
      // ---- B2C ----
      $sql = "
        SELECT DISTINCT p.products_id
        FROM :table_products p
        JOIN :table_products_description pd ON p.products_id = pd.products_id
          AND pd.language_id = :language_id
        JOIN :table_products_to_categories p2c ON p.products_id = p2c.products_id
        JOIN :table_categories c ON p2c.categories_id = c.categories_id

        WHERE p.products_status = 1
          AND p.products_archive = 0
          AND p.products_view = 1
          AND c.status = 1
          AND p.products_id NOT IN ({$placeholders})

          AND p2c.categories_id NOT IN (
            SELECT p2c2.categories_id
            FROM :table_products_to_categories p2c2
            WHERE p2c2.products_id IN ({$placeholders})
          )

        ORDER BY p.products_ordered DESC
        LIMIT :limit
      ";
    } else {
      // ---- B2B ----
      $sql = "
        SELECT DISTINCT p.products_id
        FROM :table_products p
        JOIN :table_products_description pd ON p.products_id = pd.products_id
          AND pd.language_id = :language_id
        JOIN :table_products_groups g ON p.products_id = g.products_id
        JOIN :table_products_to_categories p2c ON p.products_id = p2c.products_id
        JOIN :table_categories c ON p2c.categories_id = c.categories_id

        WHERE p.products_status = 1
          AND p.products_archive = 0
          AND p.products_view = 1
          AND g.customers_group_id = :customers_group_id
          AND g.products_group_view = 1
          AND g.price_group_view = 1
          AND c.status = 1
          AND p.products_id NOT IN ({$placeholders})

          AND p2c.categories_id NOT IN (
            SELECT p2c2.categories_id
            FROM :table_products_to_categories p2c2
            WHERE p2c2.products_id IN ({$placeholders})
          )

        ORDER BY p.products_ordered DESC
        LIMIT :limit
      ";
    }

    $Q = $CLICSHOPPING_Db->prepare($sql);
    $Q->bindInt(':language_id', $language_id);
    $Q->bindInt(':limit',       $limit);

    if ($group_id != 0) {
      $Q->bindInt(':customers_group_id', $group_id);
    }

    $Q->execute();

    $results = [];
    while ($Q->fetch()) {
      $results[] = $Q->valueInt('products_id');
    }

    return $results;
  }
}
