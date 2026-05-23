<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Catalog\Products\Sites\ClicShoppingAdmin\Pages\Home\Actions\Products;

use ClicShopping\OM\Cache;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class MoveConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;
  protected $newParentId;
  protected $currentCategoryId;
  protected $ID;

  public function __construct()
  {
    $this->app = Registry::get('Products');

    $this->newParentId = HTML::sanitize($_POST['move_to_category_id']);

    if (isset($_GET['pID'])) {
      $this->ID = HTML::sanitize($_GET['pID']); // insert
    } elseif (isset($_POST['pID'])) {
      $this->ID = HTML::sanitize($_POST['pID']); // update
    }

    if (isset($_POST['products_id'])) {
      $this->ID = HTML::sanitize($_POST['products_id']); // boxe
    }

    if (isset($_POST['current_category_id'])) {
      $this->currentCategoryId = HTML::sanitize($_POST['current_category_id']); // insert- update
    } else {
      $this->currentCategoryId = HTML::sanitize($_GET['cPath']); // boxe
    }
  }

  public function execute()
  {
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    if (!\is_null($this->ID)) {
      $QCheck = $this->app->db->prepare('select count(*)
                                            from :table_products_to_categories
                                            where products_id = :products_id
                                            and categories_id not in ( :categories_id )
                                          ');
      $QCheck->bindInt(':products_id', $this->ID);
      $QCheck->bindInt(':categories_id', $this->newParentId);
      $QCheck->execute();

      if ($QCheck->rowCount() > 0) {
        $Qupdate = $this->app->db->prepare('update :table_products_to_categories
                                              set categories_id = :categories_id
                                              where products_id = :products_id
                                              and categories_id = :categories_id1
                                            ');
        $Qupdate->bindInt(':categories_id', $this->newParentId);
        $Qupdate->bindInt(':products_id', $this->ID);
        $Qupdate->bindInt(':categories_id1', $this->currentCategoryId);

        $Qupdate->execute();
      }

      Cache::clear('categories');
      Cache::clear('products-also_purchased');
      Cache::clear('products_related');
      Cache::clear('products_cross_sell');
      Cache::clear('upcoming');
    }

    $CLICSHOPPING_Hooks->call('Products', 'Move');

    $this->app->redirect('Products&cPath=' . $this->newParentId . '&pID=' . $this->ID);
  }
}