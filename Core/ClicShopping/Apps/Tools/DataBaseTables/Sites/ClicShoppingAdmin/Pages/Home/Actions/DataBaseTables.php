<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\DataBaseTables\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class DataBaseTables extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_DataBaseTables = Registry::get('DataBaseTables');

    $this->page->setFile('data_base_tables.php');

    $CLICSHOPPING_DataBaseTables->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}