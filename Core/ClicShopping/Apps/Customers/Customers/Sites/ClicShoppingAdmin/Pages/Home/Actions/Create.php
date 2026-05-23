<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Customers\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Create extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customers = Registry::get('Customers');

    $this->page->setFile('create.php');
    $this->page->data['action'] = 'Create';

    $CLICSHOPPING_Customers->loadDefinitions('Sites/ClicShoppingAdmin/customers');
    $CLICSHOPPING_Customers->loadDefinitions('Sites/ClicShoppingAdmin/create');
  }
}