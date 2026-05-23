<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Sites\ClicShoppingAdmin\Pages\Home\Actions\Products;

use ClicShopping\Apps\Catalog\Products\Classes\ClicShoppingAdmin\ProductsStatusAdmin;
use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Products');
  }

  public function execute()
  {
    if (isset($_GET['flag']) && ($_GET['flag'] == 0 || $_GET['flag'] == 1)) {
      if (isset($_GET['pID'])) {
        ProductsStatusAdmin::getProductStatus($_GET['pID'], $_GET['flag']);
      }

      Cache::clear('categories');
      Cache::clear('products-also_purchased');
      Cache::clear('products_related');
      Cache::clear('products_cross_sell');
      Cache::clear('upcoming');
    }

    if (isset($_GET['cPath']) && isset($_GET['pID'])) {
      $this->app->redirect('Products&cPath=' . $_GET['cPath'] . '&pID=' . $_GET['pID']);
    } elseif (isset($_GET['pID'])) {
      $this->app->redirect('Products&pID=' . $_GET['pID']);
    } else {
      $this->app->redirect('Products');
    }
  }
}