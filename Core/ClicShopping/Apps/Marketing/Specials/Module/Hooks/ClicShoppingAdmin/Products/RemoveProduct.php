<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Specials\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Marketing\Specials\Specials as SpecialsApp;

class RemoveProduct implements \ClicShopping\OM\Modules\HooksInterface
{
  public mixed $app;

  /**
   * Constructs the class and ensures that the 'Specials' application is registered in the registry.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Specials')) {
      Registry::set('Specials', new SpecialsApp());
    }

    $this->app = Registry::get('Specials');
  }

  /**
   * Removes products from the specials table in the database based on the given product ID.
   *
   * @param int $id The ID of the product to be removed from the specials.
   * @return void
   */
  private function removeProducts($id)
  {
    if (!empty($_POST['products_specials'])) {
      $this->app->db->delete('specials', ['products_id' => (int)$id]);
    }
  }

  /**
   * Executes the removal of a product.
   *
   * @param array $parameters Associative array of parameters passed by the hook.
   *                          Must contain the key 'products_id'.
   *
   * @return bool
   *   - false if no valid product ID is provided.
   *   - true  after successfully removing the product.
   */
  public function execute(array $parameters): bool
  {
    if (!\defined('CLICSHOPPING_APP_SPECIALS_SP_STATUS') || CLICSHOPPING_APP_SPECIALS_SP_STATUS === 'False') {
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