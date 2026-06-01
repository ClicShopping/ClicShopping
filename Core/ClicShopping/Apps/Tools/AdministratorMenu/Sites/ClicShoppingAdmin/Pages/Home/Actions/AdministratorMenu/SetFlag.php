<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\AdministratorMenu\Sites\ClicShoppingAdmin\Pages\Home\Actions\AdministratorMenu;

use ClicShopping\Apps\Tools\AdministratorMenu\Classes\ClicShoppingAdmin\Status;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('AdministratorMenu');
  }

  public function execute()
  {
    if (isset($_GET['flag']) && ($_GET['flag'] == 0 || ($_GET['flag'] == 1))) {
      if (isset($_GET['cPath'])) {
        $cPath = HTML::sanitize($_GET['cPath']);
      } else {
        $cPath = 0;
      }

      if (isset($_GET['cID'])) {
        Status::getAministratorMenuStatus($_GET['cID'], (int)$_GET['flag']);
      }
    }

    if (isset($_GET['cID'])) {
      $this->app->redirect('AdministratorMenu&cPath=' . $cPath . '&cID=' . $_GET['cID']);
    } else {
      $this->app->redirect('AdministratorMenu&cPath=' . $cPath);
    }
  }
}