<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Cache\Sites\ClicShoppingAdmin\Pages\Home\Actions\Cache;

use ClicShopping\OM\Registry;

class ToggleRealtime extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Cache');
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (!isset($_SESSION['opcache_realtime'])) {
      $_SESSION['opcache_realtime'] = false;
    }

    $_SESSION['opcache_realtime'] = !$_SESSION['opcache_realtime'];

    $status = $_SESSION['opcache_realtime'] ? 'enabled' : 'disabled';
    $CLICSHOPPING_MessageStack->add('Realtime updates ' . $status, 'success');

    $this->app->redirect('OpCache');
  }
}