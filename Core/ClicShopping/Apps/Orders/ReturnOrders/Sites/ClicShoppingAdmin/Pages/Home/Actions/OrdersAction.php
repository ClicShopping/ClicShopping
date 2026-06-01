<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class OrdersAction extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');

    $this->page->setFile('orders_action.php');
    $this->page->data['action'] = 'OrdersAction';

    $CLICSHOPPING_ReturnOrders->loadDefinitions('Sites/ClicShoppingAdmin/ReturnOrders');
  }
}