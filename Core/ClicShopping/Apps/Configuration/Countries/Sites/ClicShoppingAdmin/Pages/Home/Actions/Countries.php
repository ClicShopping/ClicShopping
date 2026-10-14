<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Countries\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Countries extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Countries = Registry::get('Countries');

    $this->page->setFile('countries.php');
    $this->page->data['action'] = 'Countries';

    $CLICSHOPPING_Countries->loadDefinitions('Sites/ClicShoppingAdmin/Countries');
  }
}