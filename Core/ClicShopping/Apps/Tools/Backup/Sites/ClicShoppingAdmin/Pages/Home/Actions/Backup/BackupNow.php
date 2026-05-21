<?php
/**
 *
 * @copyright 2008 - https://www.clicshopping.org
 * @Brand : ClicShoppingAI(TM) at Inpi all right Reserved
 * @Licence GPL 2 & MIT
 * @Info : https://www.clicshopping.org/forum/trademark/
 *
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
    $rate_check = RateLimiter::check('backup', 60);
    if (!$rate_check['allowed']) {
      $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
      $CLICSHOPPING_MessageStack->add($rate_check['message'], 'warning');
      $this->app->redirect('Backup');
    }

    Backup::backupNow();
    RateLimiter::record('backup');

    $this->app->redirect('Backup');
  }
}