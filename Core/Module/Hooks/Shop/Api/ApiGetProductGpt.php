<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Api;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiSecurity;

class ApiGetProductGpt
{
  /**
   * Executes the API call to retrieve product data.
   *
   */
  public function execute()
  {
    if (!isset($_GET['pId'])) {
      return false;
    }

    if (ApiSecurity::authenticateRequest('get_gpt_product') === false) {
      return false;
    }

    $id = HTML::sanitize($_GET['pId']);
    ApiSecurity::secureGetId($id);

    $language_id = isset($_GET['lId']) ? HTML::sanitize($_GET['lId']) : null;

    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_ProductsCommon = Registry::get('ProductsCommon');

    $sql = 'SELECT p.products_id,
                   p.products_model,
                   p.products_sku,
                   p.products_ean,
                   CAST(p.products_price AS DECIMAL(15,4)) as products_price,
                   p.products_quantity,
                   CAST(p.products_weight AS DECIMAL(15,4)) as products_weight,
                   CAST(p.products_dimension_width AS DECIMAL(15,2)) as products_width,
                   CAST(p.products_dimension_height AS DECIMAL(15,2)) as products_height,
                   CAST(p.products_dimension_depth AS DECIMAL(15,2)) as products_depth,
                   pd.language_id,
                   pd.products_name,
                   pd.products_description
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

    $products = [];

    foreach ($Qapi->fetchAll() as $row) {
      $product_id = $row['products_id'];

      $language_code = $CLICSHOPPING_Language->getLanguageCodeById($row['language_id']);

      if (!isset($products[$product_id])) {
        $products[$product_id] = [
          'id' => $product_id,
          'name' => $row['products_name'],
          'model' => $row['products_model'],
          'sku' => $row['products_sku'],
          'ean' => $row['products_ean'],
          'price' => number_format($row['products_price'], 4, '.', ''),
          'quantity' => (int)$row['products_quantity'],
          'weight' => number_format($row['products_weight'], 4, '.', ''),
          'image' => CLICSHOPPING::getConfig('http_server') .CLICSHOPPING::getConfig('http_path', 'Shop') . 'sources/images/' . $CLICSHOPPING_ProductsCommon->getProductsImage($product_id),
          'dimensions' => [
            'width' => number_format($row['products_width'], 2, '.', ''),
            'height' => number_format($row['products_height'], 2, '.', ''),
            'depth' => number_format($row['products_depth'], 2, '.', ''),
            'volume' => number_format(($row['products_width'] * $row['products_height'] * $row['products_depth']), 2, '.', '')
          ],
          'localizations' => [],  // Initialize empty localizations
          'product_url' => []  // Initialize url
        ];
      }

      // Ajouter la traduction dans le tableau 'localizations'
      $products[$product_id]['localizations'][$language_code] = [
        'name' => $row['products_name'],
        'description' => html_entity_decode(strip_tags($row['products_description']), ENT_QUOTES, 'UTF-8')
      ];

      $products[$product_id]['product_url'][$language_code] = [
        'product_url' => CLICSHOPPING::link('index.php', 'Products&Description&Id=' . $product_id .'&language=' . $language_code),
      ];
    }

    $result = json_encode($products);

    if (json_last_error() !== JSON_ERROR_NONE) {
      return json_encode(['error' => 'JSON encoding failed', 'message' => json_last_error_msg()]);
    }

    return $result;
  }
}
