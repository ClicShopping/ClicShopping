<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Administrators\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Edit extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Administrators = Registry::get('Administrators');

    $this->page->setFile('edit.php');
    $this->page->data['action'] = 'Update';

    $CLICSHOPPING_Administrators->loadDefinitions('Sites/ClicShoppingAdmin/Administrators');
  }
}