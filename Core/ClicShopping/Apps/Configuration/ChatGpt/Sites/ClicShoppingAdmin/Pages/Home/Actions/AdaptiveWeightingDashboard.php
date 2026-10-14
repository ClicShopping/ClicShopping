<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\ChatGpt\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class AdaptiveWeightingDashboard extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ChatGpt = Registry::get('ChatGpt');

    $this->page->setFile('adaptive_weighting_dashboard.php');
    $this->page->data['action'] = 'AdaptiveWeightingDashboard';

    $CLICSHOPPING_ChatGpt->loadDefinitions('Sites/ClicShoppingAdmin/adaptive_weighting_dashboard');
  }
}
