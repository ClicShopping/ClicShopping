<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO;

use ClicShopping\OM\Registry;
use ClicShopping\OM\HTTP;

/**
 * SeoCategoryContextBuilder
 *
 * Builds the rich context array consumed by SeoEntityAdapter::getAdditionalContext()
 * for category entity types.
 *
 * Used by Phase 3 (schema.org BreadcrumbList + ItemList generation) and
 * Phase 4 (thin content — category body description generation).
 *
 * Data fetched per category:
 *   top_products   — up to 10 active products sorted by sales_count desc,
 *                    with name, url, image, price, currency.
 *   subcategories  — immediate child categories with name and url.
 *   breadcrumb_path — ancestor chain from root to current category.
 *   product_count  — total active products directly in this category.
 *   url            — canonical storefront URL of the category.
 *   base_url       — storefront base URL.
 *
 * All queries use ClicShopping's :table_xxx placeholders so the
 * DB prefix is resolved transparently by the ORM layer.
 *
 * @package ClicShopping\Apps\AI\Ecommerce\Classes\ClicShoppingAdmin\SEO
 * @since   2026-03-04
 */
class SeoCategoryContextBuilder
{
  private mixed  $db;
  private bool   $debug;
  private string $baseUrl;

  public function __construct()
  {
    $this->db      = Registry::get('Db');
    $this->debug   = defined('CLICSHOPPING_APP_CHATGPT_CH_DEBUG')
      && CLICSHOPPING_APP_CHATGPT_CH_DEBUG === 'True';
    $this->baseUrl = HTTP::getShopUrlDomain();
  }

  // ────────────────────────────────────────────────────────────────────────────
  // Public API
  // ────────────────────────────────────────────────────────────────────────────

  /**
   * Returns the full enriched context array for a category.
   *
   * This array is merged into $current inside SeoAgenticPipeline::optimize()
   * so all keys are directly accessible to agents via $params['current_content'].
   *
   * @param int $categoryId  CMS category ID
   * @param int $languageId  Active language ID
   * @return array
   */
  public function build(int $categoryId, int $languageId): array
  {
    $categoryUrl = $this->baseUrl . 'index.php?cPath=' . $categoryId;

    return [
      'url'             => $categoryUrl,
      'base_url'        => $this->baseUrl,
      'top_products'    => $this->fetchTopProducts($categoryId, $languageId),
      'subcategories'   => $this->fetchSubcategories($categoryId, $languageId),
      'breadcrumb_path' => $this->fetchBreadcrumb($categoryId, $languageId),
      'product_count'   => $this->fetchProductCount($categoryId),
    ];
  }

  // ────────────────────────────────────────────────────────────────────────────
  // T4.3 — Top products (schema ItemList + body description context)
  // ────────────────────────────────────────────────────────────────────────────

  /**
   * Fetches up to 10 active products directly in the category,
   * sorted by number of products sold descending.
   *
   * Each product entry:
   *   name     (string) — localized product name
   *   url      (string) — canonical storefront URL
   *   image    (string) — absolute image URL (empty if no image set)
   *   price    (string) — formatted price with currency symbol
   *   currency (string) — ISO 4217 currency code
   *
   * @param int $categoryId
   * @param int $languageId
   * @return array<int, array{name: string, url: string, image: string, price: string, currency: string}>
   */
  private function fetchTopProducts(int $categoryId, int $languageId): array
  {
    $products = [];

    try {
      $Qproducts = $this->db->prepare('
        SELECT
          p.products_id,
          pd.products_name           AS name,
          p.products_price           AS price,
          p.products_image           AS image,
          p.products_sold            AS sales_count
        FROM :table_products p
        INNER JOIN :table_products_description  pd ON pd.products_id   = p.products_id
                                                   AND pd.language_id  = :language_id
        INNER JOIN :table_products_to_categories pc ON pc.products_id  = p.products_id
                                                    AND pc.categories_id = :category_id
        WHERE p.products_status = 1
        ORDER BY p.products_sold DESC, p.products_id ASC
        LIMIT 10
      ');
      $Qproducts->bindInt(':language_id',  $languageId);
      $Qproducts->bindInt(':category_id',  $categoryId);
      $Qproducts->execute();

      while ($Qproducts->fetch()) {
        $productId = $Qproducts->valueInt('products_id');
        $rawPrice  = $Qproducts->valueDecimal('price');
        $image     = $Qproducts->value('image');

        $products[] = [
          'name'     => $Qproducts->value('name'),
          'url'      => $this->baseUrl . 'index.php?products_id=' . $productId,
          'image'    => $image !== '' ? $this->baseUrl . 'images/' . $image : '',
          'price'    => number_format((float)$rawPrice, 2),
          'currency' => $this->getDefaultCurrency(),
        ];
      }
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[SeoCategoryContextBuilder] fetchTopProducts error: ' . $e->getMessage());
      }
    }

    return $products;
  }

  // ────────────────────────────────────────────────────────────────────────────
  // T4.3 — Subcategories (body description context)
  // ────────────────────────────────────────────────────────────────────────────

  /**
   * Fetches immediate child categories (depth = 1) of the given category.
   *
   * Each entry:
   *   name (string) — localized category name
   *   url  (string) — canonical storefront URL using cPath
   *
   * @param int $categoryId
   * @param int $languageId
   * @return array<int, array{name: string, url: string}>
   */
  private function fetchSubcategories(int $categoryId, int $languageId): array
  {
    $subcategories = [];

    try {
      $Qsubs = $this->db->prepare('
        SELECT
          c.categories_id,
          cd.categories_name AS name
        FROM :table_categories c
        INNER JOIN :table_categories_description cd ON cd.categories_id = c.categories_id
                                                    AND cd.language_id  = :language_id
        WHERE c.parent_id       = :parent_id
          AND c.categories_status = 1
        ORDER BY c.sort_order ASC, cd.categories_name ASC
        LIMIT 20
      ');
      $Qsubs->bindInt(':language_id', $languageId);
      $Qsubs->bindInt(':parent_id',   $categoryId);
      $Qsubs->execute();

      while ($Qsubs->fetch()) {
        $subId          = $Qsubs->valueInt('categories_id');
        $subcategories[] = [
          'name' => $Qsubs->value('name'),
          'url'  => $this->baseUrl . 'index.php?cPath=' . $subId,
        ];
      }
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[SeoCategoryContextBuilder] fetchSubcategories error: ' . $e->getMessage());
      }
    }

    return $subcategories;
  }

  // ────────────────────────────────────────────────────────────────────────────
  // T4.3 — Breadcrumb path (schema BreadcrumbList)
  // ────────────────────────────────────────────────────────────────────────────

  /**
   * Builds the full ancestor chain from the root down to the given category.
   *
   * Uses iterative parent_id traversal (max 10 levels) to avoid recursion
   * issues on deeply nested catalogs.
   *
   * Each entry:
   *   name (string) — localized category name
   *   url  (string) — storefront URL (using cPath of that ancestor)
   *
   * The result is ordered from root (position 1) to current category (last).
   *
   * @param int $categoryId
   * @param int $languageId
   * @return array<int, array{name: string, url: string}>
   */
  private function fetchBreadcrumb(int $categoryId, int $languageId): array
  {
    $chain    = [];
    $current  = $categoryId;
    $maxDepth = 10;
    $seen     = [];

    while ($current > 0 && $maxDepth-- > 0) {
      if (isset($seen[$current])) {
        break; // circular reference guard
      }
      $seen[$current] = true;

      try {
        $Qcat = $this->db->prepare('
          SELECT
            c.parent_id,
            cd.categories_name AS name
          FROM :table_categories c
          INNER JOIN :table_categories_description cd ON cd.categories_id = c.categories_id
                                                      AND cd.language_id  = :language_id
          WHERE c.categories_id = :category_id
          LIMIT 1
        ');
        $Qcat->bindInt(':language_id', $languageId);
        $Qcat->bindInt(':category_id', $current);
        $Qcat->execute();

        if (!$Qcat->fetch()) {
          break;
        }

        $chain[] = [
          'name' => $Qcat->value('name'),
          'url'  => $this->baseUrl . 'index.php?cPath=' . $current,
        ];

        $parentId = $Qcat->valueInt('parent_id');
        $current  = $parentId;

      } catch (\Throwable $e) {
        if ($this->debug) {
          error_log('[SeoCategoryContextBuilder] fetchBreadcrumb error at id=' . $current . ': ' . $e->getMessage());
        }
        break;
      }
    }

    // Chain was built leaf→root; reverse to get root→leaf order
    return array_reverse($chain);
  }

  // ────────────────────────────────────────────────────────────────────────────
  // T4.3 — Product count (scoring + description context)
  // ────────────────────────────────────────────────────────────────────────────

  /**
   * Returns the total number of active products directly assigned to the category.
   *
   * @param int $categoryId
   * @return int
   */
  private function fetchProductCount(int $categoryId): int
  {
    try {
      $Qcount = $this->db->prepare('
        SELECT COUNT(p.products_id) AS total
        FROM :table_products p
        INNER JOIN :table_products_to_categories pc ON pc.products_id   = p.products_id
                                                    AND pc.categories_id = :category_id
        WHERE p.products_status = 1
      ');
      $Qcount->bindInt(':category_id', $categoryId);
      $Qcount->execute();

      if ($Qcount->fetch()) {
        return $Qcount->valueInt('total');
      }
    } catch (\Throwable $e) {
      if ($this->debug) {
        error_log('[SeoCategoryContextBuilder] fetchProductCount error: ' . $e->getMessage());
      }
    }

    return 0;
  }

  // ────────────────────────────────────────────────────────────────────────────
  // Helpers
  // ────────────────────────────────────────────────────────────────────────────

  /**
   * Returns the ISO 4217 code of the default store currency.
   * Falls back to 'EUR' if the currencies table is unavailable.
   */
  private function getDefaultCurrency(): string
  {
    static $currency = null;

    if ($currency !== null) {
      return $currency;
    }

    try {
      $Qcur = $this->db->prepare('
        SELECT code
        FROM :table_currencies
        WHERE `default` = 1
        LIMIT 1
      ');
      $Qcur->execute();

      $currency = $Qcur->fetch() ? $Qcur->value('code') : 'EUR';
    } catch (\Throwable) {
      $currency = 'EUR';
    }

    return $currency;
  }
}
