<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class ReturnOrders extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');

    $this->page->setFile('return_orders.php');
    $this->page->data['action'] = 'ReturnOrders';

    $CLICSHOPPING_ReturnOrders->loadDefinitions('Sites/ClicShoppingAdmin/ReturnOrders');
  }
}