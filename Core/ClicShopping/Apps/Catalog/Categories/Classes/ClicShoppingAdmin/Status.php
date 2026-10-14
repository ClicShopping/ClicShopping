<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Classes\ClicShoppingAdmin;

use ClicShopping\Apps\Catalog\Categories\Classes\Shop\CategoryTree;
use ClicShopping\OM\Registry;

/**
 * Handles the status of categories by enabling or disabling them based on the provided status.
 */
class Status
{
  protected int $status;
  protected int $categories_id;

  /**
   * Updates the status of a category and its subcategories in the database.
   *
   * @param int $categories_id The ID of the category whose status is to be updated.
   * @param int $status The status to be assigned to the category and its subcategories (1 for active, 0 for inactive).
   * @return int Returns -1 if the status provided is invalid; otherwise, no value is returned.
   */
  public static function getCategoriesStatus(int $categories_id, int $status)
  {
    $CLICSHOPPING_Db = Registry::get('Db');

    if (Registry::exists('CategoryTree')) {
      $CLICSHOPPING_CategoryTree = Registry::get('CategoryTree');
    } else {
      $CLICSHOPPING_CategoryTree = new CategoryTree();
      Registry::set('CategoryTree', $CLICSHOPPING_CategoryTree);
    }

    if ($status == 1) {
      $data = ['id' => $categories_id];

      foreach (array_merge(array($data['id']), $CLICSHOPPING_CategoryTree->getChildren($data['id'])) as $c) {
        $sql_array = [
          'status' => 1,
          'last_modified' => 'now()'
        ];

        $update_array = ['categories_id' => (int)$c];

        $CLICSHOPPING_Db->save('categories', $sql_array, $update_array);
      }

    } elseif ($status == 0) {
      $data = ['id' => $categories_id];

      foreach (array_merge(array($data['id']), $CLICSHOPPING_CategoryTree->getChildren($data['id'])) as $c) {
        $sql_array = [
          'status' => 0,
          'last_modified' => 'now()'
        ];

        $update_array = ['categories_id' => (int)$c];

        $CLICSHOPPING_Db->save('categories', $sql_array, $update_array);
      }
    } else {
      return -1;
    }
  }
}