<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\OrderAdmin;
use ClicShopping\Apps\Orders\Orders\Classes\Pdf\OrderPdfRenderer;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

$CLICSHOPPING_Orders = Registry::get('Orders');

if (!isset($_GET['oID'])) {
  $CLICSHOPPING_Orders->redirect('Orders');
}

$oID = HTML::sanitize($_GET['oID']);

if (\is_null($oID)) {
  $CLICSHOPPING_Orders->redirect('Orders');
}

$QordersInfo = $CLICSHOPPING_Orders->db->prepare('select orders_id
                                                    from :table_orders
                                                    where orders_id = :orders_id
                                                   ');
$QordersInfo->bindInt(':orders_id', (int)$oID);
$QordersInfo->execute();

if ($QordersInfo->fetch() === false) {
  $CLICSHOPPING_Orders->redirect('Orders');
}

Registry::set('Order', new OrderAdmin($oID));
$order = Registry::get('Order');

OrderPdfRenderer::packingSlip((int)$oID, $order, OrderPdfRenderer::CONTEXT_ADMIN);
