<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */


namespace ClicShopping\Apps\Configuration\Api\Sites\ClicShoppingAdmin\Pages\Home\Actions\Api;

use ClicShopping\Apps\Configuration\Api\Classes\ClicShoppingAdmin\Status;
use ClicShopping\OM\Registry;


class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Api');
  }

  public function execute()
  {
    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;

    Status::getApiStatus($_GET['cID'], $_GET['flag']);

    $this->app->redirect('Api&' . $page . '&cID=' . $_GET['cID']);
  }
}