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
 * Class DynamicPricingRules
 *
 * This class handles the action for displaying and managing dynamic pricing rules in the admin interface.
 * It sets the appropriate page file and loads necessary language definitions for the dynamic pricing rules section.
 */
class DynamicPricingRules extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  /**
   * Execute the action to manage dynamic pricing rules.
   *
   * This method sets the page file to 'dynamic_pricing_rules.php'
   * and loads the necessary language definitions for the Products app.
   */
  public function execute()
  {
    $CLICSHOPPING_Products = Registry::get('Products');

    $this->page->setFile('dynamic_pricing_rules.php');
    $this->page->data['action'] = 'DynamicPricingRules';


    $CLICSHOPPING_Products->loadDefinitions('Sites/ClicShoppingAdmin/dynamic_pricing_rules');
  }
}