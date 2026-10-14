<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\AI\Ecommerce\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Help extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Ecommerce = Registry::get('Ecommerce');

    $this->page->setFile('help.php');
    $this->page->data['action'] = 'Help';

    $CLICSHOPPING_Ecommerce->loadDefinitions('Sites/ClicShoppingAdmin/help');
  }
}
