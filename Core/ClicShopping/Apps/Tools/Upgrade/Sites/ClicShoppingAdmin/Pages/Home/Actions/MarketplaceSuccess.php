<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class MarketplaceSuccess extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Upgrade = Registry::get('Upgrade');

    $this->page->setFile('marketplace_success.php');

    $CLICSHOPPING_Upgrade->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}