<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\AdministratorMenu\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_AdministratorMenu = Registry::get('AdministratorMenu');

    $this->page->setFile('delete.php');
    $this->page->data['action'] = 'DeleteCategoryConfirm';

    $CLICSHOPPING_AdministratorMenu->loadDefinitions('Sites/ClicShoppingAdmin/AdministratorMenu');
  }
}