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
use ClicShopping\OM\Interfaces\HooksInterface;

use ClicShopping\Apps\Marketing\Featured\Featured as FeaturedApp;

class RemoveProduct implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Featured application by setting it in the Registry if it does not already exist
   * and assigning it to the app property.
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
   * Removes products from the featured products list in the database if applicable.
   *
   * @param int $id The ID of the product to be removed.
   * @return void
   */
  private function removeProducts($id)
  {
    if (!empty($_POST['products_featured'])) {
      $this->app->db->delete('products_featured', ['products_id' => (int)$id]);
    }

  }

  /**
   * @param array $parameters
   * @return bool
   */
  public function execute(array $parameters): bool
  {
    if (!\defined('CLICSHOPPING_APP_FEATURED_FE_STATUS') || CLICSHOPPING_APP_FEATURED_FE_STATUS === 'False') {
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