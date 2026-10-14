<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Administrators\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class delete extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Administrators = Registry::get('Administrators');

    $this->page->setFile('delete.php');
    $this->page->data['action'] = 'Delete';

    $CLICSHOPPING_Administrators->loadDefinitions('Sites/ClicShoppingAdmin/Administrators');
  }
}