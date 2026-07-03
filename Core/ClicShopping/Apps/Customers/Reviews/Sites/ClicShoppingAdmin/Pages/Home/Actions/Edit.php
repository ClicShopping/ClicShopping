<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Reviews\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class Edit extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Reviews = Registry::get('Reviews');

    $this->page->setFile('edit.php');
    $this->page->data['action'] = 'Update';

    $CLICSHOPPING_Reviews->loadDefinitions('Sites/ClicShoppingAdmin/reviews');
  }
}