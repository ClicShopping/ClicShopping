<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\Registry;

$CLICSHOPPING_Orders = Registry::get('Orders');

$QconditionGeneralOfSales = $CLICSHOPPING_Orders->db->prepare('select page_manager_general_condition
                                                                from :table_orders_pages_manager
                                                                where orders_id = :orders_id
                                                                and customers_id = :customers_id
                                                                ');
$QconditionGeneralOfSales->bindInt(':orders_id', (int)$_GET['order_id']);
$QconditionGeneralOfSales->bindInt(':customers_id', (int)$_GET['customer_id']);
$QconditionGeneralOfSales->execute();

echo $QconditionGeneralOfSales->value('page_manager_general_condition');
