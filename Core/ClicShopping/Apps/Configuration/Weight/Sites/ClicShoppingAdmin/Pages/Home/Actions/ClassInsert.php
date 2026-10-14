<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\Weight\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class ClassInsert extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Weight = Registry::get('Weight');

    $this->page->setFile('class_insert.php');
    $this->page->data['action'] = 'ClassInsert';

    $CLICSHOPPING_Weight->loadDefinitions('Sites/ClicShoppingAdmin/weight');
  }
}