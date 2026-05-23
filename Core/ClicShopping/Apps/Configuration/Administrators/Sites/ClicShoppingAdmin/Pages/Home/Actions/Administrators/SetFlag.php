<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Administrators\Sites\ClicShoppingAdmin\Pages\Home\Actions\Administrators;

use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\Status;
use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Administrators = Registry::get('Administrators');
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['Administrators'])) {
      Status::getAdministratorStatus($_GET['id'], $_GET['flag']);

      $CLICSHOPPING_MessageStack->add($CLICSHOPPING_Administrators->getDef('success_status_updated'), 'success');
    }

    $CLICSHOPPING_Administrators->redirect('Administrators&page=' . $page . '&aID=' . (int)$_GET['id']);
  }
}