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

class ApiGetCategories
{
  /**
   * Retrieves category data based on category ID and language ID.
   *
   * @param int|string $id The category ID. If numeric, it is used to filter the query.
   * @param int|string $language_id The language ID. If numeric, it is used to filter the query.
   * @return array Returns an array of category information including various attributes
   *               such as name, description, and parent ID.
   */
  private static function categories(mixed $id = null, mixed $language_id = null): array
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    $sql = 'SELECT c.*, 
                   cd.*
            FROM :table_categories c
            JOIN :table_categories_description cd ON c.categories_id = cd.categories_id
            WHERE 1
            limit 100';

    $params = [];

    if ($id !== null && $id !== 'All' && is_numeric($id)) {
      $sql .= ' AND c.categories_id = :categories_id';
      $params[':categories_id'] = (int)$id;
    }

    if ($language_id !== null && is_numeric($language_id)) {
      $sql .= ' AND cd.language_id = :language_id';
      $params[':language_id'] = (int)$language_id;
    }

    $Qapi = $CLICSHOPPING_Db->prepare($sql);

    foreach ($params as $key => $value) {
      $Qapi->bindInt($key, $value);
    }

    $Qapi->execute();

    $categories_data = [];

    foreach ($Qapi->fetchAll() as $row) {
      $categories_data[] = [
        'categories_id' => $row['categories_id'],
        'parent_id' => $row['parent_id'],
        'language_id' => $row['language_id'],
        'categories_name' => $row['categories_name'],
        'categories_description' => $row['categories_description'],
        'categories_seo_url' => $row['categories_seo_url'],
        'categories_head_title_tag' => $row['categories_head_title_tag'],
        'categories_head_desc_tag' => $row['categories_head_desc_tag'],
        'categories_head_keywords_tag' => $row['categories_head_keywords_tag']
      ];
    }

    return $categories_data;
  }

  /**
   * Executes the API call to retrieve category data.
   *
   * @return array|false An array containing category data or false if the token is not set.
   */
  public function execute()
  {
    if (ApiSecurity::authenticateRequest('get_categories') === false) {
      return false;
    }

    $id = isset($_GET['cId']) ? HTML::sanitize($_GET['cId']) : null;
    ApiSecurity::secureGetId($id);

    $language_id = isset($_GET['lId']) ? HTML::sanitize($_GET['lId']) : null;

    return self::categories($id, $language_id);
  }
}
