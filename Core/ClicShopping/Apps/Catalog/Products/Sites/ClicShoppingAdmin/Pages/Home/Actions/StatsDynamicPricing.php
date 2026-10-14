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
 * Class StatsProductsExpected
 *
 * This action class is responsible for setting up the environment to display
 * statistics about expected products in the admin interface.
 */
class StatsDynamicPricing extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * Execute the action to display expected products statistics.
   *
   * This method sets the appropriate template file for rendering the statistics
   * and loads the necessary language definitions for the admin interface.
   */
  public function execute()
  {
    $CLICSHOPPING_Products = Registry::get('Products');

    $this->page->setFile('stats_dynamic_pricing.php');
    $this->page->data['action'] = 'DynamicPricingRules';

    $CLICSHOPPING_Products->loadDefinitions('Sites/ClicShoppingAdmin/stats_dynamic_pricing');
  }
}