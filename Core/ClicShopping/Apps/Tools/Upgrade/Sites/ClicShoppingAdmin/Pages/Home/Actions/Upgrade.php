<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\Upgrade\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Upgrade extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Upgrade = Registry::get('Upgrade');

    $this->page->setFile('upgrade.php');
    $this->page->data['action'] = 'Upgrade';

    $CLICSHOPPING_Upgrade->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}