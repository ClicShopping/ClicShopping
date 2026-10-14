<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Configuration\OrdersStatusInvoice\Sites\ClicShoppingAdmin\Pages\Home\Actions;

use ClicShopping\OM\Registry;

class OrdersStatusInvoice extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_OrdersStatusInvoice = Registry::get('OrdersStatusInvoice');

    $this->page->setFile('orders_status_invoice.php');
    $this->page->data['action'] = 'OrdersStatusInvoice';

    $CLICSHOPPING_OrdersStatusInvoice->loadDefinitions('Sites/ClicShoppingAdmin/OrdersStatusInvoice');
  }
}