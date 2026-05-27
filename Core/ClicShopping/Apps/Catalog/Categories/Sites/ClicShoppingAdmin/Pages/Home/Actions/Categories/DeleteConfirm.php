<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Catalog\Categories\Sites\ClicShoppingAdmin\Pages\Home\Actions\Categories;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class DeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function execute()
  {
    $this->app = Registry::get('Categories');

    if (isset($_GET['categories_id'])) {
      $categories_id = HTML::sanitize($_GET['categories_id']);
    }

    if (isset($_GET['cPath'])) {
      $cPath = HTML::sanitize($_GET['cPath']);
    } else {
      $cPath = 0;
    }

    $CLICSHOPPING_Hooks = Registry::get('Hooks');
    $CLICSHOPPING_CategoriesAdmin = Registry::get('CategoriesAdmin');

    if (isset($categories_id) && is_numeric($categories_id) && isset($cPath)) {
      $categories = $CLICSHOPPING_CategoriesAdmin->getCategoryTree($categories_id, '', '0', '', true);
      $products = [];
      $products_delete = [];

      for ($i = 0, $n = \count($categories); $i < $n; $i++) {
        $QproductIds = $this->app->db->get('products_to_categories', 'products_id', ['categories_id' => (int)$categories[$i]['id']]);

        while ($QproductIds->fetch()) {
          $products[$QproductIds->valueInt('products_id')]['categories'][] = $categories[$i]['id'];
        }
      }

      foreach ($products as $key => $value) {
        $category_ids = '';

        for ($i = 0, $n = \count($value['categories']); $i < $n; $i++) {
          $category_ids .= "'" . (int)$value['categories'][$i] . "', ";
        }

        $category_ids = substr($category_ids, 0, -2);

        $Qcheck = $this->app->db->prepare('select products_id
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

      for ($i = 0, $n = \count($categories); $i < $n; $i++) {
        $CLICSHOPPING_CategoriesAdmin->removeCategory($categories[$i]['id']);
      }

      foreach (array_keys($products_delete) as $key) {
        $CLICSHOPPING_Hooks->call('Products', 'RemoveProduct', ['products_id' => (int)$key]);
      }

      $CLICSHOPPING_Hooks->call('Categories', 'DeleteConfirm');

      Cache::clear('category_tree-');
      Cache::clear('also_purchased');
      Cache::clear('products_related');
      Cache::clear('products_cross_sell');
      Cache::clear('upcoming');
    }

    $this->app->redirect('Categories&cPath=' . $cPath);
  }
}