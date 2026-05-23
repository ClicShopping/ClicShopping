<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Favorites\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\Favorites\Favorites as FavoritesApp;

class Save implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Favorites application.
   *
   * This constructor checks if the 'Favorites' app is already registered
   * in the Registry. If not, it initializes a new instance of FavoritesApp
   * and registers it. Finally, it retrieves and assigns the 'Favorites'
   * app instance from the Registry to the class property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Favorites')) {
      Registry::set('Favorites', new FavoritesApp());
    }

    $this->app = Registry::get('Favorites');
  }

  /**
   * Saves a product as a favorite in the database if the 'products_favorites' parameter is provided in the POST request.
   *
   * @param int $id The ID of the product to be saved as a favorite.
   * @return void
   */
  private function saveProductsFavorites(int $id): void
  {
    if (!empty($_POST['products_favorites'])) {
      $inset_array = [
        'products_id' => (int)$id,
        'products_favorites_date_added' => 'now()',
        'status' => 1,
        'customers_group_id' => 0
      ];

      $this->app->db->save('products_favorites', $inset_array);
    }
  }

  /**
   * Saves a product to the favorites list.
   *
   * @param int $id The identifier of the product to be saved.
   * @return void
   */
  private function save(int $id)
  {
    $this->saveProductsFavorites($id);
  }

  /**
   * Executes the primary logic for handling the favorites application functionality.
   * This includes checking the application status and determining whether to process
   * a product ID provided via the GET parameter or handle the addition of a new favorite
   * product when no specific ID is provided.
   *
   * @return bool|void Returns false if the application status is disabled. Returns void if operation is successfully executed.
   */
  public function execute()
  {
    if (!\defined('CLICSHOPPING_APP_FAVORITES_FA_STATUS') || CLICSHOPPING_APP_FAVORITES_FA_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['pID'])) {
      $id = HTML::sanitize($_GET['pID']);
      $this->save($id);
    } else {
      if (!empty($_POST['products_favorites'])) {
        $Qproducts = $this->app->db->prepare('select products_id
                                                from :table_products
                                                order by products_id desc
                                                limit 1
                                               ');
        $Qproducts->execute();

        $inset_array = [
          'products_id' => (int)$Qproducts->valueInt('products_id'),
          'products_favorites_date_added' => 'now()',
          'status' => 1,
          'customers_group_id' => 0
        ];

        $this->app->db->save('products_favorites', $inset_array);
      }
    }
  }
}