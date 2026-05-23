<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Catalog\Categories\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Move extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Categories = Registry::get('Categories');

    $this->page->setFile('move.php');
    $this->page->data['action'] = 'MoveConfirm';

    $CLICSHOPPING_Categories->loadDefinitions('Sites/ClicShoppingAdmin/Categories');
  }
}