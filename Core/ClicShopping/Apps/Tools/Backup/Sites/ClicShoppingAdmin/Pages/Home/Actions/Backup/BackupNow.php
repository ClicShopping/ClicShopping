<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Backup\Sites\ClicShoppingAdmin\Pages\Home\Actions\Backup;

use ClicShopping\Apps\Tools\Backup\Classes\ClicShoppingAdmin\Backup;
use ClicShopping\OM\RateLimiter;
use ClicShopping\OM\Registry;

class BackupNow extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Backup');
  }

  public function execute()
  {
    // Rate limiting: 60 seconds between backup operations
    $limiter = new RateLimiter(['backup' => 60]);
    $rate_check = $limiter->check('backup');
    if (!$rate_check['allowed']) {
      $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
      $CLICSHOPPING_MessageStack->add($rate_check['message'], 'warning');
      $this->app->redirect('Backup');
    }

    Backup::backupNow();
    $limiter->record('backup');

    $this->app->redirect('Backup');
  }
}