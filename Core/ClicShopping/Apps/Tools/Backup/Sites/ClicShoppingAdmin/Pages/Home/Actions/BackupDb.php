<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Backup\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class BackupDb extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Backup = Registry::get('Backup');

    $this->page->setFile('backup_db.php');

    $CLICSHOPPING_Backup->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}