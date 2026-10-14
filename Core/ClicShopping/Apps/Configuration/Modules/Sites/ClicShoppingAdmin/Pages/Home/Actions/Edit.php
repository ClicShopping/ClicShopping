<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Modules\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Edit extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Modules = Registry::get('Modules');

    $this->page->setFile('edit.php');
    $this->page->data['action'] = 'Update';

    $CLICSHOPPING_Modules->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}