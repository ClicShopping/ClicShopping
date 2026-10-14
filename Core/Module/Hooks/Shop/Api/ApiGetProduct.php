<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Api;

use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiSecurity;

class ApiGetProduct
{
  /**
   * Retrieves product data based on product ID and language ID.
   *
   * @param int|string $id The product ID. If numeric, it is used to filter the query.
   * @param int|string $language_id The language ID. If numeric, it is used to filter the query.
   * @return array Returns an array of product information including various product attributes
   *               such as name, description, SKU, UPC, dimensions, and price.
   */
  private static function products(mixed $id = null, mixed $language_id = null): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $sql = 'SELECT p.*, pd.*
          FROM :table_products p
          JOIN :table_products_description pd ON p.products_id = pd.products_id
          WHERE 1
          limit 100
          ';

    $params = [];

    if ($id !== null && $id !== 'All' && is_numeric($id)) {
      $sql .= ' AND p.products_id = :products_id';
      $params[':products_id'] = (int)$id;
    }

    if ($language_id !== null && is_numeric($language_id)) {
      $sql .= ' AND pd.language_id = :language_id';
      $params[':language_id'] = (int)$language_id;
    }

    $Qapi = $CLICSHOPPING_Db->prepare($sql);

    foreach ($params as $key => $value) {
      $Qapi->bindInt($key, $value);
    }

    $Qapi->execute();

    $product_data = [];

    foreach ($Qapi->fetchAll() as $row) {
      $product_data[] = [
        'products_id' => $row['products_id'],
        'language_id' => $row['language_id'],
        'products_name' => $row['products_name'],
        'products_description' => $row['products_description'],
        'products_model' => $row['products_model'],
        'products_quantity' => $row['products_quantity'],
        'products_weight' => $row['products_weight'],
        'products_quantity_alert' => $row['products_quantity_alert'],
        'products_sku' => $row['products_sku'],
        'products_upc' => $row['products_upc'],
        'products_ean' => $row['products_ean'],
        'products_jan' => $row['products_jan'],
        'products_isbn' => $row['products_isbn'],
        'products_mpn' => $row['products_mpn'],
        'products_price' => $row['products_price'],
        'products_dimension_width' => $row['products_dimension_width'],
        'products_dimension_height' => $row['products_dimension_height'],
        'products_dimension_depth' => $row['products_dimension_depth'],
        'products_volume' => $row['products_volume'],
      ];
    }

    return $product_data;
  }

  /**
   * Executes the API call to retrieve product data.
   *
   * @return array|false An array of product data or false if parameters are missing.
   */
  public function execute()
  {
    if (!isset($_GET['pId'])) {
      return false;
    }

    if (ApiSecurity::authenticateRequest('get_product') === false) {
      return false;
    }

    $id = HTML::sanitize($_GET['pId']);
    ApiSecurity::secureGetId($id);

    $language_id = isset($_GET['lId']) ? HTML::sanitize($_GET['lId']) : null;

    return self::products($id, $language_id);
  }
}
