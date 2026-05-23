<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Archive\Sites\ClicShoppingAdmin\Pages\Home\Actions\Archive;

use ClicShopping\OM\Cache;
use ClicShopping\OM\Registry;

class DeleteAll extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Archive = Registry::get('Archive');

    if (isset($_POST['selected'])) {
      foreach ($_POST['selected'] as $id) {
        $Qupdate = $CLICSHOPPING_Archive->db->prepare('update :table_products
                                                        set products_archive = :products_archive
                                                        where products_id = :products_id
                                                      ');
        $Qupdate->bindInt(':products_archive', '0');
        $Qupdate->bindInt(':products_id', $id);
        $Qupdate->execute();
      }
    }

    Cache::clear('categories');
    Cache::clear('products-also_purchased');
    Cache::clear('products_related');
    Cache::clear('products_cross_sell');
    Cache::clear('upcoming');

    $CLICSHOPPING_Archive->redirect('Archive');
  }
}