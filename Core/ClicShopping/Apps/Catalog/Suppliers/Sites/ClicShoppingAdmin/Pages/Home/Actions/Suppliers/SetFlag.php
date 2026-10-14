<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Catalog\Suppliers\Sites\ClicShoppingAdmin\Pages\Home\Actions\Suppliers;

use ClicShopping\Apps\Catalog\Suppliers\Classes\ClicShoppingAdmin\Status;
use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Suppliers');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    if (isset($_GET['id'], $_GET['flag'])) {
      Status::getSuppliersStatus((int)$_GET['id'], (int)$_GET['flag']);

      $this->app->redirect('Suppliers&page=' . $page . '&mID=' . (int)$_GET['id']);
    } else {
      $this->app->redirect('Suppliers&page=' . $page);
    }
  }
}