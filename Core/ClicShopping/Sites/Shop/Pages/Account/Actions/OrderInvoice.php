<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop\Pages\Account\Actions;

use ClicShopping\Apps\Orders\Orders\Classes\Pdf\OrderPdfRenderer;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;
use function is_null;

class OrderInvoice extends \ClicShopping\OM\Domains\PagesActionsAbstract
{
  public function execute()
  {
    $CLICSHOPPING_Customer = Registry::get('Customer');
    $CLICSHOPPING_NavigationHistory = Registry::get('NavigationHistory');
    $CLICSHOPPING_Db = Registry::get('Db');
    $CLICSHOPPING_Language = Registry::get('Language');
    $CLICSHOPPING_Order = Registry::get('Order');
    $CLICSHOPPING_Hooks = Registry::get('Hooks');

    $CLICSHOPPING_Hooks->call('OrderInvoice', 'PreAction');

    if (!$CLICSHOPPING_Customer->isLoggedOn()) {
      $CLICSHOPPING_NavigationHistory->setSnapshot();
      CLICSHOPPING::redirect(null, 'Account&LogIn');
    }

    $CLICSHOPPING_Language->loadDefinitions('orders_invoice');

    if (!isset($_GET['order_id'])) {
      CLICSHOPPING::redirect(null, 'Account&Main');
    }

    $oID = HTML::sanitize($_GET['order_id']);

    if (is_null($oID)) {
      CLICSHOPPING::redirect(null, 'Account&Main');
    }

    $QordersInfo = $CLICSHOPPING_Db->prepare('select orders_id,
                                                       customers_id
                                                from :table_orders
                                                where orders_id = :orders_id
                                                  and customers_id = :customers_id
                                               ');
    $QordersInfo->bindInt(':orders_id', (int)$oID);
    $QordersInfo->bindInt(':customers_id', (int)$CLICSHOPPING_Customer->getID());
    $QordersInfo->execute();

    if ($QordersInfo->fetch() === false) {
      CLICSHOPPING::redirect(null, 'Account&Main');
    }

    OrderPdfRenderer::invoice((int)$oID, $CLICSHOPPING_Order, OrderPdfRenderer::CONTEXT_SHOP);
  }
}
