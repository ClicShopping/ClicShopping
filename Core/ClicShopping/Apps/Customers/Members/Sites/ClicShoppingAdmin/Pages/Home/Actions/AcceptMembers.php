<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Members\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class AcceptMembers extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Members = Registry::get('Members');

    $this->page->setFile('accept_member.php');

    $CLICSHOPPING_Members->loadDefinitions('Sites/ClicShoppingAdmin/main');
  }
}