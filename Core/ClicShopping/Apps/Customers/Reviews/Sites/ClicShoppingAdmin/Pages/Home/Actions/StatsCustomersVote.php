<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class StatsCustomersVote extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Reviews = Registry::get('Reviews');

    $this->page->setFile('stats_customers_vote.php');

    $CLICSHOPPING_Reviews->loadDefinitions('Sites/ClicShoppingAdmin/stats_customers_vote');
  }
}