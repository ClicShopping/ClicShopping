<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Marketing\Featured\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Edit extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Featured = Registry::get('Featured');

    $this->page->setFile('edit.php');
    $this->page->data['action'] = 'Edit';

    $CLICSHOPPING_Featured->loadDefinitions('Sites/ClicShoppingAdmin/Featured');
  }
}