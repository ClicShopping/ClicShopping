<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Module\Hooks\Shop\Api;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\Api\Classes\Shop\ApiSecurity;
use function count;

use ClicShopping\Apps\Catalog\Categories\Classes\ClicShoppingAdmin\CategoriesAdmin;

class ApiDeleteCategories
{
  /**
   * Deletes a category and its associated data, including related products if no other categories
   * are linked to them, along with clearing relevant caches and triggering necessary hooks.
   *
   * @param int $id The ID of the category to be deleted.
   * @return void No return value.
   */
  private static function deleteCategories(int $id): void
  {
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    Registry::set('CategoriesAdmin', new CategoriesAdmin());
    $CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');

    $categories_id = $id;
    $cPath = 0;

    if (isset($categories_id) && is_numeric($categories_id) && isset($cPath)) {
      $categories = $CLICSHOPPING_CategoriesAdmin->getCategoryTree($categories_id, '', '0', '', true);
      $products = [];
      $products_delete = [];

      for ($i = 0, $n = count($categories); $i < $n; $i++) {
        $QproductIds = $CLICSHOPPING_Db->get('products_to_categories', 'products_id', ['categories_id' => (int)$categories[$i]['id']]);

        while ($QproductIds->fetch()) {
          $products[$QproductIds->valueInt('products_id')]['categories'][] = $categories[$i]['id'];
        }
      }

      foreach ($products as $key => $value) {
        $category_ids = '';

        for ($i = 0, $n = count($value['categories']); $i < $n; $i++) {
          $category_ids .= "'" . (int)$value['categories'][$i] . "', ";
        }

        $category_ids = substr($category_ids, 0, -2);

        $Qcheck = $CLICSHOPPING_Db->prepare('select products_id
                                              from :table_products_to_categories
                                              where products_id = :products_id
                                              and categories_id not in (' . $category_ids . ')
                                              limit 1
                                              ');

        $Qcheck->bindInt(':products_id', $key);
        $Qcheck->execute();

        if ($Qcheck->check() === false) {
          $products_delete[$key] = $key;
        }
      }

      for ($i = 0, $n = count($categories); $i < $n; $i++) {
        $CLICSHOPPING_CategoriesAdmin->removeCategory($categories[$i]['id']);
      }

      foreach (array_keys($products_delete) as $key) {
        $CLICSHOPPING_Hooks->call('Products', 'RemoveProduct', ['products_id' => $id]);
      }

      $CLICSHOPPING_Hooks->call('Categories', 'DeleteConfirm');

      Cache::clear('category_tree-');
      Cache::clear('also_purchased');
      Cache::clear('products_related');
      Cache::clear('products_cross_sell');
      Cache::clear('upcoming');
    }
  }

  /**
   * Executes an operation to delete a category identified by a sanitized category ID,
   * if the necessary parameters are present in the input. If the category ID is invalid,
   * returns an error response. If required parameters are missing, the method will return false.
   *
   * @return string|bool Returns a JSON-encoded error message if the category ID is invalid, or
   *                     false if the required parameters are not set.
   */
  public function execute()
  {
    if (isset($_GET['cId'], $_GET['categories'])) {

    if (ApiSecurity::isLocalEnvironment()) {
      ApiSecurity::logSecurityEvent('Local environment detected', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    }

      if (!isset($_GET['token'])) {
        ApiSecurity::logSecurityEvent('Missing token in categories request');
        return false;
      }

      // Check if the token is valid
      $token = ApiSecurity::checkToken($_GET['token']);
      if (!$token) {
        return false;
      }

      // Rate limiting
      $clientIp = HTTP::getIpAddress();
      if (!ApiSecurity::checkRateLimit($clientIp, 'delete_categories')) {
        return false;
      }

      $id = HTML::sanitize($_GET['cId']);

      if (!is_numeric($id)) {
        http_response_code(400);
        return json_encode(['error' => 'Invalid ID format']);
      }

      self::deleteCategories((int)$id);

      http_response_code(200);
      echo json_encode(['success' => 'Category deleted']);
      exit;
    } else {
      return false;
    }
  }
}