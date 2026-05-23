<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Backup\Sites\ClicShoppingAdmin\Pages\Home\Actions\Backup;

use ClicShopping\OM\Registry;

class Forget extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Backup');
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $this->app->db->delete('configuration', ['configuration_key' => 'DB_LAST_RESTORE']);

    $CLICSHOPPING_MessageStack->add($this->app->getDef('success_last_restore_cleared'), 'success');

    $this->app->redirect('Backup');
  }
}