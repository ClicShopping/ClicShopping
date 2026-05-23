<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Countries\Sites\ClicShoppingAdmin\Pages\Home\Actions\Countries;

use ClicShopping\Apps\Configuration\Countries\Classes\ClicShoppingAdmin\Status;
use ClicShopping\OM\Registry;


class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Countries');
  }

  public function execute()
  {

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['cID'], $_GET['flag'])) {
      Status::getCountriesStatus($_GET['cID'], $_GET['flag']);

      $this->app->redirect('Countries&page=' . $page . '&cID=' . $_GET['cID']);
    }
  }
}