<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Zones\Sites\ClicShoppingAdmin\Pages\Home\Actions\Zones;

use ClicShopping\Apps\Configuration\Zones\Classes\ClicShoppingAdmin\Status;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

class SetFlag extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Zones');
  }

  public function execute()
  {
    $search = '';

    $page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? (int)$_GET['page'] : 1;
    Status::getZonesStatus($_GET['id'], $_GET['flag']);

    if (isset($_GET['search'])) {
      $search = '&search=' . HTML::sanitize($_GET['search']);
    }

    $this->app->redirect('Zones&page=' . $page . '&cID=' . $_GET['id'] . $search);
  }
}