<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Report\StatsProductsNotification\Sites\ClicShoppingAdmin\Pages\Home\Actions\StatsProductsNotification;

use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class Update extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {

    $CLICSHOPPING_StatsProductsNotification = Registry::get('StatsProductsNotification');

    if (isset($_GET['resetViewed'])) $resetViewed = HTML::sanitize($_GET['resetViewed']);
    if (isset($_GET['products_id'])) $products_id = HTML::sanitize($_GET['products_id']);

    if ($resetViewed == '0') {
// Reset ALL counts
      $Qupdate = $CLICSHOPPING_StatsProductsNotification->db->prepare('update :table_products_description
                                                                  set products_viewed = 0
                                                                  where 1
                                                                ');
      $Qupdate->execute();

    } else {
// Reset selected product count
      $Qupdate = $CLICSHOPPING_StatsProductsNotification->db->prepare('update :table_products_description
                                                                  set products_viewed = 0
                                                                  where products_id = :products_id
                                                                ');
      $Qupdate->bindInt(':products_id', (int)$products_id);
      $Qupdate->execute();
    }
  }
}
