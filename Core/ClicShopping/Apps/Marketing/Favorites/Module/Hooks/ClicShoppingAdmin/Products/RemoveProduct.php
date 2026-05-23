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

class RemoveProduct implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructor method to initialize the Favorites application.
   *
   * Checks if the 'Favorites' instance exists in the Registry. If not, it creates
   * a new instance of the FavoritesApp and adds it to the Registry. Then, it
   * retrieves the instance from the Registry and assigns it to the app property.
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
   * Removes a product from the favorites list based on the provided ID.
   *
   * @param int $id The ID of the product to be removed from the favorites list.
   * @return void
   */
  private function removeProducts($id)
  {
    if (!empty($_POST['products_favorites'])) {
      $this->app->db->delete('products_favorites', ['products_id' => (int)$id]);
    }
  }

  /**
   * @param array $parameters
   * @return bool
   */
  public function execute(array $parameters): bool
  {
    if (!\defined('CLICSHOPPING_APP_FAVORITES_FA_STATUS') || CLICSHOPPING_APP_FAVORITES_FA_STATUS === 'False') {
      return false;
    }

    $products_id = $parameters['products_id'] ?? null;

    if (empty($products_id)) {
      return false;
    }

    $products_id = HTML::sanitize($products_id);

    $this->removeProducts($products_id);

    return true;
  }
}