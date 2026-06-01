<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\BannerManager\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class BannerManager extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_BannerManager = Registry::get('BannerManager');

    $this->page->setFile('banner_manager.php');

    $CLICSHOPPING_BannerManager->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}