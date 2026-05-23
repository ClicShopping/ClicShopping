<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Sites\ClicShoppingAdmin\Pages\Home\Actions\CoreUpgrade;

use ClicShopping\Apps\Tools\Upgrade\Classes\ClicShoppingAdmin\Github;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\FileSystem;
use ClicShopping\OM\Registry;

class Process extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Upgrade');
  }

  public function execute()
  {

    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');
    $CLICSHOPPING_Github = new Github();

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/main');

    if (FileSystem::isWritable(CLICSHOPPING::BASE_DIR . 'Work/Temp/')) {
      $CLICSHOPPING_Github->upgradeClicShoppingCore();
    } else {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('error_directory_not_writable'), 'warning', 'header');
    }
  }
}