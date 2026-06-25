<?php
  /**
   * Copyright (c) 2008–2026 Loic Richard
   *
   * Licensed under AGPLv3 or commercial license.
   * See LICENSE file.
   */

  declare(strict_types=1);

  namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\Shop\Llms;

  use ClicShopping\OM\Registry;

/**
 * Provides product data for LLM integrations.
 *
 * Retrieves the most popular active products available in the catalog,
 * filtered by language and visibility constraints.
 */
final class ProductProvider
{
  /**
   * Maximum number of products returned per request.
   */
  private const int LIMIT = 100;

  /**
   * Database connection instance.
   */
  private readonly object $db;

  /**
   * Initializes the provider with the application database service.
   */
  public function __construct()
  {
    $this->db = Registry::get('Db');
  }

  /**
   * Returns the most popular products for a given language.
   *
   * Only products matching the following conditions are returned:
   * - Active (products_status = 1)
   * - Not archived
   * - Visible in the storefront
   * - In stock
   *
   * Results are ordered by the number of times the product has been ordered,
   * from most popular to least popular.
   *
   * @param int $languageId Language identifier used to retrieve localized
   *                        product names and descriptions.
   *
   * @return array<int, array{
   *     id:int,
   *     name:string,
   *     description:string
   * }>
   */
  public function getPopularProducts(int $languageId): array
  {
    $products = [];

    $Qproducts = $this->db->prepare(
      'SELECT p.products_id,
                  pd.products_name,
                  pd.products_description
             FROM :table_products p,
                  :table_products_description pd
            WHERE p.products_id     = pd.products_id
              AND pd.language_id    = :language_id
              AND p.products_status = 1
              AND p.products_archive = 0
              AND p.products_view    = 1
              AND p.products_quantity > 0
            ORDER BY p.products_ordered DESC
            LIMIT :limit'
    );

    $Qproducts->bindInt(':language_id', $languageId);
    $Qproducts->bindInt(':limit', self::LIMIT);

    $Qproducts->execute();

    while ($Qproducts->fetch()) {
      $products[] = [
        'id' => $Qproducts->valueInt('products_id'),
        'name' => $Qproducts->value('products_name'),
        'description' => strip_tags($Qproducts->value('products_description')),
      ];
    }

    return $products;
  }
}