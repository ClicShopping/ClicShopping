<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\BannerManager\Sites\ClicShoppingAdmin\Pages\Home\Actions\BannerManager;

use ClicShopping\Apps\Marketing\BannerManager\Classes\ClicShoppingAdmin\Status;
use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{

  public function execute()
  {

    $CLICSHOPPING_BannerManager = Registry::get('BannerManager');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (($_GET['flag'] == 0) || ($_GET['flag'] == 1)) {
      Status::setBannerStatus($_GET['bID'], $_GET['flag']);
    } else {
      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_BannerManager->getDef('error_unknown_status_flag'), 'error');
    }

    $CLICSHOPPING_BannerManager->redirect('BannerManager&page=' . $page);
  }
}