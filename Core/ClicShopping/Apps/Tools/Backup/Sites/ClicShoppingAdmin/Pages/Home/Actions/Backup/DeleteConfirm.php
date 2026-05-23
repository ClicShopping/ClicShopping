<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Backup\Sites\ClicShoppingAdmin\Pages\Home\Actions\Backup;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

class DeleteConfirm extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public mixed $app;

  public function __construct()
  {
    $this->app = Registry::get('Backup');
  }

  public function execute()
  {
    $CLICSHOPPING_MessageStack = Registry::get('MessageStack');

    $backup_directory = CLICSHOPPING::BASE_DIR . 'Work/Backups/';

    if (strstr($_GET['file'], '..')) $this->app->redirect('Backup');

    if (unlink($backup_directory . '/' . $_GET['file'])) {
      $CLICSHOPPING_MessageStack->add($this->app->getDef('success_backup_deleted'), 'success');

      $this->app->redirect('Backup');
    }
  }
}