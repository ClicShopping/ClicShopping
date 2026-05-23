<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\SecurityCheck\Sites\ClicShoppingAdmin\Pages\Home\Actions\IpRestriction;

use ClicShopping\Apps\Tools\SecurityCheck\Classes\IpRestriction;
use ClicShopping\OM\Registry;

class SetFlagShop extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('SecurityCheck');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['cID'], $_GET['flag'])) {
      IpRestriction::getIpRestrictionShopStatus($_GET['cID'], $_GET['flag']);

      $this->app->redirect('IpRestriction&page=' . $page . '&cID=' . (int)$_GET['cID']);
    } else {
      $this->app->redirect('IpRestriction&page=' . $page);
    }
  }
}