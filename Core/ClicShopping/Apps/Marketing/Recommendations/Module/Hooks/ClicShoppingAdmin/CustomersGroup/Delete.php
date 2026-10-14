<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Recommendations\Module\Hooks\ClicShoppingAdmin\CustomersGroup;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\HooksInterface;

use ClicShopping\Apps\Marketing\Recommendations\Recommendations as RecommendationsApp;
use function defined;

class Delete implements HooksInterface
{
  public mixed $app;

  /**
   * Initializes the Recommendations application by checking its existence in the Registry.
   * If it does not exist, it creates and registers a new instance of RecommendationsApp.
   * Retrieves and assigns the Recommendations application instance from the Registry.
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
   * Deletes all product recommendations associated with a specific customer group ID.
   *
   * @param int $group_id The ID of the customer group whose product recommendations are to be deleted.
   * @return void
   */
  private function delete(int $group_id): void
  {
    $QProductsRecommendationsCustomersId = $this->app->db->prepare('select count(customers_group_id) as count
                                                                             from :table_products_recommendations
                                                                             where customers_group_id = :customers_group_id
                                                                           ');
    $QProductsRecommendationsCustomersId->bindInt(':customers_group_id', (int)$group_id);
    $QProductsRecommendationsCustomersId->execute();

    if ($QProductsRecommendationsCustomersId->valueInt('count') > 0) {
      $Qdelete = $this->app->db->prepare('delete
                                            from :table_products_recommendations
                                            where customers_group_id = :customers_group_id
                                          ');
      $Qdelete->bindInt(':customers_group_id', (int)$group_id);
      $Qdelete->execute();
    }
  }

  /**
   * Executes the functionality for recommendations app based on the application's status
   * and determines whether to delete a specific record if requested.
   *
   * @return bool Returns false if the app is not active.
   */
  public function execute()
  {
    if (!defined('CLICSHOPPING_APP_RECOMMENDATIONS_PR_STATUS') || CLICSHOPPING_APP_RECOMMENDATIONS_PR_STATUS == 'False') {
      return false;
    }

    if (isset($_GET['Delete'])) {
      $id = HTML::sanitize($_GET['cID']);
      $this->delete($id);
    }
  }
}