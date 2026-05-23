<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Products\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

/**
 * Class StatsProductsSafetyStock
 *
 * This action class is responsible for displaying the statistics related to product safety stock levels
 * in the admin interface. It sets the appropriate page file and loads the necessary language definitions.
 */
class StatsProductsSafetyStock extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * Execute the action to display product safety stock statistics.
   *
   * This method sets the page file to 'stats_products_safety_stock.php' and loads
   * the relevant language definitions for the statistics page.
   */
  public function execute()
  {
    $CLICSHOPPING_Products = Registry::get('Products');

    $this->page->setFile('stats_products_safety_stock.php');

    $CLICSHOPPING_Products->loadDefinitions('Sites/ClicShoppingAdmin/stats_products_safety_stock');
  }
}