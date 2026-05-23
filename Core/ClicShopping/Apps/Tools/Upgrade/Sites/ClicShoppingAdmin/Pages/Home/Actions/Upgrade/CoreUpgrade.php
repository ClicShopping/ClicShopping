<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Sites\ClicShoppingAdmin\Pages\Home\Actions\Upgrade;

use ClicShopping\Apps\Tools\Upgrade\Classes\ClicShoppingAdmin\Github;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\Registry;

class CoreUpgrade extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {

    $CLICSHOPPING_Upgrade = Registry::get('Upgrade');
    $this->app = $CLICSHOPPING_Upgrade;
  }

  public function execute()
  {

    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Github = new Github();

    if (FileSystem::isWritable(CLICSHOPPING::BASE_DIR . 'Sites/Work/Temp')) {
      $CLICSHOPPING_Github->UpgradeClicShoppingCore();
      $CLICSHOPPING_MessageStack->add($this->app->getDef('success_core_installed'), 'success');
    } else {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('error_directory_not_writable'), 'danger');
    }

    $this->app->redirect('Upgrade');
  }
}