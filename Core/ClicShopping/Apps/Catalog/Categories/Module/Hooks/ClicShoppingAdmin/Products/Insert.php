<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\Apps\Catalog\Categories\Categories as CategoriesApp;
use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

/**
 * Class responsible
 * for managing and displaying statistics related to categories within the application.
 */
class Insert implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for the class.
   *
   * Initializes the Categories application module by checking its existence in the Registry.
   * If not present, it creates a new instance of CategoriesApp and stores it in the Registry.
   * Assigns the app instance from the Registry to the $this->app property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Categories')) {
      Registry::set('Categories', new CategoriesApp());
    }

    $this->app = Registry::get('Categories');
  }

  /**
   * Saves the product category association and clears relevant cache entries.
   *
   * @param array $current_category_id An array containing the current category ID(s).
   * @return void
   */
  private function saveProductCategory(array $current_category_id): void
  {
    $input = array_map('intval', $current_category_id);

    $hasZero = in_array(0, $input, true);

    $categories = array_values(
      array_unique(
        array_filter($input, fn($id) => $id >= 0)
      )
    );

    // No valid value and no explicit 0
    if (empty($categories) && !$hasZero) {
      $categories = [0];
    }

    $Qproducts = $this->app->db->prepare('select products_id
                                         from :table_products
                                         order by products_id desc
                                         limit 1'
                                        );
    $Qproducts->execute();

    $products_id = $Qproducts->valueInt('products_id');

    foreach ($categories as $cat_id) {
      $this->app->db->save('products_to_categories', [
        'products_id' => $products_id,
        'categories_id' => (int)$cat_id
      ]);
    }

    Cache::clear('categories');
    Cache::clear('products-also_purchased');
    Cache::clear('products_related');
    Cache::clear('products_cross_sell');
    Cache::clear('upcoming');
  }

  /**
   * Executes the operation to handle category movement for a product.
   *
   * @return bool Returns false if the application status is either undefined or set to 'False'.
   */
  public function execute(?array $parameters = null)
  {
    if (!\defined('CLICSHOPPING_APP_CATEGORIES_CT_STATUS') || CLICSHOPPING_APP_CATEGORIES_CT_STATUS === 'False') {
      return false;
    }

    if (isset($_GET['Insert'])) {
      $parameters = array_combine(
        array_map('trim', array_keys($parameters)), $parameters);

      $category_id = (array)$parameters['categories_id'];

      $this->saveProductCategory($category_id);
    }
  }
}