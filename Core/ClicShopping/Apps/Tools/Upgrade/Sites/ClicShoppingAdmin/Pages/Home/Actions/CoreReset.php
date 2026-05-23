<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\Registry;

class CoreReset extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function execute()
  {
    $this->app = Registry::get('Upgrade');

    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    if (FileSystem::isWritable(CLICSHOPPING::BASE_DIR . 'Work/Cache/Github/Temp')) {
      $cache_file = CLICSHOPPING::BASE_DIR . 'Work/Cache/Github/Temp/version.json';

      unlink($cache_file);

      $CLICSHOPPING_MessageStack->add($this->app->getDef('success_deleted_installed'), 'success', 'update');
    } else {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('error_directory_not_writable'), 'danger', 'update');
    }

    $this->app->redirect('Upgrade');
  }
}