<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Favorites\Module\Hooks\ClicShoppingAdmin\CustomersGroup;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use ClicShopping\OM\Interfaces\HooksInterface;

use ClicShopping\Apps\Marketing\Favorites\Favorites as FavoritesApp;

class Delete implements HooksInterface
{
  public mixed $app;

  /**
   * Constructor method to initialize the Favorites application.
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
   * Deletes all records associated with the given group ID from the products favorites table,
   * if such records exist.
   *
   * @param int $group_id The ID of the customer group whose associated records need to be deleted.
   * @return void
   */
  private function delete(int $group_id): void
  {
    $QProductsFavoritesCustomersId = $this->app->db->prepare('select count(customers_group_id) as count
                                                                 from :table_products_favorites
                                                                 where customers_group_id = :customers_group_id
                                                               ');
    $QProductsFavoritesCustomersId->bindInt(':customers_group_id', (int)$group_id);
    $QProductsFavoritesCustomersId->execute();

    if ($QProductsFavoritesCustomersId->valueInt('count') > 0) {
      $Qdelete = $this->app->db->prepare('delete
                                            from :table_products_favorites
                                            where customers_group_id = :customers_group_id
                                          ');
      $Qdelete->bindInt(':customers_group_id', (int)$group_id);
      $Qdelete->execute();
    }
  }

  /**
   * Executes the functionality to handle the deletion of an item based on the 'Delete' request parameter.
   *
   * @return void
   */
  public function execute()
  {
    if (isset($_GET['Delete'])) {
      $id = HTML::sanitize($_GET['cID']);
      $this->delete($id);
    }
  }
}