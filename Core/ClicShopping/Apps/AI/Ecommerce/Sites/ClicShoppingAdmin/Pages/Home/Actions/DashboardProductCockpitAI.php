<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class DashboardProductCockpitAI extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Ecommerce = Registry::get('Ecommerce');

    $this->page->setFile('dashboard_product_cockpit_ai.php');
    $this->page->data['action'] = 'DashboardProductCockpitAI';

    $CLICSHOPPING_Ecommerce->loadDefinitions('Sites/ClicShoppingAdmin/dashboard_product_cockpit_ai');
  }
}
