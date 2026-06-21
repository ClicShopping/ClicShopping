<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Module\Hooks\ClicShoppingAdmin\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\HooksInterface;

use ClicShopping\Apps\Marketing\Recommendations\Recommendations as RecommendationsApp;
use function defined;

class RemoveProduct implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method for initializing the Recommendations app.
   *
   * Checks if the 'Recommendations' application is registered in the Registry.
   * If not, it creates and registers a new RecommendationsApp instance.
   * Then, it retrieves and assigns the 'Recommendations' app instance to the class property.
   *
   * @return void
   */
  public function __construct()
  {
    if (!Registry::exists('Recommendations')) {
      Registry::set('Recommendations', new RecommendationsApp());
    }

    $this->app = Registry::get('Recommendations');
  }

  /**
   * Removes product recommendations and associated category mappings for a given product ID.
   *
   * @param int $id The ID of the product to remove recommendations for.
   * @return void
   */
  private function removeProducts($id)
  {
    if (!empty($_POST['products_recommendations'])) {
      $this->app->db->delete('products_recommendations', ['products_id' => (int)$id]);
      $this->app->db->delete('products_recommendations_to_categories', ['products_id' => (int)$id]);
    }
  }

  /**
   * Executes the removal of a product from the recommendations module.
   *
   * @param array $parameters Associative array of parameters passed by the hook.
   *                          Must contain the key 'products_id'.
   *
   * @return bool
   *   - false if the Recommendations app is disabled or if no valid product ID is provided.
   *   - true  after successfully removing the product.
   */
  public function execute(array $parameters): bool
  {
    if (!defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_STATUS') || CLICSHOPPING_APP_RECOMMENDATIONS_PR_STATUS === 'False') {
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