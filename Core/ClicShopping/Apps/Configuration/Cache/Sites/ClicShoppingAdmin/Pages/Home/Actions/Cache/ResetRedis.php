<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Cache\Sites\ClicShoppingAdmin\Pages\Home\Actions\Cache;

use ClicShopping\Apps\Configuration\Cache\Classes\ClicShoppingAdmin\CacheAdmin;
use ClicShopping\OM\Registry;

class ResetRedis extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Cache');
  }

  /**
   * Empties Redis database 0 and reports the outcome.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/redis');

    if (CacheAdmin::resetRedis() === true) {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('success_redis_reset'), 'success');
    } else {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('error_redis_reset'), 'error');
    }

    $this->app->redirect('Redis');
  }
}
