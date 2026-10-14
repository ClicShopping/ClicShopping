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

class ResetApcu extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Cache');
  }

  /**
   * Clears the APCu entries of this installation tree and reports the outcome.
   *
   * @return void
   */
  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/apcu');

    if (CacheAdmin::resetApcu() === true) {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('success_apcu_reset'), 'success');
    } else {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('error_apcu_reset'), 'error');
    }

    $this->app->redirect('Apcu');
  }
}
