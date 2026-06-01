<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\ReturnOrders\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class OrdersReasonEdit extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_ReturnOrders = Registry::get('ReturnOrders');

    $this->page->setFile('orders_reason_edit.php');
    $this->page->data['action'] = 'OrdersReasonEdit';

    $CLICSHOPPING_ReturnOrders->loadDefinitions('Sites/ClicShoppingAdmin/ReturnOrders');
  }
}