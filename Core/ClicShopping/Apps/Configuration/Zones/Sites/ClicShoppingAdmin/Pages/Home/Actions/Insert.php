<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Zones\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Insert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Zones = Registry::get('Zones');

    $this->page->setFile('insert.php');
    $this->page->data['action'] = 'Insert';

    $CLICSHOPPING_Zones->loadDefinitions('Sites/ClicShoppingAdmin/Zones');
  }
}