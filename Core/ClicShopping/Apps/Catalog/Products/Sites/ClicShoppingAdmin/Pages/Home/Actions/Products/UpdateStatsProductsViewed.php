<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Sites\ClicShoppingAdmin\Pages\Home\Actions\Products;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class UpdateStatsProductsViewed extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {

    $CLICSHOPPING_Products = Registry::get('Products');

    if (isset($_GET['resetViewed'])) {
      $resetViewed = (int)$_GET['resetViewed'];

      if (isset($_GET['products_id'])) $products_id = HTML::sanitize($_GET['products_id']);

      if ($resetViewed == '0') {
        // Reset ALL counts
        $Qupdate = $CLICSHOPPING_Products->db->prepare('update :table_products_description
                                                          set products_viewed = 0
                                                          where 1
                                                        ');
        $Qupdate->execute();

      } else {
        // Reset selected product count
        $Qupdate = $CLICSHOPPING_Products->db->prepare('update :table_products_description
                                                          set products_viewed = 0
                                                          where products_id = :products_id
                                                        ');
        $Qupdate->bindInt(':products_id', (int)$products_id);
        $Qupdate->execute();
      }
    }

    $CLICSHOPPING_Products->redirect('StatsProductsViewed');
  }
}