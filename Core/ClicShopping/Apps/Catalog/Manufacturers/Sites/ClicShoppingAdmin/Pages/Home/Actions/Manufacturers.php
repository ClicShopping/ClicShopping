<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Manufacturers\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Manufacturers extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Manufacturers = Registry::get('Manufacturers');

    $this->page->setFile('manufacturers.php');

    $CLICSHOPPING_Manufacturers->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}