<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\SecDirPermissions\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class SecDirPermissions extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_SecDirPermissions = Registry::get('SecDirPermissions');

    $this->page->setFile('sec_dir_permissions.php');

    $CLICSHOPPING_SecDirPermissions->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}