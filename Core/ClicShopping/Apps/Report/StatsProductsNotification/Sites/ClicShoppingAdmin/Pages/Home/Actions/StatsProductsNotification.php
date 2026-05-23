<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Report\StatsProductsNotification\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class StatsProductsNotification extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_StatsProductsNotification = Registry::get('StatsProductsNotification');

    $this->page->setFile('stats_products_notification.php');

    $CLICSHOPPING_StatsProductsNotification->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}