<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Archive\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Archive extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Archive = Registry::get('Archive');

    $this->page->setFile('archive.php');
    $this->page->data['action'] = 'Archive';

    $CLICSHOPPING_Archive->loadDefinitions('Sites/ClicShoppingAdmin/Archive');
  }
}