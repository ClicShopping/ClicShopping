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
 * Class StatsProductsNoPurchased
 *
 * This action class is responsible for displaying the statistics of products that have never been purchased.
 * It sets the appropriate page file and loads the necessary language definitions for the admin interface.
 */
class StatsProductsNoPurchased extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * Execute the action to display products with no purchases.
   *
   * This method sets the page file to 'stats_products_no_purchased.php' and loads the relevant
   * language definitions for displaying statistics about products that have never been purchased.
   */
  public function execute()
  {
    $CLICSHOPPING_Products = Registry::get('Products');

    $this->page->setFile('stats_products_no_purchased.php');

    $CLICSHOPPING_Products->loadDefinitions('Sites/ClicShoppingAdmin/stats_no_products_purchased');
  }
}