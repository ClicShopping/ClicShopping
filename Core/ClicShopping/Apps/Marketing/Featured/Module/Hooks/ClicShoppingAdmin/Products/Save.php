<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Featured\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\Featured\Featured as FeaturedApp;

class Save implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Initializes the FeaturedApp and sets it in the Registry if not already present.
   * Retrieves the FeaturedApp instance from the Registry and assigns it to the app property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Featured')) {
      Registry::set('Featured', new FeaturedApp());
    }

    $this->app = Registry::get('Featured');
  }

  /**
   * Saves a product as featured by inserting related data into the database.
   *
   * @param int $id The ID of the product to be marked as featured.
   * @return void
   */
  private function saveProductsFeatured(int $id): void
  {
    if (!empty($_POST['products_featured'])) {
      $insert_array = [
        'products_id' => (int)$id,
        'products_featured_date_added' => 'now()',
        'status' => 1,
        'customers_group_id' => 0
      ];

      $this->app->db->save('products_featured', $insert_array);
    }
  }

  /**
   * Saves the product as featured based on the given ID.
   *
   * @param int $id The ID of the product to be saved as featured.
   * @return void
   */
  private function save(int $id): void
  {
    $this->saveProductsFeatured($id);
  }

  /**
   * Executes the main logic for handling featured product operations.
   * This includes saving a specific product's details if a product ID is provided,
   * or inserting a new featured product record if no product ID is supplied but featured products data is present.
   *
   * @return bool Returns false if the featured product functionality is disabled, otherwise it does not return a value.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_FEATURED_FE_STATUS') || CLICSHOPPING_APP_FEATURED_FE_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['pID'])) {
      $id = HTML::sanitize($_GET['pID']);
      $this->save($id);
    } else {
      if (!empty($_POST['products_featured'])) {
        $Qproducts = $this->app->db->prepare('select products_id
                                                from :table_products
                                                order by products_id desc
                                                limit 1
                                               ');
        $Qproducts->execute();

        $insert_array = [
          'products_id' => $Qproducts->valueInt('products_id'),
          'products_featured_date_added' => 'now()',
          'status' => 1,
          'customers_group_id' => 0
        ];

        $this->app->db->save('products_featured', $insert_array);
      }
    }
  }
}