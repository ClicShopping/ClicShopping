<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Specials\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Specials extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Specials = Registry::get('Specials');

    $this->page->setFile('specials.php');
    $this->page->data['action'] = 'Specials';

    $CLICSHOPPING_Specials->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}